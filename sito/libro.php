<?php
require __DIR__ . '/inc/pubblico.php';

$token = (string)($_GET['anteprima'] ?? '');
$l = libro_per_slug((string)($_GET['s'] ?? ''), $token);
if (!$l) pagina_non_trovata();

$altri = [];
if ($l['collana_id']) {
    $st = db()->prepare('SELECT l.*, c.nome AS nome_collana FROM libri l LEFT JOIN collane c ON c.id = l.collana_id WHERE l.collana_id = ? AND l.id != ? AND ' . sql_visibile('l') . ' ORDER BY l.numero, l.id');
    $st->execute([$l['collana_id'], $l['id']]);
    $altri = $st->fetchAll();
}
$lt = $l['anteprima'] ? null : lettore_corrente();
$voti = voti_libro((int)$l['id']);
$recensioni = []; $mia = null;
if (!$l['anteprima']) {
    $st = db()->prepare('SELECT r.*, t.nick FROM recensioni r JOIN lettori t ON t.id = r.lettore_id WHERE r.libro_id = ? AND r.stato = 1 ORDER BY r.aggiornato DESC LIMIT 50');
    $st->execute([$l['id']]); $recensioni = $st->fetchAll();
    if ($lt) { $st = db()->prepare('SELECT * FROM recensioni WHERE libro_id = ? AND lettore_id = ?'); $st->execute([$l['id'], $lt['id']]); $mia = $st->fetch() ?: null; }
}
$suffisso = $l['anteprima'] ? '&anteprima=' . urlencode($token) : '';
$descr = $l['meta'] !== '' ? $l['meta'] : $l['trama'];
intestazione($l['titolo'], '', $descr, [
    'tipo' => 'book', 'canonico' => 'libro.php?s=' . urlencode($l['slug']),
    'immagine' => $l['copertina'] ? miniatura($l['copertina'], 800) : null,
]);
if ($l['anteprima']): ?><p class="avviso">👁 Anteprima privata: questo libro non è ancora visibile al pubblico.</p><?php endif; ?>
<?php
$ld = ['@context' => 'https://schema.org', '@type' => 'Book', 'name' => $l['titolo'], 'inLanguage' => $l['lingua'], 'url' => base_ufficiale() . 'libro.php?s=' . urlencode($l['slug'])];
if ($l['autore']) $ld['author'] = ['@type' => 'Person', 'name' => $l['autore']];
if ($l['trama']) $ld['description'] = $l['trama'];
if ($l['isbn']) $ld['isbn'] = $l['isbn'];
if ($l['copertina']) $ld['image'] = assoluto(miniatura($l['copertina'], 800));
if ($l['genere']) $ld['genre'] = $l['genere'];
if ($voti['n'] > 0) $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $voti['media'], 'reviewCount' => $voti['n'], 'bestRating' => 5, 'worstRating' => 1];
if (!$l['anteprima']): ?><script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script><?php endif; ?>
<article class="libro">
  <?php if ($l['copertina']): ?><a href="<?= e(url_file($l['copertina'], 'cover')) ?>" target="_blank" title="Vedi la copertina intera"><img class="copertina" src="<?= e(miniatura($l['copertina'], 800)) ?>" alt="Copertina di <?= e($l['titolo']) ?>"></a><?php endif; ?>
  <div>
    <h1><?= e($l['titolo']) ?></h1>
    <?php if ($l['sottotitolo']): ?><p class="sottotitolo"><?= e($l['sottotitolo']) ?></p><?php endif; ?>
    <p class="meta">
      <?php if ($l['autore']): ?>di <b><?= e($l['autore']) ?></b><?php endif; ?>
      <?php if ($l['nome_collana'] && $l['collana_visibile']): ?> · <a href="collana.php?s=<?= e($l['slug_collana']) ?>"><?= e($l['nome_collana']) ?></a><?= $l['numero'] ? ' (libro ' . (int)$l['numero'] . ')' : '' ?><?php endif; ?>
    </p>
    <p class="meta"><?= e(implode(' · ', array_filter([$l['genere'], $l['anno'] ? (string)$l['anno'] : '', $l['pagine_n'] ? $l['pagine_n'] . ' pagine' : '', strtoupper($l['lingua']), $l['isbn'] ? 'ISBN ' . $l['isbn'] : '']))) ?></p>
    <?php if ($l['trama']): ?><div class="trama"><?= nl2br(e($l['trama'])) ?></div><?php endif; ?>
    <?php if ($l['tags']): ?><p class="chips"><?php foreach (tags_di($l['tags']) as $t): ?><a href="index.php?tag=<?= urlencode($t) ?>">#<?= e($t) ?></a><?php endforeach; ?></p><?php endif; ?>
    <?php if ($voti['n'] > 0): ?><p class="meta"><a href="#recensioni" class="stelle" title="Media dei voti"><?= stelle($voti['media']) ?></a> <?= number_format($voti['media'], 1, ',', '') ?> su 5 · <?= $voti['n'] ?> <?= $voti['n'] === 1 ? 'voto' : 'voti' ?></p><?php endif; ?>
    <?php if ($l['pdf']): ?>
    <p class="azioni">
      <a class="bottone" href="leggi.php?s=<?= e($l['slug']) . $suffisso ?>">Leggi online</a>
      <?php if (!$l['anteprima'] && ($lt || registrazione_aperta())):
        foreach (['preferito' => ['❤', 'Aggiungi ai preferiti', 'Nei preferiti'], 'da_leggere' => ['🔖', 'Da leggere', 'Nella lista da leggere']] as $k => [$ic, $no, $si]):
          $dentro = $lt && in_lista((int)$lt['id'], (int)$l['id'], $k); ?>
        <?php if ($lt): ?><form method="post" action="account.php?a=lista" class="in-riga"><?= csrf_campo() ?><input type="hidden" name="libro" value="<?= e($l['slug']) ?>"><input type="hidden" name="lista" value="<?= $k ?>"><button class="bottone secondario<?= $dentro ? ' acceso' : '' ?>" aria-pressed="<?= $dentro ? 'true' : 'false' ?>"><?= $ic ?> <?= $dentro ? $si : $no ?></button></form>
        <?php else: ?><a class="bottone secondario" href="account.php?a=accedi&amp;ritorno=<?= urlencode('libro.php?s=' . $l['slug']) ?>"><?= $ic ?> <?= $no ?></a><?php endif; ?>
      <?php endforeach; endif; ?>
    </p>
    <?php endif; ?>
    <?php if (impost('contatori_pubblici') === '1'): ?><p class="meta">👁 <?= (int)$l['letture'] ?> letture</p><?php endif; ?>
  </div>
