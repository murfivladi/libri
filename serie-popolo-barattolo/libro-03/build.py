#!/usr/bin/env python3
"""Costruisce il PDF di «Il secolo delle lenti» (Il popolo nel barattolo, libro 3).

I capitoli stanno in capitoli/NN-*.md. Formato (semplice):
  # Titolo del capitolo
  @mondo: presente | oblio | duecento      (cambia i colori del capitolo)
  @dove: Castel del Monte, oggi            (riga piccola sotto il titolo)
  paragrafi separati da una riga vuota; i dialoghi iniziano con «—»; *corsivo*
  * * *                                    (stacco di scena)
  > testo                                  (messaggio scritto a mano nel quaderno)
  || testo                                 (iscrizione)
  [[tavola:cap03|didascalia]]              (illustrazione a tutta pagina: immagini/cap03.jpg)

Uso:  python3 serie-popolo-barattolo/libro-02/build.py [--ru]   (--ru: versione russa)
Output: serie-popolo-barattolo/libro-02/build/il-secolo-delle-lenti.pdf
"""
import glob
import html
import os
import re
import subprocess
import sys

QUI = os.path.dirname(os.path.abspath(__file__))
RADICE = os.path.normpath(os.path.join(QUI, "..", ".."))
BUILD = os.path.join(QUI, "build")
HTML_UNITO = os.path.join(BUILD, "libro-unito.html")
LOGO = "../../../grafica/emblema-700.png"
ANNO = 2026
RU = "--ru" in sys.argv            # versione russa: capitoli-ru/, appendice-ru.html, PDF a parte
PDF = os.path.join(BUILD, "vek-linz.pdf" if RU else "il-secolo-delle-lenti.pdf")
CAPITOLI = "capitoli-ru" if RU else "capitoli"
APPENDICE = "appendice-ru.html" if RU else "appendice.html"
L = {
    "serie": "Народ в банке" if RU else "Il popolo nel barattolo",
    "titolo": "Век линз" if RU else "Il secolo delle lenti",
    "titolo2": "Век<br>линз" if RU else "Il secolo<br>delle lenti",
    "libro1": "Книга 3" if RU else "Libro 3",
    "motto": "Тысяча лет за один день" if RU else "Mille anni in un giorno",
    "autore": "Влад" if RU else "Vlad",
    "editore": "Издательство Vlad Editore" if RU else "Vlad Editore",
    "luogo": "Таормина · Сицилия" if RU else "Taormina · Sicilia",
    "capitolo": "Глава" if RU else "Capitolo",
    "nella_collana": "В той же серии" if RU else "Nella stessa collana",
    "continua": "Продолжение следует…" if RU else "Continua…",
    "lang": "ru" if RU else "it",
}
SERIE = L["serie"]
TITOLO = L["titolo"]


def trova_chromium():
    cand = glob.glob("/opt/pw-browsers/chromium-*/chrome-linux/chrome")
    for nome in ("chromium", "chromium-browser", "google-chrome"):
        cand.append(nome)
    for c in cand:
        if os.path.isabs(c) and os.path.exists(c):
            return c
        if not os.path.isabs(c):
            r = subprocess.run(["which", c], capture_output=True, text=True)
            if r.returncode == 0:
                return r.stdout.strip()
    sys.exit("Chromium non trovato")


def inline(t):
    t = html.escape(t, quote=False)
    t = re.sub(r"\*([^*\n]+)\*", r"<em>\1</em>", t)
    return t


