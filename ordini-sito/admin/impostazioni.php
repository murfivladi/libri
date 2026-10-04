<?php
require __DIR__ . '/_admin.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    if (($_POST['azione'] ?? '') === 'password') {
        $h = db()->query('SELECT password FROM admin WHERE id = 1')->fetchColumn();
        $n = (string)$_POST['nuova'];
        if (!password_verify((string)$_POST['attuale'], $h)) flash('err', 'Password attuale sbagliata.');
        elseif (strlen($n) < 10 || $n !== $_POST['nuova2']) flash('err', 'La nuova password deve avere almeno 10 caratteri e coincidere nei due campi.');
        else { db()->prepare('UPDATE admin SET password = ? WHERE id = 1')->execute([password_hash($n, PASSWORD_DEFAULT)]); flash('ok', 'Password cambiata.'); }
    } elseif (($_POST['azione'] ?? '') === 'smtp') {
        foreach (['smtp_host', 'smtp_porta', 'smtp_cifratura', 'smtp_utente', 'smtp_da'] as $k) salva_impost($k, trim((string)($_POST[$k] ?? '')));
        if ((string)($_POST['smtp_password'] ?? '') !== '') salva_impost('smtp_password', (string)$_POST['smtp_password']);
        if (!empty($_POST['smtp_prova'])) {
            $a = impost('email');
            $r = $a === '' ? 'Indica prima l\'email per le notifiche.' : invia_mail($a, 'Prova SMTP · ordini', "Se leggi questa mail, l'SMTP funziona.");
            flash($r === '' ? 'ok' : 'err', $r === '' ? "Mail di prova inviata a $a." : "Invio fallito: $r");
        } else flash('ok', 'Impostazioni SMTP salvate.');
    } else {
        $mail = trim((string)$_POST['email']);
        if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) flash('err', 'Email non valida.');
        else { salva_impost('email', $mail); salva_impost('nome_sito', trim((string)$_POST['nome_sito']) ?: 'Vlad Editore'); flash('ok', 'Impostazioni salvate.'); }
    }
    vai('impostazioni.php');
}
admin_testata('Impostazioni', 'impostazioni');
?>
<form method="post" class="pannello" style="display:grid;gap:12px;max-width:520px"><?= csrf_campo() ?>
<label>Nome del sito <input type="text" name="nome_sito" value="<?= e(impost('nome_sito', 'Vlad Editore')) ?>"></label>
<label>Email per le notifiche <small>(riceverai una mail a ogni nuovo ordine, via SMTP; vuoto = nessuna)</small><input type="email" name="email" value="<?= e(impost('email')) ?>"></label>
<button class="bottone">Salva</button></form>
<form method="post" class="pannello" style="display:grid;gap:12px;max-width:520px"><?= csrf_campo() ?><input type="hidden" name="azione" value="smtp"><h2 style="margin:0">Invio email (SMTP)</h2>
<label>Server SMTP <input type="text" name="smtp_host" placeholder="smtp.esempio.com" value="<?= e(impost('smtp_host')) ?>"></label>
<div class="riga"><label style="flex:1">Porta <input type="number" name="smtp_porta" value="<?= e(impost('smtp_porta', '587')) ?>"></label>
<label style="flex:2">Cifratura <select name="smtp_cifratura"><?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'nessuna' => 'Nessuna'] as $k => $et): ?><option value="<?= $k ?>" <?= impost('smtp_cifratura', 'tls') === $k ? 'selected' : '' ?>><?= $et ?></option><?php endforeach; ?></select></label></div>
<label>Utente <input type="text" name="smtp_utente" autocomplete="off" value="<?= e(impost('smtp_utente')) ?>"></label>
<label>Password <small><?= impost('smtp_password') !== '' ? '(già salvata: lascia vuoto per non cambiarla)' : '' ?></small><input type="password" name="smtp_password" autocomplete="new-password"></label>
<label>Mittente (From) <small>(vuoto = uguale all'utente)</small><input type="text" name="smtp_da" value="<?= e(impost('smtp_da')) ?>"></label>
<div class="azioni-riga"><button class="bottone">Salva</button><button class="bottone sec" name="smtp_prova" value="1">Salva e invia mail di prova</button></div></form>
<form method="post" class="pannello" style="display:grid;gap:12px;max-width:520px"><?= csrf_campo() ?><input type="hidden" name="azione" value="password"><h2 style="margin:0">Cambia password</h2>
<label>Password attuale <input type="password" name="attuale" required></label>
<label>Nuova password <input type="password" name="nuova" required autocomplete="new-password"></label>
<label>Ripeti <input type="password" name="nuova2" required autocomplete="new-password"></label><button class="bottone">Cambia</button></form>
<?php admin_piede();
