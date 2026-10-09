#!/usr/bin/env python3
"""Genera la copertina e le tavole del libro con deAPI (modello FLUX.2 Klein 4B).

Le descrizioni stanno in immagini.json; le immagini vanno in immagini/NOME.jpg.
Le immagini che esistono già non vengono rifatte (per rifarne una, cancellala).

Serve la chiave nella variabile d'ambiente DEAPI (oppure DEAPI_API_KEY).
Uso:  python3 immagini.py [nome ...]
"""
import json, os, sys, time, urllib.error, urllib.request

API = os.environ.get("DEAPI_URL", "https://api.deapi.ai").rstrip("/") + "/api/v1/client"   # DEAPI_URL = proxy (es. https://vladproxy.v7755837.deno.net)
MODELLO = "Flux_2_Klein_4B_BF16"

QUI = os.path.dirname(os.path.abspath(__file__))
SOLO = set(sys.argv[1:])
chiave = os.environ.get("DEAPI") or os.environ.get("DEAPI_API_KEY")
if not chiave:
    sys.exit("Manca la chiave: imposta la variabile DEAPI")

dati = json.load(open(os.path.join(QUI, "immagini.json"), encoding="utf-8"))
stile = dati.pop("_stile", "")
os.makedirs(os.path.join(QUI, "immagini"), exist_ok=True)
UA = {"User-Agent": "Mozilla/5.0 (VladEditore)", "Accept": "application/json"}   # Cloudflare blocca lo user-agent di urllib


def scarica(url, corpo=None, intestazioni={}):
    for tentativo in range(8):
        req = urllib.request.Request(url, data=corpo, headers={**UA, **intestazioni})
        try:
            with urllib.request.urlopen(req, timeout=120) as r:
                return r.read()
        except urllib.error.HTTPError as e:
            if e.code != 429:
                raise
            time.sleep(10 * (tentativo + 1))   # troppe richieste: aspetta e riprova
    sys.exit("deAPI risponde sempre 429 (troppe richieste)")


def chiama(percorso, dati=None):
    corpo = json.dumps(dati).encode() if dati is not None else None
    return json.loads(scarica(API + percorso, corpo, {"Authorization": "Bearer " + chiave, "Content-Type": "application/json"}))

for nome, d in dati.items():
    if SOLO and nome not in SOLO:
        continue
    uscita = os.path.join(QUI, "immagini", nome + ".jpg")
    if os.path.exists(uscita):
        continue
    w, h = (int(x) for x in d.get("size", "1024x1024").split("x"))
    rid = chiama("/txt2img", {"model": MODELLO, "prompt": d["prompt"] + ", " + stile,
                              "width": w, "height": h, "steps": 4, "seed": d.get("seed", 7)})["data"]["request_id"]
    while True:
        time.sleep(8)
        st = chiama("/request-status/" + rid)["data"]
        if st["status"] == "done":
            break
        if st.get("error_code"):
            sys.exit(f"errore {nome}: {st.get('error_reason')}")
    open(uscita, "wb").write(scarica(st["results_alt_formats"]["jpg"]))   # jpg: PDF più leggeri
    print("ok", nome)
