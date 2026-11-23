<?php
// Studio AI: immagini con Vlad e Luce, sfondi tolti, voci (Luce e Vlad «dirette» a parole), musica, animazioni e personaggi che parlano.
// Funziona con Polza.ai (consigliato: una chiave sola per tutto) oppure, se manca la chiave Polza, con deAPI (vecchio modo).
// Ogni richiesta diventa un «lavoro» (tabella lavori_ai): si avvia subito e poi la pagina dell'admin lo controlla ogni pochi
// secondi; quando è pronto il file viene scaricato in uploads/media. Le voci e gli sfondi tolti con Polza sono immediati.
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
// Modelli di Polza.ai: immagini con i personaggi come riferimento (Nano Banana 2.1, ~3 ₽), voci (gpt-4o-mini-tts, centesimi),
// musica (Lyria 3: clip da 4 ₽, brano intero da 8 ₽), video (Seedance 2 Mini, 480p, ~2,6 ₽ al secondo)
const POLZA_MODELLO_IMMAGINI = 'google/gemini-nano-banana-2.1';
const POLZA_MODELLO_VOCE = 'openai/gpt-4o-mini-tts';
const POLZA_MODELLO_MUSICA_CLIP = 'google/lyria-3-clip-preview';
const POLZA_MODELLO_MUSICA = 'google/lyria-3-pro-preview';
const POLZA_MODELLO_VIDEO = 'bytedance/seedance-2-mini';
const AI_VLAD_PREDEFINITA = 'Voce di un ragazzo di 13 anni: allegra, chiara e paziente, come chi spiega la grammatica a un amico. Parla in italiano.';

