<?php

/**
 * Reusable function to send lead data to CRM save-lead API
 * This should be included in all API files after getting the main API response
 */

function getValInside($arr, $key)
{
    return isset($arr[$key]) ? $arr[$key] : '';
}

function truthyValue($value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function stopSpammingEnabled(array $postData): bool
{
    // Default is ON for backward compatibility and safer behavior.
    if (!array_key_exists('stop_spamming', $postData)) {
        return true;
    }

    return truthyValue($postData['stop_spamming']);
}

function resolveRequestIp(): string
{
    $requestIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (strpos((string) $requestIp, ',') !== false) {
        $requestIp = trim(explode(',', (string) $requestIp)[0]);
    }

    return trim((string) $requestIp);
}

function normalizeLeadEmail($email): string
{
    return strtolower(trim((string) $email));
}

function resolveDuplicateApiIdentifier(array $postData, string $apiName): string
{
    $instanceId = trim((string) ($postData['user_api_instance_id'] ?? ''));
    if ($instanceId !== '') {
        return 'instance:' . $instanceId;
    }

    $slug = trim((string) ($postData['save_lead_slug'] ?? ''));
    if ($slug !== '') {
        return 'slug:' . strtolower($slug);
    }

    $formType = trim((string) ($postData['form_type'] ?? ''));
    if ($formType !== '') {
        return 'form_type:' . strtolower($formType);
    }

    return 'api:' . strtolower(trim((string) $apiName));
}

function checkDuplicateLeadInCrm(array $postData, string $apiName): array
{
    $email = normalizeLeadEmail($postData['email'] ?? '');
    if ($email === '') {
        return ['checked' => false, 'is_duplicate' => false];
    }

    $organizationIdRaw = $postData['organization_id'] ?? null;
    $organizationId = null;
    if (is_numeric($organizationIdRaw) && (int) $organizationIdRaw > 0) {
        $organizationId = (int) $organizationIdRaw;
    }

    $payload = [
        'email' => $email,
        'api_identifier' => resolveDuplicateApiIdentifier($postData, $apiName),
        'api_type' => trim((string) $apiName),
        'user_api_instance_id' => trim((string) ($postData['user_api_instance_id'] ?? '')),
        'organization_id' => $organizationId,
        'web_builder_user_id' => isset($postData['web_builder_user_id']) ? ('U' . trim((string) $postData['web_builder_user_id'])) : null,
    ];

    $crmBaseUrl = 'https://crm.diy';
    $ch = curl_init(rtrim($crmBaseUrl, '/') . '/api/v1/check-duplicate-lead');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $verifySsl = false; // __CRM_VERIFY_SSL__
    if (!$verifySsl) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $responseBody = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);

    if ($curlError !== '') {
        error_log('Duplicate check API error: ' . $curlError);
        return ['checked' => false, 'is_duplicate' => false];
    }

    if ($httpCode < 200 || $httpCode >= 300 || !$responseBody) {
        return ['checked' => false, 'is_duplicate' => false];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        return ['checked' => false, 'is_duplicate' => false];
    }

    $isDuplicate = false;
    if (array_key_exists('is_duplicate', $decoded)) {
        $isDuplicate = truthyValue($decoded['is_duplicate']);
    } elseif (isset($decoded['data']) && is_array($decoded['data']) && array_key_exists('is_duplicate', $decoded['data'])) {
        $isDuplicate = truthyValue($decoded['data']['is_duplicate']);
    }

    return ['checked' => true, 'is_duplicate' => $isDuplicate];
}

function maybeBlockDuplicateLeadAndExit(array $postData, array $getData, string $apiName): void
{
    $email = normalizeLeadEmail($postData['email'] ?? '');
    if ($email === '') {
        return;
    }

    $check = checkDuplicateLeadInCrm($postData, $apiName);
    if (!$check['checked'] || !$check['is_duplicate']) {
        return;
    }

    $duplicatePayload = [
        'duplicate_check' => [
            'reason' => 'Duplicate Email',
            'matched_lead_id' => $check['matched_lead_id'] ?? null,
        ],
    ];

    saveLead(
        $postData,
        $getData,
        ['status' => false, 'message' => 'Duplicate Email'],
        $apiName,
        'failure',
        $duplicatePayload,
        [
            'failure_reason' => 'Duplicate Email',
        ]
    );

    $cid = trim((string) ($getData['cid'] ?? $postData['cid'] ?? ''));
    $pid = trim((string) ($getData['pid'] ?? $postData['pid'] ?? ''));
    $so = trim((string) ($getData['so'] ?? $postData['so'] ?? ''));

    header('Location: ' . BASE_URL . '/api_files/thank_you.php?' . http_build_query([
        'cid' => $cid,
        'pid' => $pid,
        'so' => $so,
    ]));
    exit();
}

