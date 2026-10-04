<?php
require __DIR__ . '/inc/bootstrap.php';
$st = db()->prepare('SELECT * FROM libri WHERE slug = ?'); $st->execute([(string)($_GET['s'] ?? '')]);
$l = $st->fetch();
if (!$l) { http_response_code(404); intestazione('Non trovato'); echo '<h1>Libro non trovato</h1><p><a href="index.php">Torna al catalogo</a></p>'; piede(); exit; }

$v = ['nome' => '', 'email' => '', 'telefono' => '', 'indirizzo' => '', 'cap' => '', 'citta' => '', 'paese' => 'Italia', 'note' => '', 'quantita' => '1'];
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    foreach ($v as $k => $_) $v[$k] = trim((string)($_POST[$k] ?? $v[$k]));
    $ip = ip_client();
    $n = db()->prepare('SELECT COUNT(*) FROM ordini WHERE ip = ? AND creato > ?'); $n->execute([$ip, date('Y-m-d H:i:s', time() - 3600)]);
    if (($_POST['sito'] ?? '') !== '') { vai('grazie.php'); }                       // trappola per i bot
    elseif (!$l['ordinabile']) $err = 'Questo libro non è ordinabile al momento.';
    elseif ((int)$n->fetchColumn() >= 5) $err = 'Troppi ordini da questo indirizzo: riprova più tardi.';
    elseif (mb_strlen($v['nome']) < 2) $err = 'Scrivi il tuo nome.';
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err = 'Email non valida.';
    elseif (mb_strlen($v['indirizzo']) < 4 || $v['citta'] === '' || $v['cap'] === '') $err = "Compila l'indirizzo di consegna (via, CAP e città).";
    elseif ((int)$v['quantita'] < 1 || (int)$v['quantita'] > 20) $err = 'Quantità non valida (da 1 a 20).';
    else {
        $ora = date('Y-m-d H:i:s');
        $ins = db()->prepare('INSERT INTO ordini (libro_id, titolo_libro, quantita, prezzo, nome, email, telefono, indirizzo, cap, citta, paese, note, creato, aggiornato, ip) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([$l['id'], $l['titolo'], (int)$v['quantita'], $l['prezzo'], mb_substr($v['nome'], 0, 120), mb_substr($v['email'], 0, 160),
            mb_substr($v['telefono'], 0, 40), mb_substr($v['indirizzo'], 0, 200), mb_substr($v['cap'], 0, 12), mb_substr($v['citta'], 0, 80),
            mb_substr($v['paese'], 0, 60), mb_substr($v['note'], 0, 1500), $ora, $ora, $ip]);
        $id = (int)db()->lastInsertId();
        invia_notifica(['id' => $id, 'titolo_libro' => $l['titolo'], 'quantita' => (int)$v['quantita']] + $v);
        $_SESSION['ultimo_ordine'] = $id;
        vai('grazie.php');
    }
}
intestazione('Ordina: ' . $l['titolo'], $l['descrizione']);
?>
<article class="libro">
  <?php if ($l['copertina']): ?><img class="copertina" src="<?= e($l['copertina']) ?>" alt="Copertina di <?= e($l['titolo']) ?>"><?php endif; ?>
  <div>
    <h1><?= e($l['titolo']) ?></h1>
    <?php if ($l['sottotitolo']): ?><p class="sottotitolo"><?= e($l['sottotitolo']) ?></p><?php endif; ?>
    <p class="meta">di <b><?= e($l['autore']) ?></b><?= $l['serie'] ? ' · ' . e($l['serie']) . ($l['numero'] ? ' (libro ' . (int)$l['numero'] . ')' : '') : '' ?> · <?= e(euro((float)$l['prezzo'])) ?></p>
    <div class="trama"><?= nl2br(e($l['descrizione'])) ?></div>
    <?php if ($l['ordinabile']): ?>
    <div class="ordina-box">
      <h2 style="margin-top:0">Ordina questo libro</h2>
      <?php if ($err): ?><p class="errore"><?= e($err) ?></p><?php endif; ?>
      <form method="post" class="form-pub">
        <?= csrf_campo() ?>
        <div class="trappola"><label>Sito <input type="text" name="sito" tabindex="-1" autocomplete="off"></label></div>
        <label>Nome e cognome <input type="text" name="nome" required maxlength="120" value="<?= e($v['nome']) ?>" autocomplete="name"></label>
        <label>Email <input type="email" name="email" required maxlength="160" value="<?= e($v['email']) ?>" autocomplete="email"></label>
        <label>Telefono <small>(facoltativo)</small><input type="text" name="telefono" maxlength="40" value="<?= e($v['telefono']) ?>" autocomplete="tel"></label>
        <label>Indirizzo <input type="text" name="indirizzo" required maxlength="200" value="<?= e($v['indirizzo']) ?>" autocomplete="street-address"></label>
        <div class="due">
          <label>CAP <input type="text" name="cap" required maxlength="12" value="<?= e($v['cap']) ?>" autocomplete="postal-code"></label>
          <label>Città <input type="text" name="citta" required maxlength="80" value="<?= e($v['citta']) ?>" autocomplete="address-level2"></label>
        </div>
        <label>Paese <input type="text" name="paese" maxlength="60" value="<?= e($v['paese']) ?>"></label>
        <label>Quantità <input type="number" name="quantita" min="1" max="20" value="<?= e($v['quantita']) ?>"></label>
        <label>Note <small>(dedica, richieste, lingua italiano/russo…)</small><textarea name="note" maxlength="1500"><?= e($v['note']) ?></textarea></label>
        <button class="bottone">Invia l'ordine</button>
        <p class="piccolo-nota">Non devi pagare ora: ti contatteremo via email per conferma, pagamento e spedizione.</p>
      </form>
    </div>
    <?php else: ?><p class="avviso">Questo libro non è ordinabile al momento.</p><?php endif; ?>
  </div>
</article>
<?php piede();
