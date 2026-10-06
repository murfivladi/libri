<?php
require __DIR__ . '/_admin.php';
$stato = (string)($_GET['stato'] ?? ''); $q = trim((string)($_GET['q'] ?? ''));
if (($_GET['csv'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="richieste-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['N', 'Data', 'Stato', 'Nome', 'Email', 'Telefono', 'Per chi', 'Età', 'Occasione', 'Genere', 'Lingua', 'Formato', 'Lunghezza', 'Scadenza', 'Storia', 'Dedica', 'Indirizzo', 'CAP', 'Città', 'Paese', 'Note']);
    foreach (db()->query('SELECT * FROM richieste ORDER BY id DESC') as $r)
        fputcsv($o, array_map(fn($x) => preg_match('/^[=+\-@]/', (string)$x) ? "'" . $x : $x, [$r['id'], $r['creato'], $r['stato'], $r['nome'], $r['email'], $r['telefono'], $r['per_chi'], $r['eta'], $r['occasione'], $r['genere'], $r['lingua'], $r['formato'], $r['pagine'], $r['scadenza'], $r['storia'], $r['dedica'], $r['indirizzo'], $r['cap'], $r['citta'], $r['paese'], $r['note']]));
    exit;
}
$dove = []; $par = [];
if (isset(STATI[$stato])) { $dove[] = 'stato = ?'; $par[] = $stato; }
if ($q !== '') { $dove[] = '(nome LIKE ? OR email LIKE ? OR per_chi LIKE ? OR storia LIKE ?)'; array_push($par, "%$q%", "%$q%", "%$q%", "%$q%"); }
$st = db()->prepare('SELECT * FROM richieste' . ($dove ? ' WHERE ' . implode(' AND ', $dove) : '') . ' ORDER BY id DESC LIMIT 500'); $st->execute($par);
$ordini = $st->fetchAll();
$conta = db()->query('SELECT stato, COUNT(*) n FROM richieste GROUP BY stato')->fetchAll(PDO::FETCH_KEY_PAIR);
$nuovi = (int)db()->query('SELECT COUNT(*) FROM richieste WHERE letto = 0')->fetchColumn();
admin_testata('Richieste di libri', 'ordini');
?>
<div class="carte"><div class="carta"><b><?= $nuovi ?></b><span>da leggere</span></div><div class="carta"><b><?= array_sum($conta) ?></b><span>richieste totali</span></div>
<div class="carta"><b><?= (int)($conta['nuovo'] ?? 0) ?></b><span>da confermare</span></div></div>
<p class="schede"><a href="index.php" class="<?= $stato === '' ? 'att' : '' ?>">Tutti</a>
<?php foreach (STATI as $k => $et): ?><a href="?stato=<?= $k ?>" class="<?= $stato === $k ? 'att' : '' ?>"><?= $et ?> (<?= (int)($conta[$k] ?? 0) ?>)</a><?php endforeach; ?></p>
<form class="filtri"><input type="search" name="q" placeholder="Cerca nome, email, protagonista, storia…" value="<?= e($q) ?>"><?php if ($stato): ?><input type="hidden" name="stato" value="<?= e($stato) ?>"><?php endif; ?>
<button class="bottone piccolo">Cerca</button> <a class="bottone sec piccolo" href="?csv=1">⬇ Esporta CSV</a></form>
<?php if (!$ordini): ?><p class="muto">Nessuna richiesta.</p><?php else: ?>
<table class="tabella"><tr><th>N.</th><th>Data</th><th>Libro per</th><th>Cliente</th><th>Formato</th><th>Stato</th></tr>
<?php foreach ($ordini as $o): ?>
<tr><td><a href="ordine.php?id=<?= $o['id'] ?>"><?= $o['letto'] ? '' : '🔴 ' ?>#<?= $o['id'] ?></a></td><td><?= e(date('d/m/Y H:i', strtotime($o['creato']))) ?></td>
<td><b><?= e($o['per_chi']) ?></b><br><span class="muto"><?= e($o['genere']) ?></span></td><td><?= e($o['nome']) ?><br><span class="muto"><?= e($o['email']) ?></span></td><td><?= e($o['formato']) ?><br><span class="muto"><?= e($o['lingua']) ?></span></td>
<td><span class="stato <?= $o['stato'] === 'nuovo' ? 'bozza' : ($o['stato'] === 'annullato' ? 'cest' : 'pub') ?>"><?= e(STATI[$o['stato']] ?? $o['stato']) ?></span></td></tr>
<?php endforeach; ?></table><?php endif;
admin_piede();
