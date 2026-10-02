<?php
// Area del lettore: registrazione, conferma email, accesso, password dimenticata, profilo, liste, recensioni, dati personali.
require __DIR__ . '/inc/lettori.php';

$a = (string)($_GET['a'] ?? 'profilo');
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$l = lettore_corrente();
if ($post) csrf_verifica();

function vai(string $url): void { header('Location: ' . $url); exit; }
function ritorno_sicuro(string $r): string { return preg_match('~^[a-z0-9_-]+\.php(\?[A-Za-z0-9=&%._-]*)?$~', $r) ? $r : 'account.php'; }
function testata_account(string $titolo): void {
    intestazione(t($titolo), '', '', ['noindex' => true]);
    echo '<div class="pagina-testo account">';
    foreach ($_SESSION['flash'] ?? [] as [$tipo, $msg]) echo '<p class="' . ($tipo === 'ok' ? 'ok-pub' : 'errore') . '">' . e(t($msg)) . '</p>';
    unset($_SESSION['flash']);
    echo '<h1>' . e(t($titolo)) . '</h1>';
}
function piede_account(): void { echo '</div>'; piede(); exit; }
function mostra_errori(array $errori): void { foreach ($errori as $m) echo '<p class="errore">' . e($m) . '</p>'; }
function campo(string $nome, string $etichetta, string $tipo = 'text', string $valore = '', string $extra = ''): string {
    return '<label>' . e($etichetta) . '<input type="' . $tipo . '" name="' . e($nome) . '" value="' . e($valore) . '" ' . $extra . '></label>';
}
// Chi ha accettato una versione vecchia dell'informativa deve riaccettarla prima di usare l'account.
function privacy_da_riaccettare(?array $l): bool { return $l && $l['privacy_versione'] !== privacy_versione(); }

