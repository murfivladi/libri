<?php
// Il download dei PDF non esiste: i libri si leggono online. Questo indirizzo resta solo per i vecchi link.
require __DIR__ . '/inc/pubblico.php';
http_response_code(410);
header('Cache-Control: public, max-age=86400');
intestazione(t('Solo lettura online'));
?>
<div class="pagina-testo centro">
  <h1><?= e(t('I libri si leggono online')) ?></h1>
  <p><?= e(t('I PDF non si possono scaricare: ogni libro si legge qui, nel lettore a pagine.')) ?></p>
  <p><a class="bottone" href="index.php"><?= e(t('Vai al catalogo')) ?></a></p>
</div>
<?php piede();
