<?php
// Vlad Editore Scuola: esercizi interattivi, contenuti multimediali (audio, video, immagini) e zone cliccabili dei libri scolastici.
// Ogni esercizio o contenuto ha un codice breve (es. CFC-12) stampato nel PDF accanto al suo simbolo e nel QR code:
// il lettore riconosce il codice nel testo della pagina e lo rende cliccabile; apri.php?c=CODICE lo apre da solo.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/file.php';

const TIPI_ESERCIZIO = [
    'scelta' => ['☑', 'Crocette', 'Scegli la risposta giusta tra alcune possibilità.'],
    'vf' => ['✔✘', 'Vero o falso', 'Decidi se ogni frase è vera o falsa.'],
    'completa' => ['✎', 'Completa', 'Scrivi la parola che manca negli spazi.'],
    'tocca' => ['👆', 'Tocca le parole', 'Tocca le parole della frase per segnarle (soggetto, predicato…).'],
    'ordina' => ['↔', 'Riordina la frase', 'Trascina le parole in disordine per formare la frase giusta.'],
    'gruppi' => ['🗂', 'Trascina nei gruppi', 'Trascina ogni elemento nel gruppo giusto.'],
    'aperta' => ['💬', 'Scrivi tu', 'Risposta libera, poi confronti con un esempio.'],
];
const TIPI_CONTENUTO = ['audio' => ['🔊', 'Audio'], 'video' => ['🎬', 'Video'], 'immagine' => ['🖼', 'Immagine']];
const TIPI_MEDIA = [
    'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'aac', 'audio/ogg' => 'ogg',
    'audio/webm' => 'webm', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/vnd.wave' => 'wav',
    'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
];
// Colori dell'analisi logica come a scuola («Si può dire»): soggetto verde, predicato blu/rosso, complementi in altri colori.
const CATEGORIE_PREDEFINITE = [
    ['nome' => 'soggetto', 'colore' => '#2e7d32'], ['nome' => 'predicato', 'colore' => '#c62828'],
    ['nome' => 'complemento oggetto', 'colore' => '#1565c0'],
];

function sezione_scuola_attiva(): bool {
    static $s = null;
    return $s ??= (bool)db()->query("SELECT 1 FROM libri WHERE sezione = 'scuola' AND " . sql_visibile() . ' LIMIT 1')->fetchColumn();
}

function normalizza_codice(string $c): string { return strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', trim($c))); }
function codice_valido(string $c): bool { return (bool)preg_match('/^[A-Z]{2,6}-\d{1,4}$/', $c); }

// Sigla del libro per i codici: iniziali delle parole del titolo («Chi fa cosa?» → CFC).
function sigla_libro(string $titolo): string {
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $titolo) ?: $titolo;
    preg_match_all('/[A-Za-z]+/', $t, $m);
    $s = strtoupper(implode('', array_map(fn($w) => $w[0], $m[0])));
    if (strlen($s) < 2) $s = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $t) . 'XX', 0, 3));
    return substr($s, 0, 5);
}

function codice_usato(string $c, int $escludiEs = 0, int $escludiCo = 0): bool {
    $a = db()->prepare('SELECT 1 FROM esercizi WHERE codice = ? AND id != ?'); $a->execute([$c, $escludiEs]);
    if ($a->fetchColumn()) return true;
    $b = db()->prepare('SELECT 1 FROM contenuti WHERE codice = ? AND id != ?'); $b->execute([$c, $escludiCo]);
    return (bool)$b->fetchColumn();
}

// Primo codice libero per un libro: SIGLA-1, SIGLA-2… (esercizi e contenuti condividono la numerazione).
function nuovo_codice(int $libro_id): string {
    $titolo = (string)db()->query('SELECT titolo FROM libri WHERE id = ' . $libro_id)->fetchColumn();
    $sig = $libro_id ? sigla_libro($titolo) : 'VS';
    $max = 0;
    foreach (['esercizi', 'contenuti'] as $t) {
        $st = db()->prepare("SELECT codice FROM $t WHERE codice LIKE ?"); $st->execute([$sig . '-%']);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $c) $max = max($max, (int)substr($c, strlen($sig) + 1));
    }
    return $sig . '-' . ($max + 1);
}

