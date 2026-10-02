<?php
require __DIR__ . '/_admin.php';

$campi_testo = ['nome_sito', 'slogan', 'sottoslogan', 'footer', 'email', 'autore_predefinito', 'messaggio_manutenzione'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    foreach ($campi_testo as $k) imposta($k, trim($_POST[$k] ?? ''));
    imposta('per_pagina', (string)max(3, min(60, (int)($_POST['per_pagina'] ?? 12))));
    imposta('ordine_catalogo', in_array($_POST['ordine_catalogo'] ?? '', ['numero', 'recenti', 'titolo', 'manuale'], true) ? $_POST['ordine_catalogo'] : 'numero');
    foreach (['contatori_pubblici', 'manutenzione', 'mostra_evidenza'] as $k) imposta($k, empty($_POST[$k]) ? '0' : '1');
    registra('Impostazioni salvate', impost('manutenzione') === '1' ? 'manutenzione ATTIVA' : '');
    flash('Impostazioni salvate.');
    torna('impostazioni.php');
}
admin_testata('Impostazioni', 'impostazioni');
?>
<form method="post" data-avvisa>
<?= csrf_campo() ?>
<div class="pannello"><h2 style="margin-top:0">Il sito</h2><div class="form-griglia">
  <label>Nome della casa editrice <input type="text" name="nome_sito" value="<?= e(impost('nome_sito')) ?>"></label>
  <label>Autore predefinito (per i nuovi libri) <input type="text" name="autore_predefinito" value="<?= e(impost('autore_predefinito')) ?>"></label>
  <label class="largo">Titolo grande in home <input type="text" name="slogan" value="<?= e(impost('slogan')) ?>"></label>
  <label class="largo">Frase sotto il titolo <input type="text" name="sottoslogan" value="<?= e(impost('sottoslogan')) ?>"></label>
  <label>Testo in fondo alla pagina <input type="text" name="footer" value="<?= e(impost('footer')) ?>"></label>
  <label>Email di contatto <small>(compare in fondo; vuota = nascosta)</small><input type="email" name="email" value="<?= e(impost('email')) ?>"></label>
</div></div>

<div class="pannello"><h2 style="margin-top:0">Catalogo</h2><div class="form-griglia">
  <label>Libri per pagina <input type="number" name="per_pagina" min="3" max="60" value="<?= (int)impost('per_pagina') ?>"></label>
  <label>Ordine dei libri <select name="ordine_catalogo">
    <?php foreach (['numero' => 'Per collana e numero', 'manuale' => 'Ordine manuale (frecce ↑↓ nell\'elenco)', 'recenti' => 'Più recenti prima', 'titolo' => 'Alfabetico'] as $k => $et): ?>
      <option value="<?= $k ?>" <?= impost('ordine_catalogo') === $k ? 'selected' : '' ?>><?= e($et) ?></option><?php endforeach; ?></select></label>
  <label><span>Opzioni</span>
    <span><input type="checkbox" name="mostra_evidenza" value="1" <?= impost('mostra_evidenza') === '1' ? 'checked' : '' ?>> Mostra i libri in evidenza in cima</span>
    <span><input type="checkbox" name="contatori_pubblici" value="1" <?= impost('contatori_pubblici') === '1' ? 'checked' : '' ?>> Mostra letture/download ai visitatori</span></label>
</div></div>

<div class="pannello"><h2 style="margin-top:0">Manutenzione</h2>
  <label><span><input type="checkbox" name="manutenzione" value="1" <?= impost('manutenzione') === '1' ? 'checked' : '' ?>> Metti il sito offline (tu, da loggato, lo vedi comunque)</span></label>
  <label>Messaggio per i visitatori <input type="text" name="messaggio_manutenzione" value="<?= e(impost('messaggio_manutenzione')) ?>"></label>
</div>
<div class="salva-barra"><button class="bottone">💾 Salva impostazioni</button></div>
</form>
<?php admin_piede();
