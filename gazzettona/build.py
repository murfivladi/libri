#!/usr/bin/env python3
"""Impagina La Gazzettona di Vladone.

Uso:  python3 gazzettona/build.py 00
Legge numero-NN/testi.py (dizionari IT, RU, CRU_IT, CRU_RU) e crea per ogni lingua:
  numero-NN/gazzettona-<num>-<it|ru>.html / .pdf  e  anteprime/<lingua>-pN.png
Serve: python qrcode, pillow, pymupdf; Chromium/Playwright (node).
"""
import importlib.util, json, os, subprocess, sys, html
import qrcode
from PIL import Image

QUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, QUI)
import giochi

NN = sys.argv[1] if len(sys.argv) > 1 else "00"
DIR = os.path.join(QUI, "numero-" + NN)
spec = importlib.util.spec_from_file_location("testi", os.path.join(DIR, "testi.py"))
T = importlib.util.module_from_spec(spec); spec.loader.exec_module(T)
BUILD = os.path.join(DIR, "build"); os.makedirs(BUILD, exist_ok=True)
SITO = "https://libri.vladinc.ru"
LIBRO = SITO + "/libro.php?s=chi-ha-rubato-il-cielo"


def qr(url, nome):
    p = os.path.join(BUILD, nome + ".png")
    q = qrcode.QRCode(border=1, box_size=10, error_correction=qrcode.constants.ERROR_CORRECT_M)
    q.add_data(url); q.make(fit=True); q.make_image().save(p)
    return "build/" + nome + ".png"


def bn(src, nome, w=1400):
    """copia l'immagine in bianco e nero, ridotta"""
    im = Image.open(src).convert("L"); im.thumbnail((w, w * 2))
    p = os.path.join(BUILD, nome + ".jpg"); im.save(p, quality=88)
    return "build/" + nome + ".jpg"


def tab_cru(g, num):
    r = []
    righe = [i for i, row in enumerate(g) if any(row)]
    col = [j for j in range(len(g)) if any(g[i][j] for i in range(len(g)))]
    for i in range(min(righe), max(righe) + 1):
        r.append("<tr>" + "".join(
            ('<td>%s</td>' % ('<span>%d</span>' % num[(i, j)] if (i, j) in num else '')) if g[i][j] else '<td class="nero"></td>'
            for j in range(min(col), max(col) + 1)) + "</tr>")
    return '<table class="cru">' + "".join(r) + "</table>"


def tab_sud(p):
    return '<table class="sud">' + "".join(
        "<tr>" + "".join("<td>%s</td>" % (p[r * 9 + c] or "") for c in range(9)) + "</tr>" for r in range(9)) + "</table>"


def pagina(t, n, contenuto, prima=False):
    testa = "" if prima else f'''<div class="testatina"><span>{t["data"]}</span><span class="nome">La Gazzettona di Vladone</span><span>№ {T.NUMERO}</span></div>'''
    return f'''<section class="pagina">{testa}<div class="corpo">{contenuto}</div>
<div class="piede"><span>La Gazzettona di Vladone · № {T.NUMERO}</span><span>{t["pag"]} {n}</span><span>libri.vladinc.ru/gazzettona</span></div></section>'''


def fig(src, did, h=None):
    st = f' style="height:{h}mm"' if h else ''
    return f'<figure><img src="{src}"{st}><figcaption>{did}</figcaption></figure>'


