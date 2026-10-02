<?php
// Sicurezza dell'account admin: codice di recupero, verifica in due passaggi (TOTP, RFC 6238), codici di backup.
require_once __DIR__ . '/bootstrap.php';

const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32_codifica(string $bin): string {
    $bit = '';
    foreach (str_split($bin) as $c) $bit .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bit, 5) as $g) $out .= BASE32[bindec(str_pad($g, 5, '0'))];
    return $out;
}
function base32_decodifica(string $s): string {
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s));
    $bit = '';
    foreach (str_split($s) as $c) $bit .= str_pad(decbin(strpos(BASE32, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bit, 8) as $b) if (strlen($b) === 8) $out .= chr(bindec($b));
    return $out;
}

function totp_segreto_nuovo(): string { return base32_codifica(random_bytes(20)); }

function totp_codice(string $segreto, ?int $tempo = null, int $cifre = 6): string {
    $passo = intdiv($tempo ?? time(), 30);
    $h = hash_hmac('sha1', pack('N2', $passo >> 32, $passo & 0xFFFFFFFF), base32_decodifica($segreto), true);
    $o = ord($h[19]) & 0x0F;
    $n = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % (10 ** $cifre)), $cifre, '0', STR_PAD_LEFT);
}

// Accetta il codice dell'app (con tolleranza di ±30 secondi per gli orologi sfasati).
function totp_verifica(string $segreto, string $codice, ?int $tempo = null): bool {
    $codice = preg_replace('/\s+/', '', $codice);
    if (!preg_match('/^\d{6}$/', $codice) || $segreto === '') return false;
    $t = $tempo ?? time();
    foreach ([0, -30, 30] as $d) if (hash_equals(totp_codice($segreto, $t + $d), $codice)) return true;
    return false;
}

function totp_uri(string $segreto, string $account, string $emittente): string {
    return 'otpauth://totp/' . rawurlencode($emittente . ':' . $account) . '?secret=' . $segreto . '&issuer=' . rawurlencode($emittente) . '&algorithm=SHA1&digits=6&period=30';
}

// Codice di recupero della password: mostrato una sola volta, nel database resta solo l'impronta.
function codice_recupero_nuovo(): string {
    $c = '';
    for ($i = 0; $i < 20; $i++) $c .= BASE32[random_int(0, 31)];
    return implode('-', str_split($c, 5));
}
function normalizza_recupero(string $c): string { return strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $c)); }

function imposta_codice_recupero(): string {
    $c = codice_recupero_nuovo();
    db()->prepare('UPDATE admin SET recupero = ? WHERE id = 1')->execute([password_hash(normalizza_recupero($c), PASSWORD_DEFAULT)]);
    return $c;
}
function verifica_codice_recupero(string $c): bool {
    $h = (string)db()->query('SELECT recupero FROM admin WHERE id = 1')->fetchColumn();
    $n = normalizza_recupero($c);
    return $h !== '' && strlen($n) === 20 && password_verify($n, $h);
}

// Codici di backup per la verifica in due passaggi (uso singolo).
function codici_backup_nuovi(): array {
    $codici = [];
    for ($i = 0; $i < 8; $i++) $codici[] = substr(bin2hex(random_bytes(4)), 0, 4) . '-' . substr(bin2hex(random_bytes(4)), 0, 4);
    db()->prepare('UPDATE admin SET codici_backup = ? WHERE id = 1')->execute([json_encode(array_map(fn($c) => password_hash($c, PASSWORD_DEFAULT), $codici))]);
    return $codici;
}
function usa_codice_backup(string $c): bool {
    $c = strtolower(trim($c));
    if (!preg_match('/^[0-9a-f]{4}-[0-9a-f]{4}$/', $c)) return false;
    $lista = json_decode((string)db()->query('SELECT codici_backup FROM admin WHERE id = 1')->fetchColumn(), true) ?: [];
    foreach ($lista as $i => $h) {
        if (password_verify($c, $h)) {
            unset($lista[$i]);
            db()->prepare('UPDATE admin SET codici_backup = ? WHERE id = 1')->execute([json_encode(array_values($lista))]);
            return true;
        }
    }
    return false;
}
function codici_backup_rimasti(): int {
    return count(json_decode((string)db()->query('SELECT codici_backup FROM admin WHERE id = 1')->fetchColumn(), true) ?: []);
}

function dati_admin(): array { return db()->query('SELECT * FROM admin WHERE id = 1')->fetch() ?: []; }

// Recupero tramite FTP: chi può scrivere nella cartella data/ è il proprietario del sito.
//   data/reset-2fa.txt       (anche vuoto)         -> disattiva la verifica in due passaggi
//   data/reset-password.txt  (nuova password)      -> imposta quella password (almeno 10 caratteri)
function applica_reset_da_file(): ?string {
    $msg = [];
    $f2 = RADICE . '/data/reset-2fa.txt';
    if (is_file($f2)) {
        db()->exec("UPDATE admin SET totp_attivo = 0, totp_segreto = '', codici_backup = '[]' WHERE id = 1");
        @unlink($f2); registra('2FA disattivata tramite file sul server'); $msg[] = 'Verifica in due passaggi disattivata.';
    }
    $fp = RADICE . '/data/reset-password.txt';
    if (is_file($fp)) {
        $pw = trim((string)file_get_contents($fp));
        if (strlen($pw) >= 10) {
            db()->prepare('UPDATE admin SET password = ? WHERE id = 1')->execute([password_hash($pw, PASSWORD_DEFAULT)]);
            @unlink($fp); registra('Password reimpostata tramite file sul server'); $msg[] = 'Password reimpostata.';
        } else { $msg[] = 'Il file reset-password.txt deve contenere una password di almeno 10 caratteri.'; }
    }
    return $msg ? implode(' ', $msg) : null;
}
