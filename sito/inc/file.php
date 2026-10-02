<?php
// File caricati (copertine, PDF, banner, logo).
//
// Nel database ogni file è un indirizzo intero (es. https://vladinc.ru/libri/uploads/pdf/abc.pdf) quando in
// data/config.php c'è "file_url_base"; altrimenti è il semplice nome del file (un solo sito, tutto in locale).
// I file stanno fisicamente sul "sito principale". Un sito secondario li manda lì:
//   - scrivendo direttamente nella cartella uploads del principale, se il server lo permette ("uploads_dir_primario"),
//   - oppure con una piccola API protetta (api.php), firmata con una chiave condivisa nel database.
require_once __DIR__ . '/scarica.php';

const TIPI_IMMAGINE = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const TIPI_PDF = ['application/pdf' => 'pdf'];
const LARGHEZZE_MINIATURE = [120, 256, 300, 400, 800, 900, 1920];

// ---------- dove vivono i file ----------
function file_base(): string { return rtrim((string)(config()['file_url_base'] ?? ''), '/'); }
function sito_principale(): bool { return (bool)(config()['sito_principale'] ?? true); }

// Cartella uploads in cui questo sito può scrivere (null = deve usare l'API del sito principale).
function dir_uploads(): ?string {
    if (sito_principale()) return RADICE . '/uploads';
    $d = rtrim((string)(config()['uploads_dir_primario'] ?? ''), '/\\');
    return ($d !== '' && @is_dir($d) && @is_writable($d)) ? $d : null;
}

function e_assoluto(string $u): bool { return (bool)preg_match('#^https?://#i', $u); }
function nome_file(string $valore): string {
    $p = parse_url($valore, PHP_URL_PATH);
    return basename(is_string($p) && $p !== '' ? $p : $valore);
}
// Il file è nostro (nella cartella uploads del principale) e non un indirizzo di un altro sito?
function e_nostro(string $valore): bool {
    if ($valore === '') return false;
    if (!e_assoluto($valore)) return true;
    $b = file_base();
    return $b !== '' && strncasecmp($valore, $b . '/', strlen($b) + 1) === 0;
}
function valore_file(string $cartella, string $nome): string { $b = file_base(); return $b !== '' ? "$b/uploads/$cartella/$nome" : $nome; }
// Indirizzo da mettere nelle pagine: intero, oppure relativo alla radice del sito (serve poi un prefisso, vedi con_prefisso).
function url_file(string $valore, string $cartella): string {
    if ($valore === '') return '';
    if (e_assoluto($valore)) return $valore;
    $b = file_base();
    return ($b !== '' ? $b . '/' : '') . "uploads/$cartella/" . basename($valore);
}
function con_prefisso(string $url, string $prefisso): string { return e_assoluto($url) ? $url : $prefisso . $url; }

// Percorso del file sul disco, se questo sito può leggerlo direttamente.
function file_locale(string $valore, string $cartella): ?string {
    $d = dir_uploads();
    if ($d === null || $valore === '' || !e_nostro($valore)) return null;
    $p = "$d/$cartella/" . nome_file($valore);
    return @is_file($p) ? $p : null;
}

// ---------- API verso il sito principale ----------
function api_chiave(): string {
    $leggi = fn() => (string)db()->query("SELECT valore FROM impostazioni WHERE chiave = 'chiave_api'")->fetchColumn();
    $k = $leggi();
    if ($k === '') {
        db()->prepare(sql_insert_ignore() . " INTO impostazioni (chiave, valore) VALUES ('chiave_api', ?)")->execute([bin2hex(random_bytes(24))]);
        $k = $leggi();
    }
    return $k;
}
function api_firma(string $azione, int $ts, string $extra): string { return hash_hmac('sha256', "$azione|$ts|$extra", api_chiave()); }

function api_chiama(string $azione, array $campi = [], ?string $file = null, string $extra = ''): array {
    $b = file_base();
    if ($b === '') throw new RuntimeException('Manca l\'indirizzo dei file (Admin → Database).');
    if (!function_exists('curl_init')) throw new RuntimeException('Serve l\'estensione curl per inviare i file al sito principale.');
    $ts = time();
    $post = $campi + ['azione' => $azione];
    if ($file !== null) $post['file'] = new CURLFile($file, 'application/octet-stream', 'file');
    $ch = curl_init($b . '/api.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 300, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ["X-Libri-Ts: $ts", 'X-Libri-Firma: ' . api_firma($azione, $ts, $extra)], CURLOPT_USERAGENT => 'VladEditore-API/1.0',
    ]);
    $r = curl_exec($ch); $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    $j = is_string($r) ? json_decode($r, true) : null;
    if (!is_array($j)) throw new RuntimeException('Il sito principale non risponde come previsto' . ($err ? ": $err" : " (HTTP $code)") . '.');
    if (empty($j['ok'])) throw new RuntimeException((string)($j['errore'] ?? 'Il sito principale ha rifiutato la richiesta.'));
    return $j;
}

