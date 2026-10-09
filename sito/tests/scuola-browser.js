// Scuola nel browser: zone cliccabili nel lettore, finestra dell'esercizio, tocca le parole, riordina (tocco e trascinamento),
// editor visivo dell'admin, telefono. Lanciato da tests/scuola.sh (BASE, SLUG, LID). Con SHOTS=cartella salva gli screenshot.
const { chromium } = require('playwright');
const B = process.env.BASE, SLUG = process.env.SLUG, LID = process.env.LID, SHOTS = process.env.SHOTS || '';
let ok = 0, ko = 0;
function chk(nome, cond) { if (cond) ok++; else { ko++; console.log('FAIL: ' + nome); } }
async function foto(p, nome) { if (SHOTS) await p.screenshot({ path: SHOTS + '/' + nome + '.png' }); }

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || undefined });
  const errori = [];
  try {
    // ---------- lettore: zone sulle pagine ----------
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
    const p = await ctx.newPage();
    p.on('pageerror', e => errori.push(e.message));
    await p.goto(`${B}/leggi.php?s=${SLUG}&pagina=2`);
    await p.waitForSelector('.zona-scuola[title*="CFC-1"]', { timeout: 15000 });
    chk('zona automatica dal codice CFC-1', await p.locator('.zona-scuola[title*="CFC-1"]').count() === 1);
    await p.waitForSelector('.zona-scuola[title*="CFC-2"]', { timeout: 15000 });
    chk('zona disegnata a mano per CFC-2 (e niente doppione)', await p.locator('.zona-scuola[title*="CFC-2"]').count() === 1);
    await p.waitForTimeout(2600);          // fine del lampeggio iniziale delle zone
    await foto(p, '1-lettore');
    await p.locator('.zona-scuola[title*="CFC-1"]').click();
    chk('finestra aperta', await p.locator('#finestra-scuola').isVisible());
    const f = p.frameLocator('#cornice-scuola');
    await f.locator('.vs-esercizio .parola').first().waitFor();
    chk('titolo della finestra', (await p.locator('#titolo-finestra').textContent()).includes('Trova il soggetto'));
    await f.locator('.categoria', { hasText: 'soggetto' }).click();
    await f.locator('.parola', { hasText: 'Luce' }).click();
    await f.locator('.categoria', { hasText: 'predicato' }).click();
    await f.locator('.parola', { hasText: 'mangia' }).click();
    await foto(p, '2-esercizio-tocca');
    await f.locator('.bottone-es', { hasText: 'Controlla' }).click();
    chk('tocca: 2 su 2', (await f.locator('.esito').textContent()).includes('2 su 2'));
    await foto(p, '3-esercizio-giusto');
    await f.locator('.bottone-es', { hasText: 'Riprova' }).click();
    await f.locator('.parola', { hasText: 'pappa.' }).click();      // categoria «soggetto» scelta: segno sbagliato
    await f.locator('.bottone-es', { hasText: 'Controlla' }).click();
    chk('tocca: errore e spiegazione', (await f.locator('.esito').textContent()).includes('0 su 2') && await f.locator('.spiegazione').count() === 1);
    chk('record salvato sul dispositivo', (await f.locator('.record').count()) === 1);
    await p.keyboard.press('Escape');
    chk('Esc chiude la finestra', await p.locator('#finestra-scuola').waitFor({ state: 'hidden', timeout: 3000 }).then(() => true, () => false));

    // ---------- riordina: con il tocco e con il trascinamento ----------
    await p.goto(`${B}/apri.php?c=CFC-9`);
    for (const w of ['Vlad', 'spiega', 'la', 'grammatica']) await p.locator('.banca .gettone', { hasText: new RegExp('^' + w + '$') }).click();
    await p.locator('.bottone-es', { hasText: 'Controlla' }).click();
    chk('riordina con il tocco: 1 su 1', (await p.locator('.esito').textContent()).includes('1 su 1'));
    await p.locator('.bottone-es', { hasText: 'Riprova' }).click();
    for (const w of ['Vlad', 'spiega', 'la', 'grammatica']) {
      const g = p.locator('.banca .gettone', { hasText: new RegExp('^' + w + '$') });
      const a = await g.boundingBox(), dest = await p.locator('.risposta').boundingBox();
      await p.mouse.move(a.x + a.width / 2, a.y + a.height / 2); await p.mouse.down();
      await p.mouse.move(a.x + 20, a.y - 10, { steps: 4 });
      await p.mouse.move(dest.x + dest.width - 12, dest.y + dest.height / 2, { steps: 8 }); await p.mouse.up();
    }
    const ordine = await p.locator('.risposta .gettone').allTextContents();
    chk('riordina trascinando: ordine giusto', ordine.join(' ') === 'Vlad spiega la grammatica');
    await foto(p, '4-riordina');

    // ---------- telefono ----------
    const tel = await browser.newContext({ viewport: { width: 390, height: 780 }, isMobile: true, hasTouch: true });
    const t = await tel.newPage();
    await t.goto(`${B}/apri.php?c=CFC-1`);
    await t.locator('.parola', { hasText: 'Luce' }).tap();
    chk('telefono: niente scorrimento orizzontale', await t.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
    await foto(t, '5-telefono');
    await t.goto(`${B}/scuola.php`);
    await foto(t, '6-scuola-telefono');
    await tel.close();

    // ---------- admin: editor visivo ----------
    const adm = await browser.newContext({ viewport: { width: 1360, height: 900 } });
    const a = await adm.newPage();
    a.on('pageerror', e => errori.push(e.message));
    await a.goto(`${B}/admin/login.php`);
    await a.fill('input[name=password]', 'provaprova12'); await a.click('button[type=submit], form button');
    await a.goto(`${B}/admin/esercizi.php?nuovo=1&libro=${LID}`);
    await a.fill('#titolo', 'Soggetto e predicato 2');
    await a.locator('.tipo-es', { hasText: 'Tocca le parole' }).click();
    await a.locator('#editor .voce-editor input[type=text]').first().fill('Vlad scrive il libro.');
    await a.locator('.chip-cat button', { hasText: 'soggetto' }).click();
    await a.locator('#editor .editor-parole .parola', { hasText: 'Vlad' }).click();
    await a.locator('.chip-cat button', { hasText: 'predicato' }).click();
    await a.locator('#editor .editor-parole .parola', { hasText: 'scrive' }).click();
    await a.waitForTimeout(500);
    chk('anteprima con le parole', await a.locator('#anteprima .parola').count() === 4);
    await foto(a, '7-admin-editor');
    await a.locator('.salva-barra button', { hasText: 'Salva' }).first().click();
    await a.waitForLoadState();
    chk('admin: esercizio salvato', (await a.locator('.ok').textContent()).includes('Salvato'));
    const dati = await a.locator('#campo-dati').inputValue();
    chk('admin: segni salvati', dati.includes('"0":"soggetto"') && dati.includes('"1":"predicato"'));
    await a.goto(`${B}/admin/zone.php?libro=${LID}&pagina=2`);
    await a.waitForSelector('.rett.auto', { timeout: 15000 });
    chk('zone: codice riconosciuto anche nell\'admin', await a.locator('.rett.auto').count() === 1);
    await foto(a, '8-admin-zone');
    await adm.close(); await ctx.close();

    // ---------- studente: entra in classe, fa l'esercizio, il risultato arriva al prof ----------
    if (process.env.CC) {
      const sc = await browser.newContext({ viewport: { width: 390, height: 800 }, isMobile: true, hasTouch: true });
      const s = await sc.newPage();
      s.on('pageerror', e => errori.push(e.message));
      await s.goto(`${B}/studente.php`);
      await s.fill('input[name=codice]', process.env.CC); await s.fill('input[name=nick]', 'Gatto7'); await s.fill('input[name=pin]', '4321');
      await s.locator('form.form-scuola button').click(); await s.waitForLoadState();
      await s.fill('input[name=pin]', '4321'); await s.fill('input[name=pin2]', '4321');
      await foto(s, '9-studente-entra');
      await s.locator('form.form-scuola button').click(); await s.waitForLoadState();
      chk('studente: home della classe', (await s.locator('h1').textContent()).includes('Gatto7'));
      await foto(s, '10-studente-home');
      await s.goto(`${B}/apri.php?c=CFC-1`);
      await s.locator('.parola', { hasText: 'Luce' }).tap();
      await s.locator('.categoria', { hasText: 'predicato' }).tap();
      await s.locator('.parola', { hasText: 'mangia' }).tap();
      await s.locator('.bottone-es', { hasText: 'Controlla' }).tap();
      await s.waitForFunction(() => /inviato al prof/.test((document.querySelector('.inviato') || {}).textContent || ''), null, { timeout: 8000 });
      chk('studente: risultato inviato', true);
      await foto(s, '11-studente-inviato');
      await sc.close();
      const pc = await browser.newContext({ viewport: { width: 1280, height: 900 } });
      const pr = await pc.newPage();
      await pr.goto(`${B}/prof.php`);
      await pr.fill('input[name=email]', 'rossi@scuola.it'); await pr.fill('input[name=password]', 'passwordprof1');
      await pr.locator('form.form-scuola button').click(); await pr.waitForLoadState();
      await pr.goto(`${B}/prof.php?a=classe&id=${process.env.CID}&vista=risultati`);
      chk('prof: Gatto7 nei risultati', (await pr.locator('.matrice').textContent()).includes('Gatto7'));
      await foto(pr, '12-prof-risultati');
      await pr.goto(`${B}/prof.php?a=classe&id=${process.env.CID}&vista=errori`); await foto(pr, '13-prof-errori');
      await pr.goto(`${B}/prof.php?a=classe&id=${process.env.CID}&vista=progressi`); await foto(pr, '14-prof-progressi');
      await pc.close();
    }
  } catch (e) { ko++; console.log('FAIL: ' + e.message); }
  chk('nessun errore JavaScript', errori.length === 0);
  if (errori.length) console.log(errori.slice(0, 5).join('\n'));
  await browser.close();
  console.log(`scuola-browser: OK=${ok} KO=${ko}`);
  process.exit(ko ? 1 : 0);
})();
