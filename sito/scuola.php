<?php
// Vlad Editore Scuola: libri scolastici, e un campo per aprire un esercizio o un audio dal codice stampato nel libro.
// La sezione scolastica è solo in italiano.
require __DIR__ . '/inc/pubblico.php';

$libri = db()->query("SELECT l.*, c.nome AS nome_collana, c.nome_ru AS nome_collana_ru FROM libri l LEFT JOIN collane c ON c.id = l.collana_id WHERE l.sezione = 'scuola' AND " . sql_visibile('l') . ' ORDER BY ' . ordine_catalogo())->fetchAll();
if (!$libri) pagina_non_trovata();

intestazione('Scuola', '', 'Vlad Editore Scuola: libri scolastici gratuiti con esercizi interattivi, audio e video.', ['css' => ['assets/scuola.css'], 'canonico' => 'scuola.php']);
?>
<section class="scuola-hero">
  <img src="assets/scuola-vlad-luce.png" alt="Vlad e Luce, la sua Shiba Inu" width="459" height="600">
  <div>
    <h1><?= e(impost('nome_sito')) ?> <span>Scuola</span></h1>
    <p>Libri scolastici gratuiti, spiegati da un ragazzo ai ragazzi. Leggi il libro online e tocca i simboli sulle pagine: si aprono esercizi interattivi, audio e video.</p>
  </div>
</section>

<div class="box-codice">
  <b>Hai un codice dal libro?</b>
  <form action="apri.php" method="get"><input type="text" name="c" placeholder="es. CFC-12" aria-label="Codice dell'esercizio" required maxlength="12" autocomplete="off" autocapitalize="characters"><button class="bottone arancio">Apri</button></form>
</div>

<h2 class="sezione">I libri</h2>
<div class="griglia"><?php foreach ($libri as $l) scheda_libro($l); ?></div>
<?php piede();
