// Studente entrato nella sua classe: ogni esercizio controllato manda il risultato al prof (api-scuola.php).
(function () {
  var box = document.getElementById('esercizio');
  if (!box || !box.dataset.api) return;
  box.addEventListener('vs-risultato', function (e) {
    var d = e.detail || {}, nota = document.createElement('p');
    nota.className = 'meta inviato'; nota.textContent = 'Invio del risultato…';
    var vecchia = box.querySelector('.inviato'); if (vecchia) vecchia.remove();
    var esito = box.querySelector('.esito'); if (esito) esito.after(nota);
    fetch(box.dataset.api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': box.dataset.csrf }, body: JSON.stringify({ codice: d.codice, voci: d.voci || [] }) })
      .then(function (r) { return r.json(); })
      .then(function (j) { nota.textContent = j.ok ? '✓ Risultato inviato al prof' + (j.compito ? ' (compito)' : '') + '.' : 'Risultato non inviato: ' + (j.errore || 'errore'); })
      .catch(function () { nota.textContent = 'Risultato non inviato: controlla la connessione e riprova.'; });
  });
})();
