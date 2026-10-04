<?php
require __DIR__ . '/_admin.php';
$stato = (string)($_GET['stato'] ?? ''); $q = trim((string)($_GET['q'] ?? ''));
if (($_GET['csv'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="ordini-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['N', 'Data', 'Stato', 'Libro', 'Qtà', 'Prezzo', 'Nome', 'Email', 'Telefono', 'Indirizzo', 'CAP', 'Città', 'Paese', 'Note']);
    foreach (db()->query('SELECT * FROM ordini ORDER BY id DESC') as $r)
        fputcsv($o, array_map(fn($x) => preg_match('/^[=+\-@]/', (string)$x) ? "'" . $x : $x, [$r['id'], $r['creato'], $r['stato'], $r['titolo_libro'], $r['quantita'], $r['prezzo'], $r['nome'], $r['email'], $r['telefono'], $r['indirizzo'], $r['cap'], $r['citta'], $r['paese'], $r['note']]));
    exit;
}
$dove = []; $par = [];
if (isset(STATI[$stato])) { $dove[] = 'stato = ?'; $par[] = $stato; }
if ($q !== '') { $dove[] = '(nome LIKE ? OR email LIKE ? OR titolo_libro LIKE ? OR citta LIKE ?)'; array_push($par, "%$q%", "%$q%", "%$q%", "%$q%"); }
$st = db()->prepare('SELECT * FROM ordini' . ($dove ? ' WHERE ' . implode(' AND ', $dove) : '') . ' ORDER BY id DESC LIMIT 500'); $st->execute($par);
$ordini = $st->fetchAll();
$conta = db()->query('SELECT stato, COUNT(*) n FROM ordini GROUP BY stato')->fetchAll(PDO::FETCH_KEY_PAIR);
$nuovi = (int)db()->query('SELECT COUNT(*) FROM ordini WHERE letto = 0')->fetchColumn();
admin_testata('Ordini', 'ordini');
?>
<div class="carte"><div class="carta"><b><?= $nuovi ?></b><span>da leggere</span></div><div class="carta"><b><?= array_sum($conta) ?></b><span>ordini totali</span></div>
<div class="carta"><b><?= (int)($conta['nuovo'] ?? 0) ?></b><span>da confermare</span></div></div>
<p class="schede"><a href="index.php" class="<?= $stato === '' ? 'att' : '' ?>">Tutti</a>
<?php foreach (STATI as $k => $et): ?><a href="?stato=<?= $k ?>" class="<?= $stato === $k ? 'att' : '' ?>"><?= $et ?> (<?= (int)($conta[$k] ?? 0) ?>)</a><?php endforeach; ?></p>
<form class="filtri"><input type="search" name="q" placeholder="Cerca nome, email, libro, città…" value="<?= e($q) ?>"><?php if ($stato): ?><input type="hidden" name="stato" value="<?= e($stato) ?>"><?php endif; ?>
<button class="bottone piccolo">Cerca</button> <a class="bottone sec piccolo" href="?csv=1">⬇ Esporta CSV</a></form>
<?php if (!$ordini): ?><p class="muto">Nessun ordine.</p><?php else: ?>
<table class="tabella"><tr><th>N.</th><th>Data</th><th>Libro</th><th>Cliente</th><th>Città</th><th>Stato</th></tr>
<?php foreach ($ordini as $o): ?>
<tr><td><a href="ordine.php?id=<?= $o['id'] ?>"><?= $o['letto'] ? '' : '🔴 ' ?>#<?= $o['id'] ?></a></td><td><?= e(date('d/m/Y H:i', strtotime($o['creato']))) ?></td>
<td><?= e($o['titolo_libro']) ?> × <?= (int)$o['quantita'] ?></td><td><?= e($o['nome']) ?><br><span class="muto"><?= e($o['email']) ?></span></td><td><?= e($o['citta']) ?></td>
<td><span class="stato <?= $o['stato'] === 'nuovo' ? 'bozza' : ($o['stato'] === 'annullato' ? 'cest' : 'pub') ?>"><?= e(STATI[$o['stato']] ?? $o['stato']) ?></span></td></tr>
<?php endforeach; ?></table><?php endif;
admin_piede();
