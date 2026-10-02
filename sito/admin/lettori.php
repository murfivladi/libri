<?php
require __DIR__ . '/_admin.php';

$STATI = [0 => 'da confermare', 1 => 'attivo', 2 => 'sospeso'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $st = db()->prepare('SELECT * FROM lettori WHERE id = ?'); $st->execute([$id]);
    $lt = $st->fetch();
    if ($az === 'pulisci') {
        $n = pulisci_lettori(); registra('Pulizia lettori non confermati', "$n cancellati"); flash("Cancellati $n account mai confermati (più vecchi di 7 giorni).");
    } elseif ($lt) {
        switch ($az) {
            case 'conferma':
                db()->prepare('UPDATE lettori SET stato = 1, verificato = ? WHERE id = ? AND stato = 0')->execute([date('Y-m-d H:i:s'), $id]);
                registra('Lettore confermato a mano', $lt['nick']); flash("{$lt['nick']} confermato."); break;
            case 'sospendi':
                db()->prepare('UPDATE lettori SET stato = 2 WHERE id = ?')->execute([$id]); dimentica_dispositivi($id);
                registra('Lettore sospeso', $lt['nick']); flash("{$lt['nick']} sospeso: non può più accedere."); break;
            case 'riattiva':
                db()->prepare('UPDATE lettori SET stato = 1 WHERE id = ? AND stato = 2')->execute([$id]);
                registra('Lettore riattivato', $lt['nick']); flash("{$lt['nick']} riattivato."); break;
            case 'reset':
                $t = crea_token($id, 'reset', 60);
                $err = invia_email($lt['email'], 'Reimposta la password · ' . impost('nome_sito'), "Ciao {$lt['nick']},\nl'amministratore ti manda questo link per scegliere una nuova password (vale un'ora).", url_sito('account.php?a=reimposta&t=' . $t), 'Scegli una nuova password');
                if ($err === null) { registra('Reset password inviato', $lt['nick']); flash("Email di reset spedita a {$lt['nick']}."); } else flash('Invio non riuscito: ' . $err, 'errore');
                break;
            case 'elimina':
                cancella_lettore($id); registra('Lettore eliminato', $lt['nick']); flash("{$lt['nick']} eliminato con tutti i suoi dati."); break;
        }
    }
    torna('lettori.php' . (!empty($_POST['q']) ? '?q=' . urlencode($_POST['q']) : ''));
}

$q = trim($_GET['q'] ?? '');
$stato = $_GET['stato'] ?? 'tutti';
$dove = []; $par = [];
if ($q !== '') { $dove[] = '(email LIKE ? OR nick LIKE ?)'; $par[] = "%$q%"; $par[] = "%$q%"; }
if (in_array($stato, ['0', '1', '2'], true)) { $dove[] = 'stato = ' . (int)$stato; }
$w = $dove ? 'WHERE ' . implode(' AND ', $dove) : '';
$st = db()->prepare("SELECT l.*, (SELECT COUNT(*) FROM liste x WHERE x.lettore_id = l.id) AS n_liste, (SELECT COUNT(*) FROM recensioni r WHERE r.lettore_id = l.id) AS n_rec FROM lettori l $w ORDER BY l.id DESC LIMIT 200");
$st->execute($par); $righe = $st->fetchAll();
$conta = [];
foreach (db()->query('SELECT stato, COUNT(*) AS n FROM lettori GROUP BY stato') as $r) $conta[(int)$r['stato']] = (int)$r['n'];

admin_testata('Lettori', 'lettori');
?>
<p class="muto">Chi si è registrato per salvare segnalibri, preferiti e lasciare recensioni. Vedi solo i dati indispensabili; le password non sono leggibili.
  Registrazioni: <b><?= registrazione_aperta() ? 'aperte' : 'chiuse' ?></b> (<a href="registrazione.php">impostazioni</a>).</p>
<div class="schede">
  <a href="lettori.php" class="<?= $stato === 'tutti' ? 'att' : '' ?>">Tutti (<?= array_sum($conta) ?>)</a>
  <?php foreach ($STATI as $k => $et): ?><a href="lettori.php?stato=<?= $k ?>" class="<?= (string)$stato === (string)$k ? 'att' : '' ?>"><?= e(ucfirst($et)) ?> (<?= $conta[$k] ?? 0 ?>)</a><?php endforeach; ?>
</div>
<form method="get" class="filtri"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cerca email o nome…"><?php if ($stato !== 'tutti'): ?><input type="hidden" name="stato" value="<?= e($stato) ?>"><?php endif; ?><button class="bottone piccolo">Cerca</button></form>

<?php if (!$righe): ?><p class="muto">Nessun lettore<?= $q !== '' ? ' trovato' : ' per ora' ?>.</p><?php else: ?>
<table class="tabella">
<thead><tr><th>Nome</th><th>Email</th><th>Stato</th><th>Iscritto</th><th>Ultimo accesso</th><th>Liste</th><th>Recens.</th><th>Informativa</th><th>Azioni</th></tr></thead><tbody>
<?php foreach ($righe as $r): $id = (int)$r['id']; ?>
<tr>
  <td><b><?= e($r['nick']) ?></b></td><td><?= e($r['email']) ?></td>
  <td><span class="stato <?= ['bozza', 'pub', 'cest'][(int)$r['stato']] ?>"><?= e($STATI[(int)$r['stato']] ?? '?') ?></span></td>
  <td class="muto"><?= e(formatta_data($r['creato'])) ?></td><td class="muto"><?= e(formatta_data($r['ultimo_accesso'])) ?></td>
  <td><?= (int)$r['n_liste'] ?></td><td><?= (int)$r['n_rec'] ?></td>
  <td class="muto">v<?= e($r['privacy_versione']) ?><?= $r['privacy_versione'] !== privacy_versione() ? ' ⚠' : '' ?></td>
  <td><form method="post" class="azioni-riga"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="q" value="<?= e($q) ?>">
    <?php if ((int)$r['stato'] === 0): ?><button class="mini" name="azione" value="conferma" title="Conferma l'email a mano">✓ Conferma</button><?php endif; ?>
    <?php if ((int)$r['stato'] === 1): ?><button class="mini" name="azione" value="sospendi" data-conferma="Sospendere <?= e($r['nick']) ?>?">⏸ Sospendi</button><?php endif; ?>
    <?php if ((int)$r['stato'] === 2): ?><button class="mini" name="azione" value="riattiva">▶ Riattiva</button><?php endif; ?>
    <?php if ((int)$r['stato'] !== 2): ?><button class="mini" name="azione" value="reset" title="Manda il link per cambiare password">🔑 Reset</button><?php endif; ?>
    <button class="mini" name="azione" value="elimina" data-conferma="Eliminare <?= e($r['nick']) ?> e tutti i suoi dati? Non si può annullare.">🗑</button>
  </form></td>
</tr>
<?php endforeach; ?></tbody></table>
<?php endif; ?>
<form method="post" class="pannello" style="margin-top:18px"><?= csrf_campo() ?>
  <p class="muto">Gli account non confermati entro 7 giorni vengono cancellati da soli (a ogni registrazione). Puoi farlo subito:</p>
  <button class="bottone sec piccolo" name="azione" value="pulisci" data-conferma="Cancellare gli account mai confermati più vecchi di 7 giorni?">🧹 Pulisci i non confermati</button>
</form>
<?php admin_piede();
