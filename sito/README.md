# Sito di Vlad Editore (PHP + SQLite)

Catalogo, lettore online (PDF) e area admin. Serve PHP 8.0+ con `pdo_sqlite` e `fileinfo` (consigliate anche `gd` per le miniature e `curl` per il download da URL).
L'elenco di tutte le funzioni dell'admin è in [`ADMIN.md`](ADMIN.md).

## Messa online
1. Carica la cartella `sito/` sull'hosting (anche solo i file, senza `uploads/pdf` e `uploads/cover`). Con il deploy automatico (sotto) lo fa GitHub.
2. Controlla che `data/` e `uploads/` siano scrivibili dal server.
3. Apri `https://tuodominio/admin/login.php`: la prima volta crea la password admin (min. 10 caratteri). Fallo subito, e **salva il codice di recupero** che compare dopo.
4. In **Admin → Sicurezza** puoi attivare la verifica in due passaggi.
5. Da "Nuovo libro" carica titolo, trama, copertina e PDF, poi spunta "Pubblicato".
   - Se il PDF non si carica, alza `upload_max_filesize` e `post_max_size` in PHP.
6. Per importare i 5 libri già pronti: `php sito/importa.php` (in locale), poi carica le cartelle `data/` e `uploads/`.

## Struttura
- `index.php` catalogo, `libro.php` scheda, `leggi.php` lettore (PDF.js incluso in `assets/vendor/`), `pagina.php` pagine, `sitemap.php`, `feed.php`
- `admin/` area riservata; `inc/` funzioni; `assets/` grafica e script; `tests/` test automatici
- `inc/` e `data/` sono protetti da `.htaccess` (Apache). Su Nginx va bloccato l'accesso a mano.
- Il database è `data/sito.sqlite`: scaricane un backup da Admin → Strumenti.

## Se perdi la password
1. Dalla pagina di accesso: «Password dimenticata?» e il codice di recupero.
2. Senza codice: crea via FTP nella cartella `data/` un file `reset-password.txt` con dentro la nuova password (almeno 10 caratteri), poi apri la pagina di accesso. Per spegnere il 2FA: file `reset-2fa.txt` (anche vuoto).

## Test
```
php sito/tests/unit.php          # funzioni (inclusi i vettori ufficiali del TOTP)
bash sito/tests/e2e.sh           # admin e sito pubblico con curl (usa una copia temporanea)
bash sito/tests/browser.sh       # lettore, trascinamento con mouse e dita, telefono (serve Node + Playwright)
```
Su GitHub li esegue `.github/workflows/test-sito.yml`; il deploy parte solo se unit ed end-to-end passano.

## Deploy automatico
Ogni push sul branch `claude/laughing-hamilton-4o6jud` che cambia `sito/` avvia `.github/workflows/deploy-sito.yml`: prima i test, poi il caricamento via FTP.
Secrets richiesti: `FTP_HOST`, `FTP_PORT`, `FTP_USER`, `FTP_PASS`. Variabili opzionali: `FTP_PROTOCOL` (`ftps` di default, oppure `ftp`) e `FTP_DIR` (cartella di destinazione).

Esempio per un sottodominio (es. libri.vladinc.ru): imposta `FTP_DIR` con la cartella radice del sottodominio sull'hosting.
`.htaccess` usa `ErrorDocument 404 /404.php`, che funziona se il sito sta nella radice del dominio o sottodominio.
