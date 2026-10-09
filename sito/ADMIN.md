# Funzioni dell'admin

Entra da `/admin/login.php`. Premi `?` in qualsiasi pagina admin per le scorciatoie da tastiera.

## Libri (elenco)
1. Ricerca nel titolo, sottotitolo, autore, tag, genere, ISBN, trama, note e indirizzo
2. Schede rapide per stato: tutti, pubblicati, bozze, programmati, in evidenza, senza PDF, senza copertina, senza trama, cestino (con i conteggi)
3. Filtro per collana (anche «senza collana»)
4. Filtro per tag (con quanti libri hanno ogni tag)
5. Ordinamento cliccando sulle colonne (titolo, collana, numero, stato, letture, data)
6. Ordine manuale con le frecce ↑ ↓
7. Paginazione e scelta di quanti libri per pagina (10/25/50/100)
8. Miniatura della copertina e avvisi (⚠ no PDF, ⚠ no copertina)
9. Pubblica / nascondi con un clic
10. Evidenza ⭐ con un clic
11. Duplica un libro (copia anche i file)
12. Cestino con ripristino
13. Eliminazione definitiva (con conferma, cancella anche i file non più usati)

## Azioni di massa (su più libri selezionati)
14. Pubblica
15. Nascondi
16. Programma la pubblicazione a una data
17. Togli la programmazione
18. Metti / togli in evidenza
19. Sposta in una collana (anche creandone una nuova al volo)
20. Aggiungi tag
21. Togli tag
22. Imposta genere
23. Imposta autore
24. Imposta lingua
25. Duplica
26. Rigenera il link di anteprima
27. Azzera i contatori
28. Esporta i selezionati in CSV
29. Sposta nel cestino / ripristina / elimina per sempre

## Scheda del libro
30. Campi: titolo, sottotitolo, autore, collana, numero, genere, lingua, anno, pagine, ISBN, tag, trama, descrizione SEO, note private
31. Copertina e PDF da file **o da URL**, con anteprima; opzione per toglierli
32. Calcolo automatico delle pagine dal PDF
33. Pubblicazione programmata (data e ora)
34. Indirizzo (slug) modificabile
35. Link segreto di anteprima per vedere le bozze (con «Copia link» e rigenerazione)
36. Statistiche del libro (letture, date)
37. Salva / salva e torna all'elenco / salva e aggiungi un altro
38. Avviso se esci con modifiche non salvate
39. Contatore caratteri della descrizione SEO

## Collane
40. Crea, modifica, elimina collane (nome, indirizzo, descrizione, immagine, ordine)
41. Mostra / nascondi una collana nel sito
42. Riordina le collane con le frecce
43. Pubblica o nascondi tutti i libri di una collana in un colpo solo
44. Numera automaticamente i libri della collana (1…n)

## Pagine del sito
45. Crea e modifica pagine (Chi siamo, Contatti…) con formattazione semplice (**grassetto**, *corsivo*, titoli, link)
46. Scegli quali stanno nel menu in alto e in che ordine; mostra / nascondi

## Impostazioni
47. Nome del sito, titolo e frase della home, testo in fondo, email di contatto, autore predefinito
48. Libri per pagina, ordine del catalogo, libri in evidenza in cima, contatori visibili ai visitatori
49. Modalità manutenzione (il sito mostra un messaggio, tu lo vedi comunque)

## Strumenti
50. Backup del database (download)
51. Esporta il catalogo in CSV e in JSON
52. Importa libri da CSV (crea o aggiorna)
53. Controllo dei file mancanti
54. Stato del server (PHP, estensioni, limiti di upload, HTTPS, spazio)
55. Svuota cestino, ottimizza database, azzera contatori, rigenera link di anteprima
56. Calcola le pagine di tutti i PDF

## File, attività, sicurezza
57. Elenco dei file caricati con dimensioni e a chi appartengono
58. Trova ed elimina i file orfani (uno per uno o tutti)
59. Registro delle attività (con filtro e svuotamento)
60. Dashboard con numeri, avvisi («3 libri senza trama»), più letti, ultimi modificati
61. Cambio password
62. Blocco dopo 5 accessi sbagliati (15 minuti), protezione CSRF, sessione che scade dopo 8 ore
63. Tema chiaro/scuro e menu adatto al telefono
64. Scorciatoie da tastiera (`/` cerca, `n` nuovo libro, `g` poi `l`/`c`/`d`/`p`/`s`)

## Sicurezza e aspetto (aggiunte)
70. **Recupero password**: un codice di recupero mostrato una volta sola («Password dimenticata?» nella pagina di accesso)
71. **Verifica in due passaggi (2FA)** con app di autenticazione e QR code, più 8 codici di backup monouso
72. Recupero d'emergenza via FTP: file `data/reset-password.txt` o `data/reset-2fa.txt`
73. **Aspetto**: cambia emblema, logo e banner (da file o URL) e i due colori del sito, con anteprima; ripristino dell'originale
74. **Riordino trascinando** (con mouse o con il dito) per libri, collane e pagine