// ---------- registrazione ----------
if ($a === 'registrati') {
    if ($l) vai('account.php');
    $errori = []; $fatto = false; $v = ['email' => '', 'nick' => ''];
    if ($post) {
        $v = ['email' => email_pulita($_POST['email'] ?? ''), 'nick' => trim($_POST['nick'] ?? '')];
        $pw = (string)($_POST['password'] ?? '');
        if (!registrazione_aperta()) $errori[] = t('Le registrazioni sono chiuse.');
        elseif (($_POST['sito_web'] ?? '') !== '') $fatto = true;                           // trappola per i bot: finge di riuscire
        elseif (time() - (int)($_SESSION['reg_ts'] ?? time()) < 3) $errori[] = t('Hai compilato troppo in fretta: riprova.');
        elseif (!limite_ok('reg:' . ip_client(), 10, 3600)) $errori[] = t('Troppi tentativi da questa connessione. Riprova più tardi.');
        else {
            if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) $errori[] = t('Scrivi un indirizzo email valido.');
            if ($m = nick_errore($v['nick'])) $errori[] = $m;
            if ($m = password_errore($pw, $v['email'], $v['nick'])) $errori[] = $m;
            elseif ($pw !== (string)($_POST['password2'] ?? '')) $errori[] = t('Le due password non coincidono.');
            if (empty($_POST['privacy'])) $errori[] = t('Per registrarti devi accettare l\'informativa sulla privacy.');
            if (empty($_POST['eta'])) $errori[] = t('Devi confermare di avere almeno 14 anni o l\'accordo di un genitore.');
            if (!$errori && nick_usato($v['nick'])) $errori[] = t('Questo nome pubblico è già usato: scegline un altro.');
            if (!$errori) {
                $esiste = lettore_per_email($v['email']);
                if ($esiste) {                                                              // nessuna informazione a chi non è il proprietario dell'email
                    if ((int)$esiste['stato'] === 0) {
                        $t = crea_token((int)$esiste['id'], 'verifica', ORE_VERIFICA * 60);
                        invia_email($esiste['email'], t('Conferma la tua email') . ' · ' . impost('nome_sito'), tf("Ciao %s,\nper attivare il tuo account conferma l'indirizzo email.", $esiste['nick']), url_sito('account.php?a=verifica&t=' . $t), t('Conferma la mia email'));
                    } else {
                        invia_email($esiste['email'], t('Hai già un account') . ' · ' . impost('nome_sito'), tf("Qualcuno ha provato a registrarsi con questo indirizzo, ma hai già un account (nome pubblico: %s).\nSe sei stato tu e non ricordi la password, puoi reimpostarla. Se non sei stato tu, ignora questo messaggio.", $esiste['nick']), url_sito('account.php?a=dimenticata'), t('Reimposta la password'));
                    }
                    $fatto = true;
                } else {
                    try {
                        db()->prepare('INSERT INTO lettori (email, nick, password, stato, verificato, ultimo_accesso, privacy_versione, privacy_il, eta_ok, lingua) VALUES (?,?,?,0,?,?,?,?,1,?)')
                            ->execute([$v['email'], $v['nick'], password_hash($pw, PASSWORD_DEFAULT), '', '', privacy_versione(), date('Y-m-d H:i:s'), lingua()]);
                        $id = (int)db()->lastInsertId();
                        registra_consenso($id, 'privacy'); registra_consenso($id, 'eta');
                        $t = crea_token($id, 'verifica', ORE_VERIFICA * 60);
                        $err = invia_email($v['email'], t('Conferma la tua email') . ' · ' . impost('nome_sito'), tf("Ciao %s,\nbenvenuto da %s! Per attivare il tuo account conferma l'indirizzo email: il link vale %d ore.", $v['nick'], impost('nome_sito'), ORE_VERIFICA), url_sito('account.php?a=verifica&t=' . $t), t('Conferma la mia email'));
                        if ($err !== null) { cancella_lettore($id); $errori[] = t('Non siamo riusciti a spedire l\'email di conferma. Controlla l\'indirizzo e riprova più tardi.'); }
                        else $fatto = true;
                    } catch (PDOException $e) { $errori[] = t('Questo indirizzo o questo nome sono già registrati.'); }
                }
                if (random_int(1, 20) === 1) pulisci_lettori();
            }
        }
    } else $_SESSION['reg_ts'] = time();
    if ($post && !$fatto) $_SESSION['reg_ts'] = time();
    testata_account('Registrati');
    if ($fatto) {
        echo '<p class="ok-pub">' . e(tf('Controlla la tua email: ti abbiamo scritto un link per confermare l\'indirizzo. Il link vale %d ore. Guarda anche nella posta indesiderata.', ORE_VERIFICA)) . '</p>';
        piede_account();
    }
    if (!registrazione_aperta()) { echo '<p>' . e(t('Le registrazioni sono chiuse per ora. Puoi leggere tutti i libri anche senza account.')) . '</p>'; piede_account(); }
    mostra_errori($errori);
    ?>
    <p><?= e(t('Con un account ritrovi su ogni dispositivo la pagina a cui sei arrivato, i segnalibri, i libri preferiti e quelli da leggere, e puoi lasciare un voto e una recensione. Leggere è gratis e non serve registrarsi.')) ?></p>
    <form method="post" class="form-pub" autocomplete="on">
      <?= csrf_campo() ?>
      <?= campo('email', t('Email'), 'email', $v['email'], 'required maxlength="190" autocomplete="email"') ?>
      <?= campo('nick', t('Nome pubblico (si vede nelle recensioni)'), 'text', $v['nick'], 'required minlength="3" maxlength="20" autocomplete="nickname"') ?>
      <?= campo('password', t('Password (almeno 10 caratteri)'), 'password', '', 'required minlength="10" autocomplete="new-password"') ?>
      <?= campo('password2', t('Ripeti la password'), 'password', '', 'required minlength="10" autocomplete="new-password"') ?>
      <div class="trappola" aria-hidden="true"><label><?= e(t('Non compilare')) ?><input type="text" name="sito_web" tabindex="-1" autocomplete="off"></label></div>
      <label class="crocetta"><input type="checkbox" name="privacy" value="1" required> <?= e(t('Ho letto e accetto l\'informativa sulla privacy')) ?>: <a href="privacy.php" target="_blank"><?= e(t('informativa sulla privacy')) ?></a>.</label>
      <label class="crocetta"><input type="checkbox" name="eta" value="1" required> <?= e(t('Ho almeno 14 anni, oppure un genitore o tutore è d\'accordo che io mi registri.')) ?></label>
      <button class="bottone"><?= e(t('Crea l\'account')) ?></button>
    </form>
    <p class="meta"><?= e(t('Non mandiamo newsletter né pubblicità. La tua email non è mai pubblica. Hai già un account?')) ?> <a href="account.php?a=accedi"><?= e(t('Accedi')) ?></a>.</p>
    <?php
    piede_account();
}

