#!/bin/bash
# Test della sezione Scuola: libro scolastico, esercizi, contenuti, zone, pagine pubbliche (curl) e poi nel browser (Playwright).
# Uso (dalla radice del repository):  bash sito/tests/scuola.sh      (SENZA_BROWSER=1 per saltare la parte nel browser)
set -u
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
T="$(mktemp -d)"
S="$T/sito"
cp -r "$REPO/sito" "$S"
rm -rf "$S/data/"*.sqlite "$S/uploads/pdf/"*.pdf "$S/uploads/cover/"* "$S/uploads/media/"* "$S/uploads/thumb" "$S/tests"
PORT=${PORT:-8097}
B="http://127.0.0.1:$PORT"
J="$T/cookie"; touch "$J"
ok=0; ko=0
cd "$S" || exit 1
python3 "$REPO/sito/tests/pdf_scuola.py" "$T/libro.pdf" "$T/voce.wav" || { echo "serve python3 con reportlab"; exit 1; }
php -r 'require "inc/bootstrap.php"; db()->prepare("INSERT INTO admin (id, password) VALUES (1, ?)")->execute([password_hash("provaprova12", PASSWORD_DEFAULT)]);'
php -d post_max_size=100M -d upload_max_filesize=100M -S 127.0.0.1:$PORT >"$T/server.log" 2>&1 &
PID=$!
trap 'kill $PID 2>/dev/null; rm -rf "$T"' EXIT
for i in $(seq 1 30); do curl -s -o /dev/null "$B/robots.txt" && break; sleep 0.2; done