def costruisci(t, cru):
    L = t["lingua"]
    img = {k: bn(os.path.join(DIR, "img", k + ".jpg"), k) for k in ("arancini-duello", "tartaruga", "pioggia", "sciarpa")}
    cop = bn(os.path.join(QUI, "..", "serie-cugini-nel-tempo", "per-il-sito", f"copertina-libro1-{L}.jpg"), "copertina-" + L, 900)
    q_libro, q_arch, q_isc = qr(LIBRO, "qr-libro"), qr(SITO + "/gazzettona/", "qr-archivio"), qr(SITO + "/account.php", "qr-iscrizione")
    g, num, oriz, vert, mancano = giochi.cruciverba(cru)
    assert not mancano, mancano
    sud, sol = giochi.sudoku(seme=2026, indizi=30)
    satira = "SATIRA" if L == "it" else "САТИРА"
    P = []
    P.append(pagina(t, 1, f'''
<header class="testata"><div class="riga"><span>{t["numero"]}</span><span>{t["editore"]}</span><span>{t["data"]}</span></div>
<div class="nome">La Gazzettona di Vladone</div><div class="motto">★ {t["motto"]} ★</div>
<div class="riga"><span>{t["prezzo"]}</span><span>libri.vladinc.ru</span><span>IT · RU</span></div></header>
<div class="g cresci" style="grid-template-columns: 1fr 62mm">
 <div style="display:flex;flex-direction:column;min-height:0">
  <div class="occhiello">{t["p1_occhiello"]}</div><h1>{t["p1_titolo"]}</h1>
  <div class="sommario">{t["p1_sommario"]}</div><div class="firma">{t["p1_firma"]}</div>
  <div class="c2 cresci">{t["p1_testo"]}</div></div>
 <div class="vr">{fig(img["arancini-duello"], t["p1_didascalia"])}
  <div class="box doppio" style="margin-bottom:3mm"><h4>{t["p1_box_titolo"]}</h4>{t["p1_box"]}</div>
  <div class="box">{t["p1_sommario_box"]}</div></div>
</div>''', prima=True))
    P.append(pagina(t, 2, f'''
<div class="g cresci" style="grid-template-columns: 60mm 1fr">
 <div><span class="rubrica">{t["p2_ed_firma"]}</span><h2>{t["p2_ed_titolo"]}</h2>{t["p2_ed"]}</div>
 <div class="vr" style="display:flex;flex-direction:column;gap:3mm">
  <div><span class="rubrica">{t["p2_rubrica"]}</span><div class="c2">{t["p2_notizie"]}</div></div>
  <div class="hr g" style="grid-template-columns: 1fr 52mm">
   <div><h3><span class="etichetta nera">{satira}</span>{t["p2_satira_titolo"]}</h3>{t["p2_satira"]}</div>
   <div>{fig(img["pioggia"], t["p2_didascalia"])}</div></div>
 </div></div>'''))
    P.append(pagina(t, 3, f'''
<div><span class="rubrica">{t["p3_rubrica"]}</span><div class="occhiello">{t["p3_occhiello"]}</div><h1>{t["p3_titolo"]}</h1></div>
<div class="g cresci" style="grid-template-columns: 72mm 1fr">
 <div><figure><img src="{cop}"></figure>
  <div class="g" style="grid-template-columns: 26mm 1fr;align-items:center"><div class="qr"><img src="{q_libro}"></div><div class="firma">{t["p3_qr"]}<br>libri.vladinc.ru</div></div></div>
 <div class="vr">{t["p3_trama"]}
  <div class="box doppio" style="margin:2mm 0 3mm"><p style="font-family:Testata;font-size:14pt;font-style:italic;text-align:center;margin:0">{t["p3_citazione"]}</p></div>
  {t["p3_recensione"]}
  <div class="hr"><h4>{t["p3_altri_titolo"]}</h4>{t["p3_altri"]}</div>
  <div class="box"><h4>{t["p3_ann_titolo"]}</h4>{t["p3_ann"]}</div></div>
</div>'''))
    P.append(pagina(t, 4, f'''
<div><span class="rubrica">{t["p4_rubrica"]}</span><h1>{t["p4_titolo"]}</h1><div class="sommario">{t["p4_sommario"]}</div><div class="firma">{t["p4_firma"]}</div></div>
<div class="c3 cresci" style="font-size:11pt;line-height:1.4">{t["p4_testo"]}</div>
<div class="g hr" style="grid-template-columns: 1fr 1fr"><div class="box doppio"><h4>{t["p4_vero_titolo"]}</h4>{t["p4_vero"]}</div><div class="box">{t["p4_chi"]}</div></div>'''))
    P.append(pagina(t, 5, f'''
<div><span class="rubrica">{t["p5_rubrica"]}</span><h1>{t["p5_titolo"]}</h1><div class="firma">{t["p5_firma"]}</div></div>
<div class="g" style="grid-template-columns: 55mm 1fr"><div class="box">{t["p5_ingr"]}</div><div class="c2">{t["p5_prep"]}</div></div>
<div class="g hr cresci" style="grid-template-columns: 1fr 70mm">
 <div><h3>{t["p5_detti_titolo"]}</h3>{t["p5_detti"]}</div>
 <div class="vr"><div class="firma">{t["p5_ann_tag"]}</div><div class="annuncio"><h4>{t["p5_ann1_t"]}</h4>{t["p5_ann1"]}</div></div></div>'''))
    P.append(pagina(t, 6, f'''
<div><span class="rubrica">{t["p6_rubrica"]}</span><div class="occhiello">{t["p6_occhiello"]}</div><h1>{t["p6_titolo"]}</h1></div>
<div class="c3 cresci">{t["p6_testo"]}</div>
<div class="g hr" style="grid-template-columns: 50mm 1fr 1fr">
 <div>{fig(img["tartaruga"], t["p5_ann_tag"], 62)}</div>
 <div class="annuncio"><h4>{t["p6_ann1_t"]}</h4>{t["p6_ann1"]}</div>
 <div class="annuncio"><h4>{t["p6_ann2_t"]}</h4>{t["p6_ann2"]}</div></div>'''))
    P.append(pagina(t, 7, f'''
<div class="g cresci" style="grid-template-columns: 1fr 70mm">
 <div><span class="rubrica">{t["p7_rubrica"]}</span><h1>{t["p7_titolo"]}</h1><div class="firma">{t["p7_firma"]}</div>
  <div class="c2" style="font-size:10.8pt;line-height:1.4">{t["p7_posta"]}</div><div class="box" style="margin-top:2mm">{t["p7_chi"]}</div></div>
 <div class="vr">{fig(img["sciarpa"], t["p7_didascalia"])}</div></div>
<div class="hr"><h3>{t["p7_barz_titolo"]}</h3><div class="c3 piccolo">{t["p7_barz"]}</div></div>'''))
    defs = lambda xs: "".join(f"<p><b>{n}.</b> {d} <i>({len(w)})</i></p>" for n, d, w in xs)
    P.append(pagina(t, 8, f'''
<span class="rubrica">{t["p8_rubrica"]}</span>
<div class="g" style="grid-template-columns: 1fr 70mm">
 <div><h3>{t["p8_cru_titolo"]}</h3>{tab_cru(g, num)}
  <div class="g defs" style="grid-template-columns:1fr 1fr"><div><b>{t["p8_or"]}</b>{defs(oriz)}</div><div><b>{t["p8_ve"]}</b>{defs(vert)}</div></div></div>
 <div class="vr"><h3>{t["p8_sud_titolo"]}</h3>{tab_sud(sud)}<h3>{t["p8_ind_titolo"]}</h3>{t["p8_ind"]}<p class="firma">{t["p8_soluz"]}</p></div></div>
<div class="g hr cresci" style="grid-template-columns: 1fr 62mm">
 <div><h3>{t["p8_oro_titolo"]}</h3><div class="c3 piccolo">{t["p8_oro"]}</div></div>
 <div class="vr"><div class="box doppio"><h4>{t["p8_pross_titolo"]}</h4>{t["p8_pross"]}</div>
  <div class="g" style="grid-template-columns:1fr 1fr;margin-top:2mm"><div class="qr"><img src="{q_arch}">{t["p8_qr1"]}</div><div class="qr"><img src="{q_isc}">{t["p8_qr2"]}</div></div></div></div>
<p class="piccolo" style="font-size:7pt;text-align:center;margin:0">{t["colophon"]}</p>'''))
    doc = f'''<!doctype html><html lang="{L}"><head><meta charset="utf-8"><title>La Gazzettona di Vladone № {T.NUMERO}</title>
<link rel="stylesheet" href="../stile.css"></head><body>{"".join(P)}</body></html>'''
    out = os.path.join(DIR, f"gazzettona-{T.NUMERO}-{L}.html")
    open(out, "w").write(doc)
    return out, dict(cruciverba={str(n): w for n, _, w in oriz + vert}, cru_oriz=[n for n, _, _ in oriz],
                     sudoku_soluzione="".join(map(str, sol)), sudoku="".join(map(str, sud)))


