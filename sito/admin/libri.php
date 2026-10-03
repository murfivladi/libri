<?php
require __DIR__ . '/_admin.php';
require __DIR__ . '/../inc/libri.php';

// ---------- azioni (singole e di massa) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $ritorno = $_SERVER['REQUEST_URI'];
    if (!empty($_POST['riga'])) {                       // pulsante di una riga: "id:azione"
        [$id, $azione] = array_pad(explode(':', $_POST['riga'], 2), 2, '');
        $ids = [(int)$id];
    } else {
        $azione = $_POST['azione'] ?? '';
        $ids = $_POST['ids'] ?? [];
    }
    if ($azione === 'csv') {
        $in = implode(',', array_map('intval', $ids)) ?: '0';
        esporta_csv(db()->query("SELECT * FROM libri WHERE id IN ($in) ORDER BY ordine, numero, id")->fetchAll(), 'libri-selezionati.csv');
    }
    if ($azione === 'salva_ordine') { riordina_libri($ids); registra('Libri riordinati (trascinamento)'); http_response_code(204); exit; }
    flash(azione_libri($azione, $ids, $_POST));
    torna($ritorno);
}

// ---------- filtri ----------
$q = trim($_GET['q'] ?? '');
$stato = $_GET['stato'] ?? 'tutti';
$collana = $_GET['collana'] ?? '';
$tag = trim($_GET['tag'] ?? '');
$ora = date('Y-m-d H:i:s');
$ordini = ['manuale' => 'l.ordine, l.numero, l.id', 'titolo' => sql_nocase('l.titolo'), 'numero' => 'l.numero', 'creato' => 'l.creato', 'aggiornato' => 'l.aggiornato',
           'letture' => 'l.letture','stato' => 'l.pubblicato', 'collana' => sql_nocase('c.nome')];
$ord = isset($ordini[$_GET['ord'] ?? '']) ? $_GET['ord'] : 'manuale';
$dir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
$per = (int)($_GET['per'] ?? 25);
if (!in_array($per, [10, 25, 50, 100], true)) $per = 25;
$pag = max(1, (int)($_GET['p'] ?? 1));

$dove = []; $par = [];
$dove[] = $stato === 'cestino' ? 'l.eliminato IS NOT NULL' : 'l.eliminato IS NULL';
switch ($stato) {
    case 'pubblicati': $dove[] = sql_visibile('l'); break;
    case 'bozze': $dove[] = 'l.pubblicato = 0'; break;
    case 'programmati': $dove[] = "l.pubblicato = 1 AND l.pubblica_il > '$ora'"; break;
    case 'evidenza': $dove[] = 'l.evidenza = 1'; break;
    case 'senza_pdf': $dove[] = "l.pdf = '' AND l.pdf_ru = ''"; break;
    case 'senza_copertina': $dove[] = "l.copertina = ''"; break;
    case 'senza_trama': $dove[] = "l.trama = ''"; break;
}
if ($q !== '') {
    $dove[] = '(l.titolo LIKE :q OR l.sottotitolo LIKE :q OR l.autore LIKE :q OR l.tags LIKE :q OR l.genere LIKE :q OR l.isbn LIKE :q OR l.trama LIKE :q OR l.note LIKE :q OR l.slug LIKE :q)';
    $par[':q'] = "%$q%";
}
if ($collana === 'nessuna') $dove[] = 'l.collana_id = 0';
elseif ((int)$collana > 0) { $dove[] = 'l.collana_id = :col'; $par[':col'] = (int)$collana; }
if ($tag !== '') { $dove[] = sql_concat("','", 'l.tags', "','") . ' LIKE :tag'; $par[':tag'] = "%,$tag,%"; }
$where = implode(' AND ', $dove);

$st = db()->prepare("SELECT COUNT(*) FROM libri l WHERE $where"); $st->execute($par);
$totale = (int)$st->fetchColumn();
$pagine = max(1, (int)ceil($totale / $per));
$pag = min($pag, $pagine);
$st = db()->prepare("SELECT l.*, c.nome AS nome_collana FROM libri l LEFT JOIN collane c ON c.id = l.collana_id WHERE $where ORDER BY {$ordini[$ord]} $dir, l.id LIMIT $per OFFSET " . (($pag - 1) * $per));
$st->execute($par);
$libri = $st->fetchAll();

$collane = db()->query('SELECT id, nome FROM collane ORDER BY ordine, nome')->fetchAll();
$tutti_tag = [];
foreach (db()->query("SELECT tags FROM libri WHERE tags != '' AND eliminato IS NULL")->fetchAll(PDO::FETCH_COLUMN) as $t) foreach (tags_di($t) as $x) $tutti_tag[$x] = ($tutti_tag[$x] ?? 0) + 1;
ksort($tutti_tag);

