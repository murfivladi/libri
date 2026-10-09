<?php
// Studio AI (deAPI): immagini con Vlad e Luce, pose nuove per la lavagna, voci di Luce e di Vlad, musica, animazioni, personaggi che parlano.
require __DIR__ . '/_admin.php';
require_once __DIR__ . '/../inc/ai.php';

// controllo dei lavori in attesa (chiamato ogni pochi secondi dalle pagine aperte)
if (($_GET['azione'] ?? '') === 'controlla') {
    header('Content-Type: application/json; charset=utf-8');
    $fatti = [];
    foreach (array_slice(lavori_in_attesa(), 0, 8) as $r) { $s = ai_controlla($r); if ($s !== 'attesa') $fatti[] = ['id' => (int)$r['id'], 'stato' => $s]; }
    echo json_encode(['in_attesa' => count(lavori_in_attesa()), 'cambiati' => $fatti]);
    exit;
}

$libri = db()->query("SELECT id, titolo FROM libri WHERE sezione = 'scuola' AND eliminato IS NULL ORDER BY ordine, numero, id")->fetchAll();
$vista = (string)($_GET['vista'] ?? 'immagini');
$PERSONAGGI = ['assets/personaggi/vlad-scheda.jpg' => 'Vlad', 'assets/personaggi/luce-scheda.jpg' => 'Luce'];

