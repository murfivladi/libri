<?php
// API JSON per il lettore: sincronizza pagina, segnalibri e liste dell'utente collegato.
//   GET  api-lettore.php?libro=SLUG                      -> stato
//   POST api-lettore.php  azione=progresso|segnalibro|lista  (con intestazione X-CSRF)
require __DIR__ . '/inc/lettori.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function rispondi(array $d, int $codice = 200): void { http_response_code($codice); echo json_encode($d); exit; }

$l = lettore_corrente();
if (!$l) rispondi(['loggato' => false]);
if ($l['privacy_versione'] !== privacy_versione()) rispondi(['loggato' => true, 'consenso_da_dare' => true], 403);

$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$slug = (string)($post ? ($_POST['libro'] ?? '') : ($_GET['libro'] ?? ''));
$st = db()->prepare('SELECT id FROM libri l WHERE slug = ? AND ' . sql_visibile('l')); $st->execute([$slug]);
$libro = (int)$st->fetchColumn();
if (!$libro) rispondi(['errore' => 'libro'], 404);
$lid = (int)$l['id'];

if ($post) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF'] ?? '')) rispondi(['errore' => 'csrf'], 400);
    if (!limite_ok('api:' . $lid, 240, 60)) rispondi(['errore' => 'troppe richieste'], 429);
    $az = (string)($_POST['azione'] ?? '');
    if ($az === 'progresso') salva_progresso($lid, $libro, (int)($_POST['pagina'] ?? 1), (int)($_POST['totale'] ?? 0));
    elseif ($az === 'segnalibro') imposta_segnalibro($lid, $libro, (int)($_POST['pagina'] ?? 0), ($_POST['presente'] ?? '1') === '1');
    elseif ($az === 'segnalibri') { foreach (array_slice(array_map('intval', explode(',', (string)($_POST['pagine'] ?? ''))), 0, 200) as $p) imposta_segnalibro($lid, $libro, $p, true); }
    elseif ($az === 'lista') commuta_lista($lid, $libro, (string)($_POST['lista'] ?? ''), ($_POST['presente'] ?? '1') === '1');
    else rispondi(['errore' => 'azione'], 400);
}

$p = db()->prepare('SELECT pagina, totale FROM progressi WHERE lettore_id = ? AND libro_id = ?'); $p->execute([$lid, $libro]);
$pr = $p->fetch();
rispondi([
    'loggato' => true, 'nick' => $l['nick'],
    'pagina' => $pr ? (int)$pr['pagina'] : 0,
    'segnalibri' => segnalibri_di($lid, $libro),
    'preferito' => in_lista($lid, $libro, 'preferito'), 'da_leggere' => in_lista($lid, $libro, 'da_leggere'),
]);
