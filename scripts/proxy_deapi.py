"""Fa passare le chiamate a deAPI (api.deapi.ai e results.deapi.ai) dal proxy di Vlad, se c'è.

Proxy: variabile d'ambiente DEAPI_PROXY (es. https://vladik-proxy.v7755837.deno.net).
Il proxy riceve l'indirizzo vero di destinazione nell'intestazione «vladik-proxy» (es. https://api.deapi.ai).
Senza DEAPI_PROXY gli indirizzi restano quelli di deAPI.
"""
import os
from urllib.parse import urlsplit


def proxy():
    return os.environ.get("DEAPI_PROXY", "").strip().rstrip("/")


def via_proxy(url, intestazioni=None):
    """Restituisce (indirizzo da chiamare, intestazioni) per un indirizzo di deAPI."""
    h = dict(intestazioni or {})
    p = proxy()
    if not p:
        return url, h
    x = urlsplit(url)
    h["vladik-proxy"] = f"{x.scheme}://{x.netloc}"
    return p + (x.path or "/") + (("?" + x.query) if x.query else ""), h
