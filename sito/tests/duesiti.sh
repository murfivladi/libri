#!/bin/bash
# Prova di due siti che condividono lo stesso database MySQL (come vladinc.ru/libri e libri.vladinc.ru):
#   A = sito principale, ospita i file; B = sito secondario, che li usa e li carica tramite l'API (poi anche per via diretta).
# Uso (dalla radice del repository, con un MySQL/MariaDB raggiungibile):
#   E2E_MYSQL_USER=... E2E_MYSQL_PASS=... bash sito/tests/duesiti.sh
set -u
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
T="$(mktemp -d)"
A="$T/principale/sito"; Bd="$T/secondario/sito"
mkdir -p "$T/principale" "$T/secondario"
for d in "$A" "$Bd"; do
  cp -r "$REPO/sito" "$d"
  rm -rf "$d/data/"*.sqlite "$d/uploads/pdf/"*.pdf "$d/uploads/cover/"* "$d/uploads/thumb" "$d/tests"
done
PA=${PORT_A:-8101}; PB=${PORT_B:-8102}
URLA="http://127.0.0.1:$PA"; URLB="http://127.0.0.1:$PB"
DB="libri_2siti_$$"
CFGA="$T/cfgA.php"; CFGB="$T/cfgB.php"
JA="$T/jarA"; JB="$T/jarB"; : > "$JA"; : > "$JB"
ok=0; ko=0

php "$REPO/sito/tests/mysql_db.php" crea "$CFGA" "$DB" sito_principale=true "file_url_base=$URLA" "url_ufficiale=$URLA" scarica_locale=true || { echo "MySQL non raggiungibile"; exit 1; }
php "$REPO/sito/tests/mysql_db.php" config "$CFGB" "$DB" sito_principale=false "file_url_base=$URLA" "url_ufficiale=$URLA" scarica_locale=true

