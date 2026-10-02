<?php
require __DIR__ . '/_admin.php';
require __DIR__ . '/../inc/libri.php';

$id = (int)($_GET['id'] ?? 0);
$vuoto = ['titolo' => '', 'sottotitolo' => '', 'slug' => '', 'autore' => impost('autore_predefinito'), 'collana_id' => (int)($_GET['collana'] ?? 0), 'numero' => 0, 'genere' => '',
          'lingua' => 'it', 'anno' => (int)date('Y'), 'pagine_n' => 0, 'isbn' => '', 'tags' => '', 'trama' => '', 'meta' => '', 'note' => '', 'copertina' => '', 'pdf' => '',
          'pubblicato' => 0, 'evidenza' => 0, 'pubblica_il' => '', 'token' => '', 'letture' => 0, 'download' => 0, 'creato' => '', 'aggiornato' => '', 'eliminato' => null];
$l = $id ? carica_libro($id) : $vuoto;
if (!$l) { flash('Libro non trovato.', 'errore'); torna('libri.php'); }
$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? 'salva';
    // azioni rapide dalla scheda
    if ($id && in_array($az, ['duplica', 'cestino', 'ripristina', 'elimina_def', 'nuovo_token', 'azzera_contatori'], true)) {
        $msg = azione_libri($az, [$id], $_POST);
        flash($msg);
        if ($az === 'duplica') { $nuovo = (int)db()->query('SELECT MAX(id) FROM libri')->fetchColumn(); torna("libro.php?id=$nuovo"); }
        torna(in_array($az, ['cestino', 'elimina_def'], true) ? 'libri.php' : "libro.php?id=$id");
    }
    try {
        $titolo = trim($_POST['titolo'] ?? '');
        if ($titolo === '') throw new RuntimeException('Il titolo è obbligatorio.');
        $cop = salva_file('copertina', 'cover', TIPI_IMMAGINE, 8 * 1024 * 1024);
        $pdf = salva_file('pdf', 'pdf', TIPI_PDF, 100 * 1024 * 1024);
        $copertina = $l['copertina']; $pdf_att = $l['pdf'];
        if (!empty($_POST['togli_copertina'])) $copertina = '';
        if (!empty($_POST['togli_pdf'])) $pdf_att = '';
        if ($cop) $copertina = $cop;
        if ($pdf) $pdf_att = $pdf;

        $cid = (int)($_POST['collana_id'] ?? 0);
        if (trim($_POST['nuova_collana'] ?? '') !== '') $cid = collana_id_per_nome($_POST['nuova_collana']);
        $nomeCollana = $cid ? (string)db()->query('SELECT nome FROM collane WHERE id = ' . $cid)->fetchColumn() : '';

        $pubblica_il = '';
        if (trim($_POST['pubblica_il'] ?? '') !== '' && ($ts = strtotime($_POST['pubblica_il']))) $pubblica_il = date('Y-m-d H:i:s', $ts);
        $pagine_n = (int)($_POST['pagine_n'] ?? 0);
        if ($pdf && $pagine_n === 0) $pagine_n = conta_pagine_pdf(percorso_file('pdf', $pdf));

        $slugVoluto = trim($_POST['slug'] ?? '') !== '' ? $_POST['slug'] : $titolo;
        $dati = [
            'titolo' => $titolo, 'sottotitolo' => trim($_POST['sottotitolo'] ?? ''), 'autore' => trim($_POST['autore'] ?? ''),
            'slug' => slug_unico('libri', $slugVoluto, $id), 'collana_id' => $cid, 'collana' => $nomeCollana,
            'numero' => (int)($_POST['numero'] ?? 0), 'genere' => trim($_POST['genere'] ?? ''), 'lingua' => trim($_POST['lingua'] ?? 'it'),
            'anno' => (int)($_POST['anno'] ?? 0), 'pagine_n' => $pagine_n, 'isbn' => trim($_POST['isbn'] ?? ''),
            'tags' => normalizza_tags($_POST['tags'] ?? ''), 'trama' => trim($_POST['trama'] ?? ''), 'meta' => trim($_POST['meta'] ?? ''),
            'note' => trim($_POST['note'] ?? ''), 'copertina' => $copertina, 'pdf' => $pdf_att,
            'pubblicato' => ($_POST['stato'] ?? '') === 'pubblicato' ? 1 : 0, 'evidenza' => empty($_POST['evidenza']) ? 0 : 1,
            'pubblica_il' => $pubblica_il, 'aggiornato' => date('Y-m-d H:i:s'),
        ];
        if ($id) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($dati)));
            $dati['id'] = $id;
            db()->prepare("UPDATE libri SET $set WHERE id = :id")->execute($dati);
            if ($copertina !== $l['copertina']) elimina_file('cover', $l['copertina']);
            if ($pdf_att !== $l['pdf']) elimina_file('pdf', $l['pdf']);
            registra('Libro modificato', $titolo);
        } else {
            $dati['token'] = nuovo_token();
            $campi = implode(',', array_keys($dati));
            db()->prepare("INSERT INTO libri ($campi) VALUES (:" . implode(',:', array_keys($dati)) . ')')->execute($dati);
            $id = (int)db()->lastInsertId();
            registra('Libro creato', $titolo);
        }
        flash('Salvato: ' . $titolo);
        $dopo = $_POST['dopo'] ?? 'resta';
        if ($dopo === 'elenco') torna('libri.php');
        if ($dopo === 'nuovo') torna('libro.php' . ($cid ? "?collana=$cid" : ''));
        torna("libro.php?id=$id");
    } catch (RuntimeException $ex) {
        $errore = $ex->getMessage();
        $l = array_merge($l, $_POST, ['pubblicato' => ($_POST['stato'] ?? '') === 'pubblicato' ? 1 : 0, 'evidenza' => empty($_POST['evidenza']) ? 0 : 1]);
    }
}

