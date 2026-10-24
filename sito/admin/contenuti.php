<?php
// Audio, video e immagini dei libri scolastici: caricare un file, registrare dal microfono o far leggere un testo alla voce AI (Polza.ai, oppure deAPI).
require __DIR__ . '/_admin.php';
require_once __DIR__ . '/../inc/ai.php';

$libri = db()->query("SELECT id, titolo FROM libri WHERE sezione = 'scuola' AND eliminato IS NULL ORDER BY ordine, numero, id")->fetchAll();
$libro_f = (int)($_GET['libro'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
$nuovo = !empty($_GET['nuovo']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? 'salva';
    $pid = (int)($_POST['id'] ?? 0);
    $vecchio = null;
    if ($pid) { $st = db()->prepare('SELECT * FROM contenuti WHERE id = ?'); $st->execute([$pid]); $vecchio = $st->fetch() ?: null; }
    if ($az === 'elimina' && $vecchio) {
        db()->prepare('DELETE FROM contenuti WHERE id = ?')->execute([$pid]);
        db()->prepare('DELETE FROM zone WHERE codice = ?')->execute([$vecchio['codice']]);
        elimina_file('media', $vecchio['file']);
        registra('Contenuto eliminato', $vecchio['codice']); flash('Eliminato.');
        torna('contenuti.php' . ($libro_f ? "?libro=$libro_f" : ''));
    }
    $lid = (int)($_POST['libro_id'] ?? 0);
    $codice = normalizza_codice((string)($_POST['codice'] ?? ''));
    if ($codice === '') $codice = nuovo_codice($lid);
    $r = [
        'codice' => $codice, 'libro_id' => $lid, 'pagina' => max(0, (int)($_POST['pagina'] ?? 0)),
        'titolo' => mb_substr(trim((string)($_POST['titolo'] ?? '')), 0, 200), 'testo' => mb_substr(trim((string)($_POST['testo'] ?? '')), 0, 20000),
        'voce' => (string)($_POST['voce'] ?? ''), 'tipo' => $vecchio['tipo'] ?? 'audio', 'file' => $vecchio['file'] ?? '',
    ];
    try {
        if (!codice_valido($codice)) throw new RuntimeException('Il codice deve essere fatto così: lettere, trattino, numero (es. CFC-12).');
        if (codice_usato($codice, 0, $pid)) throw new RuntimeException("Il codice $codice è già usato da un altro esercizio o contenuto.");
        $nuovo_file = null; $lavoro = null;
        $fonte = (string)($_POST['fonte'] ?? 'file');
        if ($fonte === 'voce' && $az === 'genera') {
            // la voce AI si prepara in coda (Luce e Vlad possono richiedere qualche minuto): il file arriva da solo quando è pronto
            if (!isset(voci_disponibili()[$r['voce']])) $r['voce'] = voce_predefinita();
            $lavoro = ai_campi_voce($r['voce'], $r['testo']);
            $r['tipo'] = 'audio';
        } else {
            $nuovo_file = salva_media('file');                     // file scelto o registrazione del microfono
            if ($nuovo_file) $r['tipo'] = $fonte === 'registra' ? 'audio' : $GLOBALS['ultimo_media_tipo'];
        }
        if ($nuovo_file) $r['file'] = $nuovo_file;
        if (!$pid && $r['file'] === '' && !$lavoro) throw new RuntimeException('Carica un file, registra la tua voce oppure genera la voce AI.');
        if ($pid) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($r)));
            db()->prepare("UPDATE contenuti SET $set WHERE id = :id")->execute($r + ['id' => $pid]);
            if ($vecchio['codice'] !== $codice) db()->prepare('UPDATE zone SET codice = ? WHERE codice = ?')->execute([$codice, $vecchio['codice']]);
            if ($nuovo_file && $vecchio['file'] !== $nuovo_file) elimina_file('media', $vecchio['file']);
        } else {
            db()->prepare('INSERT INTO contenuti (' . implode(',', array_keys($r)) . ') VALUES (:' . implode(',:', array_keys($r)) . ')')->execute($r);
            $pid = (int)db()->lastInsertId();
        }
        registra('Contenuto salvato', $codice);
        if ($lavoro) {
            ai_avvia('voce', '/audio/speech', $lavoro, ['voce' => $r['voce']], $pid);
            flash(ai_provider() === 'polza' ? "Salvato: $codice. La voce AI è pronta: ascoltala qui sotto."
                : "Salvato: $codice. La voce AI è in preparazione: il file arriverà da solo tra poco (puoi lasciare aperta questa pagina).");
            torna('contenuti.php?id=' . $pid);
        }
        flash("Salvato: $codice");
        torna('contenuti.php?id=' . $pid);
    } catch (RuntimeException $ex) {
        $errore = $ex->getMessage(); $id = $pid; $nuovo = !$pid; $bozza = $r + ['id' => $pid];
    }
}

