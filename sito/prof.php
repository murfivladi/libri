<?php
// Area prof: accesso, registrazione con il codice segreto dell'admin, classi, compiti, risultati, errori comuni, progressi, soluzioni.
require __DIR__ . '/inc/pubblico.php';
require_once __DIR__ . '/inc/classi.php';

$a = (string)($_GET['a'] ?? '');
$p = prof_corrente();
$errore = '';
function vai(string $url): void { header('Location: ' . $url); exit; }
function campo_data(string $v): string { $t = strtotime($v); return $v !== '' && $t ? date('Y-m-d', $t) : ''; }

// ---------- azioni ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    if (!$p) {
        if ($a === 'registrati') {
            $nome = trim((string)($_POST['nome'] ?? '')); $email = email_pulita((string)($_POST['email'] ?? ''));
            $pw = (string)($_POST['password'] ?? '');
            if (codice_prof() === '') $errore = 'La registrazione dei prof è chiusa.';
            elseif (!limite_ok('prof-reg:' . ip_client(), 10, 3600)) $errore = 'Troppi tentativi: riprova più tardi.';
            elseif (!hash_equals(strtoupper(codice_prof()), strtoupper(trim((string)($_POST['codice'] ?? ''))))) $errore = 'Il codice per i prof non è giusto. Chiedilo a Vlad Editore.';
            elseif (mb_strlen($nome) < 2 || mb_strlen($nome) > 80) $errore = 'Scrivi il tuo nome (come vuoi farti chiamare).';
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errore = 'Email non valida.';
            elseif (strlen($pw) < 10) $errore = 'La password deve avere almeno 10 caratteri.';
            elseif ($pw !== (string)($_POST['password2'] ?? '')) $errore = 'Le due password non coincidono.';
            elseif (empty($_POST['adulto'])) $errore = 'Conferma di essere un insegnante maggiorenne e di aver letto la privacy.';
            else {
                $st = db()->prepare('SELECT 1 FROM prof WHERE email = ?'); $st->execute([$email]);
                if ($st->fetchColumn()) $errore = 'Esiste già un prof con questa email: accedi.';
                else {
                    db()->prepare('INSERT INTO prof (email, nome, password) VALUES (?,?,?)')->execute([$email, $nome, password_hash($pw, PASSWORD_DEFAULT)]);
                    $st = db()->prepare('SELECT * FROM prof WHERE email = ?'); $st->execute([$email]);
                    accedi_prof($st->fetch()); registra('Prof registrato', $email);
                    flash('Benvenuto! Crea la tua prima classe.'); vai('prof.php');
                }
            }
        } else {                                                   // accedi
            $email = email_pulita((string)($_POST['email'] ?? ''));
            if (!limite_ok('prof-login:' . ip_client(), 10, 900)) $errore = 'Troppi tentativi: aspetta 15 minuti.';
            else {
                $st = db()->prepare('SELECT * FROM prof WHERE email = ? AND stato = 1'); $st->execute([$email]); $r = $st->fetch();
                if ($r && password_verify((string)($_POST['password'] ?? ''), $r['password'])) { accedi_prof($r); vai('prof.php'); }
                $errore = 'Email o password sbagliate.';
            }
        }
    } else {
        $cid = (int)($_POST['classe'] ?? 0);
        $classe = $cid ? classe_del_prof($cid, (int)$p['id']) : null;
        if ($cid && !$classe) { http_response_code(403); exit('Classe non tua.'); }
        switch ($_POST['azione'] ?? '') {
            case 'esci': esci_prof(); flash('Sei uscito.'); vai('prof.php');
            case 'nuova_classe':
                $nome = mb_substr(trim((string)($_POST['nome'] ?? '')), 0, 60);
                if ($nome === '') { flash('Scrivi il nome della classe.', 'errore'); vai('prof.php'); }
                db()->prepare('INSERT INTO classi (prof_id, nome, codice, libro_id) VALUES (?,?,?,?)')->execute([$p['id'], $nome, nuovo_codice_classe(), (int)($_POST['libro_id'] ?? 0)]);
                flash('Classe creata: dai il codice ai tuoi studenti.'); vai('prof.php?a=classe&id=' . db()->lastInsertId());
            case 'modifica_classe':
                db()->prepare('UPDATE classi SET nome = ?, libro_id = ? WHERE id = ?')->execute([mb_substr(trim((string)$_POST['nome']), 0, 60) ?: $classe['nome'], (int)($_POST['libro_id'] ?? 0), $cid]);
                flash('Classe aggiornata.'); vai("prof.php?a=classe&id=$cid&vista=classe");
            case 'nuovo_codice':
                db()->prepare('UPDATE classi SET codice = ? WHERE id = ?')->execute([nuovo_codice_classe(), $cid]);
                flash('Nuovo codice creato: quello vecchio non funziona più.'); vai("prof.php?a=classe&id=$cid&vista=classe");
            case 'elimina_classe':
                elimina_classe($cid); flash('Classe eliminata con studenti e risultati.'); vai('prof.php');
            case 'nuovo_pin':
                $sid = (int)($_POST['studente'] ?? 0); $pin = (string)random_int(1000, 9999);
                $st = db()->prepare('UPDATE studenti SET pin = ?, token = \'\' WHERE id = ? AND classe_id = ?'); $st->execute([password_hash($pin, PASSWORD_DEFAULT), $sid, $cid]);
                if ($st->rowCount()) flash("Nuovo PIN: $pin (dallo allo studente; potrà usarlo subito)."); vai("prof.php?a=classe&id=$cid");
            case 'togli_studente':
                $sid = (int)($_POST['studente'] ?? 0);
                $st = db()->prepare('SELECT id FROM studenti WHERE id = ? AND classe_id = ?'); $st->execute([$sid, $cid]);
                if ($st->fetchColumn()) { elimina_studente($sid); flash('Studente tolto dalla classe (con i suoi risultati).'); }
                vai("prof.php?a=classe&id=$cid");
            case 'nuovo_compito':
                $ids = array_values(array_unique(array_map('intval', (array)($_POST['esercizi'] ?? []))));
                $ids = array_column(esercizi_per_id($ids), 'id');
                $titolo = mb_substr(trim((string)($_POST['titolo'] ?? '')), 0, 120);
                if (!$ids || $titolo === '') { flash('Scrivi un titolo e scegli almeno un esercizio.', 'errore'); vai("prof.php?a=classe&id=$cid&vista=compiti"); }
                db()->prepare('INSERT INTO compiti (classe_id, titolo, esercizi, scadenza) VALUES (?,?,?,?)')->execute([$cid, $titolo, implode(',', $ids), campo_data((string)($_POST['scadenza'] ?? ''))]);
                flash('Compito assegnato.'); vai("prof.php?a=classe&id=$cid&vista=compiti");
            case 'elimina_compito':
                db()->prepare('DELETE FROM compiti WHERE id = ? AND classe_id = ?')->execute([(int)($_POST['compito'] ?? 0), $cid]);
                flash('Compito eliminato (i risultati restano).'); vai("prof.php?a=classe&id=$cid&vista=compiti");
            case 'password':
                if (!password_verify((string)($_POST['attuale'] ?? ''), $p['password'])) flash('La password attuale è sbagliata.', 'errore');
                elseif (strlen((string)$_POST['nuova']) < 10 || $_POST['nuova'] !== ($_POST['nuova2'] ?? '')) flash('La nuova password deve avere almeno 10 caratteri e coincidere nelle due caselle.', 'errore');
                else { db()->prepare('UPDATE prof SET password = ? WHERE id = ?')->execute([password_hash((string)$_POST['nuova'], PASSWORD_DEFAULT), $p['id']]); flash('Password cambiata.'); }
                vai('prof.php?a=account');
            case 'elimina_account':
                if (!password_verify((string)($_POST['attuale'] ?? ''), $p['password'])) { flash('Password sbagliata.', 'errore'); vai('prof.php?a=account'); }
                elimina_prof((int)$p['id']); esci_prof(); registra('Prof eliminato', $p['email']); flash('Account eliminato.'); vai('prof.php');
        }
        vai('prof.php');
    }
}

