#!/usr/bin/env python3
"""Genera immagini con deAPI v2. Vedi scripts/README.md."""
import os
import argparse
import json
import math
from pathlib import Path
import sys
import tempfile
import time
from urllib.parse import quote, urlparse

import requests
import yaml

API = "https://api.deapi.ai/api/v2"
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from proxy_deapi import via_proxy   # noqa: E402  (proxy di Vlad, variabile DEAPI_PROXY)
CONFIG = Path(__file__).resolve().parents[1] / "config.yaml"


def positive(value):
    number = float(value)
    if not math.isfinite(number) or number <= 0:
        raise argparse.ArgumentTypeError("deve essere un numero positivo finito")
    return number


def positive_int(value):
    number = int(value)
    if number <= 0:
        raise argparse.ArgumentTypeError("deve essere un intero positivo")
    return number


def load_key(path):
    try:
        config = yaml.safe_load(path.read_text(encoding="utf-8"))
    except yaml.YAMLError:
        raise ValueError("config.yaml non è YAML valido") from None
    section = config.get("deapi") if isinstance(config, dict) else None
    key = section.get("api_key") if isinstance(section, dict) else None
    if not isinstance(key, str) or not key.strip() or key == "INSERISCI_LA_CHIAVE":
        raise ValueError(f"Imposta deapi.api_key in {path}")
    return key.strip()


def api_call(key, path, payload=None, timeout=60):
    # Non ritentare POST automaticamente: potrebbe creare un secondo job a pagamento.
    url, headers = via_proxy(API + path, {"Authorization": f"Bearer {key}", "Accept": "application/json",
                                          "User-Agent": "VladEditore-deAPI/1.0"})
    response = requests.request("POST" if payload is not None else "GET", url, json=payload, timeout=timeout, headers=headers)
    if not response.ok:
        # Non stampare corpi/header: potrebbero contenere dati sensibili.
        raise RuntimeError(f"deAPI HTTP {response.status_code}: controlla chiave, credito e parametri del modello")
    data = response.json().get("data")
    if not isinstance(data, dict):
        raise ValueError("Risposta deAPI non valida: manca data")
    return data


def wait_for_job(key, request_id, timeout, interval):
    deadline = time.monotonic() + timeout
    while True:
        remaining = deadline - time.monotonic()
        if remaining <= 0:
            raise TimeoutError(f"Attesa scaduta; riprendi con --request-id {request_id} (non genera un nuovo job)")
        data = api_call(key, "/jobs/" + quote(request_id, safe=""), timeout=min(60, remaining))
        status = data.get("status")
        if status == "done":
            return data
        if status in {"error", "failed", "cancelled", "canceled"} or data.get("error_code"):
            raise RuntimeError("Generazione fallita; controlla il job nella dashboard deAPI")
        if status not in {"pending", "processing", "queued", "running"}:
            raise ValueError("Stato job deAPI non riconosciuto")
        time.sleep(min(interval, max(0, deadline - time.monotonic())))


def download(url, output, force=False):
    if not isinstance(url, str) or urlparse(url).scheme != "https":
        raise ValueError("URL risultato mancante o non HTTPS")
    output.parent.mkdir(parents=True, exist_ok=True)
    temporary = None
    try:
        # Il CDN non deve ricevere la chiave API. Salva prima in un file temporaneo.
        indirizzo, h = via_proxy(url)          # senza chiave: il CDN non deve riceverla
        with requests.get(indirizzo, stream=True, timeout=120, **({"headers": h} if h else {})) as response:
            if not response.ok:
                raise RuntimeError(f"Download HTTP {response.status_code}")
            if not response.headers.get("Content-Type", "").lower().startswith("image/"):
                raise ValueError("Il risultato scaricato non è un'immagine")
            with tempfile.NamedTemporaryFile(dir=output.parent, delete=False) as handle:
                temporary = Path(handle.name)
                for chunk in response.iter_content(1024 * 1024):
                    handle.write(chunk)
        if temporary.stat().st_size == 0:
            raise ValueError("Immagine scaricata vuota")
        if force:
            temporary.replace(output)
        else:
            # Hard link atomico: non sovrascrive nemmeno se il file appare nel frattempo.
            output.hardlink_to(temporary)
    finally:
        if temporary is not None:
            temporary.unlink(missing_ok=True)


def parser():
    p = argparse.ArgumentParser(description=__doc__)
    source = p.add_mutually_exclusive_group(required=True)
    source.add_argument("--prompt", "-p")
    source.add_argument("--prompt-file", type=Path, help="File UTF-8; '-' per stdin")
    source.add_argument("--request-id", help="Riprendi un job senza nuove spese di generazione")
    p.add_argument("--output", "-o", type=Path, required=True, help="Percorso immagine (bytes originali, nessuna conversione)")
    p.add_argument("--config", type=Path, default=CONFIG)
    p.add_argument("--model", default="Flux_2_Klein_4B_BF16")
    p.add_argument("--width", type=positive_int, default=1024)
    p.add_argument("--height", type=positive_int, default=1024)
    p.add_argument("--steps", type=positive_int, default=4)
    p.add_argument("--seed", type=int, default=-1)
    p.add_argument("--guidance", type=float)
    p.add_argument("--negative-prompt")
    p.add_argument("--timeout", type=positive, default=600, help="Secondi massimi di polling")
    p.add_argument("--poll-interval", type=positive, default=5)
    p.add_argument("--force", action="store_true", help="Consenti sovrascrittura")
    return p


def main(argv=None):
    args = parser().parse_args(argv)
    key = None
    try:
        if args.output.exists() and not args.force:
            raise ValueError("Output già esistente: usa --force per sovrascriverlo")
        key = load_key(args.config)
        request_id = args.request_id
        if not request_id:
            prompt = args.prompt
            if args.prompt_file:
                prompt = sys.stdin.read() if str(args.prompt_file) == "-" else args.prompt_file.read_text(encoding="utf-8")
            if not prompt or not prompt.strip():
                raise ValueError("Il prompt non può essere vuoto")
            payload = dict(prompt=prompt.strip(), model=args.model, width=args.width,
                           height=args.height, steps=args.steps, seed=args.seed)
            if args.guidance is not None:
                if not math.isfinite(args.guidance):
                    raise ValueError("guidance deve essere finito")
                payload["guidance"] = args.guidance
            if args.negative_prompt:
                payload["negative_prompt"] = args.negative_prompt
            request_id = api_call(key, "/images/generations", payload).get("request_id")
            if not isinstance(request_id, str) or not request_id:
                raise ValueError("Risposta deAPI senza request_id")
        print(f"request_id: {request_id}", file=sys.stderr, flush=True)
        result = wait_for_job(key, request_id, args.timeout, args.poll_interval)
        download(result.get("result_url"), args.output, args.force)
        print(json.dumps({"request_id": request_id, "output": str(args.output)}, ensure_ascii=False))
        return 0
    except requests.RequestException:
        print("Errore di rete. Se il job è stato inviato, verifica la dashboard prima di rigenerare.", file=sys.stderr)
    except (ValueError, OSError, RuntimeError) as exc:
        message = str(exc).replace(key, "[REDACTED]") if key else str(exc)
        print(f"Errore: {message}", file=sys.stderr)
    except KeyboardInterrupt:
        print("Interrotto; il job remoto non viene annullato. Riprendi con --request-id.", file=sys.stderr)
        return 130
    return 1


if __name__ == "__main__":
    sys.exit(main())
