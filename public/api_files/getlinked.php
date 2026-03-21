<?php
include_once 'config.php';
include_once 'save_lead_handler.php';
include_once 'api_error_helper.php';

header('Access-Control-Allow-Origin: ' . BASE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Api-Key, X-Api-Key');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getVal($arr, $key)
{
    return isset($arr[$key]) ? $arr[$key] : '';
}

/**
 * GetLinked phone handling (GetLinkedPlatformProvider): either digits-only national + areaCode,
 * or parsed from E.164 / US national (no libphonenumber in export).
 *
 * @return array{valid:bool, phone:string, areaCode:string}
 */
function parseGetLinkedPhoneForExport(string $phone, string $areaCode, string $countryIso2): array
{
    $phone = trim($phone);
    $areaCode = trim($areaCode);

    if (preg_match('/^\d+$/', $phone) && $areaCode !== '') {
        return ['valid' => true, 'phone' => $phone, 'areaCode' => $areaCode];
    }

    $clean = preg_replace('/[^\d+]/', '', $phone);
    if ($clean === '') {
        return ['valid' => false, 'phone' => '', 'areaCode' => ''];
    }

    // E.164 → national digits + calling code as +CC (same idea as provider's getCountryCode + getNationalNumber)
    if (preg_match('/^\+([1-9]\d{1,2})(\d{4,14})$/', $clean, $m)) {
        return [
            'valid' => true,
            'phone' => $m[2],
            'areaCode' => '+' . $m[1],
        ];
    }

    $c = strtoupper(preg_replace('/[^A-Za-z]/', '', $countryIso2));
    if ($c === '' || $c === 'US') {
        if (preg_match('/^\d{10}$/', $clean)) {
            return ['valid' => true, 'phone' => $clean, 'areaCode' => '+1'];
        }
    }

    return ['valid' => false, 'phone' => '', 'areaCode' => ''];
}

/**
 * Optional per–form_type tweaks (GetLinkedPlatformProvider::applyTypeOverrides).
 */
function applyGetLinkedTypeOverrides(string $formType, array $payload, array $postData): array
{
    $type = strtolower(trim($formType));
    switch ($type) {
        case 'meeseeksmedia':
        case 'meeseeks':
        case 'koi':
        default:
            break;
    }

    return $payload;
}

/** Failure message similar to GetLinkedPlatformProvider::submit */
function buildGetLinkedApiErrorMessage(array $body): string
{
    $message = extractApiErrorMessage($body);
    if ($message !== '') {
        if (!empty($body['errors']) && is_array($body['errors'])) {
            $errorMessages = array_column($body['errors'], 'message');
            $errorMessages = array_filter($errorMessages);
            if (!empty($errorMessages)) {
                $message .= "\n" . implode("\n", $errorMessages);
            }
        }
        return $message;
    }

    if (!empty($body['message'])) {
        $m = (string) $body['message'];
        if (!empty($body['errors']) && is_array($body['errors'])) {
            $errorMessages = array_column($body['errors'], 'message');
            $errorMessages = array_filter($errorMessages);
            if (!empty($errorMessages)) {
                $m .= "\n" . implode("\n", $errorMessages);
            }
        }
        return $m;
    }

    if (!empty($body['errors']) && is_array($body['errors'])) {
        $errorMessages = array_column($body['errors'], 'message');
        $errorMessages = array_filter($errorMessages);
        if (!empty($errorMessages)) {
            return implode("\n", $errorMessages);
        }
    }

    return 'An error occurred. Please try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = $_POST;
    $getData = $_GET;
    $dynamicCid = getVal($getData, 'cid');
    $dynamicPid = getVal($getData, 'pid');
    $dynamicSO = getVal($getData, 'so');

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

    $firstname = trim(getVal($postData, 'firstname'));
    $lastname = trim(getVal($postData, 'lastname'));
    $email = trim(getVal($postData, 'email'));
    if ($firstname === '' || $lastname === '' || $email === '') {
        $msg = 'Required fields are missing. Please ensure first name, last name, and email are provided.';
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($msg));
        exit();
    }

    $countryRaw = getVal($postData, 'country');
    $areaCodePost = getVal($postData, 'area_code');
    $phoneParsed = parseGetLinkedPhoneForExport(getVal($postData, 'phone'), $areaCodePost, $countryRaw);
    if (!$phoneParsed['valid']) {
        $msg = 'It seems like you didn\'t enter a valid phone number. Please enter your phone number in order to get exclusive help from one of our specialists!';
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($msg));
        exit();
    }

    $soValue = $dynamicSO !== '' ? $dynamicSO : trim(getVal($postData, 'so'));
    $offerName = $soValue !== '' ? $soValue : 'N/A';
    $clickId = $dynamicCid !== '' ? $dynamicCid : trim(getVal($postData, 'cid'));
    $custom5 = $clickId !== '' ? $clickId : 'N/A';

    // GetLinkedPlatformProvider base payload (application/x-www-form-urlencoded)
    $data = [
        'ip' => trim(getVal($postData, 'userip')),
        'firstName' => $firstname,
        'lastName' => $lastname,
        'email' => $email,
        'password' => 'G7pXr2kQ',
        'phone' => $phoneParsed['phone'],
        'areaCode' => $phoneParsed['areaCode'],
        'offerName' => $offerName,
        'custom5' => $custom5,
    ];
    $data = applyGetLinkedTypeOverrides($formType, $data, $postData);

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
        'Api-Key: ' . $apiKey,
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $decoded = $response ? json_decode($response, true) : null;
    $responseArray = [];
    if (is_array($decoded)) {
        $responseArray = $decoded;
    } elseif (is_string($response) && trim($response) !== '') {
        // If API returns plain text / non-JSON, surface it as message.
        $responseArray = ['message' => trim($response)];
    }
    // GetLinkedPlatformProvider: HTTP 200 and details.leadRequest.ID present
    $leadSaveStatus = ($httpCode === 200
        && is_array($responseArray)
        && isset($responseArray['details']['leadRequest']['ID'])) ? 'success' : 'failure';
    saveLead($postData, $getData, $responseArray, $slug, $leadSaveStatus, $data);

    // Configure optional broker redirect for Thank You page
    $redirectToBroker = strtolower(trim(getVal($postData, 'redirect_to_broker'))) === 'yes';
    $redirectDelay = (int) getVal($postData, 'broker_redirect_delay');
    if ($redirectDelay < 0) {
        $redirectDelay = 0;
    }
    $brokerUrl = null;
    if (is_array($responseArray)) {
        // GetLinked: redirect is typically in body.details.redirect.url,
        // but we also check the common keys and nested structures via shared helper if available.
        if (function_exists('findBrokerRedirectUrl')) {
            $brokerUrl = findBrokerRedirectUrl($responseArray);
        } else {
            if (isset($responseArray['details']['redirect']['url']) &&
                filter_var($responseArray['details']['redirect']['url'], FILTER_VALIDATE_URL)) {
                $brokerUrl = $responseArray['details']['redirect']['url'];
            } elseif (isset($responseArray['body']['details']['redirect']['url']) &&
                filter_var($responseArray['body']['details']['redirect']['url'], FILTER_VALIDATE_URL)) {
                $brokerUrl = $responseArray['body']['details']['redirect']['url'];
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
        $apiErrorMessage = $curlError !== '' ? $curlError : buildGetLinkedApiErrorMessage($responseArray);
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($apiErrorMessage));
        exit();
    }
    header('Location: ' . BASE_URL . '/api_files/thank_you.php?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO));
    exit();
}

$getData = $_GET ?? [];
header('Location: ' . BASE_URL . '?cid=' . urlencode($getData['cid'] ?? '') . '&pid=' . urlencode($getData['pid'] ?? '') . '&so=' . urlencode($getData['so'] ?? '') . '&api_error=' . urlencode('Method not allowed'));
exit();
