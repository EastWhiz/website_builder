<?php
include_once 'config.php';

function turnstileIsEnabled(): bool
{
    return defined('TURNSTILE_ENABLED') && TURNSTILE_ENABLED === true;
}

function turnstileSecretKey(): string
{
    return defined('TURNSTILE_SECRET_KEY') ? trim((string) TURNSTILE_SECRET_KEY) : '';
}

function turnstileVerifySubmission(array $postData, ?string $remoteIp = null): array
{
    if (!turnstileIsEnabled()) {
        return [
            'success' => true,
            'skipped' => true,
            'message' => 'Turnstile is disabled.',
            'error_codes' => [],
        ];
    }

    $secretKey = turnstileSecretKey();
    if ($secretKey === '') {
        return [
            'success' => false,
            'skipped' => false,
            'message' => 'Turnstile secret key is missing.',
            'error_codes' => ['missing-secret'],
        ];
    }

    $token = trim((string) ($postData['cf-turnstile-response'] ?? ''));
    if ($token === '') {
        return [
            'success' => false,
            'skipped' => false,
            'message' => 'Please complete the Turnstile verification.',
            'error_codes' => ['missing-input-response'],
        ];
    }

    $payload = [
        'secret' => $secretKey,
        'response' => $token,
    ];

    if ($remoteIp) {
        $payload['remoteip'] = $remoteIp;
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        return [
            'success' => false,
            'skipped' => false,
            'message' => 'Turnstile verification request failed.',
            'error_codes' => ['request-failed'],
            'status' => $statusCode,
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'skipped' => false,
            'message' => 'Turnstile verification returned an invalid response.',
            'error_codes' => ['invalid-response'],
            'status' => $statusCode,
        ];
    }

    $success = (bool) ($decoded['success'] ?? false);

    return [
        'success' => $success,
        'skipped' => false,
        'message' => $success ? 'Turnstile verification passed.' : 'Turnstile verification failed.',
        'error_codes' => array_values((array) ($decoded['error-codes'] ?? [])),
        'status' => $statusCode,
        'raw' => $decoded,
    ];
}
