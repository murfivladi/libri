<?php
// Studio AI (deAPI): immagini con Vlad e Luce, sfondi tolti, voci (Luce «disegnata», Vlad copiata dalla sua voce), musica,
// animazioni e personaggi che parlano. Ogni richiesta diventa un «lavoro» (tabella lavori_ai): si avvia subito e poi
// la pagina dell'admin lo controlla ogni pochi secondi; quando è pronto il file viene scaricato in uploads/media.
require_once __DIR__ . '/scuola.php';

const AI_TIPI = [
    'immagine' => ['🎨', 'Immagine'], 'scontorno' => ['✂️', 'Sfondo tolto'], 'voce' => ['🗣', 'Voce'],
    'musica' => ['🎵', 'Musica'], 'animazione' => ['🎞', 'Animazione'], 'parla' => ['👄', 'Personaggio che parla'],
];
const AI_STILE = 'illustrazione a cartone animato per un libro scolastico per ragazzi, contorni neri puliti, colori piatti e vivaci, stile coerente con i personaggi di riferimento';
const AI_LUCE_PREDEFINITA = 'Voce di una giovane cagnolina dei cartoni animati: femminile, dolce, allegra, vivace e un po\' pasticciona. Parla in italiano.';
const MODELLO_IMMAGINI = 'Flux_2_Klein_4B_BF16';
const MODELLO_VIDEO = 'Ltx2_5_22B_Dist_INT8';
const MODELLO_MUSICA = 'AceStep_1_5_Turbo';

// Voci disponibili per «leggi questo testo»: quelle pronte di Kokoro più Luce e Vlad (se configurate).
function voci_disponibili(): array {
    $v = ['luce' => '🐕 Luce (voce AI disegnata)'];
    if (impost('voce_vlad_ref') !== '') $v['vlad'] = '🧒 Vlad (copia della sua voce)';
    return $v + VOCI_TTS;
}

// Percorso locale di un file da mandare a deAPI: asset del sito (personaggi) o file di uploads/media.
function file_per_ai(string $valore): string {
    if (strncmp($valore, 'assets/', 7) === 0 || strncmp($valore, '../assets/', 10) === 0) {
        $p = RADICE . '/' . preg_replace('#^(\.\./)+#', '', $valore);
        if (is_file($p) && strpos(realpath($p), realpath(RADICE . '/assets')) === 0) return $p;
        throw new RuntimeException('File del sito non trovato.');
    }
    if ($p = file_locale($valore, 'media')) return $p;
    if (e_assoluto($valore)) return scarica_url($valore, 30 * 1024 * 1024);
    throw new RuntimeException('File non trovato: ' . nome_file($valore));
}
function curl_file(string $percorso): CURLFile {
    return new CURLFile($percorso, (new finfo(FILEINFO_MIME_TYPE))->file($percorso) ?: 'application/octet-stream', basename($percorso));
}

