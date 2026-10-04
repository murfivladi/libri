<?php
require __DIR__ . '/inc/bootstrap.php';
$v = ['nome' => '', 'email' => '', 'telefono' => '', 'per_chi' => '', 'eta' => '', 'occasione' => '', 'genere' => '', 'lingua' => 'Italiano', 'formato' => 'PDF',
      'pagine' => '', 'storia' => '', 'dedica' => '', 'scadenza' => '', 'indirizzo' => '', 'cap' => '', 'citta' => '', 'paese' => 'Italia', 'note' => ''];
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? $v[$k]));
    $n = db()->prepare('SELECT COUNT(*) FROM richieste WHERE ip = ? AND creato > ?'); $n->execute([ip_client(), date('Y-m-d H:i:s', time() - 3600)]);
    $stampa = in_array($v['formato'], ['Stampato', 'PDF + stampato'], true);
    if (($_POST['sito'] ?? '') !== '') vai('grazie.php');                          // trappola per i bot
    elseif ((int)$n->fetchColumn() >= 5) $err = 'Troppe richieste da questo indirizzo: riprova più tardi.';
    elseif (mb_strlen($v['nome']) < 2) $err = 'Scrivi il tuo nome.';
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err = 'Email non valida.';
    elseif (mb_strlen($v['per_chi']) < 2) $err = 'Scrivi per chi è il libro (protagonista).';
    elseif (mb_strlen($v['storia']) < 20) $err = 'Raccontaci almeno in poche righe la storia che desideri.';
    elseif (!in_array($v['formato'], ['PDF', 'Stampato', 'PDF + stampato'], true)) $err = 'Formato non valido.';
    elseif ($stampa && (mb_strlen($v['indirizzo']) < 4 || $v['cap'] === '' || $v['citta'] === '')) $err = "Per la copia stampata serve l'indirizzo di consegna.";
    else {
        $ora = date('Y-m-d H:i:s');
        $lim = ['nome' => 120, 'email' => 160, 'telefono' => 40, 'per_chi' => 120, 'eta' => 20, 'occasione' => 120, 'genere' => 120, 'lingua' => 30, 'formato' => 20,
                'pagine' => 30, 'storia' => 5000, 'dedica' => 500, 'scadenza' => 60, 'indirizzo' => 200, 'cap' => 12, 'citta' => 80, 'paese' => 60, 'note' => 1500];
        $d = []; foreach ($lim as $k => $m) $d[$k] = mb_substr($v[$k], 0, $m);
        $campi = implode(',', array_keys($d)) . ',creato,aggiornato,ip';
        db()->prepare("INSERT INTO richieste ($campi) VALUES (" . implode(',', array_fill(0, count($d) + 3, '?')) . ')')->execute([...array_values($d), $ora, $ora, ip_client()]);
        $id = (int)db()->lastInsertId();
        invia_notifica(['id' => $id] + $d);
        $_SESSION['ultimo_ordine'] = $id;
        vai('grazie.php');
    }
}
intestazione('Un libro scritto per te', 'Vlad Editore scrive libri personalizzati su ordinazione: racconta la tua idea e ricevi una storia solo tua.');
?>
<section class="hero">
  <div class="hero-testo"><h1>Un libro scritto apposta per te</h1><p>Racconta chi sarà il protagonista e che storia sogni: Vlad la scrive, la illustra e te la consegna.</p></div>
</section>
<h2 class="sezione" id="come">Come funziona</h2>
<ol class="passi">
  <li><b>Racconti l'idea.</b> Compili il modulo: per chi è il libro, età, genere, la storia che immagini.</li>
  <li><b>Ci mettiamo d'accordo.</b> Ti scrivo via email per i dettagli, i tempi e le condizioni. Nessun pagamento online.</li>
  <li><b>Scrivo il tuo libro.</b> Una storia unica, in italiano o in russo, in PDF e/o stampata.</li>
