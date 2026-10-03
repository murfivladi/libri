<?php
require __DIR__ . '/_admin.php';
require __DIR__ . '/../inc/libri.php';

$id = (int)($_GET['id'] ?? 0);
$nuova = isset($_GET['nuova']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? '';
    $cid = (int)($_POST['id'] ?? 0);
    if ($az === 'salva_ordine') { riordina_tabella('collane', $_POST['ids'] ?? []); registra('Collane riordinate (trascinamento)'); http_response_code(204); exit; }
    try {
        if ($az === 'salva') {
            $nome = trim($_POST['nome'] ?? '');
            if ($nome === '') throw new RuntimeException('Il nome è obbligatorio.');
            $att = $cid ? db()->query("SELECT * FROM collane WHERE id = $cid")->fetch() : ['banner' => ''];
            $banner = $att['banner'];
            if (!empty($_POST['togli_banner'])) $banner = '';
            if ($b = salva_file('banner', 'cover', TIPI_IMMAGINE, 8 * 1024 * 1024)) $banner = $b;
            $dati = ['nome' => $nome, 'slug' => slug_unico('collane', trim($_POST['slug'] ?? '') ?: $nome, $cid), 'descrizione' => trim($_POST['descrizione'] ?? ''), 'nome_ru' => trim($_POST['nome_ru'] ?? ''), 'descrizione_ru' => trim($_POST['descrizione_ru'] ?? ''),
                     'banner' => $banner, 'visibile' => empty($_POST['visibile']) ? 0 : 1, 'ordine' => (int)($_POST['ordine'] ?? 0)];
            if ($cid) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($dati))); $dati['id'] = $cid;
                db()->prepare("UPDATE collane SET $set WHERE id = :id")->execute($dati);
                db()->prepare('UPDATE libri SET collana = ? WHERE collana_id = ?')->execute([$nome, $cid]);
                if ($banner !== $att['banner']) elimina_file('cover', $att['banner']);
            } else {
                db()->prepare('INSERT INTO collane (' . implode(',', array_keys($dati)) . ') VALUES (:' . implode(',:', array_keys($dati)) . ')')->execute($dati);
            }
            registra('Collana salvata', $nome); flash('Collana salvata: ' . $nome); torna('collane.php');
        }
        if ($az === 'elimina' && $cid) {
            $c = db()->query("SELECT * FROM collane WHERE id = $cid")->fetch();
            if ($c) {
                db()->prepare("UPDATE libri SET collana_id = 0, collana = '' WHERE collana_id = ?")->execute([$cid]);
                db()->prepare('DELETE FROM collane WHERE id = ?')->execute([$cid]);
                elimina_file('cover', $c['banner']);
                registra('Collana eliminata', $c['nome']); flash('Collana eliminata (i libri restano, senza collana).');
            }
            torna('collane.php');
        }
        if ($az === 'visibile' && $cid) { db()->prepare('UPDATE collane SET visibile = 1 - visibile WHERE id = ?')->execute([$cid]); torna('collane.php'); }
        if (in_array($az, ['su', 'giu'], true) && $cid) {
            $ids = array_map('intval', db()->query('SELECT id FROM collane ORDER BY ordine, nome')->fetchAll(PDO::FETCH_COLUMN));
            $i = array_search($cid, $ids, true); $j = $i + ($az === 'su' ? -1 : 1);
            if ($i !== false && isset($ids[$j])) {
                [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                foreach ($ids as $k => $x) db()->prepare('UPDATE collane SET ordine = ? WHERE id = ?')->execute([$k + 1, $x]);
            }
            torna('collane.php');
        }
        if (in_array($az, ['pubblica_tutti', 'nascondi_tutti', 'cestina_tutti', 'riordina'], true) && $cid) {
            $ids = db()->query("SELECT id FROM libri WHERE collana_id = $cid AND eliminato IS NULL ORDER BY numero, id")->fetchAll(PDO::FETCH_COLUMN);
            if ($az === 'riordina') {      // numera i libri 1,2,3… secondo l'ordine attuale
                foreach ($ids as $k => $x) db()->prepare('UPDATE libri SET numero = ? WHERE id = ?')->execute([$k + 1, $x]);
                flash('Libri numerati da 1 a ' . count($ids) . '.');
            } else {
                flash(azione_libri(['pubblica_tutti' => 'pubblica', 'nascondi_tutti' => 'nascondi', 'cestina_tutti' => 'cestino'][$az], $ids, []));
            }
            torna('collane.php');
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'errore'); torna('collane.php' . ($cid ? "?id=$cid" : '?nuova=1')); }
}

$c = null;
if ($id) { $c = db()->query("SELECT * FROM collane WHERE id = $id")->fetch(); if (!$c) torna('collane.php'); }
elseif ($nuova) $c = ['id' => 0, 'nome' => '', 'slug' => '', 'descrizione' => '', 'nome_ru' => '', 'descrizione_ru' => '', 'banner' => '', 'visibile' => 1, 'ordine' => 0];

admin_testata($c ? ($id ? 'Modifica collana' : 'Nuova collana') : 'Collane', 'collane');

