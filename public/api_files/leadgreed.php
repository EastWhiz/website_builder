<?php
include_once 'config.php';
include_once 'save_lead_handler.php';
include_once 'api_error_helper.php';

header('Access-Control-Allow-Origin: ' . BASE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-api-key');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getVal($arr, $key)
{
    return isset($arr[$key]) ? $arr[$key] : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = $_POST;
    $getData = $_GET;
    $dynamicCid = getVal($getData, 'cid') ?? '';
    $dynamicPid = getVal($getData, 'pid') ?? '';
    $dynamicSO = getVal($getData, 'so') ?? '';

    $formType = trim(getVal($postData, 'form_type'));
    $saveLeadSlug = trim(getVal($postData, 'save_lead_slug'));
    $slug = $saveLeadSlug !== '' ? $saveLeadSlug : $formType;
    if ($slug === '') {
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('form_type or save_lead_slug is required'));
        exit();
    }

    $endpoint = "";
    $apiKey = "";
    if (empty($endpoint)) {
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('API endpoint not configured'));
        exit();
    }

    // LeadGreed-style APIs (Electra/lcaapi.net, RiceLeads/ridapi.net, etc.) expect first_name and last_name
    $data = [
        'first_name' => getVal($postData, 'firstname'),
        'last_name' => getVal($postData, 'lastname'),
        'email' => getVal($postData, 'email'),
        'phone' => getVal($postData, 'phone'),
        'userip' => getVal($postData, 'userip'),
        'cid' => $dynamicCid,
        'pid' => $dynamicPid,
        'so' => $dynamicSO,
    ];

    $isSelfHosted = (isset($postData['is_self_hosted']) && $postData['is_self_hosted'] == "true");
    if ($isSelfHosted) {
        $responseArray = ['status' => true, 'message' => 'Lead processed successfully (self-hosted)', 'is_self_hosted' => true];
        saveLead($postData, $getData, $responseArray, $slug, 'success', $data);
        header('Location: ' . BASE_URL . '/api_files/thank_you.php?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO));
        exit();
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . $apiKey,
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // DEBUG: store full raw API response for troubleshooting broker redirects.
    // WARNING: This may contain sensitive data; remove after debugging.
    $debugPath = __DIR__ . '/api-response.txt';
    $debugResponse = ($response === false || $response === null) ? '' : (string) $response;
    $debugPayload =
        "=== " . date('c') . " ===\n" .
        "httpCode=" . (string) $httpCode . "\n" .
        "curlError=" . ((string) $curlError) . "\n" .
        "response:\n" . $debugResponse . "\n\n";
    @file_put_contents($debugPath, $debugPayload, FILE_APPEND);

    $decoded = $response ? json_decode($response, true) : null;
    $responseArray = [];
    if (is_array($decoded)) {
        $responseArray = $decoded;
    } elseif (is_string($response) && trim($response) !== '') {
        // If API returns plain text / non-JSON, surface it as message.
        $responseArray = ['message' => trim($response)];
    }
    $leadSaveStatus = ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'failure';
    saveLead($postData, $getData, $responseArray, $slug, $leadSaveStatus, $data);

    // Configure optional broker redirect for Thank You page
    $redirectToBroker = strtolower(trim(getVal($postData, 'redirect_to_broker'))) === 'yes';
    $redirectDelay = (int) getVal($postData, 'broker_redirect_delay');
    if ($redirectDelay < 0) {
        $redirectDelay = 0;
    }
    $brokerUrl = null;
    if (is_array($responseArray)) {
        // LeadGreed: redirect is typically in body.extras.redirect.url,
        // but we also check the common keys and nested structures via shared helper if available.
        if (function_exists('findBrokerRedirectUrl')) {
            $brokerUrl = findBrokerRedirectUrl($responseArray);
        } else {
            if (isset($responseArray['body']['extras']['redirect']['url']) &&
                filter_var($responseArray['body']['extras']['redirect']['url'], FILTER_VALIDATE_URL)) {
                $brokerUrl = $responseArray['body']['extras']['redirect']['url'];
            } else {
                foreach (['broker_url', 'brokerUrl', 'redirect_url', 'redirectUrl', 'url'] as $key) {
                    if (!empty($responseArray[$key]) && filter_var($responseArray[$key], FILTER_VALIDATE_URL)) {
                        $brokerUrl = $responseArray[$key];
                        break;
                    }
                }
            }
        }
    }
    if (!isset($_SESSION)) {
        session_start();
    }
    if ($redirectToBroker && $brokerUrl && filter_var($brokerUrl, FILTER_VALIDATE_URL)) {
        $_SESSION['broker_redirect'] = [
            'enabled' => true,
            'delay' => $redirectDelay,
            'url' => $brokerUrl,
        ];
    } else {
        unset($_SESSION['broker_redirect']);
    }

    if ($curlError || $leadSaveStatus === 'failure') {
        $apiErrorMessage = extractApiErrorMessage($responseArray);
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($curlError ?: ($apiErrorMessage ?: 'API error')));
        exit();
    }
    header('Location: ' . BASE_URL . '/api_files/thank_you.php?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO));
    exit();
}

$getData = $_GET ?? [];
header('Location: ' . BASE_URL . '?cid=' . urlencode($getData['cid'] ?? '') . '&pid=' . urlencode($getData['pid'] ?? '') . '&so=' . urlencode($getData['so'] ?? '') . '&api_error=' . urlencode('Method not allowed'));
exit();
