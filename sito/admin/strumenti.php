<?php
require __DIR__ . '/_admin.php';
require __DIR__ . '/../inc/libri.php';

$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? '';
    switch ($az) {
        case 'backup':     // scarica una copia del database
            $tmp = tempnam(sys_get_temp_dir(), 'bk');
            @unlink($tmp);
            try { $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $tmp) . "'"); }
            catch (Throwable $e) { copy(RADICE . '/data/sito.sqlite', $tmp); }
            registra('Backup scaricato');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="backup-' . date('Y-m-d-His') . '.sqlite"');
            header('Content-Length: ' . filesize($tmp));
            readfile($tmp); @unlink($tmp); exit;
        case 'csv':
            esporta_csv($pdo->query('SELECT * FROM libri WHERE eliminato IS NULL ORDER BY ordine, numero, id')->fetchAll(), 'catalogo-' . date('Y-m-d') . '.csv');
        case 'json':
            $libri = $pdo->query('SELECT * FROM libri WHERE eliminato IS NULL ORDER BY ordine, numero, id')->fetchAll();
            foreach ($libri as &$l) unset($l['token'], $l['note']);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="catalogo-' . date('Y-m-d') . '.json"');
            echo json_encode(['collane' => $pdo->query('SELECT * FROM collane')->fetchAll(), 'libri' => $libri], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); exit;
        case 'importa_csv':
            if (empty($_FILES['csv']['tmp_name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) { flash('Scegli un file CSV.', 'errore'); break; }
            $f = fopen($_FILES['csv']['tmp_name'], 'r');
            $prima = fgets($f); rewind($f);
            if (substr($prima, 0, 3) === "\xEF\xBB\xBF") fseek($f, 3);
            $sep = substr_count($prima, ';') >= substr_count($prima, ',') ? ';' : ',';
            $testa = array_map(fn($x) => strtolower(trim($x)), fgetcsv($f, 0, $sep) ?: []);
            if (!in_array('titolo', $testa, true)) { flash('Il CSV deve avere una colonna «titolo» nella prima riga.', 'errore'); break; }
            $nuovi = $agg = 0;
            while (($r = fgetcsv($f, 0, $sep)) !== false) {
                if (count($r) < 2) continue;
                $d = array_combine($testa, array_pad(array_slice($r, 0, count($testa)), count($testa), ''));
                $titolo = trim($d['titolo'] ?? ''); if ($titolo === '') continue;
                $slug = trim($d['slug'] ?? '') ?: slug($titolo);
                $st = $pdo->prepare('SELECT id FROM libri WHERE slug = ?'); $st->execute([$slug]); $esiste = $st->fetchColumn();
                $cid = collana_id_per_nome($d['collana'] ?? '');
                $dati = ['titolo' => $titolo, 'sottotitolo' => trim($d['sottotitolo'] ?? ''), 'autore' => trim($d['autore'] ?? ''), 'collana' => trim($d['collana'] ?? ''), 'collana_id' => $cid,
                         'numero' => (int)($d['numero'] ?? 0), 'genere' => trim($d['genere'] ?? ''), 'lingua' => trim($d['lingua'] ?? '') ?: 'it', 'anno' => (int)($d['anno'] ?? 0),
                         'pagine_n' => (int)($d['pagine'] ?? 0), 'isbn' => trim($d['isbn'] ?? ''), 'tags' => normalizza_tags($d['tags'] ?? ''), 'trama' => trim($d['trama'] ?? ''),
                         'pubblicato' => (int)(($d['pubblicato'] ?? '0') === '1'), 'pubblica_il' => trim($d['pubblica_il'] ?? ''), 'evidenza' => (int)(($d['evidenza'] ?? '0') === '1'),
                         'aggiornato' => date('Y-m-d H:i:s')];
                if ($esiste) {
                    $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($dati))); $dati['id'] = $esiste;
                    $pdo->prepare("UPDATE libri SET $set WHERE id = :id")->execute($dati); $agg++;
                } else {
                    $dati['slug'] = slug_unico('libri', $slug); $dati['token'] = nuovo_token();
                    $pdo->prepare('INSERT INTO libri (' . implode(',', array_keys($dati)) . ') VALUES (:' . implode(',:', array_keys($dati)) . ')')->execute($dati); $nuovi++;
                }
            }
            fclose($f);
            registra('Import CSV', "$nuovi nuovi, $agg aggiornati");
            flash("Import completato: $nuovi nuovi, $agg aggiornati."); break;
        case 'svuota_cestino':
            $ids = $pdo->query('SELECT id FROM libri WHERE eliminato IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $i) elimina_libro_definitivo((int)$i);
            registra('Cestino svuotato', count($ids) . ' libri'); flash(count($ids) . ' libri eliminati per sempre.'); break;
        case 'ottimizza':
            $pdo->exec('VACUUM'); registra('Database ottimizzato'); flash('Database ottimizzato.'); break;
        case 'azzera_tutti':
            $pdo->exec('UPDATE libri SET letture = 0, download = 0'); registra('Contatori azzerati (tutti)'); flash('Tutti i contatori sono a zero.'); break;
        case 'nuovi_token':
            foreach ($pdo->query('SELECT id FROM libri')->fetchAll(PDO::FETCH_COLUMN) as $i) $pdo->prepare('UPDATE libri SET token = ? WHERE id = ?')->execute([nuovo_token(), $i]);
            registra('Link di anteprima rigenerati (tutti)'); flash('Tutti i link di anteprima sono stati rigenerati.'); break;
        case 'conta_pagine':
            $n = 0;
            foreach ($pdo->query("SELECT id, pdf FROM libri WHERE pdf != '' AND pagine_n = 0")->fetchAll() as $l) {
                $p = conta_pagine_pdf(percorso_file('pdf', $l['pdf']));
                if ($p) { $pdo->prepare('UPDATE libri SET pagine_n = ? WHERE id = ?')->execute([$p, $l['id']]); $n++; }
            }
            flash("Pagine calcolate per $n libri."); break;
    }
    torna('strumenti.php');
}

