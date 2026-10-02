<?php
require __DIR__ . '/inc/pubblico.php';

$q = trim($_GET['q'] ?? '');
$sc = trim($_GET['collana'] ?? '');
$tag = trim($_GET['tag'] ?? '');
$filtrato = $q !== '' || $sc !== '' || $tag !== '';
$per = max(3, (int)impost('per_pagina'));
$pag = max(1, (int)($_GET['p'] ?? 1));

$dove = [sql_visibile('l')]; $par = [];
$dove[] = '(l.collana_id = 0 OR c.visibile = 1)';
if ($q !== '') { $dove[] = '(l.titolo LIKE :q OR l.sottotitolo LIKE :q OR l.autore LIKE :q OR l.tags LIKE :q OR l.genere LIKE :q OR l.trama LIKE :q OR l.titolo_ru LIKE :q OR l.sottotitolo_ru LIKE :q OR l.trama_ru LIKE :q)'; $par[':q'] = "%$q%"; }
if ($sc !== '') { $dove[] = 'c.slug = :sc'; $par[':sc'] = $sc; }
if ($tag !== '') { $dove[] = sql_concat("','", 'l.tags', "','") . ' LIKE :tag'; $par[':tag'] = "%,$tag,%"; }
$where = implode(' AND ', $dove);
$base = 'FROM libri l LEFT JOIN collane c ON c.id = l.collana_id WHERE ' . $where;

$st = db()->prepare("SELECT COUNT(*) $base"); $st->execute($par);
$totale = (int)$st->fetchColumn();
$pagine = max(1, (int)ceil($totale / $per)); $pag = min($pag, $pagine);
$st = db()->prepare("SELECT l.*, c.nome AS nome_collana, c.nome_ru AS nome_collana_ru $base ORDER BY " . ordine_catalogo() . " LIMIT $per OFFSET " . (($pag - 1) * $per));
$st->execute($par);
$libri = $st->fetchAll();

$evidenza = [];
if (!$filtrato && $pag === 1 && impost('mostra_evidenza') === '1')
    $evidenza = db()->query('SELECT l.*, c.nome AS nome_collana, c.nome_ru AS nome_collana_ru FROM libri l LEFT JOIN collane c ON c.id = l.collana_id WHERE l.evidenza = 1 AND ' . sql_visibile('l') . ' ORDER BY l.ordine, l.numero LIMIT 6')->fetchAll();
$collane = db()->query('SELECT c.slug, c.nome, c.nome_ru FROM collane c WHERE c.visibile = 1 AND EXISTS (SELECT 1 FROM libri l WHERE l.collana_id = c.id AND ' . sql_visibile('l') . ') ORDER BY c.ordine, c.nome')->fetchAll();

function url_catalogo(array $cambia): string {
    return 'index.php?' . http_build_query(array_filter(array_merge($_GET, $cambia), fn($v) => $v !== '' && $v !== null));
}

intestazione($filtrato ? t('Ricerca') : t('Catalogo'), '', impost_l('sottoslogan'));
if (!$filtrato): ?>
<section class="hero">
  <div class="hero-testo"><h1><?= e(impost_l('slogan')) ?></h1><p><?= e(impost_l('sottoslogan')) ?></p></div>
</section>
<?php endif; ?>

<?php if ($evidenza): ?>
<h2 class="sezione">⭐ <?= e(t('In evidenza')) ?></h2>
<div class="griglia"><?php foreach ($evidenza as $l) scheda_libro($l); ?></div>
<?php endif; ?>

<h2 class="sezione"><?= e(t('Catalogo')) ?></h2>
<form method="get" class="ricerca-pub">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('Cerca un libro…')) ?>">
  <?php if ($sc !== ''): ?><input type="hidden" name="collana" value="<?= e($sc) ?>"><?php endif; ?>
  <button class="bottone"><?= e(t('Cerca')) ?></button>
</form>
<?php if ($collane): ?>
<p class="chips"><a href="<?= e(url_catalogo(['collana' => '', 'p' => 1])) ?>" class="<?= $sc === '' ? 'att' : '' ?>"><?= e(t('Tutti')) ?></a>
<?php foreach ($collane as $c): ?><a href="<?= e(url_catalogo(['collana' => $c['slug'], 'p' => 1])) ?>" class="<?= $sc === $c['slug'] ? 'att' : '' ?>"><?= e(campo_l($c, 'nome')) ?></a><?php endforeach; ?></p>
<?php endif; ?>
<?php if ($tag !== ''): ?><p><?= e(t('Tag:')) ?> <b><?= e($tag) ?></b> · <a href="<?= e(url_catalogo(['tag' => ''])) ?>"><?= e(t('togli il filtro')) ?></a></p><?php endif; ?>

<?php if (!$libri): ?><p><?= e($filtrato ? t('Nessun libro trovato.') : t('Nessun libro pubblicato per ora.')) ?></p><?php endif; ?>
<div class="griglia"><?php foreach ($libri as $l) scheda_libro($l); ?></div>

<?php if ($pagine > 1): ?><div class="pag-pub">
<?php for ($i = 1; $i <= $pagine; $i++): ?><?= $i === $pag ? "<span>$i</span>" : '<a href="' . e(url_catalogo(['p' => $i])) . "\">$i</a>" ?><?php endfor; ?></div><?php endif; ?>
<?php piede();