$conta = fn(string $w) => (int)db()->query("SELECT COUNT(*) FROM libri l WHERE $w")->fetchColumn();
$schede = [
    'tutti' => ['Tutti', $conta('l.eliminato IS NULL')], 'pubblicati' => ['Pubblicati', $conta('l.eliminato IS NULL AND ' . sql_visibile('l'))],
    'bozze' => ['Bozze', $conta('l.eliminato IS NULL AND l.pubblicato = 0')], 'programmati' => ['Programmati', $conta("l.eliminato IS NULL AND l.pubblicato = 1 AND l.pubblica_il > '$ora'")],
    'evidenza' => ['In evidenza', $conta('l.eliminato IS NULL AND l.evidenza = 1')], 'senza_pdf' => ['Senza PDF', $conta("l.eliminato IS NULL AND l.pdf = '' AND l.pdf_ru = ''")],
    'senza_copertina' => ['Senza copertina', $conta("l.eliminato IS NULL AND l.copertina = ''")], 'senza_trama' => ['Senza trama', $conta("l.eliminato IS NULL AND l.trama = ''")],
    'cestino' => ['🗑 Cestino', $conta('l.eliminato IS NOT NULL')],
];

function url_lista(array $cambia = []): string {
    $p = array_merge($_GET, $cambia);
    return 'libri.php?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== null));
}
function th_ord(string $et, string $chiave): string {
    global $ord, $dir;
    $nuova = ($ord === $chiave && $dir === 'ASC') ? 'desc' : 'asc';
    $freccia = $ord === $chiave ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
    return '<th><a href="' . e(url_lista(['ord' => $chiave, 'dir' => $nuova, 'p' => 1])) . '">' . $et . $freccia . '</a></th>';
}

admin_testata($stato === 'cestino' ? 'Cestino' : 'Libri', 'libri');
?>
<div class="schede">
<?php foreach ($schede as $k => [$et, $c]): ?>
  <a href="<?= e(url_lista(['stato' => $k === 'tutti' ? '' : $k, 'p' => 1])) ?>" class="<?= $stato === $k ? 'att' : '' ?>"><?= e($et) ?> (<?= $c ?>)</a>
<?php endforeach; ?>
</div>

