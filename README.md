# Vlad Editore

Casa editrice fondata da Vlad, Taormina - Trappitello (Sicilia).
Libri di fantascienza e fantasy, scritti da Vlad con l'aiuto dell'AI.

## Catalogo

| N. | Titolo | Genere | Stato |
|----|--------|--------|-------|
| 1  | Il Custode dell'Etna | fantascienza / fantasy | completo (10 capitoli, PDF in `libro-01/build/`) |
| 2  | Il Custode e il Fantasma del Teatro | fantasy / mistero | completo (10 capitoli, PDF in `libro-02/build/`) |
| 3  | Il Custode e la Lira del Mare | fantasy / mistero | completo (10 capitoli, PDF in `libro-03/build/`) |
| 4  | da decidere | fantasy | da scrivere ("l'uovo si apre") |

## Come lavoriamo

- `libro-NN/scheda.md`: idea, personaggi, mondo
- `libro-NN/trama.md`: capitoli in breve
- `libro-01/stile.css`: stile condiviso da tutte le pagine di tutti i libri (formato A5)
- `libro-NN/pagine/capNN-pNN.html`: il testo, una pagina per file
- `libro-NN/immagini/`: copertina e illustrazioni (vanno su git)
- `libro-NN/riferimenti/` e `personaggi.json`: schede dei personaggi per avere immagini coerenti
- `libro-01/genera.py --libro libro-NN`: genera le immagini con Polza usando i riferimenti
- `libro-NN/build.py`: crea il PDF in `libro-NN/build/` (anche il PDF va su git)
- `serie-custode-etna.md`: i segreti della serie (non mostrare prima di leggere i libri)
- Vlad decide idee e scelte; l'AI scrive bozze e propone miglioramenti.
