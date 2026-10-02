<?php
require __DIR__ . '/_admin.php';
$q = fn(string $sql) => (int)db()->query($sql)->fetchColumn();
$ora = date('Y-m-d H:i:s');
$n = [
    'Libri' => [$q('SELECT COUNT(*) FROM libri WHERE eliminato IS NULL'), 'libri.php'],
    'Pubblicati' => [$q('SELECT COUNT(*) FROM libri WHERE ' . sql_visibile()), 'libri.php?stato=pubblicati'],
    'Bozze' => [$q('SELECT COUNT(*) FROM libri WHERE pubblicato = 0 AND eliminato IS NULL'), 'libri.php?stato=bozze'],
    'Programmati' => [$q("SELECT COUNT(*) FROM libri WHERE pubblicato = 1 AND eliminato IS NULL AND pubblica_il > '$ora'"), 'libri.php?stato=programmati'],
    'Collane' => [$q('SELECT COUNT(*) FROM collane'), 'collane.php'],
    'Pagine' => [$q('SELECT COUNT(*) FROM pagine'), 'pagine.php'],
    'Letture' => [$q('SELECT COALESCE(SUM(letture),0) FROM libri'), 'libri.php?ord=letture&dir=desc'],
];
$piu_letti = db()->query('SELECT id, titolo, letture FROM libri WHERE eliminato IS NULL AND letture > 0 ORDER BY letture DESC LIMIT 5')->fetchAll();
$recenti = db()->query('SELECT id, titolo, aggiornato, pubblicato FROM libri WHERE eliminato IS NULL ORDER BY aggiornato DESC, id DESC LIMIT 6')->fetchAll();
$spazio = 0;
if (dir_uploads() !== null) foreach (['pdf', 'cover'] as $c) foreach (glob(dir_uploads() . "/$c/*") ?: [] as $f) if (is_file($f) && basename($f)[0] !== '.') $spazio += filesize($f);

// avvisi utili
$avvisi = [];
if (impost('manutenzione') === '1') $avvisi[] = ['⚠ La modalità manutenzione è ATTIVA: i visitatori non vedono il sito.', 'impostazioni.php'];
$s = $q('SELECT COUNT(*) FROM libri WHERE pubblicato = 1 AND eliminato IS NULL AND pdf = ""');
if ($s) $avvisi[] = ["$s libri pubblicati senza PDF", 'libri.php?stato=senza_pdf'];
$s = $q('SELECT COUNT(*) FROM libri WHERE pubblicato = 1 AND eliminato IS NULL AND copertina = ""');
if ($s) $avvisi[] = ["$s libri pubblicati senza copertina", 'libri.php?stato=senza_copertina'];
$s = $q('SELECT COUNT(*) FROM libri WHERE pubblicato = 1 AND eliminato IS NULL AND trama = ""');
if ($s) $avvisi[] = ["$s libri pubblicati senza trama", 'libri.php?stato=senza_trama'];
$s = $q('SELECT COUNT(*) FROM libri WHERE eliminato IS NOT NULL');
if ($s) $avvisi[] = ["$s libri nel cestino", 'libri.php?stato=cestino'];

admin_testata('Dashboard', 'dashboard');
?>
<div class="carte">
<?php foreach ($n as $et => [$v, $url]): ?>
  <a class="carta" href="<?= e($url) ?>"><b><?= number_format($v, 0, ',', '.') ?></b><span><?= e($et) ?></span></a>
<?php endforeach; ?>
  <div class="carta"><b><?= e(formatta_byte($spazio)) ?></b><span>Spazio file</span></div>
</div>

<?php if ($avvisi): ?>
<div class="pannello"><b>Da sistemare</b><ul>
<?php foreach ($avvisi as [$t, $u]): ?><li><a href="<?= e($u) ?>"><?= e($t) ?></a></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<div class="griglia2">
  <div class="pannello"><b>Ultimi modificati</b>
    <table class="tabella"><?php foreach ($recenti as $r): ?>
      <tr><td><a href="libro.php?id=<?= (int)$r['id'] ?>"><?= e($r['titolo']) ?></a></td>
      <td class="muto"><?= e(formatta_data($r['aggiornato'])) ?></td>
      <td><span class="stato <?= $r['pubblicato'] ? 'pub' : 'bozza' ?>"><?= $r['pubblicato'] ? 'pubblicato' : 'bozza' ?></span></td></tr>
    <?php endforeach; if (!$recenti): ?><tr><td class="muto">Nessun libro: <a href="libro.php">creane uno</a>.</td></tr><?php endif; ?></table>
  </div>
  <div class="pannello"><b>Più letti e scaricati</b>
    <table class="tabella"><?php foreach ($piu_letti as $r): ?>
      <tr><td><a href="libro.php?id=<?= (int)$r['id'] ?>"><?= e($r['titolo']) ?></a></td><td>👁 <?= (int)$r['letture'] ?></td></tr>
    <?php endforeach; if (!$piu_letti): ?><tr><td class="muto">Ancora nessuna lettura.</td></tr><?php endif; ?></table>
  </div>
</div>

<div class="pannello">
  <b>Azioni rapide</b><p class="azioni-riga">
    <a class="bottone" href="libro.php">+ Nuovo libro</a>
    <a class="bottone sec" href="collane.php">+ Nuova collana</a>
    <a class="bottone sec" href="pagine.php">+ Nuova pagina</a>
    <a class="bottone sec" href="strumenti.php">Backup e strumenti</a>
    <a class="bottone sec" href="../index.php" target="_blank">Vedi il sito ↗</a>
  </p>
  <p class="muto">Premi <kbd>?</kbd> per vedere le scorciatoie da tastiera.</p>
</div>
<?php admin_piede();
