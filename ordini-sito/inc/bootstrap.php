<?php
// Database SQLite, sessione, sicurezza e struttura delle pagine (stesso stile del sito principale).
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
mb_internal_encoding('UTF-8');

const STATI = ['nuovo' => 'Nuovo', 'confermato' => 'Confermato', 'spedito' => 'Spedito', 'consegnato' => 'Consegnato', 'annullato' => 'Annullato'];

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $nuovo = !file_exists("$dir/ordini.sqlite");
    $pdo = new PDO('sqlite:' . "$dir/ordini.sqlite", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS libri (
        id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE NOT NULL, titolo TEXT NOT NULL, sottotitolo TEXT DEFAULT '',
        autore TEXT DEFAULT 'Vlad', serie TEXT DEFAULT '', numero INTEGER DEFAULT 0, descrizione TEXT DEFAULT '',
        copertina TEXT DEFAULT '', prezzo REAL DEFAULT 0, ordinabile INTEGER DEFAULT 1, ordine INTEGER DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS ordini (
        id INTEGER PRIMARY KEY AUTOINCREMENT, libro_id INTEGER, titolo_libro TEXT NOT NULL, quantita INTEGER DEFAULT 1,
        prezzo REAL DEFAULT 0, nome TEXT NOT NULL, email TEXT NOT NULL, telefono TEXT DEFAULT '', indirizzo TEXT DEFAULT '',
        cap TEXT DEFAULT '', citta TEXT DEFAULT '', paese TEXT DEFAULT 'Italia', note TEXT DEFAULT '',
        stato TEXT DEFAULT 'nuovo', note_admin TEXT DEFAULT '', creato TEXT NOT NULL, aggiornato TEXT NOT NULL,
        letto INTEGER DEFAULT 0, ip TEXT DEFAULT ''
    );
    CREATE TABLE IF NOT EXISTS admin (id INTEGER PRIMARY KEY, password TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS tentativi (ip TEXT, quando INTEGER);
    CREATE TABLE IF NOT EXISTS impostazioni (chiave TEXT PRIMARY KEY, valore TEXT);
    ");
    if ($nuovo || !$pdo->query('SELECT 1 FROM libri LIMIT 1')->fetchColumn()) semina($pdo);
    return $pdo;
}

// Libri iniziali (serie "Il Custode dell'Etna"); si gestiscono poi dall'admin.
function semina(PDO $pdo): void {
    $libri = [
        ['il-custode-dell-etna', "Il Custode dell'Etna", 'Un ragazzo, un vulcano e una piuma di fuoco', 1, "Seconda media a Trappitello, con vista sull'Etna. Vlad scopre di essere il custode di un antico segreto: sotto il vulcano si muove Tifeo, il gigante del mito, e una Piuma di Zhar-ptitsa custodita dalla nonna può fermarlo. Avventura, mistero e un pizzico di paura.", 'assets/cover/custode-1.jpg'],
        ['il-custode-e-il-fantasma-del-teatro', 'Il Custode e il Fantasma del Teatro', 'Libro 2', 2, 'Un teatro antico, una presenza che non trova pace e un mistero da risolvere. Il secondo capitolo delle avventure del Custode dell\'Etna.', 'assets/cover/custode-2.jpg'],
        ['il-custode-e-la-lira-del-mare', 'Il Custode e la Lira del Mare', 'Libro 3', 3, 'Dal vulcano al mare: una lira leggendaria, sirene e correnti che nascondono antichi segreti. Fantasy e avventura.', 'assets/cover/custode-3.jpg'],
        ['il-custode-e-l-isola-dell-immortale', "Il Custode e l'Isola dell'Immortale", 'Libro 4', 4, 'Un viaggio nelle fiabe russe, tra Baba Yaga e l\'isola dove abita l\'Immortale.', 'assets/cover/custode-4.jpg'],
        ['il-custode-e-l-inverno-eterno', "Il Custode e l'Inverno Eterno", 'Libro 5 · il finale', 5, 'Il gelo non vuole finire. Il gran finale della serie del Custode dell\'Etna.', 'assets/cover/custode-5.jpg'],
    ];
    $st = $pdo->prepare('INSERT INTO libri (slug, titolo, sottotitolo, autore, serie, numero, descrizione, copertina, prezzo, ordine) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($libri as $i => [$slug, $t, $sub, $n, $d, $c]) $st->execute([$slug, $t, $sub, 'Vlad', "Il Custode dell'Etna", $n, $d, $c, 0, $i + 1]);
}

function impost(string $k, string $def = ''): string {
    $st = db()->prepare('SELECT valore FROM impostazioni WHERE chiave = ?'); $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? $def : (string)$v;
}
function salva_impost(string $k, string $v): void {
    db()->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?, ?) ON CONFLICT(chiave) DO UPDATE SET valore = excluded.valore')->execute([$k, $v]);
}

function euro(float $n): string { return $n > 0 ? '€ ' . number_format($n, 2, ',', '.') : 'Prezzo su richiesta'; }

function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_campo(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verifica(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Sessione scaduta: torna indietro e riprova.'); }
}
function ip_client(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; }
function flash(string $tipo, string $msg): void { $_SESSION['flash'][] = [$tipo, $msg]; }
function vai(string $url): void { header('Location: ' . $url); exit; }

function intestazione(string $titolo, string $descrizione = ''): void {
    $nome = impost('nome_sito', 'Vlad Editore');
    $d = $descrizione ? '<meta name="description" content="' . e($descrizione) . '">' : '';
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e($titolo) . ' · ' . e($nome) . '</title>' . $d
       . '<link rel="icon" type="image/png" href="assets/favicon.png"><link rel="stylesheet" href="assets/stile.css"><link rel="stylesheet" href="assets/ordini.css"></head><body>'
       . '<header class="testata"><a class="marchio" href="index.php"><img src="assets/emblema.png" alt="" width="44" height="44"><span>' . e(strtoupper($nome)) . '</span></a>'
       . '<nav><a href="index.php">Catalogo</a><a href="index.php#contatti">Contatti</a></nav></header><main class="contenuto">';
}
function piede(): void {
    $mail = impost('email');
    echo '</main><footer class="piede" id="contatti">© ' . date('Y') . ' ' . e(impost('nome_sito', 'Vlad Editore'))
       . ($mail ? ' · <a href="mailto:' . e($mail) . '">' . e($mail) . '</a>' : '') . '</footer></body></html>';
}

function slug_da(string $s): string {
    $s = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s)), '-'));
    return $s !== '' ? $s : 'libro';
}

function invia_notifica(array $o): void {
    $to = impost('email');
    if ($to === '' || !function_exists('mail')) return;
    $righe = ["Nuovo ordine #{$o['id']}", '', "Libro: {$o['titolo_libro']} × {$o['quantita']}", "Nome: {$o['nome']}", "Email: {$o['email']}",
              "Telefono: {$o['telefono']}", "Indirizzo: {$o['indirizzo']}, {$o['cap']} {$o['citta']} ({$o['paese']})", "Note: {$o['note']}"];
    $schiva = fn($s) => str_replace(["\r", "\n"], ' ', $s);
    @mail($to, '=?UTF-8?B?' . base64_encode("Nuovo ordine #{$o['id']} – {$o['titolo_libro']}") . '?=', implode("\n", $righe),
          "Content-Type: text/plain; charset=UTF-8\r\nReply-To: " . $schiva($o['email']));
}
