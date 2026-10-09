// Vlad Editore Scuola: esercizi interattivi (crocette, vero/falso, completa, tocca le parole, riordina, gruppi, risposta libera).
// Uso: VSEsercizio.monta(contenitore, { codice, tipo, titolo, consegna, dati }, { salva: true })
// Correzione subito con spiegazione, punteggio «7 su 8», riprova, soluzioni. Funziona con mouse e dita.
// Il miglior risultato resta sul dispositivo (localStorage); l'evento «vs-risultato» permette di inviarlo alla classe.
(function () {
  'use strict';

  function el(tag, cls, testo) { var x = document.createElement(tag); if (cls) x.className = cls; if (testo !== undefined && testo !== null) x.textContent = testo; return x; }
  function leggi(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } }
  function scrivi(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
  function mescola(a) { a = a.slice(); for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)), t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
  function parole(s) { return String(s || '').trim().split(/\s+/).filter(Boolean); }
  function norm(s) { return String(s || '').trim().replace(/\s+/g, ' ').replace(/[’`]/g, "'").toLowerCase(); }
  function senzaPunteggiatura(s) { return norm(s).replace(/[.,;:!?«»"()]/g, ''); }

  // ---------- trascinamento con mouse e dita ----------
  // Ogni «gettone» si può trascinare su una zona (data-zona) oppure toccare: tocco = azione rapida (callback tocco).
  function trascinabile(g, opz) {
    g.addEventListener('pointerdown', function (ev) {
      if (ev.button !== undefined && ev.button !== 0) return;
      ev.preventDefault();
      var r = g.getBoundingClientRect(), sx = ev.clientX, sy = ev.clientY, mosso = false, fantasma = null, sopra = null;
      try { g.setPointerCapture(ev.pointerId); } catch (e) {}
      function muovi(e) {
        if (!mosso && Math.hypot(e.clientX - sx, e.clientY - sy) < 6) return;
        if (!mosso) {
          mosso = true; fantasma = g.cloneNode(true); fantasma.className += ' fantasma';
          fantasma.style.width = r.width + 'px'; document.body.appendChild(fantasma); g.classList.add('in-viaggio');
        }
        fantasma.style.left = (e.clientX - (sx - r.left)) + 'px'; fantasma.style.top = (e.clientY - (sy - r.top)) + 'px';
        fantasma.style.display = 'none';
        var sotto = document.elementFromPoint(e.clientX, e.clientY);
        fantasma.style.display = '';
        var z = sotto && sotto.closest('[data-zona]');
        if (z !== sopra) { if (sopra) sopra.classList.remove('sopra'); sopra = z; if (z) z.classList.add('sopra'); }
        if (z) opz.anteprima && opz.anteprima(z, e.clientX, e.clientY);
      }
      function fine(e) {
        g.removeEventListener('pointermove', muovi); g.removeEventListener('pointerup', fine); g.removeEventListener('pointercancel', fine);
        if (sopra) sopra.classList.remove('sopra');
        if (fantasma) fantasma.remove();
        g.classList.remove('in-viaggio');
        if (!mosso) { opz.tocco && opz.tocco(); return; }
        if (sopra && e.type === 'pointerup') opz.lascia(sopra, e.clientX, e.clientY);
      }
      g.addEventListener('pointermove', muovi); g.addEventListener('pointerup', fine); g.addEventListener('pointercancel', fine);
    });
    g.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); opz.tocco && opz.tocco(); } });
  }
  function gettone(testo) { var g = el('button', 'gettone', testo); g.type = 'button'; return g; }
  // posizione in cui inserire un gettone in una riga, in base al punto x/y
  function dopoDi(riga, x, y) {
    var figli = Array.prototype.filter.call(riga.children, function (c) { return c.classList.contains('gettone') && !c.classList.contains('in-viaggio'); });
    for (var i = 0; i < figli.length; i++) {
      var r = figli[i].getBoundingClientRect();
      if (y < r.top) return figli[i];
      if (y <= r.bottom && x < r.left + r.width / 2) return figli[i];
    }
    return null;
  }

  // ---------- un tipo di esercizio = { costruisci(box, dati) → controllo } ----------
  // controllo: { verifica() → {fatti, totale, voci:[{ok, spiegazione, nodo}]}, soluzioni(), blocca() }
  var TIPI = {};

  function riquadroVoce(box, numero) {
    var v = el('div', 'voce-es'); v.appendChild(el('span', 'num-voce', numero + '.'));
    var c = el('div', 'corpo-voce'); v.appendChild(c); box.appendChild(v);
    return { voce: v, corpo: c };
  }

  TIPI.scelta = function (box, d) {
    var voci = (d.domande || []).map(function (q, n) {
      var r = riquadroVoce(box, n + 1); r.corpo.appendChild(el('p', 'testo-voce', q.testo));
      var ops = el('div', 'opzioni'), nome = 'q' + Math.random().toString(36).slice(2), scelte = [];
      q.opzioni.forEach(function (o, i) {
        var l = el('label', 'opzione'), inp = el('input'); inp.type = 'radio'; inp.name = nome; inp.value = i;
        l.appendChild(inp); l.appendChild(el('span', '', o)); ops.appendChild(l); scelte.push(l);
      });
      r.corpo.appendChild(ops);
      return { q: q, r: r, scelte: scelte, ops: ops };
    });
    return {
      verifica: function () {
        return voci.map(function (v) {
          var sel = v.ops.querySelector('input:checked'), ok = !!sel && +sel.value === v.q.giusta;
          v.scelte.forEach(function (l, i) { l.classList.toggle('giusta', ok && i === v.q.giusta); l.classList.toggle('sbagliata', !!sel && !ok && +sel.value === i); });
          return { ok: ok, spiegazione: v.q.spiegazione, nodo: v.r };
        });
      },
      soluzioni: function () { voci.forEach(function (v) { v.scelte[v.q.giusta].classList.add('giusta'); v.scelte[v.q.giusta].querySelector('input').checked = true; }); },
    };
  };

  TIPI.vf = function (box, d) {
    var voci = (d.domande || []).map(function (q, n) {
      var r = riquadroVoce(box, n + 1); r.corpo.appendChild(el('p', 'testo-voce', q.testo));
      var ops = el('div', 'opzioni vf'), nome = 'v' + Math.random().toString(36).slice(2), scelte = {};
      [['1', 'Vero'], ['0', 'Falso']].forEach(function (x) {
        var l = el('label', 'opzione'), inp = el('input'); inp.type = 'radio'; inp.name = nome; inp.value = x[0];
        l.appendChild(inp); l.appendChild(el('span', '', x[1])); ops.appendChild(l); scelte[x[0]] = l;
      });
      r.corpo.appendChild(ops);
      return { q: q, r: r, ops: ops, scelte: scelte };
    });
    return {
      verifica: function () {
        return voci.map(function (v) {
          var sel = v.ops.querySelector('input:checked'), giusto = v.q.vero ? '1' : '0', ok = !!sel && sel.value === giusto;
          Object.keys(v.scelte).forEach(function (k) { v.scelte[k].classList.toggle('giusta', ok && k === giusto); v.scelte[k].classList.toggle('sbagliata', !!sel && !ok && sel.value === k); });
          return { ok: ok, spiegazione: v.q.spiegazione, nodo: v.r };
        });
      },
      soluzioni: function () { voci.forEach(function (v) { var g = v.q.vero ? '1' : '0'; v.scelte[g].classList.add('giusta'); v.scelte[g].querySelector('input').checked = true; }); },
    };
  };

  // «Luce [mangia] la [pappa|crocchetta]»: tra parentesi quadre la risposta (con | le alternative accettate)
  TIPI.completa = function (box, d) {
    var spazi = [], ultimo = null, banca = null;
    if (d.banca) { banca = el('div', 'banca'); box.appendChild(banca); }
    var voci = (d.frasi || []).map(function (f, n) {
      var r = riquadroVoce(box, n + 1), p = el('p', 'testo-voce frase-completa'), suoi = [];
      String(f.testo).split(/(\[[^\]]+\])/).forEach(function (pezzo) {
        var m = pezzo.match(/^\[([^\]]+)\]$/);
        if (!m) { if (pezzo) p.appendChild(document.createTextNode(pezzo)); return; }
        var risposte = m[1].split('|').map(function (s) { return s.trim(); }).filter(Boolean);
        var inp = el('input', 'spazio'); inp.type = 'text'; inp.autocomplete = 'off'; inp.spellcheck = false; inp.setAttribute('autocapitalize', 'off');
        inp.size = Math.max(4, Math.min(24, risposte[0].length + 2));
        inp.addEventListener('focus', function () { ultimo = inp; });
        p.appendChild(inp); var s = { inp: inp, risposte: risposte }; suoi.push(s); spazi.push(s);
      });
      r.corpo.appendChild(p);
      return { r: r, spazi: suoi, spiegazione: f.spiegazione };
    });
    if (banca) mescola(spazi.map(function (s) { return s.risposte[0]; })).forEach(function (w) {
      var g = gettone(w);
      g.addEventListener('click', function () { var t = ultimo || spazi.filter(function (s) { return !s.inp.value; })[0]; if (t) { t.inp.value = w; t.inp.focus(); } });
      banca.appendChild(g);
    });
    return {
      verifica: function () {
        var out = [];
        voci.forEach(function (v) {
          v.spazi.forEach(function (s) {
            var ok = s.risposte.some(function (x) { return norm(x) === norm(s.inp.value); });
            s.inp.classList.toggle('giusta', ok); s.inp.classList.toggle('sbagliata', !ok);
            out.push({ ok: ok, spiegazione: v.spiegazione, nodo: v.r, gruppo: v });
          });
        });
        return out;
      },
      soluzioni: function () { spazi.forEach(function (s) { s.inp.value = s.risposte[0]; s.inp.classList.remove('sbagliata'); s.inp.classList.add('giusta'); }); },
    };
  };

  // Tocca le parole: scegli la categoria (soggetto, predicato…) e tocca le parole della frase.
  TIPI.tocca = function (box, d) {
    var cat = d.categorie || [], attiva = cat[0] ? cat[0].nome : null, colori = {};
    cat.forEach(function (c) { colori[c.nome] = c.colore; });
    var barra = el('div', 'categorie');
    barra.appendChild(el('span', 'muto', 'Scegli e poi tocca le parole:'));
    var bottoni = cat.map(function (c) {
      var b = el('button', 'categoria', c.nome); b.type = 'button'; b.style.setProperty('--col', c.colore);
      b.addEventListener('click', function () { attiva = c.nome; bottoni.forEach(function (x) { x.classList.toggle('att', x === b); }); });
      barra.appendChild(b); return b;
    });
    var gomma = el('button', 'categoria gomma', '⌫ gomma'); gomma.type = 'button';
    gomma.addEventListener('click', function () { attiva = null; bottoni.forEach(function (x) { x.classList.remove('att'); }); gomma.classList.add('att'); });
    barra.appendChild(gomma);
    if (bottoni[0]) bottoni[0].classList.add('att');
    box.appendChild(barra);
    function colora(w) { var c = w.dataset.cat; w.style.setProperty('--col', c ? colori[c] : 'transparent'); w.classList.toggle('segnata', !!c); w.title = c || ''; }
    var voci = (d.frasi || []).map(function (f, n) {
      var r = riquadroVoce(box, n + 1), p = el('p', 'testo-voce frase-tocca'), ws = [];
      parole(f.testo).forEach(function (w, i) {
        var b = el('button', 'parola', w); b.type = 'button'; b.dataset.i = i;
        b.addEventListener('click', function () {
          if (box.classList.contains('bloccato')) return;
          b.dataset.cat = (attiva && b.dataset.cat !== attiva) ? attiva : ''; colora(b);
        });
        p.appendChild(b); p.appendChild(document.createTextNode(' ')); ws.push(b);
      });
      r.corpo.appendChild(p);
      return { r: r, ws: ws, segni: f.segni || {}, spiegazione: f.spiegazione };
    });
    return {
      verifica: function () {
        var out = [];
        voci.forEach(function (v) {
          var errori = 0;
          v.ws.forEach(function (w, i) {
            var atteso = v.segni[i] || '', dato = w.dataset.cat || '';
            w.classList.remove('giusta', 'sbagliata');
            if (atteso) { var ok = atteso === dato; w.classList.add(ok ? 'giusta' : 'sbagliata'); out.push({ ok: ok, spiegazione: v.spiegazione, nodo: v.r, gruppo: v }); }
            else if (dato) { w.classList.add('sbagliata'); errori++; }
          });
          // le parole segnate per sbaglio tolgono punti (non si vince segnando tutto)
          for (var k = 0; k < errori; k++) { var j = out.map(function (o) { return o.gruppo === v && o.ok; }).lastIndexOf(true); if (j >= 0) out[j].ok = false; }
        });
        return out;
      },
      soluzioni: function () { voci.forEach(function (v) { v.ws.forEach(function (w, i) { w.dataset.cat = v.segni[i] || ''; colora(w); w.classList.remove('sbagliata'); if (v.segni[i]) w.classList.add('giusta'); }); }); },
    };
  };

  // Riordina: le parole arrivano in disordine, si trascinano (o si toccano) nella riga della frase.
  TIPI.ordina = function (box, d) {
    var voci = (d.frasi || []).map(function (f, n) {
      var r = riquadroVoce(box, n + 1), giuste = parole(f.testo);
      var risposta = el('div', 'riga-gettoni risposta'); risposta.dataset.zona = 'risposta';
      risposta.appendChild(el('span', 'segnaposto', 'Trascina qui le parole nell\'ordine giusto'));
      var banca = el('div', 'riga-gettoni banca'); banca.dataset.zona = 'banca';
      var ordine = mescola(giuste); var prove = 0;
      while (ordine.join(' ') === giuste.join(' ') && giuste.length > 1 && prove++ < 10) ordine = mescola(giuste);
      ordine.forEach(function (w) {
        var g = gettone(w);
        trascinabile(g, {
          tocco: function () { if (box.classList.contains('bloccato')) return; (g.parentNode === banca ? risposta : banca).appendChild(g); aggiorna(); },
          lascia: function (z, x, y) { if (box.classList.contains('bloccato')) return; var riga = z.dataset.zona === 'risposta' ? risposta : (z.dataset.zona === 'banca' ? banca : null); if (!riga || (riga !== risposta && riga !== banca)) return; riga.insertBefore(g, dopoDi(riga, x, y)); aggiorna(); },
        });
        banca.appendChild(g);
      });
      function aggiorna() { risposta.classList.toggle('vuota', !risposta.querySelector('.gettone')); }
      aggiorna();
      r.corpo.appendChild(risposta); r.corpo.appendChild(banca);
      return { r: r, risposta: risposta, banca: banca, giuste: giuste, spiegazione: f.spiegazione, aggiorna: aggiorna };
    });
    return {
      verifica: function () {
        return voci.map(function (v) {
          var date = Array.prototype.map.call(v.risposta.querySelectorAll('.gettone'), function (g) { return g.textContent; });
          var ok = senzaPunteggiatura(date.join(' ')) === senzaPunteggiatura(v.giuste.join(' '));
          v.risposta.classList.toggle('giusta', ok); v.risposta.classList.toggle('sbagliata', !ok);
          return { ok: ok, spiegazione: v.spiegazione, nodo: v.r };
        });
      },
      soluzioni: function () {
        voci.forEach(function (v) {
          var tutti = Array.prototype.slice.call(v.r.corpo.querySelectorAll('.gettone'));
          v.giuste.forEach(function (w) { for (var i = 0; i < tutti.length; i++) if (tutti[i].textContent === w) { v.risposta.appendChild(tutti.splice(i, 1)[0]); break; } });
          v.aggiorna(); v.risposta.classList.remove('sbagliata'); v.risposta.classList.add('giusta');
        });
      },
    };
  };

  // Gruppi: gli elementi si trascinano nel gruppo giusto (oppure: tocca un elemento, poi tocca il gruppo).
  TIPI.gruppi = function (box, d) {
    var gruppi = d.gruppi || [], scelto = null, giusto = {};
    var banca = el('div', 'riga-gettoni banca'); banca.dataset.zona = 'banca';
    var area = el('div', 'gruppi');
    var caselle = gruppi.map(function (g, i) {
      var c = el('div', 'gruppo'); c.dataset.zona = 'g' + i;
      c.appendChild(el('div', 'nome-gruppo', g.nome));
      var dentro = el('div', 'riga-gettoni dentro'); c.appendChild(dentro);
      c.addEventListener('click', function (ev) { if (scelto && !ev.target.closest('.gettone')) { dentro.appendChild(scelto); scelto.classList.remove('scelto'); scelto = null; } });
      area.appendChild(c); return { c: c, dentro: dentro };
    });
    var tutti = [];
    gruppi.forEach(function (g, i) { g.elementi.forEach(function (x) { tutti.push({ testo: x, g: i }); }); });
    mescola(tutti).forEach(function (x) {
      var b = gettone(x.testo); giusto[tutti.indexOf(x)] = x.g; b.dataset.g = x.g; b.dataset.k = tutti.indexOf(x);
      trascinabile(b, {
        tocco: function () { if (box.classList.contains('bloccato')) return; if (scelto) scelto.classList.remove('scelto'); if (scelto === b) { scelto = null; return; } scelto = b; b.classList.add('scelto'); },
        lascia: function (z) { if (box.classList.contains('bloccato')) return; if (z.dataset.zona === 'banca') banca.appendChild(b); else caselle[+z.dataset.zona.slice(1)].dentro.appendChild(b); },
      });
      banca.appendChild(b);
    });
    box.appendChild(banca); box.appendChild(area);
    return {
      verifica: function () {
        var out = [], ordinati = Array.prototype.slice.call(box.querySelectorAll('.gettone')).sort(function (a, b) { return a.dataset.k - b.dataset.k; });
        ordinati.forEach(function (b) {                // sempre nello stesso ordine (gruppo per gruppo): serve agli «errori comuni» del prof
          var casella = b.closest('.gruppo'), ok = !!casella && casella.dataset.zona === 'g' + b.dataset.g;
          b.classList.toggle('giusta', ok); b.classList.toggle('sbagliata', !ok);
          out.push({ ok: ok, nodo: null });
        });
        return out;
      },
      soluzioni: function () { Array.prototype.forEach.call(box.querySelectorAll('.gettone'), function (b) { caselle[+b.dataset.g].dentro.appendChild(b); b.classList.remove('sbagliata'); b.classList.add('giusta'); }); },
    };
  };

  // Risposta libera: niente punteggio, si confronta con una risposta d'esempio.
  TIPI.aperta = function (box, d) {
    var voci = (d.domande || []).map(function (q, n) {
      var r = riquadroVoce(box, n + 1); r.corpo.appendChild(el('p', 'testo-voce', q.testo));
      var t = el('textarea', 'risposta-libera'); t.rows = 3; r.corpo.appendChild(t);
      return { r: r, q: q };
    });
    return {
      libera: true,
      verifica: function () { return voci.map(function (v) { return { ok: null, spiegazione: v.q.esempio ? 'Esempio di risposta: ' + v.q.esempio : '', nodo: v.r }; }); },
      soluzioni: function () { this.verifica(); },
    };
  };

  // ---------- montaggio ----------
  function monta(cont, es, opz) {
    opz = opz || {};
    cont.textContent = ''; cont.classList.add('vs-esercizio');
    var tipo = TIPI[es.tipo];
    if (!tipo) { cont.appendChild(el('p', 'errore', 'Tipo di esercizio sconosciuto.')); return; }
    var chiave = es.codice ? 'vs-es-' + es.codice : null;
    if (es.consegna) cont.appendChild(el('p', 'consegna', es.consegna));
    var box = el('div', 'voci-es tipo-' + es.tipo); cont.appendChild(box);
    var ctl = tipo(box, es.dati || {});
    var piede = el('div', 'piede-es'), esito = el('div', 'esito'); esito.setAttribute('aria-live', 'polite');
    var bVerifica = el('button', 'bottone-es', ctl.libera ? 'Confronta' : 'Controlla'), bRiprova = el('button', 'bottone-es sec', 'Riprova'), bSol = el('button', 'bottone-es sec', 'Mostra le soluzioni');
    [bVerifica, bRiprova, bSol].forEach(function (b) { b.type = 'button'; });
    bRiprova.hidden = true; bSol.hidden = true;
    piede.appendChild(bVerifica); piede.appendChild(bRiprova); piede.appendChild(bSol);
    cont.appendChild(esito); cont.appendChild(piede);
    var prec = chiave && leggi(chiave);
    if (prec && prec.totale) { var pp = el('p', 'muto record', 'Il tuo miglior risultato: ' + prec.fatti + ' su ' + prec.totale); cont.insertBefore(pp, box); }

    function pulisciSpiegazioni() { Array.prototype.forEach.call(box.querySelectorAll('.spiegazione, .segno'), function (x) { x.remove(); }); }
    bVerifica.addEventListener('click', function () {
      pulisciSpiegazioni();
      var voci = ctl.verifica(), visti = [];
      voci.forEach(function (v) {
        if (!v.nodo) return;
        var i = visti.indexOf(v.nodo);
        if (i < 0) { visti.push(v.nodo); v.nodo.tutteOk = v.ok; v.nodo.spieg = v.spiegazione; } else if (v.ok === false) v.nodo.tutteOk = false;
      });
      visti.forEach(function (n) {
        if (n.tutteOk !== null) n.voce.insertBefore(el('span', 'segno ' + (n.tutteOk ? 'si' : 'no'), n.tutteOk ? '✓' : '✗'), n.voce.firstChild);
        if (n.spieg && (n.tutteOk === false || n.tutteOk === null)) n.corpo.appendChild(el('p', 'spiegazione', n.spieg));
      });
      if (ctl.libera) { esito.textContent = 'Confronta le tue risposte con gli esempi.'; esito.className = 'esito libero'; return; }
      var fatti = voci.filter(function (v) { return v.ok; }).length, totale = voci.length;
      var perc = totale ? fatti / totale : 0;
      esito.className = 'esito ' + (perc === 1 ? 'pieno' : perc >= 0.6 ? 'buono' : 'basso');
      esito.textContent = '';
      esito.appendChild(el('b', '', fatti + ' su ' + totale));
      esito.appendChild(document.createTextNode(' ' + (perc === 1 ? '— perfetto! 🎉' : perc >= 0.8 ? '— quasi tutto giusto!' : perc >= 0.6 ? '— bene, ripassa gli errori.' : '— riprova con calma, ce la fai.')));
      cont.classList.add('verificato'); box.classList.add('bloccato');
      bVerifica.hidden = true; bRiprova.hidden = false; bSol.hidden = perc === 1;
      if (chiave && opz.salva !== false && (!prec || fatti >= prec.fatti)) { prec = { fatti: fatti, totale: totale, quando: Date.now() }; scrivi(chiave, prec); }
      try { cont.dispatchEvent(new CustomEvent('vs-risultato', { bubbles: true, detail: { codice: es.codice, fatti: fatti, totale: totale, voci: voci.map(function (v) { return v.ok ? 1 : 0; }) } })); } catch (e) {}
    });
    bRiprova.addEventListener('click', function () { monta(cont, es, opz); });
    bSol.addEventListener('click', function () { pulisciSpiegazioni(); ctl.soluzioni(); bSol.hidden = true; esito.appendChild(document.createTextNode(' Ecco le soluzioni.')); });
  }

  window.VSEsercizio = { monta: monta, tipi: Object.keys(TIPI), parole: parole };
})();
