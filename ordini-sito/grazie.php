<?php
require __DIR__ . '/inc/bootstrap.php';
intestazione('Grazie');
$id = (int)($_SESSION['ultimo_ordine'] ?? 0);
?>
<div class="ok-pub"><h1>Grazie! Richiesta ricevuta</h1>
<p><?= $id ? "La tua richiesta <b>n. $id</b> è arrivata a Vlad Editore." : 'La tua richiesta è arrivata a Vlad Editore.' ?> Ti scriverò presto via email per parlare della storia e metterci d'accordo. Nessun pagamento è richiesto ora.</p></div>
<p><a class="bottone" href="index.php">Torna alla home</a></p>
<?php piede();
