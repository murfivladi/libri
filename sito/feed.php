<?php
// Feed RSS dei libri pubblicati più di recente.
require __DIR__ . '/inc/pubblico.php';
header('Content-Type: application/rss+xml; charset=utf-8');
$b = percorso_pubblico();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0"><channel>
<title><?= e(impost('nome_sito')) ?></title>
<link><?= e($b) ?></link>
<description><?= e(impost('slogan')) ?></description>
<language>it</language>
<?php foreach (db()->query('SELECT * FROM libri l WHERE ' . sql_visibile('l') . ' ORDER BY l.creato DESC, l.id DESC LIMIT 30') as $l): ?>
<item>
<title><?= e($l['titolo']) ?></title>
<link><?= e($b . 'libro.php?s=' . $l['slug']) ?></link>
<guid><?= e($b . 'libro.php?s=' . $l['slug']) ?></guid>
<pubDate><?= date(DATE_RSS, strtotime($l['pubblica_il'] ?: $l['creato'])) ?></pubDate>
<description><?= e($l['trama']) ?></description>
</item>
<?php endforeach; ?>
</channel></rss>
