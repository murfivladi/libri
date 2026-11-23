#!/usr/bin/env node
// Crea il PDF di una parte di «Chi fa cosa?» (A4, a colori) con Chromium (Playwright) e controlla l'impaginazione.
// Uso:  NODE_PATH=$(npm root -g) node scuola/chi-fa-cosa/build.js <file.html> [cartella-anteprime]
//   es. node scuola/chi-fa-cosa/build.js prova/impaginazione.html /tmp/anteprime
// Output: build/<nome>.pdf nella cartella di chi-fa-cosa. Con la cartella delle anteprime salva un PNG per pagina.
const path = require('path'), fs = require('fs');
const { chromium } = require('playwright');
const rel = process.argv[2], anteprime = process.argv[3] || '';
if (!rel) { console.error('Uso: node build.js <file.html> [cartella-anteprime]'); process.exit(1); }
const html = path.resolve(__dirname, rel), out = path.join(__dirname, 'build');

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || undefined });
  const page = await browser.newPage({ viewport: { width: 900, height: 1200 }, deviceScaleFactor: 2 });
  const errori = [];
  page.on('pageerror', e => errori.push(e.message));
  page.on('requestfailed', r => errori.push('manca: ' + r.url()));
  await page.goto('file://' + html, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  const controllo = await page.evaluate(() => {
    const prob = [];
    document.querySelectorAll('.pagina').forEach((p, i) => {
      const r = p.getBoundingClientRect(), piede = p.querySelector('.piede');
      const limite = piede ? piede.getBoundingClientRect().top - 2 : r.bottom;
      p.querySelectorAll('.es, .box, table, .dialogo, .racconto, p, .mappa, .intro, .scena').forEach(el => {
        if (el.closest('.piede')) return;
        const b = el.getBoundingClientRect();
        if (b.bottom > limite + 0.5) prob.push(`pagina ${i + 1}: «${(el.textContent || '').trim().slice(0, 40)}» esce di ${Math.round(b.bottom - limite)}px`);
        if (b.right > r.right + 0.5) prob.push(`pagina ${i + 1}: «${(el.textContent || '').trim().slice(0, 40)}» esce a destra`);
      });
    });
    document.querySelectorAll('img').forEach(im => { if (!im.complete || !im.naturalWidth) prob.push('immagine non caricata: ' + im.getAttribute('src')); });
    document.querySelectorAll('[data-qr]').forEach(q => { if (!q.querySelector('canvas, img')) prob.push('QR mancante: ' + q.dataset.qr); });
    return { pagine: document.querySelectorAll('.pagina').length, prob };
  });
  fs.mkdirSync(out, { recursive: true });
  const pdf = path.join(out, path.basename(html, '.html') + '.pdf');
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
