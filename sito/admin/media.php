<?php
require __DIR__ . '/_admin.php';

$dir = dir_uploads();
if ($dir === null) {                    // sito secondario senza accesso diretto alla cartella dei file
    admin_testata('File caricati', 'media');
    echo '<div class="pannello"><p>I file di questo sito stanno sul sito principale (<code>' . e(file_base()) . '</code>) e da qui non si possono elencare.</p>'
       . '<p>Per vedere e pulire i file entra nell\'admin del sito principale: <a href="' . e(file_base()) . '/admin/media.php" target="_blank">' . e(file_base()) . '/admin/media.php ↗</a></p></div>';
    admin_piede();
    exit;
}

// Mappa: nome del file → a chi appartiene (i valori nel database possono essere indirizzi interi o nomi)
$usati = ['pdf' => [], 'cover' => []];
foreach (db()->query('SELECT id, titolo, copertina, pdf, pdf_ru FROM libri') as $l) {
    if ($l['copertina']) $usati['cover'][nome_file($l['copertina'])][] = ['libro.php?id=' . $l['id'], 'Libro: ' . $l['titolo']];
    if ($l['pdf_ru']) $usati['pdf'][nome_file($l['pdf_ru'])][] = ['libro.php?id=' . $l['id'], 'Libro (russo): ' . $l['titolo']];
    if ($l['pdf']) $usati['pdf'][nome_file($l['pdf'])][] = ['libro.php?id=' . $l['id'], 'Libro: ' . $l['titolo']];
}
foreach (db()->query("SELECT id, nome, banner FROM collane WHERE banner != ''") as $c) $usati['cover'][nome_file($c['banner'])][] = ['collane.php?id=' . $c['id'], 'Collana: ' . $c['nome']];
foreach (db()->query("SELECT chiave, valore FROM impostazioni WHERE chiave IN ('logo_emblema','logo_grande','banner_home') AND valore != ''") as $s)
    $usati['cover'][nome_file($s['valore'])][] = ['aspetto.php', 'Aspetto: ' . $s['chiave']];

function elenco_file(string $dir, string $cartella): array {
    $out = [];
    foreach (glob("$dir/$cartella/*") ?: [] as $f) {
        if (is_file($f) && basename($f)[0] !== '.') $out[basename($f)] = ['dim' => filesize($f), 'data' => filemtime($f)];
    }
    krsort($out);
    return $out;
}
$files = ['pdf' => elenco_file($dir, 'pdf'), 'cover' => elenco_file($dir, 'cover')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? '';
    $n = 0;
    foreach (['pdf', 'cover'] as $c) {
        foreach ($files[$c] as $nome => $_) {
            $orfano = empty($usati[$c][$nome]);
            $scelto = in_array($c . '/' . $nome, $_POST['sel'] ?? [], true);
            if ($orfano && ($az === 'elimina_orfani' || ($az === 'elimina_sel' && $scelto))) { elimina_fisico($c, $nome); $n++; }
        }
    }
    registra('File orfani eliminati', (string)$n);
    flash("$n file eliminati.");
    torna('media.php');
}

$tot = 0; $orfani = 0; $orf_dim = 0;
foreach ($files as $c => $lista) foreach ($lista as $nome => $i) { $tot += $i['dim']; if (empty($usati[$c][$nome])) { $orfani++; $orf_dim += $i['dim']; } }
$indirizzo = fn(string $c, string $nome) => file_base() !== '' ? valore_file($c, $nome) : '../uploads/' . $c . '/' . $nome;
admin_testata('File caricati', 'media');
?>
<div class="carte">
  <div class="carta"><b><?= count($files['pdf']) ?></b><span>PDF</span></div>
  <div class="carta"><b><?= count($files['cover']) ?></b><span>Immagini</span></div>
  <div class="carta"><b><?= e(formatta_byte($tot)) ?></b><span>Spazio usato</span></div>
  <div class="carta"><b><?= $orfani ?></b><span>File orfani (<?= e(formatta_byte($orf_dim)) ?>)</span></div>
</div>
<p class="muto">Un file è «orfano» quando nessun libro o collana lo usa più: si può eliminare senza problemi.<?= sito_principale() ? '' : ' (Stai agendo direttamente sulla cartella del sito principale.)' ?></p>
<form method="post"><?= csrf_campo() ?>
<?php foreach (['pdf' => 'PDF', 'cover' => 'Immagini'] as $c => $et): ?>
<h2><?= $et ?></h2>
<table class="tabella"><tr><th></th><th>File</th><th>Dimensione</th><th>Caricato</th><th>Usato da</th></tr>
<?php foreach ($files[$c] as $nome => $i): $u = $usati[$c][$nome] ?? []; ?>
<tr><td><?= $u ? '' : '<input type="checkbox" name="sel[]" value="' . e($c . '/' . $nome) . '">' ?></td>
  <td><?php if ($c === 'cover'): ?><img class="mini-cop" src="<?= e(con_prefisso(miniatura(valore_file('cover', $nome), 120), '../')) ?>" alt="" loading="lazy" style="display:inline-block;vertical-align:middle;margin-right:8px"><?php endif; ?>
    <a href="<?= e($indirizzo($c, $nome)) ?>" target="_blank"><?= e($nome) ?></a></td>
  <td><?= e(formatta_byte($i['dim'])) ?></td><td class="muto"><?= date('d/m/Y H:i', $i['data']) ?></td>
  <td><?php foreach ($u as [$url, $t]): ?><a href="<?= e($url) ?>"><?= e($t) ?></a><br><?php endforeach; if (!$u): ?><span class="stato bozza">orfano</span><?php endif; ?></td></tr>
<?php endforeach; if (!$files[$c]): ?><tr><td colspan="5" class="muto">Nessun file.</td></tr><?php endif; ?></table>
<?php endforeach; ?>
<?php if ($orfani): ?><p class="azioni-riga" style="margin-top:12px">
  <button class="bottone sec piccolo" name="azione" value="elimina_sel" data-conferma="Eliminare i file orfani selezionati?">Elimina i selezionati</button>
  <button class="bottone pericolo piccolo" name="azione" value="elimina_orfani" data-conferma="Eliminare TUTTI i file orfani (<?= $orfani ?>)?">Elimina tutti gli orfani</button></p><?php endif; ?>
</form>
<?php admin_piede();
