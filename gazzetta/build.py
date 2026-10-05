#!/usr/bin/env python3
"""Crea i PDF della Gazzetta di Vlad (italiano e russo).

Uso:  python3 gazzetta/build.py 01
Output: gazzetta/numero-01/gazzetta-di-vlad-01-it.pdf e gazzetta-vlada-01-ru.pdf
"""
import glob, os, subprocess, sys

QUI = os.path.dirname(os.path.abspath(__file__))
N = sys.argv[1] if len(sys.argv) > 1 else "01"
CARTELLA = os.path.join(QUI, "numero-" + N)
USCITE = {"it": "gazzetta-di-vlad-%s-it.pdf", "ru": "gazzetta-vlada-%s-ru.pdf"}


def chromium():
    for c in glob.glob("/opt/pw-browsers/chromium-*/chrome-linux/chrome") + ["/usr/bin/chromium"]:
        if os.path.exists(c):
            return c
    sys.exit("Chromium non trovato")


for lingua, nome in USCITE.items():
    sorgente = os.path.join(CARTELLA, "gazzetta-%s-%s.html" % (N, lingua))
    pdf = os.path.join(CARTELLA, nome % N)
    subprocess.run([chromium(), "--headless", "--no-sandbox", "--disable-gpu", "--no-pdf-header-footer",
                    "--print-to-pdf=" + pdf, "file://" + sorgente], check=True,
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    print("ok", pdf)