// controlli
$problemi = [];
foreach ($pdo->query('SELECT id, titolo, copertina, pdf FROM libri WHERE eliminato IS NULL') as $l) {
    if ($l['pdf'] && !is_file(percorso_file('pdf', $l['pdf']))) $problemi[] = [$l['id'], $l['titolo'], 'PDF mancante sul server'];
    if ($l['copertina'] && !is_file(percorso_file('cover', $l['copertina']))) $problemi[] = [$l['id'], $l['titolo'], 'copertina mancante sul server'];
}
$sal = [
    ['PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.0', '>=')],
    ['SQLite', $pdo->query('SELECT sqlite_version()')->fetchColumn(), true],
    ['Estensione curl (download da URL)', extension_loaded('curl') ? 'sì' : 'NO', extension_loaded('curl')],
    ['Estensione fileinfo (controllo file)', extension_loaded('fileinfo') ? 'sì' : 'NO', extension_loaded('fileinfo')],
    ['Limite upload (upload_max_filesize)', ini_get('upload_max_filesize'), true],
    ['Limite POST (post_max_size)', ini_get('post_max_size'), true],
    ['Memoria PHP', ini_get('memory_limit'), true],
    ['Cartella data/ scrivibile', is_writable(RADICE . '/data') ? 'sì' : 'NO', is_writable(RADICE . '/data')],
    ['Cartella uploads/ scrivibile', is_writable(RADICE . '/uploads') ? 'sì' : 'NO', is_writable(RADICE . '/uploads')],
    ['HTTPS', !empty($_SERVER['HTTPS']) ? 'attivo' : 'non attivo', !empty($_SERVER['HTTPS'])],
    ['Spazio libero sul disco', ($d = @disk_free_space(RADICE)) ? formatta_byte((int)$d) : 'sconosciuto', true],
    ['Dimensione database', formatta_byte((int)@filesize(RADICE . '/data/sito.sqlite')), true],
];
admin_testata('Strumenti', 'strumenti');
?>
<div class="griglia2">
<div class="pannello"><h2 style="margin-top:0">Backup e esportazione</h2>
  <form method="post" class="azioni-riga"><?= csrf_campo() ?>
    <button class="bottone" name="azione" value="backup">💾 Scarica backup del database</button>
    <button class="bottone sec" name="azione" value="csv">Esporta catalogo CSV</button>
    <button class="bottone sec" name="azione" value="json">Esporta catalogo JSON</button></form>
  <p class="muto">Il backup contiene libri, collane, pagine e impostazioni (non i PDF e le immagini: quelli sono nella cartella <code>uploads/</code>, copiala via FTP).</p>
</div>
<div class="pannello"><h2 style="margin-top:0">Importa da CSV</h2>
  <form method="post" enctype="multipart/form-data"><?= csrf_campo() ?>
    <label>File CSV <input type="file" name="csv" accept=".csv,text/csv"></label>
    <p class="muto">Usa lo stesso formato dell'esportazione (colonne: <?= e(implode(', ', COLONNE_CSV)) ?>). Se lo «slug» esiste già il libro viene aggiornato, altrimenti creato.</p>
    <button class="bottone sec" name="azione" value="importa_csv">Importa</button></form>
</div>
<div class="pannello"><h2 style="margin-top:0">Manutenzione</h2>
  <form method="post" class="azioni-riga"><?= csrf_campo() ?>
    <button class="bottone sec piccolo" name="azione" value="conta_pagine">Calcola pagine dei PDF</button>
    <button class="bottone sec piccolo" name="azione" value="ottimizza">Ottimizza database</button>
    <button class="bottone sec piccolo" name="azione" value="azzera_tutti" data-conferma="Azzerare tutte le letture e i download?">Azzera contatori</button>
    <button class="bottone sec piccolo" name="azione" value="nuovi_token" data-conferma="Tutti i link segreti di anteprima cambieranno. Continuare?">Rigenera link anteprima</button>
    <button class="bottone pericolo piccolo" name="azione" value="svuota_cestino" data-conferma="Eliminare per sempre tutto il cestino?">Svuota cestino</button></form>
</div>
<div class="pannello"><h2 style="margin-top:0">Controllo file dei libri</h2>
  <?php if ($problemi): ?><ul><?php foreach ($problemi as [$i, $t, $m]): ?><li><a href="libro.php?id=<?= (int)$i ?>"><?= e($t) ?></a>: <?= e($m) ?></li><?php endforeach; ?></ul>
  <?php else: ?><p class="ok">Tutti i file di PDF e copertine esistono. ✓</p><?php endif; ?>
  <p><a href="media.php">Vedi i file caricati e gli orfani →</a></p>
</div>
</div>

<div class="pannello"><h2 style="margin-top:0">Stato del server</h2>
<table class="tabella"><?php foreach ($sal as [$n, $v, $ok]): ?>
<tr><td><?= e($n) ?></td><td><?= e((string)$v) ?></td><td><?= $ok ? '✅' : '⚠️' ?></td></tr><?php endforeach; ?></table>
</div>
<?php admin_piede();