intestazione('Area prof', '', '', ['css' => ['assets/scuola.css'], 'noindex' => true]);
foreach ($_SESSION['flash'] ?? [] as [$tipo, $msg]) echo '<p class="' . ($tipo === 'ok' ? 'ok-pub' : 'errore') . '">' . e($msg) . '</p>';
unset($_SESSION['flash']);

// ---------- non entrato: accedi / registrati ----------
if (!$p):
    $reg = $a === 'registrati'; ?>
<div class="pannello-scuola stretto">
  <h1>🍎 Area prof</h1>
  <p class="schede-mini"><a href="prof.php"<?= $reg ? '' : ' class="att"' ?>>Accedi</a><?php if (codice_prof() !== ''): ?><a href="prof.php?a=registrati"<?= $reg ? ' class="att"' : '' ?>>Registrati</a><?php endif; ?></p>
  <?php if ($errore): ?><p class="errore"><?= e($errore) ?></p><?php endif; ?>
  <form method="post" action="prof.php?a=<?= $reg ? 'registrati' : 'accedi' ?>" class="form-scuola"><?= csrf_campo() ?>
    <?php if ($reg): ?>
      <p class="meta">Per registrarti serve il <b>codice per i prof</b> che ti ha dato Vlad Editore.</p>
      <label>Codice per i prof <input type="text" name="codice" required class="mono" autocomplete="off" value="<?= e($_POST['codice'] ?? '') ?>"></label>
      <label>Il tuo nome <small>(es. Prof. Rossi)</small><input type="text" name="nome" required maxlength="80" value="<?= e($_POST['nome'] ?? '') ?>"></label>
    <?php endif; ?>
    <label>Email <input type="email" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Password <input type="password" name="password" required minlength="<?= $reg ? 10 : 1 ?>" autocomplete="<?= $reg ? 'new-password' : 'current-password' ?>"></label>
    <?php if ($reg): ?>
      <label>Ripeti la password <input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
      <label class="spunta-pub"><input type="checkbox" name="adulto" value="1" required> Sono un insegnante maggiorenne e ho letto la <a href="privacy.php" target="_blank">privacy</a>.</label>
    <?php endif; ?>
    <button class="bottone arancio"><?= $reg ? 'Crea il mio account' : 'Entra' ?></button>
  </form>
