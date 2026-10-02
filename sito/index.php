<?php
require __DIR__ . '/inc/bootstrap.php';
$libri = db()->query('SELECT * FROM libri WHERE pubblicato = 1 ORDER BY collana, numero, id')->fetchAll();
intestazione('Catalogo');
?>
<h1>Catalogo</h1>
<?php if (!$libri): ?><p>Nessun libro pubblicato per ora.</p><?php endif; ?>
<div class="griglia">
<?php foreach ($libri as $l): ?>
  <a class="scheda" href="libro.php?s=<?= e($l['slug']) ?>">
    <?php if ($l['copertina']): ?><img src="uploads/cover/<?= e($l['copertina']) ?>" alt="Copertina di <?= e($l['titolo']) ?>" loading="lazy"><?php endif; ?>
    <span class="titolo"><?= e($l['titolo']) ?></span>
    <span class="meta"><?= e($l['collana']) ?><?= $l['numero'] ? ' · n. ' . (int)$l['numero'] : '' ?></span>
  </a>
<?php endforeach; ?>
</div>
<?php piede();
