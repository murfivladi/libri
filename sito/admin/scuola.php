<?php
// Vlad Editore Scuola: panoramica dei libri scolastici, esercizi e contenuti, impostazioni (chiave deAPI).
require __DIR__ . '/_admin.php';
require_once __DIR__ . '/../inc/classi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    if (($_POST['azione'] ?? '') === 'deapi') {
        $k = trim((string)($_POST['deapi_chiave'] ?? ''));
        if (!empty($_POST['togli'])) $k = '';
        if ($k !== '' || !empty($_POST['togli'])) { imposta('deapi_chiave', $k); registra('Chiave deAPI ' . ($k === '' ? 'tolta' : 'salvata')); flash($k === '' ? 'Chiave deAPI tolta.' : 'Chiave deAPI salvata.'); }
    }
    if (($_POST['azione'] ?? '') === 'codice_prof') { $c = nuovo_codice_prof(); registra('Nuovo codice per i prof'); flash('Nuovo codice per i prof: ' . $c . ' (quello vecchio non funziona più).'); }
    if (($_POST['azione'] ?? '') === 'chiudi_prof') { imposta('codice_prof', ''); flash('Registrazione dei prof chiusa.'); }
    if (($_POST['azione'] ?? '') === 'elimina_prof' && ($pid = (int)($_POST['prof'] ?? 0))) { elimina_prof($pid); registra('Prof eliminato dall\'admin', (string)$pid); flash('Prof eliminato con classi, studenti e risultati.'); }
    if (($_POST['azione'] ?? '') === 'sospendi_prof' && ($pid = (int)($_POST['prof'] ?? 0))) { db()->prepare('UPDATE prof SET stato = 1 - stato WHERE id = ?')->execute([$pid]); flash('Stato del prof cambiato.'); }
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

<?php
$profs = db()->query('SELECT p.*, (SELECT COUNT(*) FROM classi c WHERE c.prof_id = p.id) AS n_classi, (SELECT COUNT(*) FROM studenti s JOIN classi c ON c.id = s.classe_id WHERE c.prof_id = p.id) AS n_stud FROM prof p ORDER BY p.id DESC')->fetchAll();
$cp = codice_prof();
?>
<div class="pannello">
  <h2 style="margin-top:0">🍎 Prof e classi</h2>
  <p class="muto">I prof si registrano in <a href="../prof.php" target="_blank">prof.php ↗</a> con il <b>codice per i prof</b>: dallo solo agli insegnanti che conosci. Gli studenti entrano con il codice della classe, un nickname e un PIN (nessun dato personale).</p>
  <p>Codice per i prof: <?= $cp !== '' ? '<code style="font-size:1.1rem">' . e($cp) . '</code> <button type="button" class="bottone sec piccolo" data-copia="' . e($cp) . '">Copia</button>' : '<b>registrazione chiusa</b>' ?></p>
  <form method="post" class="azioni-riga"><?= csrf_campo() ?>
    <button class="bottone piccolo" name="azione" value="codice_prof"<?= $cp !== '' ? ' data-conferma="Creare un codice nuovo? Quello attuale smetterà di funzionare (i prof già registrati restano)."' : '' ?>><?= $cp !== '' ? '🔄 Nuovo codice' : 'Apri la registrazione (crea il codice)' ?></button>
    <?php if ($cp !== ''): ?><button class="bottone sec piccolo" name="azione" value="chiudi_prof">Chiudi la registrazione</button><?php endif; ?></form>
  <table class="tabella" style="margin-top:12px"><tr><th>Prof</th><th>Email</th><th>Classi</th><th>Studenti</th><th>Ultimo accesso</th><th></th></tr>
  <?php foreach ($profs as $pr): ?><tr><td><b><?= e($pr['nome']) ?></b> <?= $pr['stato'] ? '' : '<span class="stato cest">sospeso</span>' ?></td><td><?= e($pr['email']) ?></td><td><?= (int)$pr['n_classi'] ?></td><td><?= (int)$pr['n_stud'] ?></td><td class="muto"><?= e(formatta_data($pr['ultimo_accesso'] ?: null)) ?></td>
    <td><form method="post" class="azioni-riga"><?= csrf_campo() ?><input type="hidden" name="prof" value="<?= (int)$pr['id'] ?>">
      <button class="bottone sec piccolo" name="azione" value="sospendi_prof"><?= $pr['stato'] ? 'Sospendi' : 'Riattiva' ?></button>
      <button class="bottone pericolo piccolo" name="azione" value="elimina_prof" data-conferma="Eliminare il prof con tutte le sue classi, gli studenti e i risultati?">Elimina</button></form></td></tr>
  <?php endforeach; if (!$profs): ?><tr><td colspan="6" class="muto">Nessun prof registrato.</td></tr><?php endif; ?></table>
</div>

<div class="pannello">
  <p><a class="bottone" href="ai.php">✨ Apri lo Studio AI</a> <span class="muto">immagini con Vlad e Luce, voci, musica, animazioni</span></p>
  <h2 style="margin-top:0">deAPI (voci e immagini con l'AI)</h2>
  <p class="muto">Serve per creare l'audio con la voce AI direttamente dall'admin. La chiave si trova nella dashboard di deAPI. Stato: <?= $chiave !== '' ? '<b>chiave salvata</b> (…' . e(substr($chiave, -4)) . ')' : '<b>nessuna chiave</b>' ?>.</p>
  <form method="post" class="azioni-riga"><?= csrf_campo() ?><input type="hidden" name="azione" value="deapi">
    <input type="password" name="deapi_chiave" placeholder="Incolla qui la chiave deAPI" autocomplete="off" style="max-width:420px">
    <button class="bottone">Salva</button><?php if ($chiave !== ''): ?> <button class="bottone sec" name="togli" value="1" data-conferma="Togliere la chiave deAPI?">Togli</button><?php endif; ?>
  </form>
</div>
<?php admin_piede();
