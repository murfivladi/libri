<?php
// Operazioni sui libri usate dall'admin (azioni singole e di massa).
require_once __DIR__ . '/file.php';

function carica_libro(int $id): ?array {
    $st = db()->prepare('SELECT * FROM libri WHERE id = ?'); $st->execute([$id]);
    return $st->fetch() ?: null;
}

function normalizza_tags(string $s): string { return implode(',', tags_di(str_replace(["\n", ';'], ',', $s))); }

function nuovo_token(): string { return bin2hex(random_bytes(12)); }

function duplica_libro(int $id): ?int {
    $l = carica_libro($id);
    if (!$l) return null;
    $titolo = $l['titolo'] . ' (copia)';
    $nuovo = [
        'slug' => slug_unico('libri', $titolo), 'titolo' => $titolo, 'sottotitolo' => $l['sottotitolo'], 'autore' => $l['autore'],
        'collana' => $l['collana'], 'collana_id' => $l['collana_id'], 'numero' => $l['numero'], 'genere' => $l['genere'],
        'lingua' => $l['lingua'], 'anno' => $l['anno'], 'pagine_n' => $l['pagine_n'], 'isbn' => '', 'tags' => $l['tags'],
        'trama' => $l['trama'], 'meta' => $l['meta'], 'note' => $l['note'],
        'copertina' => $l['copertina'], 'pdf' => $l['pdf'], 'pdf_ru' => $l['pdf_ru'],      // lo stesso file: viene cancellato solo quando nessun libro lo usa più
        'pubblicato' => 0, 'evidenza' => 0, 'pubblica_il' => '', 'token' => nuovo_token(), 'aggiornato' => date('Y-m-d H:i:s'),
        'ordine' => $l['ordine'], 'sezione' => $l['sezione'] ?? 'narrativa',
    ];
    $campi = implode(',', array_keys($nuovo));
    $segn = implode(',', array_fill(0, count($nuovo), '?'));
    db()->prepare("INSERT INTO libri ($campi) VALUES ($segn)")->execute(array_values($nuovo));
    return (int)db()->lastInsertId();
}

function elimina_libro_definitivo(int $id): void {
    $l = carica_libro($id);
    if (!$l) return;
    db()->prepare('DELETE FROM libri WHERE id = ?')->execute([$id]);
    // esercizi, contenuti e zone del libro scolastico (i file audio/video si cancellano se nessuno li usa più)
    $st = db()->prepare('SELECT file FROM contenuti WHERE libro_id = ?'); $st->execute([$id]); $files = $st->fetchAll(PDO::FETCH_COLUMN);
    foreach (['esercizi', 'contenuti', 'zone'] as $t) db()->prepare("DELETE FROM $t WHERE libro_id = ?")->execute([$id]);
    foreach ($files as $f) elimina_file('media', (string)$f);
    elimina_file('cover', $l['copertina']);
    elimina_file('pdf', $l['pdf']);
    elimina_file('pdf', $l['pdf_ru']);
}

// Rinumera l'ordine manuale e sposta un libro su (-1) o giù (+1).
function sposta_libro(int $id, int $verso): void {
    $ids = db()->query('SELECT id FROM libri WHERE eliminato IS NULL ORDER BY ordine, numero, id')->fetchAll(PDO::FETCH_COLUMN);
    $i = array_search($id, array_map('intval', $ids), true);
    if ($i === false) return;
    $j = $i + $verso;
    if ($j < 0 || $j >= count($ids)) return;
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    $up = db()->prepare('UPDATE libri SET ordine = ? WHERE id = ?');
    db()->beginTransaction();
    foreach ($ids as $k => $x) $up->execute([$k + 1, $x]);
    db()->commit();
}

