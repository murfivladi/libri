<?php
require __DIR__ . '/inc/bootstrap.php';
$libri = db()->query('SELECT * FROM libri ORDER BY ordine, numero, id')->fetchAll();
intestazione('Ordina i libri', 'Ordina i libri di Vlad Editore: compila il modulo e ti contatteremo per la consegna.');
?>
<section class="hero">
  <div class="hero-testo"><h1>Ordina il tuo libro</h1><p>Scegli il libro, compila il modulo: Vlad Editore riceve subito l'ordine e ti scrive per la consegna.</p></div>
</section>
<h2 class="sezione">Catalogo</h2>
<?php if (!$libri): ?><p>Nessun libro disponibile per ora.</p><?php endif; ?>
<div class="griglia">
<?php foreach ($libri as $l): ?>
  <a class="scheda" href="ordina.php?s=<?= e($l['slug']) ?>">
    <?php if ($l['copertina']): ?><img src="<?= e($l['copertina']) ?>" alt="Copertina di <?= e($l['titolo']) ?>" loading="lazy" width="400" height="600"><?php endif; ?>
    <span class="titolo"><?= e($l['titolo']) ?></span>
    <span class="meta"><?= e($l['serie']) ?><?= $l['numero'] ? ' · n. ' . (int)$l['numero'] : '' ?></span>
    <span class="prezzo"><?= e(euro((float)$l['prezzo'])) ?></span>
  </a>
<?php endforeach; ?>
</div>
<?php piede();
