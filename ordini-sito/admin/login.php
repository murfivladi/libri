<?php
require __DIR__ . '/../inc/bootstrap.php';
$pdo = db();
$primo = !$pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn();
$ip = ip_client(); $err = '';
$pdo->prepare('DELETE FROM tentativi WHERE quando < ?')->execute([time() - 900]);
$st = $pdo->prepare('SELECT COUNT(*) FROM tentativi WHERE ip = ?'); $st->execute([$ip]);
$bloccato = !$primo && (int)$st->fetchColumn() >= 5;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $pw = (string)($_POST['password'] ?? '');
    if ($bloccato) $err = 'Troppi tentativi sbagliati. Riprova tra 15 minuti.';
    elseif ($primo) {
        if (strlen($pw) < 10) $err = 'La password deve avere almeno 10 caratteri.';
        elseif ($pw !== ($_POST['password2'] ?? '')) $err = 'Le due password non coincidono.';
        else {
            $pdo->prepare('INSERT INTO admin (id, password) VALUES (1, ?)')->execute([password_hash($pw, PASSWORD_DEFAULT)]);
            session_regenerate_id(true); $_SESSION['admin'] = 1; $_SESSION['attivo'] = time(); vai('index.php');
        }
    } else {
        usleep(400000);
        $h = $pdo->query('SELECT password FROM admin WHERE id = 1')->fetchColumn();
        if ($h && password_verify($pw, $h)) {
            $pdo->prepare('DELETE FROM tentativi WHERE ip = ?')->execute([$ip]);
            session_regenerate_id(true); $_SESSION['admin'] = 1; $_SESSION['attivo'] = time(); vai('index.php');
        }
        $pdo->prepare('INSERT INTO tentativi (ip, quando) VALUES (?, ?)')->execute([$ip, time()]);
        $err = 'Password sbagliata.';
    }
}
?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Accesso · Admin</title><link rel="icon" href="../assets/favicon.png"><link rel="stylesheet" href="../assets/admin.css"></head>
<body class="adm" style="justify-content:center;align-items:center">
<div class="pannello" style="width:min(380px,92vw)">
<p style="text-align:center"><img src="../assets/emblema.png" width="64" height="64" alt="" style="border-radius:50%"></p>
<h1 style="font-size:1.3rem;text-align:center"><?= $primo ? 'Crea la password admin' : 'Accesso admin' ?></h1>
<?php if ($err): ?><p class="errore"><?= e($err) ?></p><?php endif; ?>
<form method="post" style="display:grid;gap:12px"><?= csrf_campo() ?>
<label>Password <input type="password" name="password" required autofocus autocomplete="<?= $primo ? 'new-password' : 'current-password' ?>"></label>
<?php if ($primo): ?><label>Ripeti la password <input type="password" name="password2" required autocomplete="new-password"></label><p class="muto">Almeno 10 caratteri.</p><?php endif; ?>
<button class="bottone"><?= $primo ? 'Crea' : 'Entra' ?></button></form></div></body></html>
