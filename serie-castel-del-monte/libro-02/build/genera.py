#!/usr/bin/env python3
"""Genera un'illustrazione con Polza (API Media), usando immagini di riferimento
per mantenere i personaggi uguali in tutto il libro.

Esempi (dalla radice del repository):
  python3 serie-castel-del-monte/libro-01/genera.py --scheda alessia --out riferimenti/alessia.jpg \\
      --scena "standing in front of Castel del Monte in Apulia"
  python3 serie-castel-del-monte/libro-01/genera.py --mondo oblio --out immagini/cap03.jpg --aspect 2:3 \\
      --personaggi alessia,lucerna --scena "Alessia holds the glowing lantern in a grey hall with upside down stairs"

--mondo sceglie lo stile: presente | oblio | duecento (da personaggi.json).
La chiave API si legge dalla variabile d'ambiente POLZA (non viene mai stampata).
"""
import argparse
import base64
import json
import os
import subprocess
import sys
import tempfile

QUI = os.path.dirname(os.path.abspath(__file__))
URL = "https://polza.ai/api/v1/media"
MODELLO = "bytedance/seedream-4.5"


def file_base64(percorso):
    with open(percorso, "rb") as f:
        return "data:image/jpeg;base64," + base64.b64encode(f.read()).decode("ascii")


def chiedi(corpo, chiave):
    with tempfile.NamedTemporaryFile("w", suffix=".json", delete=False, encoding="utf-8") as t:
        json.dump(corpo, t)
        nome = t.name
    try:
        r = subprocess.run(["curl", "-sS", "-m", "240", "-X", "POST", URL, "-H", "Authorization: Bearer " + chiave,
                            "-H", "Content-Type: application/json", "-d", "@" + nome], capture_output=True, text=True)
    finally:
        os.unlink(nome)
    if r.returncode != 0:
        sys.exit("Errore di rete: " + r.stderr.strip())
    try:
        return json.loads(r.stdout)
    except json.JSONDecodeError:
        sys.exit("Risposta non valida: " + r.stdout[:300])


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--out", required=True, help="file di uscita, relativo alla cartella del libro")
    p.add_argument("--personaggi", default="", help="nomi separati da virgola (da personaggi.json)")
    p.add_argument("--scena", default="", help="descrizione della scena in inglese")
    p.add_argument("--scheda", default="", help="genera la scheda di riferimento di un personaggio")
    p.add_argument("--mondo", default="presente", choices=["presente", "oblio", "duecento"])
    p.add_argument("--aspect", default="2:3", help="rapporto, es. 2:3 (verticale), 3:2, 1:1")
    a = p.parse_args()

    chiave = os.environ.get("POLZA")
    if not chiave:
        sys.exit("Manca la variabile d'ambiente POLZA")
    cfg = json.load(open(os.path.join(QUI, "personaggi.json"), encoding="utf-8"))
    pers = cfg["personaggi"]

    if a.scheda:
        nomi = [a.scheda]
        scena = ("Character reference sheet: full body of the character standing, front view, neutral friendly pose, "
                 "plain light neutral background, clear details of face, hair and clothes. " + a.scena)
    else:
        nomi = [n for n in a.personaggi.split(",") if n]
        scena = a.scena
    for n in nomi:
        if n not in pers:
            sys.exit("Personaggio sconosciuto: " + n + " (disponibili: " + ", ".join(pers) + ")")

    descrizioni = " ".join(pers[n]["descrizione"] + "." for n in nomi)
    prompt = ("Keep the exact same characters as in the reference images (same faces, hair, clothes). "
              + descrizioni + " " + scena + " Style: " + cfg["stili"][a.mondo] + ".")

    immagini = []
    for n in nomi:
        rif = pers[n].get("riferimento")
        if rif and not a.scheda:
            percorso = os.path.join(QUI, rif)
            if os.path.exists(percorso):
                immagini.append({"type": "base64", "data": file_base64(percorso)})

    corpo = {"model": MODELLO, "input": {"prompt": prompt, "aspect_ratio": a.aspect, "output_format": "jpeg"}, "async": False}
    if immagini:
        corpo["input"]["images"] = immagini
    ris = chiedi(corpo, chiave)
    dati = ris.get("data") or []
    if not dati or not dati[0].get("url"):
        sys.exit("Nessuna immagine nella risposta: " + json.dumps(ris, ensure_ascii=False)[:400])
    out = os.path.join(QUI, a.out)
    os.makedirs(os.path.dirname(out), exist_ok=True)
    subprocess.run(["curl", "-sS", "-m", "90", "-o", out, dati[0]["url"]], check=True)
    print("Immagine salvata:", out, "| riferimenti:", len(immagini), "| costo (RUB):", (ris.get("usage") or {}).get("cost_rub"))


if __name__ == "__main__":
    main()
