<?php
require __DIR__ . '/_admin.php';

$id = (int)($_GET['id'] ?? 0);
$nuova = isset($_GET['nuova']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? '';
    $pid = (int)($_POST['id'] ?? 0);
    if ($az === 'salva') {
        $titolo = trim($_POST['titolo'] ?? '');
        if ($titolo === '') { flash('Il titolo è obbligatorio.', 'errore'); torna('pagine.php' . ($pid ? "?id=$pid" : '?nuova=1')); }
        $dati = ['titolo' => $titolo, 'slug' => slug_unico('pagine', trim($_POST['slug'] ?? '') ?: $titolo, $pid), 'contenuto' => trim($_POST['contenuto'] ?? ''),
                 'visibile' => empty($_POST['visibile']) ? 0 : 1, 'nel_menu' => empty($_POST['nel_menu']) ? 0 : 1, 'ordine' => (int)($_POST['ordine'] ?? 0)];
        if ($pid) { $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($dati))); $dati['id'] = $pid; db()->prepare("UPDATE pagine SET $set WHERE id = :id")->execute($dati); }
        else { db()->prepare('INSERT INTO pagine (' . implode(',', array_keys($dati)) . ') VALUES (:' . implode(',:', array_keys($dati)) . ')')->execute($dati); $pid = (int)db()->lastInsertId(); }
        registra('Pagina salvata', $titolo); flash('Pagina salvata.');
        torna(($_POST['dopo'] ?? '') === 'elenco' ? 'pagine.php' : "pagine.php?id=$pid");
    }
    if ($az === 'elimina' && $pid) { db()->prepare('DELETE FROM pagine WHERE id = ?')->execute([$pid]); registra('Pagina eliminata', (string)$pid); flash('Pagina eliminata.'); }
    if ($az === 'visibile' && $pid) db()->prepare('UPDATE pagine SET visibile = 1 - visibile WHERE id = ?')->execute([$pid]);
    if ($az === 'menu' && $pid) db()->prepare('UPDATE pagine SET nel_menu = 1 - nel_menu WHERE id = ?')->execute([$pid]);
    torna('pagine.php');
}

$p = null;
if ($id) { $p = db()->query("SELECT * FROM pagine WHERE id = $id")->fetch(); if (!$p) torna('pagine.php'); }
elseif ($nuova) $p = ['id' => 0, 'titolo' => '', 'slug' => '', 'contenuto' => '', 'visibile' => 1, 'nel_menu' => 1, 'ordine' => 0];

admin_testata($p ? ($id ? 'Modifica pagina' : 'Nuova pagina') : 'Pagine', 'pagine');
if ($p): ?>
<form method="post" class="pannello" data-avvisa>
  <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <div class="form-griglia">
    <label>Titolo * <input type="text" name="titolo" value="<?= e($p['titolo']) ?>" required></label>
    <label>Indirizzo (slug) <input type="text" name="slug" value="<?= e($p['slug']) ?>" placeholder="automatico"></label>
    <label>Ordine nel menu <input type="number" name="ordine" value="<?= (int)$p['ordine'] ?>"></label>
    <label><span>Opzioni</span><span><input type="checkbox" name="visibile" value="1" <?= $p['visibile'] ? 'checked' : '' ?>> Visibile</span>
      <span><input type="checkbox" name="nel_menu" value="1" <?= $p['nel_menu'] ? 'checked' : '' ?>> Nel menu in alto</span></label>
    <label class="largo">Testo <small>Formattazione: **grassetto**, *corsivo*, # Titolo, [testo](https://link). Riga vuota = nuovo paragrafo.</small>
      <textarea name="contenuto" rows="14"><?= e($p['contenuto']) ?></textarea></label>
  </div>
  <div class="salva-barra">
    <button class="bottone" name="azione" value="salva">💾 Salva</button>
    <button class="bottone sec" name="azione" value="salva" onclick="this.form.insertAdjacentHTML('beforeend','<input type=hidden name=dopo value=elenco>')">Salva e torna all'elenco</button>
    <?php if ($p['id']): ?><a class="bottone sec" target="_blank" href="../pagina.php?s=<?= e($p['slug']) ?>">Vedi ↗</a><?php endif; ?>
    <a class="bottone sec" href="pagine.php">Annulla</a>
  </div>
</form>
<?php else:
$pagine = db()->query('SELECT * FROM pagine ORDER BY ordine, id')->fetchAll(); ?>
<p><a class="bottone" href="pagine.php?nuova=1">+ Nuova pagina</a></p>
<table class="tabella"><tr><th>Titolo</th><th>Indirizzo</th><th>Visibile</th><th>Menu</th><th>Azioni</th></tr>
<?php foreach ($pagine as $x): $pid = (int)$x['id']; ?>
<tr><td><a href="pagine.php?id=<?= $pid ?>"><b><?= e($x['titolo']) ?></b></a></td><td class="muto">pagina.php?s=<?= e($x['slug']) ?></td>
  <td><span class="stato <?= $x['visibile'] ? 'pub' : 'bozza' ?>"><?= $x['visibile'] ? 'sì' : 'no' ?></span></td>
  <td><span class="stato <?= $x['nel_menu'] ? 'pub' : 'bozza' ?>"><?= $x['nel_menu'] ? 'sì' : 'no' ?></span></td>
  <td><form method="post" class="azioni-riga"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $pid ?>">
    <a class="bottone sec piccolo" href="pagine.php?id=<?= $pid ?>">Modifica</a>
    <button class="mini" name="azione" value="visibile"><?= $x['visibile'] ? 'Nascondi' : 'Mostra' ?></button>
    <button class="mini" name="azione" value="menu"><?= $x['nel_menu'] ? 'Togli dal menu' : 'Metti nel menu' ?></button>
    <button class="mini" name="azione" value="elimina" data-conferma="Eliminare la pagina «<?= e($x['titolo']) ?>»?">🗑</button></form></td></tr>
<?php endforeach; ?></table>
<?php endif; admin_piede();
