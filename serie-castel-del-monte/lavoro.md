# Come si crea un libro (guida di lavoro)

Guida scritta alla fine della collana «La Porta di Castel del Monte» (8 libri). Serve per creare **nuovi libri o nuove collane** con la stessa grafica e le stesse regole. Tutto quello che c'è qui è stato usato davvero nei libri 1–8.

---

## 1. Cosa si consegna per ogni libro

Quando Vlad scrive «Ora fai libro N e solo copertina», si fa **tutto** questo, in ordine:

1. Piano del libro (`libro-0N-piano.md`, nella cartella della collana).
2. Scrivere il libro in italiano (≈ 14 capitoli, almeno **13.000 parole**).
3. Appendice italiana (`appendice.html`).
4. Generare **solo la copertina** (una prova, circa 5 RUB). Nessuna illustrazione interna.
5. PDF italiano (`python3 build.py`).
6. Tradurre in russo capitoli e appendice, PDF russo (`python3 build.py --ru`).
7. Esportare per il sito: copertine JPG e PDF in `per-il-sito/`.
8. Scrivere `DA-FARE.md` del libro (cosa manca, cosa è inventato da approvare).
9. Commit e push sul branch.
10. Rispondere **in chat** (mai in file) con: riassunto, cose inventate da approvare, **4 link diretti GitHub**, dati per il sito in italiano e in russo, saldo Polza.

### Regole fisse di Vlad (non dimenticarle)
- **Solo la copertina** si genera con l'IA. Niente tavole interne, salvo richiesta esplicita.
- **Prima di spendere** controllare il saldo (`mcp__Polza__get_balance`); **una sola prova**; **chiedere prima di rigenerare**.
- I **dati per il sito** vanno **in chat, non in file**.
- Dare **sempre 4 link diretti**: copertina IT, copertina RU, PDF IT, PDF RU.
- I libri devono essere **lunghi**: Vlad si è lamentato quando si accorciavano. Mirare a 13–15 mila parole, 14–15 capitoli, 70–80 pagine A5.
- La traduzione russa è fatta dall'IA: dire sempre che va **fatta controllare da un madrelingua**.
- **Non creare pull request** se Vlad non la chiede.
- Dire chiaramente cosa è **inventato** (soprattutto le cose grandi) perché Vlad lo approvi.
- Qualità prima della fretta: rileggere, correggere errori di continuità e refusi prima di consegnare.

---

## 2. Struttura delle cartelle

```
/home/user/libri/
├── grafica/                      emblema-700.png (marchio), logo, banner
└── serie-castel-del-monte/
    ├── lavoro.md                 (questo file)
    ├── domande-e-risposte.md     scelte iniziali di Vlad per la collana
    ├── libro-0N-piano.md         piano di ogni libro
    ├── per-il-sito/              copertine e PDF pronti da caricare
    └── libro-0N/
        ├── build.py              costruisce il PDF
        ├── stile.css             grafica (identica per tutti i libri)
        ├── genera.py             genera la copertina con l'IA
        ├── personaggi.json       stili e descrizioni dei personaggi
        ├── capitoli/*.md         capitoli italiani
        ├── capitoli-ru/*.md      capitoli russi
        ├── appendice.html / appendice-ru.html
        ├── immagini/copertina.jpg
        ├── build/                PDF e file temporanei (seg-* ignorati da git)
        └── DA-FARE.md
```

**Per iniziare un libro nuovo** (esempio libro 9):
```bash
cp -r libro-08 libro-09 && cd libro-09
rm -rf capitoli/* capitoli-ru/* immagini/* build appendice*.html DA-FARE.md
```
Poi modificare `build.py`: titolo, titolo a due righe (`titolo2`, con `<br>`), «Libro N», nome dei PDF (italiano e russo), colophon («Libro N della collana»), elenco «Nella stessa collana» in `ultima()`, e la scritta finale `continua` («Continua…» oppure «Fine della storia»). In `personaggi.json` i percorsi dei riferimenti dei libri 2+ puntano a `../libro-01/riferimenti/*.jpg`.

