#!/usr/bin/env python3
"""Crea un piccolo PDF di prova per i test della Scuola: 3 pagine, sulla 2 e sulla 3 ci sono i codici CFC-1 e CFC-2
accanto a un simbolo (come nel libro vero). Crea anche un breve file WAV. Uso: python3 pdf_scuola.py libro.pdf voce.wav"""
import math
import struct
import sys
import wave

from reportlab.lib.pagesizes import A4
from reportlab.pdfgen import canvas

pdf, wav = sys.argv[1], sys.argv[2]
c = canvas.Canvas(pdf, pagesize=A4)
W, H = A4
c.setFont('Helvetica-Bold', 28); c.drawString(60, H - 120, 'Chi fa cosa?'); c.showPage()
for numero, (codice, testo) in enumerate([('CFC-1', 'Esercizio: trova il soggetto'), ('CFC-2', 'Ascolta Luce')], start=2):
    c.setFont('Helvetica', 14); c.drawString(60, H - 80, f'Pagina {numero}: il soggetto e il predicato.')
    c.setFillColorRGB(0.94, 0.49, 0.07); c.circle(75, H - 300, 12, fill=1, stroke=0)
    c.setFillColorRGB(0, 0, 0); c.setFont('Helvetica-Bold', 12); c.drawString(95, H - 304, codice)
    c.setFont('Helvetica', 12); c.drawString(150, H - 304, testo)
    c.showPage()
c.save()

with wave.open(wav, 'wb') as w:
    w.setnchannels(1); w.setsampwidth(2); w.setframerate(8000)
    w.writeframes(b''.join(struct.pack('<h', int(8000 * math.sin(2 * math.pi * 440 * i / 8000))) for i in range(8000)))
