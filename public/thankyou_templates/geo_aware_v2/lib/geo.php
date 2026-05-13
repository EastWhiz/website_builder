<?php
// Geo helpers for thankyou.php. All times are call-center local time (Asia/Nicosia, Cyprus).
// Asia/Nicosia is GMT+3 in summer (EEST) and GMT+2 in winter (EET); the cutoff follows
// office wall-clock, not a fixed UTC offset.
// Functions are prefixed funnel_ to avoid colliding with third-party globals.

function funnel_get_client_ip(array $server): ?string {
    $cf = $server['HTTP_CF_CONNECTING_IP'] ?? '';
    if (is_string($cf) && $cf !== '') {
        return $cf;
    }
    $remote = $server['REMOTE_ADDR'] ?? '';
    if (is_string($remote) && $remote !== '') {
        return $remote;
    }
    return null;
}

function funnel_parse_ip2c_response(string $body): ?string {
    $body = trim($body);
    if ($body === '') {
        return null;
    }
    $parts = explode(';', $body);
    if (count($parts) < 2) {
        return null;
    }
    if ($parts[0] !== '1') {
        return null;
    }
    $iso2 = $parts[1];
    if ($iso2 === '' || strlen($iso2) !== 2) {
        return null;
    }
    return strtoupper($iso2);
}

function funnel_get_geo_config(?string $country, array $configs): array {
    if ($country !== null && isset($configs[$country])) {
        return $configs[$country];
    }
    return $configs['DEFAULT'];
}

function funnel_compute_call_date(DateTimeImmutable $now, array $config): DateTimeImmutable {
    $tz = new DateTimeZone('Asia/Nicosia');
    // Normalize input to call-center local time (Cyprus).
    $now_local = $now->setTimezone($tz);
    $today = $now_local->setTime(0, 0, 0);
    $hour = (int) $now_local->format('G');
    $dow_now = (int) $now_local->format('N'); // 1=Mon ... 7=Sun

    // Pick cutoff based on the visitor's *current* day. Sunday gets its own (typically tighter) window
    // only when skip_weekends is false — otherwise Sunday visits are pushed to Monday anyway and the
    // sunday_cutoff_hour value is irrelevant.
    if (empty($config['skip_weekends']) && $dow_now === 7) {
        $cutoff = (int) $config['sunday_cutoff_hour'];
    } else {
        $cutoff = (int) $config['cutoff_hour'];
    }

    $call = $hour < $cutoff
        ? $today
        : $today->modify('+1 day');

    if (!empty($config['skip_weekends'])) {
        // 6 = Saturday, 7 = Sunday in ISO 8601 (DateTime::format('N')).
        while (in_array((int) $call->format('N'), [6, 7], true)) {
            $call = $call->modify('+1 day');
        }
    }

    return $call;
}

function funnel_compute_label(DateTimeImmutable $today, DateTimeImmutable $call_date): string {
    // Compare on date string to ignore time-of-day differences and any sub-second drift.
    $today_str = $today->format('Y-m-d');
    $call_str = $call_date->format('Y-m-d');

    if ($call_str === $today_str) {
        return 'Today';
    }
    $tomorrow_str = $today->modify('+1 day')->format('Y-m-d');
    if ($call_str === $tomorrow_str) {
        return 'Tomorrow';
    }
    return $call_date->format('l'); // Monday, Tuesday, ...
}

function funnel_compute_phrase(string $label): string {
    if ($label === 'Today' || $label === 'Tomorrow') {
        return strtolower($label);
    }
    return 'on ' . $label;
}

function funnel_curl_get(string $url, int $timeout): ?string {
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT      => 'funnel-thankyou/1.0',
    ]);
    $body = curl_exec($ch);
    $err = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($err !== 0 || $status < 200 || $status >= 300 || !is_string($body)) {
        return null;
    }
    return $body;
}

function funnel_fetch_country(?string $ip, ?callable $http = null): ?string {
    if ($ip === null || $ip === '') {
        return null;
    }
    if ($http === null) {
        $http = function (string $url): ?string {
            return funnel_curl_get($url, 2); // 2-second timeout per spec
        };
    }
    $body = $http('https://ip2c.org/' . rawurlencode($ip));
    if (!is_string($body)) {
        return null;
    }
    return funnel_parse_ip2c_response($body);
}