if ($c): ?>
<form method="post" enctype="multipart/form-data" class="pannello" data-avvisa>
  <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
  <div class="form-griglia">
    <label>Nome * <input type="text" name="nome" value="<?= e($c['nome']) ?>" required></label>
    <label>Indirizzo (slug) <input type="text" name="slug" value="<?= e($c['slug']) ?>" placeholder="automatico"></label>
    <label>Ordine <input type="number" name="ordine" value="<?= (int)$c['ordine'] ?>"></label>
    <label><span>Visibilità</span><span><input type="checkbox" name="visibile" value="1" <?= $c['visibile'] ? 'checked' : '' ?>> Mostra la collana nel sito</span></label>
    <label class="largo">Descrizione <textarea name="descrizione" rows="5"><?= e($c['descrizione']) ?></textarea></label>
    <label>🇷🇺 Название (nome in russo) <input type="text" name="nome_ru" value="<?= e($c['nome_ru'] ?? '') ?>" lang="ru"></label>
    <label class="largo">🇷🇺 Описание (descrizione in russo) <textarea name="descrizione_ru" rows="5" lang="ru"><?= e($c['descrizione_ru'] ?? '') ?></textarea></label>
    <div class="largo"><?php if ($c['banner']): ?><img class="anteprima-cop" style="max-width:260px" src="<?= e(con_prefisso(miniatura($c['banner'], 400), '../')) ?>" alt=""><br>
      <label style="font-weight:400"><span><input type="checkbox" name="togli_banner" value="1"> Togli l'immagine</span></label><?php endif; ?>
      <label>Immagine della collana (opzionale) <input type="file" name="banner" accept="image/*"></label>
      <label>…oppure da URL <input type="text" name="banner_url"></label></div>
  </div>
  <div class="salva-barra"><button class="bottone" name="azione" value="salva">💾 Salva</button><a class="bottone sec" href="collane.php">Annulla</a></div>
</form>
<?php else:
$collane = db()->query('SELECT c.*, (SELECT COUNT(*) FROM libri l WHERE l.collana_id = c.id AND l.eliminato IS NULL) AS n,
    (SELECT COUNT(*) FROM libri l WHERE l.collana_id = c.id AND ' . sql_visibile('l') . ') AS np FROM collane c ORDER BY c.ordine, c.nome')->fetchAll();
$senza = (int)db()->query('SELECT COUNT(*) FROM libri WHERE collana_id = 0 AND eliminato IS NULL')->fetchColumn();
?>
<p><a class="bottone" href="collane.php?nuova=1">+ Nuova collana</a></p>
<?php if (count($collane) > 1): ?><p class="muto">☰ Trascina la maniglia per cambiare l'ordine delle collane (anche col dito).</p><?php endif; ?>
<table class="tabella" data-ordina="collane" data-csrf="<?= e(csrf()) ?>">
<thead><tr><th></th><th>Collana</th><th>Libri</th><th>Pubblicati</th><th>Visibile</th><th>Azioni</th></tr></thead><tbody>
<?php foreach ($collane as $x): $cid = (int)$x['id']; ?>
<tr data-id="<?= $cid ?>">
  <td class="maniglia" title="Trascina per riordinare">⠿</td>
  <td><a href="collane.php?id=<?= $cid ?>"><b><?= e($x['nome']) ?></b></a><br><span class="muto"><?= e(mb_substr($x['descrizione'], 0, 90)) ?></span></td>
  <td><a href="libri.php?collana=<?= $cid ?>"><?= (int)$x['n'] ?></a></td><td><?= (int)$x['np'] ?></td>
  <td><span class="stato <?= $x['visibile'] ? 'pub' : 'bozza' ?>"><?= $x['visibile'] ? 'sì' : 'no' ?></span></td>
  <td><form method="post" class="azioni-riga"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $cid ?>">
    <a class="bottone sec piccolo" href="collane.php?id=<?= $cid ?>">Modifica</a>
    <button class="mini" name="azione" value="visibile"><?= $x['visibile'] ? 'Nascondi' : 'Mostra' ?></button>
    <button class="mini" name="azione" value="su">↑</button><button class="mini" name="azione" value="giu">↓</button>
    <button class="mini" name="azione" value="pubblica_tutti" data-conferma="Pubblicare tutti i libri di questa collana?">Pubblica tutti</button>
    <button class="mini" name="azione" value="nascondi_tutti" data-conferma="Nascondere tutti i libri di questa collana?">Nascondi tutti</button>
    <button class="mini" name="azione" value="riordina" data-conferma="Numerare i libri 1, 2, 3… secondo l'ordine attuale?">Numera 1…n</button>
    <button class="mini" name="azione" value="elimina" data-conferma="Eliminare la collana «<?= e($x['nome']) ?>»? I libri restano.">🗑</button>
  </form></td>
</tr>
<?php endforeach; if (!$collane): ?><tr><td colspan="6" class="muto">Nessuna collana ancora.</td></tr><?php endif; ?>
</tbody></table>
<p class="muto">Libri senza collana: <a href="libri.php?collana=nessuna"><?= $senza ?></a></p>
<?php endif; admin_piede();
