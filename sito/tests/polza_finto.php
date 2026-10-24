<?php
// Un finto Polza.ai per i test (php -S 127.0.0.1:PORTA tests/polza_finto.php): risponde come l'API vera, senza rete e senza costi.
// Registra l'ultima richiesta di ogni tipo in $POLZA_FINTO_DIR/<nome>.json così i test controllano cosa ha mandato il sito.
$dir = getenv('POLZA_FINTO_DIR') ?: sys_get_temp_dir();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$corpo = file_get_contents('php://input');
$in = json_decode($corpo, true) ?: [];
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
function rispondi(int $codice, array $j) { http_response_code($codice); header('Content-Type: application/json'); echo json_encode($j); exit; }
function salva(string $dir, string $nome, array $in): void { file_put_contents("$dir/$nome.json", json_encode($in, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); }

if (preg_match('#^/api/f/(img|mus|vid)\.(png|wav|mp4)$#', $path, $m)) {          // i file «prodotti»
    if ($m[1] === 'img') {                                                         // figura con contorno nero su sfondo bianco
        $im = imagecreatetruecolor(120, 160); imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagefilledellipse($im, 60, 80, 70, 90, imagecolorallocate($im, 0, 0, 0)); imagefilledellipse($im, 60, 80, 62, 82, imagecolorallocate($im, 255, 140, 0));
        header('Content-Type: image/png'); imagepng($im); exit;
    }
    header('Content-Type: ' . ($m[2] === 'mp4' ? 'video/mp4' : 'audio/wav')); readfile(getenv($m[2] === 'mp4' ? 'POLZA_FINTO_MP4' : 'POLZA_FINTO_WAV')); exit;
}
if ($auth !== 'Bearer prova-chiave-polza-0123456') rispondi(401, ['error' => ['message' => 'Invalid API key']]);

if ($path === '/api/v2/balance') rispondi(200, ['amount' => '100.00000000', 'available' => '99.50000000']);
if ($path === '/api/v1/audio/speech') {
    salva($dir, 'voce', $in);
    if (str_contains((string)($in['input'] ?? ''), 'POCO CREDITO')) rispondi(402, ['error' => ['message' => 'Insufficient funds']]);
    rispondi(200, ['audio' => base64_encode((string)file_get_contents(getenv('POLZA_FINTO_WAV'))), 'contentType' => 'audio/wav', 'model' => $in['model'] ?? '']);
}
if ($path === '/api/v1/audio/transcriptions') { salva($dir, 'trascrizione', $in); rispondi(200, ['text' => 'Ciao sono Luce']); }
if ($path === '/api/v1/media' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $m = (string)($in['model'] ?? ''); $inp = $in['input'] ?? [];
    $tipo = str_contains($m, 'seedance') || str_contains($m, 'kling') ? 'vid' : (str_contains($m, 'lyria') || str_contains($m, 'suno') ? 'mus' : 'img');
    salva($dir, "media-$tipo", $in);
    $id = str_contains((string)($inp['prompt'] ?? ''), 'FALLISCI') ? 'gen_bad' : "gen_$tipo";
    rispondi(200, ['id' => $id, 'object' => 'media.generation', 'status' => 'pending', 'model' => $m]);
}
if (preg_match('#^/api/v1/media/(gen_\w+)$#', $path, $m)) {
    $id = $m[1]; $cont = "$dir/$id.conta"; $n = (int)@file_get_contents($cont); file_put_contents($cont, $n + 1);
    if ($id === 'gen_bad') rispondi(200, ['id' => $id, 'status' => 'failed', 'error' => 'Il contenuto non è consentito']);
    if ($n < 1) rispondi(200, ['id' => $id, 'status' => 'processing']);                // la prima volta non è ancora pronto
    $f = ['gen_img' => 'img.png', 'gen_mus' => 'mus.wav', 'gen_vid' => 'vid.mp4'][$id];
    $url = 'http://' . $_SERVER['HTTP_HOST'] . '/api/f/' . $f;
    rispondi(200, ['id' => $id, 'status' => 'completed', 'data' => $id === 'gen_mus' ? [['url' => $url, 'duration' => 30]] : ['url' => $url]]);   // musica: data[0].url come nella documentazione
}
rispondi(404, ['error' => ['message' => 'Not found']]);
