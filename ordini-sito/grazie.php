<?php
require __DIR__ . '/inc/bootstrap.php';
intestazione('Grazie');
$id = (int)($_SESSION['ultimo_ordine'] ?? 0);
?>
<div class="ok-pub"><h1>Grazie! Ordine ricevuto</h1>
<p><?= $id ? "Il tuo ordine <b>n. $id</b> è arrivato a Vlad Editore." : "Il tuo ordine è arrivato a Vlad Editore." ?> Ti scriveremo presto via email per confermare e organizzare la consegna.</p></div>
<p><a class="bottone" href="index.php">Torna al catalogo</a></p>
<?php piede();
