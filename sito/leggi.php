<?php
require __DIR__ . '/inc/pubblico.php';

$token = (string)($_GET['anteprima'] ?? '');
$l = libro_per_slug((string)($_GET['s'] ?? ''), $token);
if (!$l || $l['pdf'] === '') pagina_non_trovata();
if (!$l['anteprima']) conta_una_volta('letture', (int)$l['id']);
$suffisso = $l['anteprima'] ? '&anteprima=' . urlencode($token) : '';
?><!doctype html><html lang="it"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title><?= e($l['titolo']) ?> · Lettura</title>
<link rel="icon" type="image/png" href="<?= e(asset_emblema()) ?>">
<link rel="stylesheet" href="assets/stile.css"><?= stile_tema() ?></head>
<body class="lettore" data-pdf="<?= e(url_pdf_lettore($l['pdf'])) ?>" data-chiave="<?= e($l['slug']) ?>">
<div class="barra">
  <a class="indietro-libro" href="libro.php?s=<?= e($l['slug']) . $suffisso ?>" title="Torna alla scheda">←<span class="solo-pc"> <?= e($l['titolo']) ?></span></a>
  <span class="comandi">
    <button id="b-pannello" aria-label="Indice, segnalibri e ricerca" title="Indice, segnalibri, ricerca">☰</button>
    <button id="indietro" aria-label="Pagina precedente">‹</button>
    <span class="pos"><input id="vai" type="number" min="1" value="1" aria-label="Numero di pagina"> / <span id="tot">…</span></span>
    <button id="avanti" aria-label="Pagina successiva">›</button>
    <button id="meno" aria-label="Riduci" class="solo-pc">−</button>
    <button id="piu" aria-label="Ingrandisci" class="solo-pc">+</button>
    <button id="adatta" aria-label="Adatta alla larghezza" class="solo-pc" title="Adatta">⤢</button>
    <button id="b-segna" aria-label="Segnalibro" title="Segnalibro su questa pagina (b)">☆</button>
    <button id="tema" aria-label="Tema chiaro/scuro" title="Tema">◐</button>
    <button id="schermo" aria-label="Schermo intero" title="Schermo intero (f)">⛶</button>
  </span>
</div>
<div id="progresso"><i></i></div>

<aside id="pannello" hidden>
  <div class="schede-pannello">
    <button data-tab="indice" class="att">Indice</button><button data-tab="segnalibri">Segnalibri</button><button data-tab="cerca">Cerca</button>
    <button id="chiudi-pannello" aria-label="Chiudi">✕</button>
  </div>
  <div class="tab" id="tab-indice"></div>
  <div class="tab" id="tab-segnalibri" hidden></div>
  <div class="tab" id="tab-cerca" hidden>
    <form id="form-cerca"><input type="search" id="testo-cerca" placeholder="Cerca nel libro…" autocomplete="off"><button>Cerca</button></form>
    <p id="stato-cerca" class="muto"></p><ul id="risultati" class="elenco-pannello"></ul>
  </div>
</aside>

<div id="area"><canvas id="tela"></canvas><p id="stato">Caricamento…</p></div>
<script src="assets/vendor/pdf.min.js"></script>
<script src="assets/lettore.js"></script>
</body></html>
