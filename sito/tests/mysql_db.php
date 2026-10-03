<?php
// Aiuto per i test con MySQL: crea o elimina un database di prova e scrive un file di configurazione.
// Uso:  php mysql_db.php crea FILE_CONFIG NOME_DB [chiave=valore ...]     (valori extra finiscono nella configurazione)
//       php mysql_db.php elimina NOME_DB
// Credenziali dall'ambiente: E2E_MYSQL_HOST (127.0.0.1), E2E_MYSQL_PORT (3306), E2E_MYSQL_USER (root), E2E_MYSQL_PASS ('')
$h = getenv('E2E_MYSQL_HOST') ?: '127.0.0.1';
$porta = (int)(getenv('E2E_MYSQL_PORT') ?: 3306);
$u = getenv('E2E_MYSQL_USER') ?: 'root';
$pw = getenv('E2E_MYSQL_PASS') ?: '';
$pdo = new PDO("mysql:host=$h;port=$porta;charset=utf8mb4", $u, $pw, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

[$_, $azione] = [$argv[0], $argv[1] ?? ''];
if ($azione === 'crea') {
    [$file, $nome] = [$argv[2], $argv[3]];
    $pdo->exec("DROP DATABASE IF EXISTS `$nome`");
    $pdo->exec("CREATE DATABASE `$nome` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $cfg = ['db_driver' => 'mysql', 'db_host' => $h, 'db_porta' => $porta, 'db_nome' => $nome, 'db_utente' => $u, 'db_password' => $pw, 'db_tls' => false];
    foreach (array_slice($argv, 4) as $kv) {
        [$k, $v] = explode('=', $kv, 2);
        $cfg[$k] = $v === 'true' ? true : ($v === 'false' ? false : $v);
    }
    file_put_contents($file, "<?php\nreturn " . var_export($cfg, true) . ";\n");
} elseif ($azione === 'config') {              // solo un altro file di configurazione per lo stesso database (secondo sito)
    [$file, $nome] = [$argv[2], $argv[3]];
    $cfg = ['db_driver' => 'mysql', 'db_host' => $h, 'db_porta' => $porta, 'db_nome' => $nome, 'db_utente' => $u, 'db_password' => $pw, 'db_tls' => false];
    foreach (array_slice($argv, 4) as $kv) {
        [$k, $v] = explode('=', $kv, 2);
        $cfg[$k] = $v === 'true' ? true : ($v === 'false' ? false : $v);
    }
    file_put_contents($file, "<?php\nreturn " . var_export($cfg, true) . ";\n");
} elseif ($azione === 'elimina') {
    $pdo->exec('DROP DATABASE IF EXISTS `' . $argv[2] . '`');
} else {
    fwrite(STDERR, "Uso: crea FILE_CONFIG NOME_DB | elimina NOME_DB\n"); exit(1);
}