function evaluateHoneypot(array $postData): array
{
    $honeypotFieldNames = ['ref_code', 'website', 'website_url', 'company_website'];
    foreach ($honeypotFieldNames as $fieldName) {
        if (isset($postData[$fieldName]) && trim((string) $postData[$fieldName]) !== '') {
            return ['is_fake' => true, 'reason' => 'honeypot_field_filled'];
        }
    }

    if (isset($postData['submission_duration_ms'])) {
        $durationMs = (int) $postData['submission_duration_ms'];
        if ($durationMs > 0 && $durationMs < 800) {
            return ['is_fake' => true, 'reason' => 'submitted_too_fast'];
        }
    }

    return ['is_fake' => false, 'reason' => ''];
}

function maybeBlockFakeLeadAndExit(array $postData, array $getData, string $apiName, ?array $providerPayload = null): void
{
    if (!stopSpammingEnabled($postData)) {
        return;
    }

    $honeypot = evaluateHoneypot($postData);
    if (!$honeypot['is_fake']) {
        return;
    }

    $fakePayload = is_array($providerPayload) ? $providerPayload : [];
    $fakePayload['honeypot'] = [
        'reason' => $honeypot['reason'],
    ];

    saveLead(
        $postData,
        $getData,
        ['status' => false, 'message' => 'Fake Lead', 'honeypot_reason' => $honeypot['reason']],
        $apiName,
        'failure',
        $fakePayload,
        [
            'is_fake_lead' => true,
            'failure_reason' => 'Fake Lead',
            'honeypot_reason' => $honeypot['reason'],
            'blocked_ip' => resolveRequestIp(),
        ]
    );

    $cid = trim((string) ($getData['cid'] ?? $postData['cid'] ?? ''));
    $pid = trim((string) ($getData['pid'] ?? $postData['pid'] ?? ''));
    $so = trim((string) ($getData['so'] ?? $postData['so'] ?? ''));

    header('Location: ' . BASE_URL . '/api_files/thank_you.php?' . http_build_query([
        'cid' => $cid,
        'pid' => $pid,
        'so' => $so,
    ]));
    exit();
}

function saveLead($postData, $getData, $apiResponse, $apiName, $apiResponseStatus = 'success', $data = null, array $options = [])
{

    // Get request IP fallback
    $requestIp = resolveRequestIp();

    // Extract names
    $firstName = $postData['firstname'] ?? '';
    $lastName = $postData['lastname'] ?? '';

    // Normalize IP from provider payloads: userip (trackbox), _ip (leadgreed), ip (irev/getlinked)
    $leadIp = '';
    if (is_array($data)) {
        $leadIp = $data['userip'] ?? $data['_ip'] ?? $data['ip'] ?? '';
    }
    if ($leadIp === '') {
        $leadIp = $postData['userip'] ?? '';
    }
    if ($leadIp === '') {
        $leadIp = $requestIp;
    }

    // Normalize country from provider payloads / POST
    $leadCountry = '';
    if (is_array($data)) {
        $leadCountry = $data['country'] ?? $data['country_code'] ?? '';
    }
    if ($leadCountry === '') {
        $leadCountry = $postData['country'] ?? '';
    }

    $apiPayload = is_array($data) ? $data : [];
    $apiPayload['aweber_form'] = [
        'use_aweber' => $postData['use_aweber'] ?? 'no',
        'aweber_user_api_instance_id' => $postData['aweber_user_api_instance_id'] ?? '',
        'aweber_list_ids' => $postData['aweber_list_ids'] ?? '',
    ];
    // Deterministic API instance mapping for CRM history/cap attribution.
    $apiPayload['user_api_instance_id'] = $postData['user_api_instance_id'] ?? '';

    // Prepare lead data
    $leadData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $postData['email'] ?? '',
        'contact' => $postData['phone'] ?? '',
        'api_type' => $apiName,
        'web_builder_user_id' => $postData['web_builder_user_id'] ? ("U" . $postData['web_builder_user_id']) : 'Unknown',
        'sales_page_id' => $postData['sales_page_id'] ? $postData['sales_page_id'] : 'Unknown',
        'api_payload' => $apiPayload,
        'api_response' => $apiResponse,
        'api_response_status' => $apiResponseStatus,
        'is_self_hosted' => (isset($postData['is_self_hosted']) && $postData['is_self_hosted'] == "true") ? true : false,
        'so' => getValInside($getData, 'so') ?? '',
        'cid' => getValInside($getData, 'cid') ?? '',
        'ip_address' => $leadIp,
        'country' => $leadCountry,
        'is_fake_lead' => (bool) ($options['is_fake_lead'] ?? false),
        'failure_reason' => $options['failure_reason'] ?? null,
        'honeypot_reason' => $options['honeypot_reason'] ?? null,
        'blocked_ip' => $options['blocked_ip'] ?? null,
    ];

    // Send to CRM save-lead API
    $ch = curl_init('https://crm.diy/api/v1/save-lead');

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($leadData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    // SSL verification is baked into exported zips by Builder.
    // Disabled to allow staging/self-signed certificate environments.
    $verifySsl = false; // __CRM_VERIFY_SSL__
    if (!$verifySsl) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        // Log error but don't stop the main flow
        error_log("Save Lead API Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    // Log the response for debugging (optional)
    if ($response) {
        error_log("Save Lead API Response: " . $response);
    }

    return ($httpCode >= 200 && $httpCode < 300);
}
