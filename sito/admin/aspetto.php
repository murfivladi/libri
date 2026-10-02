<?php
require __DIR__ . '/_admin.php';

const IMMAGINI_ASPETTO = [
    'logo_emblema' => ['Emblema (testata e icona del sito)', 'Meglio quadrata e con sfondo trasparente (PNG).'],
    'logo_grande'  => ['Logo grande (pagina «Chi siamo»)', 'Il logo completo con il nome.'],
    'banner_home'  => ['Banner della home', 'Immagine larga, almeno 1600 px. Il testo bianco si legge a sinistra: tieni la parte sinistra scura.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    try {
        foreach (IMMAGINI_ASPETTO as $k => $_) {
            $vecchio = impost($k);
            $nuovo = salva_file($k, 'cover', TIPI_IMMAGINE, 8 * 1024 * 1024);
            if ($nuovo) { imposta($k, $nuovo); elimina_file('cover', $vecchio); }
            elseif (!empty($_POST['ripristina_' . $k])) { imposta($k, ''); elimina_file('cover', $vecchio); }
        }
        if (!empty($_POST['colori_originali'])) { imposta('colore_blu', '#0b1a36'); imposta('colore_oro', '#c9a14a'); }
        else {
            imposta('colore_blu', colore_valido($_POST['colore_blu'] ?? '', '#0b1a36'));
            imposta('colore_oro', colore_valido($_POST['colore_oro'] ?? '', '#c9a14a'));
        }
        registra('Aspetto modificato');
        flash('Aspetto salvato.');
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'errore'); }
    torna('aspetto.php');
}
admin_testata('Aspetto del sito', 'aspetto');
?>
<form method="post" enctype="multipart/form-data" data-avvisa>
<?= csrf_campo() ?>
<div class="pannello"><h2 style="margin-top:0">Colori</h2>
  <div class="form-griglia">
    <label>Colore principale (testata, pulsanti) <input type="color" name="colore_blu" id="c-blu" value="<?= e(colore_valido(impost('colore_blu'), '#0b1a36')) ?>" style="height:44px"></label>
    <label>Colore dorato (bordi, dettagli) <input type="color" name="colore_oro" id="c-oro" value="<?= e(colore_valido(impost('colore_oro'), '#c9a14a')) ?>" style="height:44px"></label>
    <label><span>&nbsp;</span><span><input type="checkbox" name="colori_originali" value="1"> Ripristina i colori originali</span></label>
  </div>
  <div id="anteprima-tema" style="margin-top:12px;border:1px solid var(--linea);border-radius:8px;overflow:hidden">
    <div id="an-testata" style="background:<?= e(colore_valido(impost('colore_blu'), '#0b1a36')) ?>;color:#f3e7c6;padding:12px 16px;border-bottom:3px solid <?= e(colore_valido(impost('colore_oro'), '#c9a14a')) ?>;display:flex;gap:10px;align-items:center">
      <img src="../<?= e(asset_emblema()) ?>" width="32" height="32" alt="" style="border-radius:50%"> <b style="letter-spacing:.12em"><?= e(strtoupper(impost('nome_sito'))) ?></b>
      <span style="margin-left:auto">Catalogo · Chi siamo</span></div>
    <div style="padding:14px"><span id="an-bottone" style="display:inline-block;background:<?= e(colore_valido(impost('colore_blu'), '#0b1a36')) ?>;color:#f3e7c6;border:1px solid <?= e(colore_valido(impost('colore_oro'), '#c9a14a')) ?>;border-radius:6px;padding:8px 14px">Leggi online</span> <span class="muto">← anteprima</span></div>
  </div>
</div>

<?php foreach (IMMAGINI_ASPETTO as $k => [$titolo, $aiuto]):
    $att = impost($k);
    $anteprima = $k === 'logo_emblema' ? asset_emblema('../') : ($k === 'logo_grande' ? asset_logo('../') : asset_banner('../')); ?>
<div class="pannello"><h2 style="margin-top:0"><?= e($titolo) ?></h2>
  <p class="muto"><?= e($aiuto) ?></p>
  <p><img src="<?= e($anteprima) ?>" alt="" style="max-width:<?= $k === 'banner_home' ? '480' : '200' ?>px;max-height:200px;border-radius:6px;border:1px solid var(--linea);background:#8883"></p>
  <div class="form-griglia">
    <label>Carica un'immagine (JPG, PNG, WebP) <input type="file" name="<?= e($k) ?>" accept="image/*"></label>
    <label>…oppure da URL <input type="text" name="<?= e($k) ?>_url" placeholder="https://…"></label>
  </div>
  <?php if ($att): ?><label style="margin-top:8px"><span><input type="checkbox" name="ripristina_<?= e($k) ?>" value="1"> Torna all'immagine originale</span></label>
  <?php else: ?><p class="muto">Ora si usa l'immagine originale.</p><?php endif; ?>
</div>
<?php endforeach; ?>
<div class="salva-barra"><button class="bottone">💾 Salva</button><a class="bottone sec" href="../index.php" target="_blank">Vedi il sito ↗</a></div>
</form>
<script>
(function () {
  var blu = document.getElementById('c-blu'), oro = document.getElementById('c-oro');
  function agg() {
    var t = document.getElementById('an-testata'), b = document.getElementById('an-bottone');
    t.style.background = blu.value; t.style.borderBottomColor = oro.value; b.style.background = blu.value; b.style.borderColor = oro.value;
  }
  blu.addEventListener('input', agg); oro.addEventListener('input', agg);
})();
</script>
<?php admin_piede();