<form method="get" class="filtri">
  <?php if ($stato !== 'tutti'): ?><input type="hidden" name="stato" value="<?= e($stato) ?>"><?php endif; ?>
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cerca titolo, autore, tag, trama…">
  <select name="collana"><option value="">Tutte le collane</option><option value="nessuna" <?= $collana === 'nessuna' ? 'selected' : '' ?>>— senza collana —</option>
    <?php foreach ($collane as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$collana === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select>
  <select name="tag"><option value="">Tutti i tag</option>
    <?php foreach ($tutti_tag as $t => $c): ?><option value="<?= e($t) ?>" <?= $tag === $t ? 'selected' : '' ?>><?= e($t) ?> (<?= $c ?>)</option><?php endforeach; ?></select>
  <select name="per"><?php foreach ([10, 25, 50, 100] as $x): ?><option value="<?= $x ?>" <?= $per === $x ? 'selected' : '' ?>><?= $x ?> per pagina</option><?php endforeach; ?></select>
  <button class="bottone piccolo">Filtra</button>
  <?php if ($q !== '' || $collana !== '' || $tag !== ''): ?><a class="bottone sec piccolo" href="<?= e(url_lista(['q' => '', 'collana' => '', 'tag' => '', 'p' => 1])) ?>">Azzera</a><?php endif; ?>
</form>

<form method="post" id="lista">
<?= csrf_campo() ?>
<?php $ordina = $ord === 'manuale' && $dir === 'ASC' && $stato !== 'cestino'; ?>
<?php if ($ordina && count($libri) > 1): ?><p class="muto">☰ Trascina la maniglia a sinistra per cambiare l'ordine dei libri (anche col dito).</p><?php endif; ?>
<table class="tabella"<?= $ordina ? ' data-ordina="libri" data-csrf="' . e(csrf()) . '"' : '' ?>>
<thead><tr><th></th><th><input type="checkbox" data-tutti aria-label="Seleziona tutti"></th><th></th>
  <?= th_ord('Titolo', 'titolo') ?><?= th_ord('Collana', 'collana') ?><?= th_ord('N.', 'numero') ?><?= th_ord('Stato', 'stato') ?>
  <?= th_ord('👁', 'letture') ?><?= th_ord('Modificato', 'aggiornato') ?><th>Azioni</th></tr></thead><tbody>
<?php foreach ($libri as $l):
    $id = (int)$l['id'];
    $prog = $l['pubblicato'] && $l['pubblica_il'] > $ora;
    if ($l['eliminato']) { $cl = 'cest'; $tx = 'cestino'; } elseif ($prog) { $cl = 'prog'; $tx = 'dal ' . formatta_data($l['pubblica_il']); } elseif ($l['pubblicato']) { $cl = 'pub'; $tx = 'pubblicato'; } else { $cl = 'bozza'; $tx = 'bozza'; }
?>
<tr data-id="<?= $id ?>">
  <?= $ordina ? '<td class="maniglia" title="Trascina per riordinare">⠿</td>' : '<td></td>' ?>
  <td><input type="checkbox" name="ids[]" value="<?= $id ?>"></td>
  <td><?php if ($l['copertina']): ?><img class="mini-cop" src="<?= e(con_prefisso(miniatura($l['copertina'], 120), '../')) ?>" alt="" loading="lazy"><?php endif; ?></td>
  <td><a href="libro.php?id=<?= $id ?>"><b><?= e($l['titolo']) ?></b></a><?= $l['evidenza'] ? ' ⭐' : '' ?>
    <?php if ($l['sottotitolo']): ?><br><span class="muto"><?= e($l['sottotitolo']) ?></span><?php endif; ?>
    <br><span class="muto"><?= !$l['pdf'] && !$l['pdf_ru'] ? '⚠ no PDF ' : '' ?><?= !$l['copertina'] ? '⚠ no copertina ' : '' ?><?= e(implode(' · ', tags_di($l['tags']))) ?></span></td>
  <td><?= e($l['nome_collana'] ?? '') ?></td>
  <td><?= $l['numero'] ? (int)$l['numero'] : '' ?></td>
  <td><span class="stato <?= $cl ?>"><?= e($tx) ?></span></td>
  <td><?= (int)$l['letture'] ?></td>  <td class="muto"><?= e(formatta_data($l['aggiornato'])) ?></td>
  <td><div class="azioni-riga">
    <?php if ($l['eliminato']): ?>
      <button class="mini" name="riga" value="<?= $id ?>:ripristina">Ripristina</button>
      <button class="mini" name="riga" value="<?= $id ?>:elimina_def" data-conferma="Eliminare per sempre «<?= e($l['titolo']) ?>» e i suoi file?">Elimina</button>
    <?php else: ?>
      <a class="mini bottone sec piccolo" href="libro.php?id=<?= $id ?>">Modifica</a>
      <button class="mini" name="riga" value="<?= $id ?>:toggle"><?= $l['pubblicato'] ? 'Nascondi' : 'Pubblica' ?></button>
      <button class="mini" name="riga" value="<?= $id ?>:evidenza_toggle" title="Metti/togli in evidenza">⭐</button>
      <button class="mini" name="riga" value="<?= $id ?>:su" title="Su">↑</button>
      <button class="mini" name="riga" value="<?= $id ?>:giu" title="Giù">↓</button>
      <button class="mini" name="riga" value="<?= $id ?>:duplica" title="Duplica">⧉</button>
      <button class="mini" name="riga" value="<?= $id ?>:cestino" title="Sposta nel cestino">🗑</button>
    <?php endif; ?>
  </div></td>
</tr>
<?php endforeach; if (!$libri): ?>
<tr><td colspan="11" class="muto">Nessun libro trovato. <a href="libro.php">Crea un libro</a>.</td></tr>
<?php endif; ?>
</tbody></table>

<?php if ($libri): ?>
<div class="barra-massa">
  <b>Con i selezionati:</b>
  <select name="azione" id="azione-massa">
    <?php if ($stato === 'cestino'): ?>
      <option value="ripristina">Ripristina</option><option value="elimina_def">Elimina per sempre</option>
    <?php else: ?>
      <optgroup label="Pubblicazione"><option value="pubblica">Pubblica</option><option value="nascondi">Nascondi (bozza)</option><option value="programma">Programma pubblicazione…</option><option value="togli_programma">Togli programmazione</option><option value="evidenza_si">Metti in evidenza</option><option value="evidenza_no">Togli evidenza</option></optgroup>
      <optgroup label="Organizzazione"><option value="sposta_collana">Sposta in una collana…</option><option value="aggiungi_tag">Aggiungi tag…</option><option value="rimuovi_tag">Togli tag…</option><option value="imposta_genere">Imposta genere…</option><option value="imposta_autore">Imposta autore…</option><option value="imposta_lingua">Imposta lingua…</option></optgroup>
      <optgroup label="Altro"><option value="duplica">Duplica</option><option value="nuovo_token">Rigenera link anteprima</option><option value="azzera_contatori">Azzera contatori</option><option value="csv">Esporta CSV</option><option value="cestino">Sposta nel cestino</option></optgroup>
    <?php endif; ?>
  </select>
  <span data-per="sposta_collana"><select name="collana_id"><option value="0">— nessuna collana —</option><?php foreach ($collane as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['nome']) ?></option><?php endforeach; ?></select>
    <input type="text" name="nuova_collana" placeholder="…o nuova collana"></span>
  <span data-per="aggiungi_tag rimuovi_tag"><input type="text" name="tag" placeholder="tag1, tag2"></span>
  <span data-per="imposta_genere imposta_autore imposta_lingua"><input type="text" name="valore" placeholder="valore"></span>
  <span data-per="programma"><input type="datetime-local" name="data"></span>
  <button class="bottone piccolo" data-conferma-massa>Applica</button>
</div>
<?php endif; ?>
</form>

<?php if ($pagine > 1): ?>
<div class="pag">
<?php for ($i = 1; $i <= $pagine; $i++): ?>
  <?= $i === $pag ? "<span>$i</span>" : '<a href="' . e(url_lista(['p' => $i])) . "\">$i</a>" ?>
<?php endfor; ?>
</div>
<?php endif; ?>
<p class="muto"><?= $totale ?> libri · pagina <?= $pag ?> di <?= $pagine ?></p>
<?php admin_piede();
