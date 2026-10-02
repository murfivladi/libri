<?php
require __DIR__ . '/inc/bootstrap.php';
$st = db()->prepare('SELECT * FROM libri WHERE slug = ? AND pubblicato = 1');
$st->execute([$_GET['s'] ?? '']);
$l = $st->fetch();
if (!$l) { http_response_code(404); intestazione('Non trovato'); echo '<p>Libro non trovato.</p>'; piede(); exit; }
intestazione($l['titolo']);
?>
<article class="libro">
  <?php if ($l['copertina']): ?><img class="copertina" src="uploads/cover/<?= e($l['copertina']) ?>" alt="Copertina di <?= e($l['titolo']) ?>"><?php endif; ?>
  <div>
    <h1><?= e($l['titolo']) ?></h1>
    <p class="meta"><?= e($l['collana']) ?><?= $l['numero'] ? ' · libro ' . (int)$l['numero'] : '' ?><?= $l['genere'] ? ' · ' . e($l['genere']) : '' ?></p>
    <p class="trama"><?= nl2br(e($l['trama'])) ?></p>
    <?php if ($l['pdf']): ?>
    <p class="azioni">
      <a class="bottone" href="leggi.php?s=<?= e($l['slug']) ?>">Leggi online</a>
      <a class="bottone secondario" href="uploads/pdf/<?= e($l['pdf']) ?>" download>Scarica PDF</a>
    </p>
    <?php endif; ?>
  </div>
</article>
<?php piede();
