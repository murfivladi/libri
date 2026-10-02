<?php
require __DIR__ . '/inc/pubblico.php';
header('Content-Type: application/xml; charset=utf-8');
$b = percorso_pubblico();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$u = fn(string $loc, string $mod = '') => print('<url><loc>' . e($b . $loc) . '</loc>' . ($mod ? '<lastmod>' . date('Y-m-d', strtotime($mod)) . '</lastmod>' : '') . "</url>\n");
$u('index.php');
foreach (db()->query('SELECT slug FROM pagine WHERE visibile = 1') as $p) $u('pagina.php?s=' . $p['slug']);
foreach (db()->query('SELECT slug FROM collane WHERE visibile = 1') as $c) $u('index.php?collana=' . $c['slug']);
foreach (db()->query('SELECT slug, aggiornato FROM libri l WHERE ' . sql_visibile('l')) as $l) $u('libro.php?s=' . $l['slug'], $l['aggiornato']);
echo '</urlset>';
