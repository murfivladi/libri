# Sito di Vlad Editore (PHP + SQLite)

Catalogo, lettore online (PDF) e area admin. Serve PHP 8+ con `pdo_sqlite` e `fileinfo`.

## Messa online
1. Carica la cartella `sito/` sull'hosting (anche solo i file, senza `uploads/pdf` e `uploads/cover`).
2. Controlla che `data/` e `uploads/` siano scrivibili dal server.
3. Apri `https://tuodominio/admin/login.php`: la prima volta crea la password admin (min. 10 caratteri). Fallo subito.
4. Da "Nuovo libro" carica titolo, trama, copertina e PDF, poi spunta "Pubblicato".
   - Se il PDF non si carica, alza `upload_max_filesize` e `post_max_size` in PHP.
5. Per importare i 5 libri già pronti: `php sito/importa.php` (in locale), poi carica le cartelle `data/` e `uploads/`.

## Struttura
- `index.php` catalogo, `libro.php` scheda, `leggi.php` lettore (PDF.js da cdnjs)
- `admin/` login, elenco, modifica libro
- `inc/` e `data/` sono protetti da `.htaccess` (Apache). Su Nginx va bloccato l'accesso a mano.
- Il database è `data/sito.sqlite`: fai un backup ogni tanto.

## Deploy automatico
Ogni push sul branch `claude/laughing-hamilton-4o6jud` che cambia `sito/` avvia `.github/workflows/deploy-sito.yml`, che carica il sito via FTP.
Secrets richiesti: `FTP_HOST`, `FTP_PORT`, `FTP_USER`, `FTP_PASS`. Variabili opzionali: `FTP_PROTOCOL` (`ftps` di default, oppure `ftp`) e `FTP_DIR` (cartella di destinazione).