// Cosa apre un codice: ['esercizio', riga] o ['contenuto', riga] o null.
function trova_codice(string $c): ?array {
    $c = normalizza_codice($c);
    if ($c === '') return null;
    foreach (['esercizi' => 'esercizio', 'contenuti' => 'contenuto'] as $t => $k) {
        $st = db()->prepare("SELECT * FROM $t WHERE codice = ?"); $st->execute([$c]);
        if ($r = $st->fetch()) return [$k, $r];
    }
    return null;
}

// Il libro a cui appartiene l'esercizio è visibile al pubblico? (esercizi senza libro: sempre visibili)
function libro_pubblico(int $id): bool {
    if ($id === 0) return true;
    return (bool)db()->query('SELECT 1 FROM libri WHERE id = ' . $id . ' AND ' . sql_visibile())->fetchColumn();
}

function dati_esercizio(array $e): array { $d = json_decode((string)$e['dati'], true); return is_array($d) ? $d : []; }

// Quanti punti vale un esercizio (uno per domanda/frase/elemento); "aperta" non ha punteggio.
function punti_esercizio(array $e): int {
    $d = dati_esercizio($e);
    switch ($e['tipo']) {
        case 'scelta': case 'vf': return count($d['domande'] ?? []);
        case 'completa': $n = 0; foreach ($d['frasi'] ?? [] as $f) $n += preg_match_all('/\[[^\]]+\]/', (string)($f['testo'] ?? '')); return $n;
        case 'tocca': $n = 0; foreach ($d['frasi'] ?? [] as $f) $n += count($f['segni'] ?? []); return $n;
        case 'ordina': return count($d['frasi'] ?? []);
        case 'gruppi': $n = 0; foreach ($d['gruppi'] ?? [] as $g) $n += count($g['elementi'] ?? []); return $n;
    }
    return 0;
}

// Pulisce i dati che arrivano dall'editor: solo i campi previsti, testi corti, niente HTML (il player usa textContent).
function pulisci_dati_esercizio(string $tipo, $d): array {
    $d = is_array($d) ? $d : [];
    $s = fn($v, $max = 2000) => mb_substr(trim((string)$v), 0, $max);
    $out = [];
    switch ($tipo) {
        case 'scelta':
            foreach (array_slice($d['domande'] ?? [], 0, 100) as $q) {
                $op = array_values(array_filter(array_map(fn($o) => $s($o, 500), array_slice((array)($q['opzioni'] ?? []), 0, 8)), 'strlen'));
                if ($s($q['testo'] ?? '') === '' || count($op) < 2) continue;
                $out['domande'][] = ['testo' => $s($q['testo']), 'opzioni' => $op, 'giusta' => max(0, min(count($op) - 1, (int)($q['giusta'] ?? 0))), 'spiegazione' => $s($q['spiegazione'] ?? '')];
            }
            break;
        case 'vf':
            foreach (array_slice($d['domande'] ?? [], 0, 100) as $q) {
                if ($s($q['testo'] ?? '') === '') continue;
                $out['domande'][] = ['testo' => $s($q['testo']), 'vero' => !empty($q['vero']), 'spiegazione' => $s($q['spiegazione'] ?? '')];
            }
            break;
        case 'completa':
            foreach (array_slice($d['frasi'] ?? [], 0, 100) as $f) {
                if (!preg_match('/\[[^\]]+\]/', (string)($f['testo'] ?? ''))) continue;
                $out['frasi'][] = ['testo' => $s($f['testo']), 'spiegazione' => $s($f['spiegazione'] ?? '')];
            }
            $out['banca'] = !empty($d['banca']);
            break;
        case 'tocca':
            foreach (array_slice($d['categorie'] ?? [], 0, 10) as $c) {
                $nome = $s($c['nome'] ?? '', 60);
                if ($nome !== '') $out['categorie'][] = ['nome' => $nome, 'colore' => colore_valido((string)($c['colore'] ?? ''), '#555555')];
            }
            $nomi = array_column($out['categorie'] ?? [], 'nome');
            foreach (array_slice($d['frasi'] ?? [], 0, 100) as $f) {
                $testo = $s($f['testo'] ?? '');
                if ($testo === '') continue;
                $n = count(preg_split('/\s+/u', $testo));
                $segni = [];
                foreach ((array)($f['segni'] ?? []) as $i => $cat) if ((int)$i >= 0 && (int)$i < $n && in_array($cat, $nomi, true)) $segni[(string)(int)$i] = $cat;
                $out['frasi'][] = ['testo' => $testo, 'segni' => (object)$segni, 'spiegazione' => $s($f['spiegazione'] ?? '')];
            }
            break;
        case 'ordina':
            foreach (array_slice($d['frasi'] ?? [], 0, 100) as $f) {
                $testo = $s($f['testo'] ?? '');
                if (count(preg_split('/\s+/u', $testo)) >= 2) $out['frasi'][] = ['testo' => $testo, 'spiegazione' => $s($f['spiegazione'] ?? '')];
            }
            break;
        case 'gruppi':
            foreach (array_slice($d['gruppi'] ?? [], 0, 8) as $g) {
                $el = array_values(array_filter(array_map(fn($x) => $s($x, 200), array_slice((array)($g['elementi'] ?? []), 0, 40)), 'strlen'));
                if ($s($g['nome'] ?? '', 80) !== '' && $el) $out['gruppi'][] = ['nome' => $s($g['nome'], 80), 'elementi' => $el];
            }
            break;
        case 'aperta':
            foreach (array_slice($d['domande'] ?? [], 0, 50) as $q) {
                if ($s($q['testo'] ?? '') !== '') $out['domande'][] = ['testo' => $s($q['testo']), 'esempio' => $s($q['esempio'] ?? '')];
            }
            break;
    }
    return $out;
}