Nomi dei PDF usati:

| Libro | Italiano | Russo |
|---|---|---|
| 1 | la-pietra-al-contrario | kamen-naoborot |
| 2 | la-torre-del-tramonto | bashnya-zakata |
| 3 | la-torre-dei-sogni | bashnya-snov |
| 4 | la-torre-dell-alba | bashnya-rassveta |
| 5 | la-torre-delle-ombre | bashnya-tenei |
| 6 | la-torre-dei-nomi | bashnya-imyon |
| 7 | la-torre-degli-specchi | bashnya-zerkal |
| 8 | la-torre-del-silenzio | bashnya-molchaniya |

---

## 3. Formato dei capitoli (mini-markdown)

Un file per capitolo: `capitoli/NN-titolo-con-trattini.md`. Il russo ha **gli stessi nomi di file** in `capitoli-ru/`.

```
# Titolo del capitolo
@mondo: presente | oblio | duecento
@dove: Luogo, data

Paragrafo. I paragrafi sono separati da una riga vuota.

— I dialoghi iniziano con il trattino lungo.

* * *                      stacco di scena (✦)
> testo                    messaggio scritto a mano (riquadro «quaderno»)
|| testo                    iscrizione (centrata, spaziata)
*corsivo*                  corsivo
[[tavola:cap03|didascalia]]  tavola a tutta pagina (immagini/cap03.jpg; se manca, nessuna pagina vuota)
```

- `@mondo` cambia i colori: **presente** (verde e bronzo), **oblio** (grigio-verde spento, ornamento ◇ ◇ ◇), **duecento** (marrone caldo, ornamento ✦ ✦ ✦).
- Il primo paragrafo di ogni scena ha il **capolettera**.
- Usare apostrofi tipografici ’ nelle stringhe Python dentro f-string (un apostrofo dritto rompe `build.py`).
- Nei file `.md` si possono usare apostrofi normali.

---

## 4. Grafica (identica per tutta la collana)

**Formato**: A5 verticale (148 × 210 mm). Testo Georgia / serif, corpo 11,5 pt, interlinea 1,5, giustificato con sillabazione, rientro di 1,2 em, margini 17 / 15 / 20 mm, numero di pagina in basso al centro (marrone, 9 pt).

**Colori della collana**
| Uso | Colore |
|---|---|
| Verde antico (titoli, ornamenti importanti) | `#24402f` |
| Bronzo (ornamenti, serie, capolettera) | `#8c5a2b` |
| Bronzo chiaro (bordi, quaderno) | `#b07d3a` |
| Testo | `#1c1a17` |
| Fondo frontespizio | `#fbf9f1` |
| Fondo quaderno | `#faf5e8` |
| Oblio (testi spenti) | `#4d5a52` / `#7b857e` |
| Duecento (titoli) | `#5b3a1a` |
| Porta rossa (prima porta nella mappa) | `#9b2f2f` |
| Porte aperte (mappa) | `#2f6b45` |

**Pagine del libro, in ordine**: copertina → frontespizio → colophon → capitoli (ogni capitolo comincia su pagina nuova, con intestazione: nome della collana, «Capitolo N», titolo in corsivo verde, riga del luogo, ornamento) → eventuali tavole → appendice (mappa, personaggi, enigma, note storiche) → ultima pagina «Nella stessa collana».

**Copertina**: immagine verticale 2:3 a tutta pagina; in alto, su sfumatura scura, la scritta «La Porta di Castel del Monte · Libro N», il titolo (28 pt) e «Vlad»; in basso «Vlad Editore». L'immagine **non deve contenere testo** (le lettere generate dall'IA sono illeggibili e vanno evitate o accettate solo se decorative).

