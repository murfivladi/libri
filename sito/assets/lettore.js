// Lettore PDF: una pagina per volta, indice, segnalibri, ricerca nel testo, zoom (anche con le dita), tema, schermo intero.
(function () {
  var body = document.body, url = body.dataset.pdf, slug = body.dataset.chiave;
  var tela = document.getElementById('tela'), ctx = tela.getContext('2d');
  var stato = document.getElementById('stato');
  var vai = document.getElementById('vai'), tot = document.getElementById('tot');
  var area = document.getElementById('area');
  var doc = null, n = 1, zoom = 1, occupato = false, coda = null, termine = '';

  function leggi(k, d) { try { var v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } }
  function scrivi(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function el(t, c, txt) { var x = document.createElement(t); if (c) x.className = c; if (txt !== undefined) x.textContent = txt; return x; }
  function norm(s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }

  if (leggi('tema', 'chiaro') === 'scuro') body.classList.add('scuro');
  zoom = parseFloat(leggi('zoom', '1')) || 1;

  // ---------- disegno della pagina ----------
  function mostra(num) {
    if (!doc) return;
    num = Math.max(1, Math.min(doc.numPages, Math.round(num) || 1));
    if (occupato) { coda = num; return; }
    occupato = true; n = num;
    doc.getPage(num).then(function (p) {
      var base = p.getViewport({ scale: 1 });
      var largo = Math.min(window.innerWidth - 12, 900) * zoom;
      var scala = largo / base.width, dpr = Math.min(window.devicePixelRatio || 1, 2.5);
      var vp = p.getViewport({ scale: scala * dpr });
      tela.width = vp.width; tela.height = vp.height;
      tela.style.width = (vp.width / dpr) + 'px'; tela.style.height = (vp.height / dpr) + 'px';
      tela.style.transform = '';
      return p.render({ canvasContext: ctx, viewport: vp }).promise.then(function () {
        if (termine) return evidenzia(p, vp);
      });
    }).then(function () {
      vai.value = n; tot.textContent = doc.numPages;
      document.querySelector('#progresso i').style.width = (doc.numPages > 1 ? (n - 1) / (doc.numPages - 1) * 100 : 100) + '%';
      scrivi('pag-' + slug, n);
      aggiornaStella();
      window.scrollTo(0, 0); area.scrollLeft = 0;
    }).catch(function () { stato.textContent = 'Errore nel disegnare la pagina.'; stato.hidden = false; })
      .then(function () { occupato = false; if (coda) { var c = coda; coda = null; mostra(c); } });
  }

  // evidenzia le parole cercate sulla pagina
  function evidenzia(page, vp) {
    return page.getTextContent().then(function (tc) {
      var t = norm(termine); if (!t) return;
      ctx.save(); ctx.globalCompositeOperation = 'multiply'; ctx.fillStyle = 'rgba(255, 214, 0, 0.55)';
      tc.items.forEach(function (it) {
        var s = norm(it.str), i = s.indexOf(t);
        while (i >= 0 && s.length) {
          var tx = pdfjsLib.Util.transform(vp.transform, it.transform);
          var w = it.width * vp.scale, h = Math.hypot(tx[2], tx[3]);
          var x = tx[4] + w * (i / s.length), ww = w * (t.length / s.length);
          ctx.fillRect(x, tx[5] - h * 0.85, ww, h * 1.05);
          i = s.indexOf(t, i + t.length);
        }
      });
      ctx.restore();
    });
  }

  // ---------- caricamento ----------
  pdfjsLib.GlobalWorkerOptions.workerSrc = 'assets/vendor/pdf.worker.min.js';
  pdfjsLib.getDocument(url).promise.then(function (d) {
    doc = d; stato.hidden = true; tot.textContent = d.numPages; vai.max = d.numPages;
    mostra(parseInt(leggi('pag-' + slug, '1'), 10) || 1);
    costruisciIndice();
    mostraSegnalibri();
  }).catch(function () { stato.textContent = 'Impossibile aprire il PDF.'; });

  // ---------- comandi di base ----------
  function on(id, f) { document.getElementById(id).addEventListener('click', f); }
  function imposta_zoom(z) { zoom = Math.max(0.5, Math.min(3, z)); scrivi('zoom', zoom); mostra(n); }
  on('avanti', function () { mostra(n + 1); });
  on('indietro', function () { mostra(n - 1); });
  on('piu', function () { imposta_zoom(zoom + 0.15); });
  on('meno', function () { imposta_zoom(zoom - 0.15); });
  on('adatta', function () { imposta_zoom(1); });
  on('tema', function () { body.classList.toggle('scuro'); scrivi('tema', body.classList.contains('scuro') ? 'scuro' : 'chiaro'); });
  on('schermo', function () {
    if (document.fullscreenElement) document.exitFullscreen();
    else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen();
  });
  vai.addEventListener('change', function () { mostra(parseInt(vai.value, 10)); });
  vai.addEventListener('keydown', function (ev) { ev.stopPropagation(); if (ev.key === 'Enter') { mostra(parseInt(vai.value, 10)); vai.blur(); } });
  window.addEventListener('resize', (function () { var t; return function () { clearTimeout(t); t = setTimeout(function () { mostra(n); }, 200); }; })());

  // ---------- pannello laterale ----------
  var pannello = document.getElementById('pannello');
  function apri(tab) {
    pannello.hidden = false;
    document.querySelectorAll('.schede-pannello [data-tab]').forEach(function (b) { b.classList.toggle('att', b.dataset.tab === tab); });
    document.querySelectorAll('#pannello .tab').forEach(function (t) { t.hidden = t.id !== 'tab-' + tab; });
    if (tab === 'cerca') setTimeout(function () { document.getElementById('testo-cerca').focus(); }, 50);
  }
  on('b-pannello', function () { if (pannello.hidden) apri('indice'); else pannello.hidden = true; });
  on('chiudi-pannello', function () { pannello.hidden = true; });
  document.querySelectorAll('.schede-pannello [data-tab]').forEach(function (b) { b.addEventListener('click', function () { apri(b.dataset.tab); }); });
  function vaiA(p) { mostra(p); if (window.innerWidth < 700) pannello.hidden = true; }

  // indice interno del PDF; se manca, griglia di pagine
  function costruisciIndice() {
    var box = document.getElementById('tab-indice');
    doc.getOutline().then(function (out) {
      if (!out || !out.length) return griglia(box);
      var ul = el('ul', 'elenco-pannello');
      (function aggiungi(voci, lista, liv) {
        voci.forEach(function (v) {
          var li = el('li'), b = el('button', 'voce', v.title); b.style.paddingLeft = (8 + liv * 14) + 'px';
          b.addEventListener('click', function () {
            var d = v.dest; if (typeof d === 'string') d = doc.getDestination(d); else d = Promise.resolve(d);
            d.then(function (dest) { if (dest) return doc.getPageIndex(dest[0]); }).then(function (i) { if (i !== undefined) vaiA(i + 1); });
          });
          li.appendChild(b); lista.appendChild(li);
          if (v.items && v.items.length) aggiungi(v.items, lista, liv + 1);
        });
      })(out, ul, 0);
      box.appendChild(ul);
    }).catch(function () { griglia(box); });
  }
  function griglia(box) {
    box.appendChild(el('p', 'muto', 'Questo libro non ha un indice: vai direttamente a una pagina.'));
    var g = el('div', 'griglia-pagine');
    for (var i = 1; i <= doc.numPages; i++) (function (i) { var b = el('button', '', String(i)); b.addEventListener('click', function () { vaiA(i); }); g.appendChild(b); })(i);
    box.appendChild(g);
  }

  // ---------- segnalibri ----------
  function segnalibri() { try { return JSON.parse(leggi('segn-' + slug, '[]')) || []; } catch (e) { return []; } }
  function salvaSegn(l) { l.sort(function (a, b) { return a - b; }); scrivi('segn-' + slug, JSON.stringify(l)); aggiornaStella(); mostraSegnalibri(); }
  function aggiornaStella() { var b = document.getElementById('b-segna'); var s = segnalibri().indexOf(n) >= 0; b.textContent = s ? '★' : '☆'; b.classList.toggle('attivo', s); }
  function commutaSegn() { var l = segnalibri(), i = l.indexOf(n); if (i >= 0) l.splice(i, 1); else l.push(n); salvaSegn(l); }
  on('b-segna', commutaSegn);
  function mostraSegnalibri() {
    var box = document.getElementById('tab-segnalibri'); box.textContent = '';
    var l = segnalibri();
    box.appendChild(el('p', 'muto', l.length ? 'Tocca per tornare a una pagina.' : 'Nessun segnalibro. Premi ☆ nella barra per aggiungerne uno.'));
    var ul = el('ul', 'elenco-pannello');
    l.forEach(function (p) {
      var li = el('li', 'riga-segn'), b = el('button', 'voce', 'Pagina ' + p);
      b.addEventListener('click', function () { vaiA(p); });
      var x = el('button', 'x', '✕'); x.setAttribute('aria-label', 'Togli segnalibro');
      x.addEventListener('click', function () { salvaSegn(segnalibri().filter(function (q) { return q !== p; })); });
      li.appendChild(b); li.appendChild(x); ul.appendChild(li);
    });
    box.appendChild(ul);
  }

  // ---------- ricerca nel testo ----------
  var testi = null;   // cache del testo di ogni pagina
  function testoPagina(i) {
    if (testi[i] !== undefined) return Promise.resolve(testi[i]);
    return doc.getPage(i).then(function (p) { return p.getTextContent(); }).then(function (tc) {
      return (testi[i] = tc.items.map(function (x) { return x.str; }).join(' '));
    });
  }
  document.getElementById('form-cerca').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var q = document.getElementById('testo-cerca').value.trim();
    var st = document.getElementById('stato-cerca'), ul = document.getElementById('risultati');
    ul.textContent = ''; termine = ''; mostra(n);
    if (q.length < 2) { st.textContent = 'Scrivi almeno 2 lettere.'; return; }
    if (!doc) return;
    testi = testi || {}; var nq = norm(q), trovati = 0, i = 1;
    (function passo() {
      if (i > doc.numPages || trovati >= 200) { st.textContent = trovati ? trovati + ' risultati' : 'Nessun risultato.'; return; }
      st.textContent = 'Cerco… pagina ' + i + ' di ' + doc.numPages;
      testoPagina(i).then(function (t) {
        var nt = norm(t), pos = nt.indexOf(nq), pagina = i;
        while (pos >= 0 && trovati < 200) {
          var a = Math.max(0, pos - 30), frase = t.substring(a, pos + q.length + 40).replace(/\s+/g, ' ');
          var li = el('li'), b = el('button', 'voce');
          b.appendChild(el('b', '', 'p. ' + pagina + ' '));
          b.appendChild(document.createTextNode('…' + frase + '…'));
          b.addEventListener('click', function (p) { return function () { termine = q; vaiA(p); }; }(pagina));
          li.appendChild(b); ul.appendChild(li); trovati++;
          pos = nt.indexOf(nq, pos + nq.length);
        }
        i++; setTimeout(passo, 0);
      });
    })();
    termine = q;
  });

  // ---------- tastiera ----------
  document.addEventListener('keydown', function (ev) {
    var t = ev.target.tagName;
    if (t === 'INPUT' || t === 'TEXTAREA') return;
    if (ev.ctrlKey || ev.metaKey || ev.altKey) return;
    var k = ev.key;
    if (k === 'ArrowRight' || k === 'PageDown' || k === ' ') { ev.preventDefault(); mostra(n + 1); }
    else if (k === 'ArrowLeft' || k === 'PageUp') { ev.preventDefault(); mostra(n - 1); }
    else if (k === 'Home') mostra(1);
    else if (k === 'End') mostra(doc ? doc.numPages : 1);
    else if (k === '+' || k === '=') imposta_zoom(zoom + 0.15);
    else if (k === '-') imposta_zoom(zoom - 0.15);
    else if (k === 'f') document.getElementById('schermo').click();
    else if (k === 'b') commutaSegn();
    else if (k === '/') { ev.preventDefault(); apri('cerca'); }
    else if (k === 'Escape') pannello.hidden = true;
  });

  // ---------- touch: scorri per girare pagina, pizzica per ingrandire, doppio tocco ----------
  var inizio = null, dist0 = 0, zoom0 = 1, ultimoTocco = 0;
  function distanza(t) { return Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY); }
  area.addEventListener('touchstart', function (ev) {
    if (ev.touches.length === 2) { dist0 = distanza(ev.touches); zoom0 = zoom; inizio = null; }
    else if (ev.touches.length === 1) { inizio = { x: ev.touches[0].clientX, y: ev.touches[0].clientY, t: Date.now() }; }
  }, { passive: true });
  area.addEventListener('touchmove', function (ev) {
    if (ev.touches.length === 2 && dist0) {
      var r = distanza(ev.touches) / dist0;
      tela.style.transformOrigin = 'center top'; tela.style.transform = 'scale(' + Math.max(0.5 / zoom0, Math.min(3 / zoom0, r)) + ')';
    }
  }, { passive: true });
  area.addEventListener('touchend', function (ev) {
    if (dist0 && ev.touches.length < 2) {
      var r = parseFloat((tela.style.transform.match(/scale\(([\d.]+)\)/) || [0, 1])[1]);
      dist0 = 0; imposta_zoom(zoom0 * r); return;
    }
    if (!inizio || !ev.changedTouches.length) return;
    var dx = ev.changedTouches[0].clientX - inizio.x, dy = ev.changedTouches[0].clientY - inizio.y, dt = Date.now() - inizio.t;
    inizio = null;
    if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5 && zoom <= 1.05) { mostra(n + (dx < 0 ? 1 : -1)); return; }
    if (Math.abs(dx) < 10 && Math.abs(dy) < 10 && dt < 300) {            // doppio tocco = zoom
      var ora = Date.now();
      if (ora - ultimoTocco < 350) { imposta_zoom(zoom > 1.2 ? 1 : 2); ultimoTocco = 0; } else ultimoTocco = ora;
    }
  });
})();
