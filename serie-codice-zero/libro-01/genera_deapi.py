#!/usr/bin/env python3
"""Genera un'immagine con deAPI.ai (modello Flux_2_Klein_4B_BF16).
Si usa quando il saldo Polza non basta. La chiave si legge da DEAPI (non viene mai stampata).

Uso:  python3 genera_deapi.py --out immagini/copertina.jpg --personaggi teo,irene --scena "..."
"""
import argparse
import json
import os
import subprocess
import sys
import time
import urllib.request

QUI = os.path.dirname(os.path.abspath(__file__))
BASE = "https://api.deapi.ai/api/v1/client"
MODELLO = "Flux_2_Klein_4B_BF16"


def chiama(metodo, url, chiave, corpo=None):
    req = urllib.request.Request(url, method=metodo, headers={
        "Authorization": "Bearer " + chiave, "Accept": "application/json", "User-Agent": "curl/8.5.0", "Content-Type": "application/json"},
        data=json.dumps(corpo).encode() if corpo else None)
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.load(r)


def main():
    p = argparse.ArgumentParser()
    p.add_argument("--out", required=True)
    p.add_argument("--personaggi", default="")
    p.add_argument("--scena", required=True)
    p.add_argument("--larghezza", type=int, default=832)
    p.add_argument("--altezza", type=int, default=1248)
    p.add_argument("--seed", type=int, default=7)
    a = p.parse_args()
    chiave = os.environ.get("DEAPI")
    if not chiave:
        sys.exit("Manca la variabile d'ambiente DEAPI")
    cfg = json.load(open(os.path.join(QUI, "personaggi.json"), encoding="utf-8"))
    nomi = [n for n in a.personaggi.split(",") if n]
    desc = " ".join(cfg["personaggi"][n]["descrizione"] + "." for n in nomi)
    prompt = desc + " " + a.scena + " Style: " + cfg["stili"]["presente"] + "."
    rid = chiama("POST", BASE + "/txt2img", chiave, {"prompt": prompt, "model": MODELLO, "width": a.larghezza,
                 "height": a.altezza, "steps": 4, "seed": a.seed})["data"]["request_id"]
    for _ in range(90):
        time.sleep(4)
        d = chiama("GET", BASE + "/request-status/" + rid, chiave)["data"]
        if d.get("status") == "done" and d.get("result_url"):
            out = os.path.join(QUI, a.out)
            os.makedirs(os.path.dirname(out), exist_ok=True)
            tmp = out + ".tmp"
            subprocess.run(["curl", "-sS", "-m", "90", "-o", tmp, d["result_url"]], check=True)
            subprocess.run(["convert", tmp, "-quality", "92", out], check=True)
            os.remove(tmp)
            print("Immagine salvata:", out, "| costo ($):", d.get("price"))
            return
        if d.get("status") in ("error", "failed"):
            sys.exit("Errore: " + str(d.get("error_reason")))
    sys.exit("Tempo scaduto")


if __name__ == "__main__":
    main()