chk()  { if [ "$2" = "$3" ]; then ok=$((ok+1)); else ko=$((ko+1)); echo "FAIL: $1 (atteso '$3', ottenuto '$2')"; fi; }
tok()  { curl -s -b "$J" -c "$J" "$B/$1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | cut -d'"' -f4; }
post() { local p=$1; shift; curl -s -b "$J" -c "$J" -o "$T/post.html" -w "%{http_code}" "$@" "$B/$p"; }
code() { curl -s -b "$J" -c "$J" -o "$T/out.html" -w "%{http_code}" "$B/$1"; }
conta() { grep -o "$1" | wc -l | tr -d ' '; }
sql()  { php -r 'require "inc/bootstrap.php"; echo db()->query($argv[1])->fetchColumn();' "$1"; }
senza_errori() { if grep -qE "Warning|Notice|Fatal error|Deprecated|Parse error" "$T/out.html"; then ko=$((ko+1)); echo "FAIL: errori PHP in $1"; else ok=$((ok+1)); fi; }

Tk=$(tok admin/login.php)
chk "accesso admin" "$(post admin/login.php -d "csrf=$Tk&password=provaprova12")" 302

# ---------- pagine admin della scuola ----------
for pg in scuola.php esercizi.php "esercizi.php?nuovo=1" contenuti.php "contenuti.php?nuovo=1" "libro.php?sezione=scuola" lavagna.php "lavagna.php?nuova=1"; do
  chk "admin/$pg" "$(code "admin/$pg")" 200; senza_errori "admin/$pg"
done
chk "scuola.php pubblica: 404 senza libri scolastici" "$(code scuola.php)" 404

# ---------- libro scolastico ----------
Tk=$(tok "admin/libro.php?sezione=scuola")
chk "nuovo libro scolastico" "$(post admin/libro.php -F csrf=$Tk -F "titolo=Chi fa cosa?" -F sezione=scuola -F stato=pubblicato -F "pdf=@$T/libro.pdf;type=application/pdf" -F dopo=resta)" 302
LID=$(sql "SELECT id FROM libri WHERE titolo = 'Chi fa cosa?'")
chk "sezione salvata" "$(sql "SELECT sezione FROM libri WHERE id = $LID")" scuola
chk "codice proposto dalla sigla del titolo" "$(php -r 'require "inc/scuola.php"; echo nuovo_codice((int)$argv[1]);' "$LID")" CFC-1

# ---------- esercizi ----------
TOCCA='{"categorie":[{"nome":"soggetto","colore":"#2e7d32"},{"nome":"predicato","colore":"#c62828"}],"frasi":[{"testo":"Luce mangia la pappa.","segni":{"0":"soggetto","1":"predicato"},"spiegazione":"Chi mangia? Luce."}]}'
Tk=$(tok "admin/esercizi.php?nuovo=1")
chk "salva esercizio «tocca»" "$(post admin/esercizi.php --data-urlencode "csrf=$Tk" -d libro_id=$LID -d pagina=2 -d codice= --data-urlencode "titolo=Trova il soggetto" --data-urlencode "consegna=Tocca soggetto e predicato." -d tipo=tocca --data-urlencode "dati=$TOCCA")" 302
chk "codice automatico CFC-1" "$(sql "SELECT codice FROM esercizi WHERE titolo = 'Trova il soggetto'")" CFC-1
chk "punti dell'esercizio" "$(php -r 'require "inc/scuola.php"; $st = db()->query("SELECT * FROM esercizi WHERE codice = \"CFC-1\""); echo punti_esercizio($st->fetch());')" 2
Tk=$(tok "admin/esercizi.php?nuovo=1")
post admin/esercizi.php --data-urlencode "csrf=$Tk" -d libro_id=$LID -d codice=CFC-1 -d tipo=vf --data-urlencode 'dati={"domande":[{"testo":"Prova","vero":true}]}' >/dev/null
chk "codice doppio rifiutato" "$(grep -c 'è già usato' "$T/post.html")" 1
Tk=$(tok "admin/esercizi.php?nuovo=1")
post admin/esercizi.php --data-urlencode "csrf=$Tk" -d libro_id=$LID -d tipo=scelta --data-urlencode 'dati={"domande":[{"testo":"Senza risposte","opzioni":["solo una"]}]}' >/dev/null
chk "esercizio vuoto rifiutato" "$(grep -c 'esercizio è vuoto' "$T/post.html")" 1
Tk=$(tok "admin/esercizi.php?nuovo=1")
post admin/esercizi.php --data-urlencode "csrf=$Tk" -d libro_id=$LID -d codice=CFC-9 -d extra=1 --data-urlencode "titolo=<b>Riordina</b>" -d tipo=ordina --data-urlencode 'dati={"frasi":[{"testo":"Vlad spiega la grammatica","spiegazione":"<script>x</script>"}],"intruso":1}' >/dev/null
chk "esercizio «ordina» extra" "$(sql "SELECT extra FROM esercizi WHERE codice = 'CFC-9'")" 1
chk "campi sconosciuti scartati" "$(sql "SELECT dati FROM esercizi WHERE codice = 'CFC-9'" | conta intruso)" 0
ES9=$(sql "SELECT id FROM esercizi WHERE codice = 'CFC-9'")
chk "editor di un esercizio" "$(code "admin/esercizi.php?id=$ES9")" 200; senza_errori "admin/esercizi.php?id=$ES9"
Tk=$(tok "admin/esercizi.php?id=$ES9")
chk "duplica esercizio" "$(post admin/esercizi.php -d "csrf=$Tk&azione=duplica&id=$ES9")" 302
chk "il duplicato ha un codice nuovo" "$(sql "SELECT COUNT(*) FROM esercizi WHERE titolo LIKE '%(copia)' AND codice != 'CFC-9'")" 1
DUP=$(sql "SELECT id FROM esercizi WHERE titolo LIKE '%(copia)'")
Tk=$(tok "admin/esercizi.php?id=$DUP")
chk "elimina il duplicato" "$(post admin/esercizi.php -d "csrf=$Tk&azione=elimina&id=$DUP")" 302
chk "elenco esercizi" "$(curl -s -b "$J" "$B/admin/esercizi.php?libro=$LID" | conta 'data-qr=')" 2

# ---------- contenuti (audio caricato) ----------
Tk=$(tok "admin/contenuti.php?nuovo=1")
chk "carica un audio" "$(post admin/contenuti.php -F csrf=$Tk -F libro_id=$LID -F pagina=3 -F codice=CFC-2 -F "titolo=Ascolta Luce" -F fonte=file -F "file=@$T/voce.wav;type=audio/wav" -F "testo=Ciao, sono Luce." -F azione=salva)" 302
chk "contenuto audio salvato" "$(sql "SELECT tipo FROM contenuti WHERE codice = 'CFC-2'")" audio
F=$(sql "SELECT file FROM contenuti WHERE codice = 'CFC-2'")
chk "file in uploads/media" "$([ -f "uploads/media/$F" ] && echo si)" si
Tk=$(tok "admin/contenuti.php?nuovo=1")
post admin/contenuti.php -F csrf=$Tk -F libro_id=$LID -F codice=CFC-3 -F fonte=file -F azione=salva >/dev/null
chk "contenuto senza file rifiutato" "$(grep -c 'class="errore">Carica un file' "$T/post.html")" 1
chk "voce AI senza chiave: avviso" "$(curl -s -b "$J" "$B/admin/contenuti.php?nuovo=1" | conta 'serve la chiave deAPI')" 1

