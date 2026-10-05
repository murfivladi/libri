#!/usr/bin/env python3
"""Genera le illustrazioni della Gazzetta con deAPI (modello FLUX.2 Klein 4B).

Le descrizioni stanno in numero-NN/immagini.json; le immagini vanno in numero-NN/img/NOME.png.
Le immagini che esistono già non vengono rifatte (per rifarne una, cancellala).

Serve la chiave nella variabile d'ambiente DEAPI (oppure DEAPI_API_KEY).
Uso:  python3 gazzetta/immagini.py 01 [nome ...]
"""
import base64, json, os, sys, urllib.request

ENDPOINT = "https://oai.deapi.ai/v1/images/generations"   # API compatibile OpenAI, risponde quando l'immagine è pronta
MODELLO = "Flux_2_Klein_4B_BF16"

QUI = os.path.dirname(os.path.abspath(__file__))
N = sys.argv[1] if len(sys.argv) > 1 else "01"
SOLO = set(sys.argv[2:])
CARTELLA = os.path.join(QUI, "numero-" + N)
chiave = os.environ.get("DEAPI") or os.environ.get("DEAPI_API_KEY")
if not chiave:
    sys.exit("Manca la chiave: imposta la variabile DEAPI")

dati = json.load(open(os.path.join(CARTELLA, "immagini.json"), encoding="utf-8"))
stile = dati.pop("_stile", "")
os.makedirs(os.path.join(CARTELLA, "img"), exist_ok=True)

for nome, d in dati.items():
    if SOLO and nome not in SOLO:
        continue
    uscita = os.path.join(CARTELLA, "img", nome + ".png")
    if os.path.exists(uscita):
        continue
    corpo = json.dumps({"model": MODELLO, "prompt": d["prompt"] + ", " + stile, "size": d.get("size", "1024x1024"),
                        "n": 1, "response_format": "b64_json"}).encode()
    req = urllib.request.Request(ENDPOINT, data=corpo, headers={"Authorization": "Bearer " + chiave,
                                                                "Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=300) as r:
        risposta = json.load(r)
    open(uscita, "wb").write(base64.b64decode(risposta["data"][0]["b64_json"]))
    print("ok", nome)