if ($id || $nuovo) {
    if (isset($bozza)) $c = $bozza;
    elseif ($id) { $st = db()->prepare('SELECT * FROM contenuti WHERE id = ?'); $st->execute([$id]); $c = $st->fetch(); if (!$c) { flash('Non trovato.', 'errore'); torna('contenuti.php'); } }
    else $c = ['id' => 0, 'codice' => nuovo_codice($libro_f), 'libro_id' => $libro_f, 'pagina' => 0, 'titolo' => '', 'testo' => '', 'voce' => voce_predefinita(), 'tipo' => 'audio', 'file' => ''];
    admin_testata($c['id'] ? 'Modifica ' . $c['codice'] : 'Nuovo audio, video o immagine', 'contenuti');
    if (!empty($errore)) echo '<p class="errore">' . e($errore) . '</p>';
    $chiave_ok = ai_pronto();
    ?>
<form method="post" enctype="multipart/form-data" id="form-co" data-avvisa>
<?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
<div class="pannello"><div class="form-griglia">
  <label>Libro <select name="libro_id"><option value="0">— nessun libro —</option><?php foreach ($libri as $lb): ?><option value="<?= (int)$lb['id'] ?>" <?= (int)$c['libro_id'] === (int)$lb['id'] ? 'selected' : '' ?>><?= e($lb['titolo']) ?></option><?php endforeach; ?></select></label>
  <label>Pagina del libro <small>(0 = nessuna)</small><input type="number" name="pagina" min="0" value="<?= (int)$c['pagina'] ?>"></label>
  <label>Codice <input type="text" name="codice" value="<?= e($c['codice']) ?>" maxlength="12" style="text-transform:uppercase;font-family:monospace"></label>
  <label class="largo">Titolo <input type="text" name="titolo" value="<?= e($c['titolo']) ?>" placeholder="es. Luce spiega il soggetto" maxlength="200"></label>
</div></div>

<?php if ($c['id'] && ($lv = db()->query("SELECT * FROM lavori_ai WHERE contenuto_id = " . (int)$c['id'] . " AND stato IN ('attesa','scarico') ORDER BY id DESC")->fetch())): ?>
<p class="avviso" data-attesa-ai>⏳ La voce AI è in preparazione (avviata <?= e(formatta_data($lv['creato'])) ?>). La pagina si aggiorna da sola quando è pronta.</p>
<script src="../assets/ai-attesa.js"></script>
<?php endif; ?>
<?php if ($c['file'] !== ''): $src = url_media($c['file'], '../'); ?>
<div class="pannello"><h2 style="margin-top:0">File attuale (<?= e(TIPI_CONTENUTO[$c['tipo']][1] ?? $c['tipo']) ?>)</h2>
  <?php if ($c['tipo'] === 'audio'): ?><audio controls src="<?= e($src) ?>" style="width:100%"></audio>
  <?php elseif ($c['tipo'] === 'video'): ?><video controls src="<?= e($src) ?>" style="max-width:100%;max-height:360px"></video>
  <?php else: ?><img src="<?= e($src) ?>" alt="" style="max-width:100%;max-height:360px"><?php endif; ?>
  <p class="muto">Per sostituirlo scegli una delle strade qui sotto.</p></div>
<?php endif; ?>

<div class="pannello"><h2 style="margin-top:0"><?= $c['file'] !== '' ? 'Sostituisci il file' : 'Da dove arriva?' ?></h2>
<div class="tipi-es" style="margin-bottom:12px">
  <label class="tipo-es"><input type="radio" name="fonte" value="file" checked><span class="ic">📁</span><b>Carica un file</b><small>Audio (mp3, m4a, wav), video (mp4) o immagine</small></label>
  <label class="tipo-es"><input type="radio" name="fonte" value="registra"><span class="ic">🎙</span><b>Registra la tua voce</b><small>Dal microfono del computer o del telefono</small></label>
  <label class="tipo-es"><input type="radio" name="fonte" value="voce"><span class="ic">🤖</span><b>Voce AI</b><small><?= e(ai_nome()) ?> legge il testo qui sotto</small></label>
</div>
<div data-fonte="file"><label>File <input type="file" name="file" id="file-media" accept=".mp3,.m4a,.aac,.ogg,.wav,.webm,.mp4,.mov,.jpg,.jpeg,.png,.webp,.gif,audio/mpeg,audio/mp4,audio/wav,video/mp4,video/quicktime,image/jpeg,image/png"></label>
  <p class="muto">Audio (mp3, m4a, wav, ogg), video (mp4, mov, webm) o immagini. Sul telefono, se vedi solo la galleria, scegli «File» o «Sfoglia».</p></div>
<div data-fonte="registra" hidden>
  <div class="registratore" id="registratore"><button type="button" class="bottone" id="reg-via">⏺ Registra</button><button type="button" class="bottone sec" id="reg-stop" disabled>⏹ Ferma</button><span class="punto"></span><span id="reg-tempo" class="muto">0:00</span></div>
  <p class="muto" id="reg-stato">Premi «Registra», parla, poi «Ferma». Puoi riascoltare prima di salvare.</p>
  <audio id="reg-ascolta" controls hidden style="width:100%"></audio>
</div>
<div data-fonte="voce" hidden>
  <?php if (!$chiave_ok): ?><p class="errore">Per la voce AI serve la chiave di Polza.ai: inseriscila in <a href="scuola.php">Scuola → Polza.ai</a>.</p><?php endif; ?>
  <label>Voce <select name="voce"><?php foreach (voci_disponibili() as $k => $v): ?><option value="<?= e($k) ?>" <?= $c['voce'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
  <p class="muto">La voce legge il «Testo» qui sotto. Con Polza.ai la voce è pronta in pochi secondi (circa 2 ₽ ogni 1000 caratteri). Le voci si regolano in <a href="ai.php?vista=voci">Studio AI → Voci</a>.</p>
  <button class="bottone" name="azione" value="genera">🤖 Genera la voce e salva</button>
</div>
</div>

<div class="pannello"><label>Testo <small>(trascrizione dell'audio, descrizione dell'immagine; per la voce AI è il testo letto)</small><textarea name="testo" rows="6"><?= e($c['testo']) ?></textarea></label></div>

<div class="salva-barra">
  <button class="bottone" name="azione" value="salva">💾 Salva</button>
  <a class="bottone sec" href="contenuti.php<?= $c['libro_id'] ? '?libro=' . (int)$c['libro_id'] : '' ?>">Torna all'elenco</a>
  <?php if ($c['id']): ?><a class="bottone sec" href="../apri.php?c=<?= e($c['codice']) ?>" target="_blank">Apri sul sito ↗</a><?php endif; ?>
</div>
</form>
<?php if ($c['id']): ?>
<form method="post" class="pannello azioni-riga" style="margin-top:16px"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
  <button class="bottone pericolo piccolo" name="azione" value="elimina" data-conferma="Eliminare questo contenuto e il suo file?">🗑 Elimina</button></form>
<?php endif; ?>
<script src="../assets/scuola-admin.js"></script>
<?php
    admin_piede();
    exit;
}

$dove = $libro_f ? 'WHERE c.libro_id = ' . $libro_f : '';
$righe = db()->query("SELECT c.*, l.titolo AS libro FROM contenuti c LEFT JOIN libri l ON l.id = c.libro_id $dove ORDER BY c.libro_id, c.pagina, c.id")->fetchAll();
admin_testata('🔊 Audio, video e immagini', 'contenuti');
?>
<div class="azioni-riga" style="margin-bottom:12px">
  <a class="bottone" href="contenuti.php?nuovo=1<?= $libro_f ? '&amp;libro=' . $libro_f : '' ?>">+ Nuovo</a>
  <form method="get"><select name="libro" onchange="this.form.submit()"><option value="0">Tutti i libri</option><?php foreach ($libri as $lb): ?><option value="<?= (int)$lb['id'] ?>" <?= $libro_f === (int)$lb['id'] ? 'selected' : '' ?>><?= e($lb['titolo']) ?></option><?php endforeach; ?></select></form>
</div>
<table class="tabella"><tr><th>Codice</th><th>Titolo</th><th>Tipo</th><th>Libro</th><th>Pag.</th><th></th></tr>
<?php foreach ($righe as $r): $t = TIPI_CONTENUTO[$r['tipo']] ?? ['📎', $r['tipo']]; ?>
<tr><td><code><?= e($r['codice']) ?></code></td><td><a href="contenuti.php?id=<?= (int)$r['id'] ?>"><b><?= e($r['titolo'] !== '' ? $r['titolo'] : '(senza titolo)') ?></b></a><?= $r['file'] === '' ? ' <span class="stato prog">⏳ in preparazione</span>' : '' ?></td>
  <td><?= $t[0] ?> <?= e($t[1]) ?></td><td class="muto"><?= e($r['libro'] ?? '—') ?></td><td><?= $r['pagina'] ? (int)$r['pagina'] : '—' ?></td>
  <td class="azioni-riga"><a class="bottone sec piccolo" href="../apri.php?c=<?= e($r['codice']) ?>" target="_blank">Apri ↗</a>
    <button type="button" class="bottone sec piccolo" data-qr="<?= e(base_ufficiale() . 'apri.php?c=' . $r['codice']) ?>" data-nome="<?= e($r['codice']) ?>">QR</button></td></tr>
<?php endforeach; if (!$righe): ?><tr><td colspan="6" class="muto">Ancora niente.</td></tr><?php endif; ?>
</table>
<div id="qr-box" class="pannello" hidden style="margin-top:16px;text-align:center"><div id="qr-img" style="display:inline-block"></div><p id="qr-testo" class="muto"></p><a class="bottone piccolo" id="qr-scarica" download>Scarica il QR (PNG)</a></div>
<script src="../assets/vendor/qrcode.min.js"></script>
<script src="../assets/scuola-admin.js"></script>
<?php admin_piede();
