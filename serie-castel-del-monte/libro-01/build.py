#!/usr/bin/env python3
"""Costruisce il PDF di «La Pietra al Contrario» (La Porta di Castel del Monte, libro 1).

I capitoli stanno in capitoli/NN-*.md. Formato (semplice):
  # Titolo del capitolo
  @mondo: presente | oblio | duecento      (cambia i colori del capitolo)
  @dove: Castel del Monte, oggi            (riga piccola sotto il titolo)
  paragrafi separati da una riga vuota; i dialoghi iniziano con «—»; *corsivo*
  * * *                                    (stacco di scena)
  > testo                                  (messaggio scritto a mano nel quaderno)
  || testo                                 (iscrizione)
  [[tavola:cap03|didascalia]]              (illustrazione a tutta pagina: immagini/cap03.jpg)

Uso:  python3 serie-castel-del-monte/libro-01/build.py
Output: serie-castel-del-monte/libro-01/build/la-pietra-al-contrario.pdf
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
PDF = os.path.join(BUILD, "la-pietra-al-contrario.pdf")
LOGO = "../../../grafica/emblema-700.png"
SERIE = "La Porta di Castel del Monte"
TITOLO = "La Pietra al Contrario"
ANNO = 2026


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
           f'<p class="titolo-libro">{html.escape(SERIE)}</p>', f'<p class="numero-cap">Capitolo {numero}</p>',
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
    sfondo = '<img class="sfondo" src="../immagini/copertina.jpg" alt="">' if os.path.exists(img) else ""
    return (f'<section class="pagina-fissa copertina">{sfondo}<div class="testo-copertina"><p class="serie-c">{SERIE} · Libro 1</p>'
            f'<h1>La Pietra<br>al Contrario</h1><p class="autore">Vlad</p></div><p class="editore">Vlad Editore</p></section>')


def frontespizio():
    return (f'<section class="pagina-fissa frontespizio"><p class="serie">{SERIE} · Libro 1</p>'
            f'<h1>La Pietra<br>al Contrario<small>Otto porte, un solo segreto</small></h1><p class="ornamento">✦ ✦ ✦</p><p class="autore">Vlad</p>'
            f'<img class="emblema" src="{LOGO}" alt="Marchio di Vlad Editore"><p class="editore">Vlad Editore<span>Taormina · Sicilia</span></p></section>')


def colophon():
    return (f'<section class="pagina-fissa colophon"><p class="titolo-col">{TITOLO}</p><p>Libro 1 della collana <em>{SERIE}</em>.</p>'
            f'<p>Prima edizione digitale, {ANNO}.</p><p>© {ANNO} Vlad. Tutti i diritti riservati.</p><p>Pubblicato da Vlad Editore, Taormina (Sicilia).</p>'
            '<p>Testo di Vlad, scritto con l’aiuto dell’intelligenza artificiale. Le illustrazioni e la copertina sono state create con strumenti di intelligenza artificiale.</p>'
            '<p>Personaggi e vicende sono frutto di fantasia; ogni somiglianza con persone reali è casuale. Federico II di Svevia, Michele Scoto e Castel del Monte sono esistiti davvero, ma ciò che fanno in questo libro è inventato: le note storiche, in fondo, dicono cosa è vero.</p>'
            f'<img class="emblema-piccolo" src="{LOGO}" alt=""><p class="isbn">ISBN: in attesa di assegnazione</p></section>')


def ultima():
    return (f'<section class="pagina-fissa nella-collana"><p class="titolo-libro">{SERIE}</p><h2>Nella stessa collana</h2>'
            '<p class="motto">Otto porte, un solo segreto</p><p><b>1.</b> La Pietra al Contrario</p><p style="color:#8c5a2b;margin-top:4mm"><em>Continua…</em></p>'
            f'<img class="emblema" src="{LOGO}" alt=""><p class="editore">Vlad Editore</p></section>')


def main():
    os.makedirs(BUILD, exist_ok=True)
    pezzi = [copertina(), frontespizio(), colophon()]
    files = sorted(glob.glob(os.path.join(QUI, "capitoli", "*.md")))
    if not files:
        sys.exit("Nessun capitolo in capitoli/")
    for i, f in enumerate(files, 1):
        pezzi.append(capitolo(f, i))
        extra = os.path.join(QUI, "capitoli", os.path.basename(f)[:-3] + ".tavola")
        if os.path.exists(extra):
            nome, did = open(extra, encoding="utf-8").read().strip().split("|", 1)
            pezzi.append(tavola(nome, did))
    app = os.path.join(QUI, "appendice.html")
    if os.path.exists(app):
        pezzi.append(open(app, encoding="utf-8").read())
    pezzi.append(ultima())
    doc = ('<!doctype html>\n<html lang="it">\n<head>\n<meta charset="utf-8">\n'
           f"<title>{TITOLO}</title>\n"
           '<link rel="stylesheet" href="../stile.css">\n</head>\n<body>\n' + "\n".join(pezzi) + "\n</body>\n</html>\n")
    with open(HTML_UNITO, "w", encoding="utf-8") as fh:
        fh.write(doc)
    subprocess.run([trova_chromium(), "--headless", "--no-sandbox", "--disable-gpu", "--no-pdf-header-footer",
                    "--print-to-pdf=" + PDF, "file://" + HTML_UNITO], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    print("Capitoli:", len(files), "| PDF creato:", PDF)


if __name__ == "__main__":
    main()