// ---------- conferma email (il link apre una pagina con un pulsante: i programmi antispam che aprono i link non consumano il token) ----------
if ($a === 'verifica' || $a === 'email') {
    $tipo = $a === 'verifica' ? 'verifica' : 'email';
    $raw = (string)($_REQUEST['t'] ?? '');
    $tk = leggi_token($raw, $tipo);
    if ($post && $tk) {
        $st = db()->prepare('SELECT * FROM lettori WHERE id = ?'); $st->execute([$tk['lettore_id']]);
        $lt = $st->fetch();
        if ($lt && $tipo === 'verifica') {
            if ((int)$lt['stato'] === 0) db()->prepare('UPDATE lettori SET stato = 1, verificato = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $lt['id']]);
            brucia_token($tk);
            flash(t('Email confermata! Ora puoi accedere.'));
            vai('account.php?a=accedi');
        } elseif ($lt) {
            $nuova = email_pulita($tk['dato']);
            $altro = lettore_per_email($nuova);
            brucia_token($tk);
            if ($altro && (int)$altro['id'] !== (int)$lt['id']) { flash(t('Questo indirizzo è già usato da un altro account.'), 'errore'); }
            else {
                $vecchia = $lt['email'];
                db()->prepare('UPDATE lettori SET email = ? WHERE id = ?')->execute([$nuova, $lt['id']]);
                invia_email($vecchia, t('Email dell\'account cambiata') . ' · ' . impost('nome_sito'), tf("L'indirizzo email del tuo account è stato cambiato in %s. Se non sei stato tu, scrivi subito a %s.", $nuova, impost('titolare_email')));
                flash(t('Il nuovo indirizzo email è confermato.'));
            }
            vai($l ? 'account.php' : 'account.php?a=accedi');
        }
    }
    testata_account($tipo === 'verifica' ? t('Conferma la tua email') : t('Conferma il nuovo indirizzo'));
    if (!$tk) echo '<p class="errore">' . e(t('Questo link non è valido o è scaduto.')) . '</p><p><a href="account.php?a=' . ($tipo === 'verifica' ? 'accedi' : 'profilo') . '">' . e(t('Vai avanti')) . '</a> ' . e(t('e, se serve, chiedi un nuovo link.')) . '</p>';
    else echo '<form method="post">' . csrf_campo() . '<input type="hidden" name="t" value="' . e($raw) . '"><p>' . e(t('Premi il pulsante per confermare.')) . '</p><button class="bottone">' . e(t('Conferma')) . '</button></form>';
    piede_account();
}

// ---------- accesso ----------
if ($a === 'accedi') {
    if ($l) vai('account.php');
    $errori = []; $nonVerificato = ''; $ritorno = ritorno_sicuro((string)($_REQUEST['ritorno'] ?? ''));
    if ($post && ($_POST['azione'] ?? '') === 'reinvia') {
        $em = email_pulita($_POST['email'] ?? '');
        if (limite_ok('reinvia:' . ip_client(), 5, 3600) && limite_ok('reinvia-m:' . $em, 3, 3600) && ($lt = lettore_per_email($em)) && (int)$lt['stato'] === 0) {
            $t = crea_token((int)$lt['id'], 'verifica', ORE_VERIFICA * 60);
            invia_email($lt['email'], t('Conferma la tua email') . ' · ' . impost('nome_sito'), tf("Ciao %s,\nper attivare il tuo account conferma l'indirizzo email.", $lt['nick']), url_sito('account.php?a=verifica&t=' . $t), t('Conferma la mia email'));
        }
        flash(t('Se l\'indirizzo è registrato e ancora da confermare, ti abbiamo mandato una nuova email.'));
        vai('account.php?a=accedi');
    } elseif ($post) {
        $id = trim((string)($_POST['id'] ?? '')); $pw = (string)($_POST['password'] ?? '');
        $kid = 'login:' . strtolower($id);
        if (!limite_ok('login-ip:' . ip_client(), 20, 900) || !limite_ok($kid, 6, 900)) $errori[] = t('Troppi tentativi. Riprova tra 15 minuti.');
        else {
            if (strpos($id, '@') !== false) $lt = lettore_per_email($id);
            else { $st = db()->prepare('SELECT * FROM lettori WHERE LOWER(nick) = ?'); $st->execute([strtolower($id)]); $lt = $st->fetch() ?: null; }
            usleep(300000);
            $ok = password_verify($pw, $lt['password'] ?? '$2y$10$abcdefghijklmnopqrstuuF3o4Hn8cYb6S1pWm2xqX7uQe5q9rG3a');
            if (!$lt || !$ok) $errori[] = t('Email, nome o password non corretti.');
            elseif ((int)$lt['stato'] === 0) { $errori[] = t('Devi prima confermare l\'indirizzo email: controlla la posta.'); $nonVerificato = $lt['email']; }
            elseif ((int)$lt['stato'] === 2) $errori[] = t('Questo account è sospeso. Scrivi a ') . (impost('titolare_email') ?: t('chi gestisce il sito')) . '.';
            else { azzera_limite($kid); accedi_lettore($lt, !empty($_POST['ricorda'])); vai($ritorno); }
        }
    }
    testata_account('Accedi');
    mostra_errori($errori);
    if ($nonVerificato !== '') echo '<form method="post">' . csrf_campo() . '<input type="hidden" name="azione" value="reinvia"><input type="hidden" name="email" value="' . e($nonVerificato) . '"><button class="bottone secondario">' . e(t('Rimanda l\'email di conferma')) . '</button></form>';
    ?>
    <form method="post" class="form-pub">
      <?= csrf_campo() ?><input type="hidden" name="ritorno" value="<?= e($ritorno) ?>">
      <?= campo('id', t('Email o nome pubblico'), 'text', '', 'required autocomplete="username"') ?>
      <?= campo('password', t('Password'), 'password', '', 'required autocomplete="current-password"') ?>
      <label class="crocetta"><input type="checkbox" name="ricorda" value="1"> <?= e(tf('Resta collegato su questo dispositivo (%d giorni)', GIORNI_RICORDAMI)) ?></label>
      <button class="bottone"><?= e(t('Accedi')) ?></button>
    </form>
    <p class="meta"><a href="account.php?a=dimenticata"><?= e(t('Password dimenticata?')) ?></a><?php if (registrazione_aperta()): ?> · <a href="account.php?a=registrati"><?= e(t('Crea un account')) ?></a><?php endif; ?></p>
    <?php
    piede_account();
}

if ($a === 'esci') {
    if ($post) { esci_lettore(); flash(t('Sei uscito.')); }
    vai('index.php');
}

// ---------- password dimenticata e nuova password ----------
if ($a === 'dimenticata') {
    $inviata = false;
    if ($post) {
        $em = email_pulita($_POST['email'] ?? '');
        if (filter_var($em, FILTER_VALIDATE_EMAIL) && limite_ok('reset:' . ip_client(), 6, 3600) && limite_ok('reset-m:' . $em, 3, 3600) && ($lt = lettore_per_email($em))) {
            if ((int)$lt['stato'] === 1) {
                $t = crea_token((int)$lt['id'], 'reset', 60);
                invia_email($lt['email'], t('Reimposta la password') . ' · ' . impost('nome_sito'), tf("Ciao %s,\nabbiamo ricevuto la richiesta di scegliere una nuova password. Il link vale un'ora. Se non l'hai chiesto tu, ignora questo messaggio: la tua password non cambia.", $lt['nick']), url_sito('account.php?a=reimposta&t=' . $t), t('Scegli una nuova password'));
            } elseif ((int)$lt['stato'] === 0) {
                $t = crea_token((int)$lt['id'], 'verifica', ORE_VERIFICA * 60);
                invia_email($lt['email'], t('Conferma la tua email') . ' · ' . impost('nome_sito'), tf("Ciao %s,\nprima di tutto conferma l'indirizzo email.", $lt['nick']), url_sito('account.php?a=verifica&t=' . $t), t('Conferma la mia email'));
            }
        }
        $inviata = true;
    }
    testata_account('Password dimenticata');
    if ($inviata) echo '<p class="ok-pub">' . e(t('Se l\'indirizzo è registrato, ti abbiamo scritto un link per scegliere una nuova password. Il link vale un\'ora.')) . '</p>';
    else echo '<form method="post" class="form-pub">' . csrf_campo() . campo('email', t('La tua email'), 'email', '', 'required autocomplete="email"') . '<button class="bottone">' . e(t('Mandami il link')) . '</button></form>';
    piede_account();
}
if ($a === 'reimposta') {
    $raw = (string)($_REQUEST['t'] ?? ''); $tk = leggi_token($raw, 'reset'); $errori = [];
    if ($post && $tk) {
        $st = db()->prepare('SELECT * FROM lettori WHERE id = ?'); $st->execute([$tk['lettore_id']]); $lt = $st->fetch();
        $pw = (string)($_POST['password'] ?? '');
        if (!$lt) $errori[] = t('Account non trovato.');
        elseif ($m = password_errore($pw, $lt['email'], $lt['nick'])) $errori[] = $m;
        elseif ($pw !== (string)($_POST['password2'] ?? '')) $errori[] = t('Le due password non coincidono.');
        else {
            db()->prepare('UPDATE lettori SET password = ?, stato = CASE WHEN stato = 0 THEN 1 ELSE stato END, verificato = CASE WHEN verificato = \'\' THEN ? ELSE verificato END WHERE id = ?')
                ->execute([password_hash($pw, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), $lt['id']]);
            brucia_token($tk); dimentica_dispositivi((int)$lt['id']);
            invia_email($lt['email'], t('La tua password è cambiata') . ' · ' . impost('nome_sito'), t('La password del tuo account è stata cambiata. Se non sei stato tu, scrivi subito a ') . (impost('titolare_email') ?: t('chi gestisce il sito')) . '.');
            flash(t('Password cambiata. Ora puoi accedere.'));
            vai('account.php?a=accedi');
        }
    }
    testata_account('Nuova password');
    if (!$tk) echo '<p class="errore">' . e(t('Questo link non è valido o è scaduto.')) . '</p><p><a href="account.php?a=dimenticata">' . e(t('Chiedine uno nuovo')) . '</a>.</p>';
    else {
        mostra_errori($errori);
        echo '<form method="post" class="form-pub">' . csrf_campo() . '<input type="hidden" name="t" value="' . e($raw) . '">'
           . campo('password', t('Nuova password (almeno 10 caratteri)'), 'password', '', 'required minlength="10" autocomplete="new-password"')
           . campo('password2', t('Ripeti la password'), 'password', '', 'required minlength="10" autocomplete="new-password"') . '<button class="bottone">' . e(t('Salva la password')) . '</button></form>';
    }
    piede_account();
}

// ---------- da qui serve essere collegati ----------
if (!$l) { vai('account.php?a=accedi&ritorno=' . urlencode(ritorno_sicuro(($_POST['ritorno'] ?? $_GET['ritorno'] ?? 'account.php')))); }

// l'informativa è cambiata: va riaccettata
if (privacy_da_riaccettare($l) && $a !== 'esporta' && $a !== 'elimina') {
    if ($post && !empty($_POST['privacy'])) {
        db()->prepare('UPDATE lettori SET privacy_versione = ?, privacy_il = ? WHERE id = ?')->execute([privacy_versione(), date('Y-m-d H:i:s'), $l['id']]);
        registra_consenso((int)$l['id'], 'privacy');
        flash(t('Grazie, informativa accettata.'));
        vai('account.php');
    }
    testata_account('Informativa aggiornata');
    echo '<p>' . e(t('L\'informativa sulla privacy è cambiata. Per continuare a usare l\'account devi leggerla e accettarla.')) . '</p><form method="post">' . csrf_campo()
       . '<label class="crocetta"><input type="checkbox" name="privacy" value="1" required> ' . e(t('Ho letto e accetto la nuova informativa')) . ': <a href="privacy.php" target="_blank">' . e(t('nuova informativa')) . '</a>.</label><button class="bottone">' . e(t('Continua')) . '</button></form>'
       . '<p class="meta">' . e(t('Se non vuoi accettare, puoi')) . ' <a href="#cancella">' . e(t('cancellare l\'account')) . '</a> ' . e(t('dal tuo profilo senza accettare.')) . '</p>';
    piede_account();
}

$lid = (int)$l['id'];
$libro_di = function (string $slug) {
    $st = db()->prepare('SELECT * FROM libri l WHERE slug = ? AND ' . sql_visibile('l')); $st->execute([$slug]); return $st->fetch() ?: null;
};

// ---------- liste (preferiti, da leggere) ----------
if ($a === 'lista' && $post) {
    $lb = $libro_di((string)($_POST['libro'] ?? ''));
    $lista = (string)($_POST['lista'] ?? '');
    if ($lb && in_array($lista, LISTE_VALIDE, true)) {
        $ora = commuta_lista($lid, (int)$lb['id'], $lista);
        flash(($lista === 'preferito' ? ($ora ? t('Aggiunto ai preferiti.') : t('Tolto dai preferiti.')) : ($ora ? t('Aggiunto a «da leggere».') : t('Tolto da «da leggere».'))));
    }
    vai($lb ? 'libro.php?s=' . urlencode($lb['slug']) : 'account.php');
}

// ---------- recensioni ----------
if ($a === 'recensione' && $post) {
    $lb = $libro_di((string)($_POST['libro'] ?? ''));
    if (!$lb) vai('index.php');
    $torna = 'libro.php?s=' . urlencode($lb['slug']) . '#recensioni';
    if (($_POST['azione'] ?? '') === 'elimina') {
        db()->prepare('DELETE FROM recensioni WHERE lettore_id = ? AND libro_id = ?')->execute([$lid, $lb['id']]);
        flash(t('Recensione cancellata.')); vai($torna);
    }
    $voto = (int)($_POST['voto'] ?? 0); $titolo = trim((string)($_POST['titolo'] ?? '')); $testo = trim((string)($_POST['testo'] ?? ''));
    if ($voto < 1 || $voto > 5) flash(t('Scegli un voto da 1 a 5 stelle.'), 'errore');
    elseif (mb_strlen($titolo) > 80 || mb_strlen($testo) > 2000) flash(t('Titolo o testo troppo lunghi (80 e 2000 caratteri).'), 'errore');
    elseif (!limite_ok('recensione:' . $lid, 10, 3600)) flash(t('Hai modificato troppe volte: riprova più tardi.'), 'errore');
    else {
        salva_recensione($lid, (int)$lb['id'], $voto, $titolo, $testo);
        flash(impost('recensioni_moderate') === '0' ? t('Recensione pubblicata. Grazie!') : t('Grazie! La recensione sarà visibile dopo la verifica.'));
    }
    vai($torna);
}

// ---------- dati personali ----------
if ($a === 'esporta' && $post) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="i-miei-dati.json"');
    echo json_encode(esporta_lettore($lid), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($a === 'profilo' && $post) {
    $az = (string)($_POST['azione'] ?? '');
    $pwOk = fn() => limite_ok('pwcheck:' . $lid, 8, 900) && password_verify((string)($_POST['password_attuale'] ?? ''), $l['password']);
    if ($az === 'nick') {
        $n = trim((string)($_POST['nick'] ?? ''));
        if ($m = nick_errore($n)) flash($m, 'errore');
        elseif (nick_usato($n, $lid)) flash(t('Questo nome pubblico è già usato.'), 'errore');
        else { db()->prepare('UPDATE lettori SET nick = ? WHERE id = ?')->execute([$n, $lid]); flash(t('Nome pubblico cambiato.')); }
    } elseif ($az === 'email') {
        $nuova = email_pulita($_POST['email'] ?? '');
        if (!filter_var($nuova, FILTER_VALIDATE_EMAIL) || strlen($nuova) > 190) flash(t('Scrivi un indirizzo email valido.'), 'errore');
        elseif (!$pwOk()) flash(t('La password attuale non è corretta.'), 'errore');
        elseif ($nuova === $l['email']) flash(t('È già il tuo indirizzo.'), 'errore');
        elseif (!limite_ok('cambiomail:' . $lid, 3, 3600)) flash(t('Troppi tentativi, riprova più tardi.'), 'errore');
        else {
            if (!lettore_per_email($nuova)) {
                $t = crea_token($lid, 'email', ORE_VERIFICA * 60, $nuova);
                invia_email($nuova, t('Conferma il nuovo indirizzo') . ' · ' . impost('nome_sito'), tf("Ciao %s,\nper usare questo indirizzo con il tuo account confermalo con il pulsante qui sotto.", $l['nick']), url_sito('account.php?a=email&t=' . $t), t('Conferma il nuovo indirizzo'));
            }
            flash(t('Se l\'indirizzo è libero, ti abbiamo scritto un link per confermarlo. Finché non lo confermi resta valido quello vecchio.'));
        }
    } elseif ($az === 'password') {
        $nuova = (string)($_POST['password'] ?? '');
        if (!$pwOk()) flash(t('La password attuale non è corretta.'), 'errore');
        elseif ($m = password_errore($nuova, $l['email'], $l['nick'])) flash($m, 'errore');
        elseif ($nuova !== (string)($_POST['password2'] ?? '')) flash(t('Le due password non coincidono.'), 'errore');
        else {
            db()->prepare('UPDATE lettori SET password = ? WHERE id = ?')->execute([password_hash($nuova, PASSWORD_DEFAULT), $lid]);
            dimentica_dispositivi($lid);
            invia_email($l['email'], t('La tua password è cambiata') . ' · ' . impost('nome_sito'), t('La password del tuo account è stata cambiata. Se non sei stato tu, scrivi subito a ') . (impost('titolare_email') ?: t('chi gestisce il sito')) . '.');
            flash(t('Password cambiata.'));
        }
    }
    vai('account.php?a=profilo#dati');
}
if ($a === 'elimina' && $post) {
    if (empty($_POST['conferma'])) flash(t('Spunta la casella per confermare.'), 'errore');
    elseif (!limite_ok('elimina:' . $lid, 5, 900) || !password_verify((string)($_POST['password_attuale'] ?? ''), $l['password'])) flash(t('La password non è corretta.'), 'errore');
    else {
        $mail = $l['email']; $nick = $l['nick'];
        cancella_lettore($lid);
        esci_lettore();
        invia_email($mail, t('Account cancellato') . ' · ' . impost('nome_sito'), tf("Ciao %s,\nil tuo account e tutti i dati collegati sono stati cancellati, come hai chiesto. Grazie per aver letto con noi.", $nick));
        flash(t('Il tuo account e tutti i tuoi dati sono stati cancellati.'));
        vai('index.php');
    }
    vai('account.php?a=profilo#cancella');
}

// ---------- profilo: la mia libreria ----------
$join = ' JOIN libri l ON l.id = x.libro_id LEFT JOIN collane c ON c.id = l.collana_id';
$st = db()->prepare("SELECT l.*, c.nome AS nome_collana, c.nome_ru AS nome_collana_ru, x.pagina AS p_pagina, x.totale AS p_totale FROM progressi x$join WHERE x.lettore_id = ? AND " . sql_visibile('l') . ' ORDER BY x.aggiornato DESC');
$st->execute([$lid]); $continua = array_map(fn($x) => loc($x, CAMPI_LIBRO), $st->fetchAll());
$preferiti = []; $dafare = [];
$st = db()->prepare("SELECT l.*, c.nome AS nome_collana, c.nome_ru AS nome_collana_ru, x.lista FROM liste x$join WHERE x.lettore_id = ? AND " . sql_visibile('l') . ' ORDER BY x.id DESC');
$st->execute([$lid]);
foreach ($st->fetchAll() as $r) { if ($r['lista'] === 'preferito') $preferiti[] = $r; else $dafare[] = $r; }
$st = db()->prepare('SELECT l.slug, l.titolo, l.titolo_ru, x.pagina FROM segnalibri x JOIN libri l ON l.id = x.libro_id WHERE x.lettore_id = ? AND ' . sql_visibile('l') . ' ORDER BY l.titolo, x.pagina');
$st->execute([$lid]); $segnalibri = array_map(fn($x) => loc($x, ['titolo']), $st->fetchAll());
$st = db()->prepare('SELECT x.*, l.titolo AS libro_titolo, l.titolo_ru AS libro_titolo_ru, l.slug AS libro_slug FROM recensioni x JOIN libri l ON l.id = x.libro_id WHERE x.lettore_id = ? ORDER BY x.aggiornato DESC');
$st->execute([$lid]); $mie = $st->fetchAll();

testata_account('La mia libreria');
?>
<p class="meta"><?= e(t('Ciao')) ?> <b><?= e($l['nick']) ?></b> · <?= e($l['email']) ?></p>
<?php if ($continua): ?>
<h2 class="sezione"><?= e(t('Continua a leggere')) ?></h2>
<ul class="elenco-account">
<?php foreach ($continua as $r): $perc = $r['p_totale'] > 0 ? min(100, (int)round($r['p_pagina'] * 100 / $r['p_totale'])) : 0; ?>
  <li><a href="leggi.php?s=<?= e($r['slug']) ?>&amp;pagina=<?= (int)$r['p_pagina'] ?>"><b><?= e($r['titolo']) ?></b></a> — <?= e(t('pagina')) ?> <?= (int)$r['p_pagina'] ?><?= $r['p_totale'] ? ' ' . e(t('di')) . ' ' . (int)$r['p_totale'] : '' ?> <span class="barra-perc"><i style="width:<?= $perc ?>%"></i></span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<h2 class="sezione">❤ <?= e(t('Preferiti')) ?></h2>
<?php if (!$preferiti): ?><p class="meta"><?= e(t('Ancora nessun preferito: apri un libro e premi «Aggiungi ai preferiti».')) ?></p><?php endif; ?>
<div class="griglia"><?php foreach ($preferiti as $r) scheda_libro($r); ?></div>
<h2 class="sezione">🔖 <?= e(t('Da leggere')) ?></h2>
<?php if (!$dafare): ?><p class="meta"><?= e(t('La lista è vuota: aggiungi i libri che vuoi leggere dopo.')) ?></p><?php endif; ?>
<div class="griglia"><?php foreach ($dafare as $r) scheda_libro($r); ?></div>
<?php if ($segnalibri): ?>
<h2 class="sezione"><?= e(t('Segnalibri')) ?></h2>
<ul class="elenco-account"><?php foreach ($segnalibri as $r): ?><li><a href="leggi.php?s=<?= e($r['slug']) ?>&amp;pagina=<?= (int)$r['pagina'] ?>"><?= e($r['titolo']) ?> · <?= e(t('pagina')) ?> <?= (int)$r['pagina'] ?></a></li><?php endforeach; ?></ul>
<?php endif; ?>
<?php if ($mie): ?>
<h2 class="sezione"><?= e(t('Le mie recensioni')) ?></h2>
<ul class="elenco-account"><?php foreach ($mie as $r): ?><li><a href="libro.php?s=<?= e($r['libro_slug']) ?>#recensioni"><?= e(campo_l($r, 'libro_titolo')) ?></a> — <span class="stelle"><?= stelle((float)$r['voto']) ?></span> <span class="meta"><?= e(t(['in attesa di verifica', 'pubblicata', 'non pubblicata'][(int)$r['stato']] ?? '')) ?></span></li><?php endforeach; ?></ul>
<?php endif; ?>

<h2 class="sezione" id="dati"><?= e(t('I miei dati')) ?></h2>
<form method="post" class="form-pub riga-form"><?= csrf_campo() ?><input type="hidden" name="azione" value="nick">
  <?= campo('nick', t('Nome pubblico'), 'text', $l['nick'], 'required minlength="3" maxlength="20"') ?><button class="bottone secondario"><?= e(t('Cambia nome')) ?></button></form>
<details><summary><?= e(t('Cambia email')) ?></summary>
<form method="post" class="form-pub"><?= csrf_campo() ?><input type="hidden" name="azione" value="email">
  <?= campo('email', t('Nuova email'), 'email', '', 'required maxlength="190"') ?><?= campo('password_attuale', t('Password attuale'), 'password', '', 'required autocomplete="current-password"') ?><button class="bottone secondario"><?= e(t('Cambia email')) ?></button></form></details>
<details><summary><?= e(t('Cambia password')) ?></summary>
<form method="post" class="form-pub"><?= csrf_campo() ?><input type="hidden" name="azione" value="password">
  <?= campo('password_attuale', t('Password attuale'), 'password', '', 'required autocomplete="current-password"') ?>
  <?= campo('password', t('Nuova password (almeno 10 caratteri)'), 'password', '', 'required minlength="10" autocomplete="new-password"') ?>
  <?= campo('password2', t('Ripeti la nuova password'), 'password', '', 'required minlength="10" autocomplete="new-password"') ?><button class="bottone secondario"><?= e(t('Cambia password')) ?></button></form></details>

<h2 class="sezione" id="privacy-dati"><?= e(t('Privacy')) ?></h2>
<p class="meta"><?= e(tf('Hai accettato l\'informativa (versione %s) il %s.', $l['privacy_versione'], formatta_data($l['privacy_il']))) ?> <a href="privacy.php"><?= e(t('Leggila')) ?></a>.</p>
<form method="post" action="account.php?a=esporta"><?= csrf_campo() ?><button class="bottone secondario">⬇ <?= e(t('Scarica una copia dei miei dati (JSON)')) ?></button></form>
<details id="cancella"><summary><?= e(t('Cancella il mio account')) ?></summary>
<form method="post" action="account.php?a=elimina" class="form-pub"><?= csrf_campo() ?>
  <p><?= e(t('Verranno cancellati subito e per sempre l\'account, le liste, i segnalibri, i progressi e le recensioni.')) ?></p>
  <?= campo('password_attuale', t('La tua password'), 'password', '', 'required autocomplete="current-password"') ?>
  <label class="crocetta"><input type="checkbox" name="conferma" value="1" required> <?= e(t('Voglio cancellare tutto.')) ?></label>
  <button class="bottone pericolo"><?= e(t('Cancella l\'account')) ?></button></form></details>
<form method="post" action="account.php?a=esci"><?= csrf_campo() ?><p><button class="bottone secondario"><?= e(t('Esci')) ?></button></p></form>
<?php piede_account();
