# Funzioni dell'admin

Entra da `/admin/login.php`. Premi `?` in qualsiasi pagina admin per le scorciatoie da tastiera.

## Libri (elenco)
1. Ricerca nel titolo, sottotitolo, autore, tag, genere, ISBN, trama, note e indirizzo
2. Schede rapide per stato: tutti, pubblicati, bozze, programmati, in evidenza, senza PDF, senza copertina, senza trama, cestino (con i conteggi)
3. Filtro per collana (anche «senza collana»)
4. Filtro per tag (con quanti libri hanno ogni tag)
5. Ordinamento cliccando sulle colonne (titolo, collana, numero, stato, letture, download, data)
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
36. Statistiche del libro (letture, download, date)
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

## Lato pubblico (novità)
65. Ricerca nel catalogo, filtro per collana e per tag, paginazione
66. Libri in evidenza, «Nella stessa collana»
67. Contatore di letture e download
68. Pagine statiche modificabili e menu dinamico
69. `sitemap.php` e `feed.php` (RSS)