( cd "$A" && LIBRI_CONFIG="$CFGA" LIBRI_REPO="$REPO" php importa.php >/dev/null ) || { echo "importa.php non riuscito"; exit 1; }
( cd "$A" && LIBRI_CONFIG="$CFGA" php -r 'require "inc/bootstrap.php";
  db()->exec("UPDATE libri SET pubblicato = 1");
  db()->prepare("INSERT INTO admin (id, password) VALUES (1, ?)")->execute([password_hash("provaprova12", PASSWORD_DEFAULT)]);' )

( cd "$A"  && LIBRI_CONFIG="$CFGA" exec php -d post_max_size=100M -d upload_max_filesize=100M -S 127.0.0.1:$PA >"$T/serverA.log" 2>&1 ) & PIDA=$!
( cd "$Bd" && LIBRI_CONFIG="$CFGB" exec php -d post_max_size=100M -d upload_max_filesize=100M -S 127.0.0.1:$PB >"$T/serverB.log" 2>&1 ) & PIDB=$!
trap 'kill $PIDA $PIDB 2>/dev/null; pkill -f "php -S 127.0.0.1:$PA" 2>/dev/null; pkill -f "php -S 127.0.0.1:$PB" 2>/dev/null; php "$REPO/sito/tests/mysql_db.php" elimina "$DB"; rm -rf "$T"' EXIT
for i in $(seq 1 40); do curl -s -o /dev/null "$URLA/robots.txt" && curl -s -o /dev/null "$URLB/robots.txt" && break; sleep 0.2; done

chk()   { if [ "$2" = "$3" ]; then ok=$((ok+1)); else ko=$((ko+1)); echo "FAIL: $1 (atteso '$3', ottenuto '$2')"; fi; }
conta() { grep -o "$1" | wc -l | tr -d ' '; }
contai() { grep -io "$1" | wc -l | tr -d ' '; }
sql()   { ( cd "$A" && LIBRI_CONFIG="$CFGA" php -r 'require "inc/bootstrap.php"; echo db()->query($argv[1])->fetchColumn();' "$1" ); }
tokA()  { curl -s -b "$JA" -c "$JA" "$URLA/$1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | cut -d'"' -f4; }
tokB()  { curl -s -b "$JB" -c "$JB" "$URLB/$1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | cut -d'"' -f4; }
postA() { local p=$1; shift; curl -s -b "$JA" -c "$JA" -o /dev/null -w "%{http_code}" "$@" "$URLA/$p"; }
postB() { local p=$1; shift; curl -s -b "$JB" -c "$JB" -o /dev/null -w "%{http_code}" "$@" "$URLB/$p"; }
codeA() { curl -s -b "$JA" -c "$JA" -o "$T/outA.html" -w "%{http_code}" "$URLA/$1"; }
codeB() { curl -s -b "$JB" -c "$JB" -o "$T/outB.html" -w "%{http_code}" "$URLB/$1"; }

# ---------- stessi dati, indirizzi interi ----------
COP=$(sql "SELECT copertina FROM libri WHERE slug = 'il-custode-dell-etna'")
PDF=$(sql "SELECT pdf FROM libri WHERE slug = 'il-custode-dell-etna'")
chk "database: copertina = indirizzo intero del principale" "$(echo "$COP" | conta "^$URLA/uploads/cover/[0-9a-f]*\.jpg$")" 1
chk "database: pdf = indirizzo intero del principale" "$(echo "$PDF" | conta "^$URLA/uploads/pdf/[0-9a-f]*\.pdf$")" 1
chk "il file sta sul principale (HTTP)" "$(curl -s -o /dev/null -w '%{http_code}' "$COP")" 200

HB=$(curl -s "$URLB/")
HA=$(curl -s "$URLA/")
chk "B: 5 libri nel catalogo" "$(echo "$HB" | conta 'class="scheda"')" 5
chk "A: 5 libri nel catalogo" "$(echo "$HA" | conta 'class="scheda"')" 5
chk "B: miniature servite dal principale (img.php)" "$(echo "$HB" | conta "src=\"$URLA/img.php?t=cover&amp;f=")" 5
chk "A: miniature statiche sul principale" "$(echo "$HA" | conta "src=\"$URLA/uploads/thumb/400-")" 5
IMG=$(echo "$HB" | grep -o "src=\"$URLA/img.php?[^\"]*" | head -1 | sed 's/^src="//; s/&amp;/\&/g')
chk "img.php risponde con un'immagine" "$(curl -s -o /dev/null -w '%{http_code}:%{content_type}' "$IMG")" "200:image/jpeg"
chk "img.php: cache lunga" "$(curl -sI "$IMG" | tr -d '\r' | contai 'cache-control: public, max-age=31536000, immutable')" 1
chk "img.php: larghezza non ammessa" "$(curl -s -o /dev/null -w '%{http_code}' "$URLA/img.php?t=cover&f=x.jpg&w=777")" 404

# ---------- indirizzo ufficiale ----------
LB=$(curl -s "$URLB/libro.php?s=il-custode-dell-etna")
chk "B: canonical = indirizzo ufficiale" "$(echo "$LB" | conta "rel=\"canonical\" href=\"$URLA/libro.php?s=il-custode-dell-etna\"")" 1
chk "B: og:url = indirizzo ufficiale" "$(echo "$LB" | conta "og:url\" content=\"$URLA/libro.php")" 1
chk "B: og:image assoluta" "$(echo "$LB" | conta "og:image\" content=\"$URLA/")" 1
chk "B: sitemap con indirizzo ufficiale" "$(curl -s "$URLB/sitemap.php" | conta "<loc>$URLA/libro.php")" 5
chk "B: feed con indirizzo ufficiale" "$(curl -s "$URLB/feed.php" | conta "<link>$URLA/libro.php")" 5