</div>
<?php piede(); exit; endif;

$libri = db()->query("SELECT id, slug, titolo FROM libri WHERE sezione = 'scuola' AND " . sql_visibile() . ' ORDER BY ordine, numero, id')->fetchAll();
$titolo_libro = array_column($libri, 'titolo', 'id');
$menu = function (string $att) use ($p) { ?>
  <div class="testata-studente"><div><h1>🍎 <?= e($p['nome']) ?></h1><p class="meta">Area prof · <?= e($p['email']) ?></p></div>
    <p class="schede-mini"><a href="prof.php"<?= $att === 'home' ? ' class="att"' : '' ?>>Le mie classi</a><a href="prof.php?a=soluzioni"<?= $att === 'soluzioni' ? ' class="att"' : '' ?>>Soluzioni</a><a href="prof.php?a=account"<?= $att === 'account' ? ' class="att"' : '' ?>>Account</a></p></div>
<?php };

// ---------- soluzioni ----------
if ($a === 'soluzioni'):
    $menu('soluzioni');
    $lid = (int)($_GET['libro'] ?? ($libri[0]['id'] ?? 0)); ?>
  <form method="get" class="riga-form"><input type="hidden" name="a" value="soluzioni"><label>Libro <select name="libro" onchange="this.form.submit()"><?php foreach ($libri as $l): ?><option value="<?= (int)$l['id'] ?>" <?= $lid === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['titolo']) ?></option><?php endforeach; ?></select></label></form>
  <?php $st = db()->prepare('SELECT * FROM esercizi WHERE libro_id = ? ORDER BY pagina, ordine, id'); $st->execute([$lid]);
  foreach ($st as $e): ?>
    <div class="soluzione"><h3><?= TIPI_ESERCIZIO[$e['tipo']][0] ?? '' ?> <?= e($e['titolo'] !== '' ? $e['titolo'] : $e['codice']) ?> <small class="meta"><?= e($e['codice']) ?><?= $e['pagina'] ? ' · pag. ' . (int)$e['pagina'] : '' ?><?= $e['extra'] ? ' · solo online' : '' ?></small></h3>
      <?php if ($e['consegna'] !== ''): ?><p class="meta"><?= e($e['consegna']) ?></p><?php endif; ?>
      <ol><?php foreach (soluzione_righe($e) as $riga): ?><li><?= e($riga) ?></li><?php endforeach; ?></ol></div>
  <?php endforeach; ?>
