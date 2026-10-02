// Lettore PDF: una pagina per volta, zoom, tema, ricorda la pagina.
(function () {
  var body = document.body, url = body.dataset.pdf, chiave = 'pag-' + body.dataset.chiave;
  var tela = document.getElementById('tela'), ctx = tela.getContext('2d');
  var stato = document.getElementById('stato'), pos = document.getElementById('pos');
  var doc = null, n = 1, zoom = 1, occupato = false, coda = null;
  function leggi(k, d) { try { return localStorage.getItem(k) || d; } catch (e) { return d; } }
  function scrivi(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  if (leggi('tema', 'chiaro') === 'scuro') body.classList.add('scuro');
  zoom = parseFloat(leggi('zoom', '1')) || 1;

  function mostra(num) {
    if (!doc) return;
    num = Math.max(1, Math.min(doc.numPages, num));
    if (occupato) { coda = num; return; }
    occupato = true; n = num;
    doc.getPage(num).then(function (p) {
      var base = p.getViewport({ scale: 1 });
      var largo = Math.min(window.innerWidth - 16, 900) * zoom;
      var scala = largo / base.width, dpr = window.devicePixelRatio || 1;
      var vp = p.getViewport({ scale: scala * dpr });
      tela.width = vp.width; tela.height = vp.height;
      tela.style.width = (vp.width / dpr) + 'px';
      return p.render({ canvasContext: ctx, viewport: vp }).promise;
    }).then(function () {
      pos.textContent = n + ' / ' + doc.numPages;
      scrivi(chiave, n); window.scrollTo(0, 0);
    }).catch(function () { stato.textContent = 'Errore nel disegnare la pagina.'; })
      .then(function () { occupato = false; if (coda) { var c = coda; coda = null; mostra(c); } });
  }

  pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
  pdfjsLib.getDocument(url).promise.then(function (d) {
    doc = d; stato.remove();
    mostra(parseInt(leggi(chiave, '1'), 10) || 1);
  }).catch(function () { stato.textContent = 'Impossibile aprire il PDF.'; });

  document.getElementById('avanti').onclick = function () { mostra(n + 1); };
  document.getElementById('indietro').onclick = function () { mostra(n - 1); };
  document.getElementById('piu').onclick = function () { zoom = Math.min(2.5, zoom + 0.15); scrivi('zoom', zoom); mostra(n); };
  document.getElementById('meno').onclick = function () { zoom = Math.max(0.5, zoom - 0.15); scrivi('zoom', zoom); mostra(n); };
  document.getElementById('tema').onclick = function () { body.classList.toggle('scuro'); scrivi('tema', body.classList.contains('scuro') ? 'scuro' : 'chiaro'); };
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'ArrowRight') mostra(n + 1);
    if (ev.key === 'ArrowLeft') mostra(n - 1);
  });
  var x0 = null;
  document.getElementById('area').addEventListener('touchstart', function (ev) { x0 = ev.touches[0].clientX; }, { passive: true });
  document.getElementById('area').addEventListener('touchend', function (ev) {
    if (x0 === null) return;
    var dx = ev.changedTouches[0].clientX - x0; x0 = null;
    if (Math.abs(dx) > 60) mostra(n + (dx < 0 ? 1 : -1));
  });
})();
