<?php
// Lettori registrati: email (SMTP), token, accesso, limiti di tentativi, liste, recensioni, esportazione e cancellazione.
require_once __DIR__ . '/pubblico.php';
require_once __DIR__ . '/vendor/phpmailer/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

const ORE_VERIFICA = 48;
const GIORNI_RICORDAMI = 30;

// ---------- stato della funzione ----------
function smtp_configurato(): bool { return impost('smtp_host') !== '' && filter_var(impost('email_mittente'), FILTER_VALIDATE_EMAIL) !== false; }
function titolare_compilato(): bool { return trim(impost('titolare_nome')) !== '' && filter_var(impost('titolare_email'), FILTER_VALIDATE_EMAIL) !== false; }
// La registrazione si apre solo con un titolare del trattamento (un adulto) e le email funzionanti.
function registrazione_aperta(): bool { return impost('registrazione_attiva') === '1' && titolare_compilato() && smtp_configurato(); }
function privacy_versione(): string { return impost('privacy_versione') !== '' ? impost('privacy_versione') : '1'; }

// ---------- email ----------
// Invia una email di testo + HTML. Restituisce null se è partita, altrimenti il motivo dell'errore.
function invia_email(string $a, string $oggetto, string $testo, string $bottoneUrl = '', string $bottoneTesto = ''): ?string {
    if (!smtp_configurato()) return 'Le email non sono configurate.';
    $nome = impost('nome_sito');
    $html = '<div style="font:16px/1.5 Georgia,serif;color:#1d2433;max-width:560px;margin:auto"><div style="background:' . e(colore_valido(impost('colore_blu'), '#0b1a36')) . ';color:#f3e7c6;padding:14px 18px;letter-spacing:.1em">' . e(strtoupper($nome)) . '</div><div style="padding:18px">'
          . nl2br(e($testo))
          . ($bottoneUrl !== '' ? '<p style="margin:22px 0"><a href="' . e($bottoneUrl) . '" style="background:' . e(colore_valido(impost('colore_blu'), '#0b1a36')) . ';color:#f3e7c6;padding:11px 20px;border-radius:6px;text-decoration:none">' . e($bottoneTesto) . '</a></p><p style="font-size:13px;color:#777">Se il pulsante non funziona copia questo indirizzo nel browser:<br>' . e($bottoneUrl) . '</p>' : '')
          . '</div><div style="padding:0 18px 18px;font-size:12px;color:#777">' . e($nome) . ' · ' . e(impost('footer')) . '</div></div>';
    $piano = $testo . ($bottoneUrl !== '' ? "\n\n$bottoneTesto: $bottoneUrl" : '') . "\n\n— $nome";
    try {
        $m = new PHPMailer(true);
        $m->isSMTP();
        $m->Host = impost('smtp_host');
        $m->Port = (int)impost('smtp_porta') ?: 587;
        $m->SMTPAuth = impost('smtp_utente') !== '';
        $m->Username = impost('smtp_utente');
        $m->Password = impost('smtp_password');
        $cif = impost('smtp_cifratura');
        $m->SMTPSecure = $cif === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : ($cif === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
        $m->SMTPAutoTLS = $cif === 'tls';
        $m->Timeout = 12;
        $m->CharSet = 'UTF-8';
        $m->setFrom(impost('email_mittente'), impost('nome_mittente') !== '' ? impost('nome_mittente') : $nome);
        if (filter_var(impost('titolare_email'), FILTER_VALIDATE_EMAIL)) $m->addReplyTo(impost('titolare_email'));
        $m->addAddress($a);
        $m->Subject = $oggetto;
        $m->isHTML(true);
        $m->Body = $html;
        $m->AltBody = $piano;
        $m->send();
        return null;
    } catch (Throwable $e) {
        error_log('Email non inviata: ' . $e->getMessage());
        return $e->getMessage();
    }
}

// ---------- limiti di tentativi (riusa la tabella "tentativi") ----------
function chiave_limite(string $c): string { return 'r' . substr(sha1($c), 0, 40); }
// true = si può procedere (e il tentativo viene contato); false = troppi tentativi
function limite_ok(string $chiave, int $max, int $secondi): bool {
    $k = chiave_limite($chiave);
    $pdo = db();
    $pdo->prepare('DELETE FROM tentativi WHERE quando < ?')->execute([time() - 86400]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM tentativi WHERE ip = ? AND quando > ?');
    $st->execute([$k, time() - $secondi]);
    if ((int)$st->fetchColumn() >= $max) return false;
    $pdo->prepare('INSERT INTO tentativi (ip, quando) VALUES (?, ?)')->execute([$k, time()]);
    return true;
}
function azzera_limite(string $chiave): void { db()->prepare('DELETE FROM tentativi WHERE ip = ?')->execute([chiave_limite($chiave)]); }

// ---------- token (a uso singolo, salvati solo come hash) ----------
function crea_token(int $lettore, string $tipo, int $minuti, string $dato = ''): string {
    if ($tipo !== 'ricorda') db()->prepare('DELETE FROM token_lettori WHERE lettore_id = ? AND tipo = ?')->execute([$lettore, $tipo]);
    $raw = bin2hex(random_bytes(24));
    db()->prepare('INSERT INTO token_lettori (lettore_id, tipo, hash, scade, dato) VALUES (?,?,?,?,?)')
        ->execute([$lettore, $tipo, hash('sha256', $raw), date('Y-m-d H:i:s', time() + $minuti * 60), $dato]);
    return $raw;
}
function leggi_token(string $raw, string $tipo): ?array {
    if (!preg_match('/^[0-9a-f]{48}$/', $raw)) return null;
    $st = db()->prepare('SELECT * FROM token_lettori WHERE hash = ? AND tipo = ?');
    $st->execute([hash('sha256', $raw), $tipo]);
    $t = $st->fetch();
    if (!$t) return null;
    if ($t['scade'] < date('Y-m-d H:i:s')) { db()->prepare('DELETE FROM token_lettori WHERE id = ?')->execute([$t['id']]); return null; }
    return $t;
}
function brucia_token(array $t): void { db()->prepare('DELETE FROM token_lettori WHERE id = ?')->execute([$t['id']]); }

// Pulizia: token scaduti e account mai verificati dopo 7 giorni (nessun dato inutile resta nel database).
function pulisci_lettori(): int {
    $pdo = db();
    $pdo->prepare('DELETE FROM token_lettori WHERE scade < ?')->execute([date('Y-m-d H:i:s')]);
    $st = $pdo->prepare("SELECT id FROM lettori WHERE stato = 0 AND creato < ?");
    $st->execute([date('Y-m-d H:i:s', time() - 7 * 86400)]);
    $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) { cancella_lettore((int)$id); $n++; }
    return $n;
}

// ---------- accesso ----------
function lettore_corrente(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    $id = (int)($_SESSION['lettore'] ?? 0);
    if (!$id && !empty($_COOKIE['ricordami']) && preg_match('/^(\d+)\.([0-9a-f]{48})$/', $_COOKIE['ricordami'], $m)) {
        $t = leggi_token($m[2], 'ricorda');
        if ($t && (int)$t['lettore_id'] === (int)$m[1]) { $id = (int)$t['lettore_id']; $_SESSION['lettore'] = $id; }
        else cookie_ricordami('', 0);
    }
    $cache = null;
    if ($id) {
        $st = db()->prepare('SELECT * FROM lettori WHERE id = ? AND stato = 1');
        $st->execute([$id]);
        $cache = $st->fetch() ?: null;
        if (!$cache) { unset($_SESSION['lettore']); }
    }
    return $cache;
}
function cookie_ricordami(string $valore, int $scade): void {
    setcookie('ricordami', $valore, ['expires' => $scade, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
}
function accedi_lettore(array $l, bool $ricorda): void {
    session_regenerate_id(true);
    $_SESSION['lettore'] = (int)$l['id'];
    db()->prepare('UPDATE lettori SET ultimo_accesso = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $l['id']]);
    if ($ricorda) cookie_ricordami($l['id'] . '.' . crea_token((int)$l['id'], 'ricorda', GIORNI_RICORDAMI * 1440), time() + GIORNI_RICORDAMI * 86400);
}
function esci_lettore(): void {
    if (!empty($_COOKIE['ricordami']) && preg_match('/^(\d+)\.([0-9a-f]{48})$/', $_COOKIE['ricordami'], $m)) {
        db()->prepare('DELETE FROM token_lettori WHERE hash = ? AND tipo = ?')->execute([hash('sha256', $m[2]), 'ricorda']);
    }
    cookie_ricordami('', 0);
    unset($_SESSION['lettore']);
    session_regenerate_id(true);
}
// Su tutti i dispositivi: dopo un cambio password cadono anche i «ricordami».
function dimentica_dispositivi(int $id): void { db()->prepare("DELETE FROM token_lettori WHERE lettore_id = ? AND tipo = 'ricorda'")->execute([$id]); }

// ---------- controlli sui dati ----------
function email_pulita(string $e): string { return strtolower(trim($e)); }
function nick_errore(string $n): ?string {
    if (!preg_match('/^[A-Za-z0-9_.-]{3,20}$/', $n)) return 'Il nome pubblico deve avere da 3 a 20 caratteri: lettere, numeri, punto, trattino o underscore.';
    if (preg_match('/admin|editore|vlad|moderat|staff|support|sistema/i', $n)) return 'Questo nome non si può usare: scegline un altro.';
    return null;
}
function password_errore(string $p, string $email = '', string $nick = ''): ?string {
    if (strlen($p) < 10) return 'La password deve avere almeno 10 caratteri.';
    if (strlen($p) > 200) return 'La password è troppo lunga.';
    if (strtolower($p) === strtolower($email) || ($nick !== '' && strtolower($p) === strtolower($nick))) return 'La password non può essere uguale all\'email o al nome pubblico.';
    if (preg_match('/^(.)\1+$/', $p) || in_array(strtolower($p), ['password12', 'password123', '1234567890', 'qwertyuiop', '0123456789'], true)) return 'Questa password è troppo facile da indovinare.';
    return null;
}
function lettore_per_email(string $email): ?array {
    $st = db()->prepare('SELECT * FROM lettori WHERE email = ?'); $st->execute([email_pulita($email)]);
    return $st->fetch() ?: null;
}
function nick_usato(string $nick, int $escludi = 0): bool {
    $st = db()->prepare('SELECT 1 FROM lettori WHERE LOWER(nick) = ? AND id != ?'); $st->execute([strtolower($nick), $escludi]);
    return (bool)$st->fetchColumn();
}
function registra_consenso(int $id, string $tipo, int $esito = 1): void {
    db()->prepare('INSERT INTO consensi (lettore_id, tipo, versione, quando, esito) VALUES (?,?,?,?,?)')->execute([$id, $tipo, privacy_versione(), date('Y-m-d H:i:s'), $esito]);
}
function url_sito(string $pagina): string { return base_ufficiale() . $pagina; }

// ---------- cancellazione ed esportazione ----------
function cancella_lettore(int $id): void {
    $pdo = db();
    foreach (['liste', 'progressi', 'segnalibri', 'recensioni', 'consensi', 'token_lettori'] as $t) $pdo->prepare("DELETE FROM $t WHERE lettore_id = ?")->execute([$id]);
    $pdo->prepare('DELETE FROM lettori WHERE id = ?')->execute([$id]);
}
function esporta_lettore(int $id): array {
    $pdo = db();
    $q = function (string $sql) use ($pdo, $id) { $s = $pdo->prepare($sql); $s->execute([$id]); return $s->fetchAll(); };
    $l = $q('SELECT email, nick, stato, creato, verificato, ultimo_accesso, privacy_versione, privacy_il, eta_ok FROM lettori WHERE id = ?')[0] ?? [];
    return [
        'esportato_il' => date('c'), 'sito' => impost('nome_sito'), 'account' => $l,
        'liste' => $q('SELECT l.slug AS libro, x.lista, x.creato FROM liste x JOIN libri l ON l.id = x.libro_id WHERE x.lettore_id = ?'),
        'progressi' => $q('SELECT l.slug AS libro, x.pagina, x.totale, x.aggiornato FROM progressi x JOIN libri l ON l.id = x.libro_id WHERE x.lettore_id = ?'),
        'segnalibri' => $q('SELECT l.slug AS libro, x.pagina, x.creato FROM segnalibri x JOIN libri l ON l.id = x.libro_id WHERE x.lettore_id = ?'),
        'recensioni' => $q('SELECT l.slug AS libro, x.voto, x.titolo, x.testo, x.stato, x.creato, x.aggiornato FROM recensioni x JOIN libri l ON l.id = x.libro_id WHERE x.lettore_id = ?'),
        'consensi' => $q('SELECT tipo, versione, quando, esito FROM consensi WHERE lettore_id = ?'),
    ];
}

// ---------- liste, progressi, segnalibri ----------
const LISTE_VALIDE = ['preferito', 'da_leggere'];
function in_lista(int $lettore, int $libro, string $lista): bool {
    $st = db()->prepare('SELECT 1 FROM liste WHERE lettore_id = ? AND libro_id = ? AND lista = ?'); $st->execute([$lettore, $libro, $lista]);
    return (bool)$st->fetchColumn();
}
// Aggiunge o toglie; restituisce lo stato nuovo.
function commuta_lista(int $lettore, int $libro, string $lista, ?bool $voluto = null): bool {
    if (!in_array($lista, LISTE_VALIDE, true)) return false;
    $c = in_lista($lettore, $libro, $lista);
    $nuovo = $voluto ?? !$c;
    if ($nuovo && !$c) db()->prepare(sql_insert_ignore() . ' INTO liste (lettore_id, libro_id, lista, creato) VALUES (?,?,?,?)')->execute([$lettore, $libro, $lista, date('Y-m-d H:i:s')]);
    if (!$nuovo && $c) db()->prepare('DELETE FROM liste WHERE lettore_id = ? AND libro_id = ? AND lista = ?')->execute([$lettore, $libro, $lista]);
    return $nuovo;
}
function salva_progresso(int $lettore, int $libro, int $pagina, int $totale): void {
    $pagina = max(1, min($pagina, 5000)); $totale = max(0, min($totale, 5000));
    $ora = date('Y-m-d H:i:s');
    $st = db()->prepare('UPDATE progressi SET pagina = ?, totale = ?, aggiornato = ? WHERE lettore_id = ? AND libro_id = ?');
    $st->execute([$pagina, $totale, $ora, $lettore, $libro]);
    if (!$st->rowCount()) db()->prepare(sql_insert_ignore() . ' INTO progressi (lettore_id, libro_id, pagina, totale, aggiornato) VALUES (?,?,?,?,?)')->execute([$lettore, $libro, $pagina, $totale, $ora]);
}
function segnalibri_di(int $lettore, int $libro): array {
    $st = db()->prepare('SELECT pagina FROM segnalibri WHERE lettore_id = ? AND libro_id = ? ORDER BY pagina'); $st->execute([$lettore, $libro]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}
function imposta_segnalibro(int $lettore, int $libro, int $pagina, bool $presente): void {
    if ($pagina < 1 || $pagina > 5000) return;
    if ($presente) {
        $n = db()->prepare('SELECT COUNT(*) FROM segnalibri WHERE lettore_id = ? AND libro_id = ?'); $n->execute([$lettore, $libro]);
        if ((int)$n->fetchColumn() >= 200) return;
        db()->prepare(sql_insert_ignore() . ' INTO segnalibri (lettore_id, libro_id, pagina, creato) VALUES (?,?,?,?)')->execute([$lettore, $libro, $pagina, date('Y-m-d H:i:s')]);
    } else db()->prepare('DELETE FROM segnalibri WHERE lettore_id = ? AND libro_id = ? AND pagina = ?')->execute([$lettore, $libro, $pagina]);
}

// ---------- recensioni ----------
function voti_libro(int $libro): array {
    $st = db()->prepare('SELECT COUNT(*) AS n, AVG(voto) AS media FROM recensioni WHERE libro_id = ? AND stato = 1'); $st->execute([$libro]);
    $r = $st->fetch();
    return ['n' => (int)$r['n'], 'media' => $r['n'] ? round((float)$r['media'], 1) : 0.0];
}
function stelle(float $v): string { $p = (int)round($v); return str_repeat('★', $p) . str_repeat('☆', 5 - $p); }
function salva_recensione(int $lettore, int $libro, int $voto, string $titolo, string $testo): void {
    $stato = impost('recensioni_moderate') === '0' ? 1 : 0;
    $ora = date('Y-m-d H:i:s');
    $st = db()->prepare('UPDATE recensioni SET voto = ?, titolo = ?, testo = ?, stato = ?, aggiornato = ? WHERE lettore_id = ? AND libro_id = ?');
    $st->execute([$voto, $titolo, $testo, $stato, $ora, $lettore, $libro]);
    $esiste = db()->prepare('SELECT 1 FROM recensioni WHERE lettore_id = ? AND libro_id = ?'); $esiste->execute([$lettore, $libro]);
    if (!$esiste->fetchColumn())
        db()->prepare('INSERT INTO recensioni (lettore_id, libro_id, voto, titolo, testo, stato, aggiornato) VALUES (?,?,?,?,?,?,?)')->execute([$lettore, $libro, $voto, $titolo, $testo, $stato, $ora]);
}

// ---------- testo della privacy ----------
const PRIVACY_MODELLO = <<<'TXT'
Questa è l'informativa sul trattamento dei dati personali di {sito}, ai sensi degli articoli 13 e 14 del Regolamento (UE) 2016/679 (GDPR). Versione {versione}.

## Chi è il titolare

Il titolare del trattamento è {titolare}{indirizzo}. Per qualsiasi richiesta scrivi a {email_titolare}.

## Se non ti registri

Puoi leggere tutti i libri senza registrarti. Il sito non usa cookie di profilazione, non usa strumenti di analisi di terzi e non carica script, font o immagini da altri siti. Usa un solo cookie tecnico di sessione, che serve per il funzionamento del sito e per contare una sola volta le letture di un libro: non richiede consenso e si cancella chiudendo il browser. Il lettore ricorda pagina, segnalibri, tema e zoom nel tuo dispositivo: questi dati restano lì e non vengono inviati a noi. I PDF dei libri non si possono scaricare.

## Se ti registri

Quali dati raccogliamo: indirizzo email, nome pubblico (nickname), password (salvata solo in forma cifrata, non leggibile da noi), data della registrazione e dell'ultimo accesso, la versione di questa informativa che hai accettato e quando. Quando usi l'account salviamo anche i tuoi libri preferiti e da leggere, la pagina a cui sei arrivato, i segnalibri e le eventuali recensioni e voti.

Perché li usiamo e su quale base giuridica:
- creare e gestire il tuo account, farti accedere e ritrovare i tuoi libri su ogni dispositivo: esecuzione del servizio che hai richiesto registrandoti (art. 6.1.b GDPR);
- inviarti le email di servizio (conferma dell'indirizzo, recupero della password, avvisi di sicurezza): stessa base; non inviamo newsletter né pubblicità;
- pubblicare le tue recensioni con il tuo nome pubblico, dopo la verifica del titolare: il tuo consenso (art. 6.1.a), che puoi ritirare cancellando la recensione;
- difendere il sito da abusi (limiti di tentativi, controllo dei dati inseriti): legittimo interesse (art. 6.1.f).

Il cookie «ricordami» viene creato solo se lo scegli all'accesso, dura {giorni} giorni e permette di restare collegato su quel dispositivo. Puoi toglierlo uscendo dall'account.

## Chi vede i tuoi dati

Solo il titolare. La tua email non è mai pubblica: gli altri lettori vedono solo il nome pubblico accanto alle recensioni. Non vendiamo né cediamo i dati a nessuno. Per spedire le email ci appoggiamo al servizio di posta indicato dal titolare (fornitore SMTP), che le trasmette per nostro conto, e al servizio di hosting e di database su cui il sito funziona, che conservano i dati per nostro conto. I dati non vengono trasferiti fuori dallo Spazio Economico Europeo dal titolare di propria iniziativa.

## Per quanto tempo

Conserviamo i dati finché l'account esiste. Se non confermi l'email entro 7 giorni, l'account viene cancellato. Se cancelli l'account, tutti i tuoi dati (liste, segnalibri, progressi, recensioni, registro dei consensi) vengono eliminati subito. Le copie di sicurezza del database, se ci sono, si sovrascrivono con il tempo.

## I tuoi diritti

Dal tuo profilo puoi in ogni momento: vedere e correggere i tuoi dati, scaricare una copia dei tuoi dati in formato leggibile (portabilità), cancellare l'account e tutti i dati. Puoi inoltre chiedere al titolare la limitazione o l'opposizione al trattamento, e proporre reclamo al Garante per la protezione dei dati personali (www.garanteprivacy.it).

## Minori

In Italia chi ha almeno 14 anni può registrarsi da solo a un servizio online. Se hai meno di 14 anni puoi registrarti solo con il consenso di un genitore o di chi esercita la responsabilità genitoriale: per questo, registrandoti, confermi di avere almeno 14 anni oppure l'accordo di un genitore o tutore.

## Modifiche

Se questa informativa cambia in modo importante, cambia anche la versione e ti verrà chiesto di accettarla di nuovo.
TXT;

function testo_privacy(): string {
    $t = trim(impost('privacy_testo')) !== '' ? impost('privacy_testo') : PRIVACY_MODELLO;
    $nd = '__da compilare__';
    return strtr($t, [
        '{sito}' => impost('nome_sito'), '{versione}' => privacy_versione(), '{giorni}' => (string)GIORNI_RICORDAMI,
        '{titolare}' => trim(impost('titolare_nome')) !== '' ? impost('titolare_nome') : $nd,
        '{indirizzo}' => trim(impost('titolare_indirizzo')) !== '' ? ', ' . impost('titolare_indirizzo') : '',
        '{email_titolare}' => impost('titolare_email') !== '' ? impost('titolare_email') : $nd,
    ]);
}