## Due siti, un database (aggiunte)
75. **Database e file** (procedura guidata): collega il sito a un MySQL condiviso (anche su un VPS), con connessione cifrata TLS, prova della connessione e messaggi che spiegano cosa controllare
76. Ruolo del sito: **principale** (ospita i file) o **secondario** (usa quelli del principale), indirizzo dei file e indirizzo ufficiale
77. Copertine, PDF, banner e logo salvati come **indirizzi interi** nel database
78. Caricamento dal secondario verso il principale: scrittura diretta nella cartella `uploads` o **API firmata**; prova del salvataggio dei file
79. Ritorno a SQLite con un clic (o cancellando `data/config.php` via FTP)
80. Strumenti e File adattati: backup JSON per MySQL, controllo dei file sul principale, stato del database

## Lettori, recensioni, email, privacy (aggiunte)
81. **Email** (SMTP): server, porta, cifratura, utente e password (mai mostrata né nei backup JSON), mittente, **prova di invio**
82. **Privacy e registrazione**: titolare del trattamento (maggiorenne), apri/chiudi le registrazioni (si aprono solo con titolare ed email a posto), testo dell'informativa modificabile con segnaposto, **nuova versione** che fa riaccettare a tutti
83. **Lettori**: elenco con ricerca e filtro per stato; conferma a mano, sospendi/riattiva, manda il reset della password, elimina con tutti i dati, pulizia dei non confermati
84. **Recensioni**: moderazione (in attesa, pubblicate, rifiutate), approva/rifiuta/elimina anche in massa; interruttore «solo dopo la mia approvazione»
85. Dashboard con numero di lettori e recensioni da approvare; avviso con il numero nel menu
86. **Collane pubbliche** (`collane.php`, `collana.php`) con menu «Collane» e sitemap

87. **PDF in russo (facoltativo)**: nella scheda del libro un secondo PDF, da file o da URL; si può avere solo l'italiano, solo il russo o entrambi. Il sito mostra due pulsanti («Leggi online · Italiano» / «Читать онлайн · Русский») e nel lettore un interruttore IT/RU. Pagina e segnalibri del russo restano sul dispositivo del lettore (la numerazione delle pagine può essere diversa)

88. **Sito in italiano e russo**: in ogni scheda (libro, collana, pagina), nelle Impostazioni e nella Privacy c'è il campo «in russo»; se è vuoto il sito mostra l'italiano. Il sito pubblico ha l'interruttore IT | RU; l'admin resta in italiano

## Lato pubblico (novità)
- Miniature delle copertine (il catalogo carica immagini leggere), anteprime ricche quando condividi un link (Open Graph), pagina Privacy, `robots.txt`, pagina 404 con la grafica del sito, nessun servizio esterno (PDF.js è incluso)
- **Lettore a libro**: le pagine si girano davvero (con ombra e piega), a doppia pagina su schermi larghi e a pagina singola sul telefono; copertina e retro rigidi
  - gira con le frecce, trascinando un angolo, con una strisciata veloce (telefono), con la tastiera o la rotella
  - **zoom che resta sul punto che tocchi**: pizzico, doppio tocco/doppio clic, Ctrl+rotella, pulsanti ＋/−; da ingrandito si sposta trascinando e la pagina non si gira per sbaglio
  - indice, segnalibri, ricerca nel testo con evidenziazione, vai a pagina, schermo intero, tema scuro, riprende dall'ultima pagina
65. Ricerca nel catalogo, filtro per collana e per tag, paginazione
66. Libri in evidenza, «Nella stessa collana»
67. Contatore di letture. **I PDF non si possono scaricare**: si leggono solo online (nessun pulsante, `scarica.php` risponde 410, accesso diretto al file bloccato)
68. Pagine statiche modificabili e menu dinamico
69. `sitemap.php` e `feed.php` (RSS)

## Scuola (Vlad Editore Scuola)
- **Admin → Scuola**: libri scolastici con il numero di esercizi, contenuti e zone; chiave deAPI (non esce nei backup).
- **Sezione del libro**: nella scheda del libro, «🎒 Scuola» lo toglie dal catalogo dei romanzi e lo mette in `scuola.php`.
- **Esercizi** (editor visivo, con anteprima accanto): crocette, vero/falso, completa (con banca di parole), tocca le parole (soggetto, predicato, complementi… con i colori di scuola), riordina la frase, trascina nei gruppi, risposta libera. Codice automatico (es. `CFC-12`), pagina, livello, «solo online», duplica, QR da scaricare.
- **Audio, video e immagini**: carica un file, registra dal microfono (anche dal telefono) oppure fai leggere un testo alla voce AI di deAPI.
- **Zone**: sulle pagine del PDF i codici stampati vengono riconosciuti da soli (riquadri blu tratteggiati); si possono disegnare zone in più trascinando sulla pagina.
