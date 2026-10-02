<?php
// Struttura comune delle pagine admin: menu laterale, ricerca, messaggi, scorciatoie.
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/file.php';

if (empty($_SESSION['admin'])) { header('Location: login.php'); exit; }
if (time() - ($_SESSION['attivo'] ?? time()) > 8 * 3600) { $_SESSION = []; header('Location: login.php'); exit; }
$_SESSION['attivo'] = time();

function admin_testata(string $titolo, string $voce = ''): void {
    $voci = [
        'dashboard' => ['index.php', '🏠 Dashboard'], 'libri' => ['libri.php', '📚 Libri'],
        'collane' => ['collane.php', '🗂 Collane'], 'pagine' => ['pagine.php', '📄 Pagine'],
        'media' => ['media.php', '🖼 File'], 'impostazioni' => ['impostazioni.php', '⚙️ Impostazioni'],
        'strumenti' => ['strumenti.php', '🧰 Strumenti'], 'log' => ['log.php', '📜 Attività'],
        'password' => ['password.php', '🔑 Password'],
    ];
    $bozze = (int)db()->query("SELECT COUNT(*) FROM libri WHERE pubblicato = 0 AND eliminato IS NULL")->fetchColumn();
    $cestino = (int)db()->query("SELECT COUNT(*) FROM libri WHERE eliminato IS NOT NULL")->fetchColumn();
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e($titolo) . ' · Admin</title><link rel="icon" href="../assets/favicon.png"><link rel="stylesheet" href="../assets/admin.css"></head><body class="adm">'
       . '<aside class="menu" id="menu"><a class="logo" href="index.php"><img src="../assets/emblema.png" width="36" height="36" alt=""> ' . e(impost('nome_sito')) . '</a><nav>';
    foreach ($voci as $k => [$url, $et]) {
        $badge = $k === 'libri' && $bozze ? " <span class=\"badge\">$bozze bozze</span>" : '';
        echo '<a href="' . $url . '"' . ($k === $voce ? ' class="att"' : '') . '>' . $et . $badge . '</a>';
    }
    echo '<a href="libri.php?stato=cestino">🗑 Cestino' . ($cestino ? " <span class=\"badge\">$cestino</span>" : '') . '</a>'
       . '<a href="../index.php" target="_blank">🌐 Vedi il sito ↗</a><a href="logout.php">⎋ Esci</a></nav>'
       . '<button type="button" id="tema" class="tema">◐ Tema</button></aside><div class="corpo"><header class="barra-alta">'
       . '<button type="button" id="apri-menu" aria-label="Menu">☰</button>'
       . '<form action="libri.php" class="ricerca"><input type="search" name="q" id="cerca" placeholder="Cerca un libro…   ( / )" value="' . e($_GET['q'] ?? '') . '"></form>'
       . '<a class="bottone piccolo" href="libro.php">+ Nuovo libro</a></header><main class="contenuto-adm"><h1>' . e($titolo) . '</h1>';
    foreach ($_SESSION['flash'] ?? [] as [$tipo, $msg]) echo '<p class="' . ($tipo === 'ok' ? 'ok' : 'errore') . '">' . e($msg) . '</p>';
    unset($_SESSION['flash']);
}
function admin_piede(): void {
    echo '</main></div><div id="aiuto" hidden><b>Scorciatoie</b><br><kbd>/</kbd> cerca · <kbd>n</kbd> nuovo libro · <kbd>g</kbd> poi <kbd>l</kbd> libri · <kbd>c</kbd> collane · <kbd>d</kbd> dashboard · <kbd>?</kbd> questo aiuto</div><script src="../assets/admin.js"></script></body></html>';
}
function torna(string $url): void { header('Location: ' . $url); exit; }
