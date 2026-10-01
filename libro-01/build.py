#!/usr/bin/env python3
"""Costruisce il PDF del libro unendo le pagine HTML di pagine/.

Uso:  python3 libro-01/build.py
Output: libro-01/build/il-custode-dell-etna.pdf
"""
import glob
import os
import re
import subprocess
import sys

QUI = os.path.dirname(os.path.abspath(__file__))
PAGINE = sorted(glob.glob(os.path.join(QUI, "pagine", "*.html")))
BUILD = os.path.join(QUI, "build")
HTML_UNITO = os.path.join(BUILD, "libro-unito.html")
PDF = os.path.join(BUILD, "il-custode-dell-etna.pdf")


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


def corpo(path):
    testo = open(path, encoding="utf-8").read()
    m = re.search(r"<body>(.*)</body>", testo, re.S)
    if not m:
        sys.exit("Pagina senza <body>: " + path)
    return m.group(1).strip()


def main():
    if not PAGINE:
        sys.exit("Nessuna pagina in pagine/")
    os.makedirs(BUILD, exist_ok=True)
    pezzi = [corpo(p) for p in PAGINE]
    html = (
        '<!doctype html>\n<html lang="it">\n<head>\n<meta charset="utf-8">\n'
        "<title>Il Custode dell'Etna</title>\n"
        '<link rel="stylesheet" href="../stile.css">\n</head>\n<body>\n'
        + "\n".join(pezzi)
        + "\n</body>\n</html>\n"
    )
    with open(HTML_UNITO, "w", encoding="utf-8") as f:
        f.write(html)
    subprocess.run(
        [
            trova_chromium(),
            "--headless",
            "--no-sandbox",
            "--disable-gpu",
            "--no-pdf-header-footer",
            "--print-to-pdf=" + PDF,
            "file://" + HTML_UNITO,
        ],
        check=True,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    print("Pagine unite:", len(PAGINE))
    print("PDF creato:", PDF)


if __name__ == "__main__":
    main()
