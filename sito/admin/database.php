<?php
// Procedura guidata: collega il sito a un database MySQL condiviso e dice dove stanno i file.
// Non usa db() per lavorare, così funziona anche quando il database non risponde.
define('SENZA_DB', true);
require __DIR__ . '/../inc/bootstrap.php';

function spiega_errore_db(string $m): string {
    if (stripos($m, 'Access denied') !== false) return $m . ' — Utente o password sbagliati, oppure l\'utente non può collegarsi da questo indirizzo: l\'indirizzo che vede il VPS è quello dopo la «@» e va autorizzato in MySQL (es. GRANT … TO \'utente\'@\'quell\'indirizzo\').';
    if (stripos($m, 'Unknown database') !== false) return $m . ' — Il database non esiste: controlla il nome.';
    if (preg_match('/refused|timed out|timeout|No route|unreachable|getaddrinfo|php_network/i', $m)) return $m . ' — Il VPS non risponde: controlla indirizzo, porta (di solito 3306), il firewall del VPS e che MySQL ascolti anche fuori da localhost (bind-address).';
    if (stripos($m, 'SSL') !== false || stripos($m, 'TLS') !== false || stripos($m, 'cifrata') !== false || stripos($m, 'gone away') !== false) return $m . ' — Problema con la cifratura: se il server MySQL non ha il TLS attivo la connessione cifrata non parte. Attiva il TLS sul VPS (ssl-cert, ssl-key) oppure togli la spunta «connessione cifrata».';
    return $m;
}

// Chi può usare questa pagina: l'admin, oppure chiunque finché il database non ha ancora nessuna password admin (come per la creazione della password).
$consentito = !empty($_SESSION['admin']);
$conta_admin = null;
if (!$consentito) {
    try {
        $p = db_driver() === 'mysql' ? connetti_mysql(config(), false) : db();
        $conta_admin = (int)$p->query('SELECT COUNT(*) FROM admin')->fetchColumn();
        $consentito = $conta_admin === 0;
    } catch (Throwable $e) { $consentito = false; }
}
if (!$consentito) { header('Location: login.php'); exit; }

$c = config();
$errore = $ok = $esito = '';

function config_da_form(array $attuale): array {
    $f = fn($k, $d = '') => trim((string)($_POST[$k] ?? $d));
    $nuova = [
        'db_driver' => 'mysql', 'db_host' => $f('db_host'), 'db_porta' => (int)($f('db_porta') ?: 3306), 'db_nome' => $f('db_nome'), 'db_utente' => $f('db_utente'),
        'db_password' => ($_POST['db_password'] ?? '') !== '' ? (string)$_POST['db_password'] : (string)($attuale['db_password'] ?? ''),
        'db_tls' => !empty($_POST['db_tls']), 'db_tls_verifica' => !empty($_POST['db_tls_verifica']), 'db_tls_ca' => $f('db_tls_ca'),
        'sito_principale' => ($_POST['ruolo'] ?? 'principale') === 'principale',
        'file_url_base' => rtrim($f('file_url_base'), '/'), 'url_ufficiale' => rtrim($f('url_ufficiale'), '/'),
        'uploads_dir_primario' => rtrim($f('uploads_dir_primario'), '/\\'),
    ];
    return $nuova;
}

