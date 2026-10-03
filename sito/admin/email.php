<?php
require __DIR__ . '/_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? 'salva';
    if ($az === 'salva') {
        imposta('smtp_host', trim($_POST['smtp_host'] ?? ''));
        $porta = (int)($_POST['smtp_porta'] ?? 587);
        imposta('smtp_porta', (string)($porta > 0 && $porta < 65536 ? $porta : 587));
        imposta('smtp_cifratura', in_array($_POST['smtp_cifratura'] ?? '', ['tls', 'ssl', 'nessuna'], true) ? $_POST['smtp_cifratura'] : 'tls');
        imposta('smtp_utente', trim($_POST['smtp_utente'] ?? ''));
        if (($_POST['smtp_password'] ?? '') !== '') imposta('smtp_password', $_POST['smtp_password']);
        if (!empty($_POST['smtp_password_togli'])) imposta('smtp_password', '');
        imposta('email_mittente', trim($_POST['email_mittente'] ?? ''));
        imposta('nome_mittente', trim($_POST['nome_mittente'] ?? ''));
        registra('Impostazioni email salvate');
        flash('Impostazioni email salvate. Fai una prova di invio qui sotto.');
    } elseif ($az === 'prova') {
        $a = trim($_POST['prova_a'] ?? '');
        if (!filter_var($a, FILTER_VALIDATE_EMAIL)) flash('Scrivi un indirizzo email valido per la prova.', 'errore');
        elseif (!smtp_configurato()) flash('Prima compila e salva server e indirizzo mittente.', 'errore');
        else {
            $err = invia_email($a, 'Prova di invio · ' . impost('nome_sito'), "Se leggi questo messaggio, le email del sito funzionano.\nPuoi aprire le registrazioni dei lettori.", url_sito('index.php'), 'Apri il sito');
            if ($err === null) { registra('Email di prova inviata'); flash("Email di prova spedita a $a. Controlla la posta (anche lo spam)."); }
            else flash('Invio non riuscito: ' . $err, 'errore');
        }
    }
    torna('email.php');
}
admin_testata('Email', 'email');
?>
<p class="muto">Il sito spedisce le email ai lettori (conferma dell'indirizzo, password dimenticata) tramite un server SMTP: quello del tuo hosting, di un provider di posta o di un servizio come Brevo o Mailjet. I dati te li dà il servizio. Nessuna newsletter: solo email di servizio.</p>
<form method="post" data-avvisa>
<?= csrf_campo() ?><input type="hidden" name="azione" value="salva">
<div class="pannello"><h2 style="margin-top:0">Server SMTP</h2><div class="form-griglia">
  <label>Server (host) <input type="text" name="smtp_host" value="<?= e(impost('smtp_host')) ?>" placeholder="smtp.esempio.it"></label>
  <label>Porta <input type="number" name="smtp_porta" value="<?= (int)impost('smtp_porta') ?>"></label>
  <label>Cifratura <select name="smtp_cifratura">
    <?php foreach (['tls' => 'STARTTLS (porta 587, consigliata)', 'ssl' => 'SSL/TLS (porta 465)', 'nessuna' => 'Nessuna (solo per prove)'] as $k => $et): ?><option value="<?= $k ?>" <?= impost('smtp_cifratura') === $k ? 'selected' : '' ?>><?= e($et) ?></option><?php endforeach; ?></select></label>
  <label>Utente <input type="text" name="smtp_utente" value="<?= e(impost('smtp_utente')) ?>" autocomplete="off"></label>
  <label>Password <small>(<?= impost('smtp_password') !== '' ? 'salvata: lascia vuoto per non cambiarla' : 'non ancora impostata' ?>)</small><input type="password" name="smtp_password" autocomplete="new-password"></label>
  <label><span>Password</span><span><input type="checkbox" name="smtp_password_togli" value="1"> Cancella la password salvata</span></label>
  <label>Indirizzo mittente <small>(da qui partono le email)</small><input type="email" name="email_mittente" value="<?= e(impost('email_mittente')) ?>" placeholder="no-reply@vladinc.ru"></label>
  <label>Nome del mittente <input type="text" name="nome_mittente" value="<?= e(impost('nome_mittente')) ?>" placeholder="<?= e(impost('nome_sito')) ?>"></label>
</div>
<p class="muto">La password SMTP è salvata nel database (serve per spedire) e <b>non</b> compare nei backup JSON.</p></div>
<div class="salva-barra"><button class="bottone">💾 Salva</button></div>
</form>

<form method="post" class="pannello">
<?= csrf_campo() ?><input type="hidden" name="azione" value="prova">
<h2 style="margin-top:0">Prova di invio</h2>
<div class="riga"><input type="email" name="prova_a" placeholder="la tua email" required><button class="bottone">Spedisci una prova</button></div>
<p class="muto">Stato: <?= smtp_configurato() ? '✅ server e mittente compilati' : '⚠ mancano server o indirizzo mittente' ?>.</p>
</form>
<?php admin_piede();
