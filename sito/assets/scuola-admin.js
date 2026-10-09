// Admin Scuola: QR code dei codici, scelta della fonte di un contenuto e registrazione dal microfono.
(function () {
  'use strict';
  // ---------- QR code (si stampano nel libro accanto al simbolo) ----------
  var box = document.getElementById('qr-box');
  document.querySelectorAll('[data-qr]').forEach(function (b) {
    b.addEventListener('click', function () {
      var img = document.getElementById('qr-img'); img.textContent = '';
      new QRCode(img, { text: b.dataset.qr, width: 512, height: 512, correctLevel: QRCode.CorrectLevel.M });
      var canvas = img.querySelector('canvas'); if (canvas) { canvas.style.width = '220px'; canvas.style.height = '220px'; }
      document.getElementById('qr-testo').textContent = b.dataset.nome + ' → ' + b.dataset.qr;
      var a = document.getElementById('qr-scarica'); a.download = 'qr-' + b.dataset.nome + '.png';
      setTimeout(function () { var c = img.querySelector('canvas'), i = img.querySelector('img'); a.href = c ? c.toDataURL('image/png') : (i ? i.src : '#'); }, 60);
      box.hidden = false; box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });

  // ---------- contenuti: file, registrazione o voce AI ----------
  var form = document.getElementById('form-co') || document.getElementById('form-vlad');
  if (!form) return;
  var massimo = form.id === 'form-vlad' ? 15 : 0;               // voce di Vlad per la copia AI: 5–15 secondi
  function mostraFonte() {
    var f = (form.querySelector('input[name=fonte]:checked') || {}).value;
    form.querySelectorAll('[data-fonte]').forEach(function (d) { d.hidden = d.dataset.fonte !== f; });
  }
  form.querySelectorAll('input[name=fonte]').forEach(function (r) { r.addEventListener('change', mostraFonte); });
  mostraFonte();

  var via = document.getElementById('reg-via'), stop = document.getElementById('reg-stop'), stato = document.getElementById('reg-stato');
  var tempo = document.getElementById('reg-tempo'), ascolta = document.getElementById('reg-ascolta'), reg = document.getElementById('registratore');
  var input = document.getElementById('file-media'), rec = null, pezzi = [], inizio = 0, timer = null, flusso = null;
  if (!window.MediaRecorder || !navigator.mediaDevices) { via.disabled = true; stato.textContent = 'Questo browser non permette di registrare: usa Chrome, Edge, Firefox o Safari recenti (con https).'; return; }
  function tipoMigliore() {
    var tipi = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/webm'];
    for (var i = 0; i < tipi.length; i++) if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(tipi[i])) return tipi[i];
    return '';
  }
  via.addEventListener('click', function () {
    navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }).then(function (s) {
      flusso = s; pezzi = []; var t = tipoMigliore();
      rec = new MediaRecorder(s, t ? { mimeType: t } : undefined);
      rec.ondataavailable = function (e) { if (e.data.size) pezzi.push(e.data); };
      rec.onstop = function () {
        flusso.getTracks().forEach(function (tr) { tr.stop(); });
        var tipo = (rec.mimeType || 'audio/webm').split(';')[0], est = tipo.indexOf('mp4') >= 0 ? 'm4a' : tipo.indexOf('ogg') >= 0 ? 'ogg' : 'webm';
        var blob = new Blob(pezzi, { type: tipo });
        if (massimo && Date.now() - inizio < 5000) stato.textContent = 'Troppo corta: servono almeno 5 secondi. Registra di nuovo.';
        ascolta.src = URL.createObjectURL(blob); ascolta.hidden = false;
        try {                                      // la registrazione diventa il «file» del modulo
          var dt = new DataTransfer(); dt.items.add(new File([blob], 'registrazione.' + est, { type: tipo })); input.files = dt.files;
          stato.textContent = 'Registrazione pronta (' + Math.round(blob.size / 1024) + ' KB). Riascoltala e premi «Salva».';
          form.dispatchEvent(new Event('input', { bubbles: true }));
        } catch (e) { stato.textContent = 'Questo browser non permette di allegare la registrazione: scaricala e caricala come file.'; }
      };
      rec.start(250); inizio = Date.now(); reg.classList.add('attivo'); via.disabled = true; stop.disabled = false;
      stato.textContent = 'Sto registrando… parla vicino al microfono.';
      timer = setInterval(function () {
        var s = Math.floor((Date.now() - inizio) / 1000); tempo.textContent = Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2) + (massimo ? ' / 0:' + massimo : '');
        if (massimo && s >= massimo) stop.click();
      }, 250);
    }).catch(function () { stato.textContent = 'Il microfono non è disponibile o non hai dato il permesso.'; });
  });
  stop.addEventListener('click', function () { if (rec && rec.state !== 'inactive') rec.stop(); clearInterval(timer); reg.classList.remove('attivo'); via.disabled = false; stop.disabled = true; });
})();
