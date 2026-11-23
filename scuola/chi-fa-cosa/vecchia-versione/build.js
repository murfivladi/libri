#!/usr/bin/env node
// Crea il PDF di un capitolo di «Chi fa cosa?» (A4, a colori) con Chromium (Playwright).
// Uso:  NODE_PATH=$(npm root -g) node scuola/chi-fa-cosa/build.js capitolo-01 [cartella-anteprime]
// Output: scuola/chi-fa-cosa/capitolo-01/build/chi-fa-cosa-capitolo-01.pdf
// Con la cartella delle anteprime salva anche un PNG per ogni pagina (per controllare l'impaginazione).
const path = require('path'), fs = require('fs');
const { chromium } = require('playwright');
const cap = process.argv[2] || 'capitolo-01', anteprime = process.argv[3] || '';
const dir = path.join(__dirname, cap), html = path.join(dir, 'capitolo.html'), out = path.join(dir, 'build');

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || undefined });
  const page = await browser.newPage({ viewport: { width: 900, height: 1200 }, deviceScaleFactor: 2 });
  const errori = [];
  page.on('pageerror', e => errori.push(e.message));
  page.on('requestfailed', r => errori.push('manca: ' + r.url()));
  await page.goto('file://' + html, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  // controlli: niente testo che esce dalla pagina, tutte le immagini caricate, QR disegnati
  const controllo = await page.evaluate(() => {
    const prob = [];
    document.querySelectorAll('.pagina').forEach((p, i) => {
      const r = p.getBoundingClientRect(), piede = p.querySelector('.piede');
      const limite = piede ? piede.getBoundingClientRect().top - 2 : r.bottom;
      p.querySelectorAll('.es, .box, table, .fumetto, .lettura, .due-col, .riassunto, .errori-comuni, .punteggio, .auto, .definizione, p, .mappa').forEach(el => {
        if (el.closest('.piede')) return;
        const b = el.getBoundingClientRect();
        if (b.bottom > limite + 0.5) prob.push(`pagina ${i + 1}: «${(el.textContent || '').trim().slice(0, 40)}» esce di ${Math.round(b.bottom - limite)}px`);
      });
    });
    document.querySelectorAll('img').forEach(im => { if (!im.complete || !im.naturalWidth) prob.push('immagine non caricata: ' + im.getAttribute('src')); });
    document.querySelectorAll('[data-qr]').forEach(q => { if (!q.querySelector('canvas, img')) prob.push('QR mancante: ' + q.dataset.qr); });
    return { pagine: document.querySelectorAll('.pagina').length, prob };
  });
  fs.mkdirSync(out, { recursive: true });
  const pdf = path.join(out, 'chi-fa-cosa-' + cap + '.pdf');
  await page.pdf({ path: pdf, format: 'A4', printBackground: true, preferCSSPageSize: true });
  if (anteprime) {
    fs.mkdirSync(anteprime, { recursive: true });
    await page.emulateMedia({ media: 'print' });
    const pagine = await page.$$('.pagina');
    for (let i = 0; i < pagine.length; i++) await pagine[i].screenshot({ path: path.join(anteprime, `pagina-${String(i + 1).padStart(2, '0')}.png`) });
  }
  await browser.close();
  console.log(`${controllo.pagine} pagine → ${path.relative(process.cwd(), pdf)}`);
  controllo.prob.concat(errori).forEach(p => console.log('ATTENZIONE: ' + p));
  process.exit(controllo.prob.length || errori.length ? 2 : 0);
})();
