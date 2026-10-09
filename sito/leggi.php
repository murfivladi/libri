<?php
require __DIR__ . '/inc/pubblico.php';

$token = (string)($_GET['anteprima'] ?? '');
$l = libro_per_slug((string)($_GET['s'] ?? ''), $token);
if ($l && !isset($_GET['lingua']) && lingua() === 'ru' && $l['pdf_ru'] !== '') $_GET['lingua'] = 'ru';   // sito in russo: si apre il PDF russo se c'è
if (!$l || ($l['pdf'] === '' && $l['pdf_ru'] === '')) pagina_non_trovata();
// lingua: ?lingua=ru apre la versione russa (se c'è); se c'è un solo PDF si apre quello
$ru = $l['pdf_ru'] !== '' && (($_GET['lingua'] ?? '') === 'ru' || $l['pdf'] === '');
$file_pdf = $ru ? $l['pdf_ru'] : $l['pdf'];
$altra = $l['pdf'] !== '' && $l['pdf_ru'] !== '';
if (!$l['anteprima']) conta_una_volta('letture', (int)$l['id']);
$lt = $l['anteprima'] ? null : lettore_corrente();
$sync = !$ru && $lt && $lt['privacy_versione'] === privacy_versione();
$suffisso = $l['anteprima'] ? '&anteprima=' . urlencode($token) : '';
$scuola = $l['sezione'] === 'scuola';
?><!doctype html><html lang="<?= lingua() ?>"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">
<meta name="robots" content="noindex">
<title><?= e($l['titolo']) ?> · <?= e(t('Lettura')) ?></title>
<link rel="icon" type="image/png" href="<?= e(asset_emblema()) ?>">
<link rel="stylesheet" href="assets/stile.css"><?php if ($scuola): ?><link rel="stylesheet" href="assets/scuola.css"><?php endif; ?><?= stile_tema() ?></head>
<body class="lettore<?= $scuola ? ' lettore-scuola' : '' ?>" data-pdf="<?= e(url_pdf_lettore($file_pdf)) ?>" data-chiave="<?= e($l['slug'] . ($ru ? '-ru' : '')) ?>"<?php if ($sync): ?> data-api="api-lettore.php" data-csrf="<?= e(csrf()) ?>"<?php elseif (!$l['anteprima'] && registrazione_aperta()): ?> data-accedi="account.php?a=accedi&amp;ritorno=<?= e(urlencode('leggi.php?s=' . $l['slug'])) ?>"<?php endif; ?>>
<div class="barra">
  <a class="indietro-libro" href="libro.php?s=<?= e($l['slug']) . $suffisso ?>" title="<?= e(t('Torna alla scheda')) ?>">←<span class="solo-pc"> <?= e($l['titolo']) ?></span></a>
  <span class="comandi">
    <?php if ($altra): ?><a class="lingua-lettore" href="leggi.php?s=<?= e($l['slug']) ?><?= $ru ? '' : '&amp;lingua=ru' ?><?= $suffisso ?>" title="<?= $ru ? 'Leggi in italiano' : 'Читать на русском' ?>"><?= $ru ? 'IT' : 'RU' ?></a><?php endif; ?>
    <button id="b-pannello" aria-label="<?= e(t('Indice, segnalibri e ricerca')) ?>" title="<?= e(t('Indice, segnalibri, ricerca')) ?>">☰</button>
    <button id="indietro" aria-label="<?= e(t('Pagina precedente')) ?>">‹</button>
    <span class="pos"><input id="vai" type="number" min="1" value="1" aria-label="<?= e(t('Numero di pagina')) ?>"><span id="coppia"></span> / <span id="tot">…</span></span>
    <button id="avanti" aria-label="<?= e(t('Pagina successiva')) ?>">›</button>
    <button id="meno" aria-label="<?= e(t('Rimpicciolisci')) ?>" class="solo-pc">−</button>
    <button id="piu" aria-label="<?= e(t('Ingrandisci')) ?>" class="solo-pc">+</button>
    <button id="adatta" aria-label="<?= e(t('Zoom normale')) ?>" class="solo-pc" title="<?= e(t('Zoom normale')) ?> (0)">⤢</button>
    <button id="b-segna" aria-label="<?= e(t('Segnalibro')) ?>" title="<?= e(t('Segnalibro su questa pagina')) ?> (b)">☆</button>
    <button id="tema" aria-label="<?= e(t('Tema chiaro/scuro')) ?>" title="<?= e(t('Tema')) ?>">◐</button>
    <button id="schermo" aria-label="<?= e(t('Schermo intero')) ?>" title="<?= e(t('Schermo intero')) ?> (f)">⛶</button>
  </span>
</div>
<div id="progresso"><i></i></div>

<aside id="pannello" hidden>
  <div class="schede-pannello">
    <button data-tab="indice" class="att"><?= e(t('Indice')) ?></button><button data-tab="segnalibri"><?= e(t('Segnalibri')) ?></button><button data-tab="cerca"><?= e(t('Cerca')) ?></button>
    <button id="chiudi-pannello" aria-label="<?= e(t('Chiudi')) ?>">✕</button>
  </div>
  <div class="tab" id="tab-indice"></div>
  <div class="tab" id="tab-segnalibri" hidden></div>
  <div class="tab" id="tab-cerca" hidden>
    <form id="form-cerca"><input type="search" id="testo-cerca" placeholder="<?= e(t('Cerca nel libro…')) ?>" autocomplete="off"><button><?= e(t('Cerca')) ?></button></form>
    <p id="stato-cerca" class="muto"></p><ul id="risultati" class="elenco-pannello"></ul>
  </div>
</aside>

<div id="scena">
  <div id="zoom"><div id="libro"></div></div>
  <button id="freccia-sx" class="freccia sx" aria-label="<?= e(t('Pagina precedente')) ?>">‹</button>
  <button id="freccia-dx" class="freccia dx" aria-label="<?= e(t('Pagina successiva')) ?>">›</button>
  <p id="stato"><?= e(t('Caricamento…')) ?></p>
</div>
<script src="assets/vendor/pdf.min.js"></script>
<script src="assets/vendor/page-flip.browser.js"></script>
<script>window.TR = <?= json_encode(lettore_traduzioni(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php if ($scuola): ?>
<div class="finestra-scuola" id="finestra-scuola" hidden role="dialog" aria-modal="true" aria-labelledby="titolo-finestra">
  <div class="riquadro"><div class="testa"><span id="titolo-finestra"></span><button type="button" id="chiudi-finestra" aria-label="Chiudi">✕</button></div><iframe id="cornice-scuola" title="Esercizio"></iframe></div>
</div>
<script>window.SCUOLA = <?= json_encode(mappa_scuola_libro((int)$l['id']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="assets/scuola-lettore.js"></script>
<?php endif; ?>
<script src="assets/lettore.js"></script>
</body></html>