$collane = db()->query('SELECT id, nome FROM collane ORDER BY ordine, nome')->fetchAll();
$generi = db()->query("SELECT DISTINCT genere FROM libri WHERE genere != '' ORDER BY genere")->fetchAll(PDO::FETCH_COLUMN);
$anteprima = $id && $l['token'] ? percorso_pubblico() : '';
$anteprima_url = $id ? str_replace('/admin/', '/', $anteprima) . 'libro.php?s=' . urlencode($l['slug']) . '&anteprima=' . $l['token'] : '';
$dim_pdf = $l['pdf'] && is_file(percorso_file('pdf', $l['pdf'])) ? filesize(percorso_file('pdf', $l['pdf'])) : 0;
$pubblico = $id && $l['pubblicato'] && !$l['eliminato'] && ($l['pubblica_il'] === '' || $l['pubblica_il'] <= date('Y-m-d H:i:s'));

admin_testata($id ? 'Modifica libro' : 'Nuovo libro', 'libri');
if ($errore) echo '<p class="errore">' . e($errore) . '</p>';
if ($id && $l['eliminato']) echo '<p class="errore">Questo libro è nel cestino dal ' . e(formatta_data($l['eliminato'])) . '.</p>';
?>
<form method="post" enctype="multipart/form-data" data-avvisa>
<?= csrf_campo() ?>
<div class="pannello"><h2 style="margin-top:0">Informazioni</h2>
<div class="form-griglia">
  <label class="largo">Titolo * <input type="text" name="titolo" value="<?= e($l['titolo']) ?>" required></label>
  <label class="largo">Sottotitolo <input type="text" name="sottotitolo" value="<?= e($l['sottotitolo']) ?>"></label>
  <label>Autore <input type="text" name="autore" value="<?= e($l['autore']) ?>"></label>
  <label>Collana <select name="collana_id"><option value="0">— nessuna —</option>
    <?php foreach ($collane as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$l['collana_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?></select></label>
  <label>…o crea nuova collana <input type="text" name="nuova_collana" placeholder="Nome nuova collana"></label>
  <label>Numero nella collana <input type="number" name="numero" min="0" value="<?= (int)$l['numero'] ?>"></label>
  <label>Genere <input type="text" name="genere" list="generi" value="<?= e($l['genere']) ?>"></label>
  <datalist id="generi"><?php foreach ($generi as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist>
  <label>Lingua <input type="text" name="lingua" value="<?= e($l['lingua']) ?>" placeholder="it, ru, en…"></label>
  <label>Anno <input type="number" name="anno" min="0" max="2100" value="<?= (int)$l['anno'] ?>"></label>
  <label>Pagine <small>(0 = calcola dal PDF)</small><input type="number" name="pagine_n" min="0" value="<?= (int)$l['pagine_n'] ?>"></label>
  <label>ISBN <small>(se non ce l'hai, lascia vuoto)</small><input type="text" name="isbn" value="<?= e($l['isbn']) ?>"></label>
  <label class="largo">Tag <small>(separati da virgola)</small><input type="text" name="tags" value="<?= e(implode(', ', tags_di($l['tags']))) ?>" placeholder="fantasy, Etna, avventura"></label>
  <label class="largo">Trama <textarea name="trama" rows="7"><?= e($l['trama']) ?></textarea></label>
  <label class="largo">Descrizione per i motori di ricerca <small id="cmeta"></small><input type="text" name="meta" value="<?= e($l['meta']) ?>" data-conta="cmeta" placeholder="Una frase breve (max 160 caratteri); se vuota si usa la trama"></label>
  <label class="largo">Note private <small>(le vedi solo tu)</small><textarea name="note" rows="3"><?= e($l['note']) ?></textarea></label>
</div></div>

<div class="pannello"><h2 style="margin-top:0">Copertina e PDF</h2>
<div class="form-griglia">
  <div>
    <?php if ($l['copertina']): ?><img class="anteprima-cop" src="../uploads/cover/<?= e($l['copertina']) ?>" alt=""><br>
      <label style="font-weight:400"><span><input type="checkbox" name="togli_copertina" value="1"> Togli la copertina</span></label><?php endif; ?>
    <label>Copertina (JPG, PNG, WebP) <input type="file" name="copertina" accept="image/*"></label>
    <label>…oppure da URL <input type="text" name="copertina_url" placeholder="https://…/copertina.jpg"></label>
  </div>
  <div>
    <?php if ($l['pdf']): ?><p>📄 PDF attuale: <b><?= e(formatta_byte($dim_pdf)) ?></b>
      <?= $dim_pdf ? '' : ' <span class="errore">(file mancante sul server!)</span>' ?>
      <br><a href="../uploads/pdf/<?= e($l['pdf']) ?>" target="_blank">Apri il PDF ↗</a></p>
      <label style="font-weight:400"><span><input type="checkbox" name="togli_pdf" value="1"> Togli il PDF</span></label><?php endif; ?>
    <label>PDF del libro <input type="file" name="pdf" accept="application/pdf"></label>
    <label>…oppure da URL <input type="text" name="pdf_url" placeholder="https://…/libro.pdf"></label>
  </div>
</div>
<p class="muto">Se carichi un nuovo file, sostituisce quello attuale. Se scegli sia un file sia un URL, vince il file.</p></div>

<div class="pannello"><h2 style="margin-top:0">Pubblicazione</h2>
<div class="form-griglia">
  <label>Stato <select name="stato"><option value="bozza" <?= $l['pubblicato'] ? '' : 'selected' ?>>Bozza (nascosto)</option><option value="pubblicato" <?= $l['pubblicato'] ? 'selected' : '' ?>>Pubblicato</option></select></label>
  <label>Pubblica automaticamente il <small>(vuoto = subito)</small><input type="datetime-local" name="pubblica_il" value="<?= $l['pubblica_il'] ? e(date('Y-m-d\TH:i', strtotime($l['pubblica_il']))) : '' ?>"></label>
  <label><span>In evidenza</span><span><input type="checkbox" name="evidenza" value="1" <?= $l['evidenza'] ? 'checked' : '' ?>> Mostra in cima al catalogo</span></label>
</div>
<?php if ($id): ?>
  <p class="muto">Creato: <?= e(formatta_data($l['creato'])) ?> · Modificato: <?= e(formatta_data($l['aggiornato'])) ?> · 👁 <?= (int)$l['letture'] ?> letture · ⬇ <?= (int)$l['download'] ?> download</p>
  <?php if ($pubblico): ?><p><a href="../libro.php?s=<?= e($l['slug']) ?>" target="_blank">Vedi la pagina pubblica ↗</a> · Indirizzo (slug): <code><?= e($l['slug']) ?></code> <label style="display:inline">modifica: <input type="text" name="slug" value="<?= e($l['slug']) ?>" style="width:220px"></label></p>
  <?php else: ?><input type="hidden" name="slug" value="<?= e($l['slug']) ?>"><?php endif; ?>
  <p>Link segreto di anteprima (anche se è bozza): <br><input type="text" readonly value="<?= e($anteprima_url) ?>" onclick="this.select()">
    <button type="button" class="bottone sec piccolo" data-copia="<?= e($anteprima_url) ?>">Copia link</button></p>
<?php endif; ?>
</div>

<div class="salva-barra">
  <button class="bottone" name="dopo" value="resta">💾 Salva</button>
  <button class="bottone sec" name="dopo" value="elenco">Salva e torna all'elenco</button>
  <button class="bottone sec" name="dopo" value="nuovo">Salva e aggiungi un altro</button>
  <a class="bottone sec" href="libri.php">Annulla</a>
</div>
</form>

<?php if ($id): ?>
<form method="post" class="pannello azioni-riga" style="margin-top:18px">
  <?= csrf_campo() ?><b style="align-self:center">Altre azioni:</b>
  <button class="bottone sec piccolo" name="azione" value="duplica">⧉ Duplica</button>
  <button class="bottone sec piccolo" name="azione" value="nuovo_token" data-conferma="Il vecchio link di anteprima smetterà di funzionare. Continuare?">🔗 Rigenera link anteprima</button>
  <button class="bottone sec piccolo" name="azione" value="azzera_contatori" data-conferma="Azzerare letture e download?">↺ Azzera contatori</button>
  <?php if ($l['eliminato']): ?>
    <button class="bottone sec piccolo" name="azione" value="ripristina">Ripristina</button>
    <button class="bottone pericolo piccolo" name="azione" value="elimina_def" data-conferma="Eliminare per sempre questo libro e i suoi file?">Elimina per sempre</button>
  <?php else: ?>
    <button class="bottone pericolo piccolo" name="azione" value="cestino" data-conferma="Spostare il libro nel cestino?">🗑 Sposta nel cestino</button>
  <?php endif; ?>
</form>
<?php endif; ?>
<?php admin_piede();
