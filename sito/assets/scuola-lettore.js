// Lettore dei libri scolastici: rende cliccabili i simboli degli esercizi e dei contenuti sulle pagine del PDF.
// 1) Automatico: cerca nel testo della pagina i codici (es. «CFC-12») che il libro stampa accanto a ogni simbolo.
// 2) A mano: le zone disegnate nell'admin (Admin → Scuola → Zone).
// Un clic apre l'esercizio in una finestra sopra il libro, senza grandi pulsanti sulla pagina.
(function () {
  var S = window.SCUOLA || { codici: {}, zone: [] }, fatte = {};
  var finestra = document.getElementById('finestra-scuola'), cornice = document.getElementById('cornice-scuola'), titolo = document.getElementById('titolo-finestra');
  var ICONE = { esercizio: '✏️', audio: '🔊', video: '🎬', immagine: '🖼' };
  var RE = /\b([A-Z]{2,6}-\d{1,4})\b/g;

  function apri(codice) {
    var info = S.codici[codice] || {};
    titolo.textContent = (ICONE[info.g === 'esercizio' ? 'esercizio' : info.t] || '') + ' ' + (info.titolo || codice);
    cornice.src = 'apri.php?embed=1&c=' + encodeURIComponent(codice);
    finestra.hidden = false;
    document.getElementById('chiudi-finestra').focus();
  }
  function chiudi() { finestra.hidden = true; cornice.src = 'about:blank'; }
  document.getElementById('chiudi-finestra').addEventListener('click', chiudi);
  finestra.addEventListener('click', function (e) { if (e.target === finestra) chiudi(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !finestra.hidden) { e.stopPropagation(); chiudi(); } }, true);
  window.addEventListener('message', function (e) { if (e.origin === location.origin && e.data && e.data.scuola === 'chiudi') chiudi(); });

  function zona(div, x, y, w, h, codice) {                  // coordinate in frazioni della pagina (0–1)
    var info = S.codici[codice]; if (!info) return;
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'zona-scuola';
    b.style.left = (x * 100) + '%'; b.style.top = (y * 100) + '%'; b.style.width = (w * 100) + '%'; b.style.height = (h * 100) + '%';
    b.title = (info.titolo || codice) + ' (' + codice + ')';
    b.setAttribute('aria-label', 'Apri ' + (info.g === 'esercizio' ? 'esercizio' : info.t) + ': ' + (info.titolo || codice));
    var ultimoTocco = 0;
    // col dito: si apre subito al tocco (alcuni telefoni non mandano il «click» dentro il libro che si sfoglia)
    b.addEventListener('pointerup', function (e) { if (e.pointerType !== 'mouse') { e.stopPropagation(); ultimoTocco = Date.now(); apri(codice); } });
    b.addEventListener('click', function (e) { e.stopPropagation(); if (Date.now() - ultimoTocco > 700) apri(codice); });
    div.appendChild(b);
  }

  // tocco vicino a una zona piccola (fino a 22 px di distanza): apre lo stesso
  window.zonaVicina = function (cx, cy) {
    var best = null, dmin = 23;
    document.querySelectorAll('.zona-scuola').forEach(function (b) {
      var r = b.getBoundingClientRect(); if (!r.width) return;
      var d = Math.hypot(Math.max(r.left - cx, 0, cx - r.right), Math.max(r.top - cy, 0, cy - r.bottom));
      if (d < dmin) { dmin = d; best = b; }
    });
    if (best && finestra.hidden) { best.click(); return true; }
    return false;
  };

  window.dopoPagina = function (i, page, vp, div) {
    if (fatte[i]) return;
    fatte[i] = true;
    var manuali = S.zone.filter(function (z) { return z.p === i + 1; });
    manuali.forEach(function (z) { zona(div, z.x / 1000, z.y / 1000, z.w / 1000, z.h / 1000, z.c); });
    var giaManuali = {}; manuali.forEach(function (z) { giaManuali[z.c] = 1; });
    page.getTextContent().then(function (tc) {
      var W = vp.width, H = vp.height;
      tc.items.forEach(function (it) {
        var s = it.str || '', m; RE.lastIndex = 0;
        while ((m = RE.exec(s))) {
          var codice = m[1];
          if (!S.codici[codice] || giaManuali[codice]) continue;
          var t = pdfjsLib.Util.transform(vp.transform, it.transform);
          var w = it.width * vp.scale, h = Math.hypot(t[2], t[3]) || 12;
          var x0 = t[4] + w * (m.index / Math.max(1, s.length)), wc = w * (codice.length / Math.max(1, s.length));
          // il simbolo sta di solito a sinistra del codice: la zona lo comprende, con un po' di margine
          var x = x0 - h * 2.4, y = t[5] - h * 1.5, ww = wc + h * 2.8, hh = h * 2.2;
          zona(div, Math.max(0, x / W), Math.max(0, y / H), Math.min(1, ww / W), Math.min(1, hh / H), codice);
        }
      });
    }).catch(function () {});
  };
})();
