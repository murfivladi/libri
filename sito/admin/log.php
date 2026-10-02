<?php
require __DIR__ . '/_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    if (($_POST['azione'] ?? '') === 'svuota') { db()->exec('DELETE FROM log'); flash('Registro svuotato.'); }
    torna('log.php');
}
$q = trim($_GET['q'] ?? '');
$st = db()->prepare('SELECT * FROM log WHERE azione LIKE :q OR dettaglio LIKE :q OR ip LIKE :q ORDER BY id DESC LIMIT 300');
$st->execute([':q' => "%$q%"]);
$righe = $st->fetchAll();
admin_testata('Registro attività', 'log');
?>
<form method="get" class="filtri"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Filtra…"><button class="bottone piccolo">Filtra</button></form>
<table class="tabella"><tr><th>Quando</th><th>Azione</th><th>Dettaglio</th><th>IP</th></tr>
<?php foreach ($righe as $r): ?>
<tr><td class="muto"><?= e(formatta_data($r['quando'])) ?></td><td><?= e($r['azione']) ?></td><td><?= e($r['dettaglio']) ?></td><td class="muto"><?= e($r['ip']) ?></td></tr>
<?php endforeach; if (!$righe): ?><tr><td colspan="4" class="muto">Nessuna attività registrata.</td></tr><?php endif; ?></table>
<form method="post" style="margin-top:12px"><?= csrf_campo() ?><button class="bottone sec piccolo" name="azione" value="svuota" data-conferma="Cancellare tutto il registro?">Svuota il registro</button></form>
<?php admin_piede();