// Avvia un lavoro: manda la richiesta a deAPI e salva il request_id. $par = dati per sapere cosa fare del risultato.
function ai_avvia(string $tipo, string $percorso, array $campi, array $par, int $contenuto = 0): int {
    if (!isset(AI_TIPI[$tipo])) throw new LogicException('Tipo di lavoro sconosciuto');
    $id = (string)(deapi_richiesta('POST', $percorso, $campi, 120)['request_id'] ?? '');
    if ($id === '') throw new RuntimeException('deAPI non ha accettato la richiesta.');
    db()->prepare('INSERT INTO lavori_ai (tipo, request_id, parametri, contenuto_id, aggiornato) VALUES (?,?,?,?,?)')
        ->execute([$tipo, $id, json_encode($par, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $contenuto, date('Y-m-d H:i:s')]);
    registra('Lavoro AI avviato', $tipo);
    return (int)db()->lastInsertId();
}

// ---------- richieste ----------
function ai_campi_voce(string $voce, string $testo): array {
    $testo = trim($testo);
    if (mb_strlen($testo) < 10) throw new RuntimeException('Il testo da leggere è troppo corto (almeno 10 caratteri).');
    if (mb_strlen($testo) > 5000) throw new RuntimeException('Il testo è troppo lungo (massimo 5000 caratteri): dividilo in più audio.');
    $base = ['text' => $testo, 'speed' => '1', 'format' => 'mp3', 'sample_rate' => '24000'];
    if ($voce === 'luce') {
        $descr = trim(impost('voce_luce')) ?: AI_LUCE_PREDEFINITA;
        return $base + ['model' => 'Qwen3_TTS_12Hz_1_7B_VoiceDesign', 'mode' => 'voice_design', 'instruct' => $descr, 'lang' => 'Italian'];
    }
    if ($voce === 'vlad') {
        if (impost('voce_vlad_ref') === '' || trim(impost('voce_vlad_testo')) === '') throw new RuntimeException('Prima registra la voce di Vlad in Studio AI → Voci.');
        return $base + ['model' => 'Qwen3_TTS_12Hz_1_7B_Base', 'mode' => 'voice_clone', 'lang' => 'Italian',
            'ref_audio' => curl_file(file_per_ai(impost('voce_vlad_ref'))), 'ref_text' => impost('voce_vlad_testo')];
    }
    if (!isset(VOCI_TTS[$voce])) $voce = 'if_sara';
    return $base + ['model' => 'Kokoro', 'mode' => 'custom_voice', 'voice' => $voce, 'lang' => 'it'];
}

// Immagine con i personaggi come riferimento (fino a 3 immagini): es. Vlad e Luce in classe davanti alla lavagna.
// $riferimenti = [percorso => nome]; $posa = un solo personaggio intero su fondo bianco (poi si toglie lo sfondo per la lavagna).
const AI_DESCRIZIONI = ['Vlad' => 'Vlad, il ragazzo con la felpa arancione e i jeans', 'Luce' => 'Luce, la cagnolina Shiba Inu con gli occhiali tondi'];
function ai_campi_immagine(string $prompt, array $riferimenti, string $formato, bool $posa = false): array {
    $prompt = trim($prompt);
    if (mb_strlen($prompt) < 5) throw new RuntimeException('Descrivi l\'immagine.');
    [$w, $h] = ['orizzontale' => [1536, 1024], 'verticale' => [1024, 1536], 'quadrato' => [1024, 1024], 'pagina' => [1088, 1536]][$formato] ?? [1536, 1024];
    if ($posa) [$w, $h] = [1024, 1536];
    $chi = []; $i = 0;
    foreach (array_slice($riferimenti, 0, 3, true) as $r => $nome) $chi[] = (AI_DESCRIZIONI[$nome] ?? $nome) . ' è il personaggio dell\'immagine di riferimento ' . ++$i;
    $testo = ($chi ? implode('; ', $chi) . '. Disegnali uguali ai riferimenti, ben visibili e interi. ' : '') . $prompt . '. ' . AI_STILE
        . ($posa ? '. Un solo personaggio, a figura intera, centrato, su sfondo completamente bianco e uniforme, senza altri oggetti né ombre a terra' : '');
    $campi = ['prompt' => $testo, 'model' => MODELLO_IMMAGINI, 'width' => (string)$w, 'height' => (string)$h, 'steps' => '4', 'seed' => (string)random_int(1, 2147483646)];
    $i = 0;
    foreach (array_slice(array_keys($riferimenti), 0, 3) as $r) $campi['images[' . $i++ . ']'] = curl_file(file_per_ai($r));
    return [$i ? '/images/edits' : '/images/generations', $campi];
}

function ai_campi_musica(string $descr, int $durata, int $bpm): array {
    $descr = trim($descr);
    if (mb_strlen($descr) < 3) throw new RuntimeException('Descrivi la musica (es. «allegra, chitarra e flauto, per bambini»).');
    $c = ['caption' => mb_substr($descr, 0, 300), 'model' => MODELLO_MUSICA, 'lyrics' => '[Instrumental]', 'duration' => (string)max(10, min(300, $durata)),
        'inference_steps' => '8', 'guidance_scale' => '1', 'seed' => '-1', 'format' => 'mp3'];
    if ($bpm >= 50 && $bpm <= 200) $c['bpm'] = (string)$bpm;
    return $c;
}

// Video da un'immagine (animazione) o da immagine + audio (il personaggio parla). Durata in secondi (2–10), 24 fotogrammi al secondo.
function ai_campi_video(string $prompt, string $immagine, float $secondi, string $formato, ?string $audio = null): array {
    $prompt = trim($prompt);
    if (mb_strlen($prompt) < 3) throw new RuntimeException('Descrivi il movimento (es. «Vlad saluta con la mano e sorride»).');
    [$w, $h] = ['orizzontale' => [1280, 768], 'verticale' => [768, 1280], 'quadrato' => [1024, 1024]][$formato] ?? [1280, 768];
    $frames = (int)max(49, min(241, round($secondi * 24)));
    $c = ['prompt' => $prompt, 'model' => MODELLO_VIDEO, 'width' => (string)$w, 'height' => (string)$h, 'seed' => (string)random_int(1, 2147483646),
        'frames' => (string)$frames, 'fps' => '24', 'first_frame_image' => curl_file(file_per_ai($immagine))];
    if ($audio !== null) $c['audio'] = curl_file(file_per_ai($audio));
    return $c;
}

// ---------- controllo dei lavori ----------
function lavori_in_attesa(): array { return db()->query("SELECT * FROM lavori_ai WHERE stato = 'attesa' ORDER BY id")->fetchAll(); }

function ai_errore(array $r, string $msg): void {
    db()->prepare("UPDATE lavori_ai SET stato = 'errore', errore = ?, aggiornato = ? WHERE id = ?")->execute([mb_substr($msg, 0, 250), date('Y-m-d H:i:s'), $r['id']]);
}

// Controlla un lavoro: se è pronto scarica il risultato e lo mette dove deve andare. Restituisce lo stato aggiornato.
function ai_controlla(array $r): string {
    if ($r['stato'] !== 'attesa') return $r['stato'];
    if (strtotime($r['creato']) < time() - 5 * 3600) { ai_errore($r, 'Troppo tempo: il risultato non è più disponibile.'); return 'errore'; }
    try { $j = deapi_richiesta('GET', '/jobs/' . rawurlencode($r['request_id'])); }
    catch (RuntimeException $e) { return 'attesa'; }                       // deAPI non risponde ora: si riprova dopo
    $stato = (string)($j['status'] ?? '');
    if (in_array($stato, ['error', 'failed', 'cancelled', 'canceled'], true) || !empty($j['error_code'])) {
        ai_errore($r, 'deAPI: ' . ((string)($j['error_reason'] ?? '') ?: 'generazione non riuscita') . (!empty($j['refunded']) ? ' (credito restituito)' : ''));
        return 'errore';
    }
    if ($stato !== 'done') { db()->prepare('UPDATE lavori_ai SET aggiornato = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $r['id']]); return 'attesa'; }
    // pronto: lo prende un solo controllo alla volta
    $st = db()->prepare("UPDATE lavori_ai SET stato = 'scarico' WHERE id = ? AND stato = 'attesa'"); $st->execute([$r['id']]);
    if (!$st->rowCount()) return 'scarico';
    try {
        $url = (string)($j['result_url'] ?? '');
        if (!preg_match('#^https://#', $url)) throw new RuntimeException('deAPI non ha restituito il file.');
        $tmp = scarica_url($url, 400 * 1024 * 1024);
        $suggerito = ['immagine' => 'image/png', 'scontorno' => 'image/png', 'voce' => 'audio/mpeg', 'musica' => 'audio/mpeg', 'animazione' => 'video/mp4', 'parla' => 'video/mp4'][$r['tipo']];
        $file = salva_media_da_percorso($tmp, false, $suggerito);
        db()->prepare("UPDATE lavori_ai SET stato = 'fatto', risultato = ?, aggiornato = ? WHERE id = ?")->execute([$file, date('Y-m-d H:i:s'), $r['id']]);
        $r['risultato'] = $file;
        ai_dopo($r, json_decode((string)$r['parametri'], true) ?: []);
        return 'fatto';
    } catch (Throwable $e) {
        ai_errore($r, $e->getMessage());
        return 'errore';
    }
}

// Cosa fare del risultato: riempire un contenuto, creare un contenuto nuovo, aggiungere un personaggio alla lavagna, togliere lo sfondo.
function ai_dopo(array $r, array $par): void {
    $file = $r['risultato'];
    if (!empty($par['poi_scontorno'])) {                       // immagine → poi si toglie lo sfondo (stesso destino)
        unset($par['poi_scontorno']);
        ai_avvia('scontorno', '/images/background-removals', ['image' => curl_file(file_per_ai($file)), 'model' => 'Ben2'], $par);
        return;
    }
    $tipo_c = ['immagine' => 'immagine', 'scontorno' => 'immagine', 'voce' => 'audio', 'musica' => 'audio', 'animazione' => 'video', 'parla' => 'video'][$r['tipo']];
    if ((int)$r['contenuto_id']) {
        db()->prepare('UPDATE contenuti SET file = ?, tipo = ? WHERE id = ?')->execute([$file, $tipo_c, (int)$r['contenuto_id']]);
    } elseif (($par['dest'] ?? '') === 'contenuto') {
        $lib = (int)($par['libro_id'] ?? 0);
        $codice = nuovo_codice($lib);
        db()->prepare('INSERT INTO contenuti (codice, libro_id, pagina, tipo, titolo, file, testo) VALUES (?,?,?,?,?,?,?)')
            ->execute([$codice, $lib, (int)($par['pagina'] ?? 0), $tipo_c, mb_substr((string)($par['titolo'] ?? ''), 0, 200), $file, (string)($par['testo'] ?? '')]);
        db()->prepare('UPDATE lavori_ai SET contenuto_id = ? WHERE id = ?')->execute([db()->lastInsertId(), $r['id']]);
    }
    if (($par['dest'] ?? '') === 'personaggio') {
        $lista = personaggi_extra();
        $lista[] = ['titolo' => mb_substr((string)($par['titolo'] ?? 'Personaggio'), 0, 60), 'src' => $file];
        imposta('personaggi_extra', json_encode(array_slice($lista, -60), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    if (($par['dest'] ?? '') === 'voce_vlad_prova' || ($par['dest'] ?? '') === 'voce_luce_prova') imposta($par['dest'], $file);
}

// Pose nuove di Vlad e Luce create con l'AI (sfondo tolto): compaiono tra i personaggi della lavagna.
function personaggi_extra(): array { $l = json_decode(impost('personaggi_extra'), true); return is_array($l) ? $l : []; }