// Indirizzo pubblico di un file multimediale (uploads/media).
function url_media(string $valore, string $prefisso = ''): string { return con_prefisso(url_file($valore, 'media'), $prefisso); }

// Codici e zone di un libro, per il lettore (JSON): il lettore cerca i codici nel testo di ogni pagina.
function mappa_scuola_libro(int $libro_id): array {
    $codici = [];
    $st = db()->prepare('SELECT codice, tipo, titolo, pagina FROM esercizi WHERE libro_id = ? AND extra = 0'); $st->execute([$libro_id]);
    foreach ($st as $r) $codici[$r['codice']] = ['g' => 'esercizio', 't' => $r['tipo'], 'titolo' => $r['titolo'], 'p' => (int)$r['pagina']];
    $st = db()->prepare('SELECT codice, tipo, titolo, pagina FROM contenuti WHERE libro_id = ?'); $st->execute([$libro_id]);
    foreach ($st as $r) $codici[$r['codice']] = ['g' => 'contenuto', 't' => $r['tipo'], 'titolo' => $r['titolo'], 'p' => (int)$r['pagina']];
    $zone = [];
    $st = db()->prepare('SELECT pagina, x, y, w, h, codice FROM zone WHERE libro_id = ? ORDER BY pagina, id'); $st->execute([$libro_id]);
    foreach ($st as $z) if (isset($codici[$z['codice']])) $zone[] = ['p' => (int)$z['pagina'], 'x' => (int)$z['x'], 'y' => (int)$z['y'], 'w' => (int)$z['w'], 'h' => (int)$z['h'], 'c' => $z['codice']];
    return ['codici' => (object)$codici, 'zone' => $zone];
}

// ---------- file multimediali ----------
// Salva un file audio/video/immagine (caricato, registrato nel browser o scaricato da deAPI) nella cartella uploads/media.
function salva_media_da_percorso(string $tmp, bool $caricato): string {
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if ($mime === 'application/octet-stream' || $mime === 'video/x-matroska') $mime = 'audio/webm';     // registrazioni del browser
    if (!isset(TIPI_MEDIA[$mime])) throw new RuntimeException('Tipo di file non ammesso (' . $mime . ').');
    $d = dir_uploads();
    if ($d !== null) {
        $nome = bin2hex(random_bytes(8)) . '.' . TIPI_MEDIA[$mime];
        @mkdir("$d/media", 0755, true);
        $ok = $caricato ? move_uploaded_file($tmp, "$d/media/$nome") : rename($tmp, "$d/media/$nome");
        if (!$ok) throw new RuntimeException('Non riesco a salvare il file.');
        return valore_file('media', $nome);
    }
    $r = api_chiama('carica', ['cartella' => 'media'], $tmp, hash_file('sha256', $tmp) . '|media');
    if (!$caricato) @unlink($tmp);
    return (string)$r['url'];
}
function salva_media(string $campo, int $max = 200 * 1024 * 1024): ?string {
    if (empty($_FILES[$campo]['name'])) return null;
    $f = $_FILES[$campo];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Caricamento fallito (codice ' . $f['error'] . '). Controlla upload_max_filesize su PHP.');
    if ($f['size'] > $max) throw new RuntimeException('File troppo grande.');
    return salva_media_da_percorso($f['tmp_name'], true);
}
function tipo_da_mime(string $valore): string {
    $est = strtolower(pathinfo(nome_file($valore), PATHINFO_EXTENSION));
    if (in_array($est, ['jpg', 'png', 'webp', 'gif'], true)) return 'immagine';
    if (in_array($est, ['mp4', 'mov'], true)) return 'video';
    return 'audio';
}