**Marchio**: `grafica/emblema-700.png` (frontespizio, colophon, ultima pagina). Casa editrice: **Vlad Editore, Taormina (Sicilia)**. ISBN: «in attesa di assegnazione».

**Appendice** (HTML con le classi di `stile.css`): `section.appendice`, `h2`, `h3`, `p.scheda-personaggio`, `p.iscrizione`, `p.soluzione`, `p.senza-rientro`. La mappa è un SVG ottagonale; coordinate dei cerchi: N (150,55), NE (217.2,82.8), E (245,150), SE (217.2,217.2), S (150,245), SW (82.8,217.2), W (55,150), NW (82.8,82.8). Colori: prima porta rossa, porte già aperte verdi, porta successiva bronzo. Si parte dall'appendice del libro precedente e si cambiano colori e testi. Sezioni: mappa, personaggi nuovi, enigma con aiuto e soluzione, note storiche («Cosa è vero» / «Cosa è inventato»).

**Costruire i PDF**
```bash
cd libro-0N
python3 build.py        # italiano  → build/<nome>.pdf
python3 build.py --ru   # russo
```
Usa Chromium headless (pagine in PDF separati, poi `pdfunite`). Servono `pdfunite`, `pdfseparate`, `pdfinfo`, `pdftoppm`.

**Esportare per il sito** (da `serie-castel-del-monte/`):
```bash
cp libro-0N/build/<it>.pdf libro-0N/build/<ru>.pdf per-il-sito/
pdftoppm -r 200 -f 1 -l 1 -jpeg -jpegopt quality=90 per-il-sito/<it>.pdf per-il-sito/copertina-libroN-it
mv per-il-sito/copertina-libroN-it-*1.jpg per-il-sito/copertina-libroN-it.jpg   # idem per ru
```
(Il libro 1 usa i nomi `copertina-it.jpg` / `copertina-ru.jpg`, senza «libro1».)

**Link diretti** (formato):
`https://github.com/murfivladi/libri/raw/claude/laughing-hamilton-4o6jud/serie-castel-del-monte/per-il-sito/<file>`

---

## 5. Come si genera la copertina

