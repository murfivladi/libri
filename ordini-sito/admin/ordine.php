<?php
require __DIR__ . '/_admin.php';
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM richieste WHERE id = ?'); $st->execute([$id]); $o = $st->fetch();
if (!$o) { flash('err', 'Richiesta non trovata.'); vai('index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    if (($_POST['azione'] ?? '') === 'elimina') { db()->prepare('DELETE FROM richieste WHERE id = ?')->execute([$id]); flash('ok', "Richiesta #$id eliminato."); vai('index.php'); }
    $s = isset(STATI[$_POST['stato'] ?? '']) ? $_POST['stato'] : $o['stato'];
    db()->prepare('UPDATE richieste SET stato = ?, note_admin = ?, aggiornato = ?, letto = 1 WHERE id = ?')->execute([$s, mb_substr((string)($_POST['note_admin'] ?? ''), 0, 3000), date('Y-m-d H:i:s'), $id]);
    flash('ok', 'Salvato.'); vai("ordine.php?id=$id");
}
if (!$o['letto']) db()->prepare('UPDATE richieste SET letto = 1 WHERE id = ?')->execute([$id]);
admin_testata("Richiesta #$id", 'ordini');
?>
<p><a href="index.php">← Tutte le richieste</a></p>
<div class="griglia2">
<div class="pannello"><h2 style="margin-top:0">Il libro</h2>
<p><b>Per:</b> <?= e($o['per_chi']) ?><?= $o['eta'] ? ' (' . e($o['eta']) . ')' : '' ?><br><b>Occasione:</b> <?= e($o['occasione']) ?: '—' ?><br><b>Genere:</b> <?= e($o['genere']) ?: '—' ?><br>
<b>Lingua:</b> <?= e($o['lingua']) ?> · <b>Formato:</b> <?= e($o['formato']) ?><br><b>Lunghezza:</b> <?= e($o['pagine']) ?: '—' ?> · <b>Scadenza:</b> <?= e($o['scadenza']) ?: '—' ?></p>
<p><b>Storia desiderata:</b><br><?= nl2br(e($o['storia'])) ?></p>
<?php if ($o['dedica']): ?><p><b>Dedica:</b><br><?= nl2br(e($o['dedica'])) ?></p><?php endif; ?>
<?php if ($o['note']): ?><p><b>Note:</b><br><?= nl2br(e($o['note'])) ?></p><?php endif; ?>
<p class="muto">Ricevuta il <?= e(date('d/m/Y H:i', strtotime($o['creato']))) ?></p></div>
<div class="pannello"><h2 style="margin-top:0">Cliente</h2>
<p><b><?= e($o['nome']) ?></b><br><a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a><?= $o['telefono'] ? '<br>☎ ' . e($o['telefono']) : '' ?></p>
<?php if ($o['indirizzo']): ?><p><?= e($o['indirizzo']) ?><br><?= e($o['cap'] . ' ' . $o['citta']) ?><br><?= e($o['paese']) ?></p><?php endif; ?></div></div>
<form method="post" class="pannello" style="display:grid;gap:12px;max-width:560px"><?= csrf_campo() ?>
<label>Stato <select name="stato"><?php foreach (STATI as $k => $et): ?><option value="<?= $k ?>" <?= $o['stato'] === $k ? 'selected' : '' ?>><?= $et ?></option><?php endforeach; ?></select></label>
<label>Note interne <textarea name="note_admin" rows="4"><?= e($o['note_admin']) ?></textarea></label>
<div class="azioni-riga"><button class="bottone">Salva</button>
<button class="bottone pericolo" name="azione" value="elimina" onclick="return confirm('Eliminare definitivamente questa richiesta?')">Elimina</button></div></form>
<?php admin_piede();
