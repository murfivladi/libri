<?php
require __DIR__ . '/inc/bootstrap.php';
$st = db()->prepare('SELECT * FROM libri WHERE slug = ? AND pubblicato = 1 AND pdf != ""');
$st->execute([$_GET['s'] ?? '']);
$l = $st->fetch();
if (!$l) { http_response_code(404); intestazione('Non trovato'); echo '<p>Libro non trovato.</p>'; piede(); exit; }
?><!doctype html><html lang="it"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($l['titolo']) ?> · Lettura</title>
<link rel="stylesheet" href="assets/stile.css"></head>
<body class="lettore" data-pdf="uploads/pdf/<?= e($l['pdf']) ?>" data-chiave="<?= e($l['slug']) ?>">
<div class="barra">
  <a href="libro.php?s=<?= e($l['slug']) ?>">← <?= e($l['titolo']) ?></a>
  <span class="comandi">
    <button id="indietro" aria-label="Pagina precedente">‹</button>
    <span id="pos">…</span>
    <button id="avanti" aria-label="Pagina successiva">›</button>
    <button id="meno" aria-label="Riduci">−</button>
    <button id="piu" aria-label="Ingrandisci">+</button>
    <button id="tema" aria-label="Tema chiaro/scuro">◐</button>
  </span>
</div>
<div id="area"><canvas id="tela"></canvas><p id="stato">Caricamento…</p></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="assets/lettore.js"></script>
</body></html>
