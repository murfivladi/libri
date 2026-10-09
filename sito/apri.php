<?php
// Apre un esercizio interattivo o un contenuto (audio, video, immagine) dal suo codice: apri.php?c=CFC-12.
// È l'indirizzo dei QR code del PDF; con &embed=1 si apre dentro la finestra del lettore (senza testata del sito).
require __DIR__ . '/inc/pubblico.php';
require_once __DIR__ . '/inc/classi.php';

$c = normalizza_codice((string)($_GET['c'] ?? ''));
$embed = !empty($_GET['embed']);
$trovato = $c !== '' ? trova_codice($c) : null;
// esercizi di libri non ancora pubblicati: li vede solo l'admin (anteprima)
if ($trovato && !libro_pubblico((int)$trovato[1]['libro_id']) && empty($_SESSION['admin'])) $trovato = null;
if (!$trovato && $c !== '') http_response_code(404);

$libro = null;
if ($trovato && $trovato[1]['libro_id']) {
    $st = db()->prepare('SELECT slug, titolo FROM libri WHERE id = ?'); $st->execute([$trovato[1]['libro_id']]);
    $libro = $st->fetch() ?: null;
}
[$genere, $r] = $trovato ?: [null, null];
$titolo = $r ? ($r['titolo'] !== '' ? $r['titolo'] : $r['codice']) : 'Apri un codice';
$json = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

if ($embed) {
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
       . '<title>' . e($titolo) . '</title><link rel="stylesheet" href="assets/scuola.css"></head><body class="incorporato"><main class="contenuto-apri">';
} else {
    intestazione($titolo, '', $r ? (string)($r['consegna'] ?? $r['testo'] ?? '') : '', ['css' => ['assets/scuola.css'], 'noindex' => !$trovato]);
    echo '<div class="contenuto-apri">';
}

if (!$trovato): ?>
  <h1>Apri un codice</h1>
  <?php if ($c !== ''): ?><p class="errore">Il codice <b><?= e($c) ?></b> non esiste. Controlla di averlo scritto bene (lettere, trattino, numero).</p><?php endif; ?>
  <div class="box-codice"><form action="apri.php" method="get"><?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
    <input type="text" name="c" value="<?= e($c) ?>" placeholder="es. CFC-12" aria-label="Codice" required maxlength="12" autocomplete="off" autocapitalize="characters"><button class="bottone arancio">Apri</button></form></div>
<?php else: ?>
  <?php if (!$embed): ?>
  <div class="apri-testata"><h1><?= e($titolo) ?></h1><span class="codice"><?= e($r['codice']) ?></span></div>
  <?php if ($libro): ?><p class="apri-libro">Dal libro <a href="libro.php?s=<?= e($libro['slug']) ?>"><?= e($libro['titolo']) ?></a><?php if ($r['pagina']): ?>, <a href="leggi.php?s=<?= e($libro['slug']) ?>&amp;pagina=<?= (int)$r['pagina'] ?>">pagina <?= (int)$r['pagina'] ?></a><?php endif; ?></p><?php endif; ?>
  <?php endif; ?>

  <?php if ($genere === 'esercizio'): $stud = studente_corrente(); ?>
    <?php if ($stud): ?><p class="banner-studente">👤 <b><?= e($stud['nick']) ?></b> · classe <?= e($stud['classe']) ?> — il risultato arriva al tuo prof.</p><?php endif; ?>
    <div id="esercizio"<?= $stud ? ' data-api="api-scuola.php" data-csrf="' . e(csrf()) . '"' : '' ?>></div>
    <script src="assets/esercizi.js"></script>
    <script>VSEsercizio.monta(document.getElementById('esercizio'), <?= $json(['codice' => $r['codice'], 'tipo' => $r['tipo'], 'titolo' => $r['titolo'], 'consegna' => $r['consegna'], 'dati' => dati_esercizio($r)]) ?>);</script>
    <?php if ($stud): ?><script src="assets/scuola-invia.js"></script><?php endif; ?>
  <?php else: $src = url_media($r['file']); ?>
    <div class="media-box">
    <?php if ($r['file'] === ''): ?><p class="muto">Questo contenuto non ha ancora un file.</p>
    <?php elseif ($r['tipo'] === 'audio'): ?><audio controls preload="metadata" src="<?= e($src) ?>"></audio>
    <?php elseif ($r['tipo'] === 'video'): ?><video controls playsinline preload="metadata" src="<?= e($src) ?>"></video>
    <?php else: ?><img src="<?= e($src) ?>" alt="<?= e($titolo) ?>"><?php endif; ?>
    </div>
    <?php if (trim($r['testo']) !== ''): ?><details class="trascrizione" <?= $r['tipo'] === 'immagine' ? 'open' : '' ?>><summary><?= $r['tipo'] === 'immagine' ? 'Descrizione' : 'Testo' ?></summary><?= nl2br(e($r['testo'])) ?></details><?php endif; ?>
  <?php endif; ?>
<?php endif;

// dentro la finestra del lettore: Esc chiude la finestra anche quando si sta scrivendo nell'esercizio
if ($embed) echo "<script>document.addEventListener('keydown',function(e){if(e.key==='Escape'&&window.parent!==window)window.parent.postMessage({scuola:'chiudi'},location.origin);});</script></main></body></html>";
else { echo '</div>'; piede(); }