```bash
cd libro-0N
python3 genera.py --mondo presente --out immagini/copertina.jpg \
  --personaggi alessia,sofia \
  --scena "descrizione della scena in inglese, vuota in alto e in basso per il titolo"
```
- Modello: `bytedance/seedream-4.5` tramite Polza (variabile d'ambiente `POLZA`). Costo ≈ 5 RUB per immagine. Può superare i 120 s: lanciare in background (`nohup … &`) e controllare il log.
- Prima: `mcp__Polza__get_balance`. Poi **una sola prova**. Se non piace, **chiedere a Vlad** prima di rigenerare.
- `--mondo` sceglie lo stile da `personaggi.json`: `presente` (pittura digitale fantasy da libro illustrato, luce calda italiana), `oblio` (grigi, verde cenere, bronzo pallido, architetture capovolte, nebbia), `duecento` (miniatura medievale con foglia d'oro, ocra, verde profondo e bronzo).
- I personaggi sono descritti in `personaggi.json`; se hanno una `riferimento` (immagine in `libro-01/riferimenti/`) la passa al modello per mantenere i volti uguali. Personaggi disponibili: alessia, sofia, nonna_nunzia, ruggero, michele_scoto, federico, lucerna, ailessa, prof_greco.
- Per un personaggio nuovo: aggiungerlo a `personaggi.json` con una descrizione in inglese; si può generare la scheda con `--scheda nome` e salvarla in `riferimenti/`.
- Inserire nella scena sempre: «vertical book cover composition, empty space at the top and bottom for the title» e nessun testo.
- Alla fine controllare la copertina nel PDF (`pdftoppm -r 40 …`) prima di consegnare.

---

## 6. Regole di scrittura

**Voce e tono**: fantasy per ragazzi 10–14 anni, tono serio e misterioso ma caldo, mai crudele. Protagonista **Alessia**, 13 anni, curiosa e osservatrice, **narra in prima persona al passato** (i primi libri anche al presente; mantenere il tempo del libro precedente). Frasi chiare, dialoghi vivi, un po' di umorismo (il gatto, il panino, la moka). Emozione vera, senza melodramma gratuito.

**Struttura di un libro** (14–15 capitoli di ~900–1100 parole):
1. Apertura nel presente (casa/nonna/Sofia) che richiama il libro prima.
2. Torre del libro e **regola della porta** (una prova con un senso: svegliare, nominare, guardare, tacere…).
3. Un viaggio nel passato o nell'Oblio con personaggi nuovi e storici.
4. Svolta emotiva a metà libro.
5. Prova finale, costo/scelta.
6. Ritorno, enigma della porta successiva, ganci per il libro dopo.
Ogni libro ha una **data storica** reale (Jesi 1194, Toledo 1217, Benevento 1266, Lucera 1300…) e una parte «inventata» dichiarata.

**Piano**: prima di scrivere, fare `libro-0N-piano.md` con l'elenco capitoli e una sezione «Da approvare (inventato)».

**Lunghezza**: controllare con `wc -w capitoli/*.md`. Se un libro risulta corto, aggiungere un capitolo (come «I visitatori» nel libro 8) anziché ridurre.

**Continuità** (cose da non sbagliare): 
- Il **libro di casa** (tela scura, pergamena in fondo che si legge con la luce della lanterna), il panno blu con la frase *«Guarda sempre da che parte è scritta una cosa»* (la dice la nonna; viene da Bartolomeo 1240).
- La **ninna nanna** di tre note su e due giù.
- La **lanterna** di bronzo con vetri verdi (mostra ciò che è dimenticato) e **Lucerna** la falena.
- L'**arancio** nato dai semi di Costanza (fiorisce nel libro 6, matura nel libro 8).
- La frase del papà: *«Ale. Svegliati. È mattina.»*
- Il quaderno di Sofia è **nero** (non rosso); il libro rosso è quello dei nomi di Lucera.
- Famiglia: Rosa (bisnonna, moglie di Antonio, nel 1943 ha 26 anni) → Nunzia (nonna, nata 1953) → Chiara (mamma) e Marco (papà, insegna storia) → Alessia.
- Porte: 1 NE Pietra al Contrario · 2 NO Tramonto (Federico muore, Castel Fiorentino, 13 dic 1250) · 3 S Sogni (Manfredi, Benevento 1266) · 4 E Alba (Jesi 1194) · 5 O Ombre (Toledo 1217) · 6 SO Nomi (Lucera 1300) · 7 SE Specchi (22 dic) · 8 N Silenzio (mezzanotte).
- Federico Ruggero (il Primo), Rainaldo (il Custos Vacuus), la prima Alessia, Ailessa («Alessia» al contrario).
- Controllare sempre età, anni, nomi e luoghi con i libri precedenti (`grep -rn` nelle cartelle `capitoli/`).

**Evitare**: tavole interne, testi scritti dentro le immagini, anacronismi non voluti, cattiveria gratuita, citazioni lunghe di opere altrui.

---

## 7. Traduzione in russo

- Tradurre **ogni capitolo** con gli stessi nomi di file in `capitoli-ru/`; mantenere intatte le righe `@mondo:` e `@dove:` (il luogo si traduce).
- Nomi usati: Алессия, София, Айлесса, Лучерна (la falena), Лучера (la città), Руджеро, Микеле Скотт, Федерик (l'imperatore), Федерико Руджеро (il Primo), Райнальдо, Констанция, Нунция (бабушка), Кьяра, Марко, профессор Греко, синьор Вито, Тонино, Custos Vacuus (resta in latino), Turris Silentii ecc. (le torri restano in latino nell'appendice, con titolo russo «Башня …»).
- Titoli russi: Камень наоборот, Башня заката, снов, рассвета, теней, имён, зеркал, Молчания. Collana: «Врата Кастель-дель-Монте», motto «Восемь дверей, одна тайна».
- Punteggiatura russa con trattino lungo per i dialoghi; usare «ё».
- Dopo la traduzione cercare residui italiani/latini: `grep -rn "[а-я][A-Za-z]\|[A-Za-z][а-я]" capitoli-ru`.
- Dire a Vlad che serve il controllo di un madrelingua.

---

## 8. Dati per il sito (da dare **in chat**)

Per ogni libro, in italiano e in russo:
- Titolo · Sottotitolo («Libro N · La Porta di Castel del Monte») · Slug · Collana e numero
- Pagine · Anno (2026) · Genere (Fantasy per ragazzi) · Tag (5–6)
- Meta description (≈ 150–160 caratteri) · Trama (3–5 frasi, senza rivelare il finale)
- Per la collana: titolo, sottotitolo, numero di libri, descrizione.

---

## 9. Git

- Branch di lavoro: `claude/laughing-hamilton-4o6jud`.
- Dopo ogni libro: `git add -A . && git commit && git push -q -u origin claude/laughing-hamilton-4o6jud`.
- Messaggio di commit descrittivo (es. «Libro 7: La Torre degli Specchi (IT + RU, copertina, PDF)») che termina con le righe di attribuzione richieste dalla sessione.
- Il push può essere ritentato fino a 4 volte in caso di errori di rete.
- Nessuna PR senza richiesta.

---

## 10. Errori già fatti (per non ripeterli)

- Libri che si accorciavano: Vlad se n'è accorto. Mantenere ≥ 13.000 parole; il libro 4 è stato riscritto per questo. I libri 2 e 3 sono ancora i più corti (circa 70 pagine) e si possono allungare.
- Apostrofo dritto dentro una f-string di `build.py` → `SyntaxError`: usare ’.
- File di capitoli russi vecchi rimasti dopo una riscrittura: cancellare quelli con nomi non più usati.
- Errori di continuità trovati solo alla rilettura (quaderno rosso/nero, chi è la madre di chi, età di Rosa nel 1943, «Palazzo dei Silenzi» inesistente): rileggere con `grep` prima di consegnare.
- Lettere latine mescolate al cirillico: controllare con il `grep` della sezione 7.
- La generazione della copertina può durare più di 120 s: usare `nohup … &`.

---

## 11. Nuove collane

Per una collana nuova (es. «Il Custode dell'Etna», serie separata, vedi `../serie-custode-etna.md`):
1. Fare `domande-e-risposte.md` con Vlad (genere, pubblico, protagonista, luogo, epoca, tono, mondo nascosto, numero di libri) — a gruppi di 4 domande.
2. Copiare la cartella di un libro come modello, scegliere due colori di collana (cambiare `#24402f` e `#8c5a2b` in `stile.css`), tenere carattere, formato A5 e struttura.
3. Definire personaggi in `personaggi.json` e creare le schede di riferimento (`--scheda`) **una volta sola**.
4. Piano della serie, poi piano di ogni libro, con una sezione «Da approvare».
5. Seguire la consegna della sezione 1.

---

## 12. Stato della collana «La Porta di Castel del Monte»

| Libro | Titolo | Pagine IT/RU |
|---|---|---|
| 1 | La Pietra al Contrario | 93 / 97 |
| 2 | La Torre del Tramonto | 71 / 75 |
| 3 | La Torre dei Sogni | 69 / 69 |
| 4 | La Torre dell’Alba | 71 / 74 |
| 5 | La Torre delle Ombre | 75 / 76 |
| 6 | La Torre dei Nomi | 76 / 77 |
| 7 | La Torre degli Specchi | 76 / 78 |
| 8 | La Torre del Silenzio | 78 / 78 |

Collana completa. Lavori possibili: allungare i libri 2 e 3, tavole interne (solo su richiesta), controllo del russo da un madrelingua, caricare tutto sul sito.