</article>
<?php if ($altri): ?>
<h2 class="sezione">Nella stessa collana</h2>
<div class="griglia"><?php foreach ($altri as $a) scheda_libro($a); ?></div>
<?php endif; ?>
<?php if (!$l['anteprima'] && ($recensioni || $lt || registrazione_aperta())): ?>
<section id="recensioni">
<h2 class="sezione">Recensioni</h2>
<?php foreach ($_SESSION['flash'] ?? [] as [$tipo, $msg]) echo '<p class="' . ($tipo === 'ok' ? 'ok-pub' : 'errore') . '">' . e($msg) . '</p>'; unset($_SESSION['flash']); ?>
<?php if (!$recensioni): ?><p class="meta">Ancora nessuna recensione.</p><?php endif; ?>
<?php foreach ($recensioni as $r): ?>
  <div class="recensione"><span class="stelle"><?= stelle((float)$r['voto']) ?></span> <b><?= e($r['titolo']) ?></b>
    <p class="meta">di <?= e($r['nick']) ?> · <?= e(date('d/m/Y', strtotime($r['aggiornato']))) ?></p>
    <?php if ($r['testo'] !== ''): ?><p><?= nl2br(e($r['testo'])) ?></p><?php endif; ?></div>
<?php endforeach; ?>
<?php if ($lt): ?>
  <?php if ($mia && (int)$mia['stato'] !== 1): ?><p class="avviso"><?= (int)$mia['stato'] === 0 ? 'La tua recensione è in attesa di verifica.' : 'La tua recensione non è stata pubblicata.' ?></p><?php endif; ?>
  <details <?= $mia ? '' : 'open' ?>><summary><?= $mia ? 'Modifica la tua recensione' : 'Scrivi una recensione' ?></summary>
  <form method="post" action="account.php?a=recensione" class="form-pub">
    <?= csrf_campo() ?><input type="hidden" name="libro" value="<?= e($l['slug']) ?>">
    <label>Voto<select name="voto" required><option value="">Scegli…</option><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= $mia && (int)$mia['voto'] === $i ? 'selected' : '' ?>><?= str_repeat('★', $i) ?> (<?= $i ?>)</option><?php endfor; ?></select></label>
    <label>Titolo (facoltativo)<input type="text" name="titolo" maxlength="80" value="<?= e($mia['titolo'] ?? '') ?>"></label>
    <label>La tua recensione (facoltativa)<textarea name="testo" rows="5" maxlength="2000"><?= e($mia['testo'] ?? '') ?></textarea></label>
    <p class="meta">Si vedrà il tuo nome pubblico (<b><?= e($lt['nick']) ?></b>), non la tua email.<?= impost('recensioni_moderate') === '0' ? '' : ' Prima di comparire la recensione viene controllata.' ?></p>
    <button class="bottone">Salva</button>
    <?php if ($mia): ?><button class="bottone secondario" name="azione" value="elimina" formnovalidate onclick="return confirm('Cancellare la tua recensione?')">Cancella</button><?php endif; ?>
  </form></details>
<?php elseif (registrazione_aperta()): ?>
  <p><a href="account.php?a=accedi&amp;ritorno=<?= urlencode('libro.php?s=' . $l['slug'] . '#recensioni') ?>">Accedi</a> o <a href="account.php?a=registrati">registrati</a> per lasciare un voto e una recensione.</p>
<?php endif; ?>
</section>
<?php endif; ?>
<?php piede();