# ---------- zone ----------
chk "pagina zone" "$(code "admin/zone.php?libro=$LID")" 200; senza_errori "admin/zone.php"
Tk=$(tok "admin/zone.php?libro=$LID")
chk "salva zone" "$(post "admin/zone.php?libro=$LID" --data-urlencode "csrf=$Tk" --data-urlencode 'zone=[{"p":3,"x":100,"y":200,"w":300,"h":80,"c":"CFC-2"},{"p":1,"x":0,"y":0,"w":50,"h":50,"c":"NON-1"},{"p":2,"x":10,"y":10,"w":2,"h":2,"c":"CFC-1"}]' -d pagina=3)" 302
chk "solo le zone valide" "$(sql "SELECT COUNT(*) FROM zone WHERE libro_id = $LID")" 1

# ---------- sito pubblico ----------
chk "scuola.php" "$(code scuola.php)" 200; senza_errori scuola.php
chk "scuola: il libro c'è" "$(conta 'Chi fa cosa?' < "$T/out.html" | awk '{print ($1>0)?"si":"no"}')" si
chk "menu: voce Scuola" "$(curl -s "$B/index.php" | conta 'href="scuola.php"')" 1
chk "catalogo dei romanzi: niente libri scolastici" "$(curl -s "$B/index.php" | conta 'Chi fa cosa')" 0
SLUG=$(sql "SELECT slug FROM libri WHERE id = $LID")
code "libro.php?s=$SLUG" >/dev/null
chk "scheda: esercizi elencati" "$(conta 'apri.php?c=CFC-' < "$T/out.html")" 3
chk "apri.php esercizio" "$(code "apri.php?c=cfc-1")" 200; senza_errori apri.php
chk "apri.php: dati dell'esercizio" "$(conta 'VSEsercizio.monta' < "$T/out.html")" 1
chk "apri.php: titolo senza HTML" "$(curl -s "$B/apri.php?c=CFC-9" | conta '<b>Riordina')" 0
chk "apri.php: niente script dai dati" "$(curl -s "$B/apri.php?c=CFC-9" | conta '<script>x')" 0
chk "apri.php incorporato" "$(curl -s "$B/apri.php?c=CFC-2&embed=1" | conta '<audio controls')" 1
chk "apri.php codice inesistente: 404" "$(code "apri.php?c=ZZZ-99")" 404
chk "lettore: mappa dei codici" "$(curl -s "$B/leggi.php?s=$SLUG" | conta 'window.SCUOLA')" 1
# libro in bozza: esercizi invisibili al pubblico, visibili all'admin
php -r 'require "inc/bootstrap.php"; db()->exec("UPDATE libri SET pubblicato = 0 WHERE sezione = \"scuola\"");'
chk "bozza: esercizio nascosto al pubblico" "$(curl -s -o /dev/null -w '%{http_code}' "$B/apri.php?c=CFC-1")" 404
chk "bozza: l'admin lo vede" "$(code "apri.php?c=CFC-1")" 200
chk "bozza: niente voce Scuola nel menu" "$(curl -s "$B/index.php" | conta 'href="scuola.php"')" 0
php -r 'require "inc/bootstrap.php"; db()->exec("UPDATE libri SET pubblicato = 1 WHERE sezione = \"scuola\"");'

