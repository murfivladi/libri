<?php
// Database: SQLite (predefinito, un solo file) oppure MySQL/MariaDB (consigliato per due siti che condividono i dati).
// La scelta sta in data/config.php, che si crea dalla pagina Admin → Database. Senza quel file si usa SQLite.
// Questo file contiene: configurazione, connessione, schema delle tabelle (uguale per i due motori) e piccole
// funzioni che nascondono le differenze di linguaggio SQL.

const VERSIONE_DB = 12;

// Testo iniziale della pagina Privacy (modificabile dall'admin). È un modello: va controllato da un adulto.
const TESTO_PRIVACY = "Questo sito non usa cookie di profilazione, non usa strumenti di analisi di terzi e non carica script, font o immagini da altri siti.\n\n"
    . "## Cookie tecnici\n\nIl sito usa un solo cookie tecnico di sessione. Serve per l'area riservata e per contare una sola volta le letture di un libro. Si cancella chiudendo il browser e non richiede consenso.\n\n"
    . "## Dati salvati nel tuo dispositivo\n\nIl lettore ricorda la pagina, i segnalibri, il tema e lo zoom nel tuo browser. Questi dati restano solo sul tuo dispositivo: non vengono inviati a noi.\n\n"
    . "## Registri del server\n\nIl servizio di hosting può registrare l'indirizzo IP dei visitatori per motivi di sicurezza, secondo la propria informativa.\n\n"
    . "## Contatti\n\nPer qualsiasi domanda sui tuoi dati puoi scrivere all'indirizzo email indicato in fondo alla pagina.";

// ---------- configurazione ----------
function percorso_config(): string { return getenv('LIBRI_CONFIG') ?: RADICE . '/data/config.php'; }

function config(): array {
    static $c = null;
    if ($c !== null) return $c;
    $c = [];
    $f = percorso_config();
    if (is_file($f)) { $r = include $f; if (is_array($r)) $c = $r; }
    return $c;
}

// Scrive data/config.php (in modo atomico) e lo rende leggibile solo dal proprietario.
function scrivi_config(array $c): void {
    $f = percorso_config();
    @mkdir(dirname($f), 0755, true);
    $tmp = $f . '.tmp' . bin2hex(random_bytes(4));
    $codice = "<?php\n// Creato da Admin → Database. Non va su GitHub e non viene toccato dal deploy.\nreturn " . var_export($c, true) . ";\n";
    if (file_put_contents($tmp, $codice) === false) throw new RuntimeException('Non riesco a scrivere ' . $f . ': controlla che la cartella data/ sia scrivibile.');
    @chmod($tmp, 0600);
    if (!rename($tmp, $f)) { @unlink($tmp); throw new RuntimeException('Non riesco a salvare ' . $f); }
    if (function_exists('opcache_invalidate')) @opcache_invalidate($f, true);
}

function db_driver(): string { return (config()['db_driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite'; }

// ---------- connessione ----------
// Opzioni per aprire una connessione MySQL con i dati di una configurazione (usata anche per provare i dati nella procedura guidata).
function connetti_mysql(array $c, bool $persistente = true): PDO {
    $dsn = 'mysql:host=' . ($c['db_host'] ?? 'localhost') . ';port=' . (int)($c['db_porta'] ?? 3306) . ';dbname=' . ($c['db_nome'] ?? '') . ';charset=utf8mb4';
    $opz = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 8,
        // modalità non rigida: le colonne di testo senza valore predefinito diventano vuote (compatibile con tutte le versioni di MySQL/MariaDB)
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'",
    ];
    if ($persistente) $opz[PDO::ATTR_PERSISTENT] = true;      // evita di rifare la connessione (e la cifratura) a ogni pagina
    $tls = !empty($c['db_tls']);
    if ($tls) {
        $opz[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = !empty($c['db_tls_verifica']);
        if (!empty($c['db_tls_ca'])) $opz[PDO::MYSQL_ATTR_SSL_CA] = $c['db_tls_ca'];
        else $opz[PDO::MYSQL_ATTR_SSL_CIPHER] = 'DEFAULT';        // basta una opzione SSL per attivare la cifratura
    }
    $pdo = new PDO($dsn, $c['db_utente'] ?? '', $c['db_password'] ?? '', $opz);
    // Se il server non ha il TLS la connessione cifrata fallisce da sola (non scende mai a "in chiaro").
    // Nella prova della procedura guidata si controlla anche il cifrario in uso, per mostrarlo.
    if ($tls && !$persistente) {
        $cifrario = (string)($pdo->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM)[1] ?? '');
        if ($cifrario === '') throw new RuntimeException('La connessione NON è cifrata: il server MySQL non ha il TLS attivo. Attivalo sul VPS oppure togli la spunta «connessione cifrata».');
    }
    return $pdo;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        if (db_driver() === 'mysql') {
            $pdo = connetti_mysql(config());
        } else {
            @mkdir(RADICE . '/data', 0755, true);
            $pdo = new PDO('sqlite:' . RADICE . '/data/sito.sqlite');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA busy_timeout = 5000');
        }
        if (versione_db($pdo) < VERSIONE_DB) migra($pdo);
    } catch (Throwable $e) {
        $pdo = null;
        db_errore($e);
    }
    return $pdo;
}

