# Sito di Vlad Editore (PHP + MySQL o SQLite)

Catalogo, lettore online (PDF) e area admin. Serve PHP 8.0+ con `pdo_sqlite` (o `pdo_mysql`) e `fileinfo`; consigliate `gd` (miniature) e `curl` (download da URL e collegamento tra i due siti).
L'elenco di tutte le funzioni dell'admin è in [`ADMIN.md`](ADMIN.md).

## Due indirizzi, stessi dati
Il sito può girare **due volte** con lo stesso codice e gli stessi dati:

| | Sito principale | Sito secondario |
|---|---|---|
| Indirizzo | `https://vladinc.ru/libri` | `https://libri.vladinc.ru` |
| Cartella FTP | `./vladinc.ru/www/libri` | `./libri.vladinc.ru/www` |
| Ruolo | ospita i file (`uploads/`) | usa i file del principale |
| Database | lo stesso MySQL (sul tuo VPS) | lo stesso MySQL |
| Admin | sì | sì (i file caricati finiscono sul principale) |

- Nel database copertine, PDF, banner e logo sono **indirizzi interi** (`https://vladinc.ru/libri/uploads/pdf/abc.pdf`), così entrambi i siti li mostrano.
- L'indirizzo **ufficiale** (`vladinc.ru/libri`) compare in `canonical`, anteprime sui social, sitemap e feed: Google considera l'altro una copia.
- Le miniature delle copertine le crea il principale (`img.php`); il PDF si legge da `pdf.php` del principale (permesso CORS e lettura a pezzi garantiti anche senza `.htaccess`).
- Dal secondario i file arrivano al principale **scrivendo direttamente** nella sua cartella `uploads` (se il server lo permette: la procedura guidata la suggerisce) oppure con l'**API** `api.php`, firmata con una chiave condivisa nel database.

### Messa in funzione (una volta)
1. **VPS**: crea il database e un utente che possa collegarsi dall'IP dell'hosting (`CREATE USER 'libri'@'IP_HOSTING' IDENTIFIED BY '…'; GRANT ALL ON nomedb.* TO 'libri'@'IP_HOSTING';`), apri la porta 3306 solo a quell'IP e attiva il TLS di MySQL (`ssl-cert`, `ssl-key`).
   Se il DNS dei due siti dà IP diversi (`dig +short vladinc.ru libri.vladinc.ru`), autorizza **tutti e due** gli IP.
2. **Deploy**: il push sul branch carica il codice nelle due cartelle (vedi sotto).
3. **Sito principale**: apri `https://vladinc.ru/libri/admin/login.php` → «collega il database MySQL condiviso» → inserisci server, database, utente, password; lascia «connessione cifrata»; scegli **sito principale**; indirizzo dei file `https://vladinc.ru/libri`. Premi *Prova la connessione* e poi *Salva e collega*. Poi crea la password admin e **salva il codice di recupero**.
4. **Sito secondario**: `https://libri.vladinc.ru/admin/login.php` → stessa procedura con gli **stessi dati del database**, ruolo **secondario**, indirizzo dei file `https://vladinc.ru/libri`. Accedi con la password creata sul principale. In *Admin → Database e file* premi *Prova il salvataggio dei file*.
5. Carica i libri dall'admin (da uno qualsiasi dei due siti).

`data/config.php` (dati del database e ruolo) si crea dalla procedura guidata: non va su GitHub e il deploy non lo tocca. Se un giorno non riesci più a entrare, **cancellalo via FTP**: il sito torna a SQLite.
Senza `data/config.php` il sito usa SQLite (un solo sito, tutto in locale: va bene per provare).

## Struttura
- `index.php` catalogo, `libro.php` scheda, `leggi.php` lettore (PDF.js incluso in `assets/vendor/`), `pagina.php` pagine, `sitemap.php`, `feed.php`
- `img.php` miniature, `pdf.php` PDF per il lettore, `api.php` API tra i siti (solo sul principale)
- `admin/` area riservata; `inc/` funzioni (`db.php` = database e schema, `file.php` = file); `assets/` grafica e script; `tests/` test automatici
- `inc/` e `data/` sono protetti da `.htaccess` (Apache). Su Nginx va bloccato l'accesso a mano.
- Backup: Admin → Strumenti (SQLite: copia del file; MySQL: file JSON con tutte le tabelle). I file stanno in `uploads/` del principale: copiali via FTP.

## Messa online di un sito singolo (senza MySQL)
1. Carica la cartella `sito/` (o lascia fare al deploy). Controlla che `data/` e `uploads/` siano scrivibili.
2. Apri `/admin/login.php`: crea la password (min. 10 caratteri) e **salva il codice di recupero**. In Admin → Sicurezza puoi attivare la verifica in due passaggi.
3. Da «Nuovo libro» carica titolo, trama, copertina e PDF, poi spunta «Pubblicato». Se il PDF non si carica, alza `upload_max_filesize` e `post_max_size` in PHP (sul secondario valgono anche per il passaggio al principale).
4. `php sito/importa.php` (in locale, sul sito che ha i file) importa i 5 libri già pronti.

## Se perdi la password
1. Dalla pagina di accesso: «Password dimenticata?» e il codice di recupero.
2. Senza codice: crea via FTP nella cartella `data/` un file `reset-password.txt` con dentro la nuova password (almeno 10 caratteri), poi apri la pagina di accesso. Per spegnere il 2FA: file `reset-2fa.txt` (anche vuoto).

## Test
```
php sito/tests/unit.php                       # funzioni, TOTP (vettori ufficiali), indirizzi dei file, schema per i due database
bash sito/tests/e2e.sh                        # admin e sito pubblico con curl (SQLite)
E2E_DB=mysql E2E_MYSQL_USER=… E2E_MYSQL_PASS=… bash sito/tests/e2e.sh     # lo stesso su MySQL/MariaDB
E2E_MYSQL_USER=… E2E_MYSQL_PASS=… bash sito/tests/duesiti.sh              # due siti con un solo database, API e scrittura diretta
bash sito/tests/browser.sh                    # lettore, trascinamento con mouse e dita, telefono (Node + Playwright)
CON_BROWSER=1 … bash sito/tests/duesiti.sh    # in più: il secondario apre PDF e copertine del principale nel browser
```
Su GitHub li esegue `.github/workflows/test-sito.yml` (PHP 8.3 e 8.0, MySQL 8 come servizio); il deploy parte solo se unit, end-to-end e due siti passano.

## Deploy automatico
Ogni push sul branch `claude/laughing-hamilton-4o6jud` che cambia `sito/` avvia `.github/workflows/deploy-sito.yml`: prima i test, poi il caricamento via FTP **nelle due cartelle** con le stesse credenziali.
Secrets: `FTP_HOST`, `FTP_PORT`, `FTP_USER`, `FTP_PASS`. Variabili opzionali: `FTP_PROTOCOL` (`ftps` di default, oppure `ftp`), `FTP_DIR_PRINCIPALE` (default `./vladinc.ru/www/libri/`) e `FTP_DIR_SECONDARIO` (default `./libri.vladinc.ru/www/`).
Il deploy non tocca mai `data/config.php`, i database SQLite e la cartella `uploads/`.
`.htaccess` usa `ErrorDocument 404 /404.php`, che funziona se il sito sta nella radice del dominio o sottodominio (per vladinc.ru/libri Apache mostra la sua pagina 404 per gli indirizzi non-PHP).
