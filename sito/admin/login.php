<?php
require __DIR__ . '/../inc/sicurezza.php';
$pdo = db();
$primo = !$pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn();
$errore = $info = '';
$nuovo_recupero = '';
$ip = ip_client();

if (!$primo && ($m = applica_reset_da_file())) $info = $m;

$pdo->prepare('DELETE FROM tentativi WHERE quando < ?')->execute([time() - 900]);
$st = $pdo->prepare('SELECT COUNT(*) FROM tentativi WHERE ip = ?'); $st->execute([$ip]);
$bloccato = !$primo && (int)$st->fetchColumn() >= 5; // 5 errori = pausa di 15 minuti

$admin = $primo ? [] : dati_admin();
$modo = 'password';
if (!$primo && isset($_GET['recupero'])) $modo = 'recupero';
if (!$primo && !empty($admin['totp_attivo']) && !empty($_SESSION['pre2fa']) && time() - $_SESSION['pre2fa'] < 300) $modo = '2fa';

function accedi(string $come): void {
    db()->prepare('DELETE FROM tentativi WHERE ip = ?')->execute([ip_client()]);
    unset($_SESSION['pre2fa']);
    session_regenerate_id(true);
    $_SESSION['admin'] = 1; $_SESSION['attivo'] = time();
    registra($come);
    header('Location: index.php'); exit;
}
function errore_accesso(string $azione): void {
    db()->prepare('INSERT INTO tentativi (ip, quando) VALUES (?, ?)')->execute([ip_client(), time()]);
    registra($azione);
}

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
            $_SESSION['recupero_mostra'] = imposta_codice_recupero();   // si vede una volta sola, in "Sicurezza"
            registra('Password admin creata');
            session_regenerate_id(true); $_SESSION['admin'] = 1; $_SESSION['attivo'] = time();
            header('Location: sicurezza.php'); exit;
        }
    } elseif ($modo === 'recupero') {
        usleep(400000);
        $nuova = $_POST['nuova'] ?? '';
        if (!verifica_codice_recupero($_POST['codice'] ?? '')) { errore_accesso('Recupero password fallito'); $errore = 'Codice di recupero sbagliato.'; }
        elseif (strlen($nuova) < 10) $errore = 'La nuova password deve avere almeno 10 caratteri.';
        elseif ($nuova !== ($_POST['nuova2'] ?? '')) $errore = 'Le due password non coincidono.';
        else {
            $pdo->prepare('UPDATE admin SET password = ? WHERE id = 1')->execute([password_hash($nuova, PASSWORD_DEFAULT)]);
            $nuovo_recupero = imposta_codice_recupero();                 // il vecchio codice è usato: ne serve uno nuovo
            registra('Password reimpostata con il codice di recupero');
            $modo = 'fatto';
        }
    } elseif ($modo === '2fa') {
        usleep(300000);
        $codice = $_POST['codice'] ?? '';
        if (totp_verifica($admin['totp_segreto'], $codice)) accedi('Accesso admin (2FA)');
        if (usa_codice_backup($codice)) accedi('Accesso admin (codice di backup)');
        errore_accesso('Codice 2FA sbagliato'); $errore = 'Codice sbagliato.';
    } else {
        usleep(400000); // rallenta i tentativi
        if (!empty($admin['password']) && password_verify($pw, $admin['password'])) {
            if (!empty($admin['totp_attivo'])) { $_SESSION['pre2fa'] = time(); header('Location: login.php'); exit; }
            accedi('Accesso admin');
        }
        errore_accesso('Accesso fallito');
        $errore = 'Password sbagliata.';
    }
}
intestazione('Accesso', '../');
?>
<div class="admin">
<?php if ($info): ?><p class="avviso"><?= e($info) ?></p><?php endif; ?>
<?php if ($modo === 'fatto'): ?>
  <h1>Password cambiata</h1>
  <p class="ok">Ora puoi <a href="login.php">accedere con la nuova password</a>.</p>
  <p><b>Il tuo nuovo codice di recupero</b> (il vecchio non vale più). Scrivilo e tienilo al sicuro, non verrà mostrato di nuovo:</p>
  <p><code style="font-size:1.4rem;letter-spacing:.05em"><?= e($nuovo_recupero) ?></code></p>
<?php else: ?>
<h1><?= $primo ? 'Crea la password admin' : ($modo === 'recupero' ? 'Recupera la password' : ($modo === '2fa' ? 'Verifica in due passaggi' : 'Accesso')) ?></h1>
<?php if ($errore): ?><p class="errore"><?= e($errore) ?></p><?php endif; ?>
<form method="post" class="campi">
  <?= csrf_campo() ?>
  <?php if ($modo === 'recupero'): ?>
    <label>Codice di recupero <input type="text" name="codice" required autofocus autocomplete="off" placeholder="XXXXX-XXXXX-XXXXX-XXXXX"></label>
    <label>Nuova password <small>(almeno 10 caratteri)</small><input type="password" name="nuova" required autocomplete="new-password"></label>
    <label>Ripeti la nuova password <input type="password" name="nuova2" required autocomplete="new-password"></label>
    <button class="bottone">Reimposta la password</button>
    <p><a href="login.php">← Torna all'accesso</a></p>
  <?php elseif ($modo === '2fa'): ?>
    <p>Apri l'app di autenticazione e scrivi il codice a 6 cifre. Se hai perso il telefono, usa un codice di backup (formato <code>abcd-1234</code>).</p>
    <label>Codice <input type="text" name="codice" required autofocus autocomplete="one-time-code" inputmode="text"></label>
    <button class="bottone">Conferma</button>
  <?php else: ?>
    <label>Password <input type="password" name="password" required autofocus autocomplete="<?= $primo ? 'new-password' : 'current-password' ?>"></label>
    <?php if ($primo): ?><label>Ripeti la password <input type="password" name="password2" required autocomplete="new-password"></label><?php endif; ?>
    <button class="bottone"><?= $primo ? 'Crea' : 'Entra' ?></button>
    <?php if (!$primo): ?><p><a href="login.php?recupero=1">Password dimenticata?</a></p>
    <?php else: ?><p class="muto">Hai due siti che devono mostrare gli stessi libri? Prima <a href="database.php">collega il database MySQL condiviso</a>, poi crea la password.</p><?php endif; ?>
  <?php endif; ?>
</form>
<?php endif; ?>
</div>
<?php piede();