function prova_connessione(array $cfg): string {
    $p = connetti_mysql($cfg, false);
    $versione = $p->query('SELECT VERSION()')->fetchColumn();
    $cifrario = $cfg['db_tls'] ? ($p->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM)[1] ?? '') : '';
    $tab = 0; $admin = 0;
    try { $tab = count($p->query('SHOW TABLES')->fetchAll()); } catch (Throwable $e) {}
    try { $admin = (int)$p->query('SELECT COUNT(*) FROM admin')->fetchColumn(); } catch (Throwable $e) {}
    return 'Connessione riuscita: MySQL/MariaDB ' . $versione . ($cifrario ? ' · cifrata (' . $cifrario . ')' : ' · NON cifrata')
        . ' · tabelle trovate: ' . $tab . ($tab === 0 ? ' (verranno create)' : '') . ($admin ? ' · c\'è già una password admin: userai quella' : ($tab ? '' : ' · al primo accesso crei la password admin'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifica();
    $az = $_POST['azione'] ?? '';
    try {
        if ($az === 'prova' || $az === 'salva') {
            $cfg = config_da_form($c);
            if ($cfg['db_host'] === '' || $cfg['db_nome'] === '' || $cfg['db_utente'] === '') throw new RuntimeException('Compila indirizzo del server, nome del database e utente.');
            if ($cfg['file_url_base'] !== '' && !preg_match('#^https?://#i', $cfg['file_url_base'])) throw new RuntimeException('L\'indirizzo dei file deve cominciare con https://');
            if (!$cfg['sito_principale'] && $cfg['file_url_base'] === '') throw new RuntimeException('Un sito secondario deve sapere dove stanno i file: scrivi l\'indirizzo del sito principale.');
            $esito = prova_connessione($cfg);
            if ($az === 'salva') {
                scrivi_config($cfg);
                unset($_SESSION['admin']);                       // si rientra con la password del nuovo database
                flash('Collegato al database MySQL. Ora entra con la password admin di quel database (se è nuovo, creane una).');
                header('Location: login.php'); exit;
            }
        } elseif ($az === 'sqlite') {
            if (is_file(percorso_config())) @unlink(percorso_config());
            unset($_SESSION['admin']);
            flash('Il sito è tornato a usare SQLite (un solo sito, dati in locale).');
            header('Location: login.php'); exit;
        } elseif ($az === 'prova_file') {
            if (dir_uploads() !== null) {
                $esito = 'File: scrittura diretta in ' . dir_uploads() . (is_writable(dir_uploads()) ? ' (ok, scrivibile).' : ' — NON scrivibile!');
            } else {
                $r = api_chiama('ping', [], null, '');
                $esito = 'API del sito principale raggiungibile: «' . ($r['sito'] ?? '') . '»' . (!empty($r['scrivibile']) ? ' · la cartella uploads è scrivibile.' : ' · ATTENZIONE: uploads non scrivibile.');
            }
        }
    } catch (Throwable $e) { $errore = spiega_errore_db($e->getMessage()); }
}

// valori da mostrare nel modulo
$v = fn($k, $d = '') => $_POST[$k] ?? ($c[$k] ?? $d);
$mysql_attivo = db_driver() === 'mysql';
$qui = rtrim(percorso_pubblico(), '/');
$ruolo = $_POST['ruolo'] ?? (($c['sito_principale'] ?? true) ? 'principale' : 'secondario');
$suggerita = '';
$dr = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$fb = (string)$v('file_url_base', '');
if ($fb !== '' && substr($dr, -4) === '/www' && ($u = parse_url($fb)) && !empty($u['host'])) $suggerita = dirname($dr, 2) . '/' . $u['host'] . '/www' . rtrim($u['path'] ?? '', '/') . '/uploads';
$ruoli_note = $mysql_attivo ? ('MySQL attivo: ' . ($c['db_host'] ?? '') . ' / ' . ($c['db_nome'] ?? '')) : 'Ora usi SQLite (un solo sito).';
?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Database · Admin</title><link rel="stylesheet" href="../assets/admin.css"></head>
<body class="adm"><div class="corpo"><main class="contenuto-adm" style="max-width:760px;margin:0 auto">
<h1>Database e file</h1>
<p class="muto"><?= e($ruoli_note) ?> · <a href="index.php">← Admin</a></p>
<?php foreach ($_SESSION['flash'] ?? [] as [$tipo, $msg]) echo '<p class="' . ($tipo === 'ok' ? 'ok' : 'errore') . '">' . e($msg) . '</p>'; unset($_SESSION['flash']); ?>
<?php if ($errore): ?><p class="errore"><?= e($errore) ?></p><?php endif; ?>
<?php if ($esito): ?><p class="ok"><?= e($esito) ?></p><?php endif; ?>

<form method="post" class="pannello" autocomplete="off">
<?= csrf_campo() ?>
<h2 style="margin-top:0">1. Il database MySQL</h2>
<p class="muto">Usa gli stessi dati su vladinc.ru/libri e su libri.vladinc.ru: i due siti avranno gli stessi libri, collane, pagine e password.</p>
<div class="form-griglia">
  <label>Indirizzo del server (il VPS) <input type="text" name="db_host" value="<?= e($v('db_host')) ?>" placeholder="es. 203.0.113.10 oppure mysql.miodominio.it" required></label>
  <label>Porta <input type="number" name="db_porta" value="<?= e($v('db_porta', 3306)) ?>"></label>
  <label>Nome del database <input type="text" name="db_nome" value="<?= e($v('db_nome')) ?>" required></label>
  <label>Utente <input type="text" name="db_utente" value="<?= e($v('db_utente')) ?>" required autocomplete="off"></label>
  <label>Password <small><?= !empty($c['db_password']) ? '(lascia vuoto per tenere quella salvata)' : '' ?></small><input type="password" name="db_password" autocomplete="new-password"></label>
  <label class="largo"><span><input type="checkbox" name="db_tls" value="1" <?= ($_POST ? !empty($_POST['db_tls']) : ($c['db_tls'] ?? true)) ? 'checked' : '' ?>> Connessione cifrata (TLS) — consigliata: la password e i dati passano su internet</span>
    <span><input type="checkbox" name="db_tls_verifica" value="1" <?= !empty($v('db_tls_verifica')) ? 'checked' : '' ?>> Controlla anche il certificato del server (da attivare solo se il VPS ha un certificato valido o indichi la CA qui sotto)</span></label>
  <label class="largo">File della CA (facoltativo) <input type="text" name="db_tls_ca" value="<?= e($v('db_tls_ca')) ?>" placeholder="/percorso/ca.pem"></label>
</div>

<h2>2. Dove stanno i file (copertine, PDF, logo)</h2>
<div class="form-griglia">
  <label class="largo"><span><input type="radio" name="ruolo" value="principale" <?= $ruolo === 'principale' ? 'checked' : '' ?>> <b>Questo è il sito principale</b>: i file stanno qui, in <code>uploads/</code> (es. vladinc.ru/libri)</span>
    <span><input type="radio" name="ruolo" value="secondario" <?= $ruolo === 'secondario' ? 'checked' : '' ?>> <b>Questo è un sito secondario</b>: i file stanno sul sito principale (es. libri.vladinc.ru)</span></label>
  <label class="largo">Indirizzo dei file = indirizzo del sito principale, senza barra finale
    <input type="text" name="file_url_base" value="<?= e($v('file_url_base', $ruolo === 'principale' ? $qui : '')) ?>" placeholder="https://vladinc.ru/libri"></label>
  <label class="largo">Indirizzo «ufficiale» per Google e per i link condivisi <small>(di solito lo stesso del sito principale)</small>
    <input type="text" name="url_ufficiale" value="<?= e($v('url_ufficiale')) ?>" placeholder="https://vladinc.ru/libri"></label>
  <label class="largo">Solo sito secondario — cartella <code>uploads</code> del principale sul disco <small>(facoltativa: se il server lo permette i file si salvano direttamente lì, altrimenti si usa l'API)</small>
    <input type="text" name="uploads_dir_primario" value="<?= e($v('uploads_dir_primario', $suggerita)) ?>" placeholder="<?= e($suggerita ?: '/home/utente/vladinc.ru/www/libri/uploads') ?>"></label>
</div>
<p class="azioni-riga" style="margin-top:14px">
  <button class="bottone sec" name="azione" value="prova">Prova la connessione</button>
  <button class="bottone" name="azione" value="salva" data-conferma="Salvare e collegare il sito a questo database? Dovrai rientrare con la password del database.">Salva e collega</button>
</p>
</form>

<?php if ($mysql_attivo): ?>
<div class="pannello"><h2 style="margin-top:0">Controlli</h2>
  <form method="post" class="azioni-riga"><?= csrf_campo() ?>
    <button class="bottone sec piccolo" name="azione" value="prova_file">Prova il salvataggio dei file</button>
    <button class="bottone pericolo piccolo" name="azione" value="sqlite" data-conferma="Tornare a SQLite? Il sito userà di nuovo il file locale e non più il database condiviso.">Torna a SQLite</button></form>
  <p class="muto">Se un giorno non riesci più a entrare, cancella via FTP il file <code>data/config.php</code>: il sito torna a SQLite.</p>
</div>
<?php endif; ?>
<script src="../assets/admin.js"></script></main></div></body></html>
