<?php
require __DIR__ . '/_admin.php';
$id = (int)($_GET['id'] ?? 0);
$l = ['titolo' => '', 'sottotitolo' => '', 'autore' => 'Vlad', 'serie' => '', 'numero' => 0, 'descrizione' => '', 'copertina' => '', 'prezzo' => 0, 'ordinabile' => 1, 'ordine' => 0];
if ($id) { $st = db()->prepare('SELECT * FROM libri WHERE id = ?'); $st->execute([$id]); $l = $st->fetch() ?: vai('libri.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $titolo = trim((string)$_POST['titolo']);
    if ($titolo === '') { flash('err', 'Il titolo è obbligatorio.'); vai($id ? "libro.php?id=$id" : 'libro.php'); }
    $cop = $l['copertina'];
    if (!empty($_FILES['copertina']['tmp_name']) && is_uploaded_file($_FILES['copertina']['tmp_name'])) {
        $info = @getimagesize($_FILES['copertina']['tmp_name']);
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
        if (!$ext || $_FILES['copertina']['size'] > 8 * 1024 * 1024) { flash('err', 'Copertina: serve un JPG, PNG o WebP fino a 8 MB.'); vai($id ? "libro.php?id=$id" : 'libro.php'); }
        $nome = 'uploads/cover/' . bin2hex(random_bytes(8)) . ".$ext";
        move_uploaded_file($_FILES['copertina']['tmp_name'], __DIR__ . '/../' . $nome);
        $cop = $nome;
    }
    $dati = [$titolo, trim((string)$_POST['sottotitolo']), trim((string)$_POST['autore']), trim((string)$_POST['serie']), (int)$_POST['numero'],
             trim((string)$_POST['descrizione']), $cop, max(0, (float)str_replace(',', '.', (string)$_POST['prezzo'])), isset($_POST['ordinabile']) ? 1 : 0, (int)$_POST['ordine']];
    if ($id) { db()->prepare('UPDATE libri SET titolo=?, sottotitolo=?, autore=?, serie=?, numero=?, descrizione=?, copertina=?, prezzo=?, ordinabile=?, ordine=? WHERE id=?')->execute([...$dati, $id]); }
    else {
        $slug = slug_da($titolo); $base = $slug; $i = 2;
        while (db()->query("SELECT 1 FROM libri WHERE slug = " . db()->quote($slug))->fetchColumn()) $slug = $base . '-' . $i++;
        db()->prepare('INSERT INTO libri (titolo, sottotitolo, autore, serie, numero, descrizione, copertina, prezzo, ordinabile, ordine, slug) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([...$dati, $slug]);
    }
    flash('ok', 'Libro salvato.'); vai('libri.php');
}
admin_testata($id ? 'Modifica libro' : 'Nuovo libro', 'libri');
?>
<form method="post" enctype="multipart/form-data" class="pannello"><?= csrf_campo() ?>
<div class="form-griglia">
<label class="largo">Titolo <input type="text" name="titolo" required value="<?= e($l['titolo']) ?>"></label>
<label class="largo">Sottotitolo <input type="text" name="sottotitolo" value="<?= e($l['sottotitolo']) ?>"></label>
<label>Autore <input type="text" name="autore" value="<?= e($l['autore']) ?>"></label>
<label>Serie <input type="text" name="serie" value="<?= e($l['serie']) ?>"></label>
<label>Numero nella serie <input type="number" name="numero" value="<?= (int)$l['numero'] ?>"></label>
<label>Prezzo (€) <small>0 = su richiesta</small><input type="text" name="prezzo" value="<?= e($l['prezzo']) ?>"></label>
<label>Posizione nel catalogo <input type="number" name="ordine" value="<?= (int)$l['ordine'] ?>"></label>
<label>Copertina <?php if ($l['copertina']): ?><img class="anteprima-cop" src="../<?= e($l['copertina']) ?>" alt=""><?php endif; ?><input type="file" name="copertina" accept="image/jpeg,image/png,image/webp"></label>
<label class="largo">Descrizione <textarea name="descrizione" rows="6"><?= e($l['descrizione']) ?></textarea></label>
<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="ordinabile" style="width:auto" <?= $l['ordinabile'] ? 'checked' : '' ?>> Ordinabile dal sito</label>
</div>
<div class="salva-barra"><button class="bottone">Salva</button><a class="bottone sec" href="libri.php">Annulla</a></div></form>
<?php admin_piede();
