<?php
// Avvio comune: sessione, database SQLite, funzioni di aiuto.
const SITO_NOME = 'Vlad Editore';
define('RADICE', dirname(__DIR__));

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . RADICE . '/data/sito.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE IF NOT EXISTS libri (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE NOT NULL,
        titolo TEXT NOT NULL,
        collana TEXT NOT NULL DEFAULT "",
        numero INTEGER NOT NULL DEFAULT 0,
        genere TEXT NOT NULL DEFAULT "",
        trama TEXT NOT NULL DEFAULT "",
        copertina TEXT NOT NULL DEFAULT "",
        pdf TEXT NOT NULL DEFAULT "",
        pubblicato INTEGER NOT NULL DEFAULT 0,
        creato TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS admin (
        id INTEGER PRIMARY KEY, password TEXT NOT NULL
    )');
    return $pdo;
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function slug(string $t): string {
    $t = iconv('UTF-8', 'ASCII//TRANSLIT', $t) ?: $t;
    $t = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $t));
    return trim($t, '-') ?: 'libro';
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_campo(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function csrf_verifica(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('Richiesta non valida (CSRF).');
    }
}

function admin_richiesto(): void {
    if (empty($_SESSION['admin'])) { header('Location: login.php'); exit; }
}

function intestazione(string $titolo, string $prefisso = ''): void {
    $t = e($titolo) . ' · ' . SITO_NOME;
    echo "<!doctype html><html lang=\"it\"><head><meta charset=\"utf-8\">
<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
<title>$t</title><link rel=\"stylesheet\" href=\"{$prefisso}assets/stile.css\"></head><body>
<header class=\"testata\"><a class=\"marchio\" href=\"{$prefisso}index.php\">✦ " . SITO_NOME . "</a>
<nav><a href=\"{$prefisso}index.php\">Catalogo</a><a href=\"{$prefisso}chi-siamo.php\">Chi siamo</a></nav></header><main class=\"contenuto\">";
}
function piede(): void {
    echo '</main><footer class="piede">© ' . date('Y') . ' ' . SITO_NOME . ' · Taormina</footer></body></html>';
}
