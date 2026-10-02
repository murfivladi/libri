<?php
// Pagina pubblica di una collana: immagine, descrizione, i libri in ordine e «inizia dal primo».
require __DIR__ . '/inc/pubblico.php';

$st = db()->prepare('SELECT * FROM collane WHERE slug = ? AND visibile = 1');
$st->execute([(string)($_GET['s'] ?? '')]);
$c = $st->fetch();
if (!$c) pagina_non_trovata();

$st = db()->prepare('SELECT l.*, c.nome AS nome_collana, c.nome_ru AS nome_collana_ru FROM libri l LEFT JOIN collane c ON c.id = l.collana_id WHERE l.collana_id = ? AND ' . sql_visibile('l') . ' ORDER BY CASE WHEN l.numero = 0 THEN 1 ELSE 0 END, l.numero, l.id');
$st->execute([$c['id']]);
$libri = array_map(fn($x) => loc($x, CAMPI_LIBRO) + ['_loc' => 1], $st->fetchAll());
$c = loc($c, ['nome', 'descrizione']);
$primo = $libri[0] ?? null;
$immagine = $c['banner'] !== '' ? $c['banner'] : ($primo['copertina'] ?? '');

intestazione($c['nome'], '', $c['descrizione'] !== '' ? $c['descrizione'] : t('La collana') . ' ' . $c['nome'] . ' · ' . impost('nome_sito'), [
    'canonico' => 'collana.php?s=' . urlencode($c['slug']),
    'immagine' => $immagine !== '' ? miniatura($immagine, 800) : null,
]);

$json = ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $c['nome'], 'description' => $c['descrizione'],
    'url' => base_ufficiale() . 'collana.php?s=' . urlencode($c['slug']),
    'hasPart' => array_map(fn($l) => ['@type' => 'Book', 'name' => $l['titolo'], 'url' => base_ufficiale() . 'libro.php?s=' . urlencode($l['slug']), 'position' => (int)$l['numero']], $libri)];
?>
<script type="application/ld+json"><?= json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<p class="meta"><a href="collane.php"><?= e(t('Tutte le collane')) ?></a></p>
<section class="collana-testa">
  <?php if ($immagine !== ''): ?><img class="collana-img" src="<?= e(miniatura($immagine, 800)) ?>" alt="" width="800" height="400"><?php endif; ?>
  <div>
    <h1><?= e($c['nome']) ?></h1>
    <p class="meta"><?= count($libri) ?> <?= e(tn(count($libri), 'libro', 'libri', 'книга', 'книги', 'книг')) ?></p>
    <?php if ($c['descrizione'] !== ''): ?><div class="trama"><?= nl2br(e($c['descrizione'])) ?></div><?php endif; ?>
    <?php if ($primo && ($primo['pdf'] !== '' || $primo['pdf_ru'] !== '')): ?><p class="azioni"><a class="bottone" href="leggi.php?s=<?= e($primo['slug']) ?>"><?= e(t('Inizia dal libro')) ?> <?= $primo['numero'] ? (int)$primo['numero'] : 1 ?> · <?= e($primo['titolo']) ?></a></p><?php endif; ?>
  </div>
</section>
<?php if (!$libri): ?><p><?= e(t('Per ora non ci sono libri pubblicati in questa collana.')) ?></p><?php endif; ?>
<div class="griglia"><?php foreach ($libri as $l) scheda_libro($l); ?></div>
<?php piede();