</ol>
<h2 class="sezione" id="richiesta">Richiedi il tuo libro</h2>
<div class="ordina-box">
<?php if ($err): ?><p class="errore"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="form-pub" action="#richiesta">
  <?= csrf_campo() ?>
  <div class="trappola"><label>Sito <input type="text" name="sito" tabindex="-1" autocomplete="off"></label></div>
  <h3>Chi sei</h3>
  <label>Nome e cognome <input type="text" name="nome" required maxlength="120" value="<?= e($v['nome']) ?>" autocomplete="name"></label>
  <label>Email <input type="email" name="email" required maxlength="160" value="<?= e($v['email']) ?>" autocomplete="email"></label>
  <label>Telefono <small>(facoltativo)</small><input type="text" name="telefono" maxlength="40" value="<?= e($v['telefono']) ?>" autocomplete="tel"></label>
  <h3>Il libro</h3>
  <div class="due">
    <label>Per chi è / protagonista <input type="text" name="per_chi" required maxlength="120" value="<?= e($v['per_chi']) ?>" placeholder="es. Sofia"></label>
    <label>Età <input type="text" name="eta" maxlength="20" value="<?= e($v['eta']) ?>" placeholder="es. 8 anni"></label>
  </div>
  <label>Occasione <small>(compleanno, regalo, ricordo…)</small><input type="text" name="occasione" maxlength="120" value="<?= e($v['occasione']) ?>"></label>
  <label>Genere <small>(fantasy, avventura, mistero, favola, umoristico…)</small><input type="text" name="genere" maxlength="120" value="<?= e($v['genere']) ?>"></label>
  <label>La storia che desideri <small>(personaggi, luoghi, passioni, cosa deve succedere…)</small><textarea name="storia" required minlength="20" maxlength="5000" rows="7"><?= e($v['storia']) ?></textarea></label>
  <label>Dedica <small>(facoltativa, da stampare nel libro)</small><input type="text" name="dedica" maxlength="500" value="<?= e($v['dedica']) ?>"></label>
  <div class="due">
    <label>Lingua <select name="lingua"><?php foreach (['Italiano', 'Russo', 'Italiano + Russo'] as $x): ?><option <?= $v['lingua'] === $x ? 'selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select></label>
    <label>Formato <select name="formato" id="formato"><?php foreach (['PDF', 'Stampato', 'PDF + stampato'] as $x): ?><option <?= $v['formato'] === $x ? 'selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select></label>
  </div>
  <label>Lunghezza desiderata <small>(facoltativa, es. breve / circa 40 pagine)</small><input type="text" name="pagine" maxlength="30" value="<?= e($v['pagine']) ?>"></label>
  <label>Entro quando lo vorresti <small>(facoltativo)</small><input type="text" name="scadenza" maxlength="60" value="<?= e($v['scadenza']) ?>" placeholder="es. entro Natale"></label>
  <div id="spedizione">
    <h3>Spedizione <small>(solo per la copia stampata)</small></h3>
    <label>Indirizzo <input type="text" name="indirizzo" maxlength="200" value="<?= e($v['indirizzo']) ?>" autocomplete="street-address"></label>
    <div class="due"><label>CAP <input type="text" name="cap" maxlength="12" value="<?= e($v['cap']) ?>" autocomplete="postal-code"></label>
    <label>Città <input type="text" name="citta" maxlength="80" value="<?= e($v['citta']) ?>" autocomplete="address-level2"></label></div>
    <label>Paese <input type="text" name="paese" maxlength="60" value="<?= e($v['paese']) ?>"></label>
  </div>
  <label>Altre note <small>(facoltativo)</small><textarea name="note" maxlength="1500" rows="3"><?= e($v['note']) ?></textarea></label>
  <button class="bottone">Invia la richiesta</button>
  <p class="piccolo-nota">Nessun pagamento ora: ti rispondo via email per concordare i dettagli.</p>
</form>
</div>
<script>
const f=document.getElementById('formato'), s=document.getElementById('spedizione');
function agg(){ s.hidden = f.value === 'PDF'; } f.addEventListener('change', agg); agg();
</script>
<?php piede();
