// L'area lettore nel browser: accesso dal modulo vero, preferiti, recensione, e pagina/segnalibri che seguono l'account su un altro dispositivo.
// Lo avvia browser.sh (che prepara il lettore «Prova1» / «passwordprova99»); BASE = indirizzo del sito.
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
const stato = p => p.evaluate(s => fetch('api-lettore.php?libro=' + s, { credentials: 'same-origin' }).then(r => r.json()), SLUG);

async function accedi(page) {
  await page.goto(B + '/account.php?a=accedi');
  await page.fill('input[name=id]', 'Prova1');
  await page.fill('input[name=password]', 'passwordprova99');
  await Promise.all([page.waitForURL(/account\.php$/), page.click('form.form-pub button.bottone')]);
}

(async () => {
  const browser = await chromium.launch({ executablePath: trovaChromium(), args: ['--no-sandbox'] });
  const errori = [];
  const nuovo = async () => {
    const p = await (await browser.newContext({ viewport: { width: 1200, height: 900 } })).newPage();
    p.on('pageerror', e => errori.push(String(e)));
    p.on('response', r => { if (r.status() >= 400 && !/api-lettore/.test(r.url())) errori.push(r.status() + ' ' + r.url()); });
    return p;
  };

  // ----- dispositivo 1 -----
  const a = await nuovo();
  await accedi(a);
  chk('accesso: La mia libreria', (await a.textContent('h1')) === 'La mia libreria');

  await a.goto(B + '/libro.php?s=' + SLUG);
  await Promise.all([a.waitForLoadState(), a.click('button:has-text("Aggiungi ai preferiti")')]);
  await a.waitForSelector('button:has-text("Nei preferiti")');
  chk('preferito: il pulsante cambia', await a.locator('button:has-text("Nei preferiti")').count() === 1);

  await a.fill('textarea[name=testo]', 'Bellissimo, lo rileggo.');
  await a.selectOption('select[name=voto]', '5');
  await Promise.all([a.waitForLoadState(), a.click('form[action*="recensione"] button:has-text("Salva")')]);
  chk('recensione: in attesa di verifica', (await a.textContent('#recensioni')).includes('in attesa di verifica'));

  await a.goto(B + '/leggi.php?s=' + SLUG);
  await pronto(a);
  chk('lettore: collegato all\'account (api)', await a.evaluate(() => !!document.body.dataset.api));
  await a.click('#avanti'); await a.waitForTimeout(1300);
  await a.click('#avanti'); await a.waitForTimeout(1300);
  const p = Number(await a.inputValue('#vai'));
  chk('lettore: pagina avanzata', p >= 3, p);
  await a.click('#b-segna');
  await a.waitForTimeout(2600);                                   // il progresso parte dopo 1,5 s
  const s1 = await stato(a);
  chk('account: pagina salvata dal lettore', s1.pagina === p, JSON.stringify(s1));
  chk('account: segnalibro salvato dal lettore', s1.segnalibri.includes(p), JSON.stringify(s1));

  // ----- dispositivo 2: stesso account, niente memoria locale -----
  const b = await nuovo();
  await accedi(b);
  await b.goto(B + '/leggi.php?s=' + SLUG);
  await pronto(b);
  chk('altro dispositivo: riparte dalla pagina dell\'account', Number(await b.inputValue('#vai')) === p, await b.inputValue('#vai'));
  await b.click('#b-pannello'); await b.click('[data-tab=segnalibri]');
  chk('altro dispositivo: il segnalibro c\'è', (await b.textContent('#tab-segnalibri')).includes('Pagina ' + p));
  // togliere il segnalibro qui lo toglie dall'account
  await b.click('#tab-segnalibri .x'); await b.waitForTimeout(600);
  chk('segnalibro tolto anche sull\'account', (await stato(b)).segnalibri.length === 0);

  // link «continua a leggere» del profilo apre la pagina giusta
  await b.goto(B + '/account.php');
  const href = await b.getAttribute('.elenco-account a[href*="leggi.php"]', 'href');
  chk('profilo: continua a leggere porta alla pagina', /pagina=\d+/.test(href || ''), href);

  // uscita
  await Promise.all([b.waitForURL(/index\.php$|\/$/), b.click('button:has-text("Esci")')]);
  chk('uscito: il menu non mostra più il nome', !(await b.content()).includes('👤 Prova1'));

  chk('nessun errore JS/rete', errori.length === 0, errori.slice(0, 4));
  console.log(`account browser: OK=${ok} KO=${ko}`);
  await browser.close();
  process.exit(ko ? 1 : 0);
})().catch(e => { console.log('ERRORE TEST', e); process.exit(1); });
