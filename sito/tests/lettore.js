// Il lettore a libro nel browser (Chromium + Playwright): pagine che si girano, zoom e spostamento con mouse e con le dita.
// Lo avvia browser.sh; BASE = indirizzo del sito, SLUG = libro da aprire.
const { chromium } = require('playwright');
const fs = require('fs');
const B = process.env.BASE || 'http://127.0.0.1:8098';
const SLUG = process.env.SLUG || 'il-custode-dell-etna';
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

const pronto = p => p.waitForFunction(() => /^\d+$/.test(document.getElementById('tot').textContent) && document.querySelectorAll('.pagina-pdf.pronta').length > 0, null, { timeout: 40000 });
const pagina = p => p.inputValue('#vai').then(Number);
const zoomStato = p => p.evaluate(() => {
  const m = new DOMMatrixReadOnly(getComputedStyle(document.getElementById('zoom')).transform);
  const r = document.getElementById('scena').getBoundingClientRect();
  return { z: m.a, tx: m.e, ty: m.f, W: r.width, H: r.height, left: r.left, top: r.top };
});
// il punto del libro che stava sotto (fx, fy) prima dello zoom è ancora lì?
const fisso = (s, fx, fy) => Math.abs((fx - s.tx) / s.z - fx) < 2.5 && Math.abs((fy - s.ty) / s.z - fy) < 2.5;