# ---------- classi: prof, studenti, compiti, risultati ----------
JP="$T/cookie-prof"; JS="$T/cookie-stud"; JX="$T/cookie-altro"; touch "$JP" "$JS" "$JX"
tokj() { curl -s -b "$1" -c "$1" "$B/$2" | grep -o 'name="csrf" value="[^"]*"' | head -1 | cut -d'"' -f4; }
postj() { local j=$1 p=$2; shift 2; curl -s -b "$j" -c "$j" -o "$T/post.html" -w "%{http_code}" "$@" "$B/$p"; }
chk "prof.php senza codice: niente registrazione" "$(curl -s "$B/prof.php" | conta 'a=registrati')" 0
Tk=$(tok admin/scuola.php)
post admin/scuola.php -d "csrf=$Tk&azione=codice_prof" >/dev/null
CP=$(sql "SELECT valore FROM impostazioni WHERE chiave = 'codice_prof'")
chk "codice prof creato" "$(echo "$CP" | grep -c '^PROF-')" 1
Tk=$(tokj "$JP" "prof.php?a=registrati")
postj "$JP" "prof.php?a=registrati" --data-urlencode "csrf=$Tk" -d "codice=SBAGLIATO&nome=Prof+Rossi&email=rossi@scuola.it&password=passwordprof1&password2=passwordprof1&adulto=1" >/dev/null
chk "codice prof sbagliato rifiutato" "$(grep -c 'non è giusto' "$T/post.html")" 1
Tk=$(tokj "$JP" "prof.php?a=registrati")
chk "registrazione prof" "$(postj "$JP" "prof.php?a=registrati" --data-urlencode "csrf=$Tk" --data-urlencode "codice=$CP" -d "nome=Prof+Rossi&email=rossi@scuola.it&password=passwordprof1&password2=passwordprof1&adulto=1")" 302
Tk=$(tokj "$JP" prof.php)
chk "nuova classe" "$(postj "$JP" prof.php -d "csrf=$Tk&azione=nuova_classe&nome=2E+Trappitello&libro_id=$LID")" 302
CID=$(sql "SELECT id FROM classi WHERE nome = '2E Trappitello'")
CC=$(sql "SELECT codice FROM classi WHERE id = $CID")
chk "codice classe ABC-123" "$(echo "$CC" | grep -cE '^[A-Z0-9]{3}-[A-Z0-9]{3}$')" 1
Tk=$(tokj "$JS" studente.php)
postj "$JS" "studente.php?a=entra" -d "csrf=$Tk&codice=$(echo "$CC" | tr 'A-Z' 'a-z' | tr -d -)&nick=Drago42&pin=1234&ricorda=1" >/dev/null
chk "studente nuovo: chiede di ripetere il PIN" "$(grep -c 'name="pin2"' "$T/post.html")" 1
Tk=$(tokj "$JS" studente.php)
chk "studente entra in classe" "$(postj "$JS" "studente.php?a=entra" -d "csrf=$Tk&codice=$CC&nick=Drago42&pin=1234&pin2=1234&ricorda=1")" 302
chk "pagina dello studente" "$(curl -s -b "$JS" "$B/studente.php" | conta 'Ciao, Drago42')" 1
chk "il PIN è cifrato" "$(sql "SELECT COUNT(*) FROM studenti WHERE pin = '1234'")" 0
Tk=$(tokj "$JX" studente.php)
postj "$JX" "studente.php?a=entra" -d "csrf=$Tk&codice=$CC&nick=drago42&pin=9999" >/dev/null
chk "PIN sbagliato rifiutato" "$(grep -c 'PIN sbagliato' "$T/post.html")" 1
Tk=$(tokj "$JX" studente.php)
postj "$JX" "studente.php?a=entra" -d "csrf=$Tk&codice=ZZZ-999&nick=Pippo&pin=1234" >/dev/null
chk "codice classe inesistente" "$(grep -c 'non esiste' "$T/post.html")" 1
ES1=$(sql "SELECT id FROM esercizi WHERE codice = 'CFC-1'")
Tk=$(tokj "$JP" "prof.php?a=classe&id=$CID&vista=compiti")
chk "assegna compito" "$(postj "$JP" prof.php -d "csrf=$Tk&azione=nuovo_compito&classe=$CID&titolo=Per+lunedi&scadenza=2099-01-01&esercizi[]=$ES1")" 302
chk "compito visibile allo studente" "$(curl -s -b "$JS" "$B/studente.php" | conta 'Per lunedi')" 1
Tk=$(curl -s -b "$JS" -c "$JS" "$B/apri.php?c=CFC-1" | grep -o 'data-csrf="[^"]*"' | cut -d'"' -f2)
chk "esercizio: banner dello studente" "$(curl -s -b "$JS" "$B/apri.php?c=CFC-1" | conta 'banner-studente')" 1
R=$(curl -s -b "$JS" -H "X-CSRF: $Tk" -H 'Content-Type: application/json' -d '{"codice":"CFC-1","voci":[1,0]}' "$B/api-scuola.php")
chk "risultato salvato (1 su 2, nel compito)" "$(echo "$R" | grep -c '"fatti":1,"totale":2,"compito":[1-9]')" 1
R=$(curl -s -b "$JS" -H "X-CSRF: $Tk" -H 'Content-Type: application/json' -d '{"codice":"CFC-1","voci":[1,1,1,1,1,1],"totale":99}' "$B/api-scuola.php")
chk "non si bara sul totale" "$(echo "$R" | grep -c '"fatti":2,"totale":2')" 1
curl -s -b "$JS" -H "X-CSRF: $Tk" -H 'Content-Type: application/json' -d '{"codice":"CFC-1","voci":[1,0]}' "$B/api-scuola.php" >/dev/null   # ultimo tentativo: sbaglia il predicato
chk "risultato senza CSRF rifiutato" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JS" -H 'Content-Type: application/json' -d '{"codice":"CFC-1","voci":[1]}' "$B/api-scuola.php")" 400
chk "risultato senza classe rifiutato" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JX" -H "X-CSRF: x" -d '{}' "$B/api-scuola.php")" 400
for v in studenti compiti risultati errori progressi classe; do
  chk "prof: vista $v" "$(curl -s -b "$JP" -c "$JP" -o "$T/out.html" -w '%{http_code}' "$B/prof.php?a=classe&id=$CID&vista=$v")" 200; senza_errori "prof vista $v"
