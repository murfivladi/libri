# La Gazzetta di Vlad

Settimanale digitale di Vlad Editore (niente stampa). Esce quando un numero è pronto, almeno una volta a settimana. Due PDF per numero, italiano e russo, da caricare sul sito dall'admin.

- `numero-NN/gazzetta-NN-it.html` e `-ru.html`: i testi, impaginati come un giornale (testata, occhiello, titolo, sommario, colonne, didascalie, colophon)
- `numero-NN/immagini.json`: le descrizioni delle illustrazioni
- `stile.css` + `font/` (Playfair Display, Oswald, PT Serif; licenza OFL)
- `python3 gazzetta/immagini.py NN`: genera le illustrazioni in `numero-NN/img/` con deAPI
  (modello `Flux_2_Klein_4B_BF16`, endpoint `https://oai.deapi.ai/v1/images/generations`, chiave nella variabile `DEAPI`)
- `python3 gazzetta/build.py NN`: crea i PDF. Se un'immagine manca, resta un riquadro con la descrizione.
- `crivello.py`: crea la griglia del gioco "cerca le parole"

Regole: notizie vere con la fonte; una sola notizia inventata, con il timbro BUFALA. Mascotte: Briciola il geco. Il russo va riletto da un madrelingua.
