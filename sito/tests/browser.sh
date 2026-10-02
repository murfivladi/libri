#!/bin/bash
# Test nel browser: prepara un sito temporaneo con i 5 libri e lancia browser.js (richiede Node e Playwright).
# Uso (dalla radice del repository):  bash sito/tests/browser.sh
set -u
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
T="$(mktemp -d)"
S="$T/sito"
cp -r "$REPO/sito" "$S"
rm -rf "$S/data/"*.sqlite "$S/uploads/pdf/"*.pdf "$S/uploads/cover/"* "$S/uploads/thumb" "$S/tests"
PORT=${PORT:-8098}
cd "$S" || exit 1
LIBRI_REPO="$REPO" php importa.php >/dev/null || exit 1
PASSWORD="provaprova12" php -r 'require "inc/bootstrap.php";
  db()->exec("UPDATE libri SET pubblicato = 1");
  collana_id_per_nome("Seconda collana");
  db()->prepare("INSERT INTO admin (id, password) VALUES (1, ?)")->execute([password_hash(getenv("PASSWORD"), PASSWORD_DEFAULT)]);
  db()->prepare("INSERT INTO lettori (email, nick, password, stato, verificato, ultimo_accesso, privacy_versione, privacy_il, eta_ok) VALUES (?,?,?,1,?,?,?,?,1)")
      ->execute(["prova@prova.it", "Prova1", password_hash("passwordprova99", PASSWORD_DEFAULT), "2026-01-01 00:00:00", "", "1", "2026-01-01 00:00:00"]);'
php -S 127.0.0.1:$PORT >"$T/server.log" 2>&1 &
PID=$!
trap 'kill $PID 2>/dev/null; rm -rf "$T"' EXIT
for i in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PORT/robots.txt" && break; sleep 0.2; done
export NODE_PATH="${NODE_PATH:-$(npm root -g 2>/dev/null)}"
BASE="http://127.0.0.1:$PORT" PASSWORD="provaprova12" node "${NODE_SCRIPT:-$REPO/sito/tests/browser.js}"
RIS=$?
if [ -z "${NODE_SCRIPT:-}" ]; then
  BASE="http://127.0.0.1:$PORT" node "$REPO/sito/tests/lettore.js" || RIS=1
  BASE="http://127.0.0.1:$PORT" node "$REPO/sito/tests/account-browser.js" || RIS=1
fi
# (si ignorano gli avvisi di avvio di PHP, "in Unknown on line 0", come quello di JIT con Xdebug sui runner di GitHub)
ERRPHP=$(grep -iE "PHP (Warning|Fatal|Notice|Deprecated)" "$T/server.log" | grep -v "in Unknown on line 0")
if [ -n "$ERRPHP" ]; then echo "FAIL: errori PHP nel log del server"; echo "$ERRPHP" | head -5; RIS=1; fi
exit $RIS