done
chk "prof: risultato migliore 2/2" "$(curl -s -b "$JP" "$B/prof.php?a=classe&id=$CID&vista=risultati" | conta '>2/2<')" 1
chk "prof: errore comune (Luce/mangia)" "$(curl -s -b "$JP" "$B/prof.php?a=classe&id=$CID&vista=errori" | conta '«mangia» = predicato')" 1
chk "prof: soluzioni" "$(curl -s -b "$JP" "$B/prof.php?a=soluzioni&libro=$LID" | conta 'Luce \[soggetto\]')" 1
# un altro prof non vede (e non tocca) la classe del primo
php -r 'require "inc/bootstrap.php"; db()->prepare("INSERT INTO prof (email, nome, password) VALUES (?,?,?)")->execute(["bianchi@scuola.it", "Bianchi", password_hash("passwordprof2", PASSWORD_DEFAULT)]);'
Tk=$(tokj "$JX" prof.php); postj "$JX" "prof.php?a=accedi" -d "csrf=$Tk&email=bianchi@scuola.it&password=passwordprof2" >/dev/null
chk "altro prof: la classe non si apre" "$(curl -s -b "$JX" "$B/prof.php?a=classe&id=$CID" | conta "$CC")" 0
Tk=$(tokj "$JX" prof.php)
chk "altro prof: niente modifiche" "$(postj "$JX" prof.php -d "csrf=$Tk&azione=elimina_classe&classe=$CID")" 403
SID=$(sql "SELECT id FROM studenti WHERE nick = 'Drago42'")
Tk=$(tokj "$JP" "prof.php?a=classe&id=$CID")
postj "$JP" prof.php -d "csrf=$Tk&azione=nuovo_pin&classe=$CID&studente=$SID" >/dev/null
NP=$(curl -s -b "$JP" "$B/prof.php?a=classe&id=$CID" | grep -o 'Nuovo PIN: [0-9]*' | grep -o '[0-9]*$')
Tk=$(tokj "$JX" studente.php)
chk "il nuovo PIN funziona" "$(postj "$JX" "studente.php?a=entra" -d "csrf=$Tk&codice=$CC&nick=Drago42&pin=$NP")" 302
# lavagna del prof: video per la classe, lo vede lo studente
printf '\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free' > "$T/video.mp4"
CSP=$(curl -s -b "$JP" -c "$JP" "$B/prof.php?a=lavagna&nuova=1" | grep -o '"csrf":"[^"]*"' | cut -d'"' -f4)
chk "prof: editor della lavagna" "$(curl -s -b "$JP" "$B/prof.php?a=lavagna&nuova=1" | conta 'lavagna.js')" 1
R=$(curl -s -b "$JP" -c "$JP" -H "X-CSRF: $CSP" -F come=prof -F azione=salva -F "titolo=Ripasso" -F classe_id=$CID -F 'dati={"oggetti":[],"passi":[{"durata":2,"stato":{}}]}' "$B/lavagna-api.php")
LVID=$(echo "$R" | grep -o '"id":[0-9]*' | cut -d: -f2)
chk "prof: lavagna salvata" "$([ -n "$LVID" ] && echo si)" si
R=$(curl -s -b "$JP" -c "$JP" -H "X-CSRF: $CSP" -F come=prof -F azione=video -F id=$LVID -F "video=@$T/voce.wav;type=audio/wav" "$B/lavagna-api.php")
chk "prof: un audio non è un video" "$(echo "$R" | grep -c 'Serve un file video')" 1
R=$(curl -s -b "$JP" -c "$JP" -H "X-CSRF: $CSP" -F come=prof -F azione=video -F id=$LVID -F "video=@$T/video.mp4;type=video/mp4" "$B/lavagna-api.php")
chk "prof: video caricato" "$(echo "$R" | grep -c '"ok":true')" 1
chk "studente: vede il video della classe" "$(curl -s -b "$JS" "$B/studente.php" | conta 'video-classe')" 1
chk "studente senza login: niente video" "$(curl -s "$B/studente.php" | conta 'video-classe')" 0
R=$(curl -s -b "$JX" -c "$JX" -H "X-CSRF: x" -F come=prof -F azione=salva -F id=$LVID -F 'dati={"oggetti":[],"passi":[]}' "$B/lavagna-api.php")
chk "altro prof: non tocca la lavagna" "$(echo "$R" | grep -c '"ok":true')" 0
chk "lavagna senza accesso" "$(curl -s -o /dev/null -w '%{http_code}' -X POST "$B/lavagna-api.php")" 401
chk "admin: prof elencati" "$(curl -s -b "$J" "$B/admin/scuola.php" | conta 'rossi@scuola.it')" 1

