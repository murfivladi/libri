// Lavagna nel browser: strumenti, passi, riproduzione, video dei passi e dal vivo, salvataggio sul sito (admin).
// Lanciato da tests/scuola.sh (BASE, LID). Con SHOTS=cartella salva gli screenshot.
const { chromium } = require('playwright');
const B = process.env.BASE, LID = process.env.LID, SHOTS = process.env.SHOTS || '';
let ok = 0, ko = 0;
function chk(nome, cond) { if (cond) ok++; else { ko++; console.log('FAIL: ' + nome); } }
async function foto(p, nome) { if (SHOTS) await p.screenshot({ path: SHOTS + '/' + nome + '.png' }); }

(async () => {
  const browser = await chromium.launch({ args: ['--autoplay-policy=no-user-gesture-required', '--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'] });
  const errori = [];
  try {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, permissions: ['microphone'] });
    const p = await ctx.newPage();
    p.on('pageerror', e => errori.push(e.message));
    p.on('console', m => { if (m.type() === 'error' && !/favicon|404/.test(m.text())) errori.push(m.text()); });
    await p.goto(`${B}/admin/login.php`);
    await p.fill('input[name=password]', 'provaprova12'); await p.click('form button');
    await p.goto(`${B}/admin/lavagna.php?nuova=1`);
    await p.waitForSelector('#lv-sopra');
    const tela = await p.locator('#lv-sopra').boundingBox();
    const k = tela.width / 1920;
    const pt = (x, y) => [tela.x + x * k, tela.y + y * k];
    const strumento = s => p.locator(`.lv-strumenti button[data-s="${s}"]`).click();
    const progetto = () => p.evaluate(() => window.LavagnaApp.progetto());
    async function trascina(x1, y1, x2, y2, passi = 12) { await p.mouse.move(...pt(x1, y1)); await p.mouse.down(); for (let i = 1; i <= passi; i++) await p.mouse.move(...pt(x1 + (x2 - x1) * i / passi, y1 + (y2 - y1) * i / passi + Math.sin(i) * 20)); await p.mouse.up(); }

    chk('almeno 18 strumenti', await p.locator('.lv-strumenti button').count() >= 18);
    // penna
    await strumento('penna'); await trascina(200, 300, 700, 380);
    // testo
    await strumento('testo'); await p.mouse.click(...pt(960, 160));
    await p.keyboard.type('Il soggetto'); await p.keyboard.press('Escape');
    // etichetta grammaticale
    await strumento('etichetta'); await p.mouse.click(...pt(700, 600));
    await p.keyboard.type('Luce'); await p.keyboard.press('Escape');
    // freccia e rettangolo
    await strumento('freccia'); await trascina(900, 600, 1200, 520, 6);
    await strumento('rett'); await trascina(1300, 700, 1700, 950, 6);
    // personaggio
    await strumento('personaggio'); await p.locator('.lv-img-scelta').nth(3).click();
    await p.waitForFunction(() => window.LavagnaApp.progetto().oggetti.length >= 6, null, { timeout: 5000 }).catch(() => {});
    let P = await progetto();
    chk('6 oggetti (tratto, testo, etichetta, freccia, rettangolo, Luce)', P.oggetti.length === 6);
    chk('tipi giusti', ['tratto', 'testo', 'etichetta', 'forma', 'forma', 'immagine'].every((t, i) => P.oggetti[i].tipo === t));
    chk('testo scritto', P.oggetti[1].t === 'Il soggetto' && P.oggetti[2].t === 'Luce');
    await foto(p, 'L1-passo1');
    // annulla / ripeti
    await p.locator('#lv-annulla').click();
    chk('annulla', (await progetto()).oggetti.length === 5);
    await p.locator('#lv-ripeti').click();
    chk('ripeti', (await progetto()).oggetti.length === 6);
    // passo 2: sposto il testo e aggiungo la frase smontabile
    await p.locator('#lv-passo-nuovo').click();
    await strumento('seleziona');
    await p.mouse.move(...pt(960, 160)); await p.mouse.down(); await p.mouse.move(...pt(960, 260), { steps: 8 }); await p.mouse.up();
    await strumento('frase'); await p.mouse.click(...pt(960, 420));
    await p.locator('#lv-dialogo input').fill('Luce mangia la pappa.'); await p.keyboard.press('Enter');
    P = await progetto();
    chk('2 passi', P.passi.length === 2);
    const idTesto = P.oggetti[1].id;
    chk('il testo si sposta solo nel passo 2', Math.round(P.passi[0].stato[idTesto].y) === 160 && Math.round(P.passi[1].stato[idTesto].y) === 260);
    chk('frase smontata in 4 pezzi', P.oggetti.length === 10 && P.oggetti.slice(6).map(o => o.t).join(' ') === 'Luce mangia la pappa.');
    chk('i pezzi della frase compaiono dal passo 2', P.passi[0].stato[P.oggetti[7].id].v === 0 && P.passi[1].stato[P.oggetti[7].id].v === 1);
    // pannello: cambio colore della selezione (i 4 pezzi sono selezionati)
    await p.locator('.lv-pannello .lv-col').nth(1).click();
    P = await progetto();
    chk('colore cambiato a tutti i pezzi', P.oggetti.slice(6).every(o => o.col === '#ffe14d'));
    await foto(p, 'L2-passo2');
    // stato dell'animazione: a metà del passo 2 il testo è tra 160 e 260
    const meta = await p.evaluate(id => { const A = window.LavagnaApp, P = A.progetto(), t0 = A.durataTotale() - 0.0001; return { fine: A.statiAl(t0).stati[id].y }; }, idTesto);
    chk('alla fine del video il testo è al posto del passo 2', Math.round(meta.fine) === 260);
    // salva
    await p.locator('#lv-titolo').fill('Il soggetto con Luce');
    await p.locator('#lv-salva').click();
    await p.waitForFunction(() => /salvato/.test(document.getElementById('lv-stato').textContent), null, { timeout: 8000 });
    chk('salvato sul server', /id=\d+/.test(p.url()));
    // musica di sottofondo caricata dal computer
    if (process.env.WAV) {
      await p.locator('.lv-strumenti button[data-s="seleziona"]').click(); await p.mouse.click(...pt(1880, 1050));
      await p.setInputFiles('#lv-file-audio', process.env.WAV);
      await p.waitForFunction(() => !!window.LavagnaApp.progetto().audio.src, null, { timeout: 8000 }).catch(() => {});
      chk('musica caricata', !!(await progetto()).audio.src);
    }
    // riproduci
    await p.locator('.lv-mini').first().click();
    await p.locator('#lv-play').click(); await p.waitForTimeout(1300); await foto(p, 'L3-riproduzione');
    chk('in riproduzione', (await p.locator('#lv-play').textContent()).includes('Ferma'));
    await p.locator('#lv-play').click();
    // video dei passi
    await p.locator('#lv-esporta').click();
    await p.locator('#lv-dialogo button', { hasText: 'Registra il video' }).click();
    await p.waitForSelector('#lv-dialogo video', { timeout: 60000 });
    const info = await p.locator('#lv-dialogo .lv-muto').first().textContent();
    chk('video creato (' + info.trim().slice(0, 30) + ')', /MP4|WEBM/.test(info));
    await foto(p, 'L4-video-pronto');
    await p.selectOption('#lv-dialogo select', String(LID));
    await p.locator('#lv-dialogo button', { hasText: 'Salva sul sito' }).click();
    await p.waitForFunction(() => /Video salvato|Errore/.test(document.querySelector('#lv-dialogo').textContent), null, { timeout: 30000 });
    const esito = await p.locator('#lv-dialogo').textContent();
    chk('video salvato sul sito con il codice', /Video salvato sul sito con il codice [A-Z]+-\d+/.test(esito));
    if (!/Video salvato/.test(esito)) console.log(esito.slice(-200));
    await p.locator('#lv-dialogo button', { hasText: 'Chiudi' }).click();
    // dal vivo, senza microfono: laser e penna
    await p.locator('#lv-dal-vivo').click();
    await p.locator('#lv-dialogo input[type=checkbox]').uncheck();
    await p.locator('#lv-dialogo button', { hasText: 'Inizia' }).click();
    await p.waitForSelector('#lv-registra-info:not([hidden])', { timeout: 6000 });
    await strumento('laser'); await trascina(300, 800, 900, 700, 20);
    await strumento('penna'); await trascina(1000, 800, 1500, 850, 10);
    await foto(p, 'L5-dal-vivo');
    await p.locator('#lv-reg-stop').click();
    await p.waitForSelector('#lv-dialogo video', { timeout: 20000 });
    chk('video dal vivo pronto', true);
    await p.locator('#lv-dialogo button', { hasText: 'Chiudi' }).click();
    // l'elenco dell'admin mostra il video
    await p.goto(`${B}/admin/lavagna.php`);
    chk('elenco: video con codice', (await p.locator('.tabella').textContent()).includes('guarda'));
    await ctx.close();
  } catch (e) { ko++; console.log('FAIL: ' + e.message.split('\n')[0]); }
  chk('nessun errore JavaScript', errori.length === 0);
  if (errori.length) console.log(errori.slice(0, 5).join('\n'));
  await browser.close();
  console.log(`lavagna-browser: OK=${ok} KO=${ko}`);
  process.exit(ko ? 1 : 0);
})();
