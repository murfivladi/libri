<?php
require __DIR__ . '/_admin.php';
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM ordini WHERE id = ?'); $st->execute([$id]); $o = $st->fetch();
if (!$o) { flash('err', 'Ordine non trovato.'); vai('index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    if (($_POST['azione'] ?? '') === 'elimina') { db()->prepare('DELETE FROM ordini WHERE id = ?')->execute([$id]); flash('ok', "Ordine #$id eliminato."); vai('index.php'); }
    $s = isset(STATI[$_POST['stato'] ?? '']) ? $_POST['stato'] : $o['stato'];
    db()->prepare('UPDATE ordini SET stato = ?, note_admin = ?, aggiornato = ?, letto = 1 WHERE id = ?')->execute([$s, mb_substr((string)($_POST['note_admin'] ?? ''), 0, 3000), date('Y-m-d H:i:s'), $id]);
    flash('ok', 'Salvato.'); vai("ordine.php?id=$id");
}
if (!$o['letto']) db()->prepare('UPDATE ordini SET letto = 1 WHERE id = ?')->execute([$id]);
admin_testata("Ordine #$id", 'ordini');
$tot = (float)$o['prezzo'] * (int)$o['quantita'];
?>
<p><a href="index.php">← Tutti gli ordini</a></p>
<div class="griglia2">
<div class="pannello"><h2 style="margin-top:0">Ordine</h2>
<p><b><?= e($o['titolo_libro']) ?></b> × <?= (int)$o['quantita'] ?><?= $tot > 0 ? ' · ' . e(euro($tot)) : '' ?></p>
<p class="muto">Ricevuto il <?= e(date('d/m/Y H:i', strtotime($o['creato']))) ?></p>
<?php if ($o['note']): ?><p><b>Note del cliente:</b><br><?= nl2br(e($o['note'])) ?></p><?php endif; ?></div>
<div class="pannello"><h2 style="margin-top:0">Cliente</h2>
<p><b><?= e($o['nome']) ?></b><br><a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a><?= $o['telefono'] ? '<br>☎ ' . e($o['telefono']) : '' ?></p>
<p><?= e($o['indirizzo']) ?><br><?= e($o['cap'] . ' ' . $o['citta']) ?><br><?= e($o['paese']) ?></p></div></div>
<form method="post" class="pannello" style="display:grid;gap:12px;max-width:560px"><?= csrf_campo() ?>
<label>Stato <select name="stato"><?php foreach (STATI as $k => $et): ?><option value="<?= $k ?>" <?= $o['stato'] === $k ? 'selected' : '' ?>><?= $et ?></option><?php endforeach; ?></select></label>
<label>Note interne <textarea name="note_admin" rows="4"><?= e($o['note_admin']) ?></textarea></label>
<div class="azioni-riga"><button class="bottone">Salva</button>
<button class="bottone pericolo" name="azione" value="elimina" onclick="return confirm('Eliminare definitivamente questo ordine?')">Elimina</button></div></form>
<?php admin_piede();
