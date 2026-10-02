// Test nel browser (Chromium + Playwright): lettore PDF, trascinamento con mouse e con dita, admin su telefono.
// Lo avvia browser.sh, che prepara un sito temporaneo e passa BASE e PASSWORD.
const { chromium } = require('playwright');
const fs = require('fs');
const B = process.env.BASE || 'http://127.0.0.1:8099';
const PW = process.env.PASSWORD || 'provaprova12';
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
  return undefined;   // lascia scegliere a Playwright
}

async function accedi(page) {
  await page.goto(B + '/admin/login.php');
  await page.fill('input[name=password]', PW);
  await page.click('button.bottone');
  await page.waitForURL(/admin\/index\.php/);
}

const ordineRighe = page => page.$$eval('table[data-ordina] tbody tr', rs => rs.map(r => r.getAttribute('data-id')));

(async () => {
  const exe = trovaChromium();
  const browser = await chromium.launch({ executablePath: exe, args: ['--no-sandbox'] });
  const errori = [];

  // ================= ADMIN: trascinamento =================
  const a = await browser.newContext({ viewport: { width: 1200, height: 900 } });
  const pa = await a.newPage();
  pa.on('pageerror', e => errori.push('admin: ' + String(e)));
  await accedi(pa);
  await pa.goto(B + '/admin/libri.php');
  const prima1 = await ordineRighe(pa);
  chk('admin: 5 libri con maniglia', prima1.length === 5 && (await pa.locator('table[data-ordina] .maniglia').count()) === 5, prima1.length);

  // mouse: porto il primo libro sotto il terzo
  const man = i => pa.locator('table[data-ordina] tbody tr').nth(i).locator('.maniglia');
  let b0 = await man(0).boundingBox(), b2 = await man(2).boundingBox();
  await pa.mouse.move(b0.x + b0.width / 2, b0.y + b0.height / 2);
  await pa.mouse.down();
  await pa.mouse.move(b0.x + b0.width / 2, b0.y + 40, { steps: 5 });
  await pa.mouse.move(b0.x + b0.width / 2, b2.y + b2.height / 2 + 12, { steps: 12 });
  await pa.mouse.up();
  await pa.waitForSelector('#toast', { timeout: 5000 });
  const dopoMouse = await ordineRighe(pa);
  chk('mouse: l\'ordine è cambiato', dopoMouse.join() !== prima1.join() && dopoMouse[2] === prima1[0], dopoMouse.join() + ' <- ' + prima1.join());
  chk('mouse: toast "Ordine salvato"', (await pa.textContent('#toast')).includes('salvato'));
  await pa.reload();
  chk('mouse: l\'ordine è rimasto dopo il ricaricamento', (await ordineRighe(pa)).join() === dopoMouse.join());

  // dita (telefono): evento touch reale tramite il protocollo di Chrome
  const t = await browser.newContext({ viewport: { width: 390, height: 800 }, hasTouch: true, isMobile: true });
  const pt = await t.newPage();
  pt.on('pageerror', e => errori.push('admin-touch: ' + String(e)));
  await accedi(pt);
  await pt.goto(B + '/admin/libri.php');
  chk('telefono: admin senza scroll orizzontale della pagina', !(await pt.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2)));
  const prima2 = await ordineRighe(pt);
  const cdp = await t.newCDPSession(pt);
  const hb = await pt.locator('table[data-ordina] tbody tr').nth(0).locator('.maniglia').boundingBox();
  const h3 = await pt.locator('table[data-ordina] tbody tr').nth(3).locator('.maniglia').boundingBox();
  const x = hb.x + hb.width / 2, y0 = hb.y + hb.height / 2, y1 = h3.y + h3.height / 2 + 14;
  await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y: y0 }] });
  for (let i = 1; i <= 14; i++) await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x, y: y0 + (y1 - y0) * i / 14 }] });
  await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
  await pt.waitForSelector('#toast', { timeout: 5000 });
  const dopoTouch = await ordineRighe(pt);
  chk('dita: l\'ordine è cambiato', dopoTouch.join() !== prima2.join() && dopoTouch.indexOf(prima2[0]) >= 2, dopoTouch.join() + ' <- ' + prima2.join());
  await pt.reload();
  chk('dita: l\'ordine è salvato', (await ordineRighe(pt)).join() === dopoTouch.join());

  // collane e pagine con il mouse
  for (const [pag, tipo] of [['collane.php', 'collane'], ['pagine.php', 'pagine']]) {
    await pa.goto(B + '/admin/' + pag);
    const p0 = await ordineRighe(pa);
    if (p0.length < 2) { chk(tipo + ': servono 2 righe per provare', false, p0.length); continue; }
    const h0 = await pa.locator('table[data-ordina] tbody tr').nth(0).locator('.maniglia').boundingBox();
    const h1 = await pa.locator('table[data-ordina] tbody tr').nth(1).locator('.maniglia').boundingBox();
    await pa.mouse.move(h0.x + h0.width / 2, h0.y + h0.height / 2); await pa.mouse.down();
    await pa.mouse.move(h0.x + h0.width / 2, h1.y + h1.height / 2 + 14, { steps: 10 }); await pa.mouse.up();
    await pa.waitForFunction(() => { const t = document.getElementById('toast'); return t && /salvato/.test(t.textContent) && t.style.opacity === '1'; }, null, { timeout: 5000 });
    await pa.reload();
    const p1 = await ordineRighe(pa);
    chk(tipo + ': trascinamento salvato', p1[0] === p0[1] && p1[1] === p0[0], p0.join() + ' -> ' + p1.join());
  }

  // pagine admin su telefono: nessuna pagina esce dallo schermo
  for (const pag of ['index.php', 'libri.php', 'libro.php?id=1', 'collane.php', 'strumenti.php', 'aspetto.php', 'sicurezza.php']) {
    await pt.goto(B + '/admin/' + pag);
    chk('telefono: admin/' + pag + ' senza scroll orizzontale', !(await pt.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2)));
  }
  await pt.screenshot({ path: process.env.SCREENSHOT_DIR ? process.env.SCREENSHOT_DIR + '/admin-telefono.png' : '/dev/null' }).catch(() => {});

  console.log('errori JS/rete:', errori.length ? errori.slice(0, 5) : 'nessuno');
  chk('nessun errore JS o di rete', errori.length === 0);
  console.log(`browser: OK=${ok} KO=${ko}`);
  await browser.close();
  process.exit(ko ? 1 : 0);
})().catch(e => { console.log('ERRORE TEST', e); process.exit(1); });