// Voci disponibili per «leggi questo testo»: quelle pronte di Kokoro più Luce e Vlad (se configurate).
function voci_disponibili(): array {
    if (ai_provider() === 'polza') return ['luce' => '🐕 Luce (voce AI diretta a parole)', 'vlad' => '🧒 Vlad (voce da ragazzo)'] + VOCI_POLZA;
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

// Scrive la riga di un lavoro in attesa e restituisce il suo id.
function ai_registra_lavoro(string $tipo, string $request_id, array $par, int $contenuto): int {
    db()->prepare('INSERT INTO lavori_ai (tipo, request_id, parametri, contenuto_id, aggiornato) VALUES (?,?,?,?,?)')
        ->execute([$tipo, $request_id, json_encode($par, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $contenuto, date('Y-m-d H:i:s')]);
    $nuovo = (int)db()->lastInsertId();             // prima di registra(), che scrive un'altra riga (nel registro attività)
    registra('Lavoro AI avviato', $tipo);
    return $nuovo;
}
// Un lavoro già finito (voce o sfondo tolto fatti subito, senza attesa): salva la riga e fa quello che c'è da fare col file.
function ai_lavoro_fatto(string $tipo, string $file, array $par, int $contenuto = 0): int {
    $par['provider'] = ai_provider();
    db()->prepare("INSERT INTO lavori_ai (tipo, stato, request_id, parametri, risultato, contenuto_id, aggiornato) VALUES (?,?,?,?,?,?,?)")
        ->execute([$tipo, 'fatto', '', json_encode($par, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $file, $contenuto, date('Y-m-d H:i:s')]);
    $nuovo = (int)db()->lastInsertId();
    registra('Lavoro AI fatto', $tipo);
    ai_dopo(['id' => $nuovo, 'tipo' => $tipo, 'risultato' => $file, 'contenuto_id' => $contenuto], $par);
    return $nuovo;
}

// Avvia un lavoro. Con Polza $campi viene da ai_campi_*() ed è già nella forma giusta ($percorso non serve);
// con deAPI si manda la richiesta a $percorso. $par = dati per sapere cosa fare del risultato.
function ai_avvia(string $tipo, string $percorso, array $campi, array $par, int $contenuto = 0): int {
    if (!isset(AI_TIPI[$tipo])) throw new LogicException('Tipo di lavoro sconosciuto');
    if (!empty($campi['_polza'])) {
        if (!empty($campi['voce'])) {                  // la voce è pronta subito: Polza risponde con l'audio
            $j = polza_richiesta('POST', '/v1/audio/speech', $campi['voce'], 150);
            $bin = base64_decode((string)($j['audio'] ?? ''), true);
            if ($bin === false || $bin === '') throw new RuntimeException('Polza.ai non ha restituito l\'audio.');
            $tmp = tempnam(sys_get_temp_dir(), 'tts'); file_put_contents($tmp, $bin);
            return ai_lavoro_fatto($tipo, salva_media_da_percorso($tmp, false, 'audio/mpeg'), $par, $contenuto);
        }
        if (($campi['input']['background'] ?? '') === 'transparent') unset($par['poi_scontorno']);
        $j = polza_richiesta('POST', '/v1/media', ['model' => $campi['modello'], 'input' => $campi['input'], 'async' => true], 120);
        $id = (string)($j['id'] ?? '');
        if ($id === '') throw new RuntimeException('Polza.ai non ha accettato la richiesta.');
        return ai_registra_lavoro($tipo, $id, $par + ['provider' => 'polza'], $contenuto);
    }
    $id = (string)(deapi_richiesta('POST', $percorso, $campi, 120)['request_id'] ?? '');
    if ($id === '') throw new RuntimeException('deAPI non ha accettato la richiesta.');
    return ai_registra_lavoro($tipo, $id, $par, $contenuto);
}

// Toglie lo sfondo a un'immagine già salvata. Con Polza lo fa il sito (immediato e gratis: lo sfondo bianco dell'immagine
// diventa trasparente), con deAPI c'è il lavoro «Ben2».
function ai_togli_sfondo(string $file, array $par, int $contenuto = 0): void {
    if (ai_provider() === 'polza') {
        ai_lavoro_fatto('scontorno', salva_media_da_percorso(png_senza_sfondo(file_per_ai($file)), false, 'image/png'), $par, $contenuto);
        return;
    }
    ai_avvia('scontorno', '/images/background-removals', ['image' => curl_file(file_per_ai($file)), 'model' => 'Ben2'], $par, $contenuto);
}

// Rende trasparente lo sfondo chiaro che tocca i bordi dell'immagine (le figure a fumetto hanno il contorno nero, quindi il bianco
// dentro la figura resta). Restituisce un file PNG temporaneo.
function png_senza_sfondo(string $percorso): string {
    if (!function_exists('imagecreatefromstring')) throw new RuntimeException('Per togliere lo sfondo serve l\'estensione GD di PHP.');
    $src = @imagecreatefromstring((string)file_get_contents($percorso));
    if (!$src) throw new RuntimeException('Immagine non leggibile.');
    $w = imagesx($src); $h = imagesy($src);
    $im = imagecreatetruecolor($w, $h); imagealphablending($im, false); imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagecopy($im, $src, 0, 0, 0, 0, $w, $h); imagedestroy($src);
    $chiaro = function (int $x, int $y) use ($im): int { $c = imagecolorat($im, $x, $y); return min(($c >> 16) & 255, ($c >> 8) & 255, $c & 255); };
    $visto = str_repeat("\0", $w * $h); $pila = [];
    for ($x = 0; $x < $w; $x++) { $pila[] = $x; $pila[] = ($h - 1) * $w + $x; }
    for ($y = 0; $y < $h; $y++) { $pila[] = $y * $w; $pila[] = $y * $w + $w - 1; }
    while ($pila) {
        $i = array_pop($pila);
        if ($visto[$i] !== "\0") continue;
        $x = $i % $w; $y = intdiv($i, $w);
        if ($chiaro($x, $y) < 228) continue;                       // contorno o colore: il fondo finisce qui
        $visto[$i] = "\1";
        imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, 255, 255, 255, 127));
        if ($x > 0) $pila[] = $i - 1; if ($x < $w - 1) $pila[] = $i + 1; if ($y > 0) $pila[] = $i - $w; if ($y < $h - 1) $pila[] = $i + $w;
    }
    // bordo morbido: i grigi chiari accanto al fondo diventano semitrasparenti (niente alone bianco sulla lavagna scura)
    for ($y = 1; $y < $h - 1; $y++) for ($x = 1; $x < $w - 1; $x++) {
        $i = $y * $w + $x;
        if ($visto[$i] !== "\0") continue;
        if ($visto[$i - 1] === "\0" && $visto[$i + 1] === "\0" && $visto[$i - $w] === "\0" && $visto[$i + $w] === "\0") continue;
        $l = $chiaro($x, $y);
        if ($l > 150) { $c = imagecolorat($im, $x, $y); imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, ($c >> 16) & 255, ($c >> 8) & 255, $c & 255, (int)(127 * ($l - 150) / 78))); }
    }
    $tmp = tempnam(sys_get_temp_dir(), 'sf'); imagepng($im, $tmp, 6); imagedestroy($im);
    return $tmp;
}

