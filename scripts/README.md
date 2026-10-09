# Generazione immagini deAPI

Installa: `python3 -m pip install -r scripts/requirements.txt`.
Inserisci la chiave in `deapi.api_key` nel `config.yaml` alla radice. Il file è
tracciabile su Git come richiesto: un repository privato non elimina il rischio
(accessi dei collaboratori, copie e cronologia). Non pubblicarlo; in caso di
esposizione revoca la chiave nella dashboard.

```bash
python3 scripts/deapi.py -p "L'Etna al tramonto, illustrazione fantasy" \
  -o libro-01/immagini/etna.png --width 1024 --height 1024 --seed 7
python3 scripts/deapi.py --prompt-file prompt.txt -o grafica/copertina.png
cat prompt.txt | python3 scripts/deapi.py --prompt-file - -o grafica/prova.png
python3 scripts/deapi.py --request-id ID_DEL_JOB -o grafica/recuperata.png
```

Altri argomenti: `--model`, `--steps`, `--guidance`, `--negative-prompt`,
`--config`, `--timeout` (polling, default 600 secondi), `--poll-interval`, `--force`.
Il config predefinito è relativo allo script, non alla directory corrente.
Limiti di dimensioni, passi e supporto al prompt negativo dipendono dal modello:
consulta https://docs.deapi.ai/api/v2/utilities/models prima di cambiarlo.

Usa l'API nativa v2: `POST /images/generations`, polling `GET /jobs/{id}`.
La generazione consuma credito. Il request ID viene stampato subito su stderr;
stdout contiene JSON con ID e percorso finale. Nessun retry automatico dei POST,
per evitare spese duplicate; dopo un errore di rete durante l'invio controlla la
dashboard prima di riprovare. Timeout e interruzioni non annullano il job remoto.
Il file esistente è protetto salvo `--force`; il download è atomico e non invia
la chiave al CDN. I bytes dell'immagine sono salvati senza conversione: usa
l'estensione del formato restituito dal modello (normalmente PNG).

Test senza API né spese: `python3 -m unittest discover -s scripts/tests`.

## Proxy
Tutti gli script deAPI del repository passano dal proxy se c'è la variabile `DEAPI_PROXY` (es. `DEAPI_PROXY=https://vladik-proxy.v7755837.deno.net python3 scripts/deapi.py …`): chiamate e download dei risultati vanno al proxy con l'intestazione `vladik-proxy` che indica la destinazione vera (`https://api.deapi.ai`, `https://results.deapi.ai`). Codice comune: `scripts/proxy_deapi.py`. Senza la variabile chiamano deAPI direttamente.