<?php piede(); exit; endif;

// ---------- account ----------
if ($a === 'account'): $menu('account'); ?>
  <div class="pannello-scuola stretto"><h2>Cambia la password</h2>
  <form method="post" class="form-scuola"><?= csrf_campo() ?><input type="hidden" name="azione" value="password">
    <label>Password attuale <input type="password" name="attuale" required autocomplete="current-password"></label>
    <label>Nuova password <input type="password" name="nuova" required minlength="10" autocomplete="new-password"></label>
    <label>Ripeti la nuova password <input type="password" name="nuova2" required minlength="10" autocomplete="new-password"></label>
    <button class="bottone arancio">Cambia</button></form></div>
  <div class="pannello-scuola stretto"><h2>Elimina l'account</h2><p class="meta">Si cancellano anche tutte le tue classi, gli studenti e i loro risultati.</p>
  <form method="post" class="form-scuola" onsubmit="return confirm('Eliminare per sempre il tuo account e tutte le classi?')"><?= csrf_campo() ?><input type="hidden" name="azione" value="elimina_account">
    <label>Password <input type="password" name="attuale" required autocomplete="current-password"></label><button class="bottone secondario">Elimina tutto</button></form></div>
  <form method="post"><?= csrf_campo() ?><input type="hidden" name="azione" value="esci"><button class="bottone secondario">Esci</button></form>
<?php piede(); exit; endif;

