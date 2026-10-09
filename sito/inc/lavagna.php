<?php
// Lavagna ed editor video (per l'admin e per i prof): pagina dell'editor e controlli di accesso.
require_once __DIR__ . '/classi.php';

// Chi sta usando la lavagna: ['admin' => bool, 'prof' => riga|null]. Nessuno dei due = niente accesso.
function utente_lavagna(): array { return ['admin' => !empty($_SESSION['admin']), 'prof' => prof_corrente()]; }

// La lavagna è modificabile da chi la chiede? (admin: le sue, prof_id = 0; prof: solo le proprie)
function lavagna_permessa(int $id, array $u): ?array {
    $st = db()->prepare('SELECT * FROM lavagne WHERE id = ?'); $st->execute([$id]);
    $l = $st->fetch();
    if (!$l) return null;
    if ($u['prof'] && (int)$l['prof_id'] === (int)$u['prof']['id']) return $l;
    if (!$u['prof'] && $u['admin'] && (int)$l['prof_id'] === 0) return $l;
    return null;
}

// Pagina dell'editor (a tutto schermo). $pre = prefisso per assets ('' o '../'), $api = indirizzo di lavagna-api.php,
// $indietro = dove torna il pulsante ←, $extra = dati per il pannello di salvataggio (classi del prof o libri dell'admin).
function pagina_editor_lavagna(?array $l, string $pre, string $api, string $indietro, array $extra, string $come): void {
    $cfg = [
        'come' => $come,
        'id' => $l ? (int)$l['id'] : 0, 'titolo' => $l['titolo'] ?? '', 'dati' => $l ? (json_decode((string)$l['dati'], true) ?: null) : null,
        'video' => $l && $l['video'] !== '' ? url_media($l['video'], $pre) : '', 'classe_id' => (int)($l['classe_id'] ?? 0), 'libro_id' => (int)($l['libro_id'] ?? 0),
        'api' => $api, 'csrf' => csrf(), 'pre' => $pre, 'extra' => $extra,
        'personaggi' => [
            ['Vlad (davanti)', $pre . 'assets/personaggi/vlad-davanti.png'], ['Vlad (con il tablet)', $pre . 'assets/personaggi/vlad-tablet.png'],
            ['Vlad (di profilo)', $pre . 'assets/personaggi/vlad-profilo.png'], ['Luce (davanti)', $pre . 'assets/personaggi/luce-davanti.png'],
            ['Luce (di profilo)', $pre . 'assets/personaggi/luce-profilo.png'],
        ],
        'musiche' => array_map(fn($r) => ['titolo' => $r['titolo'] !== '' ? $r['titolo'] : $r['codice'], 'src' => url_media($r['file'], $pre)],
            db()->query("SELECT codice, titolo, file FROM contenuti WHERE tipo = 'audio' AND file != '' ORDER BY id DESC LIMIT 200")->fetchAll()),
    ];
    ?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title><?= e($cfg['titolo'] !== '' ? $cfg['titolo'] : 'Nuova lavagna') ?> · Lavagna</title>
<link rel="icon" href="<?= e($pre) ?>assets/favicon.png"><link rel="stylesheet" href="<?= e($pre) ?>assets/lavagna.css"></head>
<body class="lavagna-app">
<header class="lv-barra">
  <a class="lv-indietro" href="<?= e($indietro) ?>" title="Torna indietro">←</a>
  <input type="text" id="lv-titolo" placeholder="Titolo del video" maxlength="200" aria-label="Titolo">
  <span id="lv-stato" class="lv-muto"></span>
  <span class="lv-spazio"></span>
  <button type="button" id="lv-annulla" title="Annulla (Ctrl+Z)">↶</button><button type="button" id="lv-ripeti" title="Ripeti (Ctrl+Y)">↷</button>
  <button type="button" id="lv-salva" title="Salva (Ctrl+S)">💾 Salva</button>
  <button type="button" id="lv-play" title="Riproduci dall'inizio (spazio)">▶ Riproduci</button>
  <button type="button" id="lv-dal-vivo" class="rosso" title="Registra dal vivo: tutto quello che fai sulla lavagna diventa video">⏺ Dal vivo</button>
  <button type="button" id="lv-esporta" class="arancio" title="Registra l'animazione dei passi come video">🎬 Crea il video</button>
  <button type="button" id="lv-schermo" title="Schermo intero">⛶</button>
  <button type="button" id="lv-aiuto-b" title="Aiuto e scorciatoie (?)">?</button>
</header>
<div class="lv-corpo">
  <nav class="lv-strumenti" id="lv-strumenti" aria-label="Strumenti"></nav>
  <main class="lv-scena" id="lv-scena">
    <div class="lv-tela-box" id="lv-tela-box"><canvas id="lv-tela" width="1920" height="1080"></canvas><canvas id="lv-sopra" width="1920" height="1080"></canvas>
      <textarea id="lv-scrivi" hidden spellcheck="false"></textarea></div>
    <div id="lv-registra-info" class="lv-reg" hidden><span class="punto"></span> <b id="lv-reg-tempo">0:00</b> <button type="button" id="lv-reg-stop">⏹ Ferma</button></div>
    <div id="lv-conto" class="lv-conto" hidden></div>
  </main>
  <aside class="lv-pannello" id="lv-pannello"></aside>
</div>
<footer class="lv-passi">
  <div class="lv-passi-com"><b>Passi</b>
    <button type="button" id="lv-passo-nuovo" title="Aggiungi un passo (copia di quello attuale)">+ Passo</button>
    <button type="button" id="lv-passo-togli" title="Togli il passo attuale">🗑</button>
    <label>Durata <input type="number" id="lv-durata" min="0.5" max="60" step="0.5"> s</label>
    <label title="Gli oggetti nuovi del passo compaiono uno dopo l'altro"><input type="checkbox" id="lv-seq"> uno dopo l'altro</label>
    <label>Movimento <select id="lv-trans"><option value="morbida">morbido</option><option value="lineare">costante</option><option value="rimbalzo">con rimbalzo</option><option value="scatto">a scatto</option></select></label>
    <span class="lv-muto" id="lv-tempo-tot"></span>
  </div>
  <div class="lv-miniature" id="lv-miniature"></div>
  <input type="range" id="lv-cursore" min="0" max="1000" value="0" aria-label="Tempo">
</footer>
<dialog id="lv-dialogo"><form method="dialog" id="lv-dialogo-form"></form></dialog>
<div id="lv-aiuto" class="lv-aiuto" hidden></div>
<input type="file" id="lv-file-img" accept="image/*" hidden><input type="file" id="lv-file-audio" accept="audio/*" hidden>
<script>window.LAVAGNA = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e($pre) ?>assets/lavagna.js"></script>
</body></html><?php
}

// Pulisce il progetto che arriva dal browser: solo JSON valido e non enorme (il disegno lo fa il browser con textContent/canvas).
function pulisci_progetto(string $json): string {
    if (strlen($json) > 5 * 1024 * 1024) throw new RuntimeException('Il progetto è troppo grande (max 5 MB): togli qualche immagine o disegno.');
    $d = json_decode($json, true);
    if (!is_array($d) || !isset($d['oggetti'], $d['passi']) || !is_array($d['oggetti']) || !is_array($d['passi'])) throw new RuntimeException('Progetto non valido.');
    if (count($d['oggetti']) > 3000 || count($d['passi']) > 300) throw new RuntimeException('Troppi oggetti o passi.');
    return json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