// immagini utilizzabili come primo fotogramma: pose di base, pose create con l'AI, immagini della galleria
function immagini_scelta(): array {
    $o = ['assets/personaggi/vlad-davanti.png' => 'Vlad (davanti)', 'assets/personaggi/vlad-tablet.png' => 'Vlad con il tablet', 'assets/personaggi/luce-davanti.png' => 'Luce (davanti)', 'assets/personaggi/luce-profilo.png' => 'Luce (profilo)', 'assets/scuola-vlad-luce.png' => 'Vlad e Luce insieme'];
    foreach (personaggi_extra() as $p) $o[$p['src']] = '✨ ' . $p['titolo'];
    foreach (db()->query("SELECT risultato, parametri FROM lavori_ai WHERE tipo IN ('immagine','scontorno') AND stato = 'fatto' ORDER BY id DESC LIMIT 40") as $r) {
        $par = json_decode((string)$r['parametri'], true) ?: [];
        $o[$r['risultato']] = '🎨 ' . mb_substr((string)($par['titolo'] ?? $par['prompt'] ?? 'immagine'), 0, 50);
    }
    return $o;
}
function scelta_valida(string $v, array $lista): string { if (!isset($lista[$v])) throw new RuntimeException('Scegli un\'immagine dall\'elenco.'); return $v; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = (string)($_POST['azione'] ?? '');
    $dest_contenuto = fn() => ['dest' => 'contenuto', 'libro_id' => (int)($_POST['libro_id'] ?? 0), 'titolo' => trim((string)($_POST['titolo'] ?? '')), 'pagina' => (int)($_POST['pagina'] ?? 0)];
    try {
        switch ($az) {
            case 'immagine':
                $rif = [];
                foreach ((array)($_POST['rif'] ?? []) as $r) if (isset($PERSONAGGI[$r])) $rif[$r] = $PERSONAGGI[$r];
                $dest = (string)($_POST['dest'] ?? 'galleria');
                [$percorso, $campi] = ai_campi_immagine((string)$_POST['prompt'], $rif, (string)($_POST['formato'] ?? ''), $dest === 'personaggio');
                $par = ['prompt' => trim((string)$_POST['prompt']), 'titolo' => trim((string)($_POST['titolo'] ?? ''))];
                if ($dest === 'contenuto') $par = $dest_contenuto() + $par;
                if ($dest === 'personaggio') { $par['dest'] = 'personaggio'; $par['poi_scontorno'] = 1; if ($par['titolo'] === '') $par['titolo'] = 'Posa nuova'; }
                elseif (!empty($_POST['scontorno'])) $par['poi_scontorno'] = 1;
                ai_avvia('immagine', $percorso, $campi, $par);
                flash('Immagine in preparazione: di solito servono 10–60 secondi.'); torna('ai.php?vista=immagini');
            case 'scontorna':
                $f = scelta_valida((string)$_POST['img'], immagini_scelta());
                ai_avvia('scontorno', '/images/background-removals', ['image' => curl_file(file_per_ai($f)), 'model' => 'Ben2'],
                    ['dest' => !empty($_POST['personaggio']) ? 'personaggio' : 'galleria', 'titolo' => trim((string)($_POST['titolo'] ?? 'Posa nuova'))]);
                flash('Tolgo lo sfondo…'); torna('ai.php?vista=immagini');
            case 'voce_luce':
                imposta('voce_luce', mb_substr(trim((string)$_POST['descrizione']), 0, 500));
                if (!empty($_POST['prova'])) { ai_avvia('voce', '/audio/speech', ai_campi_voce('luce', (string)$_POST['testo_prova']), ['dest' => 'voce_luce_prova']); flash('Voce di Luce salvata: la prova arriva tra qualche minuto.'); }
                else flash('Voce di Luce salvata.');
                torna('ai.php?vista=voci');
            case 'voce_vlad':
                if (empty($_POST['consenso'])) throw new RuntimeException('Per copiare la voce di Vlad serve il permesso suo e dei genitori: spunta la casella.');
                $testo = trim((string)($_POST['testo_ref'] ?? ''));
                if (!empty($_FILES['ref']['name'])) {
                    $v = salva_media('ref', 15 * 1024 * 1024);
                    if ($GLOBALS['ultimo_media_tipo'] !== 'audio' && !str_ends_with($v, '.webm')) throw new RuntimeException('Serve una registrazione audio.');
                    $vecchio = impost('voce_vlad_ref'); imposta('voce_vlad_ref', $v); if ($vecchio !== '' && $vecchio !== $v) elimina_fisico('media', nome_file($vecchio));
                }
                if (impost('voce_vlad_ref') === '') throw new RuntimeException('Registra (o carica) prima la voce di Vlad: 5–15 secondi.');
                if (mb_strlen($testo) < 10) throw new RuntimeException('Scrivi esattamente le parole dette nella registrazione.');
                imposta('voce_vlad_testo', mb_substr($testo, 0, 1000));
                if (!empty($_POST['prova'])) ai_avvia('voce', '/audio/speech', ai_campi_voce('vlad', (string)$_POST['testo_prova']), ['dest' => 'voce_vlad_prova']);
                flash('Voce di Vlad salvata' . (!empty($_POST['prova']) ? ': la prova arriva tra qualche minuto.' : '.')); torna('ai.php?vista=voci');
            case 'togli_vlad':
                if (impost('voce_vlad_ref') !== '') elimina_fisico('media', nome_file(impost('voce_vlad_ref')));
                imposta('voce_vlad_ref', ''); imposta('voce_vlad_testo', ''); flash('Voce di Vlad cancellata.'); torna('ai.php?vista=voci');
            case 'musica':
                $par = $dest_contenuto(); if ($par['titolo'] === '') $par['titolo'] = 'Musica: ' . mb_substr(trim((string)$_POST['descrizione']), 0, 60);
                ai_avvia('musica', '/audio/music', ai_campi_musica((string)$_POST['descrizione'], (int)($_POST['durata'] ?? 30), (int)($_POST['bpm'] ?? 0)), $par);
                flash('Musica in preparazione: comparirà in «Audio e video» e nella lavagna.'); torna('ai.php?vista=musica');
            case 'animazione':
                $img = scelta_valida((string)$_POST['img'], immagini_scelta());
                $par = $dest_contenuto(); if ($par['titolo'] === '') $par['titolo'] = mb_substr(trim((string)$_POST['prompt']), 0, 80);
                ai_avvia('animazione', '/videos/animations', ai_campi_video((string)$_POST['prompt'], $img, (float)($_POST['secondi'] ?? 5), (string)($_POST['formato'] ?? '')), $par);
                flash('Animazione in preparazione: può servire qualche minuto.'); torna('ai.php?vista=animazione');
            case 'parla':
                $img = scelta_valida((string)$_POST['img'], immagini_scelta());
                $audio = (string)($_POST['audio'] ?? '');
                $ok = db()->prepare("SELECT 1 FROM contenuti WHERE tipo = 'audio' AND file = ?"); $ok->execute([$audio]);
                if (!$ok->fetchColumn() && !in_array($audio, [impost('voce_luce_prova'), impost('voce_vlad_prova')], true)) throw new RuntimeException('Scegli un audio dall\'elenco.');
                $par = $dest_contenuto(); if ($par['titolo'] === '') $par['titolo'] = 'Personaggio che parla';
                ai_avvia('parla', '/videos/audio-syncs', ai_campi_video(trim((string)$_POST['prompt']) ?: 'il personaggio parla guardando chi lo ascolta, con le labbra a tempo con la voce', $img, (float)($_POST['secondi'] ?? 5), (string)($_POST['formato'] ?? ''), $audio), $par);
                flash('Video in preparazione: può servire qualche minuto.'); torna('ai.php?vista=animazione');
            case 'elimina_lavoro':
                $st = db()->prepare('SELECT * FROM lavori_ai WHERE id = ?'); $st->execute([(int)$_POST['id']]);
                if ($r = $st->fetch()) { db()->prepare('DELETE FROM lavori_ai WHERE id = ?')->execute([$r['id']]); if ($r['risultato'] !== '') elimina_file('media', $r['risultato']); }
                flash('Eliminato.'); torna('ai.php?vista=' . urlencode($vista));
            case 'togli_personaggio':
                $src = (string)$_POST['src'];
                imposta('personaggi_extra', json_encode(array_values(array_filter(personaggi_extra(), fn($p) => $p['src'] !== $src)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                flash('Tolto dai personaggi della lavagna.'); torna('ai.php?vista=immagini');
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'errore'); torna('ai.php?vista=' . urlencode($vista));
    }
    torna('ai.php');
}

admin_testata('✨ Studio AI', 'ai');
$attesa = count(lavori_in_attesa());
$tab = fn($k, $t) => '<a href="ai.php?vista=' . $k . '"' . ($vista === $k ? ' class="att"' : '') . '>' . $t . '</a>';
$opzLibri = function () use ($libri) { ?>
  <label>Libro <select name="libro_id"><option value="0">— nessun libro —</option><?php foreach ($libri as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['titolo']) ?></option><?php endforeach; ?></select></label>
  <label>Pagina <small>(0 = nessuna)</small><input type="number" name="pagina" min="0" value="0"></label>
<?php };
$selImg = function (string $nome = 'img', string $scelto = '') { ?>
  <label>Immagine di partenza <select name="<?= $nome ?>" required><?php foreach (immagini_scelta() as $v => $t): ?><option value="<?= e($v) ?>" <?= $v === $scelto ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></label>
<?php };
if (deapi_chiave() === ''): ?><p class="errore">Manca la chiave deAPI: inseriscila in <a href="scuola.php">Scuola → deAPI</a>.</p><?php endif; ?>
<?php if ($attesa): ?><p class="avviso" data-attesa-ai>⏳ Lavori in preparazione: <b><?= $attesa ?></b>. La pagina si aggiorna da sola quando sono pronti.</p><?php endif; ?>
<nav class="schede-ai"><?= $tab('immagini', '🎨 Immagini') . $tab('voci', '🗣 Voci') . $tab('musica', '🎵 Musica') . $tab('animazione', '🎞 Animazioni') . $tab('lavori', '📋 Tutti i lavori') ?></nav>

<?php if ($vista === 'immagini'): ?>
<form method="post" class="pannello"><?= csrf_campo() ?><input type="hidden" name="azione" value="immagine">
  <h2 style="margin-top:0">Nuova immagine con Vlad e Luce</h2>
  <div class="form-griglia">
    <label class="largo">Cosa deve esserci <textarea name="prompt" rows="3" required placeholder="es. Vlad alla lavagna indica la parola «Luce» cerchiata in verde, Luce lo guarda con gli occhiali, in un'aula luminosa"></textarea></label>
    <div><b>Personaggi di riferimento</b><?php foreach ($PERSONAGGI as $v => $t): ?><label class="spunta"><input type="checkbox" name="rif[]" value="<?= e($v) ?>" checked> <?= e($t) ?> <small>(dalla scheda a 360°)</small></label><?php endforeach; ?></div>
    <label>Formato <select name="formato"><option value="orizzontale">Orizzontale (lavagna, video)</option><option value="pagina">Pagina del libro</option><option value="quadrato">Quadrato</option><option value="verticale">Verticale</option></select></label>
    <label>Titolo <input type="text" name="titolo" maxlength="120" placeholder="es. Vlad alla lavagna"></label>
    <div class="largo"><b>Dove va</b>
      <label class="spunta"><input type="radio" name="dest" value="galleria" checked> Solo nella galleria qui sotto</label>
      <label class="spunta"><input type="radio" name="dest" value="contenuto"> Immagine del libro (con il suo codice)</label>
      <label class="spunta"><input type="radio" name="dest" value="personaggio"> Posa nuova per la lavagna: un solo personaggio intero (es. «Luce che salta felice»), lo sfondo viene tolto da solo</label>
      <label class="spunta"><input type="checkbox" name="scontorno" value="1"> Togli lo sfondo</label></div>
    <?php $opzLibri(); ?>
  </div>
  <p><button class="bottone">🎨 Crea l'immagine</button> <span class="muto">FLUX.2 con le schede dei personaggi come riferimento.</span></p>
</form>
<?php $gal = db()->query("SELECT * FROM lavori_ai WHERE tipo IN ('immagine','scontorno') AND stato = 'fatto' ORDER BY id DESC LIMIT 60")->fetchAll(); ?>
<h2>Galleria</h2>
<div class="galleria-ai">
<?php foreach ($gal as $g): $par = json_decode((string)$g['parametri'], true) ?: []; ?>
  <figure><a href="<?= e(url_media($g['risultato'], '../')) ?>" target="_blank"><img src="<?= e(url_media($g['risultato'], '../')) ?>" alt="" loading="lazy" class="<?= $g['tipo'] === 'scontorno' ? 'scacchi' : '' ?>"></a>
    <figcaption><?= e(mb_substr((string)($par['titolo'] ?: ($par['prompt'] ?? '')), 0, 80)) ?></figcaption>
    <form method="post" class="azioni-riga"><?= csrf_campo() ?><input type="hidden" name="img" value="<?= e($g['risultato']) ?>"><input type="hidden" name="titolo" value="<?= e((string)($par['titolo'] ?? '')) ?>">
      <?php if ($g['tipo'] === 'immagine'): ?><button class="bottone sec piccolo" name="azione" value="scontorna">✂️ Togli lo sfondo</button><?php endif; ?>
      <button class="bottone sec piccolo" name="azione" value="scontorna" onclick="this.form.insertAdjacentHTML('beforeend','<input type=hidden name=personaggio value=1>')">🧒 Usa nella lavagna</button>
      <a class="bottone sec piccolo" href="ai.php?vista=animazione&amp;img=<?= urlencode($g['risultato']) ?>">🎞 Anima</a>
      <input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="bottone pericolo piccolo" name="azione" value="elimina_lavoro" data-conferma="Eliminare l'immagine?">🗑</button></form></figure>
<?php endforeach; if (!$gal): ?><p class="muto">Ancora nessuna immagine.</p><?php endif; ?>
</div>
<?php if ($extra = personaggi_extra()): ?>
<h2>Pose nuove nella lavagna</h2>
<div class="galleria-ai"><?php foreach ($extra as $p): ?>
  <figure><img src="<?= e(url_media($p['src'], '../')) ?>" alt="" class="scacchi" loading="lazy"><figcaption><?= e($p['titolo']) ?></figcaption>
    <form method="post"><?= csrf_campo() ?><input type="hidden" name="src" value="<?= e($p['src']) ?>"><button class="bottone sec piccolo" name="azione" value="togli_personaggio">Togli dalla lavagna</button></form></figure>
<?php endforeach; ?></div>
<?php endif; ?>

<?php elseif ($vista === 'voci'): $luce = impost('voce_luce') ?: AI_LUCE_PREDEFINITA; ?>
<form method="post" class="pannello"><?= csrf_campo() ?><input type="hidden" name="azione" value="voce_luce">
  <h2 style="margin-top:0">🐕 Voce di Luce</h2>
  <p class="muto">La voce di Luce è «disegnata» dall'AI partendo da una descrizione. Cambia la descrizione finché ti piace.</p>
  <label>Come parla Luce <textarea name="descrizione" rows="3" maxlength="500"><?= e($luce) ?></textarea></label>
  <label>Frase di prova <input type="text" name="testo_prova" value="Ciao! Sono Luce. Oggi scopriamo insieme chi fa l'azione nella frase: il soggetto!"></label>
  <?php if (impost('voce_luce_prova') !== ''): ?><p>Ultima prova: <audio controls src="<?= e(url_media(impost('voce_luce_prova'), '../')) ?>"></audio></p><?php endif; ?>
  <p><button class="bottone" name="prova" value="1">💾 Salva e prova</button> <button class="bottone sec">Salva soltanto</button></p>
</form>
<form method="post" enctype="multipart/form-data" class="pannello" id="form-vlad"><?= csrf_campo() ?><input type="hidden" name="azione" value="voce_vlad">
  <h2 style="margin-top:0">🧒 Voce di Vlad</h2>
  <p class="muto">L'AI impara la voce di Vlad da una registrazione di <b>5–15 secondi</b>: leggi ad alta voce la frase qui sotto, in un posto silenzioso. Poi le parti «AI» di Vlad avranno la sua voce.</p>
  <?php if (impost('voce_vlad_ref') !== ''): ?><p>Registrazione attuale: <audio controls src="<?= e(url_media(impost('voce_vlad_ref'), '../')) ?>"></audio></p><?php endif; ?>
  <label>Frase da leggere (scrivi esattamente le parole dette) <textarea name="testo_ref" rows="2" required><?= e(impost('voce_vlad_testo') ?: 'Ciao, sono Vlad. In questo libro ti spiego la grammatica come la spiegherei a un amico, con calma e con qualche esempio.') ?></textarea></label>
  <div class="registratore" id="registratore"><button type="button" class="bottone" id="reg-via">⏺ Registra</button><button type="button" class="bottone sec" id="reg-stop" disabled>⏹ Ferma</button><span class="punto"></span><span id="reg-tempo" class="muto">0:00</span></div>
  <p class="muto" id="reg-stato">Oppure carica un file audio: <input type="file" name="ref" id="file-media" accept="audio/*"></p>
  <audio id="reg-ascolta" controls hidden style="width:100%"></audio>
  <label class="spunta"><input type="checkbox" name="consenso" value="1" required> Vlad e i suoi genitori sono d'accordo a creare una copia AI della sua voce, solo per i libri di Vlad Editore.</label>
  <label>Frase di prova <input type="text" name="testo_prova" value="Adesso proviamo insieme: trova il predicato in questa frase!"></label>
  <?php if (impost('voce_vlad_prova') !== ''): ?><p>Ultima prova: <audio controls src="<?= e(url_media(impost('voce_vlad_prova'), '../')) ?>"></audio></p><?php endif; ?>
  <p><button class="bottone" name="prova" value="1">💾 Salva e prova</button> <button class="bottone sec">Salva soltanto</button></p>
</form>
<?php if (impost('voce_vlad_ref') !== ''): ?><form method="post"><?= csrf_campo() ?><button class="bottone pericolo piccolo" name="azione" value="togli_vlad" data-conferma="Cancellare la registrazione e la copia della voce di Vlad?">Cancella la voce di Vlad</button></form><?php endif; ?>
<script src="../assets/scuola-admin.js"></script>

<?php elseif ($vista === 'musica'): ?>
<form method="post" class="pannello"><?= csrf_campo() ?><input type="hidden" name="azione" value="musica">
  <h2 style="margin-top:0">🎵 Musica di sottofondo</h2>
  <div class="form-griglia">
    <label class="largo">Che musica? <textarea name="descrizione" rows="2" required maxlength="300" placeholder="es. musica allegra e leggera per bambini, ukulele e glockenspiel, ritmo tranquillo"></textarea></label>
    <label>Durata (secondi) <input type="number" name="durata" min="10" max="300" value="60"></label>
    <label>Battiti al minuto <small>(facoltativo, 50–200)</small><input type="number" name="bpm" min="0" max="200" value="0"></label>
    <label>Titolo <input type="text" name="titolo" maxlength="120" placeholder="es. Sigla della lavagna"></label>
    <?php $opzLibri(); ?>
  </div>
  <p><button class="bottone">🎵 Crea la musica</button> <span class="muto">Senza voce (strumentale). Finisce in «Audio e video» e nella lista musiche della lavagna.</span></p>
</form>

<?php elseif ($vista === 'animazione'): $pre = (string)($_GET['img'] ?? ''); ?>
<form method="post" class="pannello"><?= csrf_campo() ?><input type="hidden" name="azione" value="animazione">
  <h2 style="margin-top:0">🎞 Anima un'immagine</h2>
  <div class="form-griglia">
    <?php $selImg('img', $pre); ?>
    <label class="largo">Cosa succede <textarea name="prompt" rows="2" required placeholder="es. Vlad saluta con la mano e sorride, Luce scodinzola"></textarea></label>
    <label>Durata (secondi, 2–10) <input type="number" name="secondi" min="2" max="10" step="0.5" value="5"></label>
    <label>Formato <select name="formato"><option value="orizzontale">Orizzontale</option><option value="quadrato">Quadrato</option><option value="verticale">Verticale</option></select></label>
    <label>Titolo <input type="text" name="titolo" maxlength="120"></label>
    <?php $opzLibri(); ?>
  </div>
  <p><button class="bottone">🎞 Crea l'animazione</button> <span class="muto">LTX-2.5, 24 fotogrammi al secondo. Diventa un video in «Audio e video».</span></p>
</form>
<?php $audio = db()->query("SELECT codice, titolo, file FROM contenuti WHERE tipo = 'audio' AND file != '' ORDER BY id DESC LIMIT 100")->fetchAll(); ?>
<form method="post" class="pannello"><?= csrf_campo() ?><input type="hidden" name="azione" value="parla">
  <h2 style="margin-top:0">👄 Fai parlare Vlad o Luce</h2>
  <p class="muto">Scegli un'immagine e un audio corto (al massimo 10 secondi, per esempio fatto con la voce di Luce): il personaggio si muove e parla a tempo con la voce.</p>
  <div class="form-griglia">
    <?php $selImg('img', $pre); ?>
    <label>Audio <select name="audio" required><option value="">— scegli —</option>
      <?php foreach (['voce_luce_prova' => 'Prova voce di Luce', 'voce_vlad_prova' => 'Prova voce di Vlad'] as $k => $t): if (impost($k) !== ''): ?><option value="<?= e(impost($k)) ?>"><?= e($t) ?></option><?php endif; endforeach; ?>
      <?php foreach ($audio as $a): ?><option value="<?= e($a['file']) ?>"><?= e($a['codice'] . ' · ' . ($a['titolo'] ?: 'audio')) ?></option><?php endforeach; ?></select></label>
    <label>Durata dell'audio (secondi, 1–10) <input type="number" name="secondi" min="2" max="10" step="0.5" value="5"></label>
    <label class="largo">Come si muove <small>(facoltativo)</small><input type="text" name="prompt" maxlength="300" placeholder="es. Luce parla allegra muovendo le orecchie"></label>
    <label>Formato <select name="formato"><option value="orizzontale">Orizzontale</option><option value="quadrato">Quadrato</option><option value="verticale">Verticale</option></select></label>
    <label>Titolo <input type="text" name="titolo" maxlength="120"></label>
    <?php $opzLibri(); ?>
  </div>
  <p><button class="bottone">👄 Crea il video</button></p>
</form>

<?php else: $lav = db()->query('SELECT * FROM lavori_ai ORDER BY id DESC LIMIT 200')->fetchAll(); ?>
<table class="tabella"><tr><th>Tipo</th><th>Stato</th><th>Risultato</th><th>Avviato</th><th></th></tr>
<?php foreach ($lav as $r): $par = json_decode((string)$r['parametri'], true) ?: []; $u = $r['risultato'] !== '' ? url_media($r['risultato'], '../') : ''; ?>
<tr><td><?= AI_TIPI[$r['tipo']][0] ?? '' ?> <?= e(AI_TIPI[$r['tipo']][1] ?? $r['tipo']) ?><br><small class="muto"><?= e(mb_substr((string)($par['titolo'] ?? $par['prompt'] ?? $par['voce'] ?? ''), 0, 70)) ?></small></td>
  <td><?= ['attesa' => '<span class="stato prog">⏳ in preparazione</span>', 'scarico' => '<span class="stato prog">⬇ scarico</span>', 'fatto' => '<span class="stato pub">✓ pronto</span>', 'errore' => '<span class="stato cest">errore</span>'][$r['stato']] ?? e($r['stato']) ?><?= $r['errore'] !== '' ? '<br><small class="muto">' . e($r['errore']) . '</small>' : '' ?></td>
  <td><?php if ($u): if (in_array($r['tipo'], ['voce', 'musica'], true)): ?><audio controls preload="none" src="<?= e($u) ?>"></audio><?php elseif (in_array($r['tipo'], ['animazione', 'parla'], true)): ?><video controls preload="none" src="<?= e($u) ?>" style="max-width:220px"></video><?php else: ?><img src="<?= e($u) ?>" alt="" style="max-width:120px;max-height:80px"><?php endif; endif; ?>
    <?php if ($r['contenuto_id'] && ($c = db()->query('SELECT codice FROM contenuti WHERE id = ' . (int)$r['contenuto_id'])->fetchColumn())): ?><br><a href="contenuti.php?id=<?= (int)$r['contenuto_id'] ?>"><code><?= e($c) ?></code></a><?php endif; ?></td>
  <td class="muto"><?= e(formatta_data($r['creato'])) ?></td>
  <td><form method="post"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="bottone sec piccolo" name="azione" value="elimina_lavoro" data-conferma="Eliminare questo lavoro<?= $r['contenuto_id'] ? ' (il contenuto del libro resta)' : '' ?>?">🗑</button></form></td></tr>
<?php endforeach; if (!$lav): ?><tr><td colspan="5" class="muto">Ancora nessun lavoro.</td></tr><?php endif; ?>
</table>
<?php endif; ?>
<?php if ($attesa): ?><script src="../assets/ai-attesa.js"></script><?php endif; ?>
<?php admin_piede();
