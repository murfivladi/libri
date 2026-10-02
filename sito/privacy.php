<?php
// Informativa sulla privacy: modello con i dati del titolare (Admin → Lettori e privacy), modificabile.
require __DIR__ . '/inc/pubblico.php';

intestazione('Privacy', '', 'Informativa sul trattamento dei dati personali di ' . impost('nome_sito'));
?>
<article class="pagina-testo">
<h1>Privacy</h1>
<?= md(testo_privacy()) ?>
</article>
<?php piede();
