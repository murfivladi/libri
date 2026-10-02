<?php
require __DIR__ . '/inc/pubblico.php';

$st = db()->prepare('SELECT * FROM pagine WHERE slug = ? AND visibile = 1');
$st->execute([(string)($_GET['s'] ?? '')]);
$p = $st->fetch();
if (!$p) pagina_non_trovata();
intestazione($p['titolo']);
?>
<article class="pagina-testo">
<h1><?= e($p['titolo']) ?></h1>
<?php if ($p['slug'] === 'chi-siamo'): ?><p class="centro"><img class="logo-grande" src="<?= e(asset_logo()) ?>" alt="Logo di <?= e(impost('nome_sito')) ?>" width="360"></p><?php endif; ?>
<?= md($p['contenuto']) ?>
</article>
<?php piede();
