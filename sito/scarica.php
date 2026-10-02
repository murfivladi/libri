<?php
require __DIR__ . '/inc/pubblico.php';

$token = (string)($_GET['anteprima'] ?? '');
$l = libro_per_slug((string)($_GET['s'] ?? ''), $token);
if (!$l || $l['pdf'] === '') pagina_non_trovata();
if (!$l['anteprima']) conta_una_volta('download', (int)$l['id']);
session_write_close();
invia_download($l['pdf'], 'pdf', $l['slug'] . '.pdf', 'application/pdf');
