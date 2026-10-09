<?php
// Riceve il risultato di un esercizio fatto da uno studente entrato nella sua classe (lo vede il prof).
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/classi.php';

header('Content-Type: application/json; charset=utf-8');
function fine(array $r, int $c = 200): void { http_response_code($c); echo json_encode($r, JSON_UNESCAPED_UNICODE); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fine(['ok' => false, 'errore' => 'Metodo non ammesso.'], 405);
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) fine(['ok' => false, 'errore' => 'Richiesta non valida.'], 400);
$s = studente_corrente();
if (!$s) fine(['ok' => false, 'errore' => 'Non sei in una classe.'], 401);
if (!limite_ok('risultati:' . $s['id'], 120, 600)) fine(['ok' => false, 'errore' => 'Troppi invii.'], 429);

$d = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($d)) fine(['ok' => false, 'errore' => 'Dati non validi.'], 400);
$t = trova_codice((string)($d['codice'] ?? ''));
if (!$t || $t[0] !== 'esercizio' || !libro_pubblico((int)$t[1]['libro_id'])) fine(['ok' => false, 'errore' => 'Esercizio non trovato.'], 404);
// il totale lo decide il server (non si può barare mandando «10 su 10» di un esercizio da 3 punti)
$totale = punti_esercizio($t[1]);
$voci = array_values((array)($d['voci'] ?? []));
$fatti = $totale ? min($totale, count(array_filter(array_slice($voci, 0, $totale)))) : 0;
$compito = salva_risultato($s, $t[1], $fatti, $totale, array_slice($voci, 0, max($totale, count($voci))));
fine(['ok' => true, 'fatti' => $fatti, 'totale' => $totale, 'compito' => $compito]);
