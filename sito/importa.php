<?php
// Importa i 5 libri esistenti (PDF + copertina) dal repository nel sito.
// Uso da terminale, dalla cartella del repository:  php sito/importa.php
if (PHP_SAPI !== 'cli') exit('Solo da terminale.');
require __DIR__ . '/inc/bootstrap.php';
$repo = dirname(__DIR__);
$libri = [
  [1, "Il Custode dell'Etna", 'il-custode-dell-etna.pdf', 'Fantascienza / fantasy'],
  [2, 'Il Custode e il Fantasma del Teatro', 'il-custode-e-il-fantasma.pdf', 'Fantasy / mistero'],
  [3, 'Il Custode e la Lira del Mare', 'il-custode-e-la-lira-del-mare.pdf', 'Fantasy / mistero'],
  [4, "Il Custode e l'Isola dell'Immortale", 'il-custode-e-l-isola-dell-immortale.pdf', 'Fantasy / fiabe russe'],
  [5, "Il Custode e l'Inverno Eterno", 'il-custode-e-l-inverno-eterno.pdf', 'Fantasy / finale della serie'],
];
$ins = db()->prepare('INSERT OR IGNORE INTO libri (slug,titolo,collana,numero,genere,copertina,pdf,pubblicato) VALUES (?,?,?,?,?,?,?,0)');
foreach ($libri as [$n, $titolo, $pdf, $genere]) {
    $cartella = sprintf('%s/libro-%02d', $repo, $n);
    $nomePdf = bin2hex(random_bytes(8)) . '.pdf';
    $nomeCop = bin2hex(random_bytes(8)) . '.jpg';
    copy("$cartella/build/$pdf", RADICE . "/uploads/pdf/$nomePdf");
    copy("$cartella/immagini/copertina.jpg", RADICE . "/uploads/cover/$nomeCop");
    $ins->execute([slug($titolo), $titolo, "Il Custode dell'Etna", $n, $genere, $nomeCop, $nomePdf]);
    echo "Importato: $titolo\n";
}
echo "Fatto. I libri sono in bozza: pubblicali dall'admin (aggiungi anche la trama).\n";