// ---------- richieste ----------
function ai_campi_voce(string $voce, string $testo): array {
    $testo = trim($testo);
    if (mb_strlen($testo) < 10) throw new RuntimeException('Il testo da leggere è troppo corto (almeno 10 caratteri).');
    if (mb_strlen($testo) > 5000) throw new RuntimeException('Il testo è troppo lungo (massimo 5000 caratteri): dividilo in più audio.');
    if (ai_provider() === 'polza') {               // Polza: voce pronta + istruzioni su come parlare (Luce e Vlad si «dirigono» a parole)
        $istr = 'Leggi in italiano con voce chiara e calma, adatta a una lezione di grammatica per ragazzi.';
        if ($voce === 'luce') { $v = 'coral'; $istr = trim(impost('voce_luce')) ?: AI_LUCE_PREDEFINITA; }
        elseif ($voce === 'vlad') { $v = 'ash'; $istr = trim(impost('voce_vlad_stile')) ?: AI_VLAD_PREDEFINITA; }
        else $v = isset(VOCI_POLZA[$voce]) ? $voce : 'nova';
        return ['_polza' => true, 'voce' => ['model' => POLZA_MODELLO_VOCE, 'input' => $testo, 'voice' => $v, 'instructions' => mb_substr($istr, 0, 1000), 'response_format' => 'mp3']];
    }
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
    if (ai_provider() === 'polza') {
        $rapporto = $posa ? '2:3' : (['orizzontale' => '3:2', 'verticale' => '2:3', 'quadrato' => '1:1', 'pagina' => '3:4'][$formato] ?? '3:2');
        $imgs = [];
        foreach (array_slice(array_keys($riferimenti), 0, 3) as $r) $imgs[] = polza_file(file_per_ai($r));
        return ['/media', ['_polza' => true, 'modello' => POLZA_MODELLO_IMMAGINI,
            'input' => ['prompt' => $testo, 'aspect_ratio' => $rapporto, 'image_resolution' => '1K', 'output_format' => 'png'] + ($imgs ? ['images' => $imgs] : [])]];
    }
    $campi = ['prompt' => $testo, 'model' => MODELLO_IMMAGINI, 'width' => (string)$w, 'height' => (string)$h, 'steps' => '4', 'seed' => (string)random_int(1, 2147483646)];
    $i = 0;
    foreach (array_slice(array_keys($riferimenti), 0, 3) as $r) $campi['images[' . $i++ . ']'] = curl_file(file_per_ai($r));
    return [$i ? '/images/edits' : '/images/generations', $campi];
}

