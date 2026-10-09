<?php
// API della lavagna (admin e prof): salva il progetto, carica immagini, salva il video esportato, elimina.
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/lavagna.php';

header('Content-Type: application/json; charset=utf-8');
function fine(array $r, int $c = 200): void { http_response_code($c); echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

$u = utente_lavagna();
if (!$u['admin'] && !$u['prof']) fine(['ok' => false, 'errore' => 'Accesso necessario.'], 401);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fine(['ok' => false, 'errore' => 'Metodo non ammesso.'], 405);
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_SERVER['HTTP_X_CSRF'] ?? $_POST['csrf'] ?? ''))) fine(['ok' => false, 'errore' => 'Richiesta non valida (ricarica la pagina).'], 400);
// chi lavora: la pagina dell'editor dice se è l'admin o un prof (la stessa persona potrebbe essere entrata come entrambi)
$come = (string)($_POST['come'] ?? '');
if ($come === 'admin' && $u['admin']) $u = ['admin' => true, 'prof' => null];
elseif ($come === 'prof' && $u['prof']) $u = ['admin' => false, 'prof' => $u['prof']];
else fine(['ok' => false, 'errore' => 'Accesso necessario.'], 401);
$prof_id = $u['prof'] ? (int)$u['prof']['id'] : 0;
$az = (string)($_POST['azione'] ?? '');

try {
    $id = (int)($_POST['id'] ?? 0);
    $l = $id ? lavagna_permessa($id, $u) : null;
    if ($id && !$l) fine(['ok' => false, 'errore' => 'Lavagna non trovata.'], 404);

    if ($az === 'salva') {
        $dati = pulisci_progetto((string)($_POST['dati'] ?? ''));
        $titolo = mb_substr(trim((string)($_POST['titolo'] ?? '')), 0, 200);
        $classe = (int)($_POST['classe_id'] ?? 0);
        if ($classe && (!$u['prof'] || !classe_del_prof($classe, (int)$u['prof']['id']))) $classe = 0;      // solo classi proprie
        $libro = (int)($_POST['libro_id'] ?? 0);
        if ($l) {
            db()->prepare('UPDATE lavagne SET titolo = ?, dati = ?, classe_id = ?, libro_id = ?, aggiornato = ? WHERE id = ?')->execute([$titolo, $dati, $classe, $libro, date('Y-m-d H:i:s'), $id]);
        } else {
            db()->prepare('INSERT INTO lavagne (titolo, prof_id, classe_id, libro_id, dati, aggiornato) VALUES (?,?,?,?,?,?)')->execute([$titolo, $prof_id, $classe, $libro, $dati, date('Y-m-d H:i:s')]);
            $id = (int)db()->lastInsertId();
        }
        fine(['ok' => true, 'id' => $id]);
    }

    if ($az === 'immagine') {
        if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Immagine non ricevuta.');
        if ($_FILES['file']['size'] > 15 * 1024 * 1024) throw new RuntimeException('Immagine troppo grande (max 15 MB).');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['file']['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) throw new RuntimeException('Serve un\'immagine JPG, PNG, WebP o GIF.');
        $v = salva_media('file', 15 * 1024 * 1024);
        if ($GLOBALS['ultimo_media_tipo'] !== 'immagine') { elimina_fisico('media', nome_file($v)); throw new RuntimeException('Serve un\'immagine.'); }
        fine(['ok' => true, 'url' => url_media($v)]);
    }

    if ($az === 'audio') {                                       // musica o voce di sottofondo caricata dal computer
        if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Audio non ricevuto.');
        $v = salva_media('file', 60 * 1024 * 1024);
        if ($GLOBALS['ultimo_media_tipo'] !== 'audio') { elimina_fisico('media', nome_file($v)); throw new RuntimeException('Serve un file audio (mp3, m4a, wav, ogg).'); }
        fine(['ok' => true, 'url' => url_media($v)]);
    }

    if ($az === 'video') {
        if (!$l) throw new RuntimeException('Salva prima la lavagna.');
        $v = salva_media('video', 500 * 1024 * 1024);
        if (!$v) throw new RuntimeException('Video non ricevuto.');
        if ($GLOBALS['ultimo_media_tipo'] !== 'video') { elimina_fisico('media', nome_file($v)); throw new RuntimeException('Serve un file video (MP4 o WebM).'); }
        $vecchio = $l['video'];
        db()->prepare('UPDATE lavagne SET video = ?, aggiornato = ? WHERE id = ?')->execute([$v, date('Y-m-d H:i:s'), $id]);
        $codice = '';
        // admin: il video diventa anche un contenuto del libro (con il suo codice, per il PDF e il lettore)
        if ($u['admin'] && (int)$l['prof_id'] === 0 && !empty($_POST['pubblica'])) {
            $lib = (int)($_POST['libro_id'] ?? $l['libro_id']);
            $titolo = $l['titolo'] !== '' ? $l['titolo'] : 'Video';
            if ($l['contenuto_id'] && ($c = db()->query('SELECT * FROM contenuti WHERE id = ' . (int)$l['contenuto_id'])->fetch())) {
                db()->prepare("UPDATE contenuti SET file = ?, tipo = 'video', titolo = ?, libro_id = ?, pagina = ? WHERE id = ?")->execute([$v, $titolo, $lib, (int)($_POST['pagina'] ?? $c['pagina']), $c['id']]);
                $codice = $c['codice'];
            } else {
                $codice = nuovo_codice($lib);
                db()->prepare("INSERT INTO contenuti (codice, libro_id, pagina, tipo, titolo, file, testo) VALUES (?,?,?,'video',?,?,'')")->execute([$codice, $lib, (int)($_POST['pagina'] ?? 0), $titolo, $v]);
                db()->prepare('UPDATE lavagne SET contenuto_id = ?, libro_id = ? WHERE id = ?')->execute([db()->lastInsertId(), $lib, $id]);
            }
        }
        if ($vecchio !== '' && $vecchio !== $v) elimina_file('media', $vecchio);
        registra('Video della lavagna salvato', $l['titolo']);
        fine(['ok' => true, 'url' => url_media($v), 'codice' => $codice]);
    }

    if ($az === 'elimina' && $l) {
        db()->prepare('DELETE FROM lavagne WHERE id = ?')->execute([$id]);
        if (!$l['contenuto_id']) elimina_file('media', $l['video']);       // se è anche un contenuto del libro, il file resta lì
        fine(['ok' => true]);
    }
    fine(['ok' => false, 'errore' => 'Azione sconosciuta.'], 400);
} catch (RuntimeException $e) {
    fine(['ok' => false, 'errore' => $e->getMessage()], 400);
}