// ---------- caricamento ----------
// Salva il file caricato nel campo $campo oppure, se c'è un URL in "{$campo}_url", lo scarica.
// Restituisce il valore da mettere nel database (indirizzo intero o nome). Dettagli (pagine, byte) in $GLOBALS['ultimo_upload'].
function salva_file(string $campo, string $cartella, array $tipi_ok, int $max): ?string {
    $url = trim($_POST[$campo . '_url'] ?? '');
    if (!empty($_FILES[$campo]['name'])) {
        $f = $_FILES[$campo];
        if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Caricamento fallito (codice ' . $f['error'] . '). Controlla upload_max_filesize su PHP.');
        if ($f['size'] > $max) throw new RuntimeException('File troppo grande.');
        $tmp = $f['tmp_name']; $caricato = true;
    } elseif ($url !== '') {
        $tmp = scarica_url($url, $max); $caricato = false;
    } else {
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!isset($tipi_ok[$mime])) { if (!$caricato) @unlink($tmp); throw new RuntimeException('Tipo di file non ammesso (' . $mime . ').'); }
    $info = ['pagine' => 0, 'dim' => (int)filesize($tmp)];
    $d = dir_uploads();
    if ($d !== null) {                                   // questo sito (o il principale, per strada diretta) ha la cartella
        $nome = bin2hex(random_bytes(8)) . '.' . $tipi_ok[$mime];
        @mkdir("$d/$cartella", 0755, true);
        $dest = "$d/$cartella/$nome";
        $ok = $caricato ? move_uploaded_file($tmp, $dest) : rename($tmp, $dest);
        if (!$ok) throw new RuntimeException('Non riesco a salvare il file.');
        if ($cartella === 'pdf') $info['pagine'] = conta_pagine_pdf($dest);
        $GLOBALS['ultimo_upload'] = $info;
        return valore_file($cartella, $nome);
    }
    try {                                                // sito secondario: lo manda al principale
        $r = api_chiama('carica', ['cartella' => $cartella], $tmp, hash_file('sha256', $tmp) . '|' . $cartella);
    } finally { if (!$caricato) @unlink($tmp); }
    $info['pagine'] = (int)($r['pagine'] ?? 0);
    $GLOBALS['ultimo_upload'] = $info;
    return (string)$r['url'];
}

// ---------- eliminazione ----------
// Un file è "usato" se un libro, una collana o l'aspetto del sito lo richiama (anche con un altro formato di indirizzo).
function file_usato(string $cartella, string $valore): bool {
    $nome = nome_file($valore);
    $campi = ['cover' => [['libri', 'copertina'], ['collane', 'banner']], 'pdf' => [['libri', 'pdf']]][$cartella] ?? [];
    foreach ($campi as [$t, $c]) {
        $st = db()->prepare("SELECT COUNT(*) FROM $t WHERE $c = ? OR $c = ? OR $c LIKE ?"); $st->execute([$valore, $nome, '%/' . $nome]);
        if ($st->fetchColumn() > 0) return true;
    }
    if ($cartella === 'cover') {
        $st = db()->prepare("SELECT COUNT(*) FROM impostazioni WHERE chiave IN ('logo_emblema','logo_grande','banner_home') AND (valore = ? OR valore = ? OR valore LIKE ?)");
        $st->execute([$valore, $nome, '%/' . $nome]);
        if ($st->fetchColumn() > 0) return true;
    }
    return false;
}

// Elimina il file solo se è nostro e nessun altro record lo usa.
function elimina_file(string $cartella, string $valore): void {
    if ($valore === '' || !e_nostro($valore) || file_usato($cartella, $valore)) return;
    elimina_fisico($cartella, nome_file($valore));
}
function elimina_fisico(string $cartella, string $nome): void {
    $d = dir_uploads();
    if ($d !== null) {
        foreach (glob("$d/thumb/*-" . pathinfo($nome, PATHINFO_FILENAME) . '.*') ?: [] as $t) @unlink($t);
        @unlink("$d/$cartella/" . basename($nome));
        return;
    }
    try { api_chiama('elimina', ['cartella' => $cartella, 'nome' => $nome], null, "$nome|$cartella"); }
    catch (Throwable $e) { registra('Eliminazione del file sul sito principale non riuscita', $e->getMessage()); }
}

