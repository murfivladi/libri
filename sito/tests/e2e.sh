#!/bin/bash
# Test end-to-end: avvia il sito su una copia temporanea e prova admin e pagine pubbliche con curl.
# Uso (dalla radice del repository):  bash sito/tests/e2e.sh
set -u
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
T="$(mktemp -d)"
S="$T/sito"
cp -r "$REPO/sito" "$S"
rm -rf "$S/data/"*.sqlite "$S/uploads/pdf/"*.pdf "$S/uploads/cover/"* "$S/uploads/thumb" "$S/tests"
PORT=${PORT:-8099}
B="http://127.0.0.1:$PORT"
J="$T/cookie"; touch "$J"
LOG="$T/server.log"
ok=0; ko=0

cd "$S" || exit 1
# E2E_DB=mysql: usa un database MySQL/MariaDB vero (credenziali in E2E_MYSQL_*, vedi tests/mysql_db.php)
DBNAME=""
if [ "${E2E_DB:-sqlite}" = "mysql" ]; then
  DBNAME="libri_e2e_$$"
  export LIBRI_CONFIG="$T/config.php"
  php "$REPO/sito/tests/mysql_db.php" crea "$LIBRI_CONFIG" "$DBNAME" || { echo "MySQL non raggiungibile"; exit 1; }
fi
LIBRI_REPO="$REPO" php importa.php >/dev/null || { echo "importa.php non riuscito"; exit 1; }
php -S 127.0.0.1:$PORT >"$LOG" 2>&1 &
PID=$!
SINK=""
trap 'kill $PID $SINK 2>/dev/null; [ -n "$DBNAME" ] && php "$REPO/sito/tests/mysql_db.php" elimina "$DBNAME"; rm -rf "$T"' EXIT
for i in $(seq 1 30); do curl -s -o /dev/null "$B/robots.txt" && break; sleep 0.2; done

