# Libri personalizzati su ordinazione · Vlad Editore (PHP + SQLite)

Sito nello stesso stile del sito principale (`sito/`), per **https://vladinc.ru/ordini-libri/**.
Niente catalogo e niente pagamento online: il cliente descrive il libro che vuole (per chi, età, genere, storia, lingua, formato PDF/stampato, scadenza) e la richiesta arriva in admin e per email; poi ci si accorda via email.

Serve PHP 8.1+ con `pdo_sqlite`. Il database `data/ordini.sqlite` si crea da solo alla prima visita.

- Pubblico: `index.php` (come funziona + modulo) → `grazie.php`
- Admin: `admin/` — la prima volta crea la password. **Richieste** (stato, note interne, ricerca, CSV), **Impostazioni** (email di notifica, SMTP con mail di prova, password)
- Notifiche via **SMTP** (PHPMailer): server, porta, utente, password in Admin → Impostazioni
- Protezioni: CSRF, trappola anti-bot, max 5 richieste/ora per IP, 5 errori di login = pausa 15 min
- Backup: copia `data/ordini.sqlite`. `data/` e `inc/` sono bloccati da `.htaccess` (su Nginx va fatto a mano).
