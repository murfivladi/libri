#!/usr/bin/env python3
"""Crea frontespizio, colophon e pagina "Nella stessa collana" per i 5 libri della serie.

Uso:  python3 libro-01/frontespizi.py
Scrive in libro-NN/pagine/:  00a-frontespizio.html, 00b-colophon.html, zz-nella-collana.html
(i nomi sono scelti perché l'ordine alfabetico li metta nel posto giusto: dopo la copertina e dopo l'ultimo capitolo).
Poi rifai i PDF con:  python3 libro-NN/build.py
"""
import os

RADICE = os.path.normpath(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".."))
ANNO = 2026
SERIE = "Il Custode dell'Etna"
LOGO = "../../grafica/emblema-700.png"   # relativo sia a pagine/ sia a build/

# numero, titolo (con a capo per il frontespizio), titolo semplice
LIBRI = [
    (1, "Il Custode<br>dell'Etna", "Il Custode dell'Etna"),
    (2, "Il Custode<br>e il Fantasma<br>del Teatro", "Il Custode e il Fantasma del Teatro"),
    (3, "Il Custode<br>e la Lira<br>del Mare", "Il Custode e la Lira del Mare"),
    (4, "Il Custode<br>e l'Isola<br>dell'Immortale", "Il Custode e l'Isola dell'Immortale"),
    (5, "Il Custode<br>e l'Inverno<br>Eterno", "Il Custode e l'Inverno Eterno"),
]

TESTA = """<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{titolo} - {parte}</title>
<link rel="stylesheet" href="../stile.css">
</head>
<body>
"""
CODA = "</body>\n</html>\n"


def frontespizio(n, titolo_br, titolo):
    return TESTA.format(titolo=titolo, parte="Frontespizio") + f"""<main class="pagina frontespizio">
  <p class="serie">{SERIE} &middot; Libro {n}</p>
  <h1>{titolo_br}</h1>
  <p class="ornamento">&#10022; &#10022; &#10022;</p>
  <p class="autore">Vlad</p>
  <img class="emblema" src="{LOGO}" alt="Marchio di Vlad Editore">
  <p class="editore">Vlad Editore<span>Taormina &middot; Sicilia</span></p>
</main>
""" + CODA


def colophon(n, titolo_br, titolo):
    return TESTA.format(titolo=titolo, parte="Colophon") + f"""<main class="pagina colophon">
  <p class="titolo-col">{titolo}</p>
  <p>Libro {n} della serie <em>{SERIE}</em>.</p>
  <p>Prima edizione digitale, {ANNO}.</p>
  <p>&copy; {ANNO} Vlad. Tutti i diritti riservati.</p>
  <p>Pubblicato da Vlad Editore, Taormina (Sicilia).</p>
  <p>Testo di Vlad, scritto con l'aiuto dell'intelligenza artificiale. Le illustrazioni e la copertina sono state create con strumenti di intelligenza artificiale.</p>
  <p>Personaggi e vicende sono frutto di fantasia; ogni somiglianza con persone reali &egrave; casuale.</p>
  <img class="emblema-piccolo" src="{LOGO}" alt="">
  <p class="isbn">ISBN: in attesa di assegnazione</p>
</main>
""" + CODA


def nella_collana(n, titolo_br, titolo):
    righe = []
    for k, _, t in LIBRI:
        classe = ' class="questo"' if k == n else ""
        righe.append(f'    <li{classe}><span class="n">{k}.</span>{t}</li>')
    voci = "\n".join(righe)
    return TESTA.format(titolo=titolo, parte="Nella stessa collana") + f"""<main class="pagina nella-collana">
  <p class="titolo-libro">{SERIE}</p>
  <h2>Nella stessa collana</h2>
  <ol>
{voci}
  </ol>
  <img class="emblema" src="{LOGO}" alt="">
  <p class="editore">Vlad Editore</p>
</main>
""" + CODA


def main():
    for n, titolo_br, titolo in LIBRI:
        cartella = os.path.join(RADICE, f"libro-{n:02d}", "pagine")
        for nome, funzione in (("00a-frontespizio.html", frontespizio), ("00b-colophon.html", colophon), ("zz-nella-collana.html", nella_collana)):
            with open(os.path.join(cartella, nome), "w", encoding="utf-8") as f:
                f.write(funzione(n, titolo_br, titolo))
        print(f"libro-{n:02d}: frontespizio, colophon e pagina collana scritti")


if __name__ == "__main__":
    main()
