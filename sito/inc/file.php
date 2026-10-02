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
    @unlink(percorso_file($cartella, $nome));
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
