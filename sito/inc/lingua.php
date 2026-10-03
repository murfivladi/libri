<?php
// Italiano e russo: lingua scelta con ?lang=it|ru (ricordata in un cookie), poi cookie, poi la lingua del browser.
// t('testo italiano') restituisce la traduzione russa (inc/ru.php) se la lingua è il russo; il testo italiano è la chiave.
const LINGUE = ['it', 'ru'];

function lingua(?string $forza = null): string {
    static $l = null;
    if ($forza !== null && in_array($forza, LINGUE, true)) $l = $forza;
    if ($l !== null) return $l;
    if (strpos((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') !== false) return $l = 'it';   // l'admin resta in italiano
    $r = (string)($_GET['lang'] ?? '');
    if (in_array($r, LINGUE, true)) {
        $l = $r;
        if (PHP_SAPI !== 'cli' && !headers_sent()) setcookie('lingua', $r, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
        return $l;
    }
    $c = (string)($_COOKIE['lingua'] ?? '');
    if (in_array($c, LINGUE, true)) return $l = $c;
    return $l = (stripos((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 'ru') === 0 ? 'ru' : 'it');
}
function e_ru(): bool { return lingua() === 'ru'; }

function dizionario_ru(): array { static $d = null; return $d ??= require __DIR__ . '/ru.php'; }
function t(string $it): string { return lingua() === 'ru' ? (dizionario_ru()[$it] ?? $it) : $it; }
function tf(string $it, ...$args): string { return sprintf(t($it), ...$args); }   // frasi con %s / %d
// singolare/plurale (in russo tre forme: 1, 2-4, 5+)
function tn(int $n, string $uno, string $molti, string $ru1 = '', string $ru2 = '', string $ru5 = ''): string {
    if (lingua() !== 'ru') return $n === 1 ? $uno : $molti;
    $m10 = $n % 10; $m100 = $n % 100;
    if ($m10 === 1 && $m100 !== 11) return $ru1;
    if ($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) return $ru2;
    return $ru5;
}

// Campo di contenuto nella lingua corrente: se in russo e c'è la traduzione la usa, altrimenti l'italiano.
function campo_l(array $r, string $k): string {
    if (lingua() === 'ru' && isset($r[$k . '_ru']) && trim((string)$r[$k . '_ru']) !== '') return (string)$r[$k . '_ru'];
    return (string)($r[$k] ?? '');
}
// Sostituisce nel record i campi con la versione nella lingua corrente.
function loc(array $r, array $campi): array {
    foreach ($campi as $k) if (array_key_exists($k, $r)) $r[$k] = campo_l($r, $k);
    return $r;
}
const CAMPI_LIBRO = ['titolo', 'sottotitolo', 'trama', 'meta', 'genere'];

// Indirizzo della pagina attuale nell'altra lingua (per l'interruttore IT / RU e per hreflang).
function url_lingua(string $l): string {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $parti = explode('?', $uri, 2);
    parse_str($parti[1] ?? '', $q);
    $q['lang'] = $l;
    return basename($parti[0]) . '?' . http_build_query($q);
}
