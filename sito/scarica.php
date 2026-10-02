<?php
require __DIR__ . '/inc/pubblico.php';

$token = (string)($_GET['anteprima'] ?? '');
$l = libro_per_slug((string)($_GET['s'] ?? ''), $token);
if (!$l || $l['pdf'] === '') pagina_non_trovata();
$file = RADICE . '/uploads/pdf/' . basename($l['pdf']);
if (!is_file($file)) pagina_non_trovata();
if (!$l['anteprima']) conta_una_volta('download', (int)$l['id']);
session_write_close();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $l['slug'] . '.pdf"');
header('Content-Length: ' . filesize($file));
readfile($file);
