<?php
require __DIR__ . '/_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? '';
    $ids = !empty($_POST['id']) ? [(int)$_POST['id']] : array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    $n = 0;
    foreach ($ids as $id) {
        if ($az === 'approva') $s = db()->prepare('UPDATE recensioni SET stato = 1 WHERE id = ?');
        elseif ($az === 'rifiuta') $s = db()->prepare('UPDATE recensioni SET stato = 2 WHERE id = ?');
        elseif ($az === 'elimina') $s = db()->prepare('DELETE FROM recensioni WHERE id = ?');
        else continue;
        $s->execute([$id]); $n += $s->rowCount();
    }
    if ($n) { registra('Recensioni: ' . $az, "$n"); flash(['approva' => 'Pubblicate', 'rifiuta' => 'Rifiutate', 'elimina' => 'Eliminate'][$az] . ": $n."); }
    torna('recensioni.php?stato=' . urlencode($_POST['stato'] ?? '0'));
}

$stato = $_GET['stato'] ?? '0';
$w = in_array($stato, ['0', '1', '2'], true) ? 'WHERE r.stato = ' . (int)$stato : '';
$righe = db()->query("SELECT r.*, l.titolo AS libro_titolo, l.slug, t.nick FROM recensioni r JOIN libri l ON l.id = r.libro_id JOIN lettori t ON t.id = r.lettore_id $w ORDER BY r.aggiornato DESC LIMIT 200")->fetchAll();
$conta = [];
foreach (db()->query('SELECT stato, COUNT(*) AS n FROM recensioni GROUP BY stato') as $r) $conta[(int)$r['stato']] = (int)$r['n'];
admin_testata('Recensioni', 'recensioni');
?>
<p class="muto">I lettori registrati possono votare e scrivere. <?= impost('recensioni_moderate') === '1' ? 'Le recensioni compaiono nel sito solo dopo la tua approvazione.' : 'Le recensioni compaiono subito; puoi comunque rifiutarle o eliminarle.' ?> Si vede solo il nome pubblico.</p>
<div class="schede">
  <?php foreach (['0' => 'In attesa', '1' => 'Pubblicate', '2' => 'Rifiutate', 'tutte' => 'Tutte'] as $k => $et): ?>
    <a href="recensioni.php?stato=<?= $k ?>" class="<?= (string)$stato === (string)$k ? 'att' : '' ?>"><?= e($et) ?><?= $k === 'tutte' ? ' (' . array_sum($conta) . ')' : ' (' . ($conta[(int)$k] ?? 0) . ')' ?></a>
  <?php endforeach; ?>
</div>
<?php if (!$righe): ?><p class="muto">Niente qui.</p><?php else: ?>
<form method="post"><?= csrf_campo() ?><input type="hidden" name="stato" value="<?= e($stato) ?>">
<table class="tabella"><thead><tr><th><input type="checkbox" data-tutti aria-label="Seleziona tutte"></th><th>Libro</th><th>Voto</th><th>Recensione</th><th>Di</th><th>Quando</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>
<?php foreach ($righe as $r): ?>
<tr>
  <td><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>"></td>
  <td><a href="../libro.php?s=<?= e($r['slug']) ?>#recensioni" target="_blank"><?= e($r['libro_titolo']) ?></a></td>
  <td style="white-space:nowrap;color:#c9a14a"><?= stelle((float)$r['voto']) ?></td>
  <td style="max-width:360px"><b><?= e($r['titolo'] === '' ? '' : $r['titolo']) ?></b><br><?= nl2br(e($r['testo'])) ?></td>
  <td><?= e($r['nick']) ?></td><td class="muto"><?= e(formatta_data($r['aggiornato'])) ?></td>
  <td><span class="stato <?= ['bozza', 'pub', 'cest'][(int)$r['stato']] ?>"><?= ['in attesa', 'pubblicata', 'rifiutata'][(int)$r['stato']] ?></span></td>
  <td><div class="azioni-riga">
    <?php if ((int)$r['stato'] !== 1): ?><button class="mini" name="id" value="<?= (int)$r['id'] ?>" onclick="this.form.azione.value='approva'">✓ Approva</button><?php endif; ?>
    <?php if ((int)$r['stato'] !== 2): ?><button class="mini" name="id" value="<?= (int)$r['id'] ?>" onclick="this.form.azione.value='rifiuta'">✕ Rifiuta</button><?php endif; ?>
    <button class="mini" name="id" value="<?= (int)$r['id'] ?>" onclick="if(!confirm('Eliminare questa recensione?'))return false;this.form.azione.value='elimina'">🗑</button>
  </div></td>
</tr>
<?php endforeach; ?></tbody></table>
<input type="hidden" name="azione" value="">
<p class="azioni-massa">Selezionate:
  <button class="bottone piccolo" onclick="this.form.azione.value='approva'">✓ Approva</button>
  <button class="bottone sec piccolo" onclick="this.form.azione.value='rifiuta'">✕ Rifiuta</button>
  <button class="bottone sec piccolo" onclick="if(!confirm('Eliminare le recensioni selezionate?'))return false;this.form.azione.value='elimina'">🗑 Elimina</button></p>
</form>
<?php endif; ?>
<?php admin_piede();