(async () => {
  const browser = await chromium.launch({ executablePath: trovaChromium(), args: ['--no-sandbox'] });
  const errori = [];

  // ================= DESKTOP =================
  const page = await (await browser.newContext({ viewport: { width: 1200, height: 900 } })).newPage();
  page.on('pageerror', e => errori.push(String(e)));
  page.on('requestfailed', r => errori.push('richiesta fallita: ' + r.url()));
  page.on('response', r => { if (r.status() >= 400) errori.push(r.status() + ' ' + r.url()); });
  await page.goto(B + '/leggi.php?s=' + SLUG);
  await pronto(page);
  const totale = parseInt(await page.textContent('#tot'));
  chk('desktop: più di una pagina', totale > 10, totale);
  chk('desktop: la copertina è disegnata', await page.evaluate(() => { const c = document.querySelector('.pagina-pdf canvas'); const d = c.getContext('2d').getImageData(0, 0, c.width, c.height).data; let k = 0; for (let i = 0; i < d.length; i += 800) if (d[i] < 200 || d[i + 1] < 200) k++; return k > 20; }));
  chk('desktop: le pagine non sono tutte disegnate (solo le vicine)', (await page.locator('.pagina-pdf.pronta').count()) < totale / 2);
  chk('desktop: libro vero (StPageFlip)', await page.evaluate(() => !!document.querySelector('.stf__parent .stf__block')));
  await page.screenshot({ path: (process.env.SCREENSHOT_DIR || '/tmp') + '/lettore-copertina.png' });

  // la pagina si gira davvero, con un'animazione
  await page.click('#avanti');
  await page.waitForTimeout(250);
  chk('desktop: durante il giro la pagina non è ancora cambiata (c\'è un\'animazione)', (await pagina(page)) === 1);
  await page.screenshot({ path: (process.env.SCREENSHOT_DIR || '/tmp') + '/lettore-giro.png' });
  await page.waitForTimeout(1100);
  chk('desktop: avanti -> doppia pagina 2-3', (await pagina(page)) === 2 && (await page.textContent('#coppia')) === '–3', await page.textContent('#coppia'));
  await page.waitForTimeout(500);
  await page.screenshot({ path: (process.env.SCREENSHOT_DIR || '/tmp') + '/lettore-doppia.png' });
  await page.keyboard.press('ArrowRight'); await page.waitForTimeout(1300);
  chk('desktop: tastiera avanti', (await pagina(page)) === 4, await pagina(page));
  await page.keyboard.press('ArrowLeft'); await page.waitForTimeout(1300);
  chk('desktop: tastiera indietro', (await pagina(page)) === 2, await pagina(page));
  await page.fill('#vai', '9'); await page.press('#vai', 'Enter'); await page.waitForTimeout(900);
  chk('desktop: vai a pagina', Math.abs((await pagina(page)) - 9) <= 1, await pagina(page));
  await page.waitForTimeout(600);
  chk('desktop: la pagina di arrivo è disegnata', await page.evaluate(() => document.querySelectorAll('.pagina-pdf.pronta').length >= 2));
  await page.click('#b-segna'); await page.waitForTimeout(200);
  chk('desktop: segnalibro', (await page.textContent('#b-segna')) === '★');
  await page.click('#b-pannello'); await page.click('[data-tab=segnalibri]');
  chk('desktop: segnalibro in elenco', /Pagina \d+/.test(await page.textContent('#tab-segnalibri')));
  await page.click('[data-tab=cerca]');
  await page.fill('#testo-cerca', 'Vlad'); await page.press('#testo-cerca', 'Enter');
  await page.waitForFunction(() => /risultati|Nessun/.test(document.getElementById('stato-cerca').textContent), null, { timeout: 60000 });
  chk('desktop: la ricerca trova risultati', (await page.locator('#risultati li').count()) > 0);
  await page.click('#risultati li >> nth=3'); await page.waitForTimeout(1500);
  chk('desktop: il risultato porta alla pagina giusta', (await pagina(page)) > 1);
  await page.click('#chiudi-pannello');

  // zoom con la rotella (Ctrl): il punto sotto il mouse resta fermo
  let s = await zoomStato(page);
  chk('desktop: all\'inizio zoom 1', s.z === 1);
  const fx = 700, fy = 380;   // posizioni nella pagina (non nella scena): si convertono sotto
  await page.mouse.move(s.left + fx, s.top + fy);
  await page.keyboard.down('Control'); await page.mouse.wheel(0, -120); await page.keyboard.up('Control');
  await page.waitForTimeout(300);
  s = await zoomStato(page);
  chk('desktop: Ctrl+rotella ingrandisce', s.z > 1.2, s.z);
  chk('desktop: lo zoom resta sul punto del mouse (non nell\'angolo)', fisso(s, fx, fy), JSON.stringify(s));
  // trascinando si sposta l'ingrandimento e le pagine non si girano
  const prima = await pagina(page), s0 = await zoomStato(page);
  await page.mouse.move(s.left + 600, s.top + 400); await page.mouse.down();
  await page.mouse.move(s.left + 640, s.top + 430, { steps: 6 }); await page.mouse.up();
  await page.waitForTimeout(200);
  const s1 = await zoomStato(page);
  chk('desktop: trascinare sposta l\'ingrandimento', Math.abs(s1.tx - s0.tx) > 10 || Math.abs(s1.ty - s0.ty) > 10, JSON.stringify([s0, s1]));
  chk('desktop: da ingrandito non si girano le pagine', (await pagina(page)) === prima);
  chk('desktop: lo spostamento non esce dal libro', s1.tx <= 0.5 && s1.tx >= s.W - s.W * s1.z - 0.5 && s1.ty <= 0.5 && s1.ty >= s.H - s.H * s1.z - 0.5, JSON.stringify(s1));
  await page.keyboard.press('0'); await page.waitForTimeout(400);
  chk('desktop: il tasto 0 torna a zoom 1', (await zoomStato(page)).z === 1);
  // doppio clic: zoom sul punto cliccato; di nuovo: torna normale
  await page.mouse.click(s.left + 900, s.top + 500); await page.waitForTimeout(80);
  await page.mouse.click(s.left + 900, s.top + 500); await page.waitForTimeout(500);
  s = await zoomStato(page);
  chk('desktop: doppio clic ingrandisce', s.z > 2, s.z);
  chk('desktop: il doppio clic ingrandisce sul punto, non nell\'angolo', fisso(s, 900, 500), JSON.stringify(s));
  await page.mouse.click(s.left + 500, s.top + 400); await page.waitForTimeout(80);
  await page.mouse.click(s.left + 500, s.top + 400); await page.waitForTimeout(500);
  chk('desktop: secondo doppio clic: torna normale', (await zoomStato(page)).z === 1);
  await page.click('#piu'); await page.waitForTimeout(300);
  s = await zoomStato(page);
  chk('desktop: il pulsante + ingrandisce dal centro', s.z > 1.2 && fisso(s, s.W / 2, s.H / 2), JSON.stringify(s));
  await page.keyboard.press('Escape'); await page.waitForTimeout(400);
  chk('desktop: Esc torna a zoom 1', (await zoomStato(page)).z === 1);
  await page.click('#tema');
  chk('desktop: tema scuro', await page.evaluate(() => document.body.classList.contains('scuro')));
  await page.reload(); await pronto(page); await page.waitForTimeout(500);
  chk('desktop: riprende dalla pagina salvata', (await pagina(page)) > 1);
  chk('desktop: il lettore sta nello schermo (nessuno scroll)', !(await page.evaluate(() => document.documentElement.scrollHeight > window.innerHeight + 2 || document.documentElement.scrollWidth > window.innerWidth + 2)));

  // ================= TELEFONO (dita vere tramite il protocollo di Chrome) =================
  const mc = await browser.newContext({ viewport: { width: 390, height: 780 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2 });
  const pm = await mc.newPage();
  pm.on('pageerror', e => errori.push('telefono: ' + String(e)));
  await pm.goto(B + '/leggi.php?s=' + SLUG);
  await pronto(pm); await pm.waitForTimeout(700);
  const cdp = await mc.newCDPSession(pm);
  const tocco = (tipo, punti) => cdp.send('Input.dispatchTouchEvent', { type: tipo, touchPoints: punti.map((p, i) => ({ x: p[0], y: p[1], id: i })) });
  chk('telefono: una pagina alla volta', await pm.evaluate(() => document.querySelector('.stf__parent').getBoundingClientRect().width < 700));
  chk('telefono: niente scroll orizzontale', !(await pm.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2)));
  await pm.screenshot({ path: (process.env.SCREENSHOT_DIR || '/tmp') + '/lettore-telefono.png' });

  // i pulsanti girano la pagina anche quando il libro è a una pagina sola e centrato in verticale
  chk('telefono: parte dalla copertina', (await pagina(pm)) === 1);
  await pm.click('#avanti'); await pm.waitForTimeout(1400);
  chk('telefono: il pulsante › gira la pagina', (await pagina(pm)) === 2, await pagina(pm));
  await pm.click('#indietro'); await pm.waitForTimeout(1400);
  chk('telefono: il pulsante ‹ torna indietro', (await pagina(pm)) === 1, await pagina(pm));
  // strisciata veloce: gira la pagina
  await tocco('touchStart', [[300, 400]]);
  for (let i = 1; i <= 4; i++) { await tocco('touchMove', [[300 - i * 45, 405]]); await pm.waitForTimeout(15); }
  await tocco('touchEnd', []);
  await pm.waitForTimeout(1300);
  chk('telefono: strisciata = pagina girata', (await pagina(pm)) === 2, await pagina(pm));
  await tocco('touchStart', [[100, 400]]);
  for (let i = 1; i <= 4; i++) { await tocco('touchMove', [[100 + i * 45, 405]]); await pm.waitForTimeout(15); }
  await tocco('touchEnd', []);
  await pm.waitForTimeout(1300);
  chk('telefono: strisciata inversa = pagina indietro', (await pagina(pm)) === 1, await pagina(pm));
  await tocco('touchStart', [[300, 400]]);
  for (let i = 1; i <= 4; i++) { await tocco('touchMove', [[300 - i * 45, 405]]); await pm.waitForTimeout(15); }
  await tocco('touchEnd', []); await pm.waitForTimeout(1300);
  const pg = await pagina(pm);

  // pizzico: lo zoom si ferma sul punto tra le dita
  let t0 = await zoomStato(pm);
  const px = 200, py = 350;                          // coordinate sullo schermo
  const f = [px - t0.left, py - t0.top];
  await tocco('touchStart', [[px - 50, py], [px + 50, py]]);
  for (let i = 1; i <= 10; i++) { await tocco('touchMove', [[px - 50 - i * 12, py], [px + 50 + i * 12, py]]); await pm.waitForTimeout(12); }
  await tocco('touchEnd', []);
  await pm.waitForTimeout(500);
  let t1 = await zoomStato(pm);
  chk('telefono: il pizzico ingrandisce', t1.z > 2, t1.z);
  chk('telefono: il pizzico ingrandisce sul punto tra le dita (non nell\'angolo)', fisso(t1, f[0], f[1]), JSON.stringify([f, t1]));
  chk('telefono: durante il pizzico la pagina non si gira', (await pagina(pm)) === pg);
  // un dito: sposta l'ingrandimento
  await tocco('touchStart', [[200, 400]]);
  for (let i = 1; i <= 6; i++) { await tocco('touchMove', [[200 + i * 8, 400 + i * 6]]); await pm.waitForTimeout(12); }
  await tocco('touchEnd', []);
  await pm.waitForTimeout(300);
  const t2 = await zoomStato(pm);
  chk('telefono: un dito sposta l\'ingrandimento', Math.abs(t2.tx - t1.tx) > 15 || Math.abs(t2.ty - t1.ty) > 10, JSON.stringify([t1, t2]));
  chk('telefono: da ingrandito la strisciata non gira la pagina', (await pagina(pm)) === pg);
  chk('telefono: lo spostamento resta dentro il libro', t2.tx <= 0.5 && t2.tx >= t2.W - t2.W * t2.z - 0.5 && t2.ty <= 0.5 && t2.ty >= t2.H - t2.H * t2.z - 0.5, JSON.stringify(t2));
  // pizzico al contrario: si torna a 1
  await tocco('touchStart', [[100, 350], [300, 350]]);
  for (let i = 1; i <= 12; i++) { await tocco('touchMove', [[100 + i * 8, 350], [300 - i * 8, 350]]); await pm.waitForTimeout(12); }
  await tocco('touchEnd', []);
  await pm.waitForTimeout(500);
  chk('telefono: pizzico al contrario: torna a zoom 1', (await zoomStato(pm)).z === 1, JSON.stringify(await zoomStato(pm)));
  // doppio tocco
  const dt = [90, 300]; t0 = await zoomStato(pm);
  for (let k = 0; k < 2; k++) { await tocco('touchStart', [dt]); await pm.waitForTimeout(40); await tocco('touchEnd', []); await pm.waitForTimeout(90); }
  await pm.waitForTimeout(500);
  t1 = await zoomStato(pm);
  chk('telefono: doppio tocco ingrandisce sul punto', t1.z > 2 && fisso(t1, dt[0] - t0.left, dt[1] - t0.top), JSON.stringify(t1));
  for (let k = 0; k < 2; k++) { await tocco('touchStart', [[200, 400]]); await pm.waitForTimeout(40); await tocco('touchEnd', []); await pm.waitForTimeout(90); }
  await pm.waitForTimeout(500);
  chk('telefono: secondo doppio tocco torna normale', (await zoomStato(pm)).z === 1);
  chk('telefono: nessuna pagina girata per sbaglio', (await pagina(pm)) === pg, await pagina(pm));
  await pm.waitForTimeout(500);
  await pm.screenshot({ path: (process.env.SCREENSHOT_DIR || '/tmp') + '/lettore-telefono2.png' });

  console.log('errori JS/rete:', errori.length ? errori.slice(0, 5) : 'nessuno');
  chk('nessun errore JS o di rete', errori.length === 0);
  console.log(`lettore: OK=${ok} KO=${ko}`);
  await browser.close();
  process.exit(ko ? 1 : 0);
})().catch(e => { console.log('ERRORE TEST', e); process.exit(1); });
