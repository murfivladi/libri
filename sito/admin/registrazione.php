<?php
require __DIR__ . '/_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? 'salva';
    if ($az === 'salva') {
        foreach (['titolare_nome', 'titolare_email', 'titolare_indirizzo'] as $k) imposta($k, trim($_POST[$k] ?? ''));
        imposta('recensioni_moderate', empty($_POST['recensioni_moderate']) ? '0' : '1');
        $testo = trim(str_replace("\r\n", "\n", $_POST['privacy_testo'] ?? ''));
        imposta('privacy_testo', $testo === trim(PRIVACY_MODELLO) ? '' : $testo);       // uguale al modello = resta il modello (si aggiorna da solo)
        $apri = !empty($_POST['registrazione_attiva']);
        if ($apri && !titolare_compilato()) { $apri = false; flash('Per aprire le registrazioni scrivi nome e email del titolare del trattamento.', 'errore'); }
        elseif ($apri && !smtp_configurato()) { $apri = false; flash('Per aprire le registrazioni configura prima le email (menu Email).', 'errore'); }
        imposta('registrazione_attiva', $apri ? '1' : '0');
        registra('Privacy e registrazione salvate', $apri ? 'registrazioni APERTE' : 'registrazioni chiuse');
        flash('Salvato.');
    } elseif ($az === 'nuova_versione') {
        $v = (int)privacy_versione() + 1;
        imposta('privacy_versione', (string)$v);
        registra('Nuova versione dell\'informativa', (string)$v);
        flash("Informativa alla versione $v: al prossimo accesso i lettori dovranno accettarla di nuovo.");
    }
    torna('registrazione.php');
}
admin_testata('Privacy e registrazione', 'registrazione');
$n_let = (int)db()->query('SELECT COUNT(*) FROM lettori')->fetchColumn();
?>
<div class="pannello">
  <h2 style="margin-top:0">Prima di aprire le registrazioni</h2>
  <ul class="elenco-controllo">
    <li><?= titolare_compilato() ? '✅' : '❌' ?> <b>Titolare del trattamento</b>: una persona <b>maggiorenne</b> (un genitore, per esempio) che si assume la responsabilità dei dati dei lettori. Non può essere un minorenne.</li>
    <li><?= smtp_configurato() ? '✅' : '❌' ?> <b>Email</b> funzionanti per la conferma dell'indirizzo (<a href="email.php">Email</a>)</li>
    <li><?= impost('registrazione_attiva') === '1' ? '✅ Registrazioni aperte' : '⏸ Registrazioni chiuse' ?></li>
  </ul>
  <p class="muto">Chi si registra lascia email e nome: sono dati personali (GDPR). Il sito raccoglie solo quelli necessari, li protegge, li cancella quando il lettore lo chiede e non li usa per altro. Il testo qui sotto è un modello ragionevole, ma <b>fallo leggere a un adulto</b> prima di aprire.</p>
</div>

<form method="post" data-avvisa>
<?= csrf_campo() ?><input type="hidden" name="azione" value="salva">
<div class="pannello"><h2 style="margin-top:0">Titolare del trattamento</h2><div class="form-griglia">
  <label>Nome e cognome (o ragione sociale) <input type="text" name="titolare_nome" value="<?= e(impost('titolare_nome')) ?>" placeholder="Es. Mario Rossi"></label>
  <label>Email per le richieste sulla privacy <input type="email" name="titolare_email" value="<?= e(impost('titolare_email')) ?>"></label>
  <label class="largo">Indirizzo <small>(facoltativo ma consigliato)</small><input type="text" name="titolare_indirizzo" value="<?= e(impost('titolare_indirizzo')) ?>" placeholder="Via…, Taormina (ME)"></label>
</div></div>

<div class="pannello"><h2 style="margin-top:0">Registrazione e recensioni</h2>
  <label><span><input type="checkbox" name="registrazione_attiva" value="1" <?= impost('registrazione_attiva') === '1' ? 'checked' : '' ?>> Registrazioni dei lettori aperte</span></label>
  <label><span><input type="checkbox" name="recensioni_moderate" value="1" <?= impost('recensioni_moderate') === '1' ? 'checked' : '' ?>> Le recensioni compaiono solo dopo che le approvi (consigliato)</span></label>
</div>

<div class="pannello"><h2 style="margin-top:0">Testo dell'informativa privacy</h2>
  <p class="muto">Si vede in <a href="../privacy.php" target="_blank">privacy.php</a>. Puoi usare **grassetto**, ## titoli, elenchi con «- » e i segnaposto <code>{sito} {titolare} {indirizzo} {email_titolare} {versione} {giorni}</code>. Versione attuale: <b><?= e(privacy_versione()) ?></b>.</p>
  <textarea name="privacy_testo" rows="22" style="width:100%;font-family:inherit"><?= e(trim(impost('privacy_testo')) !== '' ? impost('privacy_testo') : PRIVACY_MODELLO) ?></textarea>
</div>
<div class="salva-barra"><button class="bottone">💾 Salva</button></div>
</form>

<form method="post" class="pannello">
<?= csrf_campo() ?><input type="hidden" name="azione" value="nuova_versione">
<h2 style="margin-top:0">Cambio importante dell'informativa</h2>
<p class="muto">Se cambi il testo in modo che incide sui diritti dei lettori, pubblica una nuova versione: chi ha già un account (<?= $n_let ?>) dovrà accettarla di nuovo al prossimo accesso. I consensi restano registrati con la data e la versione.</p>
<button class="bottone sec" data-conferma="Chiedere a tutti i lettori di accettare di nuovo l'informativa?">Pubblica una nuova versione</button>
</form>
<?php admin_piede();
