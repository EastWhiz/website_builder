<?php
include_once 'config.php';
include_once 'save_lead_handler.php';
include_once 'api_error_helper.php'; // Extract readable error from API responses
include_once __DIR__ . '/aweber_send_helper.php';

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
    $optionalFields = [
        // ELPS navigation integration fields
        'zipcode',
        'currentAdvisor',
        'ageRange',
        'retirementPlan',
        'businessOwner',
        'totalInvestableAssets',
        'investableAssetsDetail',
        'annualIncome',
        // common aliases seen across forms
        'zip',
        'postal_code',
    ];

    foreach ($optionalFields as $field) {
        if (isset($postData[$field])) {
            $value = trim((string) $postData[$field]);
            if ($value !== '') {
                $payload[$field] = $value;
            }
        }
    }

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

/** Map browser `lang` POST (e.g. en-US) to Trackbox `lg` (ISO 639-1, uppercase). */
function trackboxNormalizeLangForLg(string $raw): string
{
    $t = trim($raw);
    if ($t === '') {
        return 'EN';
    }
    $parts = preg_split('/[-_]/', $t, 2);
    $primary = isset($parts[0]) ? preg_replace('/[^A-Za-z]/', '', $parts[0]) : '';
    if (strlen($primary) < 2) {
        return 'EN';
    }

    return strtoupper(substr($primary, 0, 2));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = $_POST;
    $getData = $_GET;
    $dynamicCid = getVal($getData, 'cid');
    $dynamicPid = getVal($getData, 'pid');
    $dynamicSO = getVal($getData, 'so');
    if ($dynamicCid === '' && trim(getVal($postData, 'cid')) !== '') {
        $dynamicCid = trim(getVal($postData, 'cid'));
    }
    if ($dynamicPid === '' && trim(getVal($postData, 'pid')) !== '') {
        $dynamicPid = trim(getVal($postData, 'pid'));
    }
    if ($dynamicSO === '' && trim(getVal($postData, 'so')) !== '') {
        $dynamicSO = trim(getVal($postData, 'so'));
    }

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
    $requestIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos((string) $requestIp, ',') !== false) {
        $requestIp = trim(explode(',', (string) $requestIp)[0]);
    }
    $resolvedIp = trim(getVal($postData, 'userip'));
    if ($resolvedIp === '') {
        $resolvedIp = trim((string) $requestIp);
    }

    $ch = curl_init($endpoint);

    // Shared Trackbox payload (ELPS / MagicAds / NewMedis / Pastile / SeaMediaOne / etc.)
    $data = [
        'ai' => '',
        'ci' => '',
        'gi' => '',
        'userip' => $resolvedIp,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $email,
        'password' => 'G7pXr2kQ',
        'phone' => $phoneData['phone'],
        'so' => $soFinal,
        'lg' => trackboxNormalizeLangForLg(getVal($postData, 'lang')),
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
        sendToAweberIfEnabled($postData);

        $thankYouParams = [
            'cid' => $dynamicCid,
            'pid' => $dynamicPid,
            'so' => $dynamicSO,
        ];
        $redirectToBroker = strtolower(trim(getVal($postData, 'redirect_to_broker'))) === 'yes';
        $redirectDelay = (int) getVal($postData, 'broker_redirect_delay');
        if ($redirectDelay < 0) {
            $redirectDelay = 0;
        }
        $brokerUrl = trim(getVal($postData, 'broker_url'));
        if ($brokerUrl !== '' && !filter_var($brokerUrl, FILTER_VALIDATE_URL)) {
            $brokerUrl = '';
        }
        if ($redirectToBroker && $brokerUrl !== '') {
            $thankYouParams['redirect_to_broker'] = 'yes';
            $thankYouParams['broker_redirect_delay'] = (string) $redirectDelay;
            $thankYouParams['broker_url'] = $brokerUrl;
        }
        header('Location: ' . BASE_URL . '/api_files/thank_you.php?' . http_build_query($thankYouParams));
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
    if (($brokerUrl === null || $brokerUrl === '') && trim(getVal($postData, 'broker_url')) !== '') {
        $fromPost = trim(getVal($postData, 'broker_url'));
        if (filter_var($fromPost, FILTER_VALIDATE_URL)) {
            $brokerUrl = $fromPost;
        }
    }

    sendToAweberIfEnabled($postData);

    if ($httpCode !== 200 || !isset($responseArray['status']) || !$responseArray['status']) {
        $apiErrorMessage = extractApiErrorMessage($responseArray);
        $finalMessage = $apiErrorMessage ?: ($responseArray['data'] ?? 'An error occurred. Please try again.');
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($finalMessage));
        exit();
    }
    $thankYouParams = [
        'cid' => $dynamicCid,
        'pid' => $dynamicPid,
        'so' => $dynamicSO,
    ];
    if ($redirectToBroker && $brokerUrl && filter_var($brokerUrl, FILTER_VALIDATE_URL)) {
        $thankYouParams['redirect_to_broker'] = 'yes';
        $thankYouParams['broker_redirect_delay'] = (string) $redirectDelay;
        $thankYouParams['broker_url'] = $brokerUrl;
    }
    header('Location: ' . BASE_URL . '/api_files/thank_you.php?' . http_build_query($thankYouParams));
    exit();
}

$getData = $_GET ?? [];
header('Location: ' . BASE_URL . '?cid=' . urlencode($getData['cid'] ?? '') . '&pid=' . urlencode($getData['pid'] ?? '') . '&so=' . urlencode($getData['so'] ?? '') . '&api_error=' . urlencode('Method not allowed'));
exit();
