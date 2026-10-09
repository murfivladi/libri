<?php
// Lavagne dell'admin: elenco e editor video (assets/lavagna.js).
require __DIR__ . '/_admin.php';
require_once __DIR__ . '/../inc/lavagna.php';

$u = ['admin' => true, 'prof' => null];
if (isset($_GET['id']) || isset($_GET['nuova'])) {
    $l = isset($_GET['id']) ? lavagna_permessa((int)$_GET['id'], $u) : null;
    if (isset($_GET['id']) && !$l) { flash('Lavagna non trovata.', 'errore'); torna('lavagna.php'); }
    $libri = db()->query("SELECT id, titolo FROM libri WHERE sezione = 'scuola' AND eliminato IS NULL ORDER BY ordine, numero, id")->fetchAll();
    pagina_editor_lavagna($l, '../', '../lavagna-api.php', 'lavagna.php', ['libri' => $libri], 'admin');
    exit;
}
$righe = db()->query('SELECT l.*, b.titolo AS libro, c.codice FROM lavagne l LEFT JOIN libri b ON b.id = l.libro_id LEFT JOIN contenuti c ON c.id = l.contenuto_id WHERE l.prof_id = 0 ORDER BY l.aggiornato DESC, l.id DESC')->fetchAll();
admin_testata('🎬 Lavagna e video', 'lavagna');
?>
<p><a class="bottone" href="lavagna.php?nuova=1">+ Nuova lavagna</a></p>
<p class="muto">Prepara la lavagna a passi (gli oggetti compaiono e si spostano da soli) oppure registra dal vivo con la tua voce. Il video si può scaricare (MP4 o WebM) o salvare sul sito come video del libro, con il suo codice.</p>
<table class="tabella"><tr><th>Titolo</th><th>Libro</th><th>Video</th><th>Modificata</th></tr>
<?php foreach ($righe as $r): ?>
<tr><td><a href="lavagna.php?id=<?= (int)$r['id'] ?>"><b><?= e($r['titolo'] !== '' ? $r['titolo'] : '(senza titolo)') ?></b></a></td><td class="muto"><?= e($r['libro'] ?? '—') ?></td>
  <td><?= $r['video'] !== '' ? '<a href="' . e(url_media($r['video'], '../')) . '" target="_blank">▶ guarda</a>' . ($r['codice'] ? ' · <code>' . e($r['codice']) . '</code>' : '') : '<span class="muto">non ancora</span>' ?></td>
  <td class="muto"><?= e(formatta_data($r['aggiornato'] ?: $r['creato'])) ?></td></tr>
<?php endforeach; if (!$righe): ?><tr><td colspan="4" class="muto">Nessuna lavagna. Creane una!</td></tr><?php endif; ?>
</table>
<?php admin_piede();
