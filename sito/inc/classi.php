<?php
// Classi della Scuola: prof (codice segreto dell'admin), studenti con nickname + PIN (nessun dato personale), compiti e risultati.
require_once __DIR__ . '/scuola.php';
require_once __DIR__ . '/lettori.php';

const GIORNI_STUDENTE = 180;
const ALFABETO_CODICI = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';     // senza 0/O e 1/I/L, che si confondono

function codice_casuale(int $n): string {
    $s = '';
    for ($i = 0; $i < $n; $i++) $s .= ALFABETO_CODICI[random_int(0, strlen(ALFABETO_CODICI) - 1)];
    return $s;
}
function codice_prof(): string { return trim(impost('codice_prof')); }
function nuovo_codice_prof(): string { $c = 'PROF-' . codice_casuale(4) . '-' . codice_casuale(4); imposta('codice_prof', $c); return $c; }
function normalizza_codice_classe(string $c): string { return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c)); }
function nuovo_codice_classe(): string {
    $st = db()->prepare('SELECT 1 FROM classi WHERE codice = ?');
    do { $c = codice_casuale(3) . '-' . codice_casuale(3); $st->execute([$c]); } while ($st->fetchColumn());
    return $c;
}
function classe_per_codice(string $c): ?array {
    $n = normalizza_codice_classe($c);
    if (strlen($n) !== 6) return null;
    $st = db()->prepare('SELECT * FROM classi WHERE codice = ?'); $st->execute([substr($n, 0, 3) . '-' . substr($n, 3)]);
    return $st->fetch() ?: null;
}

// ---------- prof ----------
function prof_corrente(): ?array {
    static $c = false;
    if ($c !== false) return $c;
    $c = null;
    if ($id = (int)($_SESSION['prof'] ?? 0)) {
        $st = db()->prepare('SELECT * FROM prof WHERE id = ? AND stato = 1'); $st->execute([$id]);
        $c = $st->fetch() ?: null;
        if (!$c) unset($_SESSION['prof']);
    }
    return $c;
}
function accedi_prof(array $p): void {
    session_regenerate_id(true);
    $_SESSION['prof'] = (int)$p['id'];
    db()->prepare('UPDATE prof SET ultimo_accesso = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $p['id']]);
}
function esci_prof(): void { unset($_SESSION['prof']); session_regenerate_id(true); }

// Elimina un prof con le sue classi, gli studenti e i risultati.
function elimina_prof(int $id): void {
    foreach (db()->query('SELECT id FROM classi WHERE prof_id = ' . $id)->fetchAll(PDO::FETCH_COLUMN) as $c) elimina_classe((int)$c);
    $video = db()->query('SELECT video FROM lavagne WHERE prof_id = ' . $id)->fetchAll(PDO::FETCH_COLUMN);
    db()->prepare('DELETE FROM lavagne WHERE prof_id = ?')->execute([$id]);
    foreach ($video as $v) elimina_file('media', (string)$v);
    db()->prepare('DELETE FROM prof WHERE id = ?')->execute([$id]);
}
function elimina_classe(int $id): void {
    $ids = db()->query('SELECT id FROM studenti WHERE classe_id = ' . $id)->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) db()->exec('DELETE FROM risultati WHERE studente_id IN (' . implode(',', array_map('intval', $ids)) . ')');
    foreach (['studenti' => 'classe_id', 'compiti' => 'classe_id', 'classi' => 'id'] as $t => $k) db()->prepare("DELETE FROM $t WHERE $k = ?")->execute([$id]);
    db()->prepare('UPDATE lavagne SET classe_id = 0 WHERE classe_id = ?')->execute([$id]);      // i video restano al prof
}
function elimina_studente(int $id): void {
    db()->prepare('DELETE FROM risultati WHERE studente_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM studenti WHERE id = ?')->execute([$id]);
}
// La classe è di questo prof? (restituisce la classe oppure null)
function classe_del_prof(int $classe, int $prof): ?array {
    $st = db()->prepare('SELECT * FROM classi WHERE id = ? AND prof_id = ?'); $st->execute([$classe, $prof]);
    return $st->fetch() ?: null;
}

