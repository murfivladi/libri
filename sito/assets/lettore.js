// Lettore: un libro vero con le pagine che si girano (StPageFlip) e le pagine del PDF disegnate da PDF.js.
// Zoom e spostamento sono gestiti qui, con il punto sotto le dita che resta fermo (pizzico, doppio tocco, rotella).
(function () {
  var body = document.body, url = body.dataset.pdf, slug = body.dataset.chiave;
  var scena = document.getElementById('scena'), zoomEl = document.getElementById('zoom'), libro = document.getElementById('libro');
  var stato = document.getElementById('stato'), vai = document.getElementById('vai'), tot = document.getElementById('tot'), coppia = document.getElementById('coppia');

  var doc = null, N = 0, flip = null, base = null;
  var pagine = [], resa = {}, coda = Promise.resolve(), corrente = 0, termine = '';
  var zoom = 1, tx = 0, ty = 0, timerQualita = null, ultimaLarghezza = 0;

  function leggi(k, d) { try { var v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } }
  function scrivi(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function el(t, c, txt) { var x = document.createElement(t); if (c) x.className = c; if (txt !== undefined) x.textContent = txt; return x; }
  function norm(s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function limita(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function onId(id, f) { document.getElementById(id).addEventListener('click', f); }

  if (leggi('tema', 'chiaro') === 'scuro') body.classList.add('scuro');

  // ---------- pagine del PDF ----------
  function larghezzaPagina() { return flip ? flip.getRender().getRect().pageWidth : 400; }
  function scalaRender() { return Math.min(3, (window.devicePixelRatio || 1) * Math.max(1, zoom)); }

  function disegna(i) {
    coda = coda.then(function () {
      if (resa[i] && Math.abs(resa[i] - scalaRender() * larghezzaPagina()) < 4 && !termine) return;
      return doc.getPage(i + 1).then(function (p) {
        var larghezza = Math.min(3200, Math.round(larghezzaPagina() * scalaRender()));
        var vp = p.getViewport({ scale: larghezza / p.getViewport({ scale: 1 }).width });
        var c = pagine[i].canvas;
        c.width = Math.round(vp.width); c.height = Math.round(vp.height);
        return p.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise.then(function () {
          resa[i] = larghezzaPagina() * scalaRender();
          pagine[i].div.classList.add('pronta');
          if (termine) return evidenzia(p, vp, c);
        });
      });
    }).catch(function () {});
    return coda;
  }

  function evidenzia(page, vp, c) {
    return page.getTextContent().then(function (tc) {
      var t = norm(termine); if (!t) return;
      var ctx = c.getContext('2d');
      ctx.save(); ctx.globalCompositeOperation = 'multiply'; ctx.fillStyle = 'rgba(255, 214, 0, 0.55)';
      tc.items.forEach(function (it) {
        var s = norm(it.str), i = s.indexOf(t);
        while (i >= 0 && s.length) {
          var m = pdfjsLib.Util.transform(vp.transform, it.transform);
          var w = it.width * vp.scale, h = Math.hypot(m[2], m[3]);
          ctx.fillRect(m[4] + w * (i / s.length), m[5] - h * 0.85, w * (t.length / s.length), h * 1.05);
          i = s.indexOf(t, i + t.length);
        }
      });
      ctx.restore();
    });
  }

  // pagine da tenere pronte: quella aperta e le vicine (le altre liberano memoria)
  function vicine() {
    var raggio = zoom > 1.3 ? 1 : 3, out = [];      // più vicine prima: la pagina aperta (e la sua gemella) si disegnano subito
    for (var d = 0; d <= raggio + 1; d++) {
      [corrente + d, corrente - d].forEach(function (j) {
        if (j >= 0 && j < N && out.indexOf(j) < 0 && j - corrente <= raggio + 1 && corrente - j <= raggio) out.push(j);
      });
    }
    return out;
  }
  function aggiornaPagine() {
    var v = vicine();
    v.forEach(disegna);
    Object.keys(resa).forEach(function (k) {
      var j = parseInt(k, 10);
      if (v.indexOf(j) < 0) { var c = pagine[j].canvas; c.width = 1; c.height = 1; delete resa[j]; pagine[j].div.classList.remove('pronta'); }
    });
  }
  function ridisegnaTutte() { resa = {}; aggiornaPagine(); }

  // ---------- account: pagina, segnalibri e liste sincronizzati (solo se il lettore è collegato) ----------
  var api = body.dataset.api, csrf = body.dataset.csrf, srv = null, timerProg = null;
  var pStato = api ? fetch(api + '?libro=' + encodeURIComponent(slug), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).catch(function () { return null; }) : Promise.resolve(null);
  function invia(dati) {
    if (!srv) return;
    dati.libro = slug;
    var corpo = Object.keys(dati).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(dati[k]); }).join('&');
    try { fetch(api, { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': csrf }, body: corpo }).catch(function () {}); } catch (e) {}
  }
  function fondiSegnalibri() {            // unisce quelli di questo dispositivo con quelli dell'account
    var loc = []; try { loc = JSON.parse(leggi('segn-' + slug, '[]')) || []; } catch (e) {}
    var tutti = loc.slice(); (srv.segnalibri || []).forEach(function (p) { if (tutti.indexOf(p) < 0) tutti.push(p); });
    tutti.sort(function (a, b) { return a - b; }); scrivi('segn-' + slug, JSON.stringify(tutti));
    var nuovi = loc.filter(function (p) { return (srv.segnalibri || []).indexOf(p) < 0; });
    if (nuovi.length) invia({ azione: 'segnalibri', pagine: nuovi.join(',') });
  }
  function inviaProgresso() { clearTimeout(timerProg); timerProg = null; invia({ azione: 'progresso', pagina: corrente + 1, totale: N }); }
  window.addEventListener('pagehide', function () { if (timerProg) inviaProgresso(); });

  // ---------- avvio ----------
  pdfjsLib.GlobalWorkerOptions.workerSrc = 'assets/vendor/pdf.worker.min.js';
  pdfjsLib.getDocument(url).promise.then(function (d) {
    doc = d; N = d.numPages;
    return d.getPage(1).then(function (p) { base = p.getViewport({ scale: 1 }); });
  }).then(function () { return pStato; }).then(function (st) { srv = st && st.loggato ? st : null; if (srv) fondiSegnalibri(); avvia(); }).catch(function (e) { stato.textContent = 'Impossibile aprire il PDF.'; if (window.console) console.log(e); });

  function avvia() {
    var frag = document.createDocumentFragment();
    for (var i = 0; i < N; i++) {
      var d = el('div', 'pagina-pdf'), c = document.createElement('canvas');
      if (i === 0 || i === N - 1) d.setAttribute('data-density', 'hard');       // copertina e retro rigidi
      d.appendChild(c); pagine.push({ div: d, canvas: c }); frag.appendChild(d);
    }
    libro.appendChild(frag);
    var richiesta = 0; try { richiesta = parseInt(new URLSearchParams(location.search).get('pagina'), 10) || 0; } catch (e) {}
    var inizio = richiesta || (srv && srv.pagina) || parseInt(leggi('pag-' + slug, '1'), 10) || 1;       // indirizzo > account > questo dispositivo
    var salvata = Math.max(0, Math.min(N - 1, inizio - 1));
    flip = new St.PageFlip(libro, {
      width: base.width, height: base.height, size: 'stretch', minWidth: 360, maxWidth: 1100, minHeight: 200, maxHeight: 2600,
      drawShadow: true, flippingTime: 700, usePortrait: true, autoSize: false, maxShadowOpacity: 0.45, showCover: true,
      mobileScrollSupport: false, useMouseEvents: false, disableFlipByClick: true, showPageCorners: true, startPage: salvata, swipeDistance: 30,
    });
    flip.loadFromHTML(libro.querySelectorAll('.pagina-pdf'));
    stato.hidden = true; tot.textContent = N; vai.max = N;
    flip.on('flip', function (e) { cambiata(e.data); });
    flip.on('changeOrientation', function () { setTimeout(function () { ultimaLarghezza = 0; controllaLarghezza(); aggiornaUI(); }, 60); });
    corrente = salvata;
    setTimeout(function () { ultimaLarghezza = larghezzaPagina(); aggiornaPagine(); aggiornaUI(); costruisciIndice(); mostraSegnalibri(); }, 30);
  }

  function cambiata(i) {
    corrente = i; scrivi('pag-' + slug, i + 1);
    if (srv) { clearTimeout(timerProg); timerProg = setTimeout(inviaProgresso, 1500); }
    aggiornaUI(); aggiornaPagine();
  }

  function aggiornaUI() {
    if (!flip) return;
    vai.value = corrente + 1;
    var doppia = flip.getOrientation() === 'landscape' && corrente > 0 && corrente + 1 < N;
    coppia.textContent = doppia ? '–' + (corrente + 2) : '';
    document.querySelector('#progresso i').style.width = (N > 1 ? corrente / (N - 1) * 100 : 100) + '%';
    aggiornaStella();
  }

  function controllaLarghezza() {                       // dopo un ridimensionamento le pagine vanno ridisegnate alla misura nuova
    var w = larghezzaPagina();
    if (!ultimaLarghezza || Math.abs(w - ultimaLarghezza) / Math.max(1, ultimaLarghezza) > 0.12) { ultimaLarghezza = w; ridisegnaTutte(); }
  }
  window.addEventListener('resize', (function () { var t; return function () { clearTimeout(t); t = setTimeout(function () { if (flip) { limita2(); controllaLarghezza(); aggiornaUI(); } }, 250); }; })());

  // ---------- navigazione ----------
  function vaiA(n, animata) {
    if (!flip) return;
    var i = limita(Math.round(n) - 1, 0, N - 1);
    if (animata && Math.abs(i - corrente) <= 2) conComando(function () { flip.flip(i); }); else { flip.turnToPage(i); cambiata(i); }
    if (zoom > 1) reimposta(true);
  }
  // flipNext/flipPrev/flip rispettano "disableFlipByClick" (che serve a non girare la pagina con un tocco): per i comandi nostri lo si abbassa un attimo
  function conComando(f) { var s = flip.getSettings(), v = s.disableFlipByClick; s.disableFlipByClick = false; try { f(); } finally { s.disableFlipByClick = v; } }
  function avanti(alto) { if (flip && corrente < N - 1) conComando(function () { flip.flipNext(alto === 'bottom' ? 'bottom' : 'top'); }); }
  function indietro(alto) { if (flip && corrente > 0) conComando(function () { flip.flipPrev(alto === 'bottom' ? 'bottom' : 'top'); }); }
  onId('avanti', function () { avanti(); }); onId('indietro', function () { indietro(); }); onId('freccia-dx', function () { avanti(); }); onId('freccia-sx', function () { indietro(); });
  vai.addEventListener('change', function () { vaiA(parseInt(vai.value, 10) || 1, false); });
  vai.addEventListener('keydown', function (ev) { ev.stopPropagation(); if (ev.key === 'Enter') { vaiA(parseInt(vai.value, 10) || 1, false); vai.blur(); } });

  // ---------- zoom e spostamento ----------
  function misure() { return { W: scena.clientWidth, H: scena.clientHeight }; }
  function limita2() {
    var m = misure();
    if (zoom <= 1.001) { zoom = 1; tx = 0; ty = 0; return; }
    tx = limita(tx, m.W - m.W * zoom, 0); ty = limita(ty, m.H - m.H * zoom, 0);
  }
  function applica(animata) {
    zoomEl.style.transition = animata ? 'transform .22s ease' : 'none';
    zoomEl.style.transform = (zoom === 1 && !tx && !ty) ? '' : 'translate(' + tx + 'px,' + ty + 'px) scale(' + zoom + ')';
    scena.classList.toggle('zoomato', zoom > 1.02);
  }
  // porta lo zoom a "nz" tenendo fermo il punto (fx, fy), in coordinate della scena
  function zoomaSu(nz, fx, fy, animata) {
    nz = limita(nz, 1, 4);
    var r = nz / zoom;
    tx = fx - (fx - tx) * r; ty = fy - (fy - ty) * r; zoom = nz;
    limita2(); applica(animata); pianificaQualita();
  }
  function reimposta(animata) { zoom = 1; tx = 0; ty = 0; applica(animata); pianificaQualita(); }
  function centro() { var m = misure(); return { x: m.W / 2, y: m.H / 2 }; }
  function pianificaQualita() { clearTimeout(timerQualita); timerQualita = setTimeout(ridisegnaTutte, 260); }   // ridisegna più nitido (o più leggero)
  function puntoScena(cx, cy) { var r = scena.getBoundingClientRect(); return { x: cx - r.left, y: cy - r.top }; }

  onId('piu', function () { var c = centro(); zoomaSu(zoom * 1.3, c.x, c.y, true); });
  onId('meno', function () { var c = centro(); zoomaSu(zoom / 1.3, c.x, c.y, true); });
  onId('adatta', function () { reimposta(true); });

  // ---------- gesti: trascinare una pagina, pizzicare, doppio tocco, rotella ----------
  var puntatori = {}, gesto = null, inizio = null, pizzico = null, ultimoTocco = null, ultimaRotella = 0;
  function nPuntatori() { return Object.keys(puntatori).length; }
  function posLibro(cx, cy) { var r = flip.getUI().getDistElement().getBoundingClientRect(); return { x: cx - r.left, y: cy - r.top }; }
  function ids2() { var k = Object.keys(puntatori); return [puntatori[k[0]], puntatori[k[1]]]; }
  function distanza(a, b) { return Math.hypot(a.x - b.x, a.y - b.y); }

  scena.addEventListener('pointerdown', function (ev) {
    if (!flip || ev.target.closest('button, a, input')) return;
    if (ev.pointerType === 'mouse' && ev.button !== 0) return;
    try { scena.setPointerCapture(ev.pointerId); } catch (e) {}
    puntatori[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
    if (nPuntatori() === 2) { avviaPizzico(); return; }
    if (nPuntatori() > 2) return;
    inizio = { x: ev.clientX, y: ev.clientY, t: Date.now(), tx: tx, ty: ty, mosso: false, tipo: ev.pointerType };
    if (zoom > 1.02) gesto = 'sposta';
    else { gesto = 'pagina'; flip.startUserTouch(posLibro(ev.clientX, ev.clientY)); }
  });

  scena.addEventListener('pointermove', function (ev) {
    if (!flip) return;
    if (puntatori[ev.pointerId]) { puntatori[ev.pointerId].x = ev.clientX; puntatori[ev.pointerId].y = ev.clientY; }
    if (gesto === 'pizzico') { aggiornaPizzico(); return; }
    if (!inizio) {                                         // il mouse passa sopra il libro: l'angolo della pagina si alza un po'
      if (ev.pointerType === 'mouse' && zoom <= 1.02 && !ev.target.closest('button, a, input')) flip.userMove(posLibro(ev.clientX, ev.clientY), false);
      return;
    }
    var dx = ev.clientX - inizio.x, dy = ev.clientY - inizio.y;
    if (Math.hypot(dx, dy) > 6) inizio.mosso = true;
    if (gesto === 'sposta') { tx = inizio.tx + dx; ty = inizio.ty + dy; limita2(); applica(false); }
    else if (gesto === 'pagina') flip.userMove(posLibro(ev.clientX, ev.clientY), ev.pointerType !== 'mouse');
  });

  function fineGesto(ev, annullato) {
    if (!puntatori[ev.pointerId]) return;
    var ultimo = puntatori[ev.pointerId];
    delete puntatori[ev.pointerId];
    if (gesto === 'pizzico') {
      if (nPuntatori() === 0) { finePizzico(); gesto = null; inizio = null; }
      else if (nPuntatori() === 1) {                       // resta un dito: si continua a spostare l'ingrandimento
        finePizzico();
        var r = ids2()[0] || puntatori[Object.keys(puntatori)[0]];
        inizio = { x: r.x, y: r.y, t: Date.now(), tx: tx, ty: ty, mosso: true, tipo: 'touch' };
        gesto = zoom > 1.02 ? 'sposta' : null;
        if (!gesto) inizio = null;
      }
      return;
    }
    if (!inizio) return;
    var dx = ultimo.x - inizio.x, dy = ultimo.y - inizio.y, durata = Date.now() - inizio.t;
    var fermo = !inizio.mosso && durata < 350;
    if (gesto === 'pagina') {
      var pos = posLibro(ultimo.x, ultimo.y);
      if (!annullato && Math.abs(dx) > 30 && Math.abs(dy) < 70 && durata < 250 && inizio.tipo !== 'mouse') {   // strisciata veloce = pagina girata
        flip.userStop(pos, true);
        var alto = posLibro(inizio.x, inizio.y).y < flip.getRender().getRect().height / 2 ? 'top' : 'bottom';
        if (dx > 0) indietro(alto); else avanti(alto);
      } else flip.userStop(pos);
    }
    if (fermo && !annullato) {
      var ora = Date.now(), p = puntoScena(ultimo.x, ultimo.y);
      if (ultimoTocco && ora - ultimoTocco.t < 330 && Math.hypot(p.x - ultimoTocco.x, p.y - ultimoTocco.y) < 34) {   // doppio tocco / doppio clic
        if (zoom > 1.2) reimposta(true); else zoomaSu(2.4, p.x, p.y, true);
        ultimoTocco = null;
      } else ultimoTocco = { t: ora, x: p.x, y: p.y };
    }
    gesto = null; inizio = null;
  }
  scena.addEventListener('pointerup', function (ev) { fineGesto(ev, false); });
  scena.addEventListener('pointercancel', function (ev) { fineGesto(ev, true); });

  function avviaPizzico() {
    if (gesto === 'pagina' && inizio) flip.userStop(posLibro(inizio.x, inizio.y), true);       // annulla il trascinamento di pagina
    var a = ids2(), c = { x: (a[0].x + a[1].x) / 2, y: (a[0].y + a[1].y) / 2 };
    pizzico = { d0: Math.max(10, distanza(a[0], a[1])), z0: zoom, tx0: tx, ty0: ty, f0: puntoScena(c.x, c.y) };
    gesto = 'pizzico'; inizio = null;
  }
  function aggiornaPizzico() {
    var a = ids2(); if (!a[0] || !a[1]) return;
    var c = puntoScena((a[0].x + a[1].x) / 2, (a[0].y + a[1].y) / 2);
    var nz = limita(pizzico.z0 * distanza(a[0], a[1]) / pizzico.d0, 1, 4), r = nz / pizzico.z0;
    // il punto del libro che stava sotto le dita all'inizio segue le dita (anche se si spostano)
    tx = c.x - (pizzico.f0.x - pizzico.tx0) * r; ty = c.y - (pizzico.f0.y - pizzico.ty0) * r; zoom = nz;
    limita2(); applica(false);
  }
  function finePizzico() {
    if (zoom < 1.05) reimposta(true); else { limita2(); applica(false); pianificaQualita(); }
    pizzico = null;
  }

  scena.addEventListener('wheel', function (ev) {
    if (!flip) return;
    ev.preventDefault();
    var p = puntoScena(ev.clientX, ev.clientY);
    if (ev.ctrlKey || ev.metaKey) { zoomaSu(zoom * Math.exp(-ev.deltaY * 0.01), p.x, p.y, false); return; }
    if (zoom > 1.02) { tx -= ev.deltaX; ty -= ev.deltaY; limita2(); applica(false); return; }
    var ora = Date.now();
    if (ora - ultimaRotella > 650 && Math.abs(ev.deltaY) + Math.abs(ev.deltaX) > 8) { ultimaRotella = ora; if (ev.deltaY + ev.deltaX > 0) avanti(); else indietro(); }
  }, { passive: false });

  // ---------- tema, schermo intero, tastiera ----------
  onId('tema', function () { body.classList.toggle('scuro'); scrivi('tema', body.classList.contains('scuro') ? 'scuro' : 'chiaro'); });
  onId('schermo', function () {
    if (document.fullscreenElement) document.exitFullscreen();
    else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen();
  });
  document.addEventListener('keydown', function (ev) {
    var t = ev.target.tagName;
    if (t === 'INPUT' || t === 'TEXTAREA' || ev.ctrlKey || ev.metaKey || ev.altKey) return;
    var k = ev.key, c = centro();
    if (zoom > 1.02 && (k === 'ArrowLeft' || k === 'ArrowRight' || k === 'ArrowUp' || k === 'ArrowDown')) {
      ev.preventDefault();
      tx += k === 'ArrowLeft' ? 80 : k === 'ArrowRight' ? -80 : 0; ty += k === 'ArrowUp' ? 80 : k === 'ArrowDown' ? -80 : 0;
      limita2(); applica(true); return;
    }
    if (k === 'ArrowRight' || k === 'PageDown' || k === ' ') { ev.preventDefault(); avanti(); }
    else if (k === 'ArrowLeft' || k === 'PageUp') { ev.preventDefault(); indietro(); }
    else if (k === 'Home') vaiA(1, false);
    else if (k === 'End') vaiA(N, false);
    else if (k === '+' || k === '=') zoomaSu(zoom * 1.3, c.x, c.y, true);
    else if (k === '-') zoomaSu(zoom / 1.3, c.x, c.y, true);
    else if (k === '0') reimposta(true);
    else if (k === 'f') document.getElementById('schermo').click();
    else if (k === 'b') commutaSegn();
    else if (k === '/') { ev.preventDefault(); apri('cerca'); }
    else if (k === 'Escape') { if (zoom > 1.02) reimposta(true); else pannello.hidden = true; }
  });

  // ---------- pannello laterale: indice, segnalibri, ricerca ----------
  var pannello = document.getElementById('pannello');
  function apri(tab) {
    pannello.hidden = false;
    document.querySelectorAll('.schede-pannello [data-tab]').forEach(function (b) { b.classList.toggle('att', b.dataset.tab === tab); });
    document.querySelectorAll('#pannello .tab').forEach(function (t) { t.hidden = t.id !== 'tab-' + tab; });
    if (tab === 'cerca') setTimeout(function () { document.getElementById('testo-cerca').focus(); }, 50);
  }
  onId('b-pannello', function () { if (pannello.hidden) apri('indice'); else pannello.hidden = true; });
  onId('chiudi-pannello', function () { pannello.hidden = true; });
  document.querySelectorAll('.schede-pannello [data-tab]').forEach(function (b) { b.addEventListener('click', function () { apri(b.dataset.tab); }); });
  function vaiDaPannello(p) { vaiA(p, false); if (window.innerWidth < 700) pannello.hidden = true; }

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
            d.then(function (dest) { if (dest) return doc.getPageIndex(dest[0]); }).then(function (i) { if (i !== undefined) vaiDaPannello(i + 1); });
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
    for (var i = 1; i <= N; i++) (function (i) { var b = el('button', '', String(i)); b.addEventListener('click', function () { vaiDaPannello(i); }); g.appendChild(b); })(i);
    box.appendChild(g);
  }

  function segnalibri() { try { return JSON.parse(leggi('segn-' + slug, '[]')) || []; } catch (e) { return []; } }
  function salvaSegn(l) { l.sort(function (a, b) { return a - b; }); scrivi('segn-' + slug, JSON.stringify(l)); aggiornaStella(); mostraSegnalibri(); }
  function aggiornaStella() { var b = document.getElementById('b-segna'); var s = segnalibri().indexOf(corrente + 1) >= 0; b.textContent = s ? '★' : '☆'; b.classList.toggle('attivo', s); }
  function commutaSegn() { var l = segnalibri(), p = corrente + 1, i = l.indexOf(p); if (i >= 0) l.splice(i, 1); else l.push(p); salvaSegn(l); invia({ azione: 'segnalibro', pagina: p, presente: i >= 0 ? 0 : 1 }); }
  onId('b-segna', commutaSegn);
  function mostraSegnalibri() {
    var box = document.getElementById('tab-segnalibri'); box.textContent = '';
    var l = segnalibri();
    box.appendChild(el('p', 'muto', l.length ? 'Tocca per tornare a una pagina.' : 'Nessun segnalibro. Premi ☆ nella barra per aggiungerne uno.'));
    if (!srv && body.dataset.accedi) { var nota = el('p', 'muto'); nota.appendChild(document.createTextNode('Con un account ritrovi pagina e segnalibri su ogni dispositivo. ')); var aa = el('a', '', 'Accedi'); aa.href = body.dataset.accedi; nota.appendChild(aa); box.appendChild(nota); }
    var ul = el('ul', 'elenco-pannello');
    l.forEach(function (p) {
      var li = el('li', 'riga-segn'), b = el('button', 'voce', 'Pagina ' + p);
      b.addEventListener('click', function () { vaiDaPannello(p); });
      var x = el('button', 'x', '✕'); x.setAttribute('aria-label', 'Togli segnalibro');
      x.addEventListener('click', function () { salvaSegn(segnalibri().filter(function (q) { return q !== p; })); invia({ azione: 'segnalibro', pagina: p, presente: 0 }); });
      li.appendChild(b); li.appendChild(x); ul.appendChild(li);
    });
    box.appendChild(ul);
  }

  var testi = null;
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
    ul.textContent = ''; termine = ''; ridisegnaTutte();
    if (q.length < 2) { st.textContent = 'Scrivi almeno 2 lettere.'; return; }
    if (!doc) return;
    testi = testi || {}; var nq = norm(q), trovati = 0, i = 1;
    (function passo() {
      if (i > N || trovati >= 200) { st.textContent = trovati ? trovati + ' risultati' : 'Nessun risultato.'; return; }
      st.textContent = 'Cerco… pagina ' + i + ' di ' + N;
      testoPagina(i).then(function (t) {
        var nt = norm(t), pos = nt.indexOf(nq), pagina = i;
        while (pos >= 0 && trovati < 200) {
          var a = Math.max(0, pos - 30), frase = t.substring(a, pos + q.length + 40).replace(/\s+/g, ' ');
          var li = el('li'), b = el('button', 'voce');
          b.appendChild(el('b', '', 'p. ' + pagina + ' '));
          b.appendChild(document.createTextNode('…' + frase + '…'));
          b.addEventListener('click', function (p) { return function () { termine = q; ridisegnaTutte(); vaiDaPannello(p); }; }(pagina));
          li.appendChild(b); ul.appendChild(li); trovati++;
          pos = nt.indexOf(nq, pos + nq.length);
        }
        i++; setTimeout(passo, 0);
      });
    })();
    termine = q; ridisegnaTutte();
  });
})();
