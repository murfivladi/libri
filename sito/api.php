<?php
// API per i file, usata dai siti secondari che condividono il database con questo (il sito principale).
// Ogni richiesta è firmata (HMAC-SHA256) con una chiave che sta nel database condiviso, quindi la conoscono solo i tuoi siti.
define('SENZA_SESSIONE', true);
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
function fine(array $r, int $codice = 200): void { http_response_code($codice); echo json_encode($r, JSON_UNESCAPED_UNICODE); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fine(['ok' => false, 'errore' => 'Metodo non ammesso.'], 405);
if (!sito_principale()) fine(['ok' => false, 'errore' => 'Questo sito non ospita i file.'], 403);
if (file_base() === '') fine(['ok' => false, 'errore' => 'Sul sito principale manca l\'indirizzo dei file (Admin → Database).'], 409);

$azione = (string)($_POST['azione'] ?? '');
$ts = (int)($_SERVER['HTTP_X_LIBRI_TS'] ?? 0);
$firma = (string)($_SERVER['HTTP_X_LIBRI_FIRMA'] ?? '');
if (abs(time() - $ts) > 300) fine(['ok' => false, 'errore' => 'Richiesta scaduta: controlla che l\'orologio dei due server sia giusto.'], 401);

$cartella = (string)($_POST['cartella'] ?? '');
if ($azione !== 'ping' && !in_array($cartella, ['cover', 'pdf', 'media'], true)) fine(['ok' => false, 'errore' => 'Cartella non valida.'], 400);

if ($azione === 'ping') {
    if (!hash_equals(api_firma('ping', $ts, ''), $firma)) fine(['ok' => false, 'errore' => 'Firma non valida.'], 401);
    fine(['ok' => true, 'sito' => impost('nome_sito'), 'scrivibile' => is_writable(RADICE . '/uploads')]);
}

if ($azione === 'carica') {
    $f = $_FILES['file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) fine(['ok' => false, 'errore' => 'Caricamento non riuscito (codice ' . ($f['error'] ?? '?') . '): controlla upload_max_filesize e post_max_size sul sito principale.'], 400);
    if (!hash_equals(api_firma('carica', $ts, hash_file('sha256', $f['tmp_name']) . '|' . $cartella), $firma)) fine(['ok' => false, 'errore' => 'Firma non valida.'], 401);
    if ($cartella === 'media') require_once __DIR__ . '/inc/scuola.php';
    $tipi = $cartella === 'pdf' ? TIPI_PDF : ($cartella === 'media' ? TIPI_MEDIA : TIPI_IMMAGINE);
    $max = $cartella === 'pdf' ? 100 * 1024 * 1024 : ($cartella === 'media' ? 200 * 1024 * 1024 : 8 * 1024 * 1024);
    if ($f['size'] > $max) fine(['ok' => false, 'errore' => 'File troppo grande.'], 413);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if ($cartella === 'media' && ($mime === 'application/octet-stream' || $mime === 'video/x-matroska')) $mime = 'audio/webm';
    if (!isset($tipi[$mime])) fine(['ok' => false, 'errore' => 'Tipo di file non ammesso (' . $mime . ').'], 415);
    $nome = bin2hex(random_bytes(8)) . '.' . $tipi[$mime];
    @mkdir(RADICE . "/uploads/$cartella", 0755, true);
    $dest = RADICE . "/uploads/$cartella/$nome";
    if (!move_uploaded_file($f['tmp_name'], $dest)) fine(['ok' => false, 'errore' => 'Il sito principale non riesce a salvare il file (cartella uploads non scrivibile?).'], 500);
    fine(['ok' => true, 'nome' => $nome, 'url' => valore_file($cartella, $nome), 'dim' => (int)filesize($dest), 'pagine' => $cartella === 'pdf' ? conta_pagine_pdf($dest) : 0]);
}

if ($azione === 'elimina') {
    $nome = basename((string)($_POST['nome'] ?? ''));
    if (!hash_equals(api_firma('elimina', $ts, "$nome|$cartella"), $firma)) fine(['ok' => false, 'errore' => 'Firma non valida.'], 401);
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $nome)) fine(['ok' => false, 'errore' => 'Nome non valido.'], 400);
    elimina_fisico($cartella, $nome);
    fine(['ok' => true]);
}

fine(['ok' => false, 'errore' => 'Azione sconosciuta.'], 400);
