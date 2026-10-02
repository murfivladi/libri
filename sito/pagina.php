<?php
require __DIR__ . '/inc/pubblico.php';

if (($_GET['s'] ?? '') === 'privacy') { header('Location: privacy.php', true, 301); exit; }
$st = db()->prepare('SELECT * FROM pagine WHERE slug = ? AND visibile = 1');
$st->execute([(string)($_GET['s'] ?? '')]);
$p = $st->fetch();
if (!$p) pagina_non_trovata();
$p = loc($p, ['titolo', 'contenuto']);
intestazione($p['titolo']);
?>
<article class="pagina-testo">
<h1><?= e($p['titolo']) ?></h1>
<?php if ($p['slug'] === 'chi-siamo'): ?><p class="centro"><img class="logo-grande" src="<?= e(asset_logo()) ?>" alt="<?= e(t('Logo di') . ' ' . impost('nome_sito')) ?>" width="360"></p><?php endif; ?>
<?= md($p['contenuto']) ?>
</article>
<?php piede();