# ---------- lettore e download dal secondario ----------
LEGGI=$(curl -s "$URLB/leggi.php?s=il-custode-dell-etna")
PDFURL=$(echo "$LEGGI" | grep -o 'data-pdf="[^"]*"' | sed 's/data-pdf="//; s/"$//; s/&amp;/\&/g')
chk "B: il lettore usa pdf.php del principale" "$(echo "$PDFURL" | conta "^$URLA/pdf.php?f=[0-9a-f]*\.pdf$")" 1
chk "pdf.php: Range -> 206" "$(curl -s -o "$T/r.bin" -r 0-99 -w '%{http_code}' "$PDFURL")" 206
chk "pdf.php: inizia con %PDF" "$(head -c 4 "$T/r.bin")" "%PDF"
chk "pdf.php: Content-Range" "$(curl -sI -r 0-99 "$PDFURL" | tr -d '\r' | contai '^content-range: bytes 0-99/')" 1
chk "pdf.php: CORS aperto" "$(curl -sI -H "Origin: $URLB" "$PDFURL" | tr -d '\r' | contai '^access-control-allow-origin: \*')" 1
chk "pdf.php: ultimi byte" "$(curl -s -o /dev/null -r -50 -w '%{http_code}' "$PDFURL")" 206
chk "pdf.php: Range fuori misura -> 416" "$(curl -s -o /dev/null -r 999999999-999999999 -w '%{http_code}' "$PDFURL")" 416
chk "pdf.php: nome non valido" "$(curl -s -o /dev/null -w '%{http_code}' "$URLA/pdf.php?f=../data/config.php")" 404
DIM=$(stat -c %s "$A/uploads/pdf/$(basename "$PDF")")
chk "B: scarica.php: PDF intero (copiato dal principale)" "$(curl -s -o "$T/d.pdf" -w '%{http_code}:%{content_type}:%{size_download}' "$URLB/scarica.php?s=il-custode-dell-etna")" "200:application/pdf:$DIM"
chk "B: scarica.php: il file è identico" "$(cmp -s "$T/d.pdf" "$A/uploads/pdf/$(basename "$PDF")" && echo si)" si
chk "contatore download condiviso" "$(sql "SELECT download FROM libri WHERE slug = 'il-custode-dell-etna'")" 1

# ---------- admin: stessa password sui due siti, stessi dati ----------
Tb=$(tokB admin/login.php)
chk "B: login con la password condivisa" "$(postB admin/login.php -d "csrf=$Tb&password=provaprova12")" 302
Ta=$(tokA admin/login.php)
chk "A: login con la stessa password" "$(postA admin/login.php -d "csrf=$Ta&password=provaprova12")" 302
for pg in index.php libri.php "libro.php?id=1" collane.php pagine.php media.php strumenti.php database.php aspetto.php sicurezza.php; do
  chk "B admin/$pg" "$(codeB "admin/$pg")" 200
  if grep -qE "Warning|Notice|Fatal error|Deprecated" "$T/outB.html"; then ko=$((ko+1)); echo "FAIL: errori PHP in B admin/$pg"; fi
done
chk "B: media.php rimanda al principale" "$(codeB admin/media.php >/dev/null; conta "admin/media.php ↗" < "$T/outB.html")" 1
chk "B: strumenti indica la via API" "$(codeB admin/strumenti.php >/dev/null; conta "tramite l&#039;API del sito principale" < "$T/outB.html")" 1
chk "B: strumenti: nessun file mancante" "$(conta 'Tutti i file di PDF e copertine esistono' < "$T/outB.html")" 1

# modifica da B -> visibile su A
Tb=$(tokB "admin/libro.php?id=1")
chk "B: salva libro (solo testo)" "$(postB "admin/libro.php?id=1" -F csrf=$Tb -F titolo='Titolo scritto da B' -F slug=il-custode-dell-etna -F stato=pubblicato -F dopo=resta)" 302
chk "A vede il titolo scritto da B" "$(curl -s "$URLA/libro.php?s=il-custode-dell-etna" | conta 'Titolo scritto da B' | awk '$1>0{print "si"}')" si
chk "il file di copertina non è cambiato" "$(sql "SELECT copertina FROM libri WHERE slug = 'il-custode-dell-etna'")" "$COP"

# impostazione da A -> visibile su B
Ta=$(tokA admin/impostazioni.php)
postA admin/impostazioni.php -d "csrf=$Ta&nome_sito=Editore+Condiviso&slogan=Slogan&sottoslogan=x&footer=f&email=&autore_predefinito=Vlad&per_pagina=12&ordine_catalogo=numero&mostra_evidenza=1" >/dev/null
chk "B vede il nome del sito scritto da A" "$(curl -s "$URLB/" | conta 'EDITORE CONDIVISO')" 1

