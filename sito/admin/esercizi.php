<?php
// Esercizi interattivi: elenco ed editor visivo (niente JSON da scrivere a mano: l'editor costruisce i dati da solo).
require __DIR__ . '/_admin.php';

$libri = db()->query("SELECT id, titolo FROM libri WHERE sezione = 'scuola' AND eliminato IS NULL ORDER BY ordine, numero, id")->fetchAll();
$libro_f = (int)($_GET['libro'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
$nuovo = !empty($_GET['nuovo']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? 'salva';
    $pid = (int)($_POST['id'] ?? 0);
    if ($az === 'elimina' && $pid) {
        db()->prepare('DELETE FROM esercizi WHERE id = ?')->execute([$pid]);
        registra('Esercizio eliminato', (string)$pid); flash('Esercizio eliminato.');
        torna('esercizi.php' . ($libro_f ? "?libro=$libro_f" : ''));
    }
    if ($az === 'duplica' && $pid) {
        $st = db()->prepare('SELECT * FROM esercizi WHERE id = ?'); $st->execute([$pid]);
        if ($e = $st->fetch()) {
            unset($e['id'], $e['creato']);
            $e['codice'] = nuovo_codice((int)$e['libro_id']); $e['titolo'] .= ' (copia)'; $e['aggiornato'] = date('Y-m-d H:i:s');
            db()->prepare('INSERT INTO esercizi (' . implode(',', array_keys($e)) . ') VALUES (:' . implode(',:', array_keys($e)) . ')')->execute($e);
            flash('Duplicato con il codice ' . $e['codice'] . '.');
            torna('esercizi.php?id=' . db()->lastInsertId());
        }
        torna('esercizi.php');
    }
    // salva
    $tipo = (string)($_POST['tipo'] ?? '');
    $lid = (int)($_POST['libro_id'] ?? 0);
    $codice = normalizza_codice((string)($_POST['codice'] ?? ''));
    if ($codice === '') $codice = nuovo_codice($lid);
    $err = '';
    if (!isset(TIPI_ESERCIZIO[$tipo])) $err = 'Scegli il tipo di esercizio.';
    elseif (!codice_valido($codice)) $err = 'Il codice deve essere fatto così: lettere, trattino, numero (es. CFC-12).';
    elseif (codice_usato($codice, $pid)) $err = "Il codice $codice è già usato da un altro esercizio o contenuto.";
    $dati = pulisci_dati_esercizio($tipo, json_decode((string)($_POST['dati'] ?? '{}'), true));
    if (!$err && $tipo !== 'aperta' && punti_esercizio(['tipo' => $tipo, 'dati' => json_encode($dati)]) === 0) $err = 'L\'esercizio è vuoto: aggiungi almeno una domanda o una frase completa (e, se serve, segna le parole o le risposte giuste).';
    if (!$err && $tipo === 'aperta' && empty($dati['domande'])) $err = 'Aggiungi almeno una domanda.';
    $r = [
        'codice' => $codice, 'libro_id' => $lid, 'pagina' => max(0, (int)($_POST['pagina'] ?? 0)), 'titolo' => mb_substr(trim((string)($_POST['titolo'] ?? '')), 0, 200),
        'consegna' => mb_substr(trim((string)($_POST['consegna'] ?? '')), 0, 2000), 'tipo' => $tipo, 'dati' => json_encode($dati, JSON_UNESCAPED_UNICODE),
        'extra' => empty($_POST['extra']) ? 0 : 1, 'livello' => max(1, min(3, (int)($_POST['livello'] ?? 1))), 'aggiornato' => date('Y-m-d H:i:s'),
    ];
    if ($err) {
        $errore = $err; $id = $pid; $nuovo = !$pid; $bozza = $r + ['id' => $pid];
    } else {
        if ($pid) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($r)));
            db()->prepare("UPDATE esercizi SET $set WHERE id = :id")->execute($r + ['id' => $pid]);
            registra('Esercizio modificato', $codice);
        } else {
            $r['ordine'] = (int)db()->query('SELECT COALESCE(MAX(ordine), 0) + 1 FROM esercizi')->fetchColumn();
            db()->prepare('INSERT INTO esercizi (' . implode(',', array_keys($r)) . ') VALUES (:' . implode(',:', array_keys($r)) . ')')->execute($r);
            $pid = (int)db()->lastInsertId();
            registra('Esercizio creato', $codice);
        }
        flash("Salvato: $codice");
        torna(($_POST['dopo'] ?? '') === 'nuovo' ? 'esercizi.php?nuovo=1&libro=' . $lid : 'esercizi.php?id=' . $pid);
    }
}

