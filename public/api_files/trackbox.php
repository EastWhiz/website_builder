<?php
include_once 'config.php';
include_once 'save_lead_handler.php';
include_once 'api_error_helper.php'; // Extract readable error from API responses

header('Access-Control-Allow-Origin: ' . BASE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-trackbox-username, x-trackbox-password, x-api-key');
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
 * Lightweight phone validation for exported standalone PHP (no libphonenumber).
 * Mirrors TrackboxPlatformProvider intent: E164 when possible, ISO2 country for context.
 *
 * @return array{valid:bool, phone:string, country:string}
 */
function parseTrackboxPhoneForExport(string $phone, string $countryIso2): array
{
    $clean = preg_replace('/[^\d+]/', '', trim($phone));
    $country = strtoupper(preg_replace('/[^A-Za-z]/', '', $countryIso2));
    if ($country === '') {
        $country = 'US';
    }

    // Already E164 (+country code + subscriber number, ITU-style bounds)
    if ($clean !== '' && preg_match('/^\+[1-9]\d{6,14}$/', $clean)) {
        return ['valid' => true, 'phone' => $clean, 'country' => $country];
    }

    // US 10-digit national → +1… (common when intl-tel-input not used)
    if ($country === 'US' && preg_match('/^\d{10}$/', $clean)) {
        return ['valid' => true, 'phone' => '+1' . $clean, 'country' => 'US'];
    }

    return ['valid' => false, 'phone' => $clean, 'country' => $country];
}

/**
 * Optional per–form_type tweaks (TrackboxPlatformProvider::applyTypeOverrides).
 * Currently no extra keys; extend here if a Trackbox variant needs them.
 */
function applyTrackboxTypeOverrides(string $formType, array $payload, array $postData, string $dynamicCid): array
{
    $type = strtolower(trim($formType));
    switch ($type) {
        case 'magicads':
        case 'pastile':
        case 'seamediaone':
        case 'seamedia_one':
        case 'seamedia':
        case 'newmedis':
        case 'new_medis':
        default:
            break;
    }

    return $payload;
}

function findBrokerRedirectUrl(array $response)
{
    $candidates = [];

    // Common top-level keys
    foreach (['broker_url', 'brokerUrl', 'redirect_url', 'redirectUrl', 'url'] as $key) {
        if (isset($response[$key]) && is_string($response[$key])) {
            $candidates[] = $response[$key];
        }
    }

    // LeadGreed style: body.extras.redirect.url
    if (isset($response['body']['extras']['redirect']['url'])) {
        $candidates[] = $response['body']['extras']['redirect']['url'];
    }

    // Trackbox style (ELPS / Magicads / Pastile / SeaMediaOne):
    // - body.data
    // - body.addonData.data.loginURL
    // - body.addonData.data.brokerUrl
    if (isset($response['body']['data']) && is_string($response['body']['data'])) {
        $candidates[] = $response['body']['data'];
    }
    if (isset($response['body']['addonData']['data']['loginURL'])) {
        $candidates[] = $response['body']['addonData']['data']['loginURL'];
    }
    if (isset($response['body']['addonData']['data']['brokerUrl'])) {
        $candidates[] = $response['body']['addonData']['data']['brokerUrl'];
    }

    // GetLinked style: details.redirect.url (top-level or under body)
    if (isset($response['details']['redirect']['url'])) {
        $candidates[] = $response['details']['redirect']['url'];
    }
    if (isset($response['body']['details']['redirect']['url'])) {
        $candidates[] = $response['body']['details']['redirect']['url'];
    }

    // iRev style: body.auto_login_url
    if (isset($response['body']['auto_login_url'])) {
        $candidates[] = $response['body']['auto_login_url'];
    }

    // If body itself is a URL string
    if (isset($response['body']) && is_string($response['body'])) {
        $candidates[] = $response['body'];
    }

    // Validate candidates in order
    foreach ($candidates as $url) {
        if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
    }

    // Fallback: recursively scan for the first URL-looking string
    $stack = [$response];
    while (!empty($stack)) {
        $current = array_pop($stack);
        if (!is_array($current)) {
            continue;
        }
        foreach ($current as $value) {
            if (is_array($value)) {
                $stack[] = $value;
            } elseif (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                return $value;
            }
        }
    }

    return null;
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

    // Get endpoint from injected credentials (set during export)
    $endpoint = "";
    if (empty($endpoint)) {
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('API endpoint not configured'));
        exit();
    }

    // Align with TrackboxPlatformProvider: required fields + phone validation
    $firstname = trim(getVal($postData, 'firstname'));
    $lastname = trim(getVal($postData, 'lastname'));
    $email = trim(getVal($postData, 'email'));
    if ($firstname === '' || $lastname === '' || $email === '') {
        $msg = 'Required fields are missing. Please ensure first name, last name, and email are provided.';
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($msg));
        exit();
    }

    $phoneRaw = getVal($postData, 'phone');
    $countryRaw = getVal($postData, 'country');
    $phoneData = parseTrackboxPhoneForExport($phoneRaw, $countryRaw);
    if (!$phoneData['valid']) {
        $msg = 'It seems like you didn\'t enter a valid phone number. Please enter your phone number in order to get exclusive help from one of our specialists!';
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($msg));
        exit();
    }

    $clickId = $dynamicCid !== '' ? $dynamicCid : trim(getVal($postData, 'cid'));
    $mpc1 = $clickId !== '' ? $clickId : 'N/A';
    $soValue = $dynamicSO !== '' ? $dynamicSO : trim(getVal($postData, 'so'));
    $soFinal = $soValue !== '' ? $soValue : 'N/A';

    $ch = curl_init($endpoint);

    // Shared Trackbox payload (ELPS / MagicAds / NewMedis / Pastile / SeaMediaOne / etc.)
    $data = [
        'ai' => '',
        'ci' => '',
        'gi' => '',
        'userip' => trim(getVal($postData, 'userip')),
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $email,
        'password' => 'G7pXr2kQ',
        'phone' => $phoneData['phone'],
        'so' => $soFinal,
        'lg' => 'EN',
        'country' => $phoneData['country'],
        'MPC_1' => $mpc1,
    ];
    $data = applyTrackboxTypeOverrides($formType, $data, $postData, $dynamicCid);

    $username = "";
    $password = "";
    $xapikey = "";

    $isSelfHosted = (isset($postData['is_self_hosted']) && $postData['is_self_hosted'] == "true");
    if ($isSelfHosted) {
        $responseArray = ['status' => true, 'message' => 'Lead processed successfully (self-hosted)', 'is_self_hosted' => true];
        saveLead($postData, $getData, $responseArray, $slug, 'success', $data);
        header('Location: ' . BASE_URL . '/api_files/thank_you.php?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO));
        exit();
    }

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-trackbox-username: ' . $username,
        'x-trackbox-password: ' . $password,
        'x-api-key: ' . $xapikey
    ]);

    if (curl_errno($ch)) {
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode(curl_error($ch)));
        exit();
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = $response ? json_decode($response, true) : null;
    $responseArray = [];
    if (is_array($decoded)) {
        $responseArray = $decoded;
    } elseif (is_string($response) && trim($response) !== '') {
        // If API returns plain text / non-JSON, surface it as message.
        $responseArray = ['message' => trim($response)];
    }
    $leadSaveStatus = ($httpCode === 200 && isset($responseArray['status']) && $responseArray['status']) ? 'success' : 'failure';
    saveLead($postData, $getData, $responseArray, $slug, $leadSaveStatus, $data);

    // Configure optional broker redirect for Thank You page
    $redirectToBroker = strtolower(trim(getVal($postData, 'redirect_to_broker'))) === 'yes';
    $redirectDelay = (int) getVal($postData, 'broker_redirect_delay');
    if ($redirectDelay < 0) {
        $redirectDelay = 0;
    }
    $brokerUrl = null;
    if (is_array($responseArray)) {
        $brokerUrl = findBrokerRedirectUrl($responseArray);
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
        // Clear any previous redirect config
        unset($_SESSION['broker_redirect']);
    }

    if (function_exists('sendToAweber')) {
        sendToAweber($postData);
    }

    if ($httpCode !== 200 || !isset($responseArray['status']) || !$responseArray['status']) {
        $apiErrorMessage = extractApiErrorMessage($responseArray);
        $finalMessage = $apiErrorMessage ?: ($responseArray['data'] ?? 'An error occurred. Please try again.');
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($finalMessage));
        exit();
    }
    header('Location: ' . BASE_URL . '/api_files/thank_you.php?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO));
    exit();
}

$getData = $_GET ?? [];
header('Location: ' . BASE_URL . '?cid=' . urlencode($getData['cid'] ?? '') . '&pid=' . urlencode($getData['pid'] ?? '') . '&so=' . urlencode($getData['so'] ?? '') . '&api_error=' . urlencode('Method not allowed'));
exit();

function sendToAweber($data)
{
    unset($data['form_type']);
    unset($data['web_builder_user_id']);
    unset($data['project_directory']);
    unset($data['sales_page_id']);
    $aweberUrl = BASE_URL . "/api_files/aweber.php";
    $ch = curl_init($aweberUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return ['status' => false, 'message' => 'AWeber API Error: ' . curl_error($ch)];
    }
    curl_close($ch);
    $decodedResponse = json_decode($response, true);
    return $decodedResponse !== null ? $decodedResponse : ['status' => false, 'message' => 'Invalid response from AWeber API'];
}
