// Admin → Zone: disegna rettangoli sulle pagine del PDF (coordinate in millesimi) e scegli cosa aprono.
(function () {
  'use strict';
  var Z = window.ZONE, zone = Z.zone.slice(), sel = null, doc = null, N = 0, pag = 1, RE = /\b([A-Z]{2,6}-\d{1,4})\b/g;
  var box = document.getElementById('z-pagina-box'), tela = document.getElementById('z-tela'), inPag = document.getElementById('z-pagina');
  var sCod = document.getElementById('z-codice'), det = document.getElementById('z-dettagli'), nessuna = document.getElementById('z-nessuna');
  var sporco = false;
  pdfjsLib.GlobalWorkerOptions.workerSrc = '../assets/vendor/pdf.worker.min.js';

  function el(t, c, x) { var e = document.createElement(t); if (c) e.className = c; if (x !== undefined) e.textContent = x; return e; }
  function conta() { document.getElementById('z-conta').textContent = 'Zone disegnate in tutto il libro: ' + zone.length + (sporco ? ' (non salvate)' : ''); }
  function segna() { sporco = true; conta(); }
  window.addEventListener('beforeunload', function (e) { if (sporco) { e.preventDefault(); e.returnValue = ''; } });
  document.getElementById('z-form').addEventListener('submit', function () {
    sporco = false;
    document.getElementById('z-dati').value = JSON.stringify(zone); document.getElementById('z-pag-salva').value = pag;
  });

  function disegnaRett() {
    box.querySelectorAll('.rett').forEach(function (r) { r.remove(); });
    (box.auto || []).forEach(function (a) { aggiungiRett(a, true); });
    zone.forEach(function (z) { if (z.p === pag) aggiungiRett(z, false); });
    det.hidden = !sel; nessuna.hidden = !!sel;
    if (sel) sCod.value = sel.c || '';
  }
  function aggiungiRett(z, auto) {
    var r = el('div', 'rett' + (auto ? ' auto' : '') + (z === sel ? ' sel' : ''));
    r.style.left = z.x / 10 + '%'; r.style.top = z.y / 10 + '%'; r.style.width = z.w / 10 + '%'; r.style.height = z.h / 10 + '%';
    var info = Z.codici[z.c]; r.appendChild(el('span', '', (z.c || '?') + (info && info.titolo ? ' · ' + info.titolo : '')));
    if (!auto) {
      r.addEventListener('pointerdown', function (ev) {          // tocca = scegli; trascina = sposta
        ev.stopPropagation(); ev.preventDefault(); sel = z; disegnaRett();
        var b = box.getBoundingClientRect(), sx = ev.clientX, sy = ev.clientY, x0 = z.x, y0 = z.y;
        function muovi(e) {
          z.x = Math.max(0, Math.min(1000 - z.w, Math.round(x0 + (e.clientX - sx) / b.width * 1000)));
          z.y = Math.max(0, Math.min(1000 - z.h, Math.round(y0 + (e.clientY - sy) / b.height * 1000)));
          var q = box.querySelector('.rett.sel'); if (q) { q.style.left = z.x / 10 + '%'; q.style.top = z.y / 10 + '%'; }
        }
        function fine() { document.removeEventListener('pointermove', muovi); document.removeEventListener('pointerup', fine); if (z.x !== x0 || z.y !== y0) segna(); }
        document.addEventListener('pointermove', muovi); document.addEventListener('pointerup', fine);
      });
    }
    box.appendChild(r);
  }

  // disegnare una zona nuova trascinando sulla pagina
  box.addEventListener('pointerdown', function (ev) {
    if (ev.target.closest('.rett:not(.auto)')) return;
    ev.preventDefault();
    var b = box.getBoundingClientRect(), x0 = (ev.clientX - b.left) / b.width * 1000, y0 = (ev.clientY - b.top) / b.height * 1000;
    var z = { p: pag, x: Math.round(x0), y: Math.round(y0), w: 0, h: 0, c: sel && sel.c ? sel.c : '' }, prova = el('div', 'rett'); box.appendChild(prova);
    function muovi(e) {
      var x1 = Math.max(0, Math.min(1000, (e.clientX - b.left) / b.width * 1000)), y1 = Math.max(0, Math.min(1000, (e.clientY - b.top) / b.height * 1000));
      z.x = Math.round(Math.min(x0, x1)); z.y = Math.round(Math.min(y0, y1)); z.w = Math.round(Math.abs(x1 - x0)); z.h = Math.round(Math.abs(y1 - y0));
      prova.style.left = z.x / 10 + '%'; prova.style.top = z.y / 10 + '%'; prova.style.width = z.w / 10 + '%'; prova.style.height = z.h / 10 + '%';
    }
    function fine() {
      document.removeEventListener('pointermove', muovi); document.removeEventListener('pointerup', fine); prova.remove();
      if (z.w < 8 || z.h < 8) { sel = null; disegnaRett(); return; }   // solo un tocco: deseleziona
      zone.push(z); sel = z; segna(); disegnaRett(); sCod.focus();
    }
    document.addEventListener('pointermove', muovi); document.addEventListener('pointerup', fine);
  });
  sCod.addEventListener('change', function () { if (sel) { sel.c = sCod.value; segna(); disegnaRett(); } });
  document.getElementById('z-togli').addEventListener('click', function () { if (!sel) return; zone.splice(zone.indexOf(sel), 1); sel = null; segna(); disegnaRett(); });
  document.addEventListener('keydown', function (e) { if ((e.key === 'Delete' || e.key === 'Backspace') && sel && !/INPUT|SELECT|TEXTAREA/.test(e.target.tagName)) { e.preventDefault(); document.getElementById('z-togli').click(); } });

  function mostra(n) {
    pag = Math.max(1, Math.min(N || 1, n)); inPag.value = pag; sel = null;
    doc.getPage(pag).then(function (p) {
      var w = Math.min(1600, Math.max(600, box.parentNode.clientWidth * (window.devicePixelRatio || 1)));
      var vp = p.getViewport({ scale: w / p.getViewport({ scale: 1 }).width });
      tela.width = vp.width; tela.height = vp.height;
      return p.render({ canvasContext: tela.getContext('2d'), viewport: vp }).promise.then(function () { return p.getTextContent(); }).then(function (tc) {
        var auto = [];
        tc.items.forEach(function (it) {
          var s = it.str || '', m; RE.lastIndex = 0;
          while ((m = RE.exec(s))) {
            if (!Z.codici[m[1]]) continue;
            var t = pdfjsLib.Util.transform(vp.transform, it.transform), w2 = it.width * vp.scale, h = Math.hypot(t[2], t[3]) || 12;
            var x0 = t[4] + w2 * (m.index / s.length), wc = w2 * (m[1].length / s.length);
            auto.push({ p: pag, c: m[1], x: Math.max(0, (x0 - h * 2.4) / vp.width * 1000), y: Math.max(0, (t[5] - h * 1.5) / vp.height * 1000), w: (wc + h * 2.8) / vp.width * 1000, h: h * 2.2 / vp.height * 1000 });
          }
        });
        box.auto = auto; disegnaRett();
      });
    });
  }
  document.getElementById('z-prec').addEventListener('click', function () { mostra(pag - 1); });
  document.getElementById('z-succ').addEventListener('click', function () { mostra(pag + 1); });
  inPag.addEventListener('change', function () { mostra(parseInt(inPag.value, 10) || 1); });

  pdfjsLib.getDocument(Z.pdf).promise.then(function (d) {
    doc = d; N = d.numPages; document.getElementById('z-tot').textContent = N; inPag.max = N;
    mostra(parseInt(inPag.value, 10) || 1); conta();
  }).catch(function () { box.textContent = 'Non riesco ad aprire il PDF.'; });
})();
