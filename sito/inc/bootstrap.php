<?php
// Avvio comune: sessione, database (SQLite o MySQL), impostazioni, funzioni di aiuto.
if (!defined('RADICE')) define('RADICE', dirname(__DIR__));
date_default_timezone_set('Europe/Rome');

// Gli endpoint di servizio (immagini, PDF, API) non hanno bisogno della sessione: così non mandano cookie.
if (!defined('SENZA_SESSIONE')) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/file.php';

function collana_id_per_nome(string $nome, ?PDO $p = null): int {
    $p = $p ?: db();
    $nome = trim($nome);
    if ($nome === '') return 0;
    $st = $p->prepare('SELECT id FROM collane WHERE nome = ?'); $st->execute([$nome]);
    if ($id = $st->fetchColumn()) return (int)$id;
    $base = $s = slug($nome); $i = 2;
    $chk = $p->prepare('SELECT 1 FROM collane WHERE slug = ?');
    while ($chk->execute([$s]) && $chk->fetchColumn()) $s = $base . '-' . $i++;
    $ordine = (int)$p->query('SELECT COALESCE(MAX(ordine), 0) + 1 FROM collane')->fetchColumn();
    $p->prepare('INSERT INTO collane (slug, nome, ordine) VALUES (?,?,?)')->execute([$s, $nome, $ordine]);
    return (int)$p->lastInsertId();
}

// ---------- impostazioni ----------
const IMPOSTAZIONI_PREDEFINITE = [
    'nome_sito' => 'Vlad Editore', 'slogan' => 'Storie di fantasy e fantascienza',
    'sottoslogan' => "Dalle pendici dell'Etna, libri scritti da Vlad.",
    'footer' => 'Taormina · Sicilia', 'email' => '', 'autore_predefinito' => 'Vlad',
    'per_pagina' => '12', 'ordine_catalogo' => 'numero', 'contatori_pubblici' => '0',
    'manutenzione' => '0', 'messaggio_manutenzione' => 'Il sito è in manutenzione. Torna presto!',
    'mostra_evidenza' => '1',
    'colore_blu' => '#0b1a36', 'colore_oro' => '#c9a14a', 'logo_emblema' => '', 'logo_grande' => '', 'banner_home' => '',
];
function impost(string $k): string {
    static $cache = null;
    if ($cache === null) {
        $cache = IMPOSTAZIONI_PREDEFINITE;
        foreach (db()->query('SELECT chiave, valore FROM impostazioni') as $r) $cache[$r['chiave']] = $r['valore'];
    }
    return (string)($cache[$k] ?? '');
}
function imposta(string $k, string $v): void {
    $sql = db_driver() === 'mysql'
        ? 'INSERT INTO impostazioni (chiave, valore) VALUES (?,?) ON DUPLICATE KEY UPDATE valore = VALUES(valore)'
        : 'INSERT INTO impostazioni (chiave, valore) VALUES (?,?) ON CONFLICT(chiave) DO UPDATE SET valore = excluded.valore';
    db()->prepare($sql)->execute([$k, $v]);
}

// ---------- utilità ----------
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function slug(string $t): string {
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $t) ?: $t;
    $t = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $t));
    return trim($t, '-') ?: 'x';
}
function slug_unico(string $tabella, string $voluto, int $escludiId = 0): string {
    $base = $s = slug($voluto); $i = 2;
    $st = db()->prepare("SELECT 1 FROM $tabella WHERE slug = ? AND id != ?");
    while ($st->execute([$s, $escludiId]) && $st->fetchColumn()) $s = $base . '-' . $i++;
    return $s;
}

// Mini-formattazione sicura: **grassetto**, *corsivo*, # titoli, [testo](https://link), paragrafi.
function md(string $t): string {
    $out = [];
    foreach (preg_split('/\R{2,}/', trim($t)) as $blocco) {
        $b = e(trim($blocco));
        if ($b === '') continue;
        $b = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $b);
        $b = preg_replace('/(?<!\*)\*(?!\*)(.+?)\*/s', '<em>$1</em>', $b);
        $b = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" rel="noopener">$1</a>', $b);
        if (preg_match('/^(#{1,3})\s+(.*)$/s', $b, $m)) { $n = strlen($m[1]) + 1; $out[] = "<h$n>{$m[2]}</h$n>"; }
        else $out[] = '<p>' . nl2br($b) . '</p>';
    }
    return implode("\n", $out);
}