// ---------- studenti ----------
// Il nickname non deve essere il nome vero: lettere, numeri, spazi, - e _, da 2 a 20 caratteri.
function nick_studente_errore(string $n): ?string {
    if (!preg_match('/^[\p{L}\p{N}_\- ]{2,20}$/u', $n)) return 'Il nickname deve avere da 2 a 20 caratteri: lettere, numeri, spazio, - o _.';
    return null;
}
function pin_errore(string $p): ?string { return preg_match('/^\d{4,6}$/', $p) ? null : 'Il PIN deve avere da 4 a 6 cifre.'; }
function studente_per_nick(int $classe, string $nick): ?array {
    $st = db()->prepare('SELECT * FROM studenti WHERE classe_id = ? AND LOWER(nick) = ?'); $st->execute([$classe, mb_strtolower($nick)]);
    return $st->fetch() ?: null;
}
function studente_corrente(): ?array {
    static $c = false;
    if ($c !== false) return $c;
    $c = null;
    $id = (int)($_SESSION['studente'] ?? 0);
    if (!$id && preg_match('/^(\d+)\.([0-9a-f]{40})$/', (string)($_COOKIE['studente'] ?? ''), $m)) {      // «ricordami» su questo dispositivo
        $st = db()->prepare('SELECT token FROM studenti WHERE id = ?'); $st->execute([(int)$m[1]]);
        $h = (string)$st->fetchColumn();
        if ($h !== '' && hash_equals($h, hash('sha256', $m[2]))) { $id = (int)$m[1]; $_SESSION['studente'] = $id; }
        else cookie_studente('', 0);
    }
    if ($id) {
        $st = db()->prepare('SELECT s.*, c.nome AS classe, c.codice AS codice_classe, c.libro_id, c.prof_id FROM studenti s JOIN classi c ON c.id = s.classe_id WHERE s.id = ?');
        $st->execute([$id]);
        $c = $st->fetch() ?: null;
        if (!$c) { unset($_SESSION['studente']); cookie_studente('', 0); }
    }
    return $c;
}
function cookie_studente(string $v, int $scade): void {
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    setcookie('studente', $v, ['expires' => $scade, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
}
function accedi_studente(array $s, bool $ricorda): void {
    session_regenerate_id(true);
    $_SESSION['studente'] = (int)$s['id'];
    $tok = '';
    if ($ricorda) { $raw = bin2hex(random_bytes(20)); $tok = hash('sha256', $raw); cookie_studente($s['id'] . '.' . $raw, time() + GIORNI_STUDENTE * 86400); }
    db()->prepare('UPDATE studenti SET ultimo_accesso = ?, token = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $tok, $s['id']]);
}
function esci_studente(): void {
    if ($s = studente_corrente()) db()->prepare("UPDATE studenti SET token = '' WHERE id = ?")->execute([$s['id']]);
    cookie_studente('', 0);
    unset($_SESSION['studente']);
    session_regenerate_id(true);
}

// ---------- risultati e compiti ----------
// Salva il risultato di un esercizio; lo collega al compito aperto della classe che contiene quell'esercizio (se c'è).
function salva_risultato(array $stud, array $es, int $fatti, int $totale, array $voci): int {
    $totale = max(0, min(500, $totale)); $fatti = max(0, min($totale, $fatti));
    $voci = array_slice(array_map(fn($v) => $v ? 1 : 0, $voci), 0, 500);
    $compito = 0;
    $st = db()->prepare("SELECT id, esercizi FROM compiti WHERE classe_id = ? AND (scadenza = '' OR scadenza >= ?) ORDER BY id DESC");
    $st->execute([$stud['classe_id'], date('Y-m-d')]);
    foreach ($st as $c) if (in_array((int)$es['id'], ids_esercizi($c['esercizi']), true)) { $compito = (int)$c['id']; break; }
    db()->prepare('INSERT INTO risultati (studente_id, esercizio_id, compito_id, fatti, totale, voci, quando) VALUES (?,?,?,?,?,?,?)')
        ->execute([$stud['id'], $es['id'], $compito, $fatti, $totale, json_encode($voci), date('Y-m-d H:i:s')]);
    return $compito;
}
function ids_esercizi(string $s): array { return array_values(array_filter(array_map('intval', explode(',', $s)))); }

// Esercizi (righe) di una lista di id, nell'ordine dato.
function esercizi_per_id(array $ids): array {
    if (!$ids) return [];
    $r = [];
    foreach (db()->query('SELECT * FROM esercizi WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')') as $e) $r[(int)$e['id']] = $e;
    return array_values(array_filter(array_map(fn($i) => $r[$i] ?? null, $ids)));
}

// Miglior risultato (e numero di tentativi) di ogni studente per ogni esercizio: [studente][esercizio] = [fatti, totale, tentativi].
function migliori_risultati(array $studenti): array {
    $out = [];
    if (!$studenti) return $out;
    $ids = implode(',', array_map(fn($s) => (int)$s['id'], $studenti));
    foreach (db()->query("SELECT studente_id, esercizio_id, fatti, totale FROM risultati WHERE studente_id IN ($ids) ORDER BY id") as $r) {
        $p = &$out[(int)$r['studente_id']][(int)$r['esercizio_id']];
        if (!$p) $p = [0, (int)$r['totale'], 0];
        $p[2]++;
        if ($r['totale'] > 0 && $r['fatti'] / $r['totale'] >= ($p[1] ? $p[0] / $p[1] : 0)) { $p[0] = (int)$r['fatti']; $p[1] = (int)$r['totale']; }
        unset($p);
    }
    return $out;
}

// Etichetta di ogni punto dell'esercizio, nello stesso ordine in cui lo corregge assets/esercizi.js (per gli «errori comuni»).
function etichette_voci(array $e): array {
    $d = dati_esercizio($e); $o = [];
    switch ($e['tipo']) {
        case 'scelta': case 'vf': case 'aperta': foreach ($d['domande'] ?? [] as $q) $o[] = (string)$q['testo']; break;
        case 'ordina': foreach ($d['frasi'] ?? [] as $f) $o[] = (string)$f['testo']; break;
        case 'completa':
            foreach ($d['frasi'] ?? [] as $f) {
                preg_match_all('/\[([^\]]+)\]/', (string)$f['testo'], $m);
                $vuota = preg_replace('/\[[^\]]+\]/', '___', (string)$f['testo']);
                foreach ($m[1] as $risp) $o[] = $vuota . ' → ' . explode('|', $risp)[0];
            }
            break;
        case 'tocca':
            foreach ($d['frasi'] ?? [] as $f) {
                $w = preg_split('/\s+/u', trim((string)$f['testo']));
                $segni = (array)($f['segni'] ?? []); ksort($segni, SORT_NUMERIC);
                foreach ($segni as $i => $cat) $o[] = '«' . ($w[(int)$i] ?? '?') . '» = ' . $cat . ' (' . $f['testo'] . ')';
            }
            break;
        case 'gruppi': foreach ($d['gruppi'] ?? [] as $g) foreach ($g['elementi'] as $x) $o[] = $x . ' → ' . $g['nome']; break;
    }
    return $o;
}

// Errori comuni: per ogni punto dell'esercizio, quanti studenti lo hanno sbagliato nell'ultimo tentativo.
function errori_comuni(array $es, array $studenti): array {
    if (!$studenti) return [];
    $ids = implode(',', array_map(fn($s) => (int)$s['id'], $studenti));
    $ultimi = [];
    $st = db()->prepare("SELECT studente_id, voci FROM risultati WHERE esercizio_id = ? AND studente_id IN ($ids) ORDER BY id");
    $st->execute([$es['id']]);
    foreach ($st as $r) $ultimi[(int)$r['studente_id']] = json_decode((string)$r['voci'], true) ?: [];
    $et = etichette_voci($es); $out = [];
    foreach ($et as $i => $testo) {
        $tot = 0; $sbagli = 0;
        foreach ($ultimi as $v) { if (!array_key_exists($i, $v)) continue; $tot++; if (!$v[$i]) $sbagli++; }
        if ($tot) $out[] = ['testo' => $testo, 'sbagli' => $sbagli, 'tot' => $tot];
    }
    usort($out, fn($a, $b) => $b['sbagli'] / $b['tot'] <=> $a['sbagli'] / $a['tot']);
    return ['studenti' => count($ultimi), 'voci' => $out];
}

// Media della classe (in %) settimana per settimana, ultime $n settimane: [['dal' => 'Y-m-d', 'media' => float|null, 'n' => int]].
function progressi_classe(array $studenti, int $n = 10): array {
    $lun = strtotime('monday this week');
    $sett = [];
    for ($i = $n - 1; $i >= 0; $i--) $sett[date('Y-m-d', $lun - $i * 7 * 86400)] = [0.0, 0];
    if ($studenti) {
        $ids = implode(',', array_map(fn($s) => (int)$s['id'], $studenti));
        $st = db()->prepare("SELECT fatti, totale, quando FROM risultati WHERE studente_id IN ($ids) AND totale > 0 AND quando >= ?");
        $st->execute([array_key_first($sett) . ' 00:00:00']);
        foreach ($st as $r) {
            $k = date('Y-m-d', strtotime('monday this week', strtotime($r['quando'])));
            if (isset($sett[$k])) { $sett[$k][0] += $r['fatti'] / $r['totale']; $sett[$k][1]++; }
        }
    }
    $out = [];
    foreach ($sett as $k => [$somma, $c]) $out[] = ['dal' => $k, 'media' => $c ? round($somma / $c * 100) : null, 'n' => $c];
    return $out;
}

// Soluzione di un esercizio in testo semplice (area prof): elenco di righe.
function soluzione_righe(array $e): array {
    $d = dati_esercizio($e); $o = [];
    switch ($e['tipo']) {
        case 'scelta': foreach ($d['domande'] ?? [] as $q) $o[] = $q['testo'] . ' → ' . ($q['opzioni'][$q['giusta']] ?? '?'); break;
        case 'vf': foreach ($d['domande'] ?? [] as $q) $o[] = $q['testo'] . ' → ' . ($q['vero'] ? 'VERO' : 'FALSO'); break;
        case 'completa': foreach ($d['frasi'] ?? [] as $f) $o[] = preg_replace_callback('/\[([^\]]+)\]/', fn($m) => '«' . str_replace('|', '» o «', $m[1]) . '»', (string)$f['testo']); break;
        case 'ordina': foreach ($d['frasi'] ?? [] as $f) $o[] = (string)$f['testo']; break;
        case 'aperta': foreach ($d['domande'] ?? [] as $q) $o[] = $q['testo'] . ($q['esempio'] !== '' ? ' → es.: ' . $q['esempio'] : ''); break;
        case 'gruppi': foreach ($d['gruppi'] ?? [] as $g) $o[] = $g['nome'] . ': ' . implode(', ', $g['elementi']); break;
        case 'tocca':
            foreach ($d['frasi'] ?? [] as $f) {
                $w = preg_split('/\s+/u', trim((string)$f['testo'])); $segni = (array)($f['segni'] ?? []);
                $o[] = implode(' ', array_map(fn($i, $x) => isset($segni[(string)$i]) ? $x . ' [' . $segni[(string)$i] . ']' : $x, array_keys($w), $w));
            }
            break;
    }
    return $o;
}