# ---------- upload dal secondario tramite API ----------
Tb=$(tokB "admin/libro.php?id=1")
chk "B: carica copertina e PDF (via API)" "$(postB "admin/libro.php?id=1" -F csrf=$Tb -F titolo='Titolo scritto da B' -F slug=il-custode-dell-etna -F stato=pubblicato -F copertina=@"$A/assets/emblema.png" -F pdf=@"$REPO/libro-02/build/il-custode-e-il-fantasma.pdf" -F pagine_n=0 -F dopo=resta)" 302
NCOP=$(sql "SELECT copertina FROM libri WHERE slug = 'il-custode-dell-etna'")
NPDF=$(sql "SELECT pdf FROM libri WHERE slug = 'il-custode-dell-etna'")
chk "nuova copertina: indirizzo intero del principale" "$(echo "$NCOP" | conta "^$URLA/uploads/cover/[0-9a-f]*\.png$")" 1
chk "nuova copertina: il file è sul disco del principale" "$([ -f "$A/uploads/cover/$(basename "$NCOP")" ] && echo si)" si
chk "nuova copertina: non è sul disco del secondario" "$([ ! -f "$Bd/uploads/cover/$(basename "$NCOP")" ] && echo si)" si
chk "nuova copertina: raggiungibile" "$(curl -s -o /dev/null -w '%{http_code}:%{content_type}' "$NCOP")" "200:image/png"
chk "nuovo PDF: sul disco del principale" "$([ -f "$A/uploads/pdf/$(basename "$NPDF")" ] && echo si)" si
chk "nuovo PDF: pagine contate dal principale" "$(sql "SELECT pagine_n FROM libri WHERE slug = 'il-custode-dell-etna'" | awk '$1>50{print "si"}')" si
chk "vecchia copertina cancellata dal principale" "$(curl -s -o /dev/null -w '%{http_code}' "$COP")" 404
chk "vecchio PDF cancellato dal principale" "$(curl -s -o /dev/null -w '%{http_code}' "$PDF")" 404
chk "A: la nuova copertina è nel catalogo (già piccola: file originale)" "$(curl -s "$URLA/" | conta "$NCOP")" 1

# togliere la copertina da B cancella il file sul principale
Tb=$(tokB "admin/libro.php?id=1")
postB "admin/libro.php?id=1" -F csrf=$Tb -F titolo='Titolo scritto da B' -F slug=il-custode-dell-etna -F stato=pubblicato -F togli_copertina=1 -F dopo=resta >/dev/null
chk "togli copertina: file cancellato sul principale" "$(curl -s -o /dev/null -w '%{http_code}' "$NCOP")" 404

# caricamento da URL sul secondario (scarica dal principale e lo rimanda al principale)
Tb=$(tokB "admin/libro.php?id=1")
chk "B: copertina da URL (via API)" "$(postB "admin/libro.php?id=1" -F csrf=$Tb -F titolo='Titolo scritto da B' -F slug=il-custode-dell-etna -F stato=pubblicato -F "copertina_url=$URLA/assets/emblema.png" -F dopo=resta)" 302
chk "copertina da URL: salvata sul principale" "$(sql "SELECT copertina FROM libri WHERE slug = 'il-custode-dell-etna'" | conta "^$URLA/uploads/cover/")" 1

