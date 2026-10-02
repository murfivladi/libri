<?php
// Importa i 5 libri esistenti (PDF + copertina) dal repository nel sito.
// Uso da terminale, dalla cartella del repository:  php sito/importa.php
// Va eseguito dove stanno i file (il sito principale, o un sito con una sola cartella uploads). I file vengono copiati
// in uploads/ e nel database si scrive l'indirizzo intero (se data/config.php ha "file_url_base") oppure il nome.
if (PHP_SAPI !== 'cli') exit('Solo da terminale.');
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/libri.php';
$repo = getenv('LIBRI_REPO') ?: dirname(__DIR__);
$dir = dir_uploads() ?? exit("Questo sito non ha la cartella dei file: esegui l'importazione sul sito principale.\n");
$libri = [
  [1, "Il Custode dell'Etna", 'il-custode-dell-etna.pdf', 'Fantascienza / fantasy'],
  [2, 'Il Custode e il Fantasma del Teatro', 'il-custode-e-il-fantasma.pdf', 'Fantasy / mistero'],
  [3, 'Il Custode e la Lira del Mare', 'il-custode-e-la-lira-del-mare.pdf', 'Fantasy / mistero'],
  [4, "Il Custode e l'Isola dell'Immortale", 'il-custode-e-l-isola-dell-immortale.pdf', 'Fantasy / fiabe russe'],
  [5, "Il Custode e l'Inverno Eterno", 'il-custode-e-l-inverno-eterno.pdf', 'Fantasy / finale della serie'],
];
@mkdir("$dir/pdf", 0755, true);
@mkdir("$dir/cover", 0755, true);
$cid = collana_id_per_nome("Il Custode dell'Etna");
$esiste = db()->prepare('SELECT 1 FROM libri WHERE slug = ?');
$ins = db()->prepare('INSERT INTO libri (slug,titolo,autore,collana,collana_id,numero,genere,lingua,anno,copertina,pdf,pagine_n,token,pubblicato,ordine,aggiornato) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?)');
foreach ($libri as [$n, $titolo, $pdf, $genere]) {
    $s = slug($titolo);
    $esiste->execute([$s]);
    if ($esiste->fetchColumn()) { echo "Già presente: $titolo\n"; continue; }
    $cartella = sprintf('%s/libro-%02d', $repo, $n);
    $nomePdf = bin2hex(random_bytes(8)) . '.pdf';
    $nomeCop = bin2hex(random_bytes(8)) . '.jpg';
    copy("$cartella/build/$pdf", "$dir/pdf/$nomePdf");
    copy("$cartella/immagini/copertina.jpg", "$dir/cover/$nomeCop");
    $ins->execute([$s, $titolo, impost('autore_predefinito'), "Il Custode dell'Etna", $cid, $n, $genere, 'it', 2026,
                   valore_file('cover', $nomeCop), valore_file('pdf', $nomePdf), conta_pagine_pdf("$dir/pdf/$nomePdf"), nuovo_token(), $n, date('Y-m-d H:i:s')]);
    echo "Importato: $titolo\n";
}
echo "Fatto. I libri sono in bozza: pubblicali dall'admin (aggiungi anche la trama).\n";
