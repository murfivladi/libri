<?php
// Funzioni per le pagine pubbliche (catalogo, scheda libro, lettore).
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/file.php';
require_once __DIR__ . '/scuola.php';

function ordine_catalogo(): string {
    return [
        'numero' => 'c.ordine, c.nome, l.numero, l.id', 'manuale' => 'l.ordine, l.numero, l.id',
        'recenti' => 'l.creato DESC, l.id DESC', 'titolo' => sql_nocase('l.titolo'),
    ][impost('ordine_catalogo')] ?? 'l.numero, l.id';
}

// Libro visibile al pubblico; con il token segreto si vede anche la bozza.
function libro_per_slug(string $slug, string $token = ''): ?array {
    $st = db()->prepare('SELECT l.*, c.nome AS nome_collana, c.nome_ru AS nome_collana_ru, c.slug AS slug_collana, c.visibile AS collana_visibile FROM libri l LEFT JOIN collane c ON c.id = l.collana_id WHERE l.slug = ?');
    $st->execute([$slug]);
    $l = $st->fetch();
    if (!$l || $l['eliminato'] !== null) return null;
    $l['nome_collana'] = campo_l(['nome' => $l['nome_collana'], 'nome_ru' => $l['nome_collana_ru'] ?? ''], 'nome');
    $l = loc($l, CAMPI_LIBRO);
    $l['anteprima'] = false;
    $visibile = $l['pubblicato'] == 1 && ($l['pubblica_il'] === '' || $l['pubblica_il'] <= date('Y-m-d H:i:s'));
    if ($visibile) return $l;
    if ($token !== '' && $l['token'] !== '' && hash_equals($l['token'], $token)) { $l['anteprima'] = true; return $l; }
    return null;
}

function pagina_non_trovata(): void {
    http_response_code(404);
    intestazione(t('Non trovato'));
    echo '<h1>' . e(t('Pagina non trovata')) . '</h1><p><a href="index.php">' . e(t('Torna al catalogo')) . '</a></p>';
    piede();
    exit;
}

function scheda_libro(array $l): void {
    if (!isset($l['_loc'])) { $l = loc($l, CAMPI_LIBRO); $l['nome_collana'] = campo_l(['nome' => $l['nome_collana'] ?? '', 'nome_ru' => $l['nome_collana_ru'] ?? ''], 'nome'); }
    echo '<a class="scheda" href="libro.php?s=' . e($l['slug']) . '">';
    if ($l['copertina']) echo '<img src="' . e(miniatura($l['copertina'], 400)) . '" alt="' . e(t('Copertina di') . ' ' . $l['titolo']) . '" loading="lazy" width="400" height="600">';
    echo '<span class="titolo">' . e($l['titolo']) . '</span><span class="meta">' . e($l['nome_collana'] ?? '') . ($l['numero'] ? ' · ' . t('n.') . ' ' . (int)$l['numero'] : '') . '</span></a>';
}

// Indirizzo del PDF per il lettore: passa da pdf.php del sito principale (CORS e lettura a pezzi garantiti).
function url_pdf_lettore(string $valore): string {
    if (!e_nostro($valore)) return $valore;
    $b = file_base();
    return ($b !== '' ? $b . '/' : '') . 'pdf.php?f=' . rawurlencode(nome_file($valore));
}

function conta_una_volta(string $tipo, int $id): void {
    if (!empty($_SESSION["c_{$tipo}_$id"])) return;
    $_SESSION["c_{$tipo}_$id"] = 1;
    db()->prepare("UPDATE libri SET $tipo = $tipo + 1 WHERE id = ?")->execute([$id]);
}

require_once __DIR__ . '/lettori.php';

// Testi usati da assets/lettore.js (nella lingua corrente); la chiave è il testo italiano.
function lettore_traduzioni(): array {
    $k = ['Impossibile aprire il PDF.', 'Questo libro non ha un indice: vai direttamente a una pagina.', 'Tocca per tornare a una pagina.',
          'Nessun segnalibro. Premi ☆ nella barra per aggiungerne uno.', 'Con un account ritrovi pagina e segnalibri su ogni dispositivo.', 'Accedi',
          'Pagina', 'Togli segnalibro', 'Scrivi almeno 2 lettere.', 'Risultati:', 'Nessun risultato.', 'Cerco…', 'p.'];
    $o = [];
    foreach ($k as $x) $o[$x] = t($x);
    return $o;
}