# ---------- sicurezza dell'API ----------
chk "API: GET rifiutata" "$(curl -s -o /dev/null -w '%{http_code}' "$URLA/api.php")" 405
chk "API: firma sbagliata" "$(curl -s -o /dev/null -w '%{http_code}' -H "X-Libri-Ts: $(date +%s)" -H 'X-Libri-Firma: 00' -d azione=ping "$URLA/api.php")" 401
chk "API: richiesta scaduta" "$(curl -s -o /dev/null -w '%{http_code}' -H "X-Libri-Ts: 1000" -H 'X-Libri-Firma: 00' -d azione=ping "$URLA/api.php")" 401
chk "API: carica senza firma valida" "$(curl -s -o /dev/null -w '%{http_code}' -H "X-Libri-Ts: $(date +%s)" -H 'X-Libri-Firma: 00' -F azione=carica -F cartella=cover -F file=@"$A/assets/emblema.png" "$URLA/api.php")" 401
chk "API: elimina senza firma valida" "$(curl -s -o /dev/null -w '%{http_code}' -H "X-Libri-Ts: $(date +%s)" -H 'X-Libri-Firma: 00' -d "azione=elimina&cartella=cover&nome=$(basename "$(sql "SELECT copertina FROM libri WHERE slug = 'il-custode-dell-etna'")")" "$URLA/api.php")" 401
chk "API: il file non è stato cancellato" "$(curl -s -o /dev/null -w '%{http_code}' "$(sql "SELECT copertina FROM libri WHERE slug = 'il-custode-dell-etna'")")" 200
chk "il secondario non ospita l'API" "$(curl -s -o /dev/null -w '%{http_code}' -d azione=ping "$URLB/api.php")" 403
chk "il secondario non serve pdf.php" "$(curl -s -o /dev/null -w '%{http_code}' "$URLB/pdf.php?f=x.pdf")" 404
Tb=$(tokB admin/database.php)
chk "B: prova connessione dalla procedura guidata" "$(curl -s -b "$JB" -c "$JB" -d "csrf=$Tb&azione=prova_file" "$URLB/admin/database.php" | conta 'API del sito principale raggiungibile')" 1

# ---------- scrittura diretta nella cartella del principale (stesso hosting) ----------
php "$REPO/sito/tests/mysql_db.php" config "$CFGB" "$DB" sito_principale=false "file_url_base=$URLA" "url_ufficiale=$URLA" "uploads_dir_primario=$A/uploads" scarica_locale=true
sleep 3     # opcache controlla i file modificati ogni 2 secondi: aspetto che il server legga la nuova configurazione
Tb=$(tokB "admin/libro.php?id=1")
chk "B (diretto): carica una copertina" "$(postB "admin/libro.php?id=1" -F csrf=$Tb -F titolo='Titolo scritto da B' -F slug=il-custode-dell-etna -F stato=pubblicato -F copertina=@"$A/assets/emblema.png" -F dopo=resta)" 302
DCOP=$(sql "SELECT copertina FROM libri WHERE slug = 'il-custode-dell-etna'")
chk "diretto: indirizzo intero" "$(echo "$DCOP" | conta "^$URLA/uploads/cover/[0-9a-f]*\.png$")" 1
chk "diretto: file sul disco del principale" "$([ -f "$A/uploads/cover/$(basename "$DCOP")" ] && echo si)" si
chk "diretto: miniature create da B senza passare da img.php (4 grandi + 1 già piccola)" "$(curl -s "$URLB/" | conta "src=\"$URLA/uploads/thumb/400-")" 4
chk "B (diretto): media.php elenca i file" "$(codeB admin/media.php >/dev/null; conta 'File orfani' < "$T/outB.html")" 1
chk "B (diretto): strumenti: salvataggio diretto" "$(codeB admin/strumenti.php >/dev/null; conta "diretto in $A/uploads" < "$T/outB.html")" 1
Tb=$(tokB admin/libri.php); LID=$(sql "SELECT id FROM libri WHERE slug = 'il-custode-dell-etna'")
postB admin/libri.php -d "csrf=$Tb&riga=$LID:cestino" >/dev/null
Tb=$(tokB "admin/libri.php?stato=cestino"); postB "admin/libri.php?stato=cestino" -d "csrf=$Tb&riga=$LID:elimina_def" >/dev/null
chk "B (diretto): elimina libro -> file cancellati sul principale" "$(curl -s -o /dev/null -w '%{http_code}' "$DCOP")" 404
chk "B (diretto): anche il PDF" "$(curl -s -o /dev/null -w '%{http_code}' "$NPDF")" 404

