// Admin: mentre ci sono lavori AI in preparazione, controlla ogni 6 secondi; quando qualcosa è pronto ricarica la pagina.
(function () {
  var giri = 0;
  function controlla() {
    fetch('ai.php?azione=controlla', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.cambiati && j.cambiati.length) { location.reload(); return; }
      if (j.in_attesa > 0 && giri++ < 400) setTimeout(controlla, 6000);
    }).catch(function () { if (giri++ < 400) setTimeout(controlla, 15000); });
  }
  setTimeout(controlla, 3000);
})();
