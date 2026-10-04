<?php
// Database SQLite, sessione, sicurezza e struttura delle pagine (stesso stile del sito principale).
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
mb_internal_encoding('UTF-8');

const STATI = ['nuovo' => 'Nuova', 'accordo' => 'In accordo col cliente', 'scrittura' => 'In scrittura', 'consegnato' => 'Consegnato', 'annullato' => 'Annullata'];

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $pdo = new PDO('sqlite:' . "$dir/ordini.sqlite", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS richieste (
        id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, email TEXT NOT NULL, telefono TEXT DEFAULT '',
        per_chi TEXT DEFAULT '', eta TEXT DEFAULT '', occasione TEXT DEFAULT '', genere TEXT DEFAULT '', lingua TEXT DEFAULT 'Italiano',
        formato TEXT DEFAULT 'PDF', pagine TEXT DEFAULT '', storia TEXT DEFAULT '', dedica TEXT DEFAULT '', scadenza TEXT DEFAULT '',
        indirizzo TEXT DEFAULT '', cap TEXT DEFAULT '', citta TEXT DEFAULT '', paese TEXT DEFAULT 'Italia', note TEXT DEFAULT '',
        stato TEXT DEFAULT 'nuovo', note_admin TEXT DEFAULT '', creato TEXT NOT NULL, aggiornato TEXT NOT NULL,
        letto INTEGER DEFAULT 0, ip TEXT DEFAULT ''
    );
    CREATE TABLE IF NOT EXISTS admin (id INTEGER PRIMARY KEY, password TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS tentativi (ip TEXT, quando INTEGER);
    CREATE TABLE IF NOT EXISTS impostazioni (chiave TEXT PRIMARY KEY, valore TEXT);
    ");
    return $pdo;
}

function impost(string $k, string $def = ''): string {
    $st = db()->prepare('SELECT valore FROM impostazioni WHERE chiave = ?'); $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? $def : (string)$v;
}
function salva_impost(string $k, string $v): void {
    db()->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?, ?) ON CONFLICT(chiave) DO UPDATE SET valore = excluded.valore')->execute([$k, $v]);
}

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
       . '<nav><a href="index.php#come">Come funziona</a><a href="index.php#richiesta">Richiedi il tuo libro</a></nav></header><main class="contenuto">';
}
function piede(): void {
    $mail = impost('email');
    echo '</main><footer class="piede" id="contatti">© ' . date('Y') . ' ' . e(impost('nome_sito', 'Vlad Editore'))
       . ($mail ? ' · <a href="mailto:' . e($mail) . '">' . e($mail) . '</a>' : '') . '</footer></body></html>';
}

// Invio via SMTP (impostazioni in admin → Impostazioni). Ritorna '' se ok, altrimenti il messaggio d'errore.
function invia_mail(string $a, string $oggetto, string $testo, string $replyTo = ''): string {
    require_once __DIR__ . '/vendor/phpmailer/Exception.php';
    require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
    require_once __DIR__ . '/vendor/phpmailer/SMTP.php';
    $host = impost('smtp_host');
    if ($host === '') return 'SMTP non configurato.';
    try {
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP(); $m->Host = $host; $m->Port = (int)impost('smtp_porta', '587'); $m->CharSet = 'UTF-8'; $m->Timeout = 15;
        $cif = impost('smtp_cifratura', 'tls');
        $m->SMTPSecure = $cif === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : ($cif === 'tls' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : '');
        $m->SMTPAutoTLS = $cif !== 'nessuna';
        if (impost('smtp_utente') !== '') { $m->SMTPAuth = true; $m->Username = impost('smtp_utente'); $m->Password = impost('smtp_password'); }
        $da = impost('smtp_da') ?: impost('smtp_utente');
        $m->setFrom($da, impost('nome_sito', 'Vlad Editore'));
        $m->addAddress($a);
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $m->addReplyTo($replyTo);
        $m->Subject = $oggetto; $m->Body = $testo;
        $m->send();
        return '';
    } catch (Throwable $x) { return $x->getMessage(); }
}

function invia_notifica(array $r): void {
    $to = impost('email');
    if ($to === '') return;
    $righe = ["Nuova richiesta #{$r['id']}", '', "Nome: {$r['nome']}", "Email: {$r['email']}", "Telefono: {$r['telefono']}", '',
              "Per chi: {$r['per_chi']} ({$r['eta']})", "Occasione: {$r['occasione']}", "Genere: {$r['genere']}", "Lingua: {$r['lingua']}",
              "Formato: {$r['formato']}", "Scadenza: {$r['scadenza']}", '', "Storia:\n{$r['storia']}", '', "Dedica: {$r['dedica']}",
              "Indirizzo: {$r['indirizzo']}, {$r['cap']} {$r['citta']} ({$r['paese']})", "Note: {$r['note']}", '', "Gestiscila nell'admin."];
    $err = invia_mail($to, "Nuova richiesta #{$r['id']} – libro per {$r['per_chi']}", implode("\n", $righe), $r['email']);
    if ($err !== '') error_log('ordini-libri: invio mail fallito: ' . $err);
}
