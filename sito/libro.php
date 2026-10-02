<?php
require __DIR__ . '/inc/pubblico.php';

$token = (string)($_GET['anteprima'] ?? '');
$l = libro_per_slug((string)($_GET['s'] ?? ''), $token);
if (!$l) pagina_non_trovata();

$altri = [];
if ($l['collana_id']) {
    $st = db()->prepare('SELECT l.*, c.nome AS nome_collana FROM libri l LEFT JOIN collane c ON c.id = l.collana_id WHERE l.collana_id = ? AND l.id != ? AND ' . sql_visibile('l') . ' ORDER BY l.numero, l.id');
    $st->execute([$l['collana_id'], $l['id']]);
    $altri = $st->fetchAll();
}
$suffisso = $l['anteprima'] ? '&anteprima=' . urlencode($token) : '';
$descr = $l['meta'] !== '' ? $l['meta'] : $l['trama'];
intestazione($l['titolo'], '', $descr);
if ($l['anteprima']): ?><p class="avviso">👁 Anteprima privata: questo libro non è ancora visibile al pubblico.</p><?php endif; ?>
<article class="libro">
  <?php if ($l['copertina']): ?><img class="copertina" src="uploads/cover/<?= e($l['copertina']) ?>" alt="Copertina di <?= e($l['titolo']) ?>"><?php endif; ?>
  <div>
    <h1><?= e($l['titolo']) ?></h1>
    <?php if ($l['sottotitolo']): ?><p class="sottotitolo"><?= e($l['sottotitolo']) ?></p><?php endif; ?>
    <p class="meta">
      <?php if ($l['autore']): ?>di <b><?= e($l['autore']) ?></b><?php endif; ?>
      <?php if ($l['nome_collana'] && $l['collana_visibile']): ?> · <a href="index.php?collana=<?= e($l['slug_collana']) ?>"><?= e($l['nome_collana']) ?></a><?= $l['numero'] ? ' (libro ' . (int)$l['numero'] . ')' : '' ?><?php endif; ?>
    </p>
    <p class="meta"><?= e(implode(' · ', array_filter([$l['genere'], $l['anno'] ? (string)$l['anno'] : '', $l['pagine_n'] ? $l['pagine_n'] . ' pagine' : '', strtoupper($l['lingua']), $l['isbn'] ? 'ISBN ' . $l['isbn'] : '']))) ?></p>
    <?php if ($l['trama']): ?><div class="trama"><?= nl2br(e($l['trama'])) ?></div><?php endif; ?>
    <?php if ($l['tags']): ?><p class="chips"><?php foreach (tags_di($l['tags']) as $t): ?><a href="index.php?tag=<?= urlencode($t) ?>">#<?= e($t) ?></a><?php endforeach; ?></p><?php endif; ?>
    <?php if ($l['pdf']): ?>
    <p class="azioni">
      <a class="bottone" href="leggi.php?s=<?= e($l['slug']) . $suffisso ?>">Leggi online</a>
      <a class="bottone secondario" href="scarica.php?s=<?= e($l['slug']) . $suffisso ?>">Scarica PDF</a>
    </p>
    <?php endif; ?>
    <?php if (impost('contatori_pubblici') === '1'): ?><p class="meta">👁 <?= (int)$l['letture'] ?> letture · ⬇ <?= (int)$l['download'] ?> download</p><?php endif; ?>
  </div>
</article>
<?php if ($altri): ?>
<h2 class="sezione">Nella stessa collana</h2>
<div class="griglia"><?php foreach ($altri as $a) scheda_libro($a); ?></div>
<?php endif; ?>
<?php piede();
