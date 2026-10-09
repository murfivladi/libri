// Editor visivo degli esercizi (admin): caselle, pulsanti e clic sulle parole al posto del JSON.
// I dati vanno nel campo nascosto «dati» a ogni modifica; a destra l'anteprima usa lo stesso motore degli studenti.
(function () {
  'use strict';
  var form = document.getElementById('form-es'), campo = document.getElementById('campo-dati');
  var editor = document.getElementById('editor'), anteprima = document.getElementById('anteprima');
  var stati = {}, tipo = null;
  try { var iniziale = JSON.parse(campo.value || '{}'); } catch (e) { iniziale = {}; }
  var tipoIniziale = (form.querySelector('input[name=tipo]:checked') || {}).value;
  if (tipoIniziale) stati[tipoIniziale] = iniziale;

  var CATEGORIE = [
    ['soggetto', '#2e7d32'], ['predicato', '#c62828'], ['complemento oggetto', '#1565c0'], ['complemento di termine', '#6a1b9a'],
    ['complemento di specificazione', '#00838f'], ['complemento di luogo', '#ef6c00'], ['complemento di tempo', '#5d4037'],
    ['attributo', '#ad1457'], ['apposizione', '#558b2f'], ['nome', '#1565c0'], ['verbo', '#c62828'], ['aggettivo', '#ef6c00'],
    ['articolo', '#6a1b9a'], ['preposizione', '#00838f'], ['pronome', '#5d4037'], ['avverbio', '#558b2f'],
  ];

  function el(tag, cls, testo) { var x = document.createElement(tag); if (cls) x.className = cls; if (testo !== undefined) x.textContent = testo; return x; }
  function campoTesto(etichetta, valore, onInput, opz) {
    opz = opz || {};
    var l = el('label', opz.cls || ''), i = el(opz.area ? 'textarea' : 'input');
    if (!opz.area) i.type = 'text'; else i.rows = opz.righe || 2;
    i.value = valore || ''; if (opz.placeholder) i.placeholder = opz.placeholder;
    i.addEventListener('input', function () { onInput(i.value); cambiato(); });
    if (etichetta) l.appendChild(document.createTextNode(etichetta));
    l.appendChild(i); return l;
  }
  function bottone(testo, cls, f) { var b = el('button', 'mini ' + (cls || ''), testo); b.type = 'button'; b.addEventListener('click', f); return b; }

  var timer = null;
  function cambiato(subito) {
    campo.value = JSON.stringify(stati[tipo] || {});
    campo.dispatchEvent(new Event('input', { bubbles: true }));       // per l'avviso «modifiche non salvate»
    clearTimeout(timer); timer = setTimeout(anteprimaAggiorna, subito ? 0 : 350);
  }
  function anteprimaAggiorna() {
    if (!tipo) return;
    VSEsercizio.monta(anteprima, { tipo: tipo, titolo: document.getElementById('titolo').value, consegna: document.getElementById('consegna').value, dati: stati[tipo] || {} }, { salva: false });
  }
  document.getElementById('consegna').addEventListener('input', function () { cambiato(); });

  // Elenco di voci (domande/frasi/gruppi) con aggiungi, sposta su/giù, togli.
  function elenco(lista, nuova, disegnaVoce, testoAggiungi) {
    var box = el('div', 'elenco-voci');
    function ridisegna() {
      box.textContent = '';
      lista.forEach(function (v, i) {
        var riga = el('div', 'voce-editor'), testa = el('div', 'testa-voce');
        testa.appendChild(el('b', '', (i + 1) + '.'));
        var sp = el('span', 'azioni-voce');
        if (i > 0) sp.appendChild(bottone('↑', '', function () { lista.splice(i - 1, 0, lista.splice(i, 1)[0]); ridisegna(); cambiato(); }));
        if (i < lista.length - 1) sp.appendChild(bottone('↓', '', function () { lista.splice(i + 1, 0, lista.splice(i, 1)[0]); ridisegna(); cambiato(); }));
        sp.appendChild(bottone('✕ togli', 'pericolo-mini', function () { if (confirm('Togliere questa voce?')) { lista.splice(i, 1); ridisegna(); cambiato(); } }));
        testa.appendChild(sp); riga.appendChild(testa);
        disegnaVoce(riga, v, ridisegna);
        box.appendChild(riga);
      });
      var agg = bottone('+ ' + testoAggiungi, 'aggiungi', function () { lista.push(nuova()); ridisegna(); cambiato(); var ins = box.querySelectorAll('.voce-editor'); var u = ins[ins.length - 1]; if (u) { var f = u.querySelector('input,textarea'); if (f) f.focus(); } });
      box.appendChild(agg);
    }
    ridisegna();
    return box;
  }

  var EDITOR = {};
  EDITOR.scelta = function (d) {
    d.domande = d.domande || [{ testo: '', opzioni: ['', ''], giusta: 0, spiegazione: '' }];
    editor.appendChild(elenco(d.domande, function () { return { testo: '', opzioni: ['', ''], giusta: 0, spiegazione: '' }; }, function (riga, q, rid) {
      riga.appendChild(campoTesto('Domanda', q.testo, function (v) { q.testo = v; }, { placeholder: 'es. Qual è il soggetto di «Luce dorme»?' }));
      var ops = el('div', 'opzioni-editor'); ops.appendChild(el('small', 'muto', 'Risposte (segna con ◉ quella giusta):'));
      var nome = 'g' + Math.random().toString(36).slice(2);
      q.opzioni.forEach(function (o, j) {
        var r = el('div', 'riga-opzione'), rb = el('input'); rb.type = 'radio'; rb.name = nome; rb.checked = q.giusta === j; rb.title = 'Risposta giusta';
        rb.addEventListener('change', function () { q.giusta = j; cambiato(); });
        var t = el('input'); t.type = 'text'; t.value = o; t.placeholder = 'Risposta ' + (j + 1);
        t.addEventListener('input', function () { q.opzioni[j] = t.value; cambiato(); });
        r.appendChild(rb); r.appendChild(t);
        if (q.opzioni.length > 2) r.appendChild(bottone('✕', '', function () { q.opzioni.splice(j, 1); if (q.giusta >= q.opzioni.length) q.giusta = 0; rid(); cambiato(); }));
        ops.appendChild(r);
      });
      if (q.opzioni.length < 8) ops.appendChild(bottone('+ risposta', '', function () { q.opzioni.push(''); rid(); cambiato(); }));
      riga.appendChild(ops);
      riga.appendChild(campoTesto('Spiegazione (si vede se sbaglia)', q.spiegazione, function (v) { q.spiegazione = v; }));
    }, 'domanda'));
  };

  EDITOR.vf = function (d) {
    d.domande = d.domande || [{ testo: '', vero: true, spiegazione: '' }];
    editor.appendChild(elenco(d.domande, function () { return { testo: '', vero: true, spiegazione: '' }; }, function (riga, q) {
      riga.appendChild(campoTesto('Frase', q.testo, function (v) { q.testo = v; }, { placeholder: 'es. Il predicato può essere sottinteso.' }));
      var s = el('div', 'interruttore');
      [[true, '✔ Vero'], [false, '✘ Falso']].forEach(function (x) {
        var b = bottone(x[1], q.vero === x[0] ? 'att' : '', function () { q.vero = x[0]; s.querySelectorAll('button').forEach(function (bb) { bb.classList.remove('att'); }); b.classList.add('att'); cambiato(); });
        s.appendChild(b);
      });
      riga.appendChild(s);
      riga.appendChild(campoTesto('Spiegazione', q.spiegazione, function (v) { q.spiegazione = v; }));
    }, 'frase'));
  };

  EDITOR.completa = function (d) {
    d.frasi = d.frasi || [{ testo: '', spiegazione: '' }];
    var b = el('label', 'spunta'), cb = el('input'); cb.type = 'checkbox'; cb.checked = !!d.banca;
    cb.addEventListener('change', function () { d.banca = cb.checked; cambiato(); });
    b.appendChild(cb); b.appendChild(document.createTextNode(' Mostra le parole da inserire (banca di parole)'));
    editor.appendChild(b);
    editor.appendChild(el('p', 'muto', 'Scrivi la frase, poi seleziona la parola da nascondere e premi «[ ] Nascondi». Più risposte giuste: separale con | (es. [mangia|mangiava]).'));
    editor.appendChild(elenco(d.frasi, function () { return { testo: '', spiegazione: '' }; }, function (riga, f) {
      var l = campoTesto('Frase', f.testo, function (v) { f.testo = v; }, { area: true, placeholder: 'es. Luce [mangia] la pappa.' });
      var area = l.querySelector('textarea'); riga.appendChild(l);
      riga.appendChild(bottone('[ ] Nascondi la parola selezionata', '', function () {
        var a = area.selectionStart, z = area.selectionEnd, v = area.value;
        if (a === z) { alert('Seleziona prima una parola nella frase.'); return; }
        area.value = v.slice(0, a) + '[' + v.slice(a, z).trim() + ']' + v.slice(z); f.testo = area.value; cambiato();
      }));
      riga.appendChild(campoTesto('Spiegazione', f.spiegazione, function (v) { f.spiegazione = v; }));
    }, 'frase'));
  };

  EDITOR.tocca = function (d) {
    d.categorie = d.categorie || [{ nome: 'soggetto', colore: '#2e7d32' }, { nome: 'predicato', colore: '#c62828' }];
    d.frasi = d.frasi || [{ testo: '', segni: {}, spiegazione: '' }];
    var attiva = d.categorie[0] ? d.categorie[0].nome : null;
    var boxCat = el('div', 'pannello-cat');
    function disegnaCat() {
      boxCat.textContent = '';
      boxCat.appendChild(el('b', '', 'Cosa deve segnare lo studente:'));
      var chips = el('div', 'chips-cat');
      d.categorie.forEach(function (c, i) {
        var ch = el('span', 'chip-cat' + (attiva === c.nome ? ' att' : '')); ch.style.setProperty('--col', c.colore);
        var nome = el('button', '', c.nome); nome.type = 'button'; nome.title = 'Usa questa categoria per segnare le parole qui sotto';
        nome.addEventListener('click', function () { attiva = c.nome; disegnaCat(); });
        var col = el('input'); col.type = 'color'; col.value = c.colore; col.title = 'Colore';
        col.addEventListener('input', function () { c.colore = col.value; ch.style.setProperty('--col', c.colore); cambiato(); });
        var x = bottone('✕', '', function () {
          var nomeVecchio = c.nome; d.categorie.splice(i, 1);
          d.frasi.forEach(function (f) { Object.keys(f.segni || {}).forEach(function (k) { if (f.segni[k] === nomeVecchio) delete f.segni[k]; }); });
          if (attiva === nomeVecchio) attiva = d.categorie[0] ? d.categorie[0].nome : null;
          ridisegnaTutto(); cambiato();
        });
        ch.appendChild(nome); ch.appendChild(col); ch.appendChild(x); chips.appendChild(ch);
      });
      boxCat.appendChild(chips);
      var sel = el('select'); sel.appendChild(new Option('+ aggiungi una categoria…', ''));
      CATEGORIE.forEach(function (c) { if (!d.categorie.some(function (x) { return x.nome === c[0]; })) sel.appendChild(new Option(c[0], c[0])); });
      sel.appendChild(new Option('altro (scrivi tu)…', '*'));
      sel.addEventListener('change', function () {
        var v = sel.value; if (!v) return;
        var col = '#555555';
        if (v === '*') { v = (prompt('Nome della categoria:') || '').trim(); } else CATEGORIE.forEach(function (c) { if (c[0] === v) col = c[1]; });
        if (v && !d.categorie.some(function (x) { return x.nome === v; })) { d.categorie.push({ nome: v, colore: col }); attiva = v; }
        ridisegnaTutto(); cambiato();
      });
      boxCat.appendChild(sel);
    }
    var boxFrasi = el('div');
    function ridisegnaTutto() { disegnaCat(); boxFrasi.textContent = ''; boxFrasi.appendChild(elencoFrasi()); }
    function colore(nome) { var c = d.categorie.filter(function (x) { return x.nome === nome; })[0]; return c ? c.colore : 'transparent'; }
    function elencoFrasi() {
      return elenco(d.frasi, function () { return { testo: '', segni: {}, spiegazione: '' }; }, function (riga, f) {
        f.segni = f.segni || {};
        var parole = el('p', 'frase-tocca editor-parole');
        function disegnaParole() {
          parole.textContent = '';
          var ws = VSEsercizio.parole(f.testo);
          if (!ws.length) { parole.appendChild(el('span', 'muto', 'Scrivi la frase qui sopra, poi tocca le parole per segnarle.')); return; }
          ws.forEach(function (w, i) {
            var b = el('button', 'parola' + (f.segni[i] ? ' segnata' : ''), w); b.type = 'button';
            b.style.setProperty('--col', f.segni[i] ? colore(f.segni[i]) : 'transparent'); b.title = f.segni[i] || 'tocca per segnare';
            b.addEventListener('click', function () { if (!attiva) return; if (f.segni[i] === attiva) delete f.segni[i]; else f.segni[i] = attiva; disegnaParole(); cambiato(); });
            parole.appendChild(b); parole.appendChild(document.createTextNode(' '));
          });
        }
        riga.appendChild(campoTesto('Frase', f.testo, function (v) {
          var vecchie = VSEsercizio.parole(f.testo), nuove = VSEsercizio.parole(v);
          if (vecchie.length !== nuove.length) f.segni = {};          // cambiano le parole: i segni non valgono più
          f.testo = v; disegnaParole();
        }, { placeholder: 'es. Luce mangia la pappa.' }));
        riga.appendChild(el('small', 'muto', 'Tocca le parole per segnarle con la categoria scelta (toccale di nuovo per togliere):'));
        riga.appendChild(parole); disegnaParole();
        riga.appendChild(campoTesto('Spiegazione', f.spiegazione, function (v) { f.spiegazione = v; }));
      }, 'frase');
    }
    editor.appendChild(boxCat); editor.appendChild(boxFrasi); ridisegnaTutto();
  };

  EDITOR.ordina = function (d) {
    d.frasi = d.frasi || [{ testo: '', spiegazione: '' }];
    editor.appendChild(el('p', 'muto', 'Scrivi la frase giusta: allo studente le parole arriveranno in disordine.'));
    editor.appendChild(elenco(d.frasi, function () { return { testo: '', spiegazione: '' }; }, function (riga, f) {
      riga.appendChild(campoTesto('Frase nell\'ordine giusto', f.testo, function (v) { f.testo = v; }, { placeholder: 'es. Vlad spiega la grammatica ai compagni.' }));
      riga.appendChild(campoTesto('Spiegazione', f.spiegazione, function (v) { f.spiegazione = v; }));
    }, 'frase'));
  };

  EDITOR.gruppi = function (d) {
    d.gruppi = d.gruppi || [{ nome: '', elementi: [] }, { nome: '', elementi: [] }];
    editor.appendChild(el('p', 'muto', 'Crea i gruppi e scrivi gli elementi di ciascuno, uno per riga. Lo studente li riceve mescolati.'));
    editor.appendChild(elenco(d.gruppi, function () { return { nome: '', elementi: [] }; }, function (riga, g) {
      riga.appendChild(campoTesto('Nome del gruppo', g.nome, function (v) { g.nome = v; }, { placeholder: 'es. Verbi' }));
      riga.appendChild(campoTesto('Elementi (uno per riga)', (g.elementi || []).join('\n'), function (v) { g.elementi = v.split('\n').map(function (s) { return s.trim(); }).filter(Boolean); }, { area: true, righe: 4 }));
    }, 'gruppo'));
  };

  EDITOR.aperta = function (d) {
    d.domande = d.domande || [{ testo: '', esempio: '' }];
    editor.appendChild(elenco(d.domande, function () { return { testo: '', esempio: '' }; }, function (riga, q) {
      riga.appendChild(campoTesto('Domanda', q.testo, function (v) { q.testo = v; }, { placeholder: 'es. Scrivi una frase con il soggetto sottinteso.' }));
      riga.appendChild(campoTesto('Risposta d\'esempio (si vede dopo)', q.esempio, function (v) { q.esempio = v; }, { area: true }));
    }, 'domanda'));
  };

  function scegli(t) {
    tipo = t; editor.textContent = '';
    if (!stati[t]) stati[t] = {};
    EDITOR[t](stati[t]);
    cambiato(true);
  }
  form.querySelectorAll('input[name=tipo]').forEach(function (r) { r.addEventListener('change', function () { if (r.checked) scegli(r.value); }); });
  if (tipoIniziale) scegli(tipoIniziale);
  form.addEventListener('submit', function () { campo.value = JSON.stringify(stati[tipo] || {}); });
})();