// ---------- miniature ----------
// Crea (o riusa) la miniatura di una copertina; restituisce il percorso sul disco, o null se non serve/non si può.
function crea_miniatura(string $nome, int $larghezza): ?string {
    $d = dir_uploads();
    if ($d === null || !function_exists('imagecreatetruecolor')) return null;
    $nome = basename($nome);
    $src = "$d/cover/$nome";
    if (!@is_file($src)) return null;
    $base = "$d/thumb/$larghezza-" . pathinfo($nome, PATHINFO_FILENAME);
    foreach (['.jpg', '.png'] as $est) if (is_file($base . $est)) return $base . $est;
    try {
        $info = @getimagesize($src);
        if (!$info || $info[0] <= $larghezza) return null;                // già piccola: si usa l'originale
        if ($info[0] * $info[1] > 40_000_000) return null;                 // troppo grande per la memoria
        $img = match ($info[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($src), IMAGETYPE_PNG => @imagecreatefrompng($src),
                                  IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false, default => false };
        if (!$img) return null;
        $png = $info[2] === IMAGETYPE_PNG;                                 // i PNG restano PNG (trasparenza dei loghi)
        $h = (int)round($info[1] * $larghezza / $info[0]);
        $out = imagecreatetruecolor($larghezza, $h);
        if ($png) { imagealphablending($out, false); imagesavealpha($out, true); imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127)); }
        else imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $larghezza, $h, $info[0], $info[1]);
        @mkdir("$d/thumb", 0755, true);
        $dest = $base . ($png ? '.png' : '.jpg');
        $ok = $png ? imagepng($out, $dest, 6) : imagejpeg($out, $dest, 82);
        imagedestroy($img); imagedestroy($out);
        return $ok ? $dest : null;
    } catch (Throwable $e) { return null; }
}

// Indirizzo della miniatura di un'immagine. Se questo sito ha i file la crea subito; altrimenti chiede al principale (img.php).
function miniatura(string $valore, int $larghezza = 400): string {
    if ($valore === '') return '';
    if (!e_nostro($valore)) return $valore;                                // immagine di un altro sito: così com'è
    $nome = nome_file($valore);
    $b = file_base();
    if (dir_uploads() !== null) {
        $p = crea_miniatura($nome, $larghezza);
        return $p !== null ? ($b !== '' ? $b . '/' : '') . 'uploads/thumb/' . basename($p) : url_file($valore, 'cover');
    }
    return $b . '/img.php?t=cover&f=' . rawurlencode($nome) . '&w=' . $larghezza;
}

// ---------- varie ----------
function dimensione_file(string $valore, string $cartella): ?int {
    $p = file_locale($valore, $cartella);
    return $p === null ? null : (int)filesize($p);
}

// Stima del numero di pagine di un PDF (conta gli oggetti /Type /Page).
function conta_pagine_pdf(string $percorso): int {
    if (!@is_file($percorso) || filesize($percorso) > 60 * 1024 * 1024) return 0;
    $c = file_get_contents($percorso);
    if (preg_match_all('/\/Type\s*\/Pages\b[^>]*?\/Count\s+(\d+)/s', $c, $m) && $m[1]) return (int)max($m[1]);
    return (int)preg_match_all('/\/Type\s*\/Page(?![s\w])/', $c);
}

// Il file risponde? (controllo veloce con una richiesta HEAD, per i file che stanno sul sito principale)
function file_raggiungibile(string $url): bool {
    if (!function_exists('curl_init')) return true;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3]);
    curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return $code >= 200 && $code < 300;
}

// Invia il file al visitatore come download; se sta su un altro sito (il principale) lo ricopia al volo.
function invia_download(string $valore, string $cartella, string $nomeDownload, string $mime): void {
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
    $loc = file_locale($valore, $cartella);
    if ($loc !== null) { header('Content-Length: ' . filesize($loc)); readfile($loc); return; }
    $url = url_file($valore, $cartella);
    if (!e_nostro($valore) || !function_exists('curl_init')) { header_remove('Content-Type'); header_remove('Content-Disposition'); header('Location: ' . $url); return; }
    $ok = true;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false, CURLOPT_TIMEOUT => 600, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
        CURLOPT_HEADERFUNCTION => function ($c, $riga) use (&$ok) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $riga, $m)) $ok = $m[1] === '200';
            if ($ok && stripos($riga, 'content-length:') === 0) header('Content-Length: ' . trim(substr($riga, 15)));
            return strlen($riga);
        },
        CURLOPT_WRITEFUNCTION => function ($c, $dati) use (&$ok) { if (!$ok) return strlen($dati); echo $dati; flush(); return strlen($dati); },
    ]);
    curl_exec($ch); curl_close($ch);
}
