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
<label>Email per le notifiche <small>(riceverai una mail a ogni nuovo ordine; vuoto = nessuna)</small><input type="email" name="email" value="<?= e(impost('email')) ?>"></label>
<button class="bottone">Salva</button></form>
<form method="post" class="pannello" style="display:grid;gap:12px;max-width:520px"><?= csrf_campo() ?><input type="hidden" name="azione" value="password"><h2 style="margin:0">Cambia password</h2>
<label>Password attuale <input type="password" name="attuale" required></label>
<label>Nuova password <input type="password" name="nuova" required autocomplete="new-password"></label>
<label>Ripeti <input type="password" name="nuova2" required autocomplete="new-password"></label><button class="bottone">Cambia</button></form>
<?php admin_piede();
