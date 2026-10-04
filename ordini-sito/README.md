# Ordini libri · Vlad Editore (PHP + SQLite)

Sito per ordinare i libri, nello stesso stile del sito principale (`sito/`). Va su **https://vladinc.ru/ordini-libri/**.
Serve PHP 8.1+ con `pdo_sqlite`. Nessuna configurazione: il database `data/ordini.sqlite` si crea da solo alla prima visita (con i 5 libri del Custode dell'Etna).

- Pubblico: `index.php` catalogo → `ordina.php?s=…` modulo d'ordine → `grazie.php`
- Admin: `admin/` — la prima volta crea la password; poi **Ordini** (stato, note, CSV), **Libri** (aggiungi/modifica, copertina, prezzo), **Impostazioni** (email di notifica, password)
- Con un'email nelle Impostazioni, a ogni ordine arriva anche una mail (funzione `mail()` del server)
- Protezioni: CSRF, trappola anti-bot, limite 5 ordini/ora per IP, 5 errori di login = pausa 15 min
- Backup: copia `data/ordini.sqlite` e `uploads/`. `data/` e `inc/` sono bloccati da `.htaccess` (su Nginx vanno bloccati a mano).
