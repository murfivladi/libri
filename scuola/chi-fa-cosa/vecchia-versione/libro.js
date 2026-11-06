#!/usr/bin/env node
// Crea il libro intero di «Chi fa cosa?»: copertina + pagine iniziali (con l'indice) + tutti i capitoli pronti.
// L'indice si costruisce da solo leggendo i capitoli (capitolo-NN/capitolo.html): aggiungendo un capitolo, l'indice cresce.
// Uso:  NODE_PATH=$(npm root -g) node scuola/chi-fa-cosa/libro.js
// Output: build/chi-fa-cosa.pdf (libro), build/pagine-iniziali.pdf, copertina/chi-fa-cosa-copertina-fronte.png
const path = require('path'), fs = require('fs'), { execFileSync } = require('child_process');
const { chromium } = require('playwright');
const dir = __dirname, out = path.join(dir, 'build');
const capitoli = fs.readdirSync(dir).filter(d => /^capitolo-\d+$/.test(d) && fs.existsSync(path.join(dir, d, 'capitolo.html'))).sort();

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || undefined });
  const page = await browser.newPage({ viewport: { width: 900, height: 1200 }, deviceScaleFactor: 2 });
  const voci = [];
  for (const c of capitoli) {
    await page.goto('file://' + path.join(dir, c, 'capitolo.html'));
    voci.push(await page.evaluate(() => {
      const num = s => (s.querySelector('.piede .num') || {}).textContent || '';
      const ap = document.querySelector('.apertura'), righe = [];
      document.querySelectorAll('.pagina').forEach(s => {
        if (s === ap) return;
        const lez = s.querySelector('.titolo-lezione');
        const t = lez ? s.querySelector('.testata').textContent.trim() + ' · ' + lez.querySelector('h2').textContent.trim()
          : (s.querySelector('.esercizi-testa h2, .verifica-testa h2, .testata') || {}).textContent;
        if (t && (!righe.length || righe[righe.length - 1].t !== t.trim())) righe.push({ t: t.trim(), p: num(s) });
      });
      return { parte: ap.querySelector('.parte-nome').textContent.trim(), n: ap.querySelector('.numero').textContent.trim(),
        titolo: ap.querySelector('h1').textContent.trim(), p: num(ap), logica: document.body.classList.contains('parte-logica'), righe };
    }));
  }
  await page.goto('file://' + path.join(dir, 'inizio', 'inizio.html'), { waitUntil: 'networkidle' });
  await page.evaluate(voci => {
    const e = s => s.replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
    let h = '', parte = '';
    voci.forEach(v => {
      if (v.parte !== parte) { parte = v.parte; h += `<div class="parte-ind" style="background:${v.logica ? '#e8710a' : '#1f5fbf'}">${e(parte)}</div>`; }
      h += `<div class="cap-ind"><span class="n">${e(v.n)}</span><span>${e(v.titolo)}</span><span class="pg">${e(v.p)}</span></div>`;
      // le lezioni una per riga; il resto (per cominciare, esercizi, testo, mappa, verifica) in una riga sola
      const altro = [];
      v.righe.forEach(r => {
        if (/^Lezione/.test(r.t)) h += `<div class="riga"><span>${e(r.t.replace(/^Lezione (\d+) · /, '$1. '))}</span><span class="pg">${e(r.p)}</span></div>`;
        else altro.push(`${r.t.replace(/ · Capitolo \d+$/, '').replace('Dal testo alla grammatica', 'Testo').replace('Mappa del capitolo', 'Mappa').toLowerCase()} ${r.p}`);
      });
      if (altro.length) h += `<div class="altro">${e(altro.join(' · '))}</div>`;
    });
    document.getElementById('indice').innerHTML = h;
  }, voci);
  await page.evaluate(() => document.fonts.ready);
  const fuori = await page.evaluate(() => { const i = document.querySelector('.indice'), r = i.getBoundingClientRect(), u = i.querySelector('#indice').getBoundingClientRect(); return u.bottom > r.bottom - 30 * 3.78; });
  if (fuori) console.log('ATTENZIONE: l\'indice non sta più in una pagina: serve una seconda pagina di indice.');
  fs.mkdirSync(out, { recursive: true });
  const inizio = path.join(out, 'pagine-iniziali.pdf');
  await page.pdf({ path: inizio, format: 'A4', printBackground: true, preferCSSPageSize: true });
  await page.emulateMedia({ media: 'print' });
  await (await page.$('.copertina')).screenshot({ path: path.join(dir, 'copertina', 'chi-fa-cosa-copertina-fronte.png') });
  await browser.close();
  // unisce pagine iniziali e capitoli (i PDF dei capitoli si fanno con build.js)
  const pdf = [inizio, ...capitoli.map(c => path.join(dir, c, 'build', `chi-fa-cosa-${c}.pdf`))];
  pdf.forEach(f => { if (!fs.existsSync(f)) throw new Error('Manca ' + f + ': prima esegui build.js'); });
  const libro = path.join(out, 'chi-fa-cosa.pdf');
  execFileSync('python3', ['-c', 'import sys,pypdf\nw=pypdf.PdfWriter()\n[w.append(f) for f in sys.argv[2:]]\nw.write(sys.argv[1])', libro, ...pdf]);
  console.log(`${capitoli.length} capitoli nell'indice → ${path.relative(process.cwd(), libro)}`);
})();