// ---------- editor ----------
if ($id || $nuovo) {
    if (isset($bozza)) $e = $bozza;
    elseif ($id) { $st = db()->prepare('SELECT * FROM esercizi WHERE id = ?'); $st->execute([$id]); $e = $st->fetch(); if (!$e) { flash('Esercizio non trovato.', 'errore'); torna('esercizi.php'); } }
    else $e = ['id' => 0, 'codice' => nuovo_codice($libro_f), 'libro_id' => $libro_f, 'pagina' => 0, 'titolo' => '', 'consegna' => '', 'tipo' => '', 'dati' => '{}', 'extra' => 0, 'livello' => 1];
    admin_testata($e['id'] ? 'Modifica esercizio ' . $e['codice'] : 'Nuovo esercizio', 'esercizi');
    if (!empty($errore)) echo '<p class="errore">' . e($errore) . '</p>';
    ?>
<link rel="stylesheet" href="../assets/scuola.css">
<form method="post" id="form-es" data-avvisa>
<?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><input type="hidden" name="dati" id="campo-dati" value="<?= e($e['dati']) ?>">
<div class="pannello"><div class="form-griglia">
  <label>Libro <select name="libro_id" id="libro"><option value="0">— nessun libro —</option><?php foreach ($libri as $lb): ?><option value="<?= (int)$lb['id'] ?>" <?= (int)$e['libro_id'] === (int)$lb['id'] ? 'selected' : '' ?>><?= e($lb['titolo']) ?></option><?php endforeach; ?></select></label>
  <label>Pagina del libro <small>(dove sta il simbolo; 0 = nessuna)</small><input type="number" name="pagina" min="0" value="<?= (int)$e['pagina'] ?>"></label>
  <label>Codice <small>(stampato nel libro e nel QR)</small><input type="text" name="codice" value="<?= e($e['codice']) ?>" maxlength="12" style="text-transform:uppercase;font-family:monospace"></label>
  <label>Livello <select name="livello"><?php foreach ([1 => '● Facile', 2 => '●● Medio', 3 => '●●● Difficile'] as $k => $v): ?><option value="<?= $k ?>" <?= (int)$e['livello'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
  <label class="largo">Titolo <input type="text" name="titolo" id="titolo" value="<?= e($e['titolo']) ?>" placeholder="es. Trova il soggetto" maxlength="200"></label>
  <label class="largo">Consegna <small>(cosa deve fare lo studente)</small><input type="text" name="consegna" id="consegna" value="<?= e($e['consegna']) ?>" placeholder="es. Tocca il soggetto di ogni frase." maxlength="2000"></label>
  <label><span>Solo online</span><span style="font-weight:400"><input type="checkbox" name="extra" value="1" <?= $e['extra'] ? 'checked' : '' ?>> Esercizio extra: non è stampato nel libro</span></label>
</div></div>

<div class="pannello"><h2 style="margin-top:0">Tipo di esercizio</h2>
<div class="tipi-es">
<?php foreach (TIPI_ESERCIZIO as $k => [$ic, $nome, $descr]): ?>
  <label class="tipo-es"><input type="radio" name="tipo" value="<?= $k ?>" <?= $e['tipo'] === $k ? 'checked' : '' ?> required><span class="ic"><?= $ic ?></span><b><?= e($nome) ?></b><small><?= e($descr) ?></small></label>
<?php endforeach; ?>
</div></div>

<div class="editor-due">
  <div class="pannello"><h2 style="margin-top:0">Contenuto</h2><div id="editor"><p class="muto">Scegli prima il tipo di esercizio.</p></div></div>
  <div class="pannello anteprima-es"><h2 style="margin-top:0">Anteprima <small class="muto">(come lo vede lo studente)</small></h2><div id="anteprima"></div></div>
</div>

<div class="salva-barra">
  <button class="bottone" name="dopo" value="resta">💾 Salva</button>
  <button class="bottone sec" name="dopo" value="nuovo">Salva e crea un altro</button>
  <a class="bottone sec" href="esercizi.php<?= $e['libro_id'] ? '?libro=' . (int)$e['libro_id'] : '' ?>">Torna all'elenco</a>
  <?php if ($e['id']): ?><a class="bottone sec" href="../apri.php?c=<?= e($e['codice']) ?>" target="_blank">Prova sul sito ↗</a><?php endif; ?>
</div>
</form>
<?php if ($e['id']): ?>
<form method="post" class="pannello azioni-riga" style="margin-top:16px"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
  <b style="align-self:center">Altre azioni:</b>
  <button class="bottone sec piccolo" name="azione" value="duplica">⧉ Duplica</button>
  <button class="bottone pericolo piccolo" name="azione" value="elimina" data-conferma="Eliminare questo esercizio?">🗑 Elimina</button>
</form>
<?php endif; ?>
<script src="../assets/esercizi.js"></script>
<script src="../assets/editor-esercizi.js"></script>
<?php
    admin_piede();
    exit;
}

// ---------- elenco ----------
$dove = $libro_f ? 'WHERE e.libro_id = ' . $libro_f : '';
$righe = db()->query("SELECT e.*, l.titolo AS libro FROM esercizi e LEFT JOIN libri l ON l.id = e.libro_id $dove ORDER BY e.libro_id, e.pagina, e.ordine, e.id")->fetchAll();
admin_testata('✏️ Esercizi interattivi', 'esercizi');
?>
<div class="azioni-riga" style="margin-bottom:12px">
  <a class="bottone" href="esercizi.php?nuovo=1<?= $libro_f ? '&amp;libro=' . $libro_f : '' ?>">+ Nuovo esercizio</a>
  <form method="get" style="display:flex;gap:6px"><select name="libro" onchange="this.form.submit()"><option value="0">Tutti i libri</option><?php foreach ($libri as $lb): ?><option value="<?= (int)$lb['id'] ?>" <?= $libro_f === (int)$lb['id'] ? 'selected' : '' ?>><?= e($lb['titolo']) ?></option><?php endforeach; ?></select></form>
</div>
<table class="tabella"><tr><th>Codice</th><th>Titolo</th><th>Tipo</th><th>Libro</th><th>Pag.</th><th>Punti</th><th></th></tr>
<?php foreach ($righe as $r): $t = TIPI_ESERCIZIO[$r['tipo']] ?? ['?', $r['tipo']]; ?>
<tr><td><code><?= e($r['codice']) ?></code></td>
  <td><a href="esercizi.php?id=<?= (int)$r['id'] ?>"><b><?= e($r['titolo'] !== '' ? $r['titolo'] : '(senza titolo)') ?></b></a><?= $r['extra'] ? ' <span class="stato prog">solo online</span>' : '' ?> <span class="muto"><?= str_repeat('●', (int)$r['livello']) ?></span></td>
  <td><?= $t[0] ?> <?= e($t[1]) ?></td><td class="muto"><?= e($r['libro'] ?? '—') ?></td><td><?= $r['pagina'] ? (int)$r['pagina'] : '—' ?></td><td><?= punti_esercizio($r) ?: '—' ?></td>
  <td class="azioni-riga"><a class="bottone sec piccolo" href="../apri.php?c=<?= e($r['codice']) ?>" target="_blank">Prova ↗</a>
    <button type="button" class="bottone sec piccolo" data-qr="<?= e(base_ufficiale() . 'apri.php?c=' . $r['codice']) ?>" data-nome="<?= e($r['codice']) ?>">QR</button></td></tr>
<?php endforeach; if (!$righe): ?><tr><td colspan="7" class="muto">Nessun esercizio. Crea il primo!</td></tr><?php endif; ?>
</table>
<div id="qr-box" class="pannello" hidden style="margin-top:16px;text-align:center"><div id="qr-img" style="display:inline-block"></div><p id="qr-testo" class="muto"></p><a class="bottone piccolo" id="qr-scarica" download>Scarica il QR (PNG)</a></div>
<script src="../assets/vendor/qrcode.min.js"></script>
<script src="../assets/scuola-admin.js"></script>
<?php admin_piede();