# ---------- procedura guidata: un terzo sito appena caricato (SQLite) viene collegato al database condiviso ----------
C="$T/terzo/sito"; mkdir -p "$T/terzo"; cp -r "$REPO/sito" "$C"
rm -rf "$C/data/"*.sqlite "$C/data/config.php" "$C/uploads/pdf/"*.pdf "$C/uploads/cover/"* "$C/uploads/thumb" "$C/tests"
PC=${PORT_C:-8103}; URLC="http://127.0.0.1:$PC"; JC="$T/jarC"; : > "$JC"
( cd "$C" && exec php -d post_max_size=100M -d upload_max_filesize=100M -S 127.0.0.1:$PC >"$T/serverC.log" 2>&1 ) & PIDC=$!
for i in $(seq 1 40); do curl -s -o /dev/null "$URLC/robots.txt" && break; sleep 0.2; done
tokC()  { curl -s -b "$JC" -c "$JC" "$URLC/$1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | cut -d'"' -f4; }
postC() { local p=$1; shift; curl -s -b "$JC" -c "$JC" -o /dev/null -w "%{http_code}" "$@" "$URLC/$p"; }
MY_HOST=${E2E_MYSQL_HOST:-127.0.0.1}; MY_PORT=${E2E_MYSQL_PORT:-3306}; MY_USER=${E2E_MYSQL_USER:-root}; MY_PASS=${E2E_MYSQL_PASS:-}
campi="db_host=$MY_HOST&db_porta=$MY_PORT&db_nome=$DB&db_utente=$MY_USER&ruolo=secondario&file_url_base=$URLA&url_ufficiale=$URLA"
chk "C: all'inizio usa SQLite e mostra «Crea la password»" "$(curl -s "$URLC/admin/login.php" | conta 'Crea la password admin')" 1
chk "C: la procedura guidata si apre prima di creare la password" "$(curl -s -o "$T/outC.html" -w '%{http_code}' "$URLC/admin/database.php")" 200
chk "C: la pagina spiega cosa fare" "$(conta 'Il database MySQL' < "$T/outC.html")" 1
Tc=$(tokC admin/database.php)
chk "C: password del database sbagliata -> messaggio chiaro" "$(curl -s -b "$JC" -c "$JC" --data-urlencode "db_password=sbagliata" -d "csrf=$Tc&azione=prova&$campi" "$URLC/admin/database.php" | conta 'Utente o password sbagliati')" 1
Tc=$(tokC admin/database.php)
chk "C: host irraggiungibile -> messaggio chiaro" "$(curl -s -b "$JC" -c "$JC" --data-urlencode "db_password=$MY_PASS" -d "csrf=$Tc&azione=prova&db_host=127.0.0.1&db_porta=1&db_nome=$DB&db_utente=$MY_USER&ruolo=secondario&file_url_base=$URLA" "$URLC/admin/database.php" | conta 'Il VPS non risponde')" 1
Tc=$(tokC admin/database.php)
chk "C: manca l'indirizzo dei file per un secondario" "$(curl -s -b "$JC" -c "$JC" --data-urlencode "db_password=$MY_PASS" -d "csrf=$Tc&azione=prova&db_host=$MY_HOST&db_porta=$MY_PORT&db_nome=$DB&db_utente=$MY_USER&ruolo=secondario" "$URLC/admin/database.php" | conta 'deve sapere dove stanno i file')" 1
Tc=$(tokC admin/database.php)
RIS=$(curl -s -b "$JC" -c "$JC" --data-urlencode "db_password=$MY_PASS" -d "csrf=$Tc&azione=prova&$campi" "$URLC/admin/database.php")
chk "C: la prova della connessione riesce" "$(echo "$RIS" | conta 'Connessione riuscita')" 1
chk "C: la prova vede la password admin già presente" "$(echo "$RIS" | conta "c&#039;è già una password admin")" 1
Tc=$(tokC admin/database.php)
RIS=$(curl -s -b "$JC" -c "$JC" --data-urlencode "db_password=$MY_PASS" -d "csrf=$Tc&azione=prova&db_tls=1&$campi" "$URLC/admin/database.php")
chk "C: connessione cifrata (TLS): riesce e mostra il cifrario" "$(echo "$RIS" | conta 'Connessione riuscita[^<]*cifrata (')" 1
chk "C: senza il segno CSRF niente" "$(postC admin/database.php -d "azione=salva&$campi")" 400
Tc=$(tokC admin/database.php)
chk "C: salva e collega -> torna al login" "$(curl -s -b "$JC" -c "$JC" -o /dev/null -w '%{http_code}:%{redirect_url}' --data-urlencode "db_password=$MY_PASS" -d "csrf=$Tc&azione=salva&$campi" "$URLC/admin/database.php")" "302:$URLC/admin/login.php"
chk "C: data/config.php scritto" "$([ -f "$C/data/config.php" ] && echo si)" si
chk "C: la configurazione dice MySQL e secondario" "$(grep -c "'db_driver' => 'mysql'" "$C/data/config.php")$(grep -c "'sito_principale' => false" "$C/data/config.php")" 11
chk "C: login: ora chiede la password (quella condivisa), non di crearla" "$(curl -s -b "$JC" -c "$JC" "$URLC/admin/login.php" | conta 'Crea la password admin')" 0
chk "C: la procedura guidata ora richiede il login" "$(curl -s -o /dev/null -w '%{http_code}' "$URLC/admin/database.php")" 302
Tc=$(tokC admin/login.php)
chk "C: accede con la password del database condiviso" "$(postC admin/login.php -d "csrf=$Tc&password=provaprova12")" 302
chk "C: vede gli stessi libri" "$(curl -s -b "$JC" "$URLC/admin/libri.php" | conta 'name="ids\[\]"')" "$(sql "SELECT COUNT(*) FROM libri WHERE eliminato IS NULL")"
chk "C: il catalogo pubblico usa le miniature del principale" "$(curl -s "$URLC/" | conta "src=\"$URLA/img.php?t=cover&amp;f=")" 4
chk "C: il file di configurazione non è leggibile dal web (cartella data/)" "$(grep -c "Require all denied" "$C/data/.htaccess")" 1
Tc=$(tokC admin/database.php)
chk "C: «Torna a SQLite» cancella la configurazione" "$(curl -s -b "$JC" -c "$JC" -o /dev/null -w '%{http_code}' -d "csrf=$Tc&azione=sqlite" "$URLC/admin/database.php")" 302
chk "C: config.php rimosso" "$([ ! -f "$C/data/config.php" ] && echo si)" si
kill $PIDC 2>/dev/null

