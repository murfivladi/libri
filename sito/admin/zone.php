<?php
// Zone cliccabili sulle pagine di un libro scolastico: si disegnano trascinando sulla pagina e aprono un esercizio o un contenuto.
// Quelle trovate da sole (il codice stampato nel PDF) si vedono tratteggiate in blu: non serve disegnarle.
require __DIR__ . '/_admin.php';
require_once __DIR__ . '/../inc/pubblico.php';

$lid = (int)($_GET['libro'] ?? 0);
$st = db()->prepare("SELECT * FROM libri WHERE id = ?"); $st->execute([$lid]);
$l = $st->fetch();
if (!$l) { flash('Scegli un libro scolastico.', 'errore'); torna('scuola.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $zone = json_decode((string)($_POST['zone'] ?? '[]'), true);
    if (!is_array($zone)) $zone = [];
    $codici = mappa_scuola_libro($lid)['codici'];
    $ok = 0;
    db()->beginTransaction();
    db()->prepare('DELETE FROM zone WHERE libro_id = ?')->execute([$lid]);
    $ins = db()->prepare('INSERT INTO zone (libro_id, pagina, x, y, w, h, codice) VALUES (?,?,?,?,?,?,?)');
    foreach (array_slice($zone, 0, 2000) as $z) {
        $c = normalizza_codice((string)($z['c'] ?? ''));
        $v = array_map(fn($k) => max(0, min(1000, (int)($z[$k] ?? 0))), ['x', 'y', 'w', 'h']);
        if (!isset($codici->$c) || (int)($z['p'] ?? 0) < 1 || $v[2] < 5 || $v[3] < 5) continue;
        $ins->execute([$lid, (int)$z['p'], $v[0], $v[1], $v[2], $v[3], $c]); $ok++;
    }
    db()->commit();
    registra('Zone salvate', $l['titolo'] . ": $ok");
    flash("Zone salvate: $ok.");
    torna("zone.php?libro=$lid&pagina=" . max(1, (int)($_POST['pagina'] ?? 1)));
}

$mappa = mappa_scuola_libro($lid);
admin_testata('Zone cliccabili · ' . $l['titolo'], 'scuola');
if ($l['pdf'] === ''): ?>
  <p class="errore">Questo libro non ha ancora il PDF: caricalo dalla <a href="libro.php?id=<?= $lid ?>">scheda del libro</a>.</p>
<?php admin_piede(); exit; endif; ?>
<p class="muto">Trascina sulla pagina per disegnare una zona, poi scegli a destra cosa apre. Le zone <span style="color:#1565c0">tratteggiate in blu</span> sono quelle trovate da sole dal codice stampato nel PDF: non serve ridisegnarle.</p>
<div class="zone-lavoro">
  <div>
    <div class="nav-pagine"><button type="button" class="bottone sec piccolo" id="z-prec">‹ Pagina prima</button>
      <label style="display:flex;gap:6px;align-items:center;font-weight:400">Pagina <input type="number" id="z-pagina" min="1" value="<?= max(1, (int)($_GET['pagina'] ?? 1)) ?>"> / <span id="z-tot">…</span></label>
      <button type="button" class="bottone sec piccolo" id="z-succ">Pagina dopo ›</button></div>
    <div class="pagina-zone" id="z-pagina-box"><canvas id="z-tela"></canvas></div>
  </div>
  <div class="pannello">
    <h2 style="margin-top:0">Zona scelta</h2>
    <p id="z-nessuna" class="muto">Nessuna: disegnane una o toccane una esistente.</p>
    <div id="z-dettagli" hidden>
      <label>Apre <select id="z-codice"><option value="">— scegli —</option>
        <?php foreach ($mappa['codici'] as $c => $i): ?><option value="<?= e($c) ?>"><?= e($c) ?> · <?= e($i['titolo'] !== '' ? $i['titolo'] : ($i['g'] === 'esercizio' ? 'esercizio' : $i['t'])) ?></option><?php endforeach; ?></select></label>
      <p><button type="button" class="bottone pericolo piccolo" id="z-togli">🗑 Togli la zona</button></p>
    </div>
    <?php if (!(array)$mappa['codici']): ?><p class="errore">Questo libro non ha ancora esercizi o contenuti: <a href="esercizi.php?nuovo=1&amp;libro=<?= $lid ?>">crea un esercizio</a>.</p><?php endif; ?>
    <form method="post" id="z-form"><?= csrf_campo() ?><input type="hidden" name="zone" id="z-dati"><input type="hidden" name="pagina" id="z-pag-salva">
      <button class="bottone">💾 Salva le zone</button></form>
    <p class="muto" id="z-conta"></p>
  </div>
</div>
<script>window.ZONE = <?= json_encode(['pdf' => con_prefisso(url_pdf_lettore($l['pdf']), '../'), 'codici' => $mappa['codici'], 'zone' => $mappa['zone']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="../assets/vendor/pdf.min.js"></script>
<script src="../assets/zone.js"></script>
<?php admin_piede();
