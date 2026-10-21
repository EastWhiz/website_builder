<?php

/**
 * Server-side lead input validation for exported landing pages.
 *
 * Twin of public/export_assets/lead-validation.js: the same rules, checked against the shared vectors in
 * tests/fixtures/lead-validation-vectors.json. Standalone on purpose (no Laravel, no composer): this file is
 * copied into every export. Works without the PHP intl extension (Normalizer is optional).
 *
 * Data files (blocked domains, messages) are read from this directory when exported, or from
 * ../export_assets/ when running inside the Builder.
 */

if (!function_exists('lv_data')) {

    function lv_read_json(string $name): array
    {
        $candidates = [
            __DIR__ . '/' . str_replace('-', '_', $name),
            __DIR__ . '/../export_assets/' . $name,
        ];
        foreach ($candidates as $file) {
            if (is_file($file)) {
                $decoded = json_decode((string) file_get_contents($file), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }

    function lv_data(): array
    {
        static $data = null;
        if ($data === null) {
            $data = lv_read_json('lead-validation-data.json') + [
                'reservedDomains' => ['example.com', 'example.org', 'example.net', 'test.com', 'localhost'],
                'reservedSuffixes' => ['.test', '.invalid', '.localhost', '.example', '.local'],
                'disposableDomains' => [],
                'hardTldTypos' => ['con', 'coom', 'comm', 'cmo', 'ocm', 'vom', 'xom', 'cim', 'cpm', 'clm', 'cok', 'comn'],
                'tldTypoSuggestion' => 'com',
                'typoDomains' => [],
                'commonDomains' => [],
            ];
        }

        return $data;
    }

    function lv_messages(): array
    {
        static $messages = null;
        if ($messages === null) {
            $messages = lv_read_json('lead-validation-messages.json');
        }

        return $messages;
    }

    function lv_message(string $lang, string $key, ?string $sub = null): string
    {
        $all = lv_messages();
        $table = $all[$lang] ?? ($all['English'] ?? []);
        $text = $table[$key] ?? ($all['English'][$key] ?? $key);

        return $sub !== null ? str_replace('{s}', $sub, $text) : $text;
    }

    function lv_len(string $s): int
    {
        return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    }

    function lv_lower(string $s): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    }

    function lv_normalize_text($value): string
    {
        $s = $value === null ? '' : (string) $value;
        if (class_exists('Normalizer')) {
            $n = Normalizer::normalize($s, Normalizer::FORM_KC);
            if ($n !== false) {
                $s = $n;
            }
        } elseif (function_exists('mb_convert_kana')) {
            $s = mb_convert_kana($s, 'as', 'UTF-8'); // full-width ASCII and spaces to half-width
        }
        $s = (string) preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00AD}]/u', '', $s);
        $s = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $s);

        return trim((string) preg_replace('/^[\s\x{00A0}\x{3000}]+|[\s\x{00A0}\x{3000}]+$/u', '', $s));
    }

    function lv_osa_distance(string $a, string $b): int
    {
        $al = strlen($a);
        $bl = strlen($b);
        $d = [];
        for ($i = 0; $i <= $al; $i++) {
            $d[$i] = [$i];
        }
        for ($j = 0; $j <= $bl; $j++) {
            $d[0][$j] = $j;
        }
        for ($i = 1; $i <= $al; $i++) {
            for ($j = 1; $j <= $bl; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cost);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $d[$al][$bl];
    }

    function lv_domain_in_list(array $list, string $domain): bool
    {
        $set = array_flip(array_map('strtolower', $list));
        $parts = explode('.', $domain);
        for ($i = 0; $i < count($parts) - 1; $i++) {
            if (isset($set[implode('.', array_slice($parts, $i))])) {
                return true;
            }
        }

        return false;
    }

    function lv_suggest_domain(string $domain): ?string
    {
        $data = lv_data();
        if ($domain === '' || in_array($domain, $data['commonDomains'], true)) {
            return null;
        }
        if (isset($data['typoDomains'][$domain])) {
            return $data['typoDomains'][$domain];
        }
        if (strlen($domain) < 6) {
            return null;
        }
        foreach ($data['commonDomains'] as $common) {
            if (abs(strlen($common) - strlen($domain)) <= 1 && lv_osa_distance($domain, $common) === 1) {
                return $common;
            }
        }

        return null;
    }

    /**
     * @return array{ok:bool,value:string,code:string,suggestion?:string}
     * codes: required, space, chars, invalid, blocked, tld
     */
    function lv_validate_email($raw): array
    {
        $data = lv_data();
        $v = lv_normalize_text($raw);
        if ($v === '') {
            return ['ok' => false, 'value' => '', 'code' => 'required'];
        }
        if (preg_match('/\s/u', $v)) {
            return ['ok' => false, 'value' => $v, 'code' => 'space'];
        }
        $v = lv_lower($v);
        if (lv_len($v) > 254) {
            return ['ok' => false, 'value' => $v, 'code' => 'invalid'];
        }
        $at = explode('@', $v);
        if (count($at) !== 2) {
            return ['ok' => false, 'value' => $v, 'code' => 'invalid'];
        }
        [$local, $domain] = $at;
        if ($local === '' || $domain === '') {
            return ['ok' => false, 'value' => $v, 'code' => 'invalid'];
        }
        if (lv_len($local) > 64 || lv_len($domain) > 253) {
            return ['ok' => false, 'value' => $v, 'code' => 'invalid'];
        }
        if (!preg_match('/^[\x21-\x7E]+$/', $local . $domain)) {
            return ['ok' => false, 'value' => $v, 'code' => 'chars'];
        }
        if (!preg_match('/^[a-z0-9._%+-]+$/', $local)) {
            return ['ok' => false, 'value' => $v, 'code' => 'chars'];
        }
        if ($local[0] === '.' || substr($local, -1) === '.' || strpos($local, '..') !== false) {
            return ['ok' => false, 'value' => $v, 'code' => 'invalid'];
        }
        if (!preg_match('/^[a-z0-9.-]+$/', $domain)) {
            return ['ok' => false, 'value' => $v, 'code' => 'chars'];
        }
        $labels = explode('.', $domain);
        if (count($labels) < 2) {
            return ['ok' => false, 'value' => $v, 'code' => 'invalid'];
        }
        foreach ($labels as $label) {
            if (strlen($label) < 1 || strlen($label) > 63 || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $label)) {
                return ['ok' => false, 'value' => $v, 'code' => 'invalid'];
            }
        }
        $tld = end($labels);
        if (!preg_match('/^([a-z]{2,24}|xn--[a-z0-9-]{1,59})$/', $tld)) {
            return ['ok' => false, 'value' => $v, 'code' => 'invalid'];
        }
        if (in_array($tld, $data['hardTldTypos'], true)) {
            $fixed = array_merge(array_slice($labels, 0, -1), [$data['tldTypoSuggestion']]);

            return ['ok' => false, 'value' => $v, 'code' => 'tld', 'suggestion' => $local . '@' . implode('.', $fixed)];
        }
        if (lv_domain_in_list($data['reservedDomains'], $domain)) {
            return ['ok' => false, 'value' => $v, 'code' => 'blocked'];
        }
        foreach ($data['reservedSuffixes'] as $suffix) {
            $suffix = strtolower((string) $suffix);
            if (strlen($domain) > strlen($suffix) && substr($domain, -strlen($suffix)) === $suffix) {
                return ['ok' => false, 'value' => $v, 'code' => 'blocked'];
            }
        }
        if (lv_domain_in_list($data['disposableDomains'], $domain)) {
            return ['ok' => false, 'value' => $v, 'code' => 'blocked'];
        }
        $out = ['ok' => true, 'value' => $v, 'code' => ''];
        $suggestion = lv_suggest_domain($domain);
        if ($suggestion !== null) {
            $out['suggestion'] = $local . '@' . $suggestion;
        }

        return $out;
    }

    /** @return array{ok:bool,value:string,code:string} codes: required, invalid */
    function lv_validate_name($raw): array
    {
        $v = lv_normalize_text($raw);
        $v = trim((string) preg_replace('/\s+/u', ' ', $v));
        if ($v === '') {
            return ['ok' => false, 'value' => '', 'code' => 'required'];
        }
        $len = lv_len($v);
        if ($len < 2 || $len > 100) {
            return ['ok' => false, 'value' => $v, 'code' => 'invalid'];
        }
        if (!preg_match('/^[\p{L}\p{M}][\p{L}\p{M} .\'\x{2019}-]*$/u', $v)) {
            return ['ok' => false, 'value' => $v, 'code' => 'invalid'];
        }

        return ['ok' => true, 'value' => $v, 'code' => ''];
    }

    /**
     * Server cannot run libphonenumber here; it enforces shape + consistency. Full per-country validity is
     * enforced in the browser now and by the CRM later.
     *
     * @return array{ok:bool,code:string,value:string} codes: required, format, country, mismatch
     */
    function lv_validate_phone($phone, $country, $areaCode): array
    {
        $p = trim((string) $phone);
        if ($p === '') {
            return ['ok' => false, 'code' => 'required', 'value' => ''];
        }
        if (!preg_match('/^\+[1-9]\d{6,14}$/', $p)) {
            return ['ok' => false, 'code' => 'format', 'value' => $p];
        }
        if (!preg_match('/^[a-z]{2}$/', strtolower(trim((string) $country)))) {
            return ['ok' => false, 'code' => 'country', 'value' => $p];
        }
        $ac = trim((string) $areaCode);
        if ($ac !== '') {
            $acDigits = '+' . ltrim($ac, '+ ');
            if (!preg_match('/^\+[1-9]\d{0,3}$/', $acDigits) || strpos($p, $acDigits) !== 0) {
                return ['ok' => false, 'code' => 'mismatch', 'value' => $p];
            }
        }

        return ['ok' => true, 'code' => '', 'value' => $p];
    }

    /** Detect the form language the same way the browser does (placeholders are not posted; use hint). */
    function lv_language(array $post): string
    {
        $lang = trim((string) ($post['form_language'] ?? ''));
        $all = lv_messages();

        return isset($all[$lang]) ? $lang : 'English';
    }

    function lv_email_message_key(string $code): string
    {
        return ['required' => 'email_required', 'space' => 'email_space', 'chars' => 'email_chars', 'invalid' => 'email_invalid', 'blocked' => 'email_blocked', 'tld' => 'email_tld'][$code] ?? 'email_invalid';
    }

    function lv_phone_message_key(string $code): string
    {
        return ['required' => 'phone_required', 'country' => 'phone_country'][$code] ?? 'phone_invalid';
    }

    /**
     * Validate the posted lead fields. Returns null when valid, otherwise the first problem:
     * ['field' => 'email'|'firstname'|'lastname'|'phone', 'code' => string, 'message' => string].
     */
    function lv_validate_lead_post(array $post): ?array
    {
        $lang = lv_language($post);

        $checks = [
            ['firstname', lv_validate_name($post['firstname'] ?? ''), 'name_'],
            ['lastname', lv_validate_name($post['lastname'] ?? ''), 'name_'],
        ];
        foreach ($checks as [$field, $result, $prefix]) {
            if (!$result['ok']) {
                $key = $result['code'] === 'required' ? 'name_required' : 'name_invalid';

                return ['field' => $field, 'code' => $result['code'], 'message' => lv_message($lang, $key)];
            }
        }

        $email = lv_validate_email($post['email'] ?? '');
        if (!$email['ok']) {
            return ['field' => 'email', 'code' => $email['code'], 'message' => lv_message($lang, lv_email_message_key($email['code']))];
        }

        $phone = lv_validate_phone($post['phone'] ?? '', $post['country'] ?? '', $post['area_code'] ?? '');
        if (!$phone['ok']) {
            return ['field' => 'phone', 'code' => $phone['code'], 'message' => lv_message($lang, lv_phone_message_key($phone['code']))];
        }

        return null;
    }
}
