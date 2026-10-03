<?php
// Controlla che ogni testo t('...') / tf('...') del sito pubblico abbia la traduzione russa in inc/ru.php.
// Uso: php sito/tests/chiavi_ru.php   (esce con errore se ne manca qualcuna)
chdir(__DIR__ . '/..');
$dizionario = require 'inc/ru.php';
$extra = ['Registrati', 'Accedi', 'Password dimenticata', 'Nuova password', 'Informativa aggiornata', 'La mia libreria', 'Conferma la tua email',
          'in attesa di verifica', 'pubblicata', 'non pubblicata'];            // titoli e valori passati senza t() diretto
$chiavi = array_fill_keys($extra, 'extra');
foreach (array_merge(glob('*.php'), glob('inc/*.php')) as $f) {
    if ($f === 'inc/ru.php' || $f === 'inc/contenuti_ru.php') continue;
    $tk = token_get_all(file_get_contents($f)); $n = count($tk);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($tk[$i]) || $tk[$i][0] !== T_STRING || !in_array($tk[$i][1], ['t', 'tf'], true)) continue;
        $j = $i + 1; while ($j < $n && is_array($tk[$j]) && $tk[$j][0] === T_WHITESPACE) $j++;
        if (($tk[$j] ?? null) !== '(') continue;
        $j++; while ($j < $n && is_array($tk[$j]) && $tk[$j][0] === T_WHITESPACE) $j++;
        if (is_array($tk[$j] ?? null) && $tk[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
            $s = $tk[$j][1]; $in = substr($s, 1, -1);
            $chiavi[$s[0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], $in) : stripcslashes($in)] = $f;
        }
    }
}
$mancano = 0;
foreach ($chiavi as $k => $f) if (!isset($dizionario[$k]) || trim($dizionario[$k]) === '') { echo "MANCA la traduzione russa ($f): " . str_replace("\n", '\n', $k) . "\n"; $mancano++; }
foreach ($dizionario as $k => $v) {                                              // i segnaposto devono coincidere
    preg_match_all('/%[sd]/', $k, $a); preg_match_all('/%[sd]/', $v, $b);
    if ($a[0] !== $b[0]) { echo "SEGNAPOSTO diversi: " . str_replace("\n", '\n', $k) . "\n"; $mancano++; }
}
echo 'traduzioni russe: ' . count($chiavi) . ' testi controllati, ' . ($mancano ? "$mancano problemi" : 'tutti presenti') . "\n";
exit($mancano ? 1 : 0);
