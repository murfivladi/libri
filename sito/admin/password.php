<?php
require __DIR__ . '/_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $hash = db()->query('SELECT password FROM admin WHERE id = 1')->fetchColumn();
    $nuova = $_POST['nuova'] ?? '';
    if (!password_verify($_POST['attuale'] ?? '', (string)$hash)) flash('La password attuale è sbagliata.', 'errore');
    elseif (strlen($nuova) < 10) flash('La nuova password deve avere almeno 10 caratteri.', 'errore');
    elseif ($nuova !== ($_POST['nuova2'] ?? '')) flash('Le due nuove password non coincidono.', 'errore');
    else {
        db()->prepare('UPDATE admin SET password = ? WHERE id = 1')->execute([password_hash($nuova, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        registra('Password cambiata');
        flash('Password cambiata.');
    }
    torna('password.php');
}
admin_testata('Cambia password', 'password');
?>
<form method="post" class="pannello" style="max-width:480px">
<?= csrf_campo() ?>
<div class="form-griglia">
  <label class="largo">Password attuale <input type="password" name="attuale" required autocomplete="current-password"></label>
  <label class="largo">Nuova password <small>(almeno 10 caratteri)</small><input type="password" name="nuova" required autocomplete="new-password"></label>
  <label class="largo">Ripeti la nuova password <input type="password" name="nuova2" required autocomplete="new-password"></label>
</div>
<div class="salva-barra"><button class="bottone">Cambia password</button></div>
</form>
<?php admin_piede();
