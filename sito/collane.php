<?php
// Elenco pubblico delle collane (solo quelle visibili e con almeno un libro pubblicato).
require __DIR__ . '/inc/pubblico.php';

$collane = db()->query('SELECT c.* FROM collane c WHERE c.visibile = 1 AND EXISTS (SELECT 1 FROM libri l WHERE l.collana_id = c.id AND ' . sql_visibile('l') . ') ORDER BY c.ordine, c.nome')->fetchAll();

intestazione(t('Collane'), '', t('Le collane') . ' · ' . impost('nome_sito') . ': ' . t('serie di libri da leggere in ordine.'));
?>
<h1><?= e(t('Collane')) ?></h1>
<?php if (!$collane): ?><p><?= e(t('Nessuna collana per ora.')) ?></p><?php endif; ?>
<div class="griglia griglia-collane">
<?php foreach ($collane as $c):
    $st = db()->prepare('SELECT COUNT(*) AS n, MIN(l.copertina) AS cop FROM libri l WHERE l.collana_id = ? AND ' . sql_visibile('l'));
    $st->execute([$c['id']]); $r = $st->fetch();
    $img = $c['banner'] !== '' ? $c['banner'] : (string)$r['cop']; ?>
  <a class="scheda" href="collana.php?s=<?= e($c['slug']) ?>">
    <?php if ($img !== ''): ?><img src="<?= e(miniatura($img, 400)) ?>" alt="" loading="lazy" width="400" height="600"><?php endif; ?>
    <span class="titolo"><?= e(campo_l($c, 'nome')) ?></span>
    <span class="meta"><?= (int)$r['n'] ?> <?= e(tn((int)$r['n'], 'libro', 'libri', 'книга', 'книги', 'книг')) ?></span>
  </a>
<?php endforeach; ?>
</div>
<?php piede();