# ---------- nel browser vero: il secondario apre PDF e copertine che stanno sul principale ----------
if [ -n "${CON_BROWSER:-}" ]; then
  # prima una copertina e un PDF "originali" nel libro 1 (l'API li ha cancellati nei test sopra)
  Tb=$(tokB "admin/libro.php?id=$(sql "SELECT id FROM libri ORDER BY id LIMIT 1")")
  postB "admin/libro.php?id=$(sql "SELECT id FROM libri ORDER BY id LIMIT 1")" -F csrf=$Tb -F titolo='Libro per il browser' -F slug=libro-browser -F stato=pubblicato -F copertina=@"$A/assets/hero.jpg" -F pdf=@"$REPO/libro-01/build/il-custode-dell-etna.pdf" >/dev/null
  export NODE_PATH="${NODE_PATH:-$(npm root -g 2>/dev/null)}"
  RB=$(BASE_B="$URLB" SLUG=libro-browser node "$REPO/sito/tests/browser-duesiti.js" 2>&1); echo "$RB" | tail -6
  echo "$RB" | grep -q 'KO=0' && ok=$((ok+1)) || { ko=$((ko+1)); echo "FAIL: test nel browser (due siti)"; }
fi

# ---------- nessun errore PHP nei registri dei due server ----------
for s in A B; do
  ERRPHP=$(grep -iE "PHP (Warning|Fatal|Notice|Deprecated)" "$T/server$s.log" | grep -v "in Unknown on line 0")   # niente avvisi di avvio (JIT/Xdebug sui runner)
  if [ -n "$ERRPHP" ]; then ko=$((ko+1)); echo "FAIL: errori PHP nel registro del server $s:"; echo "$ERRPHP" | head -5; fi
done

echo "due siti: OK=$ok KO=$ko"
[ "$ko" -eq 0 ]