// ---------- deAPI (voci) ----------
// Voci pronte in italiano (Kokoro). Le voci create da una descrizione o copiate da una registrazione arriveranno con la fase 4.
const VOCI_TTS = [
    'if_sara' => 'Sara (donna, italiano)', 'im_nicola' => 'Nicola (uomo, italiano)',
];
function deapi_chiave(): string { return trim(impost('deapi_chiave')); }

function deapi_richiesta(string $metodo, string $percorso, ?array $campi = null, int $timeout = 60): array {
    if (!function_exists('curl_init')) throw new RuntimeException('Serve l\'estensione curl di PHP per usare deAPI.');
    $ch = curl_init('https://api.deapi.ai/api/v2' . $percorso);
    $h = ['Accept: application/json'];
    if (deapi_chiave() !== '') $h[] = 'Authorization: Bearer ' . deapi_chiave();
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $h, CURLOPT_USERAGENT => 'VladEditore-Scuola/1.0']);
    if ($metodo === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $campi ?? []); }
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $err = curl_error($ch); curl_close($ch);
    if ($r === false) throw new RuntimeException('deAPI non risponde: ' . $err);
    if ($code === 401 || $code === 403) throw new RuntimeException('deAPI rifiuta la chiave: controlla Admin → Scuola → Impostazioni.');
    if ($code === 429) throw new RuntimeException('deAPI: troppe richieste, riprova tra un minuto.');
    $j = json_decode((string)$r, true);
    if ($code >= 400 || !is_array($j) || !is_array($j['data'] ?? null)) throw new RuntimeException("deAPI ha risposto con un errore (HTTP $code).");
    return $j['data'];
}

// Genera la voce e restituisce il percorso di un file temporaneo mp3.
function deapi_voce(string $testo, string $voce): string {
    $testo = trim($testo);
    if (mb_strlen($testo) < 3) throw new RuntimeException('Scrivi il testo da leggere.');
    if (mb_strlen($testo) > 10000) throw new RuntimeException('Il testo è troppo lungo (massimo 10.000 caratteri).');
    if (!isset(VOCI_TTS[$voce])) $voce = 'if_sara';
    $id = (string)(deapi_richiesta('POST', '/audio/speech', ['text' => $testo, 'model' => 'Kokoro', 'mode' => 'custom_voice', 'voice' => $voce,
        'lang' => 'it', 'speed' => '1', 'format' => 'mp3', 'sample_rate' => '24000'])['request_id'] ?? '');
    if ($id === '') throw new RuntimeException('deAPI non ha accettato la richiesta.');
    $fine = time() + 150;
    while (time() < $fine) {
        $j = deapi_richiesta('GET', '/jobs/' . rawurlencode($id));
        $stato = (string)($j['status'] ?? '');
        if ($stato === 'done') {
            $url = (string)($j['result_url'] ?? '');
            if (!preg_match('#^https://#', $url)) throw new RuntimeException('deAPI non ha restituito il file.');
            return scarica_url($url, 50 * 1024 * 1024);
        }
        if (in_array($stato, ['error', 'failed', 'cancelled', 'canceled'], true)) throw new RuntimeException('deAPI: generazione non riuscita.');
        sleep(2);
    }
    throw new RuntimeException('deAPI ci mette troppo: riprova più tardi.');
}
