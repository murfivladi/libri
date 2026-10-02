<?php
// Serve un PDF al lettore, anche a un altro sito (CORS) e a pezzi (Range): pdf.php?f=NOME
// Così il lettore di libri.vladinc.ru può aprire i PDF che stanno su vladinc.ru/libri senza dipendere dalle impostazioni di Apache.
define('SENZA_SESSIONE', true);
require __DIR__ . '/inc/bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Range, If-Range');
header('Access-Control-Expose-Headers: Content-Length, Content-Range, Accept-Ranges');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$nome = basename((string)($_GET['f'] ?? ''));
$file = RADICE . '/uploads/pdf/' . $nome;
if (!sito_principale() || !preg_match('/^[A-Za-z0-9._-]+\.pdf$/', $nome) || !is_file($file)) { http_response_code(404); exit; }

// Solo per il lettore (PDF.js usa fetch): il browser che apre il PDF in una scheda, un frame o un incorporamento viene respinto.
$dest = strtolower((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));
if (in_array($dest, ['document', 'iframe', 'frame', 'embed', 'object'], true)) { http_response_code(403); exit; }
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline');

$tot = filesize($file);
$da = 0; $a = $tot - 1;
header('Content-Type: application/pdf');
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=3600');
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m) && ($m[1] !== '' || $m[2] !== '')) {
    if ($m[1] === '') { $da = max(0, $tot - (int)$m[2]); }                     // ultimi N byte
    else { $da = (int)$m[1]; if ($m[2] !== '') $a = min($a, (int)$m[2]); }
    if ($da > $a || $da >= $tot) { http_response_code(416); header("Content-Range: bytes */$tot"); exit; }
    http_response_code(206);
    header("Content-Range: bytes $da-$a/$tot");
}
header('Content-Length: ' . ($a - $da + 1));
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') exit;
$f = fopen($file, 'rb');
fseek($f, $da);
for ($resto = $a - $da + 1; $resto > 0 && !feof($f); ) {
    $blocco = fread($f, min(65536, $resto));
    if ($blocco === false) break;
    echo $blocco; $resto -= strlen($blocco);
    if (connection_aborted()) break;
}
fclose($f);
