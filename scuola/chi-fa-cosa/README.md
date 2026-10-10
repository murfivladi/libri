# «Chi fa cosa?» · grammatica per la 2ª media · Vlad Editore Scuola

Autore: **Vladislav Balesnyi**. Formato **A4 a colori**. Parte 1 (blu): ripasso grammaticale. Parte 2 (arancione): analisi logica.
Personaggi: Vlad cartone (felpa arancione, tablet) e Luce, la sua Shiba Inu con gli occhiali (schede in `riferimenti/`).

## Come è fatto un capitolo
Apertura (scena, domanda, obiettivi) → per cominciare → lezioni (una pagina ciascuna, box Ricorda / Attenzione / Trucco / Lo sapevi?, fumetti con Vlad e Luce)
→ esercizi (● ●● ●●●, «risposte esatte __/N», codice + QR) → dal testo alla grammatica → mappa, riassunto in 5 righe, errori comuni → verifica /30 e autovalutazione.

## Libro intero e indice
- `inizio/inizio.html`: copertina + pagine 1–6 (frontespizio, colophon, presentazione e grazie ai prof, protagonisti, come è fatto il libro, indice).
- `NODE_PATH=$(npm root -g) node scuola/chi-fa-cosa/libro.js` → `build/chi-fa-cosa.pdf` (libro completo) e `copertina/chi-fa-cosa-copertina-fronte.png`.
- L'**indice si fa da solo**: legge ogni `capitolo-NN/capitolo.html` (apertura, lezioni, esercizi, mappa, verifica con i numeri di pagina).
  Nuovo capitolo: crea `capitolo-02/`, esegui `build.js capitolo-02`, poi `libro.js`. Se l'indice non sta più in una pagina, lo script avvisa.

## Capitolo 1 · Le parti del discorso (pagine 7–16)
- `capitolo-01/capitolo.html`: il testo (stile in `stile.css`); `capitolo-01/immagini/`: illustrazioni fatte con deAPI.
- PDF: `NODE_PATH=$(npm root -g) node scuola/chi-fa-cosa/build.js capitolo-01 [cartella-anteprime]` → `capitolo-01/build/chi-fa-cosa-capitolo-01.pdf`
  (lo script avvisa se qualcosa esce dalla pagina o se manca un'immagine).
- Esercizi interattivi: `python3 scuola/chi-fa-cosa/capitolo-01/esercizi.py` → `esercizi.json`.

## Metterlo sul sito
1. Admin → Libri → Nuovo libro: titolo «Chi fa cosa?», **Sezione: Scuola**, carica il PDF.
2. Admin → Esercizi → **Importa**: scegli il libro e carica `capitolo-01/esercizi.json` (codici CFC-1 … CFC-17, CFC-13 solo online).
3. Admin → Audio e video → Nuovo: codice **CFC-16**, titolo «Luce e il gabbiano», pagina 14, file `capitolo-01/media/CFC-16-luce-e-il-gabbiano.mp3` (testo in `.txt`).
4. Nel lettore i codici stampati diventano cliccabili da soli; i QR del PDF portano a `https://vladinc.ru/libri/apri.php?c=CODICE`.

## Capitolo 2 · Il nome (pagine 17–26)
- `capitolo-02/capitolo.html`, immagini in `capitolo-02/immagini/` (apertura «Nomi, cose, città», mercato della Pescheria).
- Esercizi interattivi: `python3 scuola/chi-fa-cosa/capitolo-02/esercizi.py` → `esercizi.json` (CFC-18 … CFC-34; CFC-30 solo online).
- Audio: codice **CFC-33** «Luce alla Pescheria», pagina 24, file `capitolo-02/media/CFC-33-luce-alla-pescheria.mp3`.

## Capitolo 3 · L'articolo (pagine 27–36)
- Esercizi: `capitolo-03/esercizi.py` → `esercizi.json` (CFC-35 … CFC-51; CFC-47 solo online). Audio **CFC-50** «Luce sull'Etna», pagina 34.
- Illustrazioni generate da Vlad con le reference dei personaggi.

## Capitolo 4 · L'aggettivo (pagine 37–46)
- Esercizi: `capitolo-04/esercizi.py` → `esercizi.json` (CFC-52 … CFC-69; CFC-65 solo online). Audio **CFC-68** «Il concorso più simpatico», pagina 44.
- Immagini `apertura.jpg` e `concorso.jpg`: le genera Vlad dai prompt (reference: 1 = Vlad, 2 = Luce).

## Capitolo 5 · Il pronome (pagine 47–56)
- Esercizi: `capitolo-05/esercizi.py` → `esercizi.json` (CFC-70 … CFC-86; CFC-82 solo online). Audio **CFC-85** «Il segreto della nonna», pagina 54.
- Immagini generate da Vlad dai prompt.
