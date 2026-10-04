<?php
require_once __DIR__ . '/../inc/bootstrap.php';
if (empty($_SESSION['admin'])) vai('login.php');
if (time() - ($_SESSION['attivo'] ?? time()) > 8 * 3600) { $_SESSION = []; vai('login.php'); }
$_SESSION['attivo'] = time();

function admin_testata(string $titolo, string $voce = ''): void {
    $nuovi = (int)db()->query("SELECT COUNT(*) FROM ordini WHERE letto = 0")->fetchColumn();
    $voci = ['ordini' => ['index.php', '📦 Ordini'], 'libri' => ['libri.php', '📚 Libri'], 'impostazioni' => ['impostazioni.php', '⚙️ Impostazioni']];
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($titolo)
       . ' · Admin</title><link rel="icon" href="../assets/favicon.png"><link rel="stylesheet" href="../assets/admin.css"></head><body class="adm">'
       . '<aside class="menu" id="menu"><a class="logo" href="index.php"><img src="../assets/emblema.png" width="36" height="36" alt=""> ' . e(impost('nome_sito', 'Vlad Editore')) . '</a><nav>';
    foreach ($voci as $k => [$url, $et])
        echo '<a href="' . $url . '"' . ($k === $voce ? ' class="att"' : '') . '>' . $et . ($k === 'ordini' && $nuovi ? " <span class=\"badge\">$nuovi nuovi</span>" : '') . '</a>';
    echo '<a href="../index.php" target="_blank">🌐 Vedi il sito ↗</a><a href="logout.php">⎋ Esci</a></nav></aside>'
       . '<div class="corpo"><main class="contenuto-adm"><h1>' . e($titolo) . '</h1>';
    foreach ($_SESSION['flash'] ?? [] as [$tipo, $msg]) echo '<p class="' . ($tipo === 'ok' ? 'ok' : 'errore') . '">' . e($msg) . '</p>';
    unset($_SESSION['flash']);
}
function admin_piede(): void { echo '</main></div></body></html>'; }
