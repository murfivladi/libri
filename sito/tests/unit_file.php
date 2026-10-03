<?php
// Test di unità per file come indirizzi interi, indirizzo ufficiale e differenze SQLite/MySQL.
// Lo lancia unit.php (una volta per modo, perché la configurazione si legge una volta sola):  php unit_file.php sqlite|mysql
$modo = $argv[1] ?? 'sqlite';
$tmp = sys_get_temp_dir() . '/libri-unitfile-' . getmypid();
mkdir("$tmp/sito/inc", 0777, true);
foreach (glob(__DIR__ . '/../inc/*.php') as $f) copy($f, "$tmp/sito/inc/" . basename($f));
$cfg = ['db_driver' => $modo, 'sito_principale' => false, 'file_url_base' => 'https://vladinc.ru/libri/', 'url_ufficiale' => 'https://vladinc.ru/libri/'];
file_put_contents("$tmp/config.php", "<?php\nreturn " . var_export($cfg, true) . ";\n");
putenv("LIBRI_CONFIG=$tmp/config.php");
define('RADICE', "$tmp/sito");
define('SENZA_SESSIONE', true);
define('SENZA_DB', true);
$_SERVER['HTTP_HOST'] = 'libri.vladinc.ru'; $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['HTTPS'] = 'on';
require RADICE . '/inc/bootstrap.php';        // con SENZA_SESSIONE e SENZA_DB non apre né sessione né database

$ok = $ko = 0;
function prova(string $nome, $atteso, $ottenuto): void {
    global $ok, $ko, $modo;
    if ($atteso === $ottenuto) { $ok++; return; }
    $ko++; echo "FAIL [$modo]: $nome\n  atteso:   " . var_export($atteso, true) . "\n  ottenuto: " . var_export($ottenuto, true) . "\n";
}

// ----- configurazione e ruolo -----
prova('driver letto dalla configurazione', $modo, db_driver());
prova('sito secondario', false, sito_principale());
prova('indirizzo dei file senza barra finale', 'https://vladinc.ru/libri', file_base());
prova('un secondario senza cartella diretta non scrive in locale', null, dir_uploads());

// ----- file come indirizzi interi -----
prova('valore_file', 'https://vladinc.ru/libri/uploads/pdf/a.pdf', valore_file('pdf', 'a.pdf'));
prova('url_file: nome semplice -> indirizzo intero', 'https://vladinc.ru/libri/uploads/cover/b.jpg', url_file('b.jpg', 'cover'));
prova('url_file: indirizzo intero resta uguale', 'https://altro.it/x.jpg', url_file('https://altro.it/x.jpg', 'cover'));
prova('url_file: vuoto', '', url_file('', 'cover'));
prova('nome_file da indirizzo', 'z.jpg', nome_file('https://vladinc.ru/libri/uploads/cover/z.jpg'));
prova('nome_file con parametri', 'z.jpg', nome_file('https://x.it/a/z.jpg?x=1&y=2'));
prova('nome_file semplice', 'q.pdf', nome_file('q.pdf'));
prova('nome_file non esce dalla cartella', 'passwd', nome_file('../../etc/passwd'));
prova('e_nostro: indirizzo del principale', true, e_nostro('https://vladinc.ru/libri/uploads/pdf/a.pdf'));
prova('e_nostro: nome semplice', true, e_nostro('a.pdf'));
prova('e_nostro: altro sito', false, e_nostro('https://altro.it/libri/uploads/pdf/a.pdf'));
prova('e_nostro: prefisso ingannevole', false, e_nostro('https://vladinc.ru/libri-finto/uploads/pdf/a.pdf'));
prova('e_nostro: vuoto', false, e_nostro(''));
prova('con_prefisso: relativo', '../uploads/x.jpg', con_prefisso('uploads/x.jpg', '../'));
prova('con_prefisso: intero', 'https://a.it/x.jpg', con_prefisso('https://a.it/x.jpg', '../'));
prova('file_locale: senza cartella diretta', null, file_locale('a.pdf', 'pdf'));

// ----- miniature: il secondario le chiede al principale -----
prova('miniatura dal principale (img.php)', 'https://vladinc.ru/libri/img.php?t=cover&f=abc.jpg&w=400', miniatura('https://vladinc.ru/libri/uploads/cover/abc.jpg', 400));
prova('miniatura da nome semplice', 'https://vladinc.ru/libri/img.php?t=cover&f=abc.jpg&w=120', miniatura('abc.jpg', 120));
prova('miniatura di un altro sito: invariata', 'https://altro.it/x.jpg', miniatura('https://altro.it/x.jpg', 400));
prova('miniatura vuota', '', miniatura('', 400));

// ----- indirizzo ufficiale -----
prova('base_ufficiale', 'https://vladinc.ru/libri/', base_ufficiale());
prova('assoluto: relativo -> ufficiale', 'https://vladinc.ru/libri/assets/x.jpg', assoluto('assets/x.jpg'));
prova('assoluto: intero resta uguale', 'https://a.it/x.jpg', assoluto('https://a.it/x.jpg'));
prova('percorso_pubblico = questo sito', 'https://libri.vladinc.ru/', percorso_pubblico());

// ----- differenze di linguaggio SQL -----
prova('sql_concat', $modo === 'mysql' ? "CONCAT(',', l.tags, ',')" : "(',' || l.tags || ',')", sql_concat("','", 'l.tags', "','"));
prova('sql_nocase', $modo === 'mysql' ? 'l.titolo' : 'l.titolo COLLATE NOCASE', sql_nocase('l.titolo'));
prova('sql_insert_ignore', $modo === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE', sql_insert_ignore());

// ----- schema: ogni tipo logico ha una definizione per entrambi i motori -----
foreach (['sqlite', 'mysql'] as $d) {
    $tipi = [];
    foreach (schema() as $t => $colonne) foreach ($colonne as $n => $tipo) $tipi[$tipo] = true;
    $mancanti = [];
    foreach (array_keys($tipi) as $tipo) { try { ddl_colonna($tipo, $d); } catch (Throwable $e) { $mancanti[] = $tipo; } }
    prova("schema: tutti i tipi sono definiti per $d", [], $mancanti);
}
prova('MySQL: testo lungo senza valore predefinito', 'MEDIUMTEXT NOT NULL', ddl_colonna('txt', 'mysql'));
prova('MySQL: testo breve', "VARCHAR(255) NOT NULL DEFAULT ''", ddl_colonna('s', 'mysql'));
prova('MySQL: indirizzo del file abbastanza lungo', "VARCHAR(500) NOT NULL DEFAULT ''", ddl_colonna('url', 'mysql'));
prova('MySQL: contatore dei tentativi a 64 bit', 'BIGINT NOT NULL DEFAULT 0', ddl_colonna('big', 'mysql'));
prova('SQLite: id automatico', 'INTEGER PRIMARY KEY AUTOINCREMENT', ddl_colonna('pk', 'sqlite'));
prova('valore predefinito: lingua', "VARCHAR(255) NOT NULL DEFAULT 'it'", ddl_colonna('s:it', 'mysql'));
prova('valore predefinito: visibile', 'INTEGER NOT NULL DEFAULT 1', ddl_colonna('int:1', 'sqlite'));
$colonne_libri = schema()['libri'];
prova('lo schema ha le colonne dei file', true, isset($colonne_libri['copertina'], $colonne_libri['pdf']) && $colonne_libri['pdf'] === 'url');

echo "unit_file [$modo]: OK=$ok KO=$ko\n";
exec('rm -rf ' . escapeshellarg($tmp));
exit($ko ? 1 : 0);
