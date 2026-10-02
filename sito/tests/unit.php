<?php
// Test di unità per le funzioni pure del sito. Uso:  php sito/tests/unit.php
// Usa un database temporaneo, non tocca quello vero.
$tmp = sys_get_temp_dir() . '/libri-unit-' . getmypid();
mkdir($tmp . '/data', 0777, true);
$copia = $tmp . '/sito';
mkdir($copia);
foreach (['inc'] as $d) { mkdir("$copia/$d"); foreach (glob(__DIR__ . "/../$d/*.php") as $f) copy($f, "$copia/$d/" . basename($f)); }
require "$copia/inc/sicurezza.php";

$ok = $ko = 0;
function prova(string $nome, $atteso, $ottenuto): void {
    global $ok, $ko;
    if ($atteso === $ottenuto) { $ok++; return; }
    $ko++; echo "FAIL: $nome\n  atteso:   " . var_export($atteso, true) . "\n  ottenuto: " . var_export($ottenuto, true) . "\n";
}

// slug
prova('slug accenti', 'il-custode-e-l-isola', slug("Il Custode e l'Isola"));
prova('slug vuoto', 'x', slug('!!!'));
prova('slug maiuscole', 'ciao-mondo', slug('  Ciao   MONDO  '));

// md(): formattazione semplice e sicura
prova('md grassetto', '<p><strong>ciao</strong></p>', md('**ciao**'));
prova('md elenco puntato', '<p>Titolo</p><ul><li>uno</li><li>due</li></ul>', md("Titolo\n- uno\n- due"));
prova('md corsivo', '<p><em>ciao</em></p>', md('*ciao*'));
prova('md titolo', '<h2>Titolo</h2>', md('# Titolo'));
prova('md paragrafi', 2, substr_count(md("uno\n\ndue"), '<p>'));
prova('md link', true, str_contains(md('[sito](https://esempio.it)'), '<a href="https://esempio.it" rel="noopener">sito</a>'));
prova('md blocca javascript:', false, str_contains(md('[x](javascript:alert(1))'), '<a '));
prova('md blocca html', false, str_contains(md('<script>alert(1)</script>'), '<script>'));

// tag
prova('tags_di', ['a', 'b'], tags_di('a, b, a, ,'));
require "$copia/inc/libri.php";
prova('normalizza_tags', 'uno,due', normalizza_tags("uno; due\nuno"));

// byte
prova('formatta_byte', '1.5 KB', formatta_byte(1536));
prova('formatta_byte MB', '5 MB', formatta_byte(5 * 1024 * 1024));

// colori
prova('colore valido', '#aabbcc', colore_valido('#aabbcc', '#000000'));
prova('colore non valido', '#000000', colore_valido('rosso; background:url(x)', '#000000'));

// TOTP: vettori ufficiali dell'RFC 6238 (SHA-1)
$seg = base32_codifica('12345678901234567890');
prova('base32', 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $seg);
prova('base32 decodifica', '12345678901234567890', base32_decodifica($seg));
prova('totp t=59', '94287082', totp_codice($seg, 59, 8));
prova('totp t=1111111109', '07081804', totp_codice($seg, 1111111109, 8));
prova('totp t=1111111111', '14050471', totp_codice($seg, 1111111111, 8));
prova('totp t=1234567890', '89005924', totp_codice($seg, 1234567890, 8));
prova('totp t=2000000000', '69279037', totp_codice($seg, 2000000000, 8));
prova('totp verifica', true, totp_verifica($seg, '287082', 59));
prova('totp tolleranza +30s', true, totp_verifica($seg, '287082', 85));
prova('totp troppo vecchio', false, totp_verifica($seg, '287082', 200));
prova('totp codice errato', false, totp_verifica($seg, '123456', 59));
prova('totp non numerico', false, totp_verifica($seg, 'abcdef', 59));
prova('totp segreto vuoto', false, totp_verifica('', '287082', 59));
prova('uri otpauth', true, str_starts_with(totp_uri($seg, 'admin', 'Vlad Editore'), 'otpauth://totp/Vlad%20Editore%3Aadmin?secret=' . $seg));

// codice di recupero e backup (con il database temporaneo)
db()->exec("INSERT INTO admin (id, password) VALUES (1, 'x')");
$c = imposta_codice_recupero();
prova('recupero formato', 1, preg_match('/^[A-Z2-7]{5}(-[A-Z2-7]{5}){3}$/', $c));
prova('recupero valido', true, verifica_codice_recupero($c));
prova('recupero minuscole e spazi', true, verifica_codice_recupero(' ' . strtolower($c) . ' '));
prova('recupero errato', false, verifica_codice_recupero('AAAAA-AAAAA-AAAAA-AAAAA'));
$b = codici_backup_nuovi();
prova('8 codici backup', 8, count($b));
prova('backup valido', true, usa_codice_backup($b[0]));
prova('backup usato una volta sola', false, usa_codice_backup($b[0]));
prova('backup rimasti', 7, codici_backup_rimasti());
prova('backup falso', false, usa_codice_backup('0000-0000'));

// migrazione: una collana testuale diventa una vera collana
prova('collana_id_per_nome crea', true, collana_id_per_nome('Serie Test') > 0);
prova('collana_id_per_nome riusa', collana_id_per_nome('Serie Test'), collana_id_per_nome('Serie Test'));
prova('collana vuota', 0, collana_id_per_nome('  '));

// riordino che non sposta gli altri libri
$ins = db()->prepare('INSERT INTO libri (slug, titolo, ordine) VALUES (?, ?, ?)');
foreach ([['a', 'A', 1], ['b', 'B', 2], ['c', 'C', 3], ['d', 'D', 4]] as $r) $ins->execute($r);
$id = fn($s) => (int)db()->query("SELECT id FROM libri WHERE slug = '$s'")->fetchColumn();
riordina_libri([$id('d'), $id('b')]);                       // scambia b e d, a e c restano dove sono
prova('riordino parziale', 'a,d,c,b', implode(',', db()->query('SELECT slug FROM libri ORDER BY ordine')->fetchAll(PDO::FETCH_COLUMN)));
sposta_libro($id('a'), 1);
prova('sposta giù', 'd,a,c,b', implode(',', db()->query('SELECT slug FROM libri ORDER BY ordine')->fetchAll(PDO::FETCH_COLUMN)));

// visibilità programmata
db()->exec("UPDATE libri SET pubblicato = 1, pubblica_il = '2999-01-01 00:00:00' WHERE slug = 'a'");
db()->exec("UPDATE libri SET pubblicato = 1 WHERE slug = 'b'");
prova('programmato non visibile', ['b'], db()->query('SELECT slug FROM libri l WHERE ' . sql_visibile('l'))->fetchAll(PDO::FETCH_COLUMN));

echo "unit: OK=$ok KO=$ko\n";
exec('rm -rf ' . escapeshellarg($tmp));

// file come indirizzi interi, indirizzo ufficiale e differenze tra i due database (un processo per modo)
foreach (['sqlite', 'mysql'] as $modo) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/unit_file.php') . ' ' . $modo, $codice);
    if ($codice !== 0) $ko++;
}
exit($ko ? 1 : 0);
