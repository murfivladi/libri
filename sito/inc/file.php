<?php
// Gestione dei file caricati (copertine, PDF, banner): upload, download da URL, eliminazione.
require_once __DIR__ . '/scarica.php';

const TIPI_IMMAGINE = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const TIPI_PDF = ['application/pdf' => 'pdf'];

// Salva il file caricato nel campo $campo oppure, se c'è un URL in "{$campo}_url", lo scarica.
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
    $nome = bin2hex(random_bytes(8)) . '.' . $tipi_ok[$mime];
    @mkdir(RADICE . "/uploads/$cartella", 0755, true);
    $dest = RADICE . "/uploads/$cartella/$nome";
    $ok = $caricato ? move_uploaded_file($tmp, $dest) : rename($tmp, $dest);
    if (!$ok) throw new RuntimeException('Non riesco a salvare il file.');
    return $nome;
}

function percorso_file(string $cartella, string $nome): string { return RADICE . "/uploads/$cartella/" . basename($nome); }

// Elimina il file solo se nessun altro record lo usa.
function elimina_file(string $cartella, string $nome): void {
    if ($nome === '') return;
    $campi = ['cover' => [['libri', 'copertina'], ['collane', 'banner']], 'pdf' => [['libri', 'pdf']]][$cartella] ?? [];
    foreach ($campi as [$t, $c]) {
        $st = db()->prepare("SELECT COUNT(*) FROM $t WHERE $c = ?"); $st->execute([$nome]);
        if ($st->fetchColumn() > 0) return;
    }
    if ($cartella === 'cover') {        // immagini scelte nella pagina Aspetto (logo, banner)
        $st = db()->prepare("SELECT COUNT(*) FROM impostazioni WHERE chiave IN ('logo_emblema','logo_grande','banner_home') AND valore = ?"); $st->execute([$nome]);
        if ($st->fetchColumn() > 0) return;
        foreach (glob(RADICE . '/uploads/thumb/*-' . pathinfo($nome, PATHINFO_FILENAME) . '.*') ?: [] as $t) @unlink($t);
    }
    @unlink(percorso_file($cartella, $nome));
}

// Miniatura di una copertina (creata la prima volta e poi riusata). Se GD non c'è, restituisce l'originale.
// Ritorna il percorso relativo alla radice del sito, es. "uploads/thumb/400-abc.jpg".
function miniatura(string $nome, int $larghezza = 400): string {
    $orig = 'uploads/cover/' . $nome;
    if ($nome === '' || !function_exists('imagecreatetruecolor')) return $orig;
    $src = RADICE . '/' . $orig;
    if (!is_file($src)) return $orig;
    $base = 'uploads/thumb/' . $larghezza . '-' . pathinfo($nome, PATHINFO_FILENAME);
    foreach (['.jpg', '.png'] as $est) if (is_file(RADICE . '/' . $base . $est)) return $base . $est;
    try {
        $info = @getimagesize($src);
        if (!$info || $info[0] <= $larghezza) return $orig;               // già piccola
        if ($info[0] * $info[1] > 40_000_000) return $orig;                // troppo grande per la memoria
        $img = match ($info[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($src), IMAGETYPE_PNG => @imagecreatefrompng($src),
                                  IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false, default => false };
        if (!$img) return $orig;
        $png = $info[2] === IMAGETYPE_PNG;                                 // i PNG restano PNG (trasparenza dei loghi)
        $h = (int)round($info[1] * $larghezza / $info[0]);
        $out = imagecreatetruecolor($larghezza, $h);
        if ($png) { imagealphablending($out, false); imagesavealpha($out, true); imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127)); }
        else imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $larghezza, $h, $info[0], $info[1]);
        @mkdir(RADICE . '/uploads/thumb', 0755, true);
        $rel = $base . ($png ? '.png' : '.jpg');
        $ok = $png ? imagepng($out, RADICE . '/' . $rel, 6) : imagejpeg($out, RADICE . '/' . $rel, 82);
        imagedestroy($img); imagedestroy($out);
        return $ok ? $rel : $orig;
    } catch (Throwable $e) { return $orig; }
}

// Copia fisica di un file (per duplicare un libro).
function copia_file(string $cartella, string $nome): string {
    if ($nome === '' || !is_file(percorso_file($cartella, $nome))) return '';
    $nuovo = bin2hex(random_bytes(8)) . '.' . pathinfo($nome, PATHINFO_EXTENSION);
    return copy(percorso_file($cartella, $nome), percorso_file($cartella, $nuovo)) ? $nuovo : '';
}

// Stima del numero di pagine di un PDF (conta gli oggetti /Type /Page).
function conta_pagine_pdf(string $percorso): int {
    if (!is_file($percorso) || filesize($percorso) > 60 * 1024 * 1024) return 0;
    $c = file_get_contents($percorso);
    if (preg_match_all('/\/Type\s*\/Pages\b[^>]*?\/Count\s+(\d+)/s', $c, $m) && $m[1]) return (int)max($m[1]);
    return (int)preg_match_all('/\/Type\s*\/Page(?![s\w])/', $c);
}
