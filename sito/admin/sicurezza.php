<?php
require __DIR__ . '/_admin.php';
require __DIR__ . '/../inc/sicurezza.php';

$a = dati_admin();
$pw_ok = fn() => password_verify($_POST['password'] ?? '', (string)$a['password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? '';
    if ($az === 'recupero_nuovo') {
        if (!$pw_ok()) flash('Password sbagliata.', 'errore');
        else { $_SESSION['recupero_mostra'] = imposta_codice_recupero(); registra('Nuovo codice di recupero'); }
    } elseif ($az === '2fa_inizia') {
        db()->prepare("UPDATE admin SET totp_segreto = ?, totp_attivo = 0 WHERE id = 1")->execute([totp_segreto_nuovo()]);
    } elseif ($az === '2fa_conferma') {
        if (totp_verifica((string)$a['totp_segreto'], $_POST['codice'] ?? '')) {
            db()->exec('UPDATE admin SET totp_attivo = 1 WHERE id = 1');
            $_SESSION['backup_mostra'] = codici_backup_nuovi();
            registra('Verifica in due passaggi attivata');
            flash('Verifica in due passaggi attivata.');
        } else flash('Codice sbagliato: controlla l\'ora del telefono e riprova.', 'errore');
    } elseif ($az === '2fa_backup') {
        if (!$pw_ok()) flash('Password sbagliata.', 'errore');
        else { $_SESSION['backup_mostra'] = codici_backup_nuovi(); registra('Nuovi codici di backup 2FA'); }
    } elseif ($az === '2fa_disattiva') {
        if (!$pw_ok()) flash('Password sbagliata.', 'errore');
        elseif (!totp_verifica((string)$a['totp_segreto'], $_POST['codice'] ?? '') && !usa_codice_backup($_POST['codice'] ?? '')) flash('Codice sbagliato.', 'errore');
        else {
            db()->exec("UPDATE admin SET totp_attivo = 0, totp_segreto = '', codici_backup = '[]' WHERE id = 1");
            registra('Verifica in due passaggi disattivata'); flash('Verifica in due passaggi disattivata.');
        }
    } elseif ($az === '2fa_annulla') {
        db()->exec("UPDATE admin SET totp_attivo = 0, totp_segreto = '' WHERE id = 1");
    }
    torna('sicurezza.php');
}

$recupero_mostra = $_SESSION['recupero_mostra'] ?? null; unset($_SESSION['recupero_mostra']);
$backup_mostra = $_SESSION['backup_mostra'] ?? null; unset($_SESSION['backup_mostra']);
$attivo = !empty($a['totp_attivo']);
$in_setup = !$attivo && $a['totp_segreto'] !== '';
$ha_recupero = $a['recupero'] !== '';

admin_testata('Sicurezza', 'sicurezza');
?>
<?php if ($recupero_mostra): ?>
<div class="pannello" style="border-color:#d99a00">
  <h2 style="margin-top:0">🔑 Il tuo codice di recupero</h2>
  <p>Serve se dimentichi la password (dalla pagina di accesso: «Password dimenticata?»). <b>Scrivilo su carta o salvalo in un posto sicuro: non verrà mostrato di nuovo.</b></p>
  <p><code style="font-size:1.5rem;letter-spacing:.05em"><?= e($recupero_mostra) ?></code></p>
</div>
<?php endif; ?>
<?php if ($backup_mostra): ?>
<div class="pannello" style="border-color:#d99a00">
  <h2 style="margin-top:0">🛟 Codici di backup</h2>
  <p>Ognuno vale <b>una sola volta</b> al posto del codice dell'app, se perdi il telefono. Salvali ora: non verranno mostrati di nuovo.</p>
  <pre class="codice"><?= e(implode("\n", $backup_mostra)) ?></pre>
</div>
<?php endif; ?>

<div class="pannello">
  <h2 style="margin-top:0">Verifica in due passaggi (2FA)</h2>
  <?php if ($attivo): ?>
    <p class="ok">Attiva. Al login, oltre alla password, serve il codice dell'app. Codici di backup rimasti: <b><?= codici_backup_rimasti() ?></b>.</p>
    <form method="post" class="form-griglia" style="max-width:640px"><?= csrf_campo() ?>
      <label>Password attuale <input type="password" name="password" autocomplete="current-password"></label>
      <label>Codice dell'app o di backup <small>(solo per disattivare)</small><input type="text" name="codice" autocomplete="off"></label>
      <p class="azioni-riga largo">
        <button class="bottone sec piccolo" name="azione" value="2fa_backup" data-conferma="I vecchi codici di backup smetteranno di funzionare. Continuare?">Genera nuovi codici di backup</button>
        <button class="bottone pericolo piccolo" name="azione" value="2fa_disattiva" data-conferma="Disattivare la verifica in due passaggi?">Disattiva 2FA</button></p>
    </form>
  <?php elseif ($in_setup): ?>
    <p><b>1.</b> Apri un'app di autenticazione (Google Authenticator, Microsoft Authenticator, Aegis, Authy…) e inquadra il QR code.</p>
    <div id="qr" style="margin:10px 0;background:#fff;display:inline-block;padding:10px;border-radius:8px"></div>
    <p class="muto">Non riesci a inquadrare? Inserisci a mano questa chiave: <code><?= e(chunk_split($a['totp_segreto'], 4, ' ')) ?></code></p>
    <p><b>2.</b> Scrivi qui il codice a 6 cifre che vedi nell'app:</p>
    <form method="post" class="riga" style="max-width:360px"><?= csrf_campo() ?>
      <input type="text" name="codice" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="123456">
      <button class="bottone" name="azione" value="2fa_conferma">Attiva</button>
      <button class="bottone sec" name="azione" value="2fa_annulla">Annulla</button>
    </form>
    <script src="../assets/vendor/qrcode.min.js"></script>
    <script>new QRCode(document.getElementById('qr'), { text: <?= json_encode(totp_uri($a['totp_segreto'], 'admin', impost('nome_sito'))) ?>, width: 200, height: 200 });</script>
  <?php else: ?>
    <p>Aggiunge un secondo controllo al login: oltre alla password serve un codice che cambia ogni 30 secondi, generato dal tuo telefono. Anche se qualcuno scopre la password, non può entrare.</p>
    <form method="post"><?= csrf_campo() ?><button class="bottone" name="azione" value="2fa_inizia">Attiva la verifica in due passaggi</button></form>
  <?php endif; ?>
</div>

<div class="pannello">
  <h2 style="margin-top:0">Codice di recupero della password</h2>
  <?php if ($ha_recupero): ?><p class="ok">Hai un codice di recupero. Per cambiarlo (il vecchio smette di funzionare) inserisci la password:</p>
  <?php else: ?><p class="errore">Non hai ancora un codice di recupero: se dimentichi la password non potrai rientrare da solo. Generalo ora.</p><?php endif; ?>
  <form method="post" class="riga" style="max-width:520px"><?= csrf_campo() ?>
    <input type="password" name="password" placeholder="Password attuale" autocomplete="current-password" required>
    <button class="bottone sec" name="azione" value="recupero_nuovo"><?= $ha_recupero ? 'Genera un nuovo codice' : 'Genera il codice' ?></button>
  </form>
  <p class="muto">Recupero d'emergenza via FTP: se perdi anche il codice, crea nella cartella <code>data/</code> del sito un file <code>reset-password.txt</code> che contiene la nuova password (o <code>reset-2fa.txt</code>, anche vuoto, per spegnere il 2FA), poi apri la pagina di accesso: il file viene usato e cancellato.</p>
</div>
<?php admin_piede();