def capitolo(path, numero):
    righe = open(path, encoding="utf-8").read().split("\n")
    titolo, mondo, dove = "", "presente", ""
    blocchi, corrente = [], []
    for r in righe:
        if r.startswith("# "):
            titolo = r[2:].strip(); continue
        m = re.match(r"@(\w+):\s*(.*)", r)
        if m:
            if m.group(1) == "mondo": mondo = m.group(2).strip()
            if m.group(1) == "dove": dove = m.group(2).strip()
            continue
        if r.strip() == "":
            if corrente: blocchi.append(" ".join(corrente)); corrente = []
        elif r.startswith(("[[", "* * *", "||", ">")):
            if corrente: blocchi.append(" ".join(corrente)); corrente = []
            blocchi.append(r.strip())
        else:
            corrente.append(r.strip())
    if corrente: blocchi.append(" ".join(corrente))
    out = [f'<section class="capitolo {mondo}">', '<div class="capitolo-testa">',
           f'<p class="titolo-libro">{html.escape(SERIE)}</p>', f'<p class="numero-cap">{L["capitolo"]} {numero}</p>',
           f'<h2 class="titolo-cap">{inline(titolo)}</h2>']
    if dove: out.append(f'<p class="dove">{html.escape(dove)}</p>')
    out.append('<p class="ornamento">' + {"oblio": "◇ ◇ ◇", "duecento": "✦ ✦ ✦"}.get(mondo, "• • •") + '</p></div>')
    primo = True
    tavole = []
    for b in blocchi:
        if b == "* * *":
            out.append('<p class="stacco">✦</p>'); primo = True; continue
        m = re.match(r"\[\[tavola:([\w-]+)\|(.*)\]\]", b)
        if m:
            tavole.append((m.group(1), m.group(2))); continue
        if b.startswith("||"):
            out.append(f'<p class="iscrizione">{inline(b[2:].strip())}</p>'); continue
        if b.startswith(">"):
            out.append(f'<p class="quaderno">{inline(b[1:].strip())}</p>'); continue
        cl = ' class="primo capolettera"' if primo and not b.startswith("—") else (' class="primo"' if primo else "")
        out.append(f"<p{cl}>{inline(b)}</p>"); primo = False
    out.append('<p class="fine-cap">✦ ✦ ✦</p></section>')
    for nome, did in tavole:
        out.append(tavola(nome, did))
    return "\n".join(out)


def tavola(nome, didascalia, larga=False):
    img = os.path.join(QUI, "immagini", nome + ".jpg")
    if not os.path.exists(img):
        return ""        # l'illustrazione non c'è ancora: nessuna pagina vuota
    return (f'<section class="pagina-fissa tavola{" larga" if larga else ""}"><figure><img src="../immagini/{nome}.jpg" alt="{html.escape(didascalia)}">'
            f'<figcaption>{html.escape(didascalia)}</figcaption></figure></section>')


def copertina():
    img = os.path.join(QUI, "immagini", "copertina.jpg")
    stile = ' style="background-image:url(\'../immagini/copertina.jpg\')"' if os.path.exists(img) else ""
    return (f'<section class="pagina-fissa copertina"{stile}><div class="testo-copertina"><p class="serie-c">{SERIE} · {L["libro1"]}</p>'
            f'<h1>{L["titolo2"]}</h1><p class="autore">{L["autore"]}</p></div><p class="editore">Vlad Editore</p></section>')


def frontespizio():
    return (f'<section class="pagina-fissa frontespizio"><p class="serie">{SERIE} · {L["libro1"]}</p>'
            f'<h1>{L["titolo2"]}<small>{L["motto"]}</small></h1><p class="ornamento">✦ ✦ ✦</p><p class="autore">{L["autore"]}</p>'
            f'<img class="emblema" src="{LOGO}" alt="Marchio di Vlad Editore"><p class="editore">Vlad Editore<span>{L["luogo"]}</span></p></section>')


def colophon():
    if RU:
        return (f'<section class="pagina-fissa colophon"><p class="titolo-col">{TITOLO}</p><p>Книга 3 серии <em>{SERIE}</em>.</p>'
                f'<p>Первое цифровое издание, {ANNO}.</p><p>© {ANNO} Влад. Все права защищены.</p><p>Издательство Vlad Editore, Таормина (Сицилия).</p>'
                '<p>Текст Влада, написанный с помощью искусственного интеллекта. Иллюстрация на обложке создана с помощью инструментов искусственного интеллекта. Перевод на русский язык выполнен с помощью искусственного интеллекта.</p>'
                '<p>Персонажи и события вымышлены; любое сходство с реальными людьми случайно. Тропеа существует на самом деле, а народ в банке придуман: в конце книги сказано, что правда, а что нет.</p>'
                f'<img class="emblema-piccolo" src="{LOGO}" alt=""><p class="isbn">ISBN: ожидает присвоения</p></section>')
    return (f'<section class="pagina-fissa colophon"><p class="titolo-col">{TITOLO}</p><p>Libro 3 della collana <em>{SERIE}</em>.</p>'
            f'<p>Prima edizione digitale, {ANNO}.</p><p>© {ANNO} Vlad. Tutti i diritti riservati.</p><p>Pubblicato da Vlad Editore, Taormina (Sicilia).</p>'
            '<p>Testo di Vlad, scritto con l’aiuto dell’intelligenza artificiale. La copertina è stata creata con strumenti di intelligenza artificiale.</p>'
            '<p>Personaggi e vicende sono frutto di fantasia; ogni somiglianza con persone reali è casuale. Tropea esiste davvero, il popolo nel barattolo no: in fondo al libro si spiega cosa è vero e cosa è inventato.</p>'
            f'<img class="emblema-piccolo" src="{LOGO}" alt=""><p class="isbn">ISBN: in attesa di assegnazione</p></section>')