// Il database non risponde: il pubblico vede un avviso gentile, nell'admin si vede il motivo.
function db_errore(Throwable $e): void {
    error_log('Database: ' . $e->getMessage());
    if (PHP_SAPI === 'cli') throw $e;
    http_response_code(503);
    $admin = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false;
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Database non raggiungibile</title>'
       . '<body style="font:17px/1.5 Georgia,serif;max-width:640px;margin:12vh auto;padding:0 16px;text-align:center">';
    if ($admin) {
        echo '<h1>Database non raggiungibile</h1><p><b>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</b></p>'
           . '<p>Controlla i dati in <a href="database.php">Admin → Database</a>. Se non riesci più a entrare, cancella via FTP il file <code>data/config.php</code>: il sito torna a usare SQLite.</p>';
    } else {
        echo '<h1>Torniamo subito</h1><p>Il sito non riesce a raggiungere i suoi dati in questo momento. Riprova tra qualche minuto.</p>';
    }
    exit;
}

function versione_db(PDO $p): int {
    if (db_driver() === 'mysql') {
        try { return (int)$p->query("SELECT valore FROM impostazioni WHERE chiave = 'versione_db'")->fetchColumn(); } catch (Throwable $e) { return 0; }
    }
    return (int)$p->query('PRAGMA user_version')->fetchColumn();
}
function salva_versione_db(PDO $p, int $v): void {
    if (db_driver() === 'mysql') $p->prepare("INSERT INTO impostazioni (chiave, valore) VALUES ('versione_db', ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore)")->execute([(string)$v]);
    else $p->exec('PRAGMA user_version = ' . $v);
}