// ---------- una classe ----------
if ($a === 'classe' && ($classe = classe_del_prof((int)($_GET['id'] ?? 0), (int)$p['id']))):
    $menu('home');
    $cid = (int)$classe['id'];
    $vista = (string)($_GET['vista'] ?? 'studenti');
    $st = db()->prepare(sql_nocase('SELECT * FROM studenti WHERE classe_id = ? ORDER BY nick')); $st->execute([$cid]); $studenti = $st->fetchAll();
    $best = migliori_risultati($studenti);
    $st = db()->prepare('SELECT * FROM compiti WHERE classe_id = ? ORDER BY id DESC'); $st->execute([$cid]); $compiti = $st->fetchAll();
    $url_entra = base_ufficiale() . 'studente.php?classe=' . rawurlencode($classe['codice']);
    $tab = fn($k, $t) => '<a href="prof.php?a=classe&amp;id=' . $cid . '&amp;vista=' . $k . '"' . ($vista === $k ? ' class="att"' : '') . '>' . $t . '</a>'; ?>
  <div class="scheda-classe">
    <div><h2><?= e($classe['nome']) ?></h2><p class="meta"><?= e($titolo_libro[$classe['libro_id']] ?? 'nessun libro') ?> · <?= count($studenti) ?> studenti</p></div>
    <div class="codice-classe"><small>Codice per gli studenti</small><b><?= e($classe['codice']) ?></b><small>da inserire in <a href="studente.php" target="_blank">Entra in classe</a></small></div>
    <div id="qr-classe" data-url="<?= e($url_entra) ?>"></div>
  </div>
  <nav class="schede-mini"><?= $tab('studenti', '👥 Studenti') . $tab('compiti', '📝 Compiti') . $tab('risultati', '📊 Risultati') . $tab('errori', '⚠️ Errori comuni') . $tab('progressi', '📈 Progressi') . $tab('classe', '⚙️ Classe') ?></nav>

  <?php if ($vista === 'studenti'): ?>
    <?php if (!$studenti): ?><p class="meta">Ancora nessuno studente. Dai ai ragazzi il codice <b><?= e($classe['codice']) ?></b> (o fai inquadrare il QR): scelgono un nickname e un PIN.</p><?php else: ?>
    <table class="tab-scuola"><tr><th>Nickname</th><th>Esercizi fatti</th><th>Media</th><th>Ultimo accesso</th><th></th></tr>
    <?php foreach ($studenti as $s): $m = $best[(int)$s['id']] ?? []; $con = array_filter($m, fn($x) => $x[1] > 0);
      $media = $con ? round(array_sum(array_map(fn($x) => $x[0] / $x[1], $con)) / count($con) * 100) : null; ?>
      <tr><td><b><?= e($s['nick']) ?></b></td><td><?= count($m) ?></td><td><?= $media === null ? '—' : '<span class="perc" style="--p:' . $media . '%">' . $media . '%</span>' ?></td><td class="meta"><?= e(formatta_data($s['ultimo_accesso'] ?: null)) ?></td>
        <td class="azioni"><form method="post"><?= csrf_campo() ?><input type="hidden" name="classe" value="<?= $cid ?>"><input type="hidden" name="studente" value="<?= (int)$s['id'] ?>">
          <button class="mini-pub" name="azione" value="nuovo_pin">Nuovo PIN</button>
          <button class="mini-pub" name="azione" value="togli_studente" onclick="return confirm('Togliere <?= e($s['nick']) ?> e i suoi risultati?')">Togli</button></form></td></tr>
    <?php endforeach; ?></table><?php endif; ?>

  <?php elseif ($vista === 'compiti'):
    $where = $classe['libro_id'] ? 'WHERE libro_id = ' . (int)$classe['libro_id'] : "WHERE libro_id IN (SELECT id FROM libri WHERE sezione = 'scuola' AND " . sql_visibile() . ')';
    $tutti = db()->query("SELECT * FROM esercizi $where ORDER BY libro_id, pagina, ordine, id")->fetchAll(); ?>
    <details class="pannello-scuola" <?= $compiti ? '' : 'open' ?>><summary><b>+ Assegna un compito</b></summary>
    <form method="post" class="form-scuola"><?= csrf_campo() ?><input type="hidden" name="classe" value="<?= $cid ?>"><input type="hidden" name="azione" value="nuovo_compito">
      <label>Titolo <input type="text" name="titolo" required maxlength="120" placeholder="es. Il soggetto – per lunedì"></label>
      <label>Scadenza <small>(facoltativa)</small><input type="date" name="scadenza"></label>
      <fieldset class="scelta-esercizi"><legend>Esercizi</legend>
      <?php foreach ($tutti as $e): ?><label class="spunta-pub"><input type="checkbox" name="esercizi[]" value="<?= (int)$e['id'] ?>"> <?= TIPI_ESERCIZIO[$e['tipo']][0] ?? '' ?> <?= e($e['titolo'] !== '' ? $e['titolo'] : $e['codice']) ?> <small class="meta"><?= e($e['codice']) ?><?= $e['pagina'] ? ' · pag. ' . (int)$e['pagina'] : '' ?> · <?= str_repeat('●', (int)$e['livello']) ?></small></label><?php endforeach; ?>
      <?php if (!$tutti): ?><p class="meta">Non ci sono ancora esercizi per il libro di questa classe.</p><?php endif; ?>
      </fieldset>
      <button class="bottone arancio">Assegna</button></form></details>
    <?php foreach ($compiti as $c): $ex = esercizi_per_id(ids_esercizi($c['esercizi']));
      $finiti = count(array_filter($studenti, fn($s) => $ex && count(array_intersect(array_map(fn($e) => (int)$e['id'], $ex), array_keys($best[(int)$s['id']] ?? []))) === count($ex))); ?>
      <div class="compito"><div class="testa-compito"><b><?= e($c['titolo']) ?></b><span class="meta"><?= $c['scadenza'] !== '' ? 'entro il ' . e(date('d/m/Y', strtotime($c['scadenza']))) : 'senza scadenza' ?> · finito da <?= $finiti ?>/<?= count($studenti) ?></span></div>
        <table class="tab-scuola"><tr><th>Studente</th><?php foreach ($ex as $e): ?><th title="<?= e($e['titolo']) ?>"><?= e($e['codice']) ?></th><?php endforeach; ?></tr>
        <?php foreach ($studenti as $s): ?><tr><td><?= e($s['nick']) ?></td><?php foreach ($ex as $e): $m = $best[(int)$s['id']][(int)$e['id']] ?? null; ?><td><?= $m ? ($m[1] ? $m[0] . '/' . $m[1] : '✓') : '<span class="meta">—</span>' ?></td><?php endforeach; ?></tr><?php endforeach; ?></table>
        <form method="post" onsubmit="return confirm('Eliminare il compito?')"><?= csrf_campo() ?><input type="hidden" name="classe" value="<?= $cid ?>"><input type="hidden" name="compito" value="<?= (int)$c['id'] ?>"><button class="mini-pub" name="azione" value="elimina_compito">Elimina il compito</button></form></div>
    <?php endforeach; ?>

  <?php elseif ($vista === 'risultati'):
    $ids = []; foreach ($best as $m) $ids = array_merge($ids, array_keys($m));
    $ex = esercizi_per_id(array_values(array_unique($ids))); usort($ex, fn($a, $b) => [$a['libro_id'], $a['pagina'], $a['id']] <=> [$b['libro_id'], $b['pagina'], $b['id']]); ?>
    <?php if (!$ex): ?><p class="meta">Ancora nessun risultato.</p><?php else: ?>
    <div class="scorri"><table class="tab-scuola matrice"><tr><th>Studente</th><?php foreach ($ex as $e): ?><th title="<?= e($e['titolo']) ?>"><?= e($e['codice']) ?></th><?php endforeach; ?></tr>
    <?php foreach ($studenti as $s): ?><tr><td><b><?= e($s['nick']) ?></b></td><?php foreach ($ex as $e): $m = $best[(int)$s['id']][(int)$e['id']] ?? null;
      $perc = $m && $m[1] ? round($m[0] / $m[1] * 100) : null; ?><td class="<?= $perc === null ? '' : ($perc >= 80 ? 'alto' : ($perc >= 50 ? 'medio' : 'basso')) ?>" title="<?= $m ? $m[2] . ' tentativi' : '' ?>"><?= $m ? ($m[1] ? $m[0] . '/' . $m[1] : '✓') : '' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    </table></div><p class="meta">Miglior risultato di ogni studente (passa sopra una casella per vedere i tentativi).</p><?php endif; ?>

  <?php elseif ($vista === 'errori'):
    $ids = []; foreach ($best as $m) $ids = array_merge($ids, array_keys($m));
    $ex = esercizi_per_id(array_values(array_unique($ids))); $niente = true;
    foreach ($ex as $e): $ec = errori_comuni($e, $studenti); $voci = array_filter($ec['voci'] ?? [], fn($v) => $v['sbagli'] > 0); if (!$voci) continue; $niente = false; ?>
      <div class="compito"><div class="testa-compito"><b><?= e($e['titolo'] !== '' ? $e['titolo'] : $e['codice']) ?></b><span class="meta"><?= e($e['codice']) ?> · <?= (int)$ec['studenti'] ?> studenti</span></div>
      <?php foreach (array_slice($voci, 0, 6) as $v): $pc = round($v['sbagli'] / $v['tot'] * 100); ?>
        <div class="errore-comune"><span class="barra-perc"><i style="width:<?= $pc ?>%"></i></span><b><?= $pc ?>%</b> <span><?= e($v['testo']) ?></span> <small class="meta">(<?= $v['sbagli'] ?>/<?= $v['tot'] ?>)</small></div>
      <?php endforeach; ?></div>
    <?php endforeach; if ($niente): ?><p class="meta">Nessun errore da segnalare (o ancora nessun risultato).</p><?php endif; ?>

  <?php elseif ($vista === 'progressi'): $pr = progressi_classe($studenti); ?>
    <div class="grafico" role="img" aria-label="Media della classe settimana per settimana">
    <?php foreach ($pr as $s): ?><div class="colonna" title="<?= $s['n'] ?> esercizi"><span class="valore"><?= $s['media'] === null ? '' : $s['media'] . '%' ?></span><i style="height:<?= (int)($s['media'] ?? 0) ?>%"></i><small><?= e(date('d/m', strtotime($s['dal']))) ?></small></div><?php endforeach; ?>
    </div><p class="meta">Media dei risultati della classe, settimana per settimana (ultime 10 settimane).</p>
    <table class="tab-scuola"><tr><th>Studente</th><th>Tentativi</th><th>Prime 5 prove</th><th>Ultime 5 prove</th><th>Andamento</th></tr>
    <?php foreach ($studenti as $s):
      $st = db()->prepare('SELECT fatti, totale FROM risultati WHERE studente_id = ? AND totale > 0 ORDER BY id'); $st->execute([$s['id']]);
      $v = array_map(fn($r) => $r['fatti'] / $r['totale'] * 100, $st->fetchAll());
      $prime = $v ? round(array_sum(array_slice($v, 0, 5)) / count(array_slice($v, 0, 5))) : null; $ult = $v ? round(array_sum(array_slice($v, -5)) / count(array_slice($v, -5))) : null; ?>
      <tr><td><b><?= e($s['nick']) ?></b></td><td><?= count($v) ?></td><td><?= $prime === null ? '—' : $prime . '%' ?></td><td><?= $ult === null ? '—' : $ult . '%' ?></td>
        <td><?= count($v) < 6 ? '<span class="meta">pochi dati</span>' : ($ult - $prime >= 5 ? '📈 migliora' : ($prime - $ult >= 5 ? '📉 in calo' : '➡️ stabile')) ?></td></tr>
    <?php endforeach; ?></table>

  <?php else: ?>
    <div class="pannello-scuola stretto"><h3>Modifica la classe</h3>
    <form method="post" class="form-scuola"><?= csrf_campo() ?><input type="hidden" name="classe" value="<?= $cid ?>"><input type="hidden" name="azione" value="modifica_classe">
      <label>Nome <input type="text" name="nome" value="<?= e($classe['nome']) ?>" maxlength="60" required></label>
      <label>Libro <select name="libro_id"><option value="0">— nessuno —</option><?php foreach ($libri as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$classe['libro_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['titolo']) ?></option><?php endforeach; ?></select></label>
      <button class="bottone arancio">Salva</button></form></div>
    <form method="post" class="azioni"><?= csrf_campo() ?><input type="hidden" name="classe" value="<?= $cid ?>">
      <button class="bottone secondario" name="azione" value="nuovo_codice" onclick="return confirm('Il codice attuale smetterà di funzionare per i nuovi ingressi. Continuare?')">🔄 Nuovo codice</button>
      <button class="bottone secondario" name="azione" value="elimina_classe" onclick="return confirm('Eliminare la classe con tutti gli studenti e i risultati?')">🗑 Elimina la classe</button></form>
  <?php endif; ?>
  <script src="assets/vendor/qrcode.min.js"></script>
  <script>(function () { var q = document.getElementById('qr-classe'); if (q && window.QRCode) new QRCode(q, { text: q.dataset.url, width: 120, height: 120 }); })();</script>
<?php piede(); exit; endif;

// ---------- le mie classi ----------
$menu('home');
$st = db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM studenti s WHERE s.classe_id = c.id) AS n FROM classi c WHERE c.prof_id = ? ORDER BY c.id DESC'); $st->execute([$p['id']]);
$classi = $st->fetchAll(); ?>
<div class="griglia-classi">
<?php foreach ($classi as $c): ?>
  <a class="carta-classe" href="prof.php?a=classe&amp;id=<?= (int)$c['id'] ?>"><b><?= e($c['nome']) ?></b><span class="mono"><?= e($c['codice']) ?></span><small class="meta"><?= (int)$c['n'] ?> studenti · <?= e($titolo_libro[$c['libro_id']] ?? 'nessun libro') ?></small></a>
<?php endforeach; ?>
</div>
<div class="pannello-scuola stretto"><h2>+ Nuova classe</h2>
<form method="post" class="form-scuola"><?= csrf_campo() ?><input type="hidden" name="azione" value="nuova_classe">
  <label>Nome della classe <input type="text" name="nome" required maxlength="60" placeholder="es. 2ª E Trappitello"></label>
  <label>Libro <select name="libro_id"><option value="0">— nessuno —</option><?php foreach ($libri as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['titolo']) ?></option><?php endforeach; ?></select></label>
  <button class="bottone arancio">Crea la classe</button></form></div>
<?php piede();
