<?php
require __DIR__ . '/../inc/bootstrap.php';
admin_richiesto();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['azione'] ?? '') === 'pubblica') {
        db()->prepare('UPDATE libri SET pubblicato = 1 - pubblicato WHERE id = ?')->execute([$id]);
    } elseif (($_POST['azione'] ?? '') === 'elimina') {
        $st = db()->prepare('SELECT copertina, pdf FROM libri WHERE id = ?'); $st->execute([$id]);
        if ($l = $st->fetch()) {
            if ($l['copertina']) @unlink(RADICE . '/uploads/cover/' . basename($l['copertina']));
            if ($l['pdf']) @unlink(RADICE . '/uploads/pdf/' . basename($l['pdf']));
            db()->prepare('DELETE FROM libri WHERE id = ?')->execute([$id]);
        }
    }
    header('Location: index.php'); exit;
}
$libri = db()->query('SELECT * FROM libri ORDER BY collana, numero, id')->fetchAll();
intestazione('Admin', '../');
?>
<div class="admin">
<h1>Admin</h1>
<p class="azioni"><a class="bottone" href="libro.php">+ Nuovo libro</a> <a class="bottone secondario" href="logout.php">Esci</a></p>
<table>
<tr><th>N.</th><th>Titolo</th><th>Stato</th><th></th></tr>
<?php foreach ($libri as $l): ?>
<tr>
  <td><?= (int)$l['numero'] ?></td>
  <td><?= e($l['titolo']) ?><br><span class="meta"><?= e($l['collana']) ?></span></td>
  <td><?= $l['pubblicato'] ? 'Pubblicato' : 'Bozza' ?></td>
  <td class="azioni">
    <a href="libro.php?id=<?= (int)$l['id'] ?>">Modifica</a>
    <form method="post"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="azione" value="pubblica"><button><?= $l['pubblicato'] ? 'Nascondi' : 'Pubblica' ?></button></form>
    <form method="post" onsubmit="return confirm('Eliminare davvero questo libro?')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="azione" value="elimina"><button>Elimina</button></form>
  </td>
</tr>
<?php endforeach; ?>
</table></div>
<?php piede();
