<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/scarica.php';
admin_richiesto();
$id = (int)($_GET['id'] ?? 0);
$l = ['titolo'=>'','collana'=>'','numero'=>0,'genere'=>'','trama'=>'','copertina'=>'','pdf'=>'','pubblicato'=>0,'slug'=>''];
if ($id) {
    $st = db()->prepare('SELECT * FROM libri WHERE id = ?'); $st->execute([$id]);
    $l = $st->fetch() ?: exit('Libro non trovato');
}
$errore = '';

// Salva il file caricato oppure, se c'è un URL, lo scarica da lì.
function salva_file(string $campo, string $cartella, array $tipi_ok, int $max): ?string {
    $url = trim($_POST[$campo . '_url'] ?? '');
    if (!empty($_FILES[$campo]['name'])) {
        $f = $_FILES[$campo];
        if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Caricamento fallito (codice ' . $f['error'] . '). Controlla upload_max_filesize su PHP.');
        if ($f['size'] > $max) throw new RuntimeException('File troppo grande.');
        $tmp = $f['tmp_name']; $caricato = true;
    } elseif ($url !== '') {
        $tmp = scarica_url($url, $max); $caricato = false;
    } else {
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!isset($tipi_ok[$mime])) { if (!$caricato) @unlink($tmp); throw new RuntimeException('Tipo di file non ammesso (' . $mime . ').'); }
    $nome = bin2hex(random_bytes(8)) . '.' . $tipi_ok[$mime];
    @mkdir(RADICE . "/uploads/$cartella", 0755, true);
    $dest = RADICE . "/uploads/$cartella/$nome";
    $ok = $caricato ? move_uploaded_file($tmp, $dest) : rename($tmp, $dest);
    if (!$ok) throw new RuntimeException('Non riesco a salvare il file.');
    return $nome;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    try {
        $titolo = trim($_POST['titolo'] ?? '');
        if ($titolo === '') throw new RuntimeException('Il titolo è obbligatorio.');
        $cop = salva_file('copertina', 'cover', ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'], 8*1024*1024);
        $pdf = salva_file('pdf', 'pdf', ['application/pdf'=>'pdf'], 100*1024*1024);
        $dati = [
            'titolo' => $titolo,
            'collana' => trim($_POST['collana'] ?? ''),
            'numero' => (int)($_POST['numero'] ?? 0),
            'genere' => trim($_POST['genere'] ?? ''),
            'trama' => trim($_POST['trama'] ?? ''),
            'pubblicato' => empty($_POST['pubblicato']) ? 0 : 1,
        ];
        if ($id) {
            if ($cop && $l['copertina']) @unlink(RADICE . '/uploads/cover/' . basename($l['copertina']));
            if ($pdf && $l['pdf']) @unlink(RADICE . '/uploads/pdf/' . basename($l['pdf']));
            $dati['copertina'] = $cop ?? $l['copertina'];
            $dati['pdf'] = $pdf ?? $l['pdf'];
            $sql = 'UPDATE libri SET titolo=:titolo, collana=:collana, numero=:numero, genere=:genere, trama=:trama, pubblicato=:pubblicato, copertina=:copertina, pdf=:pdf WHERE id=:id';
            $dati['id'] = $id;
        } else {
            $dati['copertina'] = $cop ?? ''; $dati['pdf'] = $pdf ?? '';
            $base = $s = slug($titolo); $i = 2;
            $chk = db()->prepare('SELECT 1 FROM libri WHERE slug = ?');
            while ($chk->execute([$s]) && $chk->fetchColumn()) $s = $base . '-' . $i++;
            $dati['slug'] = $s;
            $sql = 'INSERT INTO libri (titolo,collana,numero,genere,trama,pubblicato,copertina,pdf,slug) VALUES (:titolo,:collana,:numero,:genere,:trama,:pubblicato,:copertina,:pdf,:slug)';
        }
        db()->prepare($sql)->execute($dati);
        if (!empty($_POST['resta'])) { header('Location: libro.php?id=' . ($id ?: db()->lastInsertId())); exit; }
        header('Location: index.php'); exit;
    } catch (RuntimeException $ex) {
        $errore = $ex->getMessage();
        $l = array_merge($l, $_POST);
    }
}
intestazione($id ? 'Modifica libro' : 'Nuovo libro', '../');
?>
<div class="admin">
<h1><?= $id ? 'Modifica libro' : 'Nuovo libro' ?></h1>
<?php if ($errore): ?><p class="errore"><?= e($errore) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="campi">
  <?= csrf_campo() ?>
  <label>Titolo <input type="text" name="titolo" value="<?= e($l['titolo']) ?>" required></label>
  <label>Collana <input type="text" name="collana" value="<?= e($l['collana']) ?>"></label>
  <label>Numero nella collana <input type="number" name="numero" min="0" value="<?= (int)$l['numero'] ?>"></label>
  <label>Genere <input type="text" name="genere" value="<?= e($l['genere']) ?>"></label>
  <label>Trama <textarea name="trama" rows="6"><?= e($l['trama']) ?></textarea></label>
  <label>Copertina (JPG/PNG/WebP) <input type="file" name="copertina" accept="image/*"></label>
  <label>…oppure da URL <span class="riga"><input type="text" name="copertina_url" placeholder="https://…/copertina.jpg"> <button class="bottone secondario" name="resta" value="1">Scarica da URL</button></span></label>
  <?php if ($l['copertina']): ?><img src="../uploads/cover/<?= e($l['copertina']) ?>" alt="" width="120"><?php endif; ?>
  <label>PDF del libro <input type="file" name="pdf" accept="application/pdf"></label>
  <label>…oppure da URL <span class="riga"><input type="text" name="pdf_url" placeholder="https://…/libro.pdf"> <button class="bottone secondario" name="resta" value="1">Scarica da URL</button></span></label>
  <?php if ($l['pdf']): ?><p class="meta">PDF attuale: <?= e($l['pdf']) ?> (lascia vuoto per tenerlo)</p><?php endif; ?>
  <label><span><input type="checkbox" name="pubblicato" value="1" <?= $l['pubblicato'] ? 'checked' : '' ?>> Pubblicato (visibile nel catalogo)</span></label>
  <p class="azioni"><button class="bottone">Salva</button> <a class="bottone secondario" href="index.php">Annulla</a></p>
</form></div>
<?php piede();
