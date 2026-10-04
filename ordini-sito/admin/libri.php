<?php
require __DIR__ . '/_admin.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('DELETE FROM libri WHERE id = ?')->execute([$id]); // gli ordini restano (titolo salvato nell'ordine)
    flash('ok', 'Libro eliminato.'); vai('libri.php');
}
$libri = db()->query('SELECT * FROM libri ORDER BY ordine, numero, id')->fetchAll();
admin_testata('Libri', 'libri');
?>
<p><a class="bottone" href="libro.php">+ Nuovo libro</a></p>
<table class="tabella"><tr><th></th><th>Titolo</th><th>Serie</th><th>Prezzo</th><th>Ordinabile</th><th></th></tr>
<?php foreach ($libri as $l): ?>
<tr><td><?php if ($l['copertina']): ?><img class="mini-cop" src="../<?= e($l['copertina']) ?>" alt=""><?php endif; ?></td>
<td><a href="libro.php?id=<?= $l['id'] ?>"><?= e($l['titolo']) ?></a></td><td><?= e($l['serie']) ?><?= $l['numero'] ? ' #' . (int)$l['numero'] : '' ?></td>
<td><?= e(euro((float)$l['prezzo'])) ?></td><td><?= $l['ordinabile'] ? 'Sì' : 'No' ?></td>
<td><form method="post" onsubmit="return confirm('Eliminare il libro?')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><button class="mini">🗑</button></form></td></tr>
<?php endforeach; ?></table>
<?php admin_piede();
