<?php
include_once 'config.php'; // Include config to get BASE_URL
include_once 'save_lead_handler.php'; // Include save lead functionality
include_once 'api_error_helper.php';
// Set headers for CORS and JSON content
header('Access-Control-Allow-Origin: ' . BASE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getVal($arr, $key)
{
    return isset($arr[$key]) ? $arr[$key] : '';
}

/**
 * Optional phone for iRev (IrevPlatformProvider): if empty, no phone fields are sent.
 * If present, must be valid E.164 (+…digits) or US 10-digit national (no libphonenumber in export).
 *
 * @return array{valid:bool, phone:?string}
 */
function parseIrevPhoneForExport(string $phone, string $countryIso2): array
{
    $trimmed = trim($phone);
    if ($trimmed === '') {
        return ['valid' => true, 'phone' => null];
    }

    $clean = preg_replace('/[^\d+]/', '', $trimmed);
    // Same quick path as IrevPlatformProvider (digits after +, length 7–15)
    if (preg_match('/^\+\d{7,15}$/', $clean)) {
        return ['valid' => true, 'phone' => $clean];
    }

    $country = strtoupper(preg_replace('/[^A-Za-z]/', '', $countryIso2));
    if ($country === '') {
        $country = 'US';
    }
    if (preg_match('/^\+[1-9]\d{6,14}$/', $clean)) {
        return ['valid' => true, 'phone' => $clean];
    }
    if ($country === 'US' && preg_match('/^\d{10}$/', $clean)) {
        return ['valid' => true, 'phone' => '+1' . $clean];
    }

    return ['valid' => false, 'phone' => null];
}

/**
 * Build user-facing error text similar to IrevPlatformProvider::submit failure branch.
 */
function buildIrevApiErrorMessage(array $responseArray, int $httpCode): string
{
    $msg = extractApiErrorMessage($responseArray);
    if ($msg !== '') {
        if (!empty($responseArray['errors']) && is_array($responseArray['errors'])) {
            $errorMessages = array_column($responseArray['errors'], 'message');
            $errorMessages = array_filter($errorMessages);
            if (!empty($errorMessages)) {
                $msg .= "\n" . implode("\n", $errorMessages);
            }
        }
        return $msg;
    }

    if (!empty($responseArray['message'])) {
        $m = (string) $responseArray['message'];
        if (!empty($responseArray['errors']) && is_array($responseArray['errors'])) {
            $errorMessages = array_column($responseArray['errors'], 'message');
            $errorMessages = array_filter($errorMessages);
            if (!empty($errorMessages)) {
                $m .= "\n" . implode("\n", $errorMessages);
            }
        }
        return $m;
    }

    if (!empty($responseArray['errors']) && is_array($responseArray['errors'])) {
        $errorMessages = array_column($responseArray['errors'], 'message');
        $errorMessages = array_filter($errorMessages);
        if (!empty($errorMessages)) {
            return implode("\n", $errorMessages);
        }
    }

    switch ($httpCode) {
        case 400:
            return 'Bad request. Please check the provided data.';
        case 401:
            return 'Unauthorized. Please check your API token.';
        case 403:
            return 'Access denied.';
        case 404:
            return 'API endpoint not found.';
        case 422:
            return 'Validation failed. Please check required fields.';
        case 500:
            return 'Server error. Please try again later.';
        default:
            return 'An error occurred. Please try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = $_POST;
    $getData = $_GET;
    $dynamicCid = getVal($getData, 'cid');
    $dynamicPid = getVal($getData, 'pid');
    $dynamicSO = getVal($getData, 'so');
    // Robust cid/pid/so handling (GET first, then fall back to POST).
    // This mirrors `trackbox.php` so thank-you page tracking works even if the form submit URL
    // does not include cid/pid/so in the query string.
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

    // IrevPlatformProvider: IP is required
    $ip = trim(getVal($postData, 'userip'));
    if ($ip === '') {
        $msg = 'IP address is required. Please ensure IP address is provided.';
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($msg));
        exit();
    }

    $countryRaw = getVal($postData, 'country');
    $phoneParsed = parseIrevPhoneForExport(getVal($postData, 'phone'), $countryRaw);
    if (!$phoneParsed['valid']) {
        $msg = 'It seems like you didn\'t enter a valid phone number. Please enter your phone number in order to get exclusive help from one of our specialists!';
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($msg));
        exit();
    }

    $firstname = trim(getVal($postData, 'firstname'));
    $lastname = trim(getVal($postData, 'lastname'));
    $email = trim(getVal($postData, 'email'));

    // Shared iRev / Nauta payload shape (IrevPlatformProvider::submit)
    $data = ['ip' => $ip];

    if ($countryRaw !== '') {
        $data['country_code'] = strtoupper(substr((string) $countryRaw, 0, 2));
    }

    $leadLang = trim(getVal($postData, 'lead_language'));
    if ($leadLang === '') {
        $leadLang = trim(getVal($postData, 'language'));
    }
    if ($leadLang === '') {
        $leadLang = trim(getVal($postData, 'lang'));
    }
    if ($leadLang !== '') {
        // Normalize to ISO 639-1 (2-letter, lowercase). Accept values like "en-US".
        $leadLangAlpha = preg_replace('/[^A-Za-z]/', '', (string) $leadLang);
        $leadLangIso = strtolower(substr($leadLangAlpha, 0, 2));
        if (strlen($leadLangIso) >= 2) {
            $data['lead_language'] = $leadLangIso;
        }
    }

    if (array_key_exists('is_test', $postData)) {
        $v = $postData['is_test'];
        $data['is_test'] = $v === true || $v === 1 || $v === '1' || strtolower((string) $v) === 'true' || strtolower((string) $v) === 'yes' || strtolower((string) $v) === 'on';
    }

    if ($email !== '') {
        $data['email'] = $email;
    }
    if ($firstname !== '') {
        $data['first_name'] = $firstname;
    }
    if ($lastname !== '') {
        $data['last_name'] = $lastname;
    }

    $postPassword = trim(getVal($postData, 'password'));
    $data['password'] = $postPassword !== '' ? $postPassword : '123abcDEF';

    if ($phoneParsed['phone'] !== null) {
        $data['phone'] = $phoneParsed['phone'];
        $data['contact'] = $phoneParsed['phone'];
    }

    $affiliatePost = trim(getVal($postData, 'affiliate_id'));
    if ($affiliatePost !== '') {
        $data['affiliate_id'] = $affiliatePost;
    }

    $offerPost = trim(getVal($postData, 'offer_id'));
    if ($offerPost !== '') {
        $data['offer_id'] = $offerPost;
    }

    // Provider: aff_sub is set only when aff_sub is present in lead input; value is click id (cid)
    $affSubFlag = trim(getVal($postData, 'aff_sub'));
    if ($affSubFlag !== '') {
        $clickForSub = $dynamicCid !== '' ? $dynamicCid : trim(getVal($postData, 'cid'));
        $data['aff_sub'] = (string) $clickForSub;
    }

    $ch = curl_init($endpoint);

    $irevApiToken = "";

    $isSelfHosted = (isset($postData['is_self_hosted']) && $postData['is_self_hosted'] == "true") ? true : false;

    if ($isSelfHosted) {
        $responseArray = [
            'status' => true,
            'message' => 'Lead processed successfully (self-hosted)',
            'is_self_hosted' => true
        ];
        saveLead($postData, $getData, $responseArray, $slug, 'success', $data);
        header('Location: ' . BASE_URL . '/api_files/thank_you.php?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO));
        exit();
    }

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $irevApiToken,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        // echo json_encode(['status' => false, 'message' => curl_error($ch)]);
        // exit();

        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode(curl_error($ch)));
        exit();
    }
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

    // Save lead to CRM - always call regardless of main API success/failure
    $leadSaveStatus = 'success';
    // IrevPlatformProvider: HTTP 200 and non-empty lead_uuid
    if ($httpCode !== 200 || empty($responseArray['lead_uuid'])) {
        $leadSaveStatus = 'failure';
    }
    saveLead($postData, $getData, $responseArray, $slug, $leadSaveStatus, $data);

    // Configure optional broker redirect for Thank You page
    $redirectToBroker = strtolower(trim(getVal($postData, 'redirect_to_broker'))) === 'yes';
    $redirectDelay = (int) getVal($postData, 'broker_redirect_delay');
    if ($redirectDelay < 0) {
        $redirectDelay = 0;
    }
    $brokerUrl = null;
    if (is_array($responseArray)) {
        // iRev: primary URL is auto_login_url; also support common keys and nested structures,
        // using the shared helper from trackbox.php if available.
        if (function_exists('findBrokerRedirectUrl')) {
            $brokerUrl = findBrokerRedirectUrl($responseArray);
        } else {
            if (!empty($responseArray['auto_login_url']) && filter_var($responseArray['auto_login_url'], FILTER_VALIDATE_URL)) {
                $brokerUrl = $responseArray['auto_login_url'];
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

    // Send data to Aweber for adding the subscriber
    $aweberResponse = sendToAweber($postData);

    // Filter and sanitize response for the client
    // Success response structure: { lead_uuid, auto_login_url, advertiser_uuid (optional), advertiser_name (optional) }
    if ($httpCode !== 200 || empty($responseArray['lead_uuid'])) {
        $message = buildIrevApiErrorMessage($responseArray, $httpCode);

        // echo json_encode([
        //     'status' => false,
        //     'message' => $message,
        //     'aweber_message' => $aweberResponse
        // ]);

        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($message));
        exit();
    } else {
        // Success: Response contains lead_uuid, auto_login_url, and optionally advertiser_uuid/advertiser_name
        // echo json_encode([
        //     'status' => true,
        //     'message' => 'Registration completed successfully.',
        //     'lead_uuid' => $responseArray['lead_uuid'] ?? '',
        //     'auto_login_url' => $responseArray['auto_login_url'] ?? '',
        //     'advertiser_name' => $responseArray['advertiser_name'] ?? '',
        //     'aweber_message' => $aweberResponse
        // ]);

        header('Location: ' . BASE_URL . '/api_files/thank_you.php?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO));
        exit();
    }
} else {
    $getData = $_GET ?? [];
    header('Location: ' . BASE_URL . '?cid=' . urlencode($getData['cid'] ?? '') . '&pid=' . urlencode($getData['pid'] ?? '') . '&so=' . urlencode($getData['so'] ?? '') . '&api_error=' . urlencode('Method not allowed'));
    exit();
}

// Function to send data to Aweber API
function sendToAweber($data)
{
    unset($data['form_type']);
    unset($data['web_builder_user_id']);
    unset($data['project_directory']);
    unset($data['sales_page_id']);
    $aweberUrl = BASE_URL . "/api_files/aweber.php"; // Using BASE_URL to form the Aweber API URL

    // Initialize cURL for Aweber API
    $ch = curl_init($aweberUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return ['status' => false, 'message' => 'AWeber API Error: ' . curl_error($ch)];
    }

    curl_close($ch);

    $decodedResponse = json_decode($response, true);
    if ($decodedResponse === null) {
        return ['status' => false, 'message' => 'Invalid response from AWeber API'];
    }

    return $decodedResponse;
}