if [ -z "${SENZA_BROWSER:-}" ]; then
  export NODE_PATH="${NODE_PATH:-$(npm root -g 2>/dev/null)}"
  BASE="$B" SLUG="$SLUG" LID="$LID" CC="$CC" CID="$CID" node "$REPO/sito/tests/scuola-browser.js" && ok=$((ok+1)) || { ko=$((ko+1)); echo "FAIL: test nel browser"; }
  BASE="$B" LID="$LID" WAV="$T/voce.wav" node "$REPO/sito/tests/lavagna-browser.js" && ok=$((ok+1)) || { ko=$((ko+1)); echo "FAIL: test della lavagna nel browser"; }
  chk "lavagna: video salvato" "$(sql "SELECT COUNT(*) FROM lavagne WHERE video != '' AND prof_id = 0")" 1
  chk "lavagna: diventato un contenuto video del libro" "$(sql "SELECT COUNT(*) FROM contenuti WHERE tipo = 'video' AND libro_id = $LID")" 1
fi

# lo studente cancella il suo profilo; il prof elimina il suo account
Tk=$(tokj "$JS" studente.php)
postj "$JS" "studente.php?a=cancella" -d "csrf=$Tk" >/dev/null
chk "profilo studente cancellato" "$(sql "SELECT COUNT(*) FROM studenti WHERE nick = 'Drago42'")" 0
chk "risultati cancellati" "$(sql "SELECT COUNT(*) FROM risultati WHERE studente_id = $SID")" 0
Tk=$(tokj "$JP" "prof.php?a=account")
postj "$JP" prof.php -d "csrf=$Tk&azione=elimina_account&attuale=passwordprof1" >/dev/null
chk "account prof eliminato con le classi" "$(sql "SELECT COUNT(*) FROM classi WHERE id = $CID")" 0

# eliminazione definitiva del libro: via anche esercizi, contenuti, zone e file
php -r 'require "inc/bootstrap.php"; require "inc/libri.php"; elimina_libro_definitivo((int)$argv[1]);' "$LID"
chk "eliminazione: esercizi tolti" "$(sql "SELECT COUNT(*) FROM esercizi WHERE libro_id = $LID")" 0
chk "eliminazione: file audio tolto" "$([ -f "uploads/media/$F" ] && echo resta || echo via)" via

ERRPHP=$(grep -iE "PHP (Warning|Fatal|Notice|Deprecated)" "$T/server.log" | grep -v "in Unknown on line 0")
if [ -n "$ERRPHP" ]; then echo "FAIL: errori PHP nel log del server"; echo "$ERRPHP" | head -5; ko=$((ko+1)); fi
echo "scuola: OK=$ok KO=$ko"
[ $ko -eq 0 ]