function ai_campi_musica(string $descr, int $durata, int $bpm): array {
    $descr = trim($descr);
    if (mb_strlen($descr) < 3) throw new RuntimeException('Descrivi la musica (es. «allegra, chitarra e flauto, per bambini»).');
    if (ai_provider() === 'polza') {               // Lyria 3: prezzo fisso a brano; la «clip» è breve, il brano intero dura di più
        $p = 'Musica strumentale, senza voce e senza cantato, adatta a un video didattico per ragazzi. ' . mb_substr($descr, 0, 300)
            . ($bpm >= 50 && $bpm <= 200 ? ". Ritmo: circa $bpm battiti al minuto." : '');
        return ['_polza' => true, 'modello' => $durata > 40 ? POLZA_MODELLO_MUSICA : POLZA_MODELLO_MUSICA_CLIP, 'input' => ['prompt' => $p]];
    }
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
    if (ai_provider() === 'polza') {               // Seedance 2 Mini a 480p: l'immagine è il personaggio; la voce la genera il video stesso
        if ($audio !== null) {                     // il modello non usa l'audio come riferimento: gli si fa dire la stessa frase (trascritta)
            $prompt .= '. Il personaggio dice in italiano, con la bocca a tempo con le parole: «' . polza_trascrivi(file_per_ai($audio)) . '»';
        }
        $in = ['prompt' => $prompt, 'aspect_ratio' => ['orizzontale' => '16:9', 'verticale' => '9:16', 'quadrato' => '1:1'][$formato] ?? '16:9',
            'resolution' => '480p', 'duration' => (string)max(4, min(15, (int)round($secondi))), 'images' => [polza_file(file_per_ai($immagine))],
            'generate_audio' => $audio !== null ? 'true' : 'false'];
        return ['_polza' => true, 'modello' => POLZA_MODELLO_VIDEO, 'input' => $in];
    }
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
    $par = json_decode((string)$r['parametri'], true) ?: [];
    $polza = ($par['provider'] ?? '') === 'polza';
    $nome = $polza ? 'Polza.ai' : 'deAPI';
    try { $j = $polza ? polza_richiesta('GET', '/v1/media/' . rawurlencode($r['request_id'])) : deapi_richiesta('GET', '/jobs/' . rawurlencode($r['request_id'])); }
    catch (RuntimeException $e) {
        if ($polza && in_array($e->getCode(), [401, 402, 403], true)) { ai_errore($r, $e->getMessage()); return 'errore'; }   // chiave rifiutata o credito finito: inutile riprovare
        return 'attesa';                                                   // il servizio non risponde ora: si riprova dopo
    }
    $stato = (string)($j['status'] ?? '');
    if ($polza) {
        $fallito = $stato === 'failed'; $pronto = $stato === 'completed'; $motivo = $fallito ? polza_errore_testo($j) : '';
    } else {
        $fallito = in_array($stato, ['error', 'failed', 'cancelled', 'canceled'], true) || !empty($j['error_code']); $pronto = $stato === 'done';
        $motivo = ((string)($j['error_reason'] ?? '') ?: 'generazione non riuscita') . (!empty($j['refunded']) ? ' (credito restituito)' : '');
    }
    if ($fallito) { ai_errore($r, "$nome: $motivo"); return 'errore'; }
    if (!$pronto) { db()->prepare('UPDATE lavori_ai SET aggiornato = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $r['id']]); return 'attesa'; }
    // pronto: lo prende un solo controllo alla volta
    $st = db()->prepare("UPDATE lavori_ai SET stato = 'scarico' WHERE id = ? AND stato = 'attesa'"); $st->execute([$r['id']]);
    if (!$st->rowCount()) return 'scarico';
    try {
        $url = $polza ? polza_url_risultato($j) : (string)($j['result_url'] ?? '');
        if ($url === '' || (!$polza && !preg_match('#^https://#', $url))) throw new RuntimeException("$nome non ha restituito il file.");
        $tmp = $polza ? polza_scarica($url, 400 * 1024 * 1024) : deapi_scarica($url, 400 * 1024 * 1024);
        $suggerito = ['immagine' => 'image/png', 'scontorno' => 'image/png', 'voce' => 'audio/mpeg', 'musica' => 'audio/mpeg', 'animazione' => 'video/mp4', 'parla' => 'video/mp4'][$r['tipo']];
        $file = salva_media_da_percorso($tmp, false, $suggerito);
        db()->prepare("UPDATE lavori_ai SET stato = 'fatto', risultato = ?, aggiornato = ? WHERE id = ?")->execute([$file, date('Y-m-d H:i:s'), $r['id']]);
        $r['risultato'] = $file;
        ai_dopo($r, $par);
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
        ai_togli_sfondo($file, $par);
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
