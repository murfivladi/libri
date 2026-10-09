// Lavagna di Vlad Editore Scuola: editor di video didattici.
// Due modi di fare un video:
//  1) «Passi»: si prepara la lavagna passo dopo passo; nel video gli oggetti compaiono (scritti, disegnati, in dissolvenza…)
//     e si spostano da soli da un passo all'altro, con musica e, se vuoi, la tua voce registrata sopra.
//  2) «Dal vivo»: si registra tutto quello che fai sulla lavagna (scrivi, sposti, disegni, laser) con la voce dal microfono.
// La tela è sempre 1920×1080 (Full HD); sopra c'è una seconda tela per maniglie e guide, che non finisce nel video.
(function () {
  'use strict';
  var CFG = window.LAVAGNA, W = 1920, H = 1080;
  var tela = document.getElementById('lv-tela'), ctx = tela.getContext('2d');
  var sopra = document.getElementById('lv-sopra'), sctx = sopra.getContext('2d');
  var box = document.getElementById('lv-tela-box'), scena = document.getElementById('lv-scena');

  // ---------- utilità ----------
  function $(id) { return document.getElementById(id); }
  function el(tag, cls, testo) { var x = document.createElement(tag); if (cls) x.className = cls; if (testo !== undefined) x.textContent = testo; return x; }
  function clona(o) { return JSON.parse(JSON.stringify(o)); }
  function limita(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function lerp(a, b, t) { return a + (b - a) * t; }
  function nuovoId() { return 'o' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }
  var EASE = {
    morbida: function (t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; },
    lineare: function (t) { return t; },
    rimbalzo: function (t) { var c1 = 1.70158, c3 = c1 + 1; return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2); },
    scatto: function (t) { return t < 1 ? 0 : 1; },
  };

  // ---------- progetto ----------
  function progettoVuoto() {
    return { versione: 1, w: W, h: H, sfondo: { tipo: 'lavagna', colore: '#1f4d3a', img: '' }, griglia: false,
      oggetti: [], passi: [{ durata: 3, seq: true, trans: 'morbida', stato: {} }], audio: { src: '', titolo: '', volume: 0.5 } };
  }
  var P = CFG.dati && CFG.dati.oggetti ? CFG.dati : progettoVuoto();
  var idLavagna = CFG.id, passo = 0, strumento = 'seleziona', scelti = [], modificato = false;
  var stile = { col: chiaro() ? '#1d2433' : '#ffffff', sp: 6, fs: 64, font: 'gesso', grassetto: false, riempi: '', tratteggio: false, evid: '#ffe14d' };
  $('lv-titolo').value = CFG.titolo || '';

  function chiaro() { return ['bianco', 'quadretti', 'righe', 'carta'].indexOf(P.sfondo.tipo) >= 0 || (P.sfondo.tipo === 'colore' && luminosita(P.sfondo.colore) > 0.6); }
  function luminosita(hex) { var n = parseInt(String(hex || '#000').slice(1), 16); return ((n >> 16 & 255) * 0.299 + (n >> 8 & 255) * 0.587 + (n & 255) * 0.114) / 255; }
  function ogg(id) { for (var i = 0; i < P.oggetti.length; i++) if (P.oggetti[i].id === id) return P.oggetti[i]; return null; }
  function st(k, id) { return P.passi[k] && P.passi[k].stato[id]; }

  // ---------- annulla / ripeti ----------
  var storia = [], futuro = [], ultimo = JSON.stringify(P);
  function segna() {                       // da chiamare DOPO ogni modifica completa
    var ora = JSON.stringify(P);
    if (ora === ultimo) return;
    storia.push(ultimo); if (storia.length > 150) storia.shift();
    futuro = []; ultimo = ora; modificato = true; statoSalvataggio('modifiche non salvate'); pianificaMiniature(); salvaBozzaLocale();
  }
  function annulla() { if (!storia.length) return; futuro.push(ultimo); ultimo = storia.pop(); P = JSON.parse(ultimo); dopoCambioProgetto(); }
  function ripeti() { if (!futuro.length) return; storia.push(ultimo); ultimo = futuro.pop(); P = JSON.parse(ultimo); dopoCambioProgetto(); }
  function dopoCambioProgetto() {
    passo = limita(passo, 0, P.passi.length - 1);
    scelti = scelti.filter(function (id) { var s = st(passo, id); return ogg(id) && s && s.v; });
    modificato = true; statoSalvataggio('modifiche non salvate'); immaginiCaricate = {}; caricaImmagini(); aggiornaTutto();
  }

  // ---------- immagini (personaggi, foto) ----------
  var cacheImg = {}, immaginiCaricate = {};
  function immagine(src) {
    if (!cacheImg[src]) { var i = new Image(); i.crossOrigin = 'anonymous'; i.onload = function () { ridisegna(); }; i.src = src; cacheImg[src] = i; }
    return cacheImg[src];
  }
  function caricaImmagini() { P.oggetti.forEach(function (o) { if (o.tipo === 'immagine') immagine(o.src); }); if (P.sfondo.img) immagine(P.sfondo.img); }

  // ---------- geometria degli oggetti (coordinate locali attorno a 0,0) ----------
  function bboxLocale(o) {
    switch (o.tipo) {
      case 'tratto': var x1 = 1e9, y1 = 1e9, x2 = -1e9, y2 = -1e9; o.pts.forEach(function (p) { x1 = Math.min(x1, p[0]); y1 = Math.min(y1, p[1]); x2 = Math.max(x2, p[0]); y2 = Math.max(y2, p[1]); });
        var m = o.sp / 2 + 2; return { x: x1 - m, y: y1 - m, w: x2 - x1 + 2 * m, h: y2 - y1 + 2 * m };
      case 'testo': case 'etichetta': var d = misuraTesto(o); return { x: -d.w / 2, y: -d.h / 2, w: d.w, h: d.h };
      case 'immagine': return { x: -o.w / 2, y: -o.h / 2, w: o.w, h: o.h };
      case 'forma':
        if (o.f === 'linea' || o.f === 'freccia' || o.f === 'curva' || o.f === 'doppia') {
          var xs = [o.x1, o.x2].concat(o.f === 'curva' ? [o.cx] : []), ys = [o.y1, o.y2].concat(o.f === 'curva' ? [o.cy] : []);
          var mm = o.sp * 2 + 6; return { x: Math.min.apply(0, xs) - mm, y: Math.min.apply(0, ys) - mm, w: Math.max.apply(0, xs) - Math.min.apply(0, xs) + 2 * mm, h: Math.max.apply(0, ys) - Math.min.apply(0, ys) + 2 * mm };
        }
        return { x: -o.w / 2 - o.sp / 2, y: -o.h / 2 - o.sp / 2 - (o.f === 'fumetto' ? 0 : 0), w: o.w + o.sp, h: o.h + o.sp + (o.f === 'fumetto' ? o.h * 0.35 : 0) };
    }
    return { x: -10, y: -10, w: 20, h: 20 };
  }
  var FONT = { gesso: '"Comic Sans MS","Chalkboard SE","Marker Felt","Segoe Print",cursive', sans: 'system-ui,"Segoe UI",Roboto,Arial,sans-serif', serif: 'Georgia,"Times New Roman",serif', mono: 'ui-monospace,Consolas,monospace' };
  function fontDi(o) { return (o.bold ? '700 ' : '400 ') + o.fs + 'px ' + (FONT[o.font] || FONT.sans); }
  function righe(o) { return String(o.t || '').split('\n'); }
  function misuraTesto(o) {
    ctx.save(); ctx.font = fontDi(o);
    var r = righe(o), w = 0; r.forEach(function (x) { w = Math.max(w, ctx.measureText(x).width); });
    ctx.restore();
    var h = r.length * o.fs * 1.25, pad = o.tipo === 'etichetta' ? o.fs * 0.35 : (o.sfondo ? o.fs * 0.25 : 0);
    return { w: Math.max(o.fs * 0.6, w) + pad * 2, h: h + pad * 2 + (o.tipo === 'etichetta' ? o.fs * 0.55 : 0), pad: pad };
  }

  // stato visivo di un oggetto: x, y, s (scala), r (gradi), o (opacità 0–1), v (visibile), p (quanto è «disegnato» 0–1)
  function disegnaOggetto(c, o, s) {
    if (!s || s.o <= 0.003) return;
    c.save();
    c.globalAlpha = limita(s.o, 0, 1);
    c.translate(s.x, s.y); c.rotate((s.r || 0) * Math.PI / 180); c.scale(s.s || 1, s.s || 1);
    var p = s.p === undefined ? 1 : s.p;
    switch (o.tipo) {
      case 'tratto': disegnaTratto(c, o, p); break;
      case 'testo': case 'etichetta': disegnaTesto(c, o, p); break;
      case 'immagine': var im = immagine(o.src); if (o.specchio) c.scale(-1, 1); if (im.complete && im.naturalWidth) c.drawImage(im, -o.w / 2, -o.h / 2, o.w, o.h); break;
      case 'forma': disegnaForma(c, o, p); break;
    }
    c.restore();
  }
  function disegnaTratto(c, o, p) {
    var n = o.pts.length; if (!n) return;
    var fino = Math.max(1, Math.ceil(n * p));
    c.lineCap = 'round'; c.lineJoin = 'round'; c.strokeStyle = o.col;
    if (o.evid) { c.globalAlpha *= 0.42; c.globalCompositeOperation = chiaro() ? 'multiply' : 'screen'; c.lineCap = 'butt'; }
    if (n === 1) { c.fillStyle = o.col; c.beginPath(); c.arc(o.pts[0][0], o.pts[0][1], o.sp / 2, 0, 7); c.fill(); return; }
    // spessore variabile con la pressione della penna: tratti brevi uniti
    var varia = !o.evid && o.pts.some(function (q) { return q[2] && Math.abs(q[2] - 0.5) > 0.05; });
    if (!varia) {
      c.lineWidth = o.sp; c.beginPath(); c.moveTo(o.pts[0][0], o.pts[0][1]);
      for (var i = 1; i < fino; i++) {
        var a = o.pts[i - 1], b = o.pts[i];
        c.quadraticCurveTo(a[0], a[1], (a[0] + b[0]) / 2, (a[1] + b[1]) / 2);
      }
      c.lineTo(o.pts[fino - 1][0], o.pts[fino - 1][1]); c.stroke(); return;
    }
    for (var j = 1; j < fino; j++) {
      var q1 = o.pts[j - 1], q2 = o.pts[j];
      c.lineWidth = o.sp * (0.35 + 1.3 * ((q1[2] || 0.5) + (q2[2] || 0.5)) / 2);
      c.beginPath(); c.moveTo(q1[0], q1[1]); c.lineTo(q2[0], q2[1]); c.stroke();
    }
  }
  function disegnaTesto(c, o, p) {
    var d = misuraTesto(o), r = righe(o), tot = String(o.t || '').length, mostra = Math.ceil(tot * p);
    if (o.tipo === 'etichetta') {
      var lh = o.fs * 0.55, top = -d.h / 2 + lh;
      c.fillStyle = o.cat_col || '#2e7d32'; c.font = '700 ' + Math.round(o.fs * 0.42) + 'px ' + FONT.sans; c.textAlign = 'center'; c.textBaseline = 'alphabetic';
      if (p >= 0.999 || p > 0.5) c.fillText(String(o.cat || '').toUpperCase(), 0, top - lh * 0.25);
      c.strokeStyle = o.cat_col || '#2e7d32'; c.lineWidth = Math.max(3, o.fs * 0.07);
      tondo(c, -d.w / 2, top, d.w, d.h - lh, o.fs * 0.25);
      c.save(); var per = 2 * (d.w + d.h - lh); c.setLineDash([per * Math.min(1, p * 1.3), per]); c.stroke(); c.restore();
      c.globalAlpha *= 0.14; c.fillStyle = o.cat_col || '#2e7d32'; c.fill(); c.globalAlpha /= 0.14;
      c.translate(0, lh / 2);
      d = { w: d.w, h: d.h - lh, pad: d.pad };
    } else if (o.sfondo) {
      c.fillStyle = o.sfondo; tondo(c, -d.w / 2, -d.h / 2, d.w, d.h, o.fs * 0.2); c.fill();
    }
    c.font = fontDi(o); c.fillStyle = o.col; c.textBaseline = 'middle';
    var al = o.allinea || 'center'; c.textAlign = al;
    var x = al === 'left' ? -d.w / 2 + d.pad : al === 'right' ? d.w / 2 - d.pad : 0;
    var y0 = -(r.length - 1) * o.fs * 1.25 / 2, usati = 0;
    r.forEach(function (riga, i) {
      var resto = mostra - usati; usati += riga.length + 1;
      if (resto <= 0) return;
      c.fillText(riga.slice(0, resto), x, y0 + i * o.fs * 1.25);
    });
    if (o.sotto) {                                       // sottolineatura (come a scuola: soggetto, predicato…)
      c.strokeStyle = o.sotto; c.lineWidth = Math.max(3, o.fs / 12); c.beginPath();
      var yy = y0 + (r.length - 1) * o.fs * 1.25 + o.fs * 0.62, ww = (d.w - 2 * d.pad) * p;
      c.moveTo(x - (al === 'center' ? (d.w - 2 * d.pad) / 2 : al === 'right' ? d.w - 2 * d.pad : 0), yy);
      c.lineTo(x - (al === 'center' ? (d.w - 2 * d.pad) / 2 : al === 'right' ? d.w - 2 * d.pad : 0) + ww, yy); c.stroke();
    }
  }
  function tondo(c, x, y, w, h, r) { r = Math.min(r, w / 2, h / 2); c.beginPath(); c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r); c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath(); }
  function pathForma(c, o) {
    c.beginPath();
    var w = o.w, h = o.h;
    switch (o.f) {
      case 'rett': tondo(c, -w / 2, -h / 2, w, h, o.arrot ? Math.min(w, h) * 0.18 : 0); return 2 * (w + h);
      case 'ellisse': c.ellipse(0, 0, Math.abs(w / 2), Math.abs(h / 2), 0, 0, Math.PI * 2); return Math.PI * (w + h) / 2;
      case 'fumetto':
        tondo(c, -w / 2, -h / 2, w, h, Math.min(w, h) * 0.3);
        c.moveTo(-w * 0.18, h / 2 - 1); c.lineTo(-w * 0.3, h / 2 + h * 0.32); c.lineTo(-w * 0.02, h / 2 - 1);
        return 2 * (w + h) + h;
      case 'stella':
        for (var i = 0; i < 10; i++) { var a = -Math.PI / 2 + i * Math.PI / 5, rr = i % 2 ? 0.42 : 1; c[i ? 'lineTo' : 'moveTo'](Math.cos(a) * w / 2 * rr, Math.sin(a) * h / 2 * rr); }
        c.closePath(); return 3 * (w + h);
      case 'linea': case 'freccia': case 'doppia': c.moveTo(o.x1, o.y1); c.lineTo(o.x2, o.y2); return Math.hypot(o.x2 - o.x1, o.y2 - o.y1);
      case 'curva': c.moveTo(o.x1, o.y1); c.quadraticCurveTo(o.cx, o.cy, o.x2, o.y2); return Math.hypot(o.cx - o.x1, o.cy - o.y1) + Math.hypot(o.x2 - o.cx, o.y2 - o.cy);
    }
    return 0;
  }
  function punta(c, x1, y1, x2, y2, sp, col) {
    var a = Math.atan2(y2 - y1, x2 - x1), l = Math.max(18, sp * 4.2);
    c.fillStyle = col; c.beginPath(); c.moveTo(x2, y2);
    c.lineTo(x2 - l * Math.cos(a - 0.45), y2 - l * Math.sin(a - 0.45)); c.lineTo(x2 - l * Math.cos(a + 0.45), y2 - l * Math.sin(a + 0.45)); c.closePath(); c.fill();
  }
  function disegnaForma(c, o, p) {
    c.lineWidth = o.sp; c.strokeStyle = o.col; c.lineCap = 'round'; c.lineJoin = 'round';
    var lung = pathForma(c, o);
    if (o.riempi && ['linea', 'freccia', 'curva', 'doppia'].indexOf(o.f) < 0) { c.save(); c.globalAlpha *= p; c.fillStyle = o.riempi; c.fill(); c.restore(); }
    if (o.tratteggio) c.setLineDash(p < 1 ? [] : [o.sp * 3, o.sp * 2.2]);
    if (p < 1) c.setLineDash([lung * p, lung + 10]);
    if (o.sp > 0) c.stroke();
    c.setLineDash([]);
    if (p >= 0.98 && (o.f === 'freccia' || o.f === 'doppia')) { punta(c, o.x1, o.y1, o.x2, o.y2, o.sp, o.col); if (o.f === 'doppia') punta(c, o.x2, o.y2, o.x1, o.y1, o.sp, o.col); }
    if (p >= 0.98 && o.f === 'curva') punta(c, o.cx, o.cy, o.x2, o.y2, o.sp, o.col);
  }

  // ---------- sfondo ----------
  var grana = null;
  function disegnaSfondo(c) {
    var s = P.sfondo, col = { lavagna: '#1f4d3a', nera: '#22272c', bianco: '#ffffff', quadretti: '#ffffff', righe: '#fffdf6', carta: '#fbf3df', blu: '#13305e' }[s.tipo] || s.colore || '#1f4d3a';
    c.fillStyle = col; c.fillRect(0, 0, W, H);
    if (s.tipo === 'lavagna' || s.tipo === 'nera') {                // grana del gesso, sempre uguale (generata una volta)
      if (!grana) {
        grana = document.createElement('canvas'); grana.width = 480; grana.height = 270; var g = grana.getContext('2d'), seme = 7;
        function caso() { seme = (seme * 16807) % 2147483647; return seme / 2147483647; }
        for (var i = 0; i < 2600; i++) { g.fillStyle = 'rgba(255,255,255,' + (caso() * 0.05) + ')'; g.fillRect(caso() * 480, caso() * 270, 1 + caso() * 2, 1); }
      }
      c.drawImage(grana, 0, 0, W, H);
      c.strokeStyle = 'rgba(0,0,0,.35)'; c.lineWidth = 26; c.strokeRect(0, 0, W, H);
    }
    if (s.tipo === 'quadretti') { c.strokeStyle = '#cfe0f5'; c.lineWidth = 2; c.beginPath(); for (var x = 0; x <= W; x += 40) { c.moveTo(x, 0); c.lineTo(x, H); } for (var y = 0; y <= H; y += 40) { c.moveTo(0, y); c.lineTo(W, y); } c.stroke(); }
    if (s.tipo === 'righe') { c.strokeStyle = '#c9daf0'; c.lineWidth = 2; c.beginPath(); for (var yy = 120; yy < H; yy += 60) { c.moveTo(0, yy); c.lineTo(W, yy); } c.stroke(); c.strokeStyle = '#f2a7a7'; c.beginPath(); c.moveTo(140, 0); c.lineTo(140, H); c.stroke(); }
    if (s.tipo === 'immagine' && s.img) { var im = immagine(s.img); if (im.complete && im.naturalWidth) { var r = Math.max(W / im.naturalWidth, H / im.naturalHeight); c.drawImage(im, (W - im.naturalWidth * r) / 2, (H - im.naturalHeight * r) / 2, im.naturalWidth * r, im.naturalHeight * r); } }
  }

  // ---------- tempo: dai passi allo stato di ogni oggetto ----------
  function durataEntrata(o) {
    var e = (o.e && o.e.tipo) || 'auto';
    if (e === 'nessuna') return 0;
    if (o.e && o.e.d) return o.e.d;
    if ((e === 'auto' || e === 'scrivi') && (o.tipo === 'testo' || o.tipo === 'etichetta')) return limita(String(o.t || '').length * 0.06, 0.5, 4);
    if ((e === 'auto' || e === 'scrivi') && o.tipo === 'tratto') return limita(o.pts.length * 0.012, 0.35, 3);
    return 0.7;
  }
  function tipoEntrata(o) { var e = (o.e && o.e.tipo) || 'auto'; return e === 'auto' ? (o.tipo === 'immagine' ? 'dissolvenza' : 'scrivi') : e; }
  // piano del passo k: quando entra ciascun oggetto e quanto dura il passo
  function pianoPasso(k) {
    var ps = P.passi[k], prima = k > 0 ? P.passi[k - 1].stato : {}, ritardi = {}, t = 0, movimento = 0, mx = 0;
    P.oggetti.forEach(function (o) {
      var a = prima[o.id], b = ps.stato[o.id];
      if (!b) return;
      if (b.v && (!a || !a.v)) { var d = durataEntrata(o); if (ps.seq) { ritardi[o.id] = t; t += d * 0.92; } else { ritardi[o.id] = 0; mx = Math.max(mx, d); } }
      else if (a && a.v && b.v && (a.x !== b.x || a.y !== b.y || a.s !== b.s || a.r !== b.r || a.o !== b.o)) movimento = 1;
    });
    var serve = Math.max(ps.seq ? t : mx, movimento ? 0.9 : 0) + 0.3;
    return { ritardi: ritardi, durata: Math.max(ps.durata || 0, serve), movimento: movimento ? Math.min(1.2, Math.max(0.5, (ps.durata || 1) * 0.6)) : 0 };
  }
  function durataTotale() { var t = 0; for (var k = 0; k < P.passi.length; k++) t += pianoPasso(k).durata; return t; }
  function inizioPasso(k) { var t = 0; for (var i = 0; i < k; i++) t += pianoPasso(i).durata; return t; }
  // stato di tutti gli oggetti al tempo t (secondi dall'inizio)
  function statiAl(t) {
    var k = 0, t0 = 0, pp;
    for (; k < P.passi.length; k++) { pp = pianoPasso(k); if (t < t0 + pp.durata || k === P.passi.length - 1) break; t0 += pp.durata; }
    var dt = t - t0, ps = P.passi[k], prima = k > 0 ? P.passi[k - 1].stato : {}, ease = EASE[ps.trans] || EASE.morbida, out = {};
    P.oggetti.forEach(function (o) {
      var a = prima[o.id], b = ps.stato[o.id];
      if (!b) return;
      var av = a && a.v, bv = b.v;
      if (av && bv) {
        var u = pp.movimento ? ease(limita(dt / pp.movimento, 0, 1)) : 1;
        out[o.id] = { x: lerp(a.x, b.x, u), y: lerp(a.y, b.y, u), s: lerp(a.s, b.s, u), r: lerp(a.r, b.r, u), o: lerp(a.o, b.o, u), v: 1, p: 1 };
      } else if (bv) {
        var d = durataEntrata(o), r = pp.ritardi[o.id] || 0, q = d ? limita((dt - r) / d, 0, 1) : (dt >= r ? 1 : 0);
        out[o.id] = effettoEntrata(o, b, q);
      } else if (av) {
        var w = limita(dt / 0.5, 0, 1);
        if (w < 1) out[o.id] = { x: a.x, y: a.y, s: a.s, r: a.r, o: a.o * (1 - w), v: 1, p: 1 };
      }
    });
    return { stati: out, passo: k };
  }
  function effettoEntrata(o, b, q) {
    var s = { x: b.x, y: b.y, s: b.s, r: b.r, o: b.o, v: 1, p: 1 }, e = tipoEntrata(o), u = EASE.morbida(q);
    if (q <= 0) { s.o = 0; return s; }
    switch (e) {
      case 'scrivi': s.p = q; if (o.tipo === 'immagine') s.o = b.o * u; break;
      case 'dissolvenza': s.o = b.o * u; break;
      case 'zoom': s.s = b.s * (0.2 + 0.8 * EASE.rimbalzo(q)); s.o = b.o * Math.min(1, q * 3); break;
      case 'sinistra': s.x = lerp(-300, b.x, u); break;
      case 'destra': s.x = lerp(W + 300, b.x, u); break;
      case 'alto': s.y = lerp(-300, b.y, u); break;
      case 'basso': s.y = lerp(H + 300, b.y, u); break;
      case 'gira': s.r = b.r + (1 - u) * 360; s.s = b.s * u; break;
    }
    return s;
  }
  // stato del passo k «finito» (quello che si modifica nell'editor)
  function statiPasso(k) { var o = {}, ps = P.passi[k]; Object.keys(ps.stato).forEach(function (id) { var s = ps.stato[id]; if (s.v) o[id] = { x: s.x, y: s.y, s: s.s, r: s.r, o: s.o, v: 1, p: 1 }; }); return o; }

  // ---------- disegno della scena ----------
  var tempoPlay = null, laser = [];
  function ridisegna() { richiesta = true; }
  var richiesta = true;
  function disegnaScena(c, stati) {
    disegnaSfondo(c);
    P.oggetti.forEach(function (o) { if (stati[o.id] && !(modifica && modifica.id === o.id)) disegnaOggetto(c, o, stati[o.id]); });
  }
  function ciclo() {
    var ora = performance.now();
    if (tempoPlay) {
      var t = (ora - tempoPlay.avvio) / 1000 + tempoPlay.da;
      var tot = durataTotale();
      if (t >= tot + 0.6) { fineRiproduzione(); }
      else {
        var r = statiAl(Math.min(t, tot));
        disegnaScena(ctx, r.stati);
        $('lv-cursore').value = Math.round(Math.min(t, tot) / Math.max(0.01, tot) * 1000);
        evidenziaMiniatura(r.passo);
      }
      richiesta = true;
    } else if (richiesta || laser.length || registrazione) {
      richiesta = false;
      disegnaScena(ctx, statiPasso(passo));
      if (inCorso) disegnaOggetto(ctx, inCorso, { x: inCorso._x || 0, y: inCorso._y || 0, s: 1, r: 0, o: 1, v: 1, p: 1 });
      disegnaSopra();
    }
    disegnaLaser(ora);
    requestAnimationFrame(ciclo);
  }
  function disegnaLaser(ora) {
    if (!laser.length) return;
    laser = laser.filter(function (p) { return ora - p[2] < 700; });
    if (!laser.length) { richiesta = true; return; }
    ctx.save(); ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    for (var i = 1; i < laser.length; i++) {
      var a = laser[i - 1], b = laser[i], vita = 1 - (ora - b[2]) / 700;
      ctx.strokeStyle = 'rgba(255,40,40,' + (vita * 0.9) + ')'; ctx.lineWidth = 14 * vita + 4; ctx.shadowColor = '#ff2020'; ctx.shadowBlur = 24;
      ctx.beginPath(); ctx.moveTo(a[0], a[1]); ctx.lineTo(b[0], b[1]); ctx.stroke();
    }
    var u = laser[laser.length - 1]; ctx.fillStyle = '#ff3b3b'; ctx.beginPath(); ctx.arc(u[0], u[1], 12, 0, 7); ctx.fill();
    ctx.restore(); richiesta = true;
  }

  // maniglie della selezione, guide, griglia (sulla tela di sopra: non vanno nel video)
  function disegnaSopra() {
    sctx.clearRect(0, 0, W, H);
    if (tempoPlay || (registrazione && registrazione.modo === 'passi')) return;
    if (P.griglia) { sctx.strokeStyle = 'rgba(240,124,18,.18)'; sctx.lineWidth = 1; sctx.beginPath(); for (var x = 0; x <= W; x += 40) { sctx.moveTo(x, 0); sctx.lineTo(x, H); } for (var y = 0; y <= H; y += 40) { sctx.moveTo(0, y); sctx.lineTo(W, y); } sctx.stroke(); }
    if (guida) { sctx.strokeStyle = '#ff2fa0'; sctx.lineWidth = 2; sctx.setLineDash([10, 8]); sctx.beginPath(); if (guida.x) { sctx.moveTo(W / 2, 0); sctx.lineTo(W / 2, H); } if (guida.y) { sctx.moveTo(0, H / 2); sctx.lineTo(W, H / 2); } sctx.stroke(); sctx.setLineDash([]); }
    if (riquadroSel) { sctx.strokeStyle = '#f07c12'; sctx.fillStyle = 'rgba(240,124,18,.1)'; sctx.lineWidth = 2; sctx.setLineDash([8, 6]); var r = riquadroSel; sctx.fillRect(r.x, r.y, r.w, r.h); sctx.strokeRect(r.x, r.y, r.w, r.h); sctx.setLineDash([]); }
    if (gomma) { sctx.strokeStyle = '#f07c12'; sctx.lineWidth = 2; sctx.beginPath(); sctx.arc(gomma.x, gomma.y, gomma.r, 0, 7); sctx.stroke(); }
    var stati = statiPasso(passo);
    scelti.forEach(function (id) {
      var o = ogg(id), s = stati[id]; if (!o || !s) return;
      var b = bboxLocale(o), ang = (s.r || 0) * Math.PI / 180;
      sctx.save(); sctx.translate(s.x, s.y); sctx.rotate(ang);
      var sc = s.s || 1, x = b.x * sc, y = b.y * sc, w = b.w * sc, h = b.h * sc;
      sctx.strokeStyle = '#f07c12'; sctx.lineWidth = 3; sctx.setLineDash([10, 6]); sctx.strokeRect(x - 6, y - 6, w + 12, h + 12); sctx.setLineDash([]);
      if (scelti.length === 1) {
        sctx.fillStyle = '#fff'; sctx.strokeStyle = '#f07c12';
        [[x - 6, y - 6], [x + w + 6, y - 6], [x - 6, y + h + 6], [x + w + 6, y + h + 6]].forEach(function (q) { sctx.beginPath(); sctx.rect(q[0] - 11, q[1] - 11, 22, 22); sctx.fill(); sctx.stroke(); });
        sctx.beginPath(); sctx.moveTo(x + w / 2, y - 6); sctx.lineTo(x + w / 2, y - 56); sctx.stroke();
        sctx.beginPath(); sctx.arc(x + w / 2, y - 66, 13, 0, 7); sctx.fill(); sctx.stroke();
        if (o.tipo === 'forma' && (o.f === 'linea' || o.f === 'freccia' || o.f === 'curva' || o.f === 'doppia')) {
          sctx.fillStyle = '#f07c12';
          var pts = [[o.x1, o.y1], [o.x2, o.y2]].concat(o.f === 'curva' ? [[o.cx, o.cy]] : []);
          pts.forEach(function (q) { sctx.beginPath(); sctx.arc(q[0] * sc, q[1] * sc, 12, 0, 7); sctx.fill(); });
        }
      }
      sctx.restore();
    });
  }

  // ---------- coordinate e colpi ----------
  function puntoTela(ev) { var r = tela.getBoundingClientRect(); return { x: (ev.clientX - r.left) / r.width * W, y: (ev.clientY - r.top) / r.height * H, p: ev.pressure || 0.5 }; }
  function inLocale(s, x, y) { var ang = -(s.r || 0) * Math.PI / 180, dx = x - s.x, dy = y - s.y; return { x: (dx * Math.cos(ang) - dy * Math.sin(ang)) / (s.s || 1), y: (dx * Math.sin(ang) + dy * Math.cos(ang)) / (s.s || 1) }; }
  function colpito(x, y) {
    var stati = statiPasso(passo);
    for (var i = P.oggetti.length - 1; i >= 0; i--) {
      var o = P.oggetti[i], s = stati[o.id]; if (!s) continue;
      var l = inLocale(s, x, y), b = bboxLocale(o), m = 10 / (s.s || 1);
      if (l.x < b.x - m || l.x > b.x + b.w + m || l.y < b.y - m || l.y > b.y + b.h + m) continue;
      if (o.tipo === 'tratto' && distanzaTratto(o, l.x, l.y) > o.sp / 2 + 14) continue;
      return o;
    }
    return null;
  }
  function distanzaTratto(o, x, y) {
    var d = 1e9;
    for (var i = 0; i < o.pts.length; i++) {
      var a = o.pts[i], b = o.pts[i + 1] || a, vx = b[0] - a[0], vy = b[1] - a[1], l2 = vx * vx + vy * vy;
      var t = l2 ? limita(((x - a[0]) * vx + (y - a[1]) * vy) / l2, 0, 1) : 0;
      d = Math.min(d, Math.hypot(x - a[0] - t * vx, y - a[1] - t * vy));
    }
    return d;
  }
  // quale maniglia della selezione (singola) è sotto il punto: 'ruota', 'angolo', 'p1', 'p2', 'pc' o null
  function maniglia(x, y) {
    if (scelti.length !== 1) return null;
    var o = ogg(scelti[0]), s = statiPasso(passo)[scelti[0]]; if (!o || !s) return null;
    var l = inLocale(s, x, y), b = bboxLocale(o), sc = s.s || 1, tol = 22 / sc;
    if (o.tipo === 'forma' && (o.f === 'linea' || o.f === 'freccia' || o.f === 'curva' || o.f === 'doppia')) {
      if (Math.hypot(l.x - o.x1, l.y - o.y1) < tol) return 'p1';
      if (Math.hypot(l.x - o.x2, l.y - o.y2) < tol) return 'p2';
      if (o.f === 'curva' && Math.hypot(l.x - o.cx, l.y - o.cy) < tol) return 'pc';
    }
    if (Math.hypot(l.x - (b.x + b.w / 2), l.y - (b.y - 66 / sc)) < tol) return 'ruota';
    var ang = [[b.x, b.y], [b.x + b.w, b.y], [b.x, b.y + b.h], [b.x + b.w, b.y + b.h]];
    for (var i = 0; i < 4; i++) if (Math.abs(l.x - ang[i][0]) < tol + 6 / sc && Math.abs(l.y - ang[i][1]) < tol + 6 / sc) return 'angolo';
    return null;
  }

  // ---------- modifiche allo stato del passo (con propagazione ai passi dopo, se lì non era stato cambiato) ----------
  function cambiaStato(id, nuovo) {
    var ps = P.passi[passo], vecchio = ps.stato[id]; if (!vecchio) return;
    var prima = JSON.stringify(vecchio);
    Object.keys(nuovo).forEach(function (k) { vecchio[k] = nuovo[k]; });
    for (var k = passo + 1; k < P.passi.length; k++) {
      var s = P.passi[k].stato[id]; if (!s) break;
      var cmp = clona(s); cmp.v = JSON.parse(prima).v;
      if (JSON.stringify(cmp) !== prima) break;
      Object.keys(nuovo).forEach(function (q) { if (q !== 'v') s[q] = nuovo[q]; });
    }
  }
  function aggiungiOggetto(o, x, y) {
    o.id = o.id || nuovoId();
    if (!o.e) o.e = { tipo: 'auto' };
    P.oggetti.push(o);
    P.passi.forEach(function (ps, k) { ps.stato[o.id] = { x: x, y: y, s: 1, r: 0, o: 1, v: k >= passo ? 1 : 0 }; });
    caricaImmagini();
    return o;
  }
  function eliminaOggetti(ids) {
    P.oggetti = P.oggetti.filter(function (o) { return ids.indexOf(o.id) < 0; });
    P.passi.forEach(function (ps) { ids.forEach(function (id) { delete ps.stato[id]; }); });
    scelti = [];
  }
  // nasconde gli oggetti da questo passo in poi (nel video escono in dissolvenza)
  function nascondiDaQui(ids) { ids.forEach(function (id) { for (var k = passo; k < P.passi.length; k++) if (P.passi[k].stato[id]) P.passi[k].stato[id].v = 0; }); scelti = []; }

  // ---------- strumenti ----------
  var STRUMENTI = [
    ['seleziona', '🖐', 'Seleziona e sposta (V)', 'v'], ['penna', '✏️', 'Penna (P)', 'p'], ['evidenziatore', '🖍', 'Evidenziatore (H)', 'h'],
    ['gomma', '🧽', 'Gomma (E)', 'e'], ['laser', '🔴', 'Puntatore laser (K): si vede nel video e sparisce da solo', 'k'],
    ['testo', 'T', 'Testo (T)', 't'], ['etichetta', '🏷', 'Etichetta grammaticale: parola in un riquadro colorato con il nome (G)', 'g'],
    ['frase', '🧩', 'Frase smontabile: ogni parola diventa un pezzo che si può spostare (F)', 'f'],
    ['freccia', '➜', 'Freccia (A)', 'a'], ['curva', '↪', 'Freccia curva (C)', 'c'], ['doppia', '↔', 'Freccia doppia', ''], ['linea', '／', 'Linea (L)', 'l'],
    ['rett', '▭', 'Rettangolo (R)', 'r'], ['ellisse', '◯', 'Cerchio / ellisse (O)', 'o'], ['fumetto', '💬', 'Nuvoletta (B)', 'b'], ['stella', '☆', 'Stella', ''],
    ['personaggio', '🧒', 'Personaggi: Vlad e Luce', ''], ['immagine', '🖼', 'Immagine dal computer o dal telefono', ''],
  ];
  function scegliStrumento(s) {
    strumento = s;
    document.querySelectorAll('.lv-strumenti button').forEach(function (b) { b.classList.toggle('att', b.dataset.s === s); });
    box.dataset.strumento = s;
    if (s === 'personaggio') return finestraPersonaggi();
    if (s === 'immagine') { $('lv-file-img').click(); scegliStrumento('seleziona'); return; }
    if (s !== 'seleziona') { scelti = []; }
    pannello(); ridisegna();
  }
  (function costruisciStrumenti() {
    var nav = $('lv-strumenti');
    STRUMENTI.forEach(function (x) {
      var b = el('button', '', x[1]); b.type = 'button'; b.dataset.s = x[0]; b.title = x[2]; b.setAttribute('aria-label', x[2]);
      b.addEventListener('click', function () { scegliStrumento(x[0]); }); nav.appendChild(b);
    });
  })();

  // ---------- mouse, penna e dita sulla tela ----------
  var trascina = null, inCorso = null, riquadroSel = null, gomma = null, guida = null, modifica = null, puntatori = {};
  sopra.addEventListener('pointerdown', function (ev) {
    if (tempoPlay) return;
    if (ev.pointerType === 'mouse' && ev.button !== 0) return;
    puntatori[ev.pointerId] = 1;
    if (Object.keys(puntatori).length > 1) { inCorso = null; trascina = null; return; }     // due dita: niente
    ev.preventDefault(); try { sopra.setPointerCapture(ev.pointerId); } catch (e) {}
    chiudiScrittura();
    var p = puntoTela(ev);
    if (strumento === 'laser') { laser.push([p.x, p.y, performance.now()]); trascina = { tipo: 'laser' }; return; }
    if (strumento === 'penna' || strumento === 'evidenziatore') {
      var evid = strumento === 'evidenziatore';
      inCorso = { tipo: 'tratto', pts: [[p.x, p.y, ev.pointerType === 'pen' ? p.p : 0.5]], col: evid ? stile.evid : stile.col, sp: evid ? Math.max(18, stile.sp * 4) : stile.sp, evid: evid };
      trascina = { tipo: 'tratto' }; return;
    }
    if (strumento === 'gomma') { gomma = { x: p.x, y: p.y, r: 26 }; trascina = { tipo: 'gomma', tolti: [] }; cancellaSotto(p, trascina); return; }
    if (['freccia', 'curva', 'doppia', 'linea'].indexOf(strumento) >= 0) {
      inCorso = { tipo: 'forma', f: strumento, x1: p.x, y1: p.y, x2: p.x, y2: p.y, cx: p.x, cy: p.y, col: stile.col, sp: stile.sp, tratteggio: stile.tratteggio, riempi: '' };
      trascina = { tipo: 'forma', x0: p.x, y0: p.y }; return;
    }
    if (['rett', 'ellisse', 'fumetto', 'stella'].indexOf(strumento) >= 0) {
      inCorso = { tipo: 'forma', f: strumento, w: 1, h: 1, col: stile.col, sp: stile.sp, riempi: stile.riempi, tratteggio: stile.tratteggio, arrot: true, _x: p.x, _y: p.y };
      trascina = { tipo: 'riquadro', x0: p.x, y0: p.y }; return;
    }
    if (strumento === 'testo' || strumento === 'etichetta' || strumento === 'frase') { nuovoTesto(strumento, p.x, p.y); return; }
    // seleziona
    var m = maniglia(p.x, p.y);
    if (m) {
      var id = scelti[0], o = ogg(id), s = clona(st(passo, id));
      trascina = { tipo: 'maniglia', m: m, id: id, s0: s, o0: clona(o), x0: p.x, y0: p.y, a0: Math.atan2(p.y - s.y, p.x - s.x) * 180 / Math.PI, d0: Math.hypot(p.x - s.x, p.y - s.y) };
      return;
    }
    var hit = colpito(p.x, p.y);
    if (hit) {
      if (ev.shiftKey) { var i = scelti.indexOf(hit.id); if (i >= 0) scelti.splice(i, 1); else scelti.push(hit.id); }
      else if (scelti.indexOf(hit.id) < 0) scelti = [hit.id];
      var partenze = {}; scelti.forEach(function (sid) { partenze[sid] = clona(st(passo, sid)); });
      trascina = { tipo: 'sposta', x0: p.x, y0: p.y, partenze: partenze, mosso: false };
      pannello(); ridisegna();
      if (ev.detail >= 2 && (hit.tipo === 'testo' || hit.tipo === 'etichetta')) apriScrittura(hit);
      return;
    }
    if (!ev.shiftKey) scelti = [];
    riquadroSel = { x: p.x, y: p.y, w: 0, h: 0, x0: p.x, y0: p.y }; trascina = { tipo: 'riquadroSel' };
    pannello(); ridisegna();
  });
  sopra.addEventListener('pointermove', function (ev) {
    var p = puntoTela(ev);
    if (strumento === 'gomma') { gomma = { x: p.x, y: p.y, r: 26 }; ridisegna(); }
    if (!trascina) { if (strumento === 'seleziona') { var m = maniglia(p.x, p.y), h = !m && colpito(p.x, p.y); sopra.style.cursor = m === 'ruota' ? 'grab' : m === 'angolo' ? 'nwse-resize' : m ? 'move' : h ? 'move' : 'default'; } return; }
    var co = ev.getCoalescedEvents ? ev.getCoalescedEvents() : [ev];
    switch (trascina.tipo) {
      case 'laser': co.forEach(function (e) { var q = puntoTela(e); laser.push([q.x, q.y, performance.now()]); }); break;
      case 'tratto': co.forEach(function (e) { var q = puntoTela(e), u = inCorso.pts[inCorso.pts.length - 1]; if (Math.hypot(q.x - u[0], q.y - u[1]) > 1.5) inCorso.pts.push([q.x, q.y, e.pointerType === 'pen' ? q.p : 0.5]); }); break;
      case 'gomma': cancellaSotto(p, trascina); break;
      case 'forma':
        inCorso.x2 = p.x; inCorso.y2 = p.y;
        if (ev.shiftKey) { var ang = Math.round(Math.atan2(p.y - trascina.y0, p.x - trascina.x0) / (Math.PI / 12)) * (Math.PI / 12), dd = Math.hypot(p.x - trascina.x0, p.y - trascina.y0); inCorso.x2 = trascina.x0 + Math.cos(ang) * dd; inCorso.y2 = trascina.y0 + Math.sin(ang) * dd; }
        var mx = (inCorso.x1 + inCorso.x2) / 2, my = (inCorso.y1 + inCorso.y2) / 2, nx = -(inCorso.y2 - inCorso.y1), ny = inCorso.x2 - inCorso.x1;
        inCorso.cx = mx + nx * 0.3; inCorso.cy = my + ny * 0.3; break;
      case 'riquadro':
        var w = p.x - trascina.x0, hh = p.y - trascina.y0;
        if (ev.shiftKey) { var l = Math.max(Math.abs(w), Math.abs(hh)); w = l * Math.sign(w || 1); hh = l * Math.sign(hh || 1); }
        inCorso.w = Math.abs(w); inCorso.h = Math.abs(hh); inCorso._x = trascina.x0 + w / 2; inCorso._y = trascina.y0 + hh / 2; break;
      case 'riquadroSel':
        var r = riquadroSel; r.x = Math.min(r.x0, p.x); r.y = Math.min(r.y0, p.y); r.w = Math.abs(p.x - r.x0); r.h = Math.abs(p.y - r.y0); break;
      case 'sposta':
        var dx = p.x - trascina.x0, dy = p.y - trascina.y0; if (Math.hypot(dx, dy) > 3) trascina.mosso = true;
        guida = null;
        if (scelti.length === 1) {                                    // calamita al centro della lavagna
          var s0 = trascina.partenze[scelti[0]];
          if (Math.abs(s0.x + dx - W / 2) < 14) { dx = W / 2 - s0.x; guida = { x: 1 }; }
          if (Math.abs(s0.y + dy - H / 2) < 14) { dy = H / 2 - s0.y; guida = guida || {}; guida.y = 1; }
        }
        scelti.forEach(function (id) {
          var s = trascina.partenze[id], x = s.x + dx, y = s.y + dy;
          if (P.griglia) { x = Math.round(x / 20) * 20; y = Math.round(y / 20) * 20; }
          P.passi[passo].stato[id].x = x; P.passi[passo].stato[id].y = y;
        }); break;
      case 'maniglia': trascinaManiglia(p, ev); break;
    }
    ridisegna();
  });
  function fineTocco(ev) {
    delete puntatori[ev.pointerId];
    if (!trascina) return;
    var t = trascina; trascina = null; guida = null;
    switch (t.tipo) {
      case 'tratto':
        if (inCorso.pts.length) {
          var b = { x1: 1e9, y1: 1e9, x2: -1e9, y2: -1e9 };
          inCorso.pts.forEach(function (q) { b.x1 = Math.min(b.x1, q[0]); b.y1 = Math.min(b.y1, q[1]); b.x2 = Math.max(b.x2, q[0]); b.y2 = Math.max(b.y2, q[1]); });
          var cx = (b.x1 + b.x2) / 2, cy = (b.y1 + b.y2) / 2;
          inCorso.pts = semplifica(inCorso.pts, 0.8).map(function (q) { return [Math.round((q[0] - cx) * 10) / 10, Math.round((q[1] - cy) * 10) / 10, Math.round((q[2] || 0.5) * 100) / 100]; });
          aggiungiOggetto(inCorso, cx, cy); segna();
        }
        inCorso = null; break;
      case 'forma':
        if (Math.hypot(inCorso.x2 - inCorso.x1, inCorso.y2 - inCorso.y1) > 8) {
          var mx = (inCorso.x1 + inCorso.x2) / 2, my = (inCorso.y1 + inCorso.y2) / 2;
          ['x1', 'x2', 'cx'].forEach(function (k) { inCorso[k] -= mx; }); ['y1', 'y2', 'cy'].forEach(function (k) { inCorso[k] -= my; });
          var o = aggiungiOggetto(inCorso, mx, my); segna(); scelti = [o.id]; scegliStrumentoSenzaPannello('seleziona');
        }
        inCorso = null; break;
      case 'riquadro':
        if (inCorso.w > 8 && inCorso.h > 8) { var x = inCorso._x, y = inCorso._y; delete inCorso._x; delete inCorso._y; var o2 = aggiungiOggetto(inCorso, x, y); segna(); scelti = [o2.id]; scegliStrumentoSenzaPannello('seleziona'); }
        inCorso = null; break;
      case 'riquadroSel':
        var r = riquadroSel, stati = statiPasso(passo);
        if (r.w > 5 && r.h > 5) P.oggetti.forEach(function (o) { var s = stati[o.id]; if (s && s.x >= r.x && s.x <= r.x + r.w && s.y >= r.y && s.y <= r.y + r.h && scelti.indexOf(o.id) < 0) scelti.push(o.id); });
        riquadroSel = null; break;
      case 'sposta': if (t.mosso) { scelti.forEach(function (id) { var nuovo = clona(P.passi[passo].stato[id]); P.passi[passo].stato[id] = t.partenze[id]; cambiaStato(id, { x: nuovo.x, y: nuovo.y }); }); segna(); } break;
      case 'maniglia': var fin = clona(P.passi[passo].stato[t.id]); P.passi[passo].stato[t.id] = t.s0; cambiaStato(t.id, { x: fin.x, y: fin.y, s: fin.s, r: fin.r }); segna(); break;
      case 'gomma': if (t.tolti.length) segna(); break;
    }
    pannello(); ridisegna();
  }
  sopra.addEventListener('pointerup', fineTocco);
  sopra.addEventListener('mousedown', function (e) { e.preventDefault(); });
  sopra.addEventListener('pointercancel', function (ev) { if (trascina && trascina.tipo === 'tratto') inCorso = null; fineTocco(ev); });
  sopra.addEventListener('pointerleave', function () { if (strumento === 'gomma' && !trascina) { gomma = null; ridisegna(); } });
  sopra.addEventListener('dblclick', function (ev) { var p = puntoTela(ev), h = colpito(p.x, p.y); if (h && (h.tipo === 'testo' || h.tipo === 'etichetta')) apriScrittura(h); });
  function scegliStrumentoSenzaPannello(s) { strumento = s; document.querySelectorAll('.lv-strumenti button').forEach(function (b) { b.classList.toggle('att', b.dataset.s === s); }); box.dataset.strumento = s; }

  function trascinaManiglia(p, ev) {
    var t = trascina, s = P.passi[passo].stato[t.id], o = ogg(t.id);
    if (t.m === 'ruota') { var a = Math.atan2(p.y - t.s0.y, p.x - t.s0.x) * 180 / Math.PI; var r = t.s0.r + a - t.a0; if (!ev.shiftKey) { var g = Math.round(r / 15) * 15; if (Math.abs(g - r) < 4) r = g; } s.r = Math.round(r * 10) / 10; }
    else if (t.m === 'angolo') { s.s = limita(t.s0.s * Math.hypot(p.x - t.s0.x, p.y - t.s0.y) / Math.max(10, t.d0), 0.05, 20); }
    else {                                                            // estremi di linee e frecce
      var l = inLocale(t.s0, p.x, p.y), k = t.m === 'p1' ? ['x1', 'y1'] : t.m === 'p2' ? ['x2', 'y2'] : ['cx', 'cy'];
      o[k[0]] = l.x; o[k[1]] = l.y;
    }
  }
  function cancellaSotto(p, t) {
    var stati = statiPasso(passo);
    for (var i = P.oggetti.length - 1; i >= 0; i--) {
      var o = P.oggetti[i], s = stati[o.id]; if (!s || o.tipo !== 'tratto') continue;
      var l = inLocale(s, p.x, p.y);
      if (distanzaTratto(o, l.x, l.y) * (s.s || 1) < o.sp / 2 + 22) { t.tolti.push(o.id); eliminaOggetti([o.id]); }
    }
  }
  // Ramer–Douglas–Peucker: meno punti, stesso disegno
  function semplifica(pts, eps) {
    if (pts.length < 3) return pts;
    var dmax = 0, idx = 0, a = pts[0], b = pts[pts.length - 1];
    for (var i = 1; i < pts.length - 1; i++) {
      var d = Math.abs((b[1] - a[1]) * pts[i][0] - (b[0] - a[0]) * pts[i][1] + b[0] * a[1] - b[1] * a[0]) / (Math.hypot(b[1] - a[1], b[0] - a[0]) || 1);
      if (d > dmax) { dmax = d; idx = i; }
    }
    if (dmax > eps) { var r1 = semplifica(pts.slice(0, idx + 1), eps), r2 = semplifica(pts.slice(idx), eps); return r1.slice(0, -1).concat(r2); }
    return [a, b];
  }

  // ---------- testo ----------
  var CATEGORIE = [['soggetto', '#2e7d32'], ['predicato', '#c62828'], ['compl. oggetto', '#1565c0'], ['compl. di termine', '#6a1b9a'], ['compl. di specificazione', '#00838f'], ['compl. di luogo', '#ef6c00'], ['compl. di tempo', '#5d4037'], ['attributo', '#ad1457'], ['apposizione', '#558b2f']];
  function nuovoTesto(tipo, x, y) {
    if (tipo === 'frase') {
      chiedi('Frase smontabile', 'Scrivi la frase: ogni parola diventerà un pezzo da spostare.', 'Luce mangia la pappa.', function (frase) {
        var parole = String(frase || '').trim().split(/\s+/).filter(Boolean); if (!parole.length) return;
        var base = { tipo: 'testo', fs: stile.fs, col: stile.col, font: stile.font, bold: stile.grassetto }, larg = [], gap = stile.fs * 0.45;
        parole.forEach(function (w) { larg.push(misuraTesto(Object.assign({ t: w }, base)).w); });
        var tot = larg.reduce(function (a, b) { return a + b; }, 0) + gap * (parole.length - 1), cx = limita(x, tot / 2 + 20, W - tot / 2 - 20), xx = cx - tot / 2;
        scelti = [];
        parole.forEach(function (w, i) { var o = aggiungiOggetto(Object.assign({ t: w }, clona(base)), xx + larg[i] / 2, y); scelti.push(o.id); xx += larg[i] + gap; });
        segna(); scegliStrumento('seleziona');
      });
      return;
    }
    var o = { tipo: tipo, t: '', fs: stile.fs, col: stile.col, font: stile.font, bold: stile.grassetto, allinea: 'center' };
    if (tipo === 'etichetta') { o.cat = CATEGORIE[0][0]; o.cat_col = CATEGORIE[0][1]; o.font = 'sans'; o.bold = true; }
    aggiungiOggetto(o, x, y); scelti = [o.id]; scegliStrumentoSenzaPannello('seleziona'); pannello();
    apriScrittura(o, true);
  }
  var scrivi = $('lv-scrivi');
  function apriScrittura(o, nuovo) {
    var s = st(passo, o.id), r = tela.getBoundingClientRect(), k = r.width / W, br = box.getBoundingClientRect();
    modifica = { id: o.id, nuovo: !!nuovo };
    scrivi.hidden = false; scrivi.value = o.t || '';
    scrivi.style.font = fontDi(Object.assign({}, o, { fs: o.fs * (s.s || 1) * k })); scrivi.style.color = o.tipo === 'etichetta' ? '#111' : o.col;
    scrivi.style.left = (r.left - br.left + s.x * k) + 'px'; scrivi.style.top = (r.top - br.top + s.y * k) + 'px';
    scrivi.style.minWidth = Math.max(160, o.fs * k * 4) + 'px';
    autoAltezza(); scrivi.focus(); scrivi.select(); ridisegna();
  }
  function autoAltezza() { scrivi.style.height = 'auto'; scrivi.style.height = scrivi.scrollHeight + 'px'; }
  scrivi.addEventListener('input', function () { var o = modifica && ogg(modifica.id); if (o) { o.t = scrivi.value; } autoAltezza(); ridisegna(); });
  scrivi.addEventListener('keydown', function (e) { e.stopPropagation(); if (e.key === 'Escape' || (e.key === 'Enter' && (e.ctrlKey || e.metaKey))) { e.preventDefault(); chiudiScrittura(); } });
  scrivi.addEventListener('blur', function () { setTimeout(chiudiScrittura, 0); });
  function chiudiScrittura() {
    if (!modifica) return;
    var o = ogg(modifica.id); scrivi.hidden = true; modifica = null;
    if (o) { o.t = scrivi.value; if (!o.t.trim()) eliminaOggetti([o.id]); }
    segna(); pannello(); ridisegna();
  }

  // ---------- personaggi e immagini ----------
  function finestraPersonaggi() {
    var f = apriDialogo('Personaggi');
    var g = el('div', 'lv-griglia-img');
    CFG.personaggi.forEach(function (x) {
      var b = el('button', 'lv-img-scelta'); b.type = 'button'; var i = el('img'); i.src = x[1]; i.alt = x[0]; b.appendChild(i); b.appendChild(el('span', '', x[0]));
      b.addEventListener('click', function () { chiudiDialogo(); aggiungiImmagine(x[1], 0.7); });
      g.appendChild(b);
    });
    f.appendChild(g); f.appendChild(bottoniDialogo([['Chiudi', null]]));
    scegliStrumentoSenzaPannello('seleziona');
  }
  function aggiungiImmagine(src, quota) {
    var i = immagine(src);
    function metti() {
      var r = Math.min(W * quota / i.naturalWidth, H * quota / i.naturalHeight, 1.5), o = aggiungiOggetto({ tipo: 'immagine', src: src, w: Math.round(i.naturalWidth * r), h: Math.round(i.naturalHeight * r), e: { tipo: 'auto' } }, W / 2, H / 2);
      scelti = [o.id]; segna(); pannello(); ridisegna();
    }
    if (i.complete && i.naturalWidth) metti(); else { i.addEventListener('load', metti, { once: true }); i.addEventListener('error', function () { avviso('Immagine non caricata.'); }, { once: true }); }
  }
  $('lv-file-img').addEventListener('change', function () {
    var inp = this, f = inp.files[0], perSfondo = inp.dataset.sfondo === '1'; inp.value = ''; inp.dataset.sfondo = ''; if (!f) return;
    statoSalvataggio('carico l\'immagine…');
    invia({ azione: 'immagine' }, { file: f }).then(function (j) {
      statoSalvataggio('');
      if (perSfondo) { P.sfondo.tipo = 'immagine'; P.sfondo.img = j.url; caricaImmagini(); segna(); pannello(); ridisegna(); }
      else aggiungiImmagine(j.url, 0.5);
    }).catch(function (e) { statoSalvataggio(''); avviso(e.message); });
  });

  // ---------- pannello delle proprietà (a destra) ----------
  var COLORI = ['#ffffff', '#ffe14d', '#f07c12', '#e53935', '#2e7d32', '#43a047', '#1565c0', '#4fc3f7', '#8e24aa', '#ad1457', '#5d4037', '#1d2433'];
  function riga(et) { var d = el('div', 'lv-riga'); if (et) d.appendChild(el('span', 'lv-et', et)); return d; }
  function tavolozza(val, cb, conNiente) {
    var d = el('div', 'lv-colori');
    if (conNiente) { var n = el('button', 'lv-col niente' + (!val ? ' att' : ''), '∅'); n.type = 'button'; n.title = 'Nessuno'; n.addEventListener('click', function () { cb(''); }); d.appendChild(n); }
    COLORI.forEach(function (c) { var b = el('button', 'lv-col' + (val === c ? ' att' : '')); b.type = 'button'; b.style.background = c; b.title = c; b.addEventListener('click', function () { cb(c); }); d.appendChild(b); });
    var inp = el('input'); inp.type = 'color'; inp.value = /^#[0-9a-f]{6}$/i.test(val || '') ? val : '#ffffff'; inp.title = 'Altro colore';
    inp.addEventListener('input', function () { cb(inp.value, true); }); inp.addEventListener('change', function () { segna(); }); d.appendChild(inp);
    return d;
  }
  function cursore(min, max, passoV, val, cb) { var i = el('input'); i.type = 'range'; i.min = min; i.max = max; i.step = passoV; i.value = val; i.addEventListener('input', function () { cb(parseFloat(i.value)); }); i.addEventListener('change', segna); return i; }
  function selettore(opz, val, cb) { var s = el('select'); opz.forEach(function (o) { var op = new Option(o[1], o[0]); if (String(o[0]) === String(val)) op.selected = true; s.appendChild(op); }); s.addEventListener('change', function () { cb(s.value); segna(); }); return s; }
  function bottone(t, cb, cls, titolo) { var b = el('button', cls || '', t); b.type = 'button'; if (titolo) b.title = titolo; b.addEventListener('click', cb); return b; }
  var FONTS = [['gesso', 'Gesso (a mano)'], ['sans', 'Moderno'], ['serif', 'Libro'], ['mono', 'Macchina da scrivere']];
  var ENTRATE = [['auto', 'Automatica'], ['scrivi', 'Si scrive / si disegna'], ['dissolvenza', 'Dissolvenza'], ['zoom', 'Zoom con rimbalzo'], ['sinistra', 'Da sinistra'], ['destra', 'Da destra'], ['alto', 'Dall\'alto'], ['basso', 'Dal basso'], ['gira', 'Girando'], ['nessuna', 'Subito']];

  function pannello() {
    var pn = $('lv-pannello'); pn.textContent = '';
    var oggs = scelti.map(ogg).filter(Boolean);
    if (oggs.length) return pannelloSelezione(pn, oggs);
    var t = { seleziona: 'Seleziona', penna: 'Penna', evidenziatore: 'Evidenziatore', gomma: 'Gomma', laser: 'Laser', testo: 'Testo', etichetta: 'Etichetta', frase: 'Frase smontabile' }[strumento] || 'Forma';
    pn.appendChild(el('h3', '', t));
    if (strumento === 'seleziona') {
      pn.appendChild(el('p', 'lv-muto', 'Tocca un oggetto per sceglierlo, trascina per spostarlo. Trascina sul vuoto per sceglierne tanti. Doppio clic su un testo per scriverlo.'));
      pannelloLavagna(pn); return;
    }
    if (strumento === 'laser') { pn.appendChild(el('p', 'lv-muto', 'Il puntino rosso segue il dito o il mouse e sparisce da solo: utile mentre registri dal vivo.')); return; }
    if (strumento === 'gomma') { pn.appendChild(el('p', 'lv-muto', 'Passa sui tratti della penna per cancellarli. Per togliere testi e forme: selezionali e premi Canc.')); return; }
    if (strumento === 'evidenziatore') { var r0 = riga('Colore'); r0.appendChild(tavolozza(stile.evid, function (c) { stile.evid = c; pannello(); })); pn.appendChild(r0); return; }
    var r1 = riga('Colore'); r1.appendChild(tavolozza(stile.col, function (c) { stile.col = c; pannello(); })); pn.appendChild(r1);
    if (['testo', 'frase', 'etichetta'].indexOf(strumento) >= 0) {
      var r2 = riga('Grandezza'); r2.appendChild(cursore(16, 220, 2, stile.fs, function (v) { stile.fs = v; })); pn.appendChild(r2);
      var r3 = riga('Carattere'); r3.appendChild(selettore(FONTS, stile.font, function (v) { stile.font = v; })); pn.appendChild(r3);
      var r4 = riga(''); var cb = el('label', 'lv-spunta'); var ci = el('input'); ci.type = 'checkbox'; ci.checked = stile.grassetto; ci.addEventListener('change', function () { stile.grassetto = ci.checked; }); cb.appendChild(ci); cb.appendChild(document.createTextNode(' Grassetto')); r4.appendChild(cb); pn.appendChild(r4);
      pn.appendChild(el('p', 'lv-muto', strumento === 'frase' ? 'Tocca la lavagna dove mettere la frase.' : 'Tocca la lavagna dove scrivere.'));
      return;
    }
    var r5 = riga('Spessore'); r5.appendChild(cursore(1, 40, 1, stile.sp, function (v) { stile.sp = v; })); pn.appendChild(r5);
    if (['rett', 'ellisse', 'fumetto', 'stella'].indexOf(strumento) >= 0) { var r6 = riga('Riempimento'); r6.appendChild(tavolozza(stile.riempi, function (c) { stile.riempi = c; pannello(); }, true)); pn.appendChild(r6); }
    if (strumento !== 'penna') { var r7 = riga(''); var tl = el('label', 'lv-spunta'), ti = el('input'); ti.type = 'checkbox'; ti.checked = stile.tratteggio; ti.addEventListener('change', function () { stile.tratteggio = ti.checked; }); tl.appendChild(ti); tl.appendChild(document.createTextNode(' Tratteggiato')); r7.appendChild(tl); pn.appendChild(r7); }
    pn.appendChild(el('p', 'lv-muto', strumento === 'penna' ? 'Con la penna del tablet il tratto cambia spessore con la pressione.' : 'Trascina sulla lavagna. Tieni premuto Maiusc per angoli dritti e forme regolari.'));
  }
  function pannelloLavagna(pn) {
    pn.appendChild(el('h3', '', 'Lavagna'));
    var r1 = riga('Sfondo'); r1.appendChild(selettore([['lavagna', 'Lavagna verde'], ['nera', 'Lavagna nera'], ['blu', 'Blu notte'], ['bianco', 'Bianco'], ['quadretti', 'Quaderno a quadretti'], ['righe', 'Quaderno a righe'], ['carta', 'Carta'], ['colore', 'Colore…'], ['immagine', 'Immagine…']], P.sfondo.tipo, function (v) {
      if (v === 'immagine') { var inp = $('lv-file-img'); inp.dataset.sfondo = '1'; inp.click(); return; }
      P.sfondo.tipo = v; if (v !== 'colore') stile.col = chiaro() ? '#1d2433' : '#ffffff'; pannello(); ridisegna(); pianificaMiniature();
    })); pn.appendChild(r1);
    if (P.sfondo.tipo === 'colore') { var r2 = riga('Colore'); r2.appendChild(tavolozza(P.sfondo.colore, function (c) { P.sfondo.colore = c; ridisegna(); pianificaMiniature(); })); pn.appendChild(r2); }
    var r3 = riga(''); var gl = el('label', 'lv-spunta'), gi = el('input'); gi.type = 'checkbox'; gi.checked = !!P.griglia; gi.addEventListener('change', function () { P.griglia = gi.checked; segna(); ridisegna(); }); gl.appendChild(gi); gl.appendChild(document.createTextNode(' Griglia e calamita (non si vede nel video)')); r3.appendChild(gl); pn.appendChild(r3);
    pn.appendChild(el('h3', '', '🎵 Musica di sottofondo'));
    var opz = [['', '— nessuna —']].concat(CFG.musiche.map(function (m) { return [m.src, m.titolo]; }));
    if (P.audio.src && !CFG.musiche.some(function (m) { return m.src === P.audio.src; })) opz.push([P.audio.src, P.audio.titolo || 'file caricato']);
    var r4 = riga(''); r4.appendChild(selettore(opz, P.audio.src, function (v) { P.audio.src = v; P.audio.titolo = (CFG.musiche.filter(function (m) { return m.src === v; })[0] || {}).titolo || ''; preparaMusica(); })); pn.appendChild(r4);
    var r5 = riga('Volume'); r5.appendChild(cursore(0, 1, 0.05, P.audio.volume, function (v) { P.audio.volume = v; if (musica) musica.volume = v; })); pn.appendChild(r5);
    pn.appendChild(bottone('📁 Carica un audio…', function () { $('lv-file-audio').click(); }, 'lv-b-piccolo'));
    pn.appendChild(el('p', 'lv-muto', 'Nell\'elenco ci sono anche gli audio del sito (per esempio quelli fatti con la voce AI).'));
  }
  function pannelloSelezione(pn, oggs) {
    var o = oggs[0], uno = oggs.length === 1;
    pn.appendChild(el('h3', '', uno ? ({ tratto: 'Disegno', testo: 'Testo', etichetta: 'Etichetta', immagine: 'Immagine', forma: 'Forma' }[o.tipo]) : oggs.length + ' oggetti'));
    function tutti(f) { oggs.forEach(f); ridisegna(); }
    var conColore = oggs.filter(function (x) { return x.tipo !== 'immagine'; });
    if (conColore.length) { var r1 = riga('Colore'); r1.appendChild(tavolozza(conColore[0].col, function (c, live) { conColore.forEach(function (x) { x.col = c; }); ridisegna(); if (!live) { segna(); pannello(); } })); pn.appendChild(r1); }
    if (uno && (o.tipo === 'testo' || o.tipo === 'etichetta')) {
      pn.appendChild(bottone('✎ Scrivi / modifica il testo', function () { apriScrittura(o); }, 'lv-b-piccolo'));
      var r2 = riga('Grandezza'); r2.appendChild(cursore(12, 260, 2, o.fs, function (v) { o.fs = v; ridisegna(); })); pn.appendChild(r2);
      var r3 = riga('Carattere'); r3.appendChild(selettore(FONTS, o.font, function (v) { o.font = v; ridisegna(); })); pn.appendChild(r3);
      var r4 = riga(''); [['B', 'bold', 'Grassetto']].forEach(function (x) { r4.appendChild(bottone(x[0], function () { o[x[1]] = !o[x[1]]; segna(); pannello(); ridisegna(); }, o[x[1]] ? 'att' : '', x[2])); });
      [['left', '⯇'], ['center', '≡'], ['right', '⯈']].forEach(function (x) { r4.appendChild(bottone(x[1], function () { o.allinea = x[0]; segna(); pannello(); ridisegna(); }, (o.allinea || 'center') === x[0] ? 'att' : '', 'Allinea')); });
      pn.appendChild(r4);
      if (o.tipo === 'testo') {
        var r5 = riga('Sfondo'); r5.appendChild(tavolozza(o.sfondo || '', function (c) { o.sfondo = c; segna(); pannello(); ridisegna(); }, true)); pn.appendChild(r5);
        var r6 = riga('Sottolinea'); r6.appendChild(tavolozza(o.sotto || '', function (c) { o.sotto = c; segna(); pannello(); ridisegna(); }, true)); pn.appendChild(r6);
      } else {
        var r7 = riga('Categoria'); r7.appendChild(selettore(CATEGORIE.map(function (c) { return [c[0], c[0]]; }).concat([['*', 'altro…']]), o.cat, function (v) {
          if (v === '*') { chiedi('Categoria', 'Nome della categoria:', o.cat, function (n) { if (n) { o.cat = n; segna(); pannello(); ridisegna(); } }); return; }
          o.cat = v; o.cat_col = (CATEGORIE.filter(function (c) { return c[0] === v; })[0] || [0, o.cat_col])[1]; pannello(); ridisegna();
        })); pn.appendChild(r7);
        var r8 = riga('Colore riquadro'); r8.appendChild(tavolozza(o.cat_col, function (c) { o.cat_col = c; segna(); pannello(); ridisegna(); })); pn.appendChild(r8);
      }
    }
    var conSp = oggs.filter(function (x) { return x.tipo === 'tratto' || x.tipo === 'forma'; });
    if (conSp.length) { var r9 = riga('Spessore'); r9.appendChild(cursore(1, 60, 1, conSp[0].sp, function (v) { conSp.forEach(function (x) { x.sp = v; }); ridisegna(); })); pn.appendChild(r9); }
    if (uno && o.tipo === 'forma' && ['rett', 'ellisse', 'fumetto', 'stella'].indexOf(o.f) >= 0) {
      var r10 = riga('Riempimento'); r10.appendChild(tavolozza(o.riempi || '', function (c) { o.riempi = c; segna(); pannello(); ridisegna(); }, true)); pn.appendChild(r10);
      var r11 = riga('Misure'); r11.appendChild(cursore(10, 1900, 2, o.w, function (v) { o.w = v; ridisegna(); })); r11.appendChild(cursore(10, 1060, 2, o.h, function (v) { o.h = v; ridisegna(); })); pn.appendChild(r11);
    }
    if (uno && o.tipo === 'forma') { var rt = riga(''); var tl = el('label', 'lv-spunta'), ti = el('input'); ti.type = 'checkbox'; ti.checked = !!o.tratteggio; ti.addEventListener('change', function () { o.tratteggio = ti.checked; segna(); ridisegna(); }); tl.appendChild(ti); tl.appendChild(document.createTextNode(' Tratteggiato')); rt.appendChild(tl); pn.appendChild(rt); }
    var s = st(passo, o.id);
    var r12 = riga('Trasparenza'); r12.appendChild(cursore(0.1, 1, 0.05, s.o, function (v) { oggs.forEach(function (x) { P.passi[passo].stato[x.id].o = v; }); ridisegna(); })); pn.appendChild(r12);
    pn.appendChild(el('h3', '', '🎬 Nel video'));
    var r13 = riga('Entrata'); r13.appendChild(selettore(ENTRATE, (o.e && o.e.tipo) || 'auto', function (v) { oggs.forEach(function (x) { x.e = x.e || {}; x.e.tipo = v; }); })); pn.appendChild(r13);
    var r14 = riga('Durata entrata'); var dur = el('input'); dur.type = 'number'; dur.min = 0; dur.max = 20; dur.step = 0.1; dur.placeholder = 'auto'; dur.value = o.e && o.e.d ? o.e.d : '';
    dur.addEventListener('change', function () { oggs.forEach(function (x) { x.e = x.e || {}; x.e.d = dur.value === '' ? 0 : limita(parseFloat(dur.value) || 0, 0, 20); }); segna(); aggiornaTempi(); }); r14.appendChild(dur); r14.appendChild(el('span', 'lv-muto', ' s')); pn.appendChild(r14);
    var az = el('div', 'lv-azioni');
    az.appendChild(bottone('⧉ Duplica', duplica, '', 'Ctrl+D'));
    az.appendChild(bottone('⬆ Davanti', function () { ordine(1); }, '', 'Porta in primo piano'));
    az.appendChild(bottone('⬇ Dietro', function () { ordine(-1); }, '', 'Porta sullo sfondo'));
    az.appendChild(bottone('⊕ Centra', function () { oggs.forEach(function (x) { cambiaStato(x.id, { x: W / 2 }); }); segna(); ridisegna(); }, '', 'Al centro della lavagna'));
    az.appendChild(bottone('⟲ Raddrizza', function () { oggs.forEach(function (x) { cambiaStato(x.id, { r: 0, s: 1 }); }); segna(); ridisegna(); pannello(); }, '', 'Rotazione 0 e grandezza originale'));
    if (oggs.length > 1) {
      az.appendChild(bottone('⇹ In fila', function () { allinea('fila'); }, '', 'Allinea in orizzontale alla stessa altezza'));
      az.appendChild(bottone('⇳ In colonna', function () { allinea('colonna'); }, '', 'Allinea in verticale'));
    }
    if (uno && o.tipo === 'immagine') az.appendChild(bottone('⇋ Specchia', function () { o.specchio = !o.specchio; segna(); ridisegna(); }, '', 'Gira l\'immagine come in uno specchio'));
    az.appendChild(bottone('👁 Nascondi da qui', function () { nascondiDaQui(scelti.slice()); segna(); pannello(); ridisegna(); }, '', 'Esce dal video da questo passo in poi'));
    az.appendChild(bottone('🗑 Elimina', function () { eliminaOggetti(scelti.slice()); segna(); pannello(); ridisegna(); }, 'pericolo', 'Canc'));
    pn.appendChild(az);
  }
  function duplica() {
    var nuovi = [];
    scelti.map(ogg).filter(Boolean).forEach(function (o) {
      var c = clona(o); c.id = nuovoId(); var s = st(passo, o.id);
      aggiungiOggetto(c, s.x + 40, s.y + 40); cambiaStato(c.id, { s: s.s, r: s.r, o: s.o }); nuovi.push(c.id);
    });
    scelti = nuovi; segna(); pannello(); ridisegna();
  }
  function ordine(verso) {
    var sel = P.oggetti.filter(function (o) { return scelti.indexOf(o.id) >= 0; }), resto = P.oggetti.filter(function (o) { return scelti.indexOf(o.id) < 0; });
    P.oggetti = verso > 0 ? resto.concat(sel) : sel.concat(resto); segna(); ridisegna();
  }
  function allinea(modo) {
    var ss = scelti.map(function (id) { return { id: id, s: st(passo, id) }; });
    if (modo === 'fila') { var y = ss.reduce(function (a, b) { return a + b.s.y; }, 0) / ss.length; ss.forEach(function (x) { cambiaStato(x.id, { y: y }); }); }
    else { var x0 = ss.reduce(function (a, b) { return a + b.s.x; }, 0) / ss.length; ss.forEach(function (x) { cambiaStato(x.id, { x: x0 }); }); }
    segna(); ridisegna();
  }

  // ---------- passi (in basso) ----------
  var timerMini = null;
  function pianificaMiniature() { clearTimeout(timerMini); timerMini = setTimeout(miniature, 250); }
  function miniature() {
    var box2 = $('lv-miniature'); box2.textContent = '';
    P.passi.forEach(function (ps, k) {
      var b = el('button', 'lv-mini' + (k === passo ? ' att' : '')); b.type = 'button'; b.title = 'Passo ' + (k + 1);
      var c = el('canvas'); c.width = 192; c.height = 108; var cc = c.getContext('2d'); cc.scale(0.1, 0.1);
      var stati = statiPasso(k); disegnaSfondo(cc); P.oggetti.forEach(function (o) { if (stati[o.id]) disegnaOggetto(cc, o, stati[o.id]); });
      b.appendChild(c); b.appendChild(el('span', '', (k + 1) + ' · ' + pianoPasso(k).durata.toFixed(1) + 's'));
      b.addEventListener('click', function () { vaiPasso(k); });
      box2.appendChild(b);
    });
    aggiornaTempi();
  }
  function evidenziaMiniatura(k) { document.querySelectorAll('.lv-mini').forEach(function (b, i) { b.classList.toggle('play', i === k); }); }
  function vaiPasso(k) {
    chiudiScrittura(); passo = limita(k, 0, P.passi.length - 1);
    scelti = scelti.filter(function (id) { var s = st(passo, id); return s && s.v; });
    document.querySelectorAll('.lv-mini').forEach(function (b, i) { b.classList.toggle('att', i === passo); });
    $('lv-cursore').value = Math.round(inizioPasso(passo) / Math.max(0.01, durataTotale()) * 1000);
    aggiornaTempi(); pannello(); ridisegna();
  }
  function aggiornaTempi() {
    var ps = P.passi[passo]; $('lv-durata').value = ps.durata; $('lv-seq').checked = !!ps.seq; $('lv-trans').value = ps.trans || 'morbida';
    var tot = durataTotale(); $('lv-tempo-tot').textContent = 'Passo ' + (passo + 1) + ' di ' + P.passi.length + ' · video di ' + Math.floor(tot / 60) + ':' + ('0' + Math.round(tot % 60)).slice(-2);
  }
  $('lv-passo-nuovo').addEventListener('click', function () {
    var c = clona(P.passi[passo]); P.passi.splice(passo + 1, 0, c); segna(); passo++; miniature(); vaiPasso(passo);
  });
  $('lv-passo-togli').addEventListener('click', function () {
    if (P.passi.length < 2) return avviso('Serve almeno un passo.');
    if (!confirm('Togliere il passo ' + (passo + 1) + '?')) return;
    P.passi.splice(passo, 1);         // gli oggetti nuovi di questo passo compaiono nel passo dopo
    segna(); miniature(); vaiPasso(Math.min(passo, P.passi.length - 1));
  });
  $('lv-durata').addEventListener('change', function () { P.passi[passo].durata = limita(parseFloat(this.value) || 1, 0.5, 60); segna(); miniature(); });
  $('lv-seq').addEventListener('change', function () { P.passi[passo].seq = this.checked; segna(); miniature(); });
  $('lv-trans').addEventListener('change', function () { P.passi[passo].trans = this.value; segna(); });
  $('lv-cursore').addEventListener('input', function () {
    if (tempoPlay) fineRiproduzione();
    var t = this.value / 1000 * durataTotale(), r = statiAl(t);
    disegnaScena(ctx, r.stati); sctx.clearRect(0, 0, W, H); evidenziaMiniatura(r.passo); anteprimaCursore = true;
  });
  var anteprimaCursore = false;
  $('lv-cursore').addEventListener('change', function () { var r = statiAl(this.value / 1000 * durataTotale()); vaiPasso(r.passo); anteprimaCursore = false; });

  // ---------- musica ----------
  var musica = null;
  function preparaMusica() {
    if (!P.audio.src) { if (musica) { musica.pause(); } musica = null; return; }
    if (!musica || musica.dataset.src !== P.audio.src) {
      if (musica) musica.pause();
      musica = new Audio(); musica.crossOrigin = 'anonymous'; musica.src = P.audio.src; musica.dataset.src = P.audio.src; musica.loop = true; musica.preload = 'auto';
      collegataAlMixer = false;
    }
    musica.volume = P.audio.volume;
  }
  $('lv-file-audio').addEventListener('change', function () {
    var f = this.files[0]; this.value = ''; if (!f) return;
    statoSalvataggio('carico l\'audio…');
    invia({ azione: 'audio' }, { file: f }).then(function (j) {
      statoSalvataggio(''); P.audio.src = j.url; P.audio.titolo = f.name; preparaMusica(); segna(); pannello();
    }).catch(function (e) { statoSalvataggio(''); avviso(e.message); });
  });

  // ---------- riproduzione ----------
  function riproduci(da) {
    chiudiScrittura(); scelti = []; pannello(); sctx.clearRect(0, 0, W, H);
    preparaMusica();
    tempoPlay = { avvio: performance.now(), da: da || 0 };
    if (musica) { try { musica.currentTime = (da || 0) % (musica.duration || 1e9); } catch (e) {} musica.play().catch(function () {}); }
    $('lv-play').textContent = '⏸ Ferma'; document.body.classList.add('in-play');
  }
  function fineRiproduzione() {
    tempoPlay = null; if (musica) musica.pause();
    $('lv-play').textContent = '▶ Riproduci'; document.body.classList.remove('in-play'); evidenziaMiniatura(-1); ridisegna();
    if (registrazione && registrazione.modo === 'passi') fermaRegistrazione();
  }
  // ▶ parte dal passo che stai guardando (dal primo se sei sul primo)
  $('lv-play').addEventListener('click', function () { if (tempoPlay) fineRiproduzione(); else riproduci(anteprimaCursore ? $('lv-cursore').value / 1000 * durataTotale() : inizioPasso(passo)); });

  // ---------- registrazione video ----------
  var registrazione = null, actx = null, destino = null, collegataAlMixer = false, gainMusica = null;
  function tipoVideo() {
    var t = ['video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'video/mp4;codecs=avc1,opus', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];
    for (var i = 0; i < t.length; i++) if (window.MediaRecorder && MediaRecorder.isTypeSupported(t[i])) return t[i];
    return '';
  }
  function mixer() {
    if (!actx) { actx = new (window.AudioContext || window.webkitAudioContext)(); destino = actx.createMediaStreamDestination(); }
    if (actx.state === 'suspended') actx.resume();
    if (musica && !collegataAlMixer) {
      try { var src = actx.createMediaElementSource(musica); gainMusica = actx.createGain(); src.connect(gainMusica); gainMusica.connect(destino); gainMusica.connect(actx.destination); collegataAlMixer = true; } catch (e) {}
    }
    return destino;
  }
  function contoAllaRovescia(cb) {
    var c = $('lv-conto'), n = 3; c.hidden = false; c.textContent = n;
    var t = setInterval(function () { n--; if (n <= 0) { clearInterval(t); c.hidden = true; cb(); } else c.textContent = n; }, 800);
  }
  function avviaRegistrazione(modo, conVoce) {
    if (!window.MediaRecorder || !tela.captureStream) return avviso('Questo browser non sa registrare video dalla lavagna: usa Chrome, Edge o Firefox recenti.');
    chiudiScrittura();
    preparaMusica();
    var d = mixer(), pronto = conVoce ? navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }) : Promise.resolve(null);
    pronto.then(function (mic) {
      var micNodo = null;
      if (mic) { micNodo = actx.createMediaStreamSource(mic); micNodo.connect(d); }
      var tracce = tela.captureStream(30).getVideoTracks().concat(d.stream.getAudioTracks());
      var tipo = tipoVideo(), rec = new MediaRecorder(new MediaStream(tracce), tipo ? { mimeType: tipo, videoBitsPerSecond: 6000000 } : undefined), pezzi = [];
      rec.ondataavailable = function (e) { if (e.data.size) pezzi.push(e.data); };
      rec.onstop = function () {
        if (mic) mic.getTracks().forEach(function (t) { t.stop(); }); if (micNodo) micNodo.disconnect();
        var blob = new Blob(pezzi, { type: (rec.mimeType || tipo || 'video/webm').split(';')[0] });
        mostraVideo(blob);
      };
      contoAllaRovescia(function () {
        registrazione = { modo: modo, rec: rec, avvio: Date.now() };
        rec.start(500); document.body.classList.add('in-registrazione'); $('lv-registra-info').hidden = false;
        registrazione.timer = setInterval(function () { var s = Math.floor((Date.now() - registrazione.avvio) / 1000); $('lv-reg-tempo').textContent = Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2); }, 250);
        if (modo === 'passi') { passo = 0; riproduci(0); }
        else if (musica) { musica.currentTime = 0; musica.play().catch(function () {}); }
      });
    }).catch(function () { avviso('Il microfono non è disponibile o non hai dato il permesso.'); });
  }
  function fermaRegistrazione() {
    if (!registrazione) return;
    var r = registrazione; registrazione = null; clearInterval(r.timer);
    if (r.rec.state !== 'inactive') r.rec.stop();
    if (tempoPlay) { tempoPlay = null; $('lv-play').textContent = '▶ Riproduci'; document.body.classList.remove('in-play'); }
    if (musica) musica.pause();
    document.body.classList.remove('in-registrazione'); $('lv-registra-info').hidden = true; ridisegna();
  }
  $('lv-reg-stop').addEventListener('click', fermaRegistrazione);
  $('lv-dal-vivo').addEventListener('click', function () {
    if (registrazione) return fermaRegistrazione();
    var f = apriDialogo('⏺ Registra dal vivo');
    f.appendChild(el('p', '', 'Tutto quello che fai sulla lavagna diventa un video: scrivi, disegna, sposta, usa il laser 🔴. Le maniglie arancioni non si vedono nel video.'));
    var l = el('label', 'lv-spunta'), c = el('input'); c.type = 'checkbox'; c.checked = true; l.appendChild(c); l.appendChild(document.createTextNode(' Registra anche la mia voce (microfono)')); f.appendChild(l);
    if (P.audio.src) f.appendChild(el('p', 'lv-muto', '🎵 Si sente anche la musica di sottofondo scelta.'));
    f.appendChild(bottoniDialogo([['Annulla', null], ['⏺ Inizia (3, 2, 1…)', function () { avviaRegistrazione('vivo', c.checked); }, 'rosso']]));
  });
  $('lv-esporta').addEventListener('click', function () {
    if (registrazione) return;
    if (!P.oggetti.length) return avviso('La lavagna è vuota: aggiungi qualcosa prima di creare il video.');
    var f = apriDialogo('🎬 Crea il video');
    var tot = durataTotale();
    f.appendChild(el('p', '', 'Il video mostra i ' + P.passi.length + ' passi con le animazioni (durata: circa ' + Math.ceil(tot) + ' secondi). Si registra mentre lo guardi: non cambiare scheda finché non finisce.'));
    var l = el('label', 'lv-spunta'), c = el('input'); c.type = 'checkbox'; l.appendChild(c); l.appendChild(document.createTextNode(' Parla sopra al video (registra la mia voce mentre va)')); f.appendChild(l);
    f.appendChild(bottoniDialogo([['Annulla', null], ['🎬 Registra il video', function () { avviaRegistrazione('passi', c.checked); }, 'arancio']]));
  });

  // video pronto: anteprima, scarica, salva sul sito
  function estensione(tipo) { return tipo.indexOf('mp4') >= 0 ? 'mp4' : 'webm'; }
  function mostraVideo(blob) {
    var url = URL.createObjectURL(blob), f = apriDialogo('Il tuo video è pronto');
    var v = el('video'); v.src = url; v.controls = true; v.className = 'lv-video'; f.appendChild(v);
    f.appendChild(el('p', 'lv-muto', 'Formato: ' + estensione(blob.type).toUpperCase() + ' · ' + (blob.size / 1048576).toFixed(1) + ' MB' + (estensione(blob.type) === 'webm' ? ' (questo browser registra in WebM: funziona sul sito e su YouTube; per l\'MP4 usa Chrome/Edge recenti o Safari)' : '')));
    var nome = (($('lv-titolo').value || 'lavagna').replace(/[^\w\- àèéìòù]+/g, '').trim() || 'lavagna') + '.' + estensione(blob.type);
    var a = el('a', 'lv-bottone', '⬇ Scarica (' + estensione(blob.type).toUpperCase() + ')'); a.href = url; a.download = nome; f.appendChild(a);
    var opz = el('div', 'lv-salva-opz'), extra = CFG.extra || {};
    if (extra.classi) {
      var s = el('select'); s.appendChild(new Option('— scegli la classe —', '0'));
      extra.classi.forEach(function (c) { var o = new Option(c.nome, c.id); if (+c.id === +(scelte.classe || CFG.classe_id)) o.selected = true; s.appendChild(o); });
      var lb = el('label', '', 'Classe che lo vede: '); lb.appendChild(s); opz.appendChild(lb); opz.dataset.tipo = 'classe'; opz._sel = s;
    } else if (extra.libri) {
      var sl = el('select'); sl.appendChild(new Option('— nessun libro —', '0'));
      extra.libri.forEach(function (l) { var o = new Option(l.titolo, l.id); if (+l.id === +(scelte.libro || CFG.libro_id)) o.selected = true; sl.appendChild(o); });
      var lb2 = el('label', '', 'Libro: '); lb2.appendChild(sl); opz.appendChild(lb2);
      var pg = el('input'); pg.type = 'number'; pg.min = 0; pg.value = 0; pg.style.width = '70px';
      var lb3 = el('label', '', ' pagina: '); lb3.appendChild(pg); opz.appendChild(lb3);
      var cb = el('label', 'lv-spunta'), ci = el('input'); ci.type = 'checkbox'; ci.checked = true; cb.appendChild(ci); cb.appendChild(document.createTextNode(' Pubblica come video del libro (con il suo codice)')); opz.appendChild(cb);
      opz._libro = sl; opz._pagina = pg; opz._pubblica = ci;
    }
    f.appendChild(opz);
    var stato = el('p', 'lv-muto');
    f.appendChild(bottoniDialogo([['Chiudi', null], ['☁ Salva sul sito', function () {
      stato.textContent = 'Salvo il progetto…';
      if (opz._sel) scelte.classe = +opz._sel.value;
      if (opz._libro) scelte.libro = +opz._libro.value;
      salva(true).then(function () {
        stato.textContent = 'Carico il video (' + (blob.size / 1048576).toFixed(1) + ' MB)…';
        var campi = { azione: 'video', id: idLavagna };
        if (opz._libro) { campi.libro_id = opz._libro.value; campi.pagina = opz._pagina.value; if (opz._pubblica.checked) campi.pubblica = 1; }
        return invia(campi, { video: new File([blob], nome, { type: blob.type }) });
      }).then(function (j) { stato.textContent = '✓ Video salvato sul sito' + (j.codice ? ' con il codice ' + j.codice : '') + '.'; CFG.video = j.url; })
        .catch(function (e) { stato.textContent = 'Errore: ' + e.message; });
      return false;
    }, 'arancio']]));
    f.appendChild(stato);
  }
  var scelte = {};

  // ---------- salvataggio ----------
  function invia(campi, files) {
    var fd = new FormData(); fd.append('csrf', CFG.csrf); fd.append('come', CFG.come);
    Object.keys(campi).forEach(function (k) { fd.append(k, campi[k]); });
    Object.keys(files || {}).forEach(function (k) { fd.append(k, files[k]); });
    return fetch(CFG.api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF': CFG.csrf } })
      .then(function (r) { return r.json().catch(function () { throw new Error('risposta non valida del server (HTTP ' + r.status + ')'); }); })
      .then(function (j) { if (!j.ok) throw new Error(j.errore || 'errore'); return j; });
  }
  function statoSalvataggio(t) { $('lv-stato').textContent = t; }
  var salvando = null;
  function salva(silenzioso) {
    chiudiScrittura();
    if (salvando) return salvando;
    statoSalvataggio('salvo…');
    var campi = { azione: 'salva', id: idLavagna, titolo: $('lv-titolo').value, dati: JSON.stringify(P), classe_id: scelte.classe !== undefined ? scelte.classe : CFG.classe_id, libro_id: scelte.libro !== undefined ? scelte.libro : CFG.libro_id };
    salvando = invia(campi).then(function (j) {
      idLavagna = j.id; modificato = false; statoSalvataggio('salvato ✓');
      if (history.replaceState && !/[?&]id=/.test(location.search)) history.replaceState(null, '', location.pathname + location.search + (location.search ? '&' : '?') + 'id=' + j.id);
      try { localStorage.removeItem('lavagna-bozza-' + (CFG.id || 'nuova')); } catch (e) {}
      salvando = null; return j;
    }).catch(function (e) { salvando = null; statoSalvataggio('non salvato!'); if (!silenzioso) avviso('Non riesco a salvare: ' + e.message); throw e; });
    return salvando;
  }
  $('lv-salva').addEventListener('click', function () { salva(); });
  $('lv-titolo').addEventListener('input', function () { modificato = true; statoSalvataggio('modifiche non salvate'); });
  var timerBozza = null;
  function salvaBozzaLocale() { clearTimeout(timerBozza); timerBozza = setTimeout(function () { try { localStorage.setItem('lavagna-bozza-' + (CFG.id || 'nuova'), JSON.stringify({ quando: Date.now(), titolo: $('lv-titolo').value, P: P })); } catch (e) {} }, 800); }
  window.addEventListener('beforeunload', function (e) { if (modificato) { e.preventDefault(); e.returnValue = ''; } });
  setInterval(function () { if (modificato && idLavagna && !registrazione && !tempoPlay) salva(true).catch(function () {}); }, 60000);   // salvataggio automatico ogni minuto

  // ---------- finestre di dialogo ----------
  var dlg = $('lv-dialogo'), dform = $('lv-dialogo-form');
  function apriDialogo(titolo) { dform.textContent = ''; dform.appendChild(el('h2', '', titolo)); if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', ''); return dform; }
  function chiudiDialogo() { if (dlg.open) dlg.close(); dlg.removeAttribute('open'); }
  function bottoniDialogo(lista) {
    var d = el('div', 'lv-dialogo-b');
    lista.forEach(function (x) { var b = el('button', x[2] || '', x[0]); b.type = 'button'; b.addEventListener('click', function () { var r = x[1] ? x[1]() : null; if (r !== false) chiudiDialogo(); }); d.appendChild(b); });
    return d;
  }
  function chiedi(titolo, testo, val, cb) {
    var f = apriDialogo(titolo); f.appendChild(el('p', '', testo));
    var i = el('input'); i.type = 'text'; i.value = val || ''; i.className = 'lv-campo'; f.appendChild(i);
    i.addEventListener('keydown', function (e) { e.stopPropagation(); if (e.key === 'Enter') { e.preventDefault(); chiudiDialogo(); cb(i.value); } });
    f.appendChild(bottoniDialogo([['Annulla', null], ['OK', function () { cb(i.value); }, 'arancio']]));
    setTimeout(function () { i.focus(); i.select(); }, 30);
  }
  function avviso(t) { var f = apriDialogo('Attenzione'); f.appendChild(el('p', '', t)); f.appendChild(bottoniDialogo([['OK', null, 'arancio']])); }

  // ---------- tastiera ----------
  document.addEventListener('keydown', function (e) {
    if (dlg.open || /INPUT|TEXTAREA|SELECT/.test(e.target.tagName)) return;
    var k = e.key.toLowerCase(), ctrl = e.ctrlKey || e.metaKey;
    if (ctrl && k === 'z' && !e.shiftKey) { e.preventDefault(); annulla(); return; }
    if (ctrl && (k === 'y' || (k === 'z' && e.shiftKey))) { e.preventDefault(); ripeti(); return; }
    if (ctrl && k === 's') { e.preventDefault(); salva(); return; }
    if (ctrl && k === 'd') { e.preventDefault(); duplica(); return; }
    if (ctrl && k === 'a') { e.preventDefault(); scelti = Object.keys(statiPasso(passo)); pannello(); ridisegna(); return; }
    if (ctrl) return;
    if (k === 'delete' || k === 'backspace') { if (scelti.length) { e.preventDefault(); eliminaOggetti(scelti.slice()); segna(); pannello(); ridisegna(); } return; }
    if (k === 'escape') { if (registrazione) fermaRegistrazione(); else if (tempoPlay) fineRiproduzione(); else { scelti = []; scegliStrumento('seleziona'); } return; }
    if (k === ' ') { e.preventDefault(); $('lv-play').click(); return; }
    if (k === 'pageup' || k === 'pagedown') { e.preventDefault(); vaiPasso(passo + (k === 'pagedown' ? 1 : -1)); return; }
    if (k.indexOf('arrow') === 0 && scelti.length) {
      e.preventDefault(); var d = e.shiftKey ? 20 : 2;
      scelti.forEach(function (id) { var s = st(passo, id); cambiaStato(id, { x: s.x + (k === 'arrowleft' ? -d : k === 'arrowright' ? d : 0), y: s.y + (k === 'arrowup' ? -d : k === 'arrowdown' ? d : 0) }); });
      segna(); ridisegna(); return;
    }
    if (k === '?') { mostraAiuto(); return; }
    var sc = STRUMENTI.filter(function (x) { return x[3] === k; })[0]; if (sc) scegliStrumento(sc[0]);
  });
  $('lv-annulla').addEventListener('click', annulla); $('lv-ripeti').addEventListener('click', ripeti);
  $('lv-schermo').addEventListener('click', function () { if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen && document.documentElement.requestFullscreen(); });
  function mostraAiuto() {
    var f = apriDialogo('Aiuto');
    var ul = el('ul', 'lv-elenco-aiuto');
    STRUMENTI.forEach(function (x) { ul.appendChild(el('li', '', x[1] + '  ' + x[2])); });
    ['Ctrl+Z / Ctrl+Y: annulla / ripeti', 'Ctrl+S: salva · Ctrl+D: duplica · Ctrl+A: scegli tutto', 'Canc: elimina · frecce: sposta (Maiusc = di più)', 'Spazio: riproduci · Pag↑/Pag↓: passo prima/dopo', 'Doppio clic su un testo: scrivi', 'Maiusc mentre disegni una forma: angoli dritti / forme regolari'].forEach(function (t) { ul.appendChild(el('li', '', '⌨ ' + t)); });
    f.appendChild(el('p', '', 'Come funzionano i passi: prepara la lavagna nel passo 1, premi «+ Passo», sposta o aggiungi cose. Nel video gli oggetti nuovi compaiono (scritti, disegnati…) e quelli spostati scivolano da soli al loro posto.'));
    f.appendChild(ul); f.appendChild(bottoniDialogo([['Chiudi', null, 'arancio']]));
  }
  $('lv-aiuto-b').addEventListener('click', mostraAiuto);


  // ---------- adattamento allo schermo ----------
  function adatta() {
    var r = scena.getBoundingClientRect(), k = Math.min((r.width - 16) / W, (r.height - 16) / H);
    box.style.width = Math.floor(W * k) + 'px'; box.style.height = Math.floor(H * k) + 'px';
  }
  window.addEventListener('resize', adatta);

  function aggiornaTutto() { adatta(); pannello(); miniature(); ridisegna(); }

  // bozza locale più recente del server? (es. il browser si è chiuso prima di salvare)
  try {
    var bozza = JSON.parse(localStorage.getItem('lavagna-bozza-' + (CFG.id || 'nuova')) || 'null');
    if (bozza && bozza.P && bozza.P.oggetti && bozza.P.oggetti.length && (!CFG.dati || JSON.stringify(bozza.P) !== JSON.stringify(CFG.dati))) {
      setTimeout(function () {
        var f = apriDialogo('Bozza ritrovata');
        f.appendChild(el('p', '', 'C\'è una versione non salvata di questa lavagna del ' + new Date(bozza.quando).toLocaleString('it-IT') + '. Vuoi riprenderla?'));
        f.appendChild(bottoniDialogo([['No, buttala', function () { try { localStorage.removeItem('lavagna-bozza-' + (CFG.id || 'nuova')); } catch (e) {} }], ['Sì, riprendila', function () { storia.push(ultimo); P = bozza.P; if (bozza.titolo) $('lv-titolo').value = bozza.titolo; ultimo = JSON.stringify(P); dopoCambioProgetto(); }, 'arancio']]));
      }, 300);
    }
  } catch (e) {}

  window.LavagnaApp = { progetto: function () { return P; }, statiAl: statiAl, durataTotale: durataTotale, salva: salva };   // per i test
  caricaImmagini(); scegliStrumento('seleziona'); aggiornaTutto(); requestAnimationFrame(ciclo);
})();
