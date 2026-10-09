<?php
// Studenti: si entra in una classe con il codice dato dal prof, un nickname (non il nome vero) e un PIN.
// Nessuna email e nessun dato personale. Si può cancellare il proprio profilo in ogni momento.
require __DIR__ . '/inc/pubblico.php';
require_once __DIR__ . '/inc/classi.php';

$a = (string)($_GET['a'] ?? '');
$errore = ''; $nuovo = false;
$val = ['codice' => (string)($_GET['classe'] ?? ''), 'nick' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    if ($a === 'esci') { esci_studente(); flash('Sei uscito dalla classe.'); header('Location: scuola.php'); exit; }
    if ($a === 'cancella' && ($s = studente_corrente())) {
        esci_studente(); elimina_studente((int)$s['id']);
        flash('Il tuo profilo e i tuoi risultati sono stati cancellati.'); header('Location: scuola.php'); exit;
    }
    if ($a === 'entra') {
        $val = ['codice' => trim((string)($_POST['codice'] ?? '')), 'nick' => trim(preg_replace('/\s+/u', ' ', (string)($_POST['nick'] ?? '')))];
        $pin = (string)($_POST['pin'] ?? ''); $pin2 = (string)($_POST['pin2'] ?? '');
        $classe = classe_per_codice($val['codice']);
        if (!limite_ok('studente:' . ip_client(), 30, 900)) $errore = 'Troppi tentativi: aspetta qualche minuto.';
        elseif (!$classe) $errore = 'Il codice della classe non esiste: chiedilo al tuo prof (es. ABC-123).';
        elseif ($e = nick_studente_errore($val['nick'])) $errore = $e;
        elseif ($e = pin_errore($pin)) $errore = $e;
        else {
            $s = studente_per_nick((int)$classe['id'], $val['nick']);
            if ($s) {
                if (!limite_ok('studente-nick:' . $classe['id'] . ':' . mb_strtolower($val['nick']), 8, 900)) $errore = 'Troppi PIN sbagliati: aspetta 15 minuti o chiedi al prof di cambiarlo.';
                elseif (!password_verify($pin, $s['pin'])) $errore = 'PIN sbagliato. Se l\'hai dimenticato, il prof può dartene uno nuovo.';
                else { accedi_studente($s, !empty($_POST['ricorda'])); header('Location: studente.php'); exit; }
            } elseif ($pin2 === '') {
                $nuovo = true;                          // nickname nuovo in questa classe: si chiede di ripetere il PIN
            } elseif ($pin2 !== $pin) {
                $nuovo = true; $errore = 'I due PIN non sono uguali.';
            } else {
                db()->prepare('INSERT INTO studenti (classe_id, nick, pin, ultimo_accesso) VALUES (?,?,?,?)')
                    ->execute([$classe['id'], $val['nick'], password_hash($pin, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
                $s = studente_per_nick((int)$classe['id'], $val['nick']);
                accedi_studente($s, !empty($_POST['ricorda']));
                flash('Benvenuto nella classe! Ricorda il tuo nickname e il PIN.');
                header('Location: studente.php'); exit;
            }
        }
    }
}

$s = studente_corrente();
intestazione($s ? 'La mia classe' : 'Entra nella tua classe', '', '', ['css' => ['assets/scuola.css'], 'noindex' => true]);
foreach ($_SESSION['flash'] ?? [] as [$tipo, $msg]) echo '<p class="' . ($tipo === 'ok' ? 'ok-pub' : 'errore') . '">' . e($msg) . '</p>';
unset($_SESSION['flash']);

if (!$s): ?>
<div class="pannello-scuola stretto">
  <h1>🎒 Entra nella tua classe</h1>
  <p>Ti servono il <b>codice della classe</b> (te lo dà il prof), un <b>nickname</b> e un <b>PIN</b> di 4–6 cifre da ricordare.</p>
  <?php if ($errore): ?><p class="errore"><?= e($errore) ?></p><?php endif; ?>
  <form method="post" action="studente.php?a=entra" class="form-scuola">
    <?= csrf_campo() ?>
    <label>Codice della classe <input type="text" name="codice" value="<?= e($val['codice']) ?>" required maxlength="10" placeholder="es. ABC-123" autocomplete="off" autocapitalize="characters" class="mono"></label>
    <label>Nickname <small>(non usare il tuo nome e cognome veri)</small><input type="text" name="nick" value="<?= e($val['nick']) ?>" required maxlength="20" autocomplete="username" placeholder="es. Drago42"></label>
    <label>PIN <input type="password" name="pin" inputmode="numeric" pattern="\d{4,6}" required maxlength="6" autocomplete="current-password"></label>
    <?php if ($nuovo): ?>
      <p class="avviso">Il nickname <b><?= e($val['nick']) ?></b> è nuovo in questa classe: ripeti il PIN per creare il tuo profilo.</p>
      <label>Ripeti il PIN <input type="password" name="pin2" inputmode="numeric" pattern="\d{4,6}" required maxlength="6" autocomplete="new-password" autofocus></label>
    <?php endif; ?>
    <label class="spunta-pub"><input type="checkbox" name="ricorda" value="1" checked> Ricordami su questo dispositivo</label>
    <button class="bottone arancio"><?= $nuovo ? 'Crea il mio profilo' : 'Entra' ?></button>
  </form>
  <p class="meta">Non salviamo email né nomi: solo il nickname, il PIN (cifrato) e i risultati degli esercizi, che vede il tuo prof.</p>
</div>
<?php piede(); exit; endif;

// ---------- home dello studente ----------
$compiti = db()->prepare('SELECT * FROM compiti WHERE classe_id = ? ORDER BY (scadenza = \'\'), scadenza, id DESC'); $compiti->execute([$s['classe_id']]); $compiti = $compiti->fetchAll();
$migliori = migliori_risultati([$s])[(int)$s['id']] ?? [];
$libro = null;
if ($s['libro_id']) { $st = db()->prepare('SELECT slug, titolo FROM libri WHERE id = ? AND ' . sql_visibile()); $st->execute([$s['libro_id']]); $libro = $st->fetch() ?: null; }
$oggi = date('Y-m-d');
?>
<div class="testata-studente">
  <div><h1>Ciao, <?= e($s['nick']) ?>! 👋</h1><p class="meta">Classe <b><?= e($s['classe']) ?></b> · codice <?= e($s['codice_classe']) ?></p></div>
  <?php if ($libro): ?><a class="bottone arancio" href="leggi.php?s=<?= e($libro['slug']) ?>">📖 Apri «<?= e($libro['titolo']) ?>»</a><?php endif; ?>
</div>

<h2 class="sezione">📝 Compiti</h2>
<?php if (!$compiti): ?><p class="meta">Per ora non ci sono compiti. Puoi fare gli esercizi del libro quando vuoi.</p><?php endif; ?>
<?php foreach ($compiti as $c): $ex = esercizi_per_id(ids_esercizi($c['esercizi'])); $fatti = count(array_filter($ex, fn($e) => isset($migliori[(int)$e['id']])));
  $scaduto = $c['scadenza'] !== '' && $c['scadenza'] < $oggi; ?>
<div class="compito<?= $fatti === count($ex) && $ex ? ' finito' : '' ?><?= $scaduto ? ' scaduto' : '' ?>">
  <div class="testa-compito"><b><?= e($c['titolo']) ?></b>
    <span class="meta"><?= $c['scadenza'] !== '' ? ($scaduto ? 'scaduto il ' : 'entro il ') . e(date('d/m/Y', strtotime($c['scadenza']))) : 'senza scadenza' ?> · <?= $fatti ?>/<?= count($ex) ?> fatti</span></div>
  <ul class="elenco-contenuti">
  <?php foreach ($ex as $e): $m = $migliori[(int)$e['id']] ?? null; ?>
    <li><a href="apri.php?c=<?= e($e['codice']) ?>"><span class="ic"><?= $m ? '✅' : (TIPI_ESERCIZIO[$e['tipo']][0] ?? '✏️') ?></span><span><?= e($e['titolo'] !== '' ? $e['titolo'] : $e['codice']) ?>
      <small><?= $m ? ($m[1] ? 'Il tuo meglio: ' . $m[0] . ' su ' . $m[1] : 'Fatto') : 'Da fare' ?> · <code><?= e($e['codice']) ?></code></small></span></a></li>
  <?php endforeach; ?></ul>
</div>
<?php endforeach; ?>

<h2 class="sezione">⭐ I miei risultati</h2>
<?php
$st = db()->prepare('SELECT r.fatti, r.totale, r.quando, e.codice, e.titolo FROM risultati r JOIN esercizi e ON e.id = r.esercizio_id WHERE r.studente_id = ? ORDER BY r.id DESC LIMIT 30');
$st->execute([$s['id']]); $ris = $st->fetchAll();
if (!$ris): ?><p class="meta">Ancora nessun esercizio fatto.</p><?php else: ?>
<table class="tab-scuola"><tr><th>Esercizio</th><th>Risultato</th><th>Quando</th></tr>
<?php foreach ($ris as $r): ?><tr><td><a href="apri.php?c=<?= e($r['codice']) ?>"><?= e($r['titolo'] !== '' ? $r['titolo'] : $r['codice']) ?></a></td>
  <td><?= $r['totale'] ? '<b>' . (int)$r['fatti'] . '</b> su ' . (int)$r['totale'] : 'risposta libera' ?></td><td class="meta"><?= e(date('d/m H:i', strtotime($r['quando']))) ?></td></tr><?php endforeach; ?>
</table><?php endif; ?>

<div class="azioni" style="margin-top:24px">
  <form method="post" action="studente.php?a=esci"><?= csrf_campo() ?><button class="bottone secondario">Esci</button></form>
  <form method="post" action="studente.php?a=cancella" onsubmit="return confirm('Cancellare per sempre il tuo profilo e tutti i tuoi risultati?')"><?= csrf_campo() ?><button class="bottone secondario">Cancella il mio profilo</button></form>
</div>
<?php piede();
