<?php
require __DIR__ . '/../inc/bootstrap.php';
$pdo = db();
$primo = !$pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn();
$errore = '';
$ip = ip_client();
$pdo->prepare('DELETE FROM tentativi WHERE quando < ?')->execute([time() - 900]);
$st = $pdo->prepare('SELECT COUNT(*) FROM tentativi WHERE ip = ?'); $st->execute([$ip]);
$bloccato = !$primo && (int)$st->fetchColumn() >= 5; // 5 errori = pausa di 15 minuti

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $pw = $_POST['password'] ?? '';
    if ($bloccato) {
        $errore = 'Troppi tentativi sbagliati. Riprova tra 15 minuti.';
    } elseif ($primo) {
        if (strlen($pw) < 10) $errore = 'La password deve avere almeno 10 caratteri.';
        elseif ($pw !== ($_POST['password2'] ?? '')) $errore = 'Le due password non coincidono.';
        else {
            $pdo->prepare('INSERT INTO admin (id, password) VALUES (1, ?)')->execute([password_hash($pw, PASSWORD_DEFAULT)]);
            session_regenerate_id(true); $_SESSION['admin'] = 1; $_SESSION['attivo'] = time();
            registra('Password admin creata');
            header('Location: index.php'); exit;
        }
    } else {
        usleep(400000); // rallenta i tentativi
        $hash = $pdo->query('SELECT password FROM admin WHERE id = 1')->fetchColumn();
        if ($hash && password_verify($pw, $hash)) {
            $pdo->prepare('DELETE FROM tentativi WHERE ip = ?')->execute([$ip]);
            session_regenerate_id(true); $_SESSION['admin'] = 1; $_SESSION['attivo'] = time();
            registra('Accesso admin');
            header('Location: index.php'); exit;
        }
        $pdo->prepare('INSERT INTO tentativi (ip, quando) VALUES (?, ?)')->execute([$ip, time()]);
        registra('Accesso fallito');
        $errore = 'Password sbagliata.';
    }
}
intestazione('Accesso', '../');
?>
<div class="admin">
<h1><?= $primo ? 'Crea la password admin' : 'Accesso' ?></h1>
<?php if ($errore): ?><p class="errore"><?= e($errore) ?></p><?php endif; ?>
<form method="post" class="campi">
  <?= csrf_campo() ?>
  <label>Password <input type="password" name="password" required autofocus></label>
  <?php if ($primo): ?><label>Ripeti la password <input type="password" name="password2" required></label><?php endif; ?>
  <button class="bottone"><?= $primo ? 'Crea' : 'Entra' ?></button>
</form></div>
<?php piede();