RENDER = r'''
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage();
  for (const f of process.argv.slice(2)) {
    await p.goto('file://' + f); await p.evaluate(() => document.fonts.ready);
    const over = await p.evaluate(() => [...document.querySelectorAll('.corpo, .cresci, .c2, .c3')].filter(e => e.scrollHeight > e.clientHeight + 2 || e.scrollWidth > e.clientWidth + 2).map(e => (e.closest('.pagina') ? [...document.querySelectorAll('.pagina')].indexOf(e.closest('.pagina')) + 1 : 0) + ':' + e.className + ':' + (e.scrollHeight - e.clientHeight) + '/' + (e.scrollWidth - e.clientWidth)));
    console.log('OVERFLOW', f, JSON.stringify(over));
    await p.pdf({ path: f.replace('.html', '.pdf'), format: 'A4', printBackground: true, preferCSSPageSize: true });
  }
  await b.close();
})();
'''

if __name__ == "__main__":
    lingue = sys.argv[2:] or ["it", "ru"]
    files, soluz = [], {}
    for L in lingue:
        f, s = costruisci(getattr(T, L.upper()), getattr(T, "CRU_" + L.upper()))
        files.append(f); soluz[L] = s
    js = os.path.join(BUILD, "render.js"); open(js, "w").write(RENDER)
    subprocess.run(["node", js] + files, check=True, env=dict(os.environ, NODE_PATH=subprocess.run(["npm", "root", "-g"], capture_output=True, text=True).stdout.strip()))
    import fitz
    prev = os.path.join(DIR, "anteprime"); os.makedirs(prev, exist_ok=True)
    for f in files:
        pdf = f.replace(".html", ".pdf"); d = fitz.open(pdf)
        print(pdf, d.page_count, "pagine")
        for i, pg in enumerate(d):
            pg.get_pixmap(dpi=110).save(os.path.join(prev, os.path.basename(pdf)[:-4] + f"-p{i + 1}.png"))
    json.dump(soluz, open(os.path.join(DIR, "soluzioni.json"), "w"), ensure_ascii=False, indent=1)