// ---------- differenze di linguaggio SQL ----------
function sql_concat(string ...$parti): string { return db_driver() === 'mysql' ? 'CONCAT(' . implode(', ', $parti) . ')' : '(' . implode(' || ', $parti) . ')'; }
function sql_nocase(string $colonna): string { return db_driver() === 'mysql' ? $colonna : $colonna . ' COLLATE NOCASE'; }
function sql_insert_ignore(): string { return db_driver() === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE'; }

// ---------- schema ----------
// Tipi logici: pk (id automatico), idfisso, slug, chiave, s (testo breve), url, ip, txt (testo lungo), int, big,
// dt (data come testo), dtnull, creato. "tipo:valore" cambia il valore predefinito.
function schema(): array {
    return [
        'libri' => [
            'id' => 'pk', 'slug' => 'slug', 'titolo' => 's', 'collana' => 's', 'numero' => 'int', 'genere' => 's', 'trama' => 'txt',
            'copertina' => 'url', 'pdf' => 'url', 'pdf_ru' => 'url', 'pubblicato' => 'int', 'creato' => 'creato',
            'sottotitolo' => 's', 'autore' => 's', 'lingua' => 's:it', 'anno' => 'int', 'pagine_n' => 'int', 'isbn' => 's',
            'tags' => 'txt', 'collana_id' => 'int', 'evidenza' => 'int', 'pubblica_il' => 'dt', 'ordine' => 'int',
            'note' => 'txt', 'meta' => 'txt', 'titolo_ru' => 's', 'sottotitolo_ru' => 's', 'trama_ru' => 'txt', 'meta_ru' => 'txt', 'genere_ru' => 's', 'token' => 's', 'letture' => 'int', 'download' => 'int', 'eliminato' => 'dtnull', 'aggiornato' => 'dt',
            'sezione' => 's:narrativa',                 // narrativa | scuola (Vlad Editore Scuola)
        ],
        'collane' => ['id' => 'pk', 'slug' => 'slug', 'nome' => 's', 'descrizione' => 'txt', 'nome_ru' => 's', 'descrizione_ru' => 'txt', 'banner' => 'url', 'visibile' => 'int:1', 'ordine' => 'int'],
        'pagine' => ['id' => 'pk', 'slug' => 'slug', 'titolo' => 's', 'contenuto' => 'txt', 'titolo_ru' => 's', 'contenuto_ru' => 'txt', 'visibile' => 'int:1', 'nel_menu' => 'int', 'ordine' => 'int'],
        'impostazioni' => ['chiave' => 'chiave', 'valore' => 'txt'],
        'log' => ['id' => 'pk', 'quando' => 'dt', 'azione' => 's', 'dettaglio' => 's', 'ip' => 'ip'],
        'tentativi' => ['ip' => 'ip', 'quando' => 'big'],
        'admin' => ['id' => 'idfisso', 'password' => 's', 'recupero' => 's', 'totp_segreto' => 's', 'totp_attivo' => 'int', 'codici_backup' => 'txt:[]'],
        // Lettori registrati (stato: 0 da verificare, 1 attivo, 2 sospeso)
        'lettori' => ['id' => 'pk', 'email' => 'slug', 'nick' => 'slug', 'password' => 's', 'stato' => 'int', 'creato' => 'creato',
            'verificato' => 'dt', 'ultimo_accesso' => 'dt', 'privacy_versione' => 's', 'privacy_il' => 'dt', 'eta_ok' => 'int', 'lingua' => 's:it'],
        'token_lettori' => ['id' => 'pk', 'lettore_id' => 'int', 'tipo' => 's', 'hash' => 's', 'scade' => 'dt', 'dato' => 's'],
        'liste' => ['id' => 'pk', 'lettore_id' => 'int', 'libro_id' => 'int', 'lista' => 's', 'creato' => 'creato'],
        'progressi' => ['id' => 'pk', 'lettore_id' => 'int', 'libro_id' => 'int', 'pagina' => 'int', 'totale' => 'int', 'aggiornato' => 'dt'],
        'segnalibri' => ['id' => 'pk', 'lettore_id' => 'int', 'libro_id' => 'int', 'pagina' => 'int', 'creato' => 'creato'],
        'recensioni' => ['id' => 'pk', 'lettore_id' => 'int', 'libro_id' => 'int', 'voto' => 'int', 'titolo' => 's', 'testo' => 'txt',
            'stato' => 'int', 'creato' => 'creato', 'aggiornato' => 'dt'],
        'consensi' => ['id' => 'pk', 'lettore_id' => 'int', 'tipo' => 's', 'versione' => 's', 'quando' => 'dt', 'esito' => 'int'],
        // Scuola: esercizi interattivi e contenuti multimediali dei libri scolastici. Ognuno ha un codice breve (es. CFC-12)
        // stampato nel PDF accanto al simbolo: il lettore lo riconosce da solo, e si apre anche da apri.php?c=CODICE (QR).
        'esercizi' => ['id' => 'pk', 'codice' => 'slug', 'libro_id' => 'int', 'pagina' => 'int', 'titolo' => 's', 'consegna' => 'txt',
            'tipo' => 's', 'dati' => 'txt:{}', 'extra' => 'int', 'livello' => 'int:1', 'ordine' => 'int', 'creato' => 'creato', 'aggiornato' => 'dt'],
        'contenuti' => ['id' => 'pk', 'codice' => 'slug', 'libro_id' => 'int', 'pagina' => 'int', 'tipo' => 's', 'titolo' => 's',
            'file' => 'url', 'testo' => 'txt', 'voce' => 's', 'durata' => 'int', 'creato' => 'creato'],
        // Zone cliccabili disegnate a mano sulle pagine (in millesimi della pagina): aprono un esercizio o un contenuto.
        'zone' => ['id' => 'pk', 'libro_id' => 'int', 'pagina' => 'int', 'x' => 'int', 'y' => 'int', 'w' => 'int', 'h' => 'int', 'codice' => 's'],
        // Classi: i prof si registrano con il codice segreto dato dall'admin; gli studenti entrano con codice classe + nickname + PIN
        // (nessun dato personale: niente email, niente nome vero). I risultati degli esercizi li vede solo il prof della classe.
        'prof' => ['id' => 'pk', 'email' => 'slug', 'nome' => 's', 'password' => 's', 'stato' => 'int:1', 'creato' => 'creato', 'ultimo_accesso' => 'dt'],
        'classi' => ['id' => 'pk', 'prof_id' => 'int', 'nome' => 's', 'codice' => 'slug', 'libro_id' => 'int', 'creato' => 'creato'],
        'studenti' => ['id' => 'pk', 'classe_id' => 'int', 'nick' => 's', 'pin' => 's', 'token' => 's', 'creato' => 'creato', 'ultimo_accesso' => 'dt'],
        'risultati' => ['id' => 'pk', 'studente_id' => 'int', 'esercizio_id' => 'int', 'compito_id' => 'int', 'fatti' => 'int', 'totale' => 'int', 'voci' => 'txt:[]', 'quando' => 'dt'],
        'compiti' => ['id' => 'pk', 'classe_id' => 'int', 'titolo' => 's', 'esercizi' => 'txt', 'scadenza' => 'dt', 'creato' => 'creato'],
        // Lavagne (editor video): il progetto (oggetti, passi, audio) e il video esportato. prof_id 0 = admin.
        // I video dei prof li vedono solo gli studenti della classe scelta (classe_id).
        'lavagne' => ['id' => 'pk', 'titolo' => 's', 'prof_id' => 'int', 'classe_id' => 'int', 'libro_id' => 'int', 'dati' => 'txt:{}',
            'video' => 'url', 'contenuto_id' => 'int', 'creato' => 'creato', 'aggiornato' => 'dt'],
        // Lavori dell'AI (deAPI): immagini, sfondi tolti, voci, musica, animazioni. Si avviano e poi si controllano finché sono pronti.
        'lavori_ai' => ['id' => 'pk', 'tipo' => 's', 'stato' => 's:attesa', 'request_id' => 's', 'parametri' => 'txt:{}', 'risultato' => 'url',
            'contenuto_id' => 'int', 'errore' => 's', 'creato' => 'creato', 'aggiornato' => 'dt'],
    ];
}

// Indici unici su più colonne (una sola riga per coppia) e indici di ricerca.
function indici(): array {
    return [
        'u_liste' => ['liste', ['lettore_id', 'libro_id', 'lista'], true],
        'u_progressi' => ['progressi', ['lettore_id', 'libro_id'], true],
        'u_segnalibri' => ['segnalibri', ['lettore_id', 'libro_id', 'pagina'], true],
        'u_recensioni' => ['recensioni', ['lettore_id', 'libro_id'], true],
        'i_recensioni_libro' => ['recensioni', ['libro_id', 'stato'], false],
        'i_token_hash' => ['token_lettori', ['hash'], false],
        'i_consensi_lettore' => ['consensi', ['lettore_id'], false],
        'i_esercizi_libro' => ['esercizi', ['libro_id', 'pagina'], false],
        'i_contenuti_libro' => ['contenuti', ['libro_id', 'pagina'], false],
        'i_zone_libro' => ['zone', ['libro_id', 'pagina'], false],
        'u_studenti' => ['studenti', ['classe_id', 'nick'], true],
        'i_classi_prof' => ['classi', ['prof_id'], false],
        'i_risultati' => ['risultati', ['studente_id', 'esercizio_id'], false],
        'i_compiti_classe' => ['compiti', ['classe_id'], false],
        'i_lavagne_prof' => ['lavagne', ['prof_id'], false],
        'i_lavori_stato' => ['lavori_ai', ['stato'], false],
    ];
}

function ddl_colonna(string $tipo, string $driver): string {
    [$t, $d] = array_pad(explode(':', $tipo, 2), 2, null);
    $my = $driver === 'mysql';
    $ds = "'" . str_replace("'", "''", (string)($d ?? '')) . "'";
    switch ($t) {
        case 'pk':      return $my ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        case 'idfisso': return $my ? 'INT NOT NULL PRIMARY KEY' : 'INTEGER PRIMARY KEY';
        case 'slug':    return $my ? 'VARCHAR(190) NOT NULL UNIQUE' : 'TEXT NOT NULL UNIQUE';
        case 'chiave':  return $my ? 'VARCHAR(100) NOT NULL PRIMARY KEY' : 'TEXT PRIMARY KEY';
        case 's':       return ($my ? 'VARCHAR(255)' : 'TEXT') . " NOT NULL DEFAULT $ds";
        case 'url':     return ($my ? 'VARCHAR(500)' : 'TEXT') . " NOT NULL DEFAULT ''";
        case 'ip':      return ($my ? 'VARCHAR(45)' : 'TEXT') . " NOT NULL DEFAULT ''";
        case 'txt':     return $my ? 'MEDIUMTEXT NOT NULL' : "TEXT NOT NULL DEFAULT $ds";
        case 'int':     return ($my ? 'INT' : 'INTEGER') . ' NOT NULL DEFAULT ' . (int)($d ?? 0);
        case 'big':     return ($my ? 'BIGINT' : 'INTEGER') . ' NOT NULL DEFAULT 0';
        case 'dt':      return ($my ? 'VARCHAR(19)' : 'TEXT') . " NOT NULL DEFAULT ''";
        case 'dtnull':  return $my ? 'VARCHAR(19) NULL DEFAULT NULL' : 'TEXT';
        case 'creato':  return $my ? 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' : 'TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP';
    }
    throw new LogicException("Tipo di colonna sconosciuto: $tipo");
}

function colonne_esistenti(PDO $p, string $tabella): array {
    if (db_driver() === 'mysql') return array_column($p->query("SHOW COLUMNS FROM `$tabella`")->fetchAll(), 'Field');
    return array_column($p->query("PRAGMA table_info($tabella)")->fetchAll(), 'name');
}

function tabelle_sito(): array { return array_keys(schema()); }

// Traduzioni russe iniziali (contenuti_ru.php): solo dove il campo è ancora vuoto. Si richiama anche da importa.php dopo l'import dei libri.
function semina_ru(PDO $p): void {
    $ru = require __DIR__ . '/contenuti_ru.php';
    $u = $p->prepare("UPDATE libri SET titolo_ru = ?, genere_ru = ?, trama_ru = ? WHERE slug = ? AND titolo_ru = '' AND trama_ru = ''");
    foreach ($ru['libri'] as $slug => $d) $u->execute([$d['titolo_ru'], $d['genere_ru'], $d['trama_ru'], $slug]);
    $p->prepare("UPDATE collane SET nome_ru = ?, descrizione_ru = ? WHERE slug = ? AND nome_ru = ''")->execute([$ru['collana']['nome_ru'], $ru['collana']['descrizione_ru'], $ru['collana']['slug']]);
    $p->prepare("UPDATE pagine SET titolo_ru = ?, contenuto_ru = ? WHERE slug = 'chi-siamo' AND titolo_ru = ''")->execute([$ru['pagina_chi_siamo']['titolo_ru'], $ru['pagina_chi_siamo']['contenuto_ru']]);
}

function migra(PDO $p): void {
    $driver = db_driver();
    foreach (schema() as $tabella => $colonne) {
        $righe = [];
        foreach ($colonne as $nome => $tipo) $righe[] = "`$nome` " . ddl_colonna($tipo, $driver);
        $p->exec("CREATE TABLE IF NOT EXISTS `$tabella` (" . implode(', ', $righe) . ')' . ($driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : ''));
        $esistenti = colonne_esistenti($p, $tabella);
        foreach ($colonne as $nome => $tipo) {            // colonne aggiunte dalle versioni più recenti
            if (in_array($nome, $esistenti, true) || in_array(explode(':', $tipo)[0], ['pk', 'idfisso', 'slug', 'chiave'], true)) continue;
            try { $p->exec("ALTER TABLE `$tabella` ADD COLUMN `$nome` " . ddl_colonna($tipo, $driver)); }
            catch (Throwable $e) { /* un altro sito che usa lo stesso database l'ha appena aggiunta */ }
        }
    }

    foreach (indici() as $nome => [$tabella, $cols, $unico]) {
        try { $p->exec('CREATE ' . ($unico ? 'UNIQUE ' : '') . "INDEX `$nome` ON `$tabella` (`" . implode('`,`', $cols) . '`)'); }
        catch (Throwable $e) { /* esiste già */ }
    }

    // il vecchio campo testuale "collana" diventa una vera collana
    foreach ($p->query("SELECT DISTINCT collana FROM libri WHERE collana != '' AND collana_id = 0")->fetchAll(PDO::FETCH_COLUMN) as $nome) {
        $id = collana_id_per_nome($nome, $p);
        $p->prepare('UPDATE libri SET collana_id = ? WHERE collana = ? AND collana_id = 0')->execute([$id, $nome]);
    }
    $p->exec("UPDATE libri SET aggiornato = creato WHERE aggiornato = ''");

    $ins = $p->prepare(sql_insert_ignore() . ' INTO pagine (slug,titolo,contenuto,visibile,nel_menu,ordine) VALUES (?,?,?,1,?,?)');
    $ins->execute(['chi-siamo', 'Chi siamo',
        "**Vlad Editore** è la casa editrice fondata da Vlad a Taormina - Trappitello, in Sicilia.\n\nPubblichiamo libri di fantascienza e fantasy, scritti da Vlad con l'aiuto dell'intelligenza artificiale.", 1, 10]);
    $ins->execute(['privacy', 'Privacy', TESTO_PRIVACY, 0, 20]);

    semina_ru($p);

    salva_versione_db($p, VERSIONE_DB);
}
