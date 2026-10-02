// Comodità dell'admin: tema, menu mobile, scorciatoie, seleziona tutto, avviso modifiche non salvate.
(function () {
  var b = document.body;
  function leggi(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function scrivi(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  if (leggi('adm-tema') === 'scuro') b.classList.add('scuro');
  var tema = document.getElementById('tema');
  if (tema) tema.onclick = function () { b.classList.toggle('scuro'); scrivi('adm-tema', b.classList.contains('scuro') ? 'scuro' : 'chiaro'); };
  var ap = document.getElementById('apri-menu'), menu = document.getElementById('menu');
  if (ap && menu) ap.onclick = function () { menu.classList.toggle('aperto'); };

  // seleziona tutto
  document.querySelectorAll('[data-tutti]').forEach(function (c) {
    c.addEventListener('change', function () {
      document.querySelectorAll('input[name="ids[]"]').forEach(function (x) { x.checked = c.checked; });
    });
  });
  // conferma azioni pericolose
  document.querySelectorAll('[data-conferma]').forEach(function (el) {
    el.addEventListener('click', function (ev) { if (!confirm(el.getAttribute('data-conferma'))) ev.preventDefault(); });
  });
  // conferma per le azioni di massa distruttive
  document.querySelectorAll('[data-conferma-massa]').forEach(function (bt) {
    bt.addEventListener('click', function (ev) {
      var s = document.getElementById('azione-massa');
      var n = document.querySelectorAll('input[name="ids[]"]:checked').length;
      if (!n) { alert('Seleziona almeno un libro.'); ev.preventDefault(); return; }
      if (s && (s.value === 'elimina_def' || s.value === 'cestino') && !confirm('Confermi l\'azione su ' + n + ' libri?')) ev.preventDefault();
    });
  });
  // mostra solo il campo extra giusto nelle azioni di massa
  var az = document.getElementById('azione-massa');
  if (az) {
    var agg = function () {
      document.querySelectorAll('[data-per]').forEach(function (x) { x.style.display = x.getAttribute('data-per').split(' ').indexOf(az.value) >= 0 ? '' : 'none'; });
    };
    az.addEventListener('change', agg); agg();
  }
  // avviso modifiche non salvate
  var f = document.querySelector('form[data-avvisa]');
  if (f) {
    var sporco = false;
    f.addEventListener('input', function () { sporco = true; });
    f.addEventListener('submit', function () { sporco = false; });
    window.addEventListener('beforeunload', function (ev) { if (sporco) { ev.preventDefault(); ev.returnValue = ''; } });
  }
  // contatore caratteri
  document.querySelectorAll('[data-conta]').forEach(function (i) {
    var out = document.getElementById(i.getAttribute('data-conta'));
    var agg2 = function () { if (out) out.textContent = i.value.length + ' caratteri'; };
    i.addEventListener('input', agg2); agg2();
  });
  // copia negli appunti
  document.querySelectorAll('[data-copia]').forEach(function (bt) {
    bt.addEventListener('click', function () {
      var t = bt.getAttribute('data-copia');
      if (navigator.clipboard) navigator.clipboard.writeText(t).then(function () { bt.textContent = 'Copiato ✓'; });
      else { prompt('Copia il link:', t); }
    });
  });
  // scorciatoie
  var g = false;
  document.addEventListener('keydown', function (ev) {
    var t = ev.target.tagName;
    if (t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT' || ev.ctrlKey || ev.metaKey || ev.altKey) return;
    if (ev.key === '/') { var c = document.getElementById('cerca'); if (c) { ev.preventDefault(); c.focus(); } }
    else if (ev.key === 'n') location.href = 'libro.php';
    else if (ev.key === '?') { var h = document.getElementById('aiuto'); if (h) h.hidden = !h.hidden; }
    else if (ev.key === 'g') { g = true; setTimeout(function () { g = false; }, 1200); }
    else if (g) { var m = { l: 'libri.php', c: 'collane.php', d: 'index.php', p: 'pagine.php', s: 'impostazioni.php' }; if (m[ev.key]) location.href = m[ev.key]; g = false; }
  });
})();
