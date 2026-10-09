<?php
// Vlad Editore Scuola: panoramica dei libri scolastici, esercizi e contenuti, impostazioni (chiave deAPI).
require __DIR__ . '/_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    if (($_POST['azione'] ?? '') === 'deapi') {
        $k = trim((string)($_POST['deapi_chiave'] ?? ''));
        if (!empty($_POST['togli'])) $k = '';
        if ($k !== '' || !empty($_POST['togli'])) { imposta('deapi_chiave', $k); registra('Chiave deAPI ' . ($k === '' ? 'tolta' : 'salvata')); flash($k === '' ? 'Chiave deAPI tolta.' : 'Chiave deAPI salvata.'); }
    }
    torna('scuola.php');
}

$libri = db()->query("SELECT l.id, l.titolo, l.pubblicato, l.copertina, l.pdf,
    (SELECT COUNT(*) FROM esercizi e WHERE e.libro_id = l.id) AS n_es,
    (SELECT COUNT(*) FROM contenuti c WHERE c.libro_id = l.id) AS n_co,
    (SELECT COUNT(*) FROM zone z WHERE z.libro_id = l.id) AS n_zo
    FROM libri l WHERE l.sezione = 'scuola' AND l.eliminato IS NULL ORDER BY l.ordine, l.numero, l.id")->fetchAll();
$tot_es = (int)db()->query('SELECT COUNT(*) FROM esercizi')->fetchColumn();
$tot_co = (int)db()->query('SELECT COUNT(*) FROM contenuti')->fetchColumn();
$chiave = deapi_chiave();

admin_testata('🎒 Vlad Editore Scuola', 'scuola');
?>
<div class="carte">
  <div class="carta"><b><?= count($libri) ?></b><span>Libri scolastici</span></div>
  <a class="carta" href="esercizi.php"><b><?= $tot_es ?></b><span>Esercizi interattivi</span></a>
  <a class="carta" href="contenuti.php"><b><?= $tot_co ?></b><span>Audio, video e immagini</span></a>
</div>

<div class="pannello">
  <h2 style="margin-top:0">Libri scolastici</h2>
  <p class="muto">Un libro è «scolastico» quando nella sua scheda la Sezione è <b>🎒 Scuola</b>: non compare nel catalogo dei romanzi ma nella pagina <a href="../scuola.php" target="_blank">Scuola ↗</a>.</p>
  <table class="tabella"><tr><th>Libro</th><th>Esercizi</th><th>Contenuti</th><th>Zone</th><th></th></tr>
  <?php foreach ($libri as $l): ?>
    <tr><td><a href="libro.php?id=<?= (int)$l['id'] ?>"><b><?= e($l['titolo']) ?></b></a> <?= $l['pubblicato'] ? '' : '<span class="stato bozza">bozza</span>' ?><?= $l['pdf'] ? '' : ' <span class="muto">(manca il PDF)</span>' ?></td>
      <td><a href="esercizi.php?libro=<?= (int)$l['id'] ?>"><?= (int)$l['n_es'] ?></a></td>
      <td><a href="contenuti.php?libro=<?= (int)$l['id'] ?>"><?= (int)$l['n_co'] ?></a></td>
      <td><a href="zone.php?libro=<?= (int)$l['id'] ?>"><?= (int)$l['n_zo'] ?></a></td>
      <td class="azioni-riga"><a class="bottone piccolo" href="esercizi.php?nuovo=1&amp;libro=<?= (int)$l['id'] ?>">+ Esercizio</a> <a class="bottone sec piccolo" href="contenuti.php?nuovo=1&amp;libro=<?= (int)$l['id'] ?>">+ Audio/video</a> <a class="bottone sec piccolo" href="zone.php?libro=<?= (int)$l['id'] ?>">Zone</a></td></tr>
  <?php endforeach; if (!$libri): ?><tr><td colspan="5" class="muto">Nessun libro scolastico per ora.</td></tr><?php endif; ?>
  </table>
  <p><a class="bottone" href="libro.php?sezione=scuola">+ Nuovo libro scolastico</a></p>
</div>

<div class="pannello">
  <h2 style="margin-top:0">Come funziona</h2>
  <ol>
    <li>Carica il <b>PDF</b> del libro (Sezione: Scuola).</li>
    <li>Crea gli <b>esercizi</b> e gli <b>audio/video</b>: ognuno riceve un codice breve, per esempio <code>CFC-12</code>.</li>
    <li>Nel PDF, accanto al simbolo dell'esercizio, c'è scritto il codice e c'è il QR code (che porta a <code>apri.php?c=CFC-12</code>).</li>
    <li>Nel lettore del sito il codice viene <b>riconosciuto da solo</b>: toccando il simbolo si apre l'esercizio. Se serve, correggi o aggiungi le zone a mano in <b>Zone</b>.</li>
  </ol>
</div>

<div class="pannello">
  <h2 style="margin-top:0">deAPI (voci e immagini con l'AI)</h2>
  <p class="muto">Serve per creare l'audio con la voce AI direttamente dall'admin. La chiave si trova nella dashboard di deAPI. Stato: <?= $chiave !== '' ? '<b>chiave salvata</b> (…' . e(substr($chiave, -4)) . ')' : '<b>nessuna chiave</b>' ?>.</p>
  <form method="post" class="azioni-riga"><?= csrf_campo() ?><input type="hidden" name="azione" value="deapi">
    <input type="password" name="deapi_chiave" placeholder="Incolla qui la chiave deAPI" autocomplete="off" style="max-width:420px">
    <button class="bottone">Salva</button><?php if ($chiave !== ''): ?> <button class="bottone sec" name="togli" value="1" data-conferma="Togliere la chiave deAPI?">Togli</button><?php endif; ?>
  </form>
</div>
<?php admin_piede();
