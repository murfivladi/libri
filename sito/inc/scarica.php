<?php
// Scarica un file da un URL http(s) in un file temporaneo, con protezione SSRF.
// Ritorna il percorso del file temporaneo, oppure lancia RuntimeException.
function scarica_url(string $url, int $max): string {
    $tmp = tempnam(sys_get_temp_dir(), 'dl');
    for ($salti = 0; $salti < 4; $salti++) {
        $p = parse_url($url);
        $schema = strtolower($p['scheme'] ?? '');
        if (!in_array($schema, ['http', 'https'], true) || empty($p['host'])) throw new RuntimeException('URL non valido: usa http o https.');
        if (isset($p['user']) || isset($p['pass'])) throw new RuntimeException('URL con credenziali non ammesso.');
        $porta = $p['port'] ?? ($schema === 'https' ? 443 : 80);
        if (!in_array($porta, [80, 443, 8080, 8443], true)) throw new RuntimeException('Porta non ammessa.');
        $ips = gethostbynamel($p['host']) ?: [];
        if (!$ips) throw new RuntimeException('Indirizzo non trovato: ' . $p['host']);
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))
                throw new RuntimeException('Indirizzo non ammesso.');
        }
        $f = fopen($tmp, 'wb');
        $troppo = false; $scritti = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => ["{$p['host']}:$porta:{$ips[0]}"], // evita cambi di DNS tra controllo e richiesta
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'VladEditore/1.0',
            CURLOPT_HEADER => false,
            CURLOPT_WRITEFUNCTION => function ($c, $dati) use ($f, $max, &$scritti, &$troppo) {
                $scritti += strlen($dati);
                if ($scritti > $max) { $troppo = true; return 0; }
                return fwrite($f, $dati);
            },
        ]);
        curl_exec($ch);
        $codice = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $loc = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $err = curl_error($ch);
        curl_close($ch); fclose($f);
        if ($troppo) { @unlink($tmp); throw new RuntimeException('File troppo grande.'); }
        if ($codice >= 300 && $codice < 400 && $loc) { $url = $loc; continue; }
        if ($codice !== 200) { @unlink($tmp); throw new RuntimeException('Download fallito' . ($err ? ": $err" : " (HTTP $codice)") . '.'); }
        return $tmp;
    }
    @unlink($tmp);
    throw new RuntimeException('Troppi reindirizzamenti.');
}
