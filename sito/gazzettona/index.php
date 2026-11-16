<?php
// Archivio della Gazzettona di Vladone: elenca i PDF in questa cartella (gazzettona-<num>-<it|ru>.pdf).
require __DIR__ . '/../inc/pubblico.php';
$numeri = [];
foreach (glob(__DIR__ . '/gazzettona-*-*.pdf') as $f) {
    if (preg_match('/gazzettona-(.+)-(it|ru)\.pdf$/', basename($f), $m)) $numeri[$m[1]][$m[2]] = basename($f);
}
krsort($numeri, SORT_NATURAL);
intestazione('La Gazzettona di Vladone');
?>
<article class="pagina-testo">
<h1>La Gazzettona di Vladone</h1>
<p><em><?= e(t('Caldo come l\'Etna. Gratis, ma con un sorriso.')) ?></em></p>
<ul>
<?php foreach ($numeri as $n => $l): ?>
  <li><strong>№ <?= e(str_replace('_', '/', $n)) ?></strong>
  <?php foreach (['it' => 'Italiano', 'ru' => 'Русский'] as $k => $nome): if (isset($l[$k])): ?>
    · <a href="<?= e($l[$k]) ?>"><?= $nome ?> (PDF)</a>
  <?php endif; endforeach; ?></li>
<?php endforeach; ?>
</ul>
</article>
<?php piede();