chk()  { if [ "$2" = "$3" ]; then ok=$((ok+1)); else ko=$((ko+1)); echo "FAIL: $1 (atteso '$3', ottenuto '$2')"; fi; }
tok()  { curl -s -b "$J" -c "$J" "$B/$1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | cut -d'"' -f4; }
post() { local p=$1; shift; curl -s -b "$J" -c "$J" -o /dev/null -w "%{http_code}" "$@" "$B/$p"; }
code() { curl -s -b "$J" -c "$J" -o "$T/out.html" -w "%{http_code}" "$B/$1"; }
conta() { grep -o "$1" | wc -l | tr -d ' '; }              # quante volte compare un testo (anche sulla stessa riga)
sql()  { php -r 'require "inc/bootstrap.php"; $r = db()->query($argv[1])->fetchColumn(); echo $r;' "$1"; }
totp() { php -r 'require "inc/sicurezza.php"; echo totp_codice(db()->query("SELECT totp_segreto FROM admin")->fetchColumn());'; }
nuova_sessione() { : > "$J"; }

# ---------- primo accesso: password + codice di recupero ----------
T1=$(tok admin/login.php)
chk "prima password: reindirizza a Sicurezza" "$(post admin/login.php -d "csrf=$T1&password=provaprova12&password2=provaprova12")" 302
curl -s -b "$J" -c "$J" "$B/admin/sicurezza.php" > "$T/sic.html"
RECUPERO=$(grep -o '[A-Z2-7]\{5\}-[A-Z2-7]\{5\}-[A-Z2-7]\{5\}-[A-Z2-7]\{5\}' "$T/sic.html" | head -1)
chk "codice di recupero mostrato" "$([ -n "$RECUPERO" ] && echo si)" si

# ---------- tutte le pagine admin rispondono senza errori PHP ----------
for pg in index.php libri.php "libri.php?stato=bozze&q=custode&ord=titolo&dir=desc" libro.php "libro.php?id=1" collane.php "collane.php?nuova=1" pagine.php "pagine.php?id=1" media.php impostazioni.php aspetto.php strumenti.php log.php password.php sicurezza.php; do
  chk "admin/$pg" "$(code "admin/$pg")" 200
  if grep -qE "Warning|Notice|Fatal error|Deprecated|Parse error" "$T/out.html"; then ko=$((ko+1)); echo "FAIL: errori PHP in admin/$pg"; fi
done

# ---------- libri: azioni di massa ----------
Tk=$(tok admin/libri.php)
chk "pubblica 1,2,3" "$(post admin/libri.php -d "csrf=$Tk&azione=pubblica&ids[]=1&ids[]=2&ids[]=3")" 302
chk "catalogo: 3 libri" "$(curl -s $B/index.php | conta 'class="scheda"')" 3
Tk=$(tok admin/libri.php); post admin/libri.php -d "csrf=$Tk&azione=aggiungi_tag&tag=fantasy,Etna&ids[]=1&ids[]=2" >/dev/null
chk "filtro per tag" "$(curl -s -b "$J" "$B/admin/libri.php?tag=Etna" | conta 'name="ids\[\]"')" 2
Tk=$(tok admin/libri.php); post admin/libri.php -d "csrf=$Tk&azione=evidenza_si&ids[]=1" >/dev/null
chk "evidenza in home" "$(curl -s $B/index.php | conta 'In evidenza')" 1
Tk=$(tok admin/libri.php); post admin/libri.php -d "csrf=$Tk&azione=sposta_collana&nuova_collana=Serie+Prova&collana_id=0&ids[]=4&ids[]=5" >/dev/null
chk "nuova collana creata" "$(sql "SELECT COUNT(*) FROM collane WHERE nome = 'Serie Prova'")" 1
Tk=$(tok admin/libri.php); post admin/libri.php -d "csrf=$Tk&azione=programma&data=2099-01-01T10:00&ids[]=4" >/dev/null
chk "programmato: pagina pubblica 404" "$(code libro.php?s=il-custode-e-l-isola-dell-immortale)" 404
Tk=$(tok admin/libri.php); post admin/libri.php -d "csrf=$Tk&azione=duplica&ids[]=1" >/dev/null
chk "duplica" "$(sql "SELECT COUNT(*) FROM libri")" 6
NUOVO=$(sql "SELECT MAX(id) FROM libri")
Tk=$(tok admin/libri.php); post admin/libri.php -d "csrf=$Tk&riga=$NUOVO:cestino" >/dev/null
chk "cestino" "$(sql "SELECT COUNT(*) FROM libri WHERE eliminato IS NOT NULL")" 1
Tk=$(tok admin/libri.php); post admin/libri.php -d "csrf=$Tk&riga=$NUOVO:elimina_def" >/dev/null
chk "eliminazione definitiva" "$(sql "SELECT COUNT(*) FROM libri")" 5
Tk=$(tok admin/libri.php)
chk "csv dei selezionati" "$(curl -s -b "$J" -d "csrf=$Tk&azione=csv&ids[]=1&ids[]=2" $B/admin/libri.php | conta 'slug;titolo')" 1
chk "CSRF mancante rifiutato" "$(post admin/libri.php -d "azione=pubblica&ids[]=1")" 400

# ---------- riordino trascinando ----------
curl -s -b "$J" "$B/admin/libri.php" | grep -q 'data-ordina="libri"' && ok=$((ok+1)) || { ko=$((ko+1)); echo "FAIL: maniglie di trascinamento assenti"; }
Tk=$(tok admin/libri.php)
chk "salva_ordine libri: 204" "$(post admin/libri.php -d "csrf=$Tk&azione=salva_ordine&ids[]=5&ids[]=4&ids[]=3&ids[]=2&ids[]=1")" 204
chk "ordine libri salvato" "$(sql "SELECT group_concat(id ORDER BY ordine) FROM libri")" "5,4,3,2,1"
Tk=$(tok admin/collane.php)
chk "salva_ordine collane: 204" "$(post admin/collane.php -d "csrf=$Tk&azione=salva_ordine&ids[]=2&ids[]=1")" 204
chk "ordine collane salvato" "$(sql "SELECT group_concat(id ORDER BY ordine) FROM collane")" "2,1"
Tk=$(tok admin/pagine.php)
chk "salva_ordine pagine: 204" "$(post admin/pagine.php -d "csrf=$Tk&azione=salva_ordine&ids[]=2&ids[]=1")" 204
chk "salva_ordine senza CSRF" "$(post admin/pagine.php -d "azione=salva_ordine&ids[]=1&ids[]=2")" 400

# ---------- scheda libro, anteprima segreta ----------
Tk=$(tok "admin/libro.php?id=1")
chk "salva libro" "$(post "admin/libro.php?id=1" -F csrf=$Tk -F titolo='Il Custode dell Etna' -F sottotitolo=Prova -F autore=Vlad -F collana_id=1 -F numero=1 -F genere=Fantasy -F lingua=it -F anno=2026 -F pagine_n=0 -F tags='a, b' -F trama='Una trama' -F stato=pubblicato -F evidenza=1 -F dopo=resta)" 302
chk "sottotitolo visibile" "$(curl -s "$B/libro.php?s=il-custode-dell-etna" | conta 'Prova')" 1
Tk=$(tok admin/libro.php); post admin/libro.php -F csrf=$Tk -F titolo='Libro Segreto' -F stato=bozza -F dopo=elenco >/dev/null
chk "bozza non pubblica" "$(code libro.php?s=libro-segreto)" 404
TK=$(sql "SELECT token FROM libri WHERE slug = 'libro-segreto'")
chk "anteprima con token" "$(code "libro.php?s=libro-segreto&anteprima=$TK")" 200
chk "anteprima con token sbagliato" "$(code "libro.php?s=libro-segreto&anteprima=sbagliato")" 404

# ---------- sito pubblico: miniature, Open Graph, lettore, contatori ----------
HOME=$(curl -s "$B/")
chk "miniature nel catalogo (3 libri + 1 in evidenza)" "$(echo "$HOME" | conta 'src="uploads/thumb/400-')" 4
chk "miniatura creata sul disco" "$([ -n "$(ls uploads/thumb 2>/dev/null)" ] && echo si)" si
chk "Open Graph: immagine" "$(curl -s "$B/libro.php?s=il-custode-dell-etna" | conta 'og:image" content="http://127.0.0.1:'$PORT'/uploads/thumb/800-')" 1
chk "Open Graph: tipo book" "$(curl -s "$B/libro.php?s=il-custode-dell-etna" | conta 'og:type" content="book"')" 1
chk "link canonico" "$(curl -s "$B/libro.php?s=il-custode-dell-etna" | conta 'rel="canonical"')" 1
chk "pagina Privacy" "$(code 'privacy.php')" 200
chk "vecchio indirizzo privacy: 301" "$(curl -s -o /dev/null -w '%{http_code}' "$B/pagina.php?s=privacy")" 301
chk "link Privacy nel piede" "$(echo "$HOME" | conta 'privacy.php')" 1
chk "robots.txt" "$(curl -s "$B/robots.txt" | conta 'Disallow: /admin/')" 1
chk "404 con grafica" "$(curl -s "$B/404.php" | conta 'Pagina non trovata')" 1
chk "lettore: usa PDF.js locale" "$(curl -s "$B/leggi.php?s=il-custode-dell-etna" | conta 'assets/vendor/pdf.min.js')" 1
chk "lettore: libro con le pagine che si girano (StPageFlip locale)" "$(curl -s "$B/leggi.php?s=il-custode-dell-etna" | conta 'assets/vendor/page-flip.browser.js')" 1
chk "StPageFlip servito" "$(code assets/vendor/page-flip.browser.js)" 200
chk "lettore: niente CDN" "$(curl -s "$B/leggi.php?s=il-custode-dell-etna" | conta 'cdnjs')" 0
chk "PDF.js servito" "$(code assets/vendor/pdf.worker.min.js)" 200
chk "scarica.php: il download non esiste (410)" "$(curl -s -o /dev/null -w '%{http_code}' "$B/scarica.php?s=il-custode-dell-etna")" 410
chk "libro: nessun pulsante Scarica" "$(curl -s "$B/libro.php?s=il-custode-dell-etna" | conta '[Ss]carica')" 0
chk "pdf.php: aperto come pagina -> 403" "$(curl -s -o /dev/null -H 'Sec-Fetch-Dest: document' -w '%{http_code}' "$B/pdf.php?f=$(basename "$(sql "SELECT pdf FROM libri WHERE slug = 'il-custode-dell-etna'")")")" 403
chk "contatori: le letture da visitatori diversi si sommano" "$(sql "SELECT letture >= 2 FROM libri WHERE slug = 'il-custode-dell-etna'")" 1

# ---------- collane e pagine ----------
Tk=$(tok admin/collane.php)
chk "salva collana" "$(post admin/collane.php -F csrf=$Tk -F azione=salva -F id=0 -F nome='Collana Nuova' -F descrizione=Ciao -F visibile=1 -F ordine=5)" 302
chk "collana nel catalogo" "$(curl -s "$B/index.php?collana=il-custode-dell-etna" | conta 'class="scheda"')" 3
chk "pagina collana: 200" "$(code collana.php?s=il-custode-dell-etna)" 200
chk "pagina collana: 3 libri" "$(curl -s "$B/collana.php?s=il-custode-dell-etna" | conta 'class="scheda"')" 3
chk "pagina collana: JSON-LD" "$(curl -s "$B/collana.php?s=il-custode-dell-etna" | conta 'CollectionPage')" 1
chk "pagina collana: inizia dal libro 1" "$(curl -s "$B/collana.php?s=il-custode-dell-etna" | conta 'Inizia dal libro 1')" 1
chk "pagina collana inesistente: 404" "$(code collana.php?s=non-esiste)" 404
chk "elenco collane: 200" "$(code collane.php)" 200
chk "elenco collane: link alla collana" "$(curl -s "$B/collane.php" | conta 'collana.php?s=il-custode-dell-etna')" 1
chk "menu con Collane" "$(curl -s "$B/index.php" | conta '>Collane<')" 1
chk "sitemap con la collana" "$(curl -s "$B/sitemap.php" | conta 'collana.php?s=il-custode')" 1
Tk=$(tok admin/pagine.php)
chk "salva pagina" "$(post admin/pagine.php -d "csrf=$Tk&azione=salva&id=0&titolo=Contatti&contenuto=**Scrivici**+a+ciao&visibile=1&nel_menu=1")" 302
chk "pagina con grassetto" "$(curl -s "$B/pagina.php?s=contatti" | conta '<strong>Scrivici</strong>')" 1
chk "menu con Contatti" "$(curl -s "$B/index.php" | conta '>Contatti<')" 1

# ---------- aspetto: logo, banner, colori ----------
Tk=$(tok admin/aspetto.php)
chk "aspetto: carica emblema e colori" "$(post admin/aspetto.php -F csrf=$Tk -F logo_emblema=@assets/emblema.png -F colore_blu='#112233' -F colore_oro='#aabbcc')" 302
chk "aspetto: emblema nella testata" "$(curl -s "$B/" | conta 'class="marchio"[^>]*><img src="uploads/')" 1
chk "aspetto: colori applicati" "$(curl -s "$B/" | conta '\-\-blu:#112233;\-\-oro:#aabbcc')" 1
Tk=$(tok admin/aspetto.php); post admin/aspetto.php -F csrf=$Tk -F ripristina_logo_emblema=1 -F colori_originali=1 >/dev/null
chk "aspetto: ripristino emblema" "$(curl -s "$B/" | conta 'src="assets/emblema.png"')" 1
chk "aspetto: ripristino colori" "$(curl -s "$B/" | conta '\-\-blu:#0b1a36')" 1
HERO=$(curl -s "$B/" | grep -o '\-\-hero:url("[^"]*")' | sed 's/.*url("//; s/")$//')
chk "banner: indirizzo completo (le variabili CSS si leggono rispetto a stile.css)" "$(echo "$HERO" | conta "^$B/assets/hero.jpg$")" 1
chk "banner: il file si scarica" "$(curl -s -o /dev/null -w '%{http_code}' "$HERO")" 200
Tk=$(tok admin/aspetto.php); post admin/aspetto.php -F csrf=$Tk -F colore_blu='rosso;}body{display:none' >/dev/null
chk "aspetto: colore non valido scartato" "$(curl -s "$B/" | conta 'display:none')" 0

# ---------- impostazioni e manutenzione ----------
Tk=$(tok admin/impostazioni.php)
post admin/impostazioni.php -d "csrf=$Tk&nome_sito=Vlad+Test&slogan=Slogan+Nuovo&sottoslogan=x&footer=f&email=a@b.it&autore_predefinito=Vlad&per_pagina=12&ordine_catalogo=numero&manutenzione=1&messaggio_manutenzione=Torno+subito" >/dev/null
chk "manutenzione: visitatore 503" "$(curl -s -o /dev/null -w '%{http_code}' $B/index.php)" 503
chk "manutenzione: admin vede il sito" "$(code index.php)" 200
Tk=$(tok admin/impostazioni.php)
post admin/impostazioni.php -d "csrf=$Tk&nome_sito=Vlad+Test&slogan=Slogan+Nuovo&sottoslogan=x&footer=f&email=a@b.it&autore_predefinito=Vlad&per_pagina=12&ordine_catalogo=numero&mostra_evidenza=1" >/dev/null
chk "slogan nuovo in home" "$(curl -s "$B/" | conta 'Slogan Nuovo')" 1

# ---------- strumenti ----------
Tk=$(tok admin/strumenti.php)
if [ -n "$DBNAME" ]; then
  chk "backup (JSON con tutte le tabelle)" "$(curl -s -b "$J" -d "csrf=$Tk&azione=backup" $B/admin/strumenti.php | conta '"libri": \[')" 1
else
  chk "backup" "$(curl -s -b "$J" -d "csrf=$Tk&azione=backup" $B/admin/strumenti.php | head -c 15)" "SQLite format 3"
fi
curl -s -b "$J" -d "csrf=$Tk&azione=csv" $B/admin/strumenti.php > "$T/cat.csv"
chk "csv esportato" "$(conta 'il-custode-dell-etna' < "$T/cat.csv")" 1
echo 'x;Libro Importato;;Vlad;Serie CSV;3;Fantasy;it;2026;0;;uno,due;Trama;0;;0' >> "$T/cat.csv"
Tk=$(tok admin/strumenti.php); post admin/strumenti.php -F csrf=$Tk -F azione=importa_csv -F csv=@"$T/cat.csv" >/dev/null
chk "import csv crea il libro" "$(sql "SELECT COUNT(*) FROM libri WHERE titolo = 'Libro Importato'")" 1
chk "sitemap" "$(curl -s "$B/sitemap.php" | conta '<loc>' | awk '$1>3{print "ok"}')" ok
chk "feed RSS" "$(curl -s "$B/feed.php" | conta '<item>' | awk '$1>1{print "ok"}')" ok
chk "chi-siamo reindirizza" "$(curl -s -o /dev/null -w '%{http_code}' $B/chi-siamo.php)" 301

# ---------- verifica in due passaggi ----------
Tk=$(tok admin/sicurezza.php); post admin/sicurezza.php -d "csrf=$Tk&azione=2fa_inizia" >/dev/null
chk "2FA: QR mostrato" "$(curl -s -b "$J" "$B/admin/sicurezza.php" | conta 'new QRCode')" 1
Tk=$(tok admin/sicurezza.php); post admin/sicurezza.php -d "csrf=$Tk&azione=2fa_conferma&codice=000000" >/dev/null
chk "2FA: codice errato non attiva" "$(sql "SELECT totp_attivo FROM admin")" 0
Tk=$(tok admin/sicurezza.php); post admin/sicurezza.php -d "csrf=$Tk&azione=2fa_conferma&codice=$(totp)" >/dev/null
chk "2FA: attivata" "$(sql "SELECT totp_attivo FROM admin")" 1
BACKUP=$(curl -s -b "$J" "$B/admin/sicurezza.php" | grep -o '[0-9a-f]\{4\}-[0-9a-f]\{4\}' | head -1)
chk "2FA: codici di backup mostrati" "$([ -n "$BACKUP" ] && echo si)" si
post admin/logout.php >/dev/null; nuova_sessione
Tk=$(tok admin/login.php)
chk "2FA: password giusta -> seconda schermata" "$(post admin/login.php -d "csrf=$Tk&password=provaprova12")" 302
chk "2FA: chiede il codice" "$(curl -s -b "$J" -c "$J" "$B/admin/login.php" | conta 'Verifica in due passaggi')" 1
chk "2FA: senza codice niente admin" "$(code admin/libri.php)" 302
Tk=$(tok admin/login.php)
chk "2FA: codice sbagliato rifiutato" "$(curl -s -b "$J" -c "$J" -d "csrf=$Tk&codice=111111" "$B/admin/login.php" | conta 'Codice sbagliato')" 1
Tk=$(tok admin/login.php)
chk "2FA: codice giusto" "$(post admin/login.php -d "csrf=$Tk&codice=$(totp)")" 302
chk "2FA: admin accessibile" "$(code admin/libri.php)" 200
post admin/logout.php >/dev/null; nuova_sessione
Tk=$(tok admin/login.php); post admin/login.php -d "csrf=$Tk&password=provaprova12" >/dev/null
Tk=$(tok admin/login.php)
chk "2FA: codice di backup" "$(post admin/login.php -d "csrf=$Tk&codice=$BACKUP")" 302
post admin/logout.php >/dev/null; nuova_sessione
Tk=$(tok admin/login.php); post admin/login.php -d "csrf=$Tk&password=provaprova12" >/dev/null
Tk=$(tok admin/login.php)
chk "2FA: backup usato una volta sola" "$(curl -s -b "$J" -c "$J" -d "csrf=$Tk&codice=$BACKUP" "$B/admin/login.php" | conta 'Codice sbagliato')" 1
Tk=$(tok admin/login.php); post admin/login.php -d "csrf=$Tk&codice=$(totp)" >/dev/null
Tk=$(tok admin/sicurezza.php)
post admin/sicurezza.php -d "csrf=$Tk&azione=2fa_disattiva&password=sbagliata&codice=$(totp)" >/dev/null
chk "2FA: disattivare richiede la password" "$(sql "SELECT totp_attivo FROM admin")" 1
Tk=$(tok admin/sicurezza.php); post admin/sicurezza.php -d "csrf=$Tk&azione=2fa_disattiva&password=provaprova12&codice=$(totp)" >/dev/null
chk "2FA: disattivata" "$(sql "SELECT totp_attivo FROM admin")" 0

# ---------- recupero password ----------
post admin/logout.php >/dev/null; nuova_sessione
chk "recupero: pagina" "$(curl -s "$B/admin/login.php?recupero=1" | conta 'Recupera la password')" 1
Tk=$(tok "admin/login.php?recupero=1")
chk "recupero: codice sbagliato" "$(curl -s -b "$J" -c "$J" -d "csrf=$Tk&codice=AAAAA-AAAAA-AAAAA-AAAAA&nuova=nuovapassword1&nuova2=nuovapassword1" "$B/admin/login.php?recupero=1" | conta 'Codice di recupero sbagliato')" 1
Tk=$(tok "admin/login.php?recupero=1")
curl -s -b "$J" -c "$J" -d "csrf=$Tk&codice=$RECUPERO&nuova=nuovapassword1&nuova2=nuovapassword1" "$B/admin/login.php?recupero=1" > "$T/rec.html"
chk "recupero: password cambiata" "$(conta 'Password cambiata' < "$T/rec.html")" 1
NUOVO_REC=$(grep -o '[A-Z2-7]\{5\}-[A-Z2-7]\{5\}-[A-Z2-7]\{5\}-[A-Z2-7]\{5\}' "$T/rec.html" | head -1)
chk "recupero: nuovo codice emesso" "$([ -n "$NUOVO_REC" ] && [ "$NUOVO_REC" != "$RECUPERO" ] && echo si)" si
Tk=$(tok "admin/login.php?recupero=1")
chk "recupero: il vecchio codice non vale più" "$(curl -s -b "$J" -c "$J" -d "csrf=$Tk&codice=$RECUPERO&nuova=altrapassword12&nuova2=altrapassword12" "$B/admin/login.php?recupero=1" | conta 'Codice di recupero sbagliato')" 1
Tk=$(tok admin/login.php)
chk "recupero: vecchia password rifiutata" "$(curl -s -b "$J" -c "$J" -d "csrf=$Tk&password=provaprova12" "$B/admin/login.php" | conta 'Password sbagliata')" 1
Tk=$(tok admin/login.php)
chk "recupero: nuova password funziona" "$(post admin/login.php -d "csrf=$Tk&password=nuovapassword1")" 302

# ---------- recupero d'emergenza via file sul server ----------
post admin/logout.php >/dev/null; nuova_sessione
echo "passwordviafile99" > data/reset-password.txt
chk "reset via file: messaggio" "$(curl -s "$B/admin/login.php" | conta 'Password reimpostata')" 1
chk "reset via file: file cancellato" "$([ ! -f data/reset-password.txt ] && echo si)" si
Tk=$(tok admin/login.php)
chk "reset via file: nuova password funziona" "$(post admin/login.php -d "csrf=$Tk&password=passwordviafile99")" 302
Tk=$(tok admin/sicurezza.php); post admin/sicurezza.php -d "csrf=$Tk&azione=2fa_inizia" >/dev/null
Tk=$(tok admin/sicurezza.php); post admin/sicurezza.php -d "csrf=$Tk&azione=2fa_conferma&codice=$(totp)" >/dev/null
post admin/logout.php >/dev/null; nuova_sessione
: > data/reset-2fa.txt
curl -s "$B/admin/login.php" >/dev/null
chk "reset 2FA via file" "$(sql "SELECT totp_attivo FROM admin")" 0

# ---------- password e blocco accessi ----------
Tk=$(tok admin/login.php); post admin/login.php -d "csrf=$Tk&password=passwordviafile99" >/dev/null
Tk=$(tok admin/password.php); post admin/password.php -d "csrf=$Tk&attuale=passwordviafile99&nuova=ultimapassword1&nuova2=ultimapassword1" >/dev/null
post admin/logout.php >/dev/null
chk "admin chiuso senza login" "$(curl -s -o /dev/null -w '%{http_code}' $B/admin/libri.php)" 302
sql "DELETE FROM tentativi" >/dev/null
nuova_sessione
for i in 1 2 3 4 5 6; do Tk=$(tok admin/login.php); post admin/login.php -d "csrf=$Tk&password=sbagliata" >/dev/null; done
Tk=$(tok admin/login.php)
chk "blocco dopo 5 errori" "$(curl -s -b "$J" -c "$J" -d "csrf=$Tk&password=ultimapassword1" "$B/admin/login.php" | conta 'Troppi tentativi')" 1


# ---------- lettori: email, registrazione, accesso, liste, recensioni, privacy ----------
MAIL="$T/mail"; SPORTA=${SMTP_PORTA:-2599}
python3 "$REPO/sito/tests/smtp_sink.py" $SPORTA "$MAIL" >/dev/null 2>&1 & SINK=$!
for i in $(seq 1 30); do [ -f "$MAIL/PRONTO" ] && break; sleep 0.2; done
JR="$T/cookie-lettore"; : > "$JR"
rtok()  { curl -s -b "$JR" -c "$JR" "$B/$1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | cut -d'"' -f4; }
rpost() { local p=$1; shift; curl -s -b "$JR" -c "$JR" -o "$T/rout.html" -w "%{http_code}" "$@" "$B/$p"; }
rtesto() { rpost "$@" >/dev/null; cat "$T/rout.html"; }
rget()  { curl -s -b "$JR" -c "$JR" "$B/$1"; }
nmail() { ls "$MAIL"/*.eml 2>/dev/null | wc -l | tr -d ' '; }
tokmail() { python3 - "$MAIL/$1.eml" <<'PY'
import email, re, sys
m = email.message_from_file(open(sys.argv[1], encoding="utf-8"))
t = ""
for p in m.walk():
    if p.get_content_type() == "text/plain":
        t = p.get_payload(decode=True).decode("utf-8")
r = re.search(r"t=([0-9a-f]{48})", t)
print(r.group(1) if r else "")
PY
}
oggetto() { python3 - "$MAIL/$1.eml" <<'PY'
import email, email.header, sys
m = email.message_from_file(open(sys.argv[1], encoding="utf-8"))
print(str(email.header.make_header(email.header.decode_header(m["Subject"]))))
PY
}
LIBRO=il-custode-dell-etna

# registrazioni chiuse finché non ci sono titolare ed email
chk "registrazione chiusa: avviso" "$(rget 'account.php?a=registrati' | conta 'registrazioni sono chiuse')" 1
chk "menu: nessun Accedi a registrazioni chiuse" "$(rget index.php | conta '>Accedi<')" 0
chk "libro senza lettori: nessuna sezione recensioni" "$(rget "libro.php?s=$LIBRO" | conta 'id="recensioni"')" 0
chk "api lettore da anonimo" "$(rget "api-lettore.php?libro=$LIBRO" | conta '"loggato":false')" 1

nuova_sessione; sql "DELETE FROM tentativi" >/dev/null
Tk=$(tok admin/login.php); post admin/login.php -d "csrf=$Tk&password=ultimapassword1" >/dev/null
for pg in lettori.php recensioni.php registrazione.php email.php "lettori.php?stato=1&q=a" "recensioni.php?stato=tutte"; do chk "admin $pg risponde" "$(code admin/$pg)" 200; done
Tk=$(tok admin/registrazione.php)
post admin/registrazione.php -d "csrf=$Tk&azione=salva&registrazione_attiva=1&recensioni_moderate=1" >/dev/null
chk "senza titolare ed email la registrazione non si apre" "$(sql "SELECT valore FROM impostazioni WHERE chiave='registrazione_attiva'")" 0
Tk=$(tok admin/email.php)
post admin/email.php -d "csrf=$Tk&azione=salva&smtp_host=127.0.0.1&smtp_porta=$SPORTA&smtp_cifratura=nessuna&smtp_utente=u&smtp_password=segreta&email_mittente=no-reply@prova.it&nome_mittente=Vlad+Editore" >/dev/null
chk "password SMTP salvata" "$(sql "SELECT valore FROM impostazioni WHERE chiave='smtp_password'")" segreta
Tk=$(tok admin/email.php); post admin/email.php -d "csrf=$Tk&azione=salva&smtp_host=127.0.0.1&smtp_porta=$SPORTA&smtp_cifratura=nessuna&smtp_utente=u&smtp_password=&email_mittente=no-reply@prova.it" >/dev/null
chk "password SMTP vuota = non cambia" "$(sql "SELECT valore FROM impostazioni WHERE chiave='smtp_password'")" segreta
Tk=$(tok admin/email.php); post admin/email.php -d "csrf=$Tk&azione=prova&prova_a=admin@prova.it" >/dev/null
chk "email di prova arrivata" "$(nmail)" 1
chk "email di prova: destinatario" "$(grep -c 'X-Sink-To: admin@prova.it' "$MAIL/1.eml")" 1
chk "backup JSON senza password SMTP" "$(curl -s -b "$J" -c "$J" "$B/admin/strumenti.php" | conta 'smtp_password')" 0
Tk=$(tok admin/registrazione.php)
post admin/registrazione.php -d "csrf=$Tk&azione=salva&registrazione_attiva=1&recensioni_moderate=1&titolare_nome=Mario+Rossi&titolare_email=privacy@prova.it&titolare_indirizzo=Taormina" >/dev/null
chk "registrazione aperta" "$(sql "SELECT valore FROM impostazioni WHERE chiave='registrazione_attiva'")" 1
chk "privacy con il nome del titolare" "$(rget privacy.php | conta 'Mario Rossi')" 1
chk "privacy: nessun segnaposto rimasto" "$(rget privacy.php | conta '{titolare}')" 0
chk "privacy: elenco puntato" "$(rget privacy.php | conta '<ul><li>')" 1
chk "menu: Accedi con registrazioni aperte" "$(rget index.php | conta '>Accedi<')" 1

# registrazione: controlli
reg() { # $1=email $2=nick $3=password $4=extra  (aspetta i 3 secondi minimi)
  Tr=$(rtok 'account.php?a=registrati'); sleep 3.3
  rpost 'account.php?a=registrati' -d "csrf=$Tr&email=$1&nick=$2&password=$3&password2=${5:-$3}&$4"
}
chk "form: casella privacy non spuntata di base" "$(rget 'account.php?a=registrati' | conta 'name="privacy" value="1" required>')" 1
chk "form: casella età" "$(rget 'account.php?a=registrati' | conta 'name="eta" value="1" required>')" 1
reg luca@prova.it Luca88 passwordlunga99 "eta=1" >/dev/null
chk "senza privacy: nessun account" "$(sql "SELECT COUNT(*) FROM lettori")" 0
reg luca@prova.it Luca88 passwordlunga99 "privacy=1" >/dev/null
chk "senza età: nessun account" "$(sql "SELECT COUNT(*) FROM lettori")" 0
reg luca@prova.it Luca88 corta "privacy=1&eta=1" >/dev/null
chk "password corta: nessun account" "$(sql "SELECT COUNT(*) FROM lettori")" 0
reg luca@prova.it Luca88 passwordlunga99 "privacy=1&eta=1" "diversa123456" >/dev/null
chk "password diverse: nessun account" "$(sql "SELECT COUNT(*) FROM lettori")" 0
reg luca@prova.it vlad passwordlunga99 "privacy=1&eta=1" >/dev/null
chk "nome riservato: nessun account" "$(sql "SELECT COUNT(*) FROM lettori")" 0
reg luca@prova.it Luca88 passwordlunga99 "privacy=1&eta=1&sito_web=http://spam" >/dev/null
chk "trappola dei bot: nessun account" "$(sql "SELECT COUNT(*) FROM lettori")" 0
Tr=$(rtok 'account.php?a=registrati')
chk "troppo in fretta: rifiutato" "$(rtesto 'account.php?a=registrati' -d "csrf=$Tr&email=veloce@prova.it&nick=Veloce1&password=passwordlunga99&password2=passwordlunga99&privacy=1&eta=1" | conta 'troppo in fretta')" 1
reg luca@prova.it Luca88 passwordlunga99 "privacy=1&eta=1" >/dev/null
chk "registrazione: account creato (da confermare)" "$(sql "SELECT stato FROM lettori WHERE email='luca@prova.it'")" 0
chk "registrazione: password cifrata" "$(sql "SELECT substr(password,1,4) FROM lettori WHERE email='luca@prova.it'")" '$2y$'
chk "registrazione: due consensi registrati" "$(sql "SELECT COUNT(*) FROM consensi WHERE tipo IN ('privacy','eta')")" 2
chk "registrazione: versione informativa salvata" "$(sql "SELECT privacy_versione FROM lettori WHERE email='luca@prova.it'")" 1
chk "registrazione: email di conferma partita" "$(nmail)" 2
chk "email di conferma: oggetto" "$(oggetto 2 | conta 'Conferma la tua email')" 1
chk "email di conferma: destinatario" "$(grep -c 'X-Sink-To: luca@prova.it' "$MAIL/2.eml")" 1
chk "token salvato solo come hash" "$(sql "SELECT COUNT(*) FROM token_lettori WHERE hash LIKE '$(tokmail 2)%'")" 0
TV=$(tokmail 2)
chk "token nell'email" "$(echo -n "$TV" | wc -c | tr -d ' ')" 48
nuova_sessione_r() { : > "$JR"; }
chk "accesso prima della conferma: rifiutato" "$(Tr=$(rtok 'account.php?a=accedi'); rtesto 'account.php?a=accedi' -d "csrf=$Tr&id=luca@prova.it&password=passwordlunga99" | conta 'prima confermare')" 1
chk "conferma: pagina con pulsante (GET non consuma)" "$(rget "account.php?a=verifica&t=$TV" | conta 'name="t" value="'"$TV"'"')" 1
chk "conferma: ancora da confermare dopo il GET" "$(sql "SELECT stato FROM lettori WHERE email='luca@prova.it'")" 0
chk "conferma: token sbagliato" "$(rget "account.php?a=verifica&t=$(printf 'a%.0s' $(seq 1 48))" | conta 'non è valido')" 1
Tr=$(rtok "account.php?a=verifica&t=$TV"); rpost 'account.php?a=verifica' -d "csrf=$Tr&t=$TV" >/dev/null
chk "conferma: account attivo" "$(sql "SELECT stato FROM lettori WHERE email='luca@prova.it'")" 1
Tr=$(rtok "account.php?a=verifica&t=$TV"); rpost 'account.php?a=verifica' -d "csrf=$Tr&t=$TV" >/dev/null
chk "token a uso singolo" "$(rget "account.php?a=verifica&t=$TV" | conta 'non è valido')" 1
sql "DELETE FROM tentativi" >/dev/null
# email già registrata: stessa risposta, nessun doppione, avviso al proprietario
reg luca@prova.it AltroNick1 passwordlunga99 "privacy=1&eta=1" >/dev/null
chk "email già registrata: nessun secondo account" "$(sql "SELECT COUNT(*) FROM lettori")" 1
chk "email già registrata: avviso al proprietario" "$(oggetto 3 | conta 'già un account')" 1
reg altro@prova.it Luca88 passwordlunga99 "privacy=1&eta=1" >/dev/null
chk "nome già usato: nessun secondo account" "$(sql "SELECT COUNT(*) FROM lettori")" 1

# accesso
Tr=$(rtok 'account.php?a=accedi'); chk "accesso con password sbagliata" "$(rtesto 'account.php?a=accedi' -d "csrf=$Tr&id=Luca88&password=sbagliata123" | conta 'non corretti')" 1
Tr=$(rtok 'account.php?a=accedi'); chk "accesso con nome (maiuscole ignorate): 302" "$(rpost 'account.php?a=accedi' -d "csrf=$Tr&id=luca88&password=passwordlunga99&ricorda=1")" 302
chk "profilo: La mia libreria" "$(rget account.php | conta '<h1>La mia libreria</h1>')" 1
chk "menu: nome del lettore" "$(rget index.php | conta '👤 Luca88')" 1
chk "cookie ricordami creato" "$(grep -c ricordami "$JR")" 1
chk "ricordami: salvato solo l'hash" "$(sql "SELECT COUNT(*) FROM token_lettori WHERE tipo='ricorda'")" 1
# la sessione cade ma il cookie «ricordami» riporta dentro
sed -i '/PHPSESSID/d' "$JR"
chk "ricordami: rientra senza sessione" "$(rget account.php | conta '<h1>La mia libreria</h1>')" 1

# preferiti e da leggere
Tr=$(rtok "libro.php?s=$LIBRO")
chk "libro: pulsanti per il lettore" "$(rget "libro.php?s=$LIBRO" | conta 'Aggiungi ai preferiti')" 1
rpost 'account.php?a=lista' -d "csrf=$Tr&libro=$LIBRO&lista=preferito" >/dev/null
chk "preferito aggiunto" "$(sql "SELECT COUNT(*) FROM liste WHERE lista='preferito'")" 1
Tr=$(rtok "libro.php?s=$LIBRO"); rpost 'account.php?a=lista' -d "csrf=$Tr&libro=$LIBRO&lista=da_leggere" >/dev/null
chk "da leggere aggiunto" "$(sql "SELECT COUNT(*) FROM liste WHERE lista='da_leggere'")" 1
chk "profilo mostra il preferito" "$(rget account.php | conta 'class="scheda"')" 2
Tr=$(rtok "libro.php?s=$LIBRO"); rpost 'account.php?a=lista' -d "csrf=$Tr&libro=$LIBRO&lista=preferito" >/dev/null
chk "preferito tolto" "$(sql "SELECT COUNT(*) FROM liste WHERE lista='preferito'")" 0
Tr=$(rtok "libro.php?s=$LIBRO"); rpost 'account.php?a=lista' -d "csrf=$Tr&libro=$LIBRO&lista=strano" >/dev/null
chk "lista non valida ignorata" "$(sql "SELECT COUNT(*) FROM liste")" 1
chk "lista senza CSRF: rifiutata" "$(rpost 'account.php?a=lista' -d "libro=$LIBRO&lista=preferito")" 400

# API del lettore: pagina e segnalibri sincronizzati
CS=$(rget "leggi.php?s=$LIBRO" | grep -o 'data-csrf="[^"]*"' | cut -d'"' -f2)
chk "lettore: api e csrf presenti per chi è collegato" "$([ -n "$CS" ] && echo si)" si
api() { curl -s -b "$JR" -c "$JR" -H "X-CSRF: $CS" -d "$1" "$B/api-lettore.php"; }
chk "api: stato" "$(rget "api-lettore.php?libro=$LIBRO" | conta '"loggato":true')" 1
chk "api senza CSRF: 400" "$(curl -s -b "$JR" -c "$JR" -o /dev/null -w '%{http_code}' -d "libro=$LIBRO&azione=progresso&pagina=3&totale=20" "$B/api-lettore.php")" 400
api "libro=$LIBRO&azione=progresso&pagina=7&totale=40" >/dev/null
chk "api: progresso salvato" "$(sql "SELECT COUNT(*) FROM progressi WHERE pagina = 7 AND totale = 40")" 1
api "libro=$LIBRO&azione=progresso&pagina=9&totale=40" >/dev/null
chk "api: progresso aggiornato (una sola riga)" "$(sql "SELECT COUNT(*) FROM progressi WHERE pagina = 9")" 1
api "libro=$LIBRO&azione=segnalibro&pagina=5&presente=1" >/dev/null; api "libro=$LIBRO&azione=segnalibro&pagina=5&presente=1" >/dev/null
api "libro=$LIBRO&azione=segnalibri&pagine=2,3,3,9999" >/dev/null
chk "api: segnalibri senza doppioni e pagine assurde ignorate" "$(sql "SELECT group_concat(pagina) FROM (SELECT pagina FROM segnalibri ORDER BY pagina) x")" "2,3,5"
api "libro=$LIBRO&azione=segnalibro&pagina=3&presente=0" >/dev/null
chk "api: segnalibro tolto" "$(sql "SELECT COUNT(*) FROM segnalibri")" 2
chk "api: stato con pagina e segnalibri" "$(rget "api-lettore.php?libro=$LIBRO" | conta '"pagina":9,"segnalibri":\[2,5\]')" 1
chk "api: libro inesistente: 404" "$(curl -s -b "$JR" -o /dev/null -w '%{http_code}' "$B/api-lettore.php?libro=non-esiste")" 404
chk "lettore: apre alla pagina richiesta nell'indirizzo" "$(rget "leggi.php?s=$LIBRO&pagina=4" | conta 'data-api')" 1
chk "profilo: continua a leggere" "$(rget account.php | conta 'pagina 9')" 1

# recensioni
Tr=$(rtok "libro.php?s=$LIBRO")
chk "recensione senza voto: niente" "$(rpost 'account.php?a=recensione' -d "csrf=$Tr&libro=$LIBRO&voto=&titolo=Bello&testo=Mi+e+piaciuto" >/dev/null; sql "SELECT COUNT(*) FROM recensioni")" 0
Tr=$(rtok "libro.php?s=$LIBRO"); rpost 'account.php?a=recensione' -d "csrf=$Tr&libro=$LIBRO&voto=5&titolo=Bellissimo&testo=Mi+e+piaciuto+tanto+<b>x</b>" >/dev/null
chk "recensione salvata (in attesa)" "$(sql "SELECT stato FROM recensioni")" 0
chk "recensione in attesa: non pubblica" "$(curl -s "$B/libro.php?s=$LIBRO" | conta 'Bellissimo')" 0
chk "recensione in attesa: lo vede l'autore" "$(rget "libro.php?s=$LIBRO" | conta 'in attesa di verifica')" 1
chk "nessun voto pubblico prima dell'approvazione" "$(curl -s "$B/libro.php?s=$LIBRO" | conta 'aggregateRating')" 0
Tk=$(tok admin/recensioni.php); RID=$(sql "SELECT id FROM recensioni")
chk "admin: approva" "$(post admin/recensioni.php -d "csrf=$Tk&azione=approva&id=$RID&stato=0")" 302
chk "recensione pubblicata" "$(curl -s "$B/libro.php?s=$LIBRO" | conta 'Bellissimo')" 1
chk "recensione: HTML neutralizzato" "$(curl -s "$B/libro.php?s=$LIBRO" | conta '<b>x</b>')" 0
chk "recensione: nome pubblico, non l'email" "$(curl -s "$B/libro.php?s=$LIBRO" | conta 'luca@prova.it')" 0
chk "schema.org: voto medio" "$(curl -s "$B/libro.php?s=$LIBRO" | conta '"ratingValue":5')" 1
Tr=$(rtok "libro.php?s=$LIBRO"); rpost 'account.php?a=recensione' -d "csrf=$Tr&libro=$LIBRO&voto=4&titolo=Rivisto&testo=ok" >/dev/null
chk "recensione modificata: una sola per lettore" "$(sql "SELECT COUNT(*) FROM recensioni")" 1
chk "recensione modificata: torna in attesa" "$(sql "SELECT stato FROM recensioni")" 0
Tk=$(tok admin/recensioni.php); post admin/recensioni.php -d "csrf=$Tk&azione=rifiuta&id=$RID&stato=0" >/dev/null
chk "recensione rifiutata: non pubblica" "$(curl -s "$B/libro.php?s=$LIBRO" | conta 'Rivisto')" 0
Tk=$(tok admin/impostazioni.php)

# password dimenticata
nuova_sessione_r
Tr=$(rtok 'account.php?a=dimenticata'); rpost 'account.php?a=dimenticata' -d "csrf=$Tr&email=luca@prova.it" >/dev/null
N=$(nmail); chk "reset: email partita" "$(oggetto $N | conta 'Reimposta')" 1
TR=$(tokmail $N)
Tr=$(rtok 'account.php?a=dimenticata'); rpost 'account.php?a=dimenticata' -d "csrf=$Tr&email=nessuno@prova.it" >/dev/null
chk "reset per email sconosciuta: nessuna email" "$(nmail)" "$N"
chk "reset: risposta uguale per email sconosciuta" "$(conta 'ti abbiamo scritto un link' < "$T/rout.html")" 1
Tr=$(rtok "account.php?a=reimposta&t=$TR"); chk "reset: password debole rifiutata" "$(rtesto 'account.php?a=reimposta' -d "csrf=$Tr&t=$TR&password=corta&password2=corta" | conta 'class="errore">La password deve avere almeno 10')" 1
Tr=$(rtok "account.php?a=reimposta&t=$TR"); rpost 'account.php?a=reimposta' -d "csrf=$Tr&t=$TR&password=nuovapassword77&password2=nuovapassword77" >/dev/null
chk "reset: i «ricordami» cadono" "$(sql "SELECT COUNT(*) FROM token_lettori WHERE tipo='ricorda'")" 0
chk "reset: avviso di sicurezza" "$(oggetto $(nmail) | conta 'password è cambiata')" 1
Tr=$(rtok 'account.php?a=accedi'); chk "reset: vecchia password non vale più" "$(rtesto 'account.php?a=accedi' -d "csrf=$Tr&id=Luca88&password=passwordlunga99" | conta 'non corretti')" 1
Tr=$(rtok 'account.php?a=accedi'); chk "reset: nuova password vale" "$(rpost 'account.php?a=accedi' -d "csrf=$Tr&id=Luca88&password=nuovapassword77")" 302

# profilo: nome, email, password
Tr=$(rtok account.php); rpost 'account.php?a=profilo' -d "csrf=$Tr&azione=nick&nick=Luca99" >/dev/null
chk "cambio nome" "$(sql "SELECT nick FROM lettori")" Luca99
Tr=$(rtok account.php); rpost 'account.php?a=profilo' -d "csrf=$Tr&azione=email&email=nuova@prova.it&password_attuale=sbagliata" >/dev/null
chk "cambio email con password sbagliata: niente" "$(sql "SELECT email FROM lettori")" luca@prova.it
Tr=$(rtok account.php); rpost 'account.php?a=profilo' -d "csrf=$Tr&azione=email&email=nuova@prova.it&password_attuale=nuovapassword77" >/dev/null
chk "cambio email: finché non conferma resta la vecchia" "$(sql "SELECT email FROM lettori")" luca@prova.it
N=$(nmail); chk "cambio email: conferma al NUOVO indirizzo" "$(grep -c 'X-Sink-To: nuova@prova.it' "$MAIL/$N.eml")" 1
TE=$(tokmail $N)
Tr=$(rtok "account.php?a=email&t=$TE"); rpost 'account.php?a=email' -d "csrf=$Tr&t=$TE" >/dev/null
chk "cambio email confermato" "$(sql "SELECT email FROM lettori")" nuova@prova.it
Tr=$(rtok account.php); rpost 'account.php?a=profilo' -d "csrf=$Tr&azione=password&password_attuale=nuovapassword77&password=ancorapiulunga55&password2=ancorapiulunga55" >/dev/null
chk "cambio password dal profilo" "$(php -r 'require "inc/bootstrap.php"; echo password_verify("ancorapiulunga55", db()->query("SELECT password FROM lettori")->fetchColumn()) ? "si" : "no";')" si

# esportazione dei dati
Tr=$(rtok account.php)
EXP=$(curl -s -b "$JR" -c "$JR" -d "csrf=$Tr" "$B/account.php?a=esporta")
chk "esporta: JSON con l'account" "$(echo "$EXP" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["account"]["nick"], len(d["progressi"]), len(d["segnalibri"]), len(d["consensi"]) >= 2, "password" in d["account"])')" "Luca99 1 2 True False"
chk "esporta senza CSRF: rifiutato" "$(curl -s -b "$JR" -o /dev/null -w '%{http_code}' -X POST "$B/account.php?a=esporta")" 400

# limiti di tentativi sul login
sql "DELETE FROM tentativi" >/dev/null; nuova_sessione_r
for i in 1 2 3 4 5 6 7; do Tr=$(rtok 'account.php?a=accedi'); rpost 'account.php?a=accedi' -d "csrf=$Tr&id=Luca99&password=sbagliatissima$i" >/dev/null; done
chk "troppi tentativi di accesso: blocco" "$(conta 'Troppi tentativi' < "$T/rout.html")" 1
Tr=$(rtok 'account.php?a=accedi'); chk "durante il blocco nemmeno la password giusta" "$(rpost 'account.php?a=accedi' -d "csrf=$Tr&id=Luca99&password=ancorapiulunga55")" 200
sql "DELETE FROM tentativi" >/dev/null

# admin: sospendi e riattiva, informativa nuova versione
LID=$(sql "SELECT id FROM lettori")
Tk=$(tok admin/lettori.php); post admin/lettori.php -d "csrf=$Tk&azione=sospendi&id=$LID" >/dev/null
Tr=$(rtok 'account.php?a=accedi'); chk "sospeso: non entra" "$(rtesto 'account.php?a=accedi' -d "csrf=$Tr&id=Luca99&password=ancorapiulunga55" | conta 'sospeso')" 1
Tk=$(tok admin/lettori.php); post admin/lettori.php -d "csrf=$Tk&azione=riattiva&id=$LID" >/dev/null
Tr=$(rtok 'account.php?a=accedi'); chk "riattivato: entra" "$(rpost 'account.php?a=accedi' -d "csrf=$Tr&id=Luca99&password=ancorapiulunga55")" 302
chk "admin: elenco lettori" "$(curl -s -b "$J" "$B/admin/lettori.php" | conta '<b>Luca99</b>')" 1
Tk=$(tok admin/registrazione.php); post admin/registrazione.php -d "csrf=$Tk&azione=nuova_versione" >/dev/null
chk "nuova versione informativa: va riaccettata" "$(rget account.php | conta '<h1>Informativa aggiornata</h1>')" 1
chk "nuova versione: l'API chiede il consenso" "$(curl -s -b "$JR" -o /dev/null -w '%{http_code}' "$B/api-lettore.php?libro=$LIBRO")" 403
Tr=$(rtok account.php); rpost account.php -d "csrf=$Tr&privacy=1" >/dev/null
chk "informativa riaccettata e registrata" "$(sql "SELECT COUNT(*) FROM consensi WHERE tipo='privacy' AND versione='2'")" 1
chk "dopo l'accettazione il profilo torna" "$(rget account.php | conta '<h1>La mia libreria</h1>')" 1

# account non confermati vecchi: pulizia
sql "INSERT INTO lettori (email,nick,password,stato,creato) VALUES ('vecchio@prova.it','Vecchio1','x',0,'2020-01-01 00:00:00')" >/dev/null
Tk=$(tok admin/lettori.php); post admin/lettori.php -d "csrf=$Tk&azione=pulisci" >/dev/null
chk "pulizia dei non confermati" "$(sql "SELECT COUNT(*) FROM lettori WHERE email='vecchio@prova.it'")" 0

# cancellazione dell'account: sparisce tutto
Tr=$(rtok account.php); rpost 'account.php?a=elimina' -d "csrf=$Tr&password_attuale=sbagliata&conferma=1" >/dev/null
chk "cancella con password sbagliata: resta" "$(sql "SELECT COUNT(*) FROM lettori")" 1
Tr=$(rtok account.php); rpost 'account.php?a=elimina' -d "csrf=$Tr&password_attuale=ancorapiulunga55&conferma=1" >/dev/null
for t in lettori liste progressi segnalibri recensioni consensi token_lettori; do chk "cancellato: $t vuota" "$(sql "SELECT COUNT(*) FROM $t")" 0; done
chk "cancellato: email di conferma" "$(oggetto $(nmail) | conta 'Account cancellato')" 1
chk "dopo la cancellazione non si entra" "$(rget account.php -o /dev/null | conta '<h1>La mia libreria</h1>')" 0
kill $SINK 2>/dev/null; SINK=""

# ---------- nessun errore PHP nel registro del server ----------
# (si ignorano gli avvisi di avvio di PHP, "in Unknown on line 0", come quello di JIT con Xdebug sui runner di GitHub)
ERRPHP=$(grep -iE "PHP (Warning|Fatal|Notice|Deprecated)" "$LOG" | grep -v "in Unknown on line 0")
if [ -n "$ERRPHP" ]; then ko=$((ko+1)); echo "FAIL: errori PHP nel log del server:"; echo "$ERRPHP" | head -5; fi

echo "e2e: OK=$ok KO=$ko"
[ "$ko" -eq 0 ]