// Riordino con il trascinamento: gli id arrivano nel nuovo ordine (anche solo quelli di una pagina filtrata);
// vanno a occupare le stesse posizioni che avevano prima, così gli altri libri non si muovono.
function riordina_libri(array $nuovo): void {
    $nuovo = array_values(array_unique(array_map('intval', $nuovo)));
    $tutti = array_map('intval', db()->query('SELECT id FROM libri WHERE eliminato IS NULL ORDER BY ordine, numero, id')->fetchAll(PDO::FETCH_COLUMN));
    $posizioni = [];
    foreach ($tutti as $i => $id) if (in_array($id, $nuovo, true)) $posizioni[] = $i;
    if (count($posizioni) !== count($nuovo)) return;
    foreach ($posizioni as $k => $i) $tutti[$i] = $nuovo[$k];
    $up = db()->prepare('UPDATE libri SET ordine = ? WHERE id = ?');
    db()->beginTransaction();
    foreach ($tutti as $k => $id) $up->execute([$k + 1, $id]);
    db()->commit();
}

// Salva l'ordine di una tabella semplice (collane, pagine): gli id arrivano nel nuovo ordine.
function riordina_tabella(string $tabella, array $nuovo): void {
    if (!in_array($tabella, ['collane', 'pagine'], true)) return;
    $up = db()->prepare("UPDATE $tabella SET ordine = ? WHERE id = ?");
    db()->beginTransaction();
    foreach (array_values(array_map('intval', $nuovo)) as $k => $id) $up->execute([$k + 1, $id]);
    db()->commit();
}

