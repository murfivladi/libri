// Nel browser: il sito secondario (B) mostra copertine e apre il PDF che stanno sul sito principale (A), con la politica CORS vera.
// Lo lancia duesiti.sh quando c'è la variabile CON_BROWSER; BASE_B = indirizzo del sito secondario.
const { chromium } = require('playwright');
const fs = require('fs');
const B = process.env.BASE_B;
let ok = 0, ko = 0;
const chk = (nome, cond, extra) => { if (cond) ok++; else { ko++; console.log('FAIL:', nome, extra === undefined ? '' : extra); } };

function trovaChromium() {
  if (process.env.CHROMIUM) return process.env.CHROMIUM;
  const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
  try {
    const d = fs.readdirSync(base).filter(x => x.startsWith('chromium-'))[0];
    const p = `${base}/${d}/chrome-linux/chrome`;
    if (fs.existsSync(p)) return p;
  } catch (e) {}
  return undefined;
}

(async () => {
  const browser = await chromium.launch({ executablePath: trovaChromium(), args: ['--no-sandbox'] });
  const page = await (await browser.newContext({ viewport: { width: 1100, height: 900 } })).newPage();
  const errori = [];
  page.on('pageerror', e => errori.push(String(e)));
  page.on('requestfailed', r => errori.push('richiesta fallita: ' + r.url()));
  page.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errori.push('console: ' + m.text()); });
  page.on('response', r => { if (r.status() >= 400) errori.push(r.status() + ' ' + r.url()); });   // mostra QUALE risorsa manca

  // catalogo del secondario: tutte le copertine (che vengono dal principale) si caricano davvero
  await page.goto(B + '/');
  await page.waitForLoadState('networkidle');
  const immagini = await page.$$eval('.scheda img', is => is.map(i => ({ src: i.currentSrc, w: i.naturalWidth })));
  chk('catalogo B: ci sono le copertine', immagini.length >= 4, immagini.length);
  chk('catalogo B: tutte le copertine si vedono', immagini.every(i => i.w > 0), JSON.stringify(immagini.filter(i => i.w === 0)));
  chk('catalogo B: le copertine non vengono dal secondario', immagini.every(i => !i.src.startsWith(B)));

  // lettore del secondario: PDF.js scarica il PDF da un altro indirizzo (CORS + Range)
  const richiestePdf = [];
  page.on('response', r => { if (r.url().includes('pdf.php')) richiestePdf.push(r.status()); });
  await page.goto(B + '/leggi.php?s=' + (process.env.SLUG || 'il-custode-dell-etna'));
  await page.waitForFunction(() => /^\d+$/.test(document.getElementById('tot').textContent), null, { timeout: 30000 });
  const totale = parseInt(await page.textContent('#tot'));
  chk('lettore B: PDF aperto (più pagine)', totale > 1, totale);
  await page.waitForTimeout(900);
  const pieno = await page.evaluate(() => { const c = document.getElementById('tela'); const d = c.getContext('2d').getImageData(0, 0, c.width, c.height).data; let k = 0; for (let i = 0; i < d.length; i += 400) if (d[i] < 200) k++; return k; });
  chk('lettore B: la pagina è disegnata', pieno > 20, pieno);
  await page.click('#avanti'); await page.waitForTimeout(700);
  chk('lettore B: cambio pagina', (await page.inputValue('#vai')) === '2');
  chk('lettore B: il PDF arriva dal principale (pdf.php)', richiestePdf.length > 0 && richiestePdf.every(s => s === 200 || s === 206), richiestePdf.join());
  chk('nessun errore (CORS compreso)', errori.length === 0, errori.slice(0, 3));

  console.log(`browser due siti: OK=${ok} KO=${ko}`);
  await browser.close();
  process.exit(ko ? 1 : 0);
})().catch(e => { console.log('ERRORE TEST', e); process.exit(1); });
