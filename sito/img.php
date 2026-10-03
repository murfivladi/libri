<?php
// Miniature delle copertine, per i siti che non hanno i file in casa: img.php?t=cover&f=NOME&w=400
// Si crea la prima volta, poi resta in uploads/thumb e il browser la tiene in cache.
define('SENZA_SESSIONE', true);
require __DIR__ . '/inc/bootstrap.php';

$nome = basename((string)($_GET['f'] ?? ''));
$w = (int)($_GET['w'] ?? 0);
if (!sito_principale() || !preg_match('/^[A-Za-z0-9._-]+$/', $nome) || !in_array($w, LARGHEZZE_MINIATURE, true)) { http_response_code(404); exit; }

$percorso = crea_miniatura($nome, $w) ?? (RADICE . '/uploads/cover/' . $nome);   // se non serve ridurla, l'originale
if (!is_file($percorso)) { http_response_code(404); exit; }
$tipo = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][strtolower(pathinfo($percorso, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
header('Content-Type: ' . $tipo);
header('Content-Length: ' . filesize($percorso));
header('Cache-Control: public, max-age=31536000, immutable');
header('Access-Control-Allow-Origin: *');
readfile($percorso);
