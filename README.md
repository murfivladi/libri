# Vlad Editore

Casa editrice fondata da Vlad, Taormina - Trappitello (Sicilia).
Libri di fantascienza e fantasy, scritti da Vlad con l'aiuto dell'AI.

## Catalogo

| N. | Titolo | Genere | Stato |
|----|--------|--------|-------|
| 1  | Il Custode dell'Etna | fantascienza / fantasy | completo (10 capitoli, PDF in `libro-01/build/`) |
| 2  | Il Custode e il Fantasma del Teatro | fantasy / mistero | completo (10 capitoli, PDF in `libro-02/build/`) |
| 3  | Il Custode e la Lira del Mare | fantasy / mistero | completo (10 capitoli, PDF in `libro-03/build/`) |
| 4  | Il Custode e l'Isola dell'Immortale | fantasy / fiabe russe | completo (10 capitoli, PDF in `libro-04/build/`) |
| 5  | Il Custode e l'Inverno Eterno | fantasy / finale della serie | completo (10 capitoli, PDF in `libro-05/build/`) |

## Come lavoriamo

- `libro-NN/scheda.md`: idea, personaggi, mondo
- `libro-NN/trama.md`: capitoli in breve
- `libro-01/stile.css`: stile condiviso da tutte le pagine di tutti i libri (formato A5)
- `libro-NN/pagine/capNN-pNN.html`: il testo, una pagina per file
- `libro-NN/immagini/`: copertina e illustrazioni (vanno su git)
- `libro-NN/riferimenti/` e `personaggi.json`: schede dei personaggi per avere immagini coerenti
- `libro-01/genera.py --libro libro-NN`: genera le immagini con Polza usando i riferimenti
- `libro-NN/build.py`: crea il PDF in `libro-NN/build/` (anche il PDF va su git)
- `libro-01/frontespizi.py`: crea frontespizio, colophon e pagina «Nella stessa collana» di tutti i libri (poi rifai i PDF)
- `grafica/`: logo, emblema e banner di Vlad Editore (originali ad alta risoluzione)
- `sito/`: il sito web (catalogo, lettore, admin), vedi `sito/README.md`; `.github/workflows/`: test e deploy FTP
- `serie-custode-etna.md`: i segreti della serie (non mostrare prima di leggere i libri)
- Vlad decide idee e scelte; l'AI scrive bozze e propone miglioramenti.
