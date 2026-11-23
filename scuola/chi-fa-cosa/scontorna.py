#!/usr/bin/env python3
"""Toglie lo sfondo bianco alle pose (pose/*.jpg -> figure/*.png), riempiendo dai bordi.
Uso: python3 scuola/chi-fa-cosa/scontorna.py [nome ...]   (senza nomi: tutte le pose nuove)"""
import sys, os
from collections import deque
from PIL import Image
QUI = os.path.dirname(os.path.abspath(__file__))
def scontorna(src, dst, soglia=225):
    im = Image.open(src).convert("RGB"); w, h = im.size; px = im.load()
    m = bytearray(w * h); q = deque()
    for x in range(w): q += [(x, 0), (x, h - 1)]
    for y in range(h): q += [(0, y), (w - 1, y)]
    while q:
        x, y = q.popleft(); i = y * w + x
        if m[i] or min(px[x, y]) < soglia: continue
        m[i] = 1
        if x > 0: q.append((x - 1, y))
        if x < w - 1: q.append((x + 1, y))
        if y > 0: q.append((x, y - 1))
        if y < h - 1: q.append((x, y + 1))
    a = Image.frombytes("L", (w, h), bytes(0 if v else 255 for v in m))
    out = im.convert("RGBA"); out.putalpha(a); out = out.crop(a.getbbox()); out.save(dst)
    return out.size
nomi = sys.argv[1:] or [f[:-4] for f in os.listdir(os.path.join(QUI, "pose")) if f.endswith(".jpg")
                       and not os.path.exists(os.path.join(QUI, "figure", f[:-4] + ".png"))]
for n in sorted(nomi):
    print(n, scontorna(os.path.join(QUI, "pose", n + ".jpg"), os.path.join(QUI, "figure", n + ".png")))