function tocca(int $id): void { db()->prepare('UPDATE libri SET aggiornato = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $id]); }

// Applica un'azione a una lista di libri. Restituisce il messaggio da mostrare.
function azione_libri(string $azione, array $ids, array $post): string {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return 'Nessun libro selezionato.';
    $n = count($ids);
    $pdo = db();
    $ora = date('Y-m-d H:i:s');
    $ciascuno = function (callable $f) use ($ids) { foreach ($ids as $id) { if ($l = carica_libro($id)) $f($l); } };

    switch ($azione) {
        case 'pubblica':   $ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET pubblicato = 1, aggiornato = ? WHERE id = ?')->execute([$ora, $l['id']])); $m = "$n libri pubblicati"; break;
        case 'nascondi':   $ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET pubblicato = 0, aggiornato = ? WHERE id = ?')->execute([$ora, $l['id']])); $m = "$n libri nascosti (bozza)"; break;
        case 'toggle':     $ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET pubblicato = 1 - pubblicato, aggiornato = ? WHERE id = ?')->execute([$ora, $l['id']])); $m = 'Stato cambiato'; break;
        case 'evidenza_si':$ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET evidenza = 1 WHERE id = ?')->execute([$l['id']])); $m = "$n libri messi in evidenza"; break;
        case 'evidenza_no':$ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET evidenza = 0 WHERE id = ?')->execute([$l['id']])); $m = "Evidenza tolta a $n libri"; break;
        case 'evidenza_toggle': $ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET evidenza = 1 - evidenza WHERE id = ?')->execute([$l['id']])); $m = 'Evidenza cambiata'; break;
        case 'sposta_collana':
            $cid = (int)($post['collana_id'] ?? 0);
            if (trim($post['nuova_collana'] ?? '') !== '') $cid = collana_id_per_nome($post['nuova_collana']);
            $nome = '';
            if ($cid) { $st = $pdo->prepare('SELECT nome FROM collane WHERE id = ?'); $st->execute([$cid]); $nome = (string)$st->fetchColumn(); }
            $ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET collana_id = ?, collana = ?, aggiornato = ? WHERE id = ?')->execute([$cid, $nome, $ora, $l['id']]));
            $m = $cid ? "$n libri spostati in «{$nome}»" : "$n libri tolti dalla collana"; break;
        case 'aggiungi_tag': case 'rimuovi_tag':
            $t = tags_di(str_replace(';', ',', $post['tag'] ?? ''));
            if (!$t) return 'Scrivi almeno un tag.';
            $ciascuno(function ($l) use ($pdo, $t, $azione, $ora) {
                $att = tags_di($l['tags']);
                $new = $azione === 'aggiungi_tag' ? array_values(array_unique(array_merge($att, $t))) : array_values(array_diff($att, $t));
                $pdo->prepare('UPDATE libri SET tags = ?, aggiornato = ? WHERE id = ?')->execute([implode(',', $new), $ora, $l['id']]);
            });
            $m = ($azione === 'aggiungi_tag' ? 'Tag aggiunti a ' : 'Tag tolti da ') . "$n libri"; break;
        case 'imposta_genere': $v = trim($post['valore'] ?? ''); $ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET genere = ?, aggiornato = ? WHERE id = ?')->execute([$v, $ora, $l['id']])); $m = "Genere impostato su $n libri"; break;
        case 'imposta_autore': $v = trim($post['valore'] ?? ''); $ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET autore = ?, aggiornato = ? WHERE id = ?')->execute([$v, $ora, $l['id']])); $m = "Autore impostato su $n libri"; break;
        case 'imposta_lingua': $v = trim($post['valore'] ?? ''); $ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET lingua = ?, aggiornato = ? WHERE id = ?')->execute([$v, $ora, $l['id']])); $m = "Lingua impostata su $n libri"; break;
        case 'programma':
            $d = trim($post['data'] ?? '');
            $ts = $d ? strtotime($d) : false;
            if (!$ts) return 'Scegli una data valida.';
            $v = date('Y-m-d H:i:s', $ts);
            $ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET pubblicato = 1, pubblica_il = ?, aggiornato = ? WHERE id = ?')->execute([$v, $ora, $l['id']]));
            $m = "$n libri programmati per il " . formatta_data($v); break;
        case 'togli_programma': $ciascuno(fn($l) => $pdo->prepare("UPDATE libri SET pubblica_il = '', aggiornato = ? WHERE id = ?")->execute([$ora, $l['id']])); $m = 'Programmazione tolta'; break;
        case 'cestino':    $ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET eliminato = ?, evidenza = 0 WHERE id = ?')->execute([$ora, $l['id']])); $m = "$n libri spostati nel cestino"; break;
        case 'ripristina': $ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET eliminato = NULL, pubblicato = 0, aggiornato = ? WHERE id = ?')->execute([$ora, $l['id']])); $m = "$n libri ripristinati (come bozza)"; break;
        case 'elimina_def':$ciascuno(fn($l) => elimina_libro_definitivo((int)$l['id'])); $m = "$n libri eliminati per sempre"; break;
        case 'duplica':    $c = 0; foreach ($ids as $id) if (duplica_libro($id)) $c++; $m = "$c libri duplicati (come bozze)"; break;
        case 'nuovo_token':$ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET token = ? WHERE id = ?')->execute([nuovo_token(), $l['id']])); $m = 'Link di anteprima rigenerati'; break;
        case 'azzera_contatori': $ciascuno(fn($l) => $pdo->prepare('UPDATE libri SET letture = 0, download = 0 WHERE id = ?')->execute([$l['id']])); $m = 'Contatori azzerati'; break;
        case 'su': sposta_libro($ids[0], -1); return 'Libro spostato su';
        case 'giu': sposta_libro($ids[0], 1); return 'Libro spostato giù';
        default: return 'Azione sconosciuta.';
    }
    registra('Libri: ' . $azione, "$n libri (id " . implode(',', array_slice($ids, 0, 20)) . ')');
    return $m;
}

// Intestazioni e righe CSV del catalogo (usate da export e import).
const COLONNE_CSV = ['slug', 'titolo', 'sottotitolo', 'autore', 'collana', 'numero', 'genere', 'lingua', 'anno', 'pagine', 'isbn', 'tags', 'trama', 'pubblicato', 'pubblica_il', 'evidenza'];

function riga_csv(array $l): array {
    $nomeCollana = $l['collana_id'] ? (db()->query('SELECT nome FROM collane WHERE id = ' . (int)$l['collana_id'])->fetchColumn() ?: $l['collana']) : $l['collana'];
    return [$l['slug'], $l['titolo'], $l['sottotitolo'], $l['autore'], $nomeCollana, $l['numero'], $l['genere'], $l['lingua'], $l['anno'], $l['pagine_n'], $l['isbn'], $l['tags'], $l['trama'], $l['pubblicato'], $l['pubblica_il'], $l['evidenza']];
}

function esporta_csv(array $libri, string $nomefile): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomefile . '"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF"); // BOM per aprirlo bene in Excel
    fputcsv($o, COLONNE_CSV, ';');
    foreach ($libri as $l) fputcsv($o, riga_csv($l), ';');
    exit;
}