function tags_di(string $s): array {
    $t = array_filter(array_map('trim', explode(',', $s)), 'strlen');
    return array_values(array_unique($t));
}

function sql_visibile(string $a = ''): string {
    $a = $a ? $a . '.' : '';
    $ora = date('Y-m-d H:i:s');
    return "({$a}pubblicato = 1 AND {$a}eliminato IS NULL AND ({$a}pubblica_il = '' OR {$a}pubblica_il <= '$ora'))";
}

function ip_client(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45); }

function registra(string $azione, string $dettaglio = ''): void {
    try { db()->prepare('INSERT INTO log (quando, azione, dettaglio, ip) VALUES (?,?,?,?)')->execute([date('Y-m-d H:i:s'), $azione, $dettaglio, ip_client()]); } catch (Throwable $e) {}
}

function flash(string $msg, string $tipo = 'ok'): void { $_SESSION['flash'][] = [$tipo, $msg]; }

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_campo(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function csrf_verifica(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Richiesta non valida (CSRF).'); }
}

function formatta_byte(int $b): string {
    foreach (['B', 'KB', 'MB', 'GB'] as $i => $u) { if ($b < 1024 || $i === 3) return ($i ? round($b, 1) : $b) . ' ' . $u; $b /= 1024; }
    return '';
}
function formatta_data(?string $d): string { return $d ? date('d/m/Y H:i', strtotime($d)) : '—'; }

// Manutenzione: i visitatori vedono un avviso, l'admin loggato no.
if (!defined('SENZA_SESSIONE') && !defined('SENZA_DB') && impost('manutenzione') === '1' && empty($_SESSION['admin']) && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') === false && PHP_SAPI !== 'cli') {
    http_response_code(503); header('Retry-After: 3600');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e(impost('nome_sito')) . '</title>'
       . '<body style="font:18px Georgia,serif;background:#0b1a36;color:#f3e7c6;display:grid;place-items:center;min-height:100vh;text-align:center;margin:0"><div><h1>' . e(impost('nome_sito')) . '</h1><p>' . e(impost('messaggio_manutenzione')) . '</p></div>';
    exit;
}

// ---------- indirizzi ----------
// Indirizzo di questo sito (con la barra finale), anche quando si è nell'admin.
function percorso_pubblico(): string {
    $schema = (!empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    $cartella = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $cartella = preg_replace('#/admin$#', '', $cartella);
    return $schema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $cartella . '/';
}
// Indirizzo "ufficiale" (per Google, sitemap, link condivisi): se due siti mostrano gli stessi dati, uno solo è l'originale.
function base_ufficiale(): string {
    $u = rtrim((string)(config()['url_ufficiale'] ?? ''), '/');
    return $u !== '' ? $u . '/' : percorso_pubblico();
}
function assoluto(string $url): string { return e_assoluto($url) ? $url : base_ufficiale() . ltrim($url, '/'); }

// ---------- parti comuni del sito pubblico ----------
// $og: ['immagine' => indirizzo, 'tipo' => 'book', 'canonico' => 'libro.php?s=...'] per le anteprime sui social.
function intestazione(string $titolo, string $prefisso = '', string $descrizione = '', array $og = []): void {
    $nome = impost('nome_sito');
    $t = e($titolo) . ' · ' . e($nome);
    $descr = mb_substr(trim(preg_replace('/\s+/', ' ', $descrizione)), 0, 200);
    $extra = '';
    if (!$prefisso) {                                  // anteprime social (Open Graph) e indirizzo ufficiale solo sul sito pubblico
        $url = base_ufficiale() . ($og['canonico'] ?? ltrim(basename($_SERVER['SCRIPT_NAME']) . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''), '/'));
        $img = assoluto($og['immagine'] ?? asset_banner());
        $extra = '<meta property="og:site_name" content="' . e($nome) . '"><meta property="og:locale" content="it_IT">'
               . '<meta property="og:type" content="' . e($og['tipo'] ?? 'website') . '"><meta property="og:title" content="' . e($titolo) . '">'
               . ($descr ? '<meta property="og:description" content="' . e($descr) . '">' : '')
               . '<meta property="og:url" content="' . e($url) . '"><meta property="og:image" content="' . e($img) . '">'
               . '<meta name="twitter:card" content="summary_large_image"><link rel="canonical" href="' . e($url) . '">'
               . '<link rel="alternate" type="application/rss+xml" title="Novità" href="feed.php"><link rel="sitemap" type="application/xml" href="sitemap.php">';
    }
    $d = $descr ? '<meta name="description" content="' . e($descr) . '">' : '';
    echo "<!doctype html><html lang=\"it\"><head><meta charset=\"utf-8\">
<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
<title>$t</title>$d$extra<link rel=\"icon\" type=\"image/png\" href=\"" . e(asset_emblema($prefisso)) . "\"><link rel=\"stylesheet\" href=\"{$prefisso}assets/stile.css\">" . stile_tema($prefisso) . "</head><body>
<header class=\"testata\"><a class=\"marchio\" href=\"{$prefisso}index.php\"><img src=\"" . e(asset_emblema($prefisso)) . "\" alt=\"\" width=\"44\" height=\"44\"><span>" . e(strtoupper($nome)) . "</span></a><nav><a href=\"{$prefisso}index.php\">Catalogo</a>";
    if (!$prefisso) {
        foreach (db()->query('SELECT slug, titolo FROM pagine WHERE visibile = 1 AND nel_menu = 1 ORDER BY ordine, id') as $p)
            echo '<a href="pagina.php?s=' . e($p['slug']) . '">' . e($p['titolo']) . '</a>';
    }
    echo "</nav></header><main class=\"contenuto\">";
}
function piede(): void {
    $mail = impost('email');
    $priv = db()->query("SELECT 1 FROM pagine WHERE slug = 'privacy' AND visibile = 1")->fetchColumn();
    echo '</main><footer class="piede">© ' . date('Y') . ' ' . e(impost('nome_sito')) . ' · ' . e(impost('footer'))
       . ($mail ? ' · <a href="mailto:' . e($mail) . '">' . e($mail) . '</a>' : '')
       . ($priv ? ' · <a href="pagina.php?s=privacy">Privacy</a>' : '') . '</footer></body></html>';
}

// Aspetto personalizzabile dall'admin: emblema, logo, banner e colori.
function asset_personalizzato(string $chiave, string $predefinito, int $larghezza, string $prefisso): string {
    $f = impost($chiave);
    if ($f !== '') return con_prefisso(miniatura($f, $larghezza), $prefisso);
    return $prefisso . $predefinito;
}
function asset_emblema(string $prefisso = ''): string { return asset_personalizzato('logo_emblema', 'assets/emblema.png', 256, $prefisso); }
function asset_logo(string $prefisso = ''): string { return asset_personalizzato('logo_grande', 'assets/logo.png', 900, $prefisso); }
function asset_banner(string $prefisso = ''): string { return asset_personalizzato('banner_home', 'assets/hero.jpg', 1920, $prefisso); }
function colore_valido(string $c, string $default): string { return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $default; }
function stile_tema(string $prefisso = ''): string {
    $blu = colore_valido(impost('colore_blu'), '#0b1a36');
    $oro = colore_valido(impost('colore_oro'), '#c9a14a');
    // dentro <style> le entità HTML non vengono lette: l'indirizzo va protetto in modo da CSS
    // Un url() dentro una variabile CSS viene letto rispetto al file stile.css (assets/), non alla pagina: serve un indirizzo completo.
    $b = asset_banner('');
    $b = e_assoluto($b) ? $b : percorso_pubblico() . ltrim($b, '/');
    $img = str_replace(['"', '\\', '<', '>', "\n", "\r"], ['%22', '%5C', '%3C', '%3E', '', ''], $b);
    return '<style>:root{--blu:' . $blu . ';--oro:' . $oro . ';--hero:url("' . $img . '")}</style>';
}