def ultima():
    titoli = (["Ночь Персеид", "Король банки", "Век линз", "Радио «Банка»", "За крышкой"] if RU else
              ["La notte delle Perseidi", "Il re del barattolo", "Il secolo delle lenti", "Radio Barattolo", "Oltre il coperchio"])
    elenco = "".join(f'<p><b>{n}.</b> {t}</p>' for n, t in enumerate(titoli, 1))
    return (f'<section class="pagina-fissa nella-collana"><p class="titolo-libro">{SERIE}</p><h2>{L["nella_collana"]}</h2>'
            f'<p class="motto">{L["motto"]}</p>{elenco}<p style="color:#6f7884;margin-top:4mm"><em>{L["continua"]}</em></p>'
            f'<img class="emblema" src="{LOGO}" alt=""><p class="editore">Vlad Editore</p></section>')


def segmento(html_corpo, nome, fisso, pagina_iniziale=1):
    """Stampa un pezzo di libro in un PDF a parte. Le pagine fisse (copertina, tavole…) sono a margini zero;
    il testo ha i margini e il numero di pagina, che parte da pagina_iniziale."""
    if fisso:
        regole = "@page { size: A5; margin: 0; } html, body { height: 210mm; overflow: hidden; }"
    else:
        regole = ("@page { size: A5; margin: 17mm 15mm 20mm; @bottom-center { content: counter(page); font-family: Georgia, serif; font-size: 9pt; color: #6f7884; } }"
                  f" html {{ counter-reset: page {pagina_iniziale - 1}; }}")
    doc = (f'<!doctype html>\n<html lang="{L["lang"]}">\n<head>\n<meta charset="utf-8">\n<link rel="stylesheet" href="../stile.css">\n'
           f"<style>{regole}</style>\n</head>\n<body>\n{html_corpo}\n</body>\n</html>\n")
    f_html = os.path.join(BUILD, nome + ".html")
    f_pdf = os.path.join(BUILD, nome + ".pdf")
    with open(f_html, "w", encoding="utf-8") as fh:
        fh.write(doc)
    subprocess.run([trova_chromium(), "--headless", "--no-sandbox", "--disable-gpu", "--no-pdf-header-footer",
                    "--print-to-pdf=" + f_pdf, "file://" + f_html], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    if fisso:        # una sola pagina: scarta un'eventuale pagina vuota in più
        sola = os.path.join(BUILD, nome + "-1.pdf")
        subprocess.run(["pdfseparate", "-f", "1", "-l", "1", f_pdf, sola], check=True)
        return sola
    return f_pdf


def pagine(pdf):
    r = subprocess.run(["pdfinfo", pdf], capture_output=True, text=True, check=True).stdout
    return int(re.search(r"Pages:\s+(\d+)", r).group(1))


def main():
    os.makedirs(BUILD, exist_ok=True)
    for vecchio in glob.glob(os.path.join(BUILD, "seg-*")):
        os.remove(vecchio)
    pezzi = []                                  # (html, fisso)
    pezzi += [(copertina(), True), (frontespizio(), True), (colophon(), True)]
    files = sorted(glob.glob(os.path.join(QUI, CAPITOLI, "*.md")))
    if not files:
        sys.exit("Nessun capitolo in " + CAPITOLI)
    for i, f in enumerate(files, 1):
        pezzi.append((capitolo(f, i), False))
        extra = os.path.join(QUI, "capitoli", os.path.basename(f)[:-3] + ".tavola")
        if os.path.exists(extra):
            nome, did = open(extra, encoding="utf-8").read().strip().split("|", 1)
            t = tavola(nome, did)
            if t: pezzi.append((t, True))
    app = os.path.join(QUI, APPENDICE)
    if os.path.exists(app):
        pezzi.append((open(app, encoding="utf-8").read(), False))
    pezzi.append((ultima(), True))

    segmenti, totale = [], 0
    for n, (corpo_html, fisso) in enumerate(pezzi, 1):
        pdf = segmento(corpo_html, f"seg-{n:02d}", fisso, totale + 1)
        segmenti.append(pdf)
        totale += pagine(pdf)
    subprocess.run(["pdfunite"] + segmenti + [PDF], check=True)
    print("Capitoli:", len(files), "| pagine:", totale, "| PDF creato:", PDF)


if __name__ == "__main__":
    main()
