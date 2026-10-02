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
trap 'kill $PID 2>/dev/null; [ -n "$DBNAME" ] && php "$REPO/sito/tests/mysql_db.php" elimina "$DBNAME"; rm -rf "$T"' EXIT
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

# ---------- sito pubblico: miniature, Open Graph, lettore, download, contatori ----------
HOME=$(curl -s "$B/")
chk "miniature nel catalogo (3 libri + 1 in evidenza)" "$(echo "$HOME" | conta 'src="uploads/thumb/400-')" 4
chk "miniatura creata sul disco" "$([ -n "$(ls uploads/thumb 2>/dev/null)" ] && echo si)" si
chk "Open Graph: immagine" "$(curl -s "$B/libro.php?s=il-custode-dell-etna" | conta 'og:image" content="http://127.0.0.1:'$PORT'/uploads/thumb/800-')" 1
chk "Open Graph: tipo book" "$(curl -s "$B/libro.php?s=il-custode-dell-etna" | conta 'og:type" content="book"')" 1
chk "link canonico" "$(curl -s "$B/libro.php?s=il-custode-dell-etna" | conta 'rel="canonical"')" 1
chk "pagina Privacy" "$(code 'pagina.php?s=privacy')" 200
chk "link Privacy nel piede" "$(echo "$HOME" | conta 'pagina.php?s=privacy')" 1
chk "robots.txt" "$(curl -s "$B/robots.txt" | conta 'Disallow: /admin/')" 1
chk "404 con grafica" "$(curl -s "$B/404.php" | conta 'Pagina non trovata')" 1
chk "lettore: usa PDF.js locale" "$(curl -s "$B/leggi.php?s=il-custode-dell-etna" | conta 'assets/vendor/pdf.min.js')" 1
chk "lettore: libro con le pagine che si girano (StPageFlip locale)" "$(curl -s "$B/leggi.php?s=il-custode-dell-etna" | conta 'assets/vendor/page-flip.browser.js')" 1
chk "StPageFlip servito" "$(code assets/vendor/page-flip.browser.js)" 200
chk "lettore: niente CDN" "$(curl -s "$B/leggi.php?s=il-custode-dell-etna" | conta 'cdnjs')" 0
chk "PDF.js servito" "$(code assets/vendor/pdf.worker.min.js)" 200
chk "scarica pdf" "$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{http_code}:%{content_type}' "$B/scarica.php?s=il-custode-dell-etna")" "200:application/pdf"
chk "contatori: le letture da visitatori diversi si sommano" "$(sql "SELECT letture >= 2 FROM libri WHERE slug = 'il-custode-dell-etna'")" 1
chk "contatori: 1 download" "$(sql "SELECT download FROM libri WHERE slug = 'il-custode-dell-etna'")" 1

# ---------- collane e pagine ----------
Tk=$(tok admin/collane.php)
chk "salva collana" "$(post admin/collane.php -F csrf=$Tk -F azione=salva -F id=0 -F nome='Collana Nuova' -F descrizione=Ciao -F visibile=1 -F ordine=5)" 302
chk "collana nel catalogo" "$(curl -s "$B/index.php?collana=il-custode-dell-etna" | conta 'class="scheda"')" 3
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

# ---------- nessun errore PHP nel registro del server ----------
# (si ignorano gli avvisi di avvio di PHP, "in Unknown on line 0", come quello di JIT con Xdebug sui runner di GitHub)
ERRPHP=$(grep -iE "PHP (Warning|Fatal|Notice|Deprecated)" "$LOG" | grep -v "in Unknown on line 0")
if [ -n "$ERRPHP" ]; then ko=$((ko+1)); echo "FAIL: errori PHP nel log del server:"; echo "$ERRPHP" | head -5; fi

echo "e2e: OK=$ok KO=$ko"
[ "$ko" -eq 0 ]
