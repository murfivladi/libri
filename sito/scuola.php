<?php
// Vlad Editore Scuola: libri scolastici, e un campo per aprire un esercizio o un audio dal codice stampato nel libro.
// La sezione scolastica è solo in italiano.
require __DIR__ . '/inc/pubblico.php';
require_once __DIR__ . '/inc/classi.php';

$libri = db()->query("SELECT l.*, c.nome AS nome_collana, c.nome_ru AS nome_collana_ru FROM libri l LEFT JOIN collane c ON c.id = l.collana_id WHERE l.sezione = 'scuola' AND " . sql_visibile('l') . ' ORDER BY ' . ordine_catalogo())->fetchAll();
if (!$libri) pagina_non_trovata();

$stud = studente_corrente();
intestazione('Scuola', '', 'Vlad Editore Scuola: libri scolastici gratuiti con esercizi interattivi, audio e video.', ['css' => ['assets/scuola.css'], 'canonico' => 'scuola.php']);
?>
<section class="scuola-hero">
  <img src="assets/scuola-vlad-luce.png" alt="Vlad e Luce, la sua Shiba Inu" width="459" height="600">
  <div>
    <h1><?= e(impost('nome_sito')) ?> <span>Scuola</span></h1>
    <p>Libri scolastici gratuiti, spiegati da un ragazzo ai ragazzi. Leggi il libro online e tocca i simboli sulle pagine: si aprono esercizi interattivi, audio e video.</p>
  </div>
</section>

<?php foreach ($_SESSION['flash'] ?? [] as [$tipo, $msg]) echo '<p class="' . ($tipo === 'ok' ? 'ok-pub' : 'errore') . '">' . e($msg) . '</p>'; unset($_SESSION['flash']); ?>
<?php if ($stud): ?><p class="banner-studente">👤 Sei nella classe <b><?= e($stud['classe']) ?></b> come <b><?= e($stud['nick']) ?></b> · <a href="studente.php">i tuoi compiti</a></p><?php endif; ?>
<div class="box-codice">
  <b>Hai un codice dal libro?</b>
  <form action="apri.php" method="get"><input type="text" name="c" placeholder="es. CFC-12" aria-label="Codice dell'esercizio" required maxlength="12" autocomplete="off" autocapitalize="characters"><button class="bottone arancio">Apri</button></form>
</div>

<div class="griglia-classi">
  <a class="carta-classe" href="studente.php"><b>🎒 Sono uno studente</b><small class="meta">Entra nella tua classe con il codice del prof: i compiti e i tuoi risultati.</small></a>
  <a class="carta-classe" href="prof.php"><b>🍎 Sono un prof</b><small class="meta">Crea le classi, assegna i compiti, guarda risultati, errori comuni e progressi.</small></a>
</div>

<h2 class="sezione">I libri</h2>
<div class="griglia"><?php foreach ($libri as $l) scheda_libro($l); ?></div>
<?php piede();
