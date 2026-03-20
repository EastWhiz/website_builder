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

    // Get endpoint from injected credentials (set during export)
    $endpoint = "";
    if (empty($endpoint)) {
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('API endpoint not configured'));
        exit();
    }

    $ch = curl_init($endpoint);

    // Prepare the data for iRev API (JSON format) - same payload for all iRev instances
    $data = array(
        'ip' => getVal($postData, 'userip'),
        'email' => getVal($postData, 'email'),
        'first_name' => getVal($postData, 'firstname'),
        'last_name' => getVal($postData, 'lastname'),
        'password' => getVal($postData, 'password') ?: 'DefaultPassword123',
        'phone' => getVal($postData, 'phone'),
        'country_code' => getVal($postData, 'country'),
        'lead_language' => getVal($postData, 'lang') ?: 'en',
        'is_test' => false,
    );
    if (!empty($dynamicCid)) {
        $data['aff_sub'] = $dynamicCid;
    }

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
        // DEBUG: store full raw API response for troubleshooting broker redirects.
        // WARNING: This may contain sensitive data; remove after debugging.
        $debugPath = __DIR__ . '/api-response.txt';
        $curlErr = curl_error($ch);
        $debugResponse = ($response === false || $response === null) ? '' : (string) $response;
        $debugPayload =
            "=== " . date('c') . " ===\n" .
            "curlError=" . $curlErr . "\n" .
            "response:\n" . $debugResponse . "\n\n";
        @file_put_contents($debugPath, $debugPayload, FILE_APPEND);

        // echo json_encode(['status' => false, 'message' => curl_error($ch)]);
        // exit();

        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode(curl_error($ch)));
        exit();
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // DEBUG: store full raw API response for troubleshooting broker redirects.
    // WARNING: This may contain sensitive data; remove after debugging.
    $debugPath = __DIR__ . '/api-response.txt';
    $debugResponse = ($response === false || $response === null) ? '' : (string) $response;
    $debugPayload =
        "=== " . date('c') . " ===\n" .
        "httpCode=" . (string) $httpCode . "\n" .
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

    // Save lead to CRM - always call regardless of main API success/failure
    $leadSaveStatus = 'success';
    // iRev API success response contains: lead_uuid, auto_login_url, advertiser_uuid (optional), advertiser_name (optional)
    if ($httpCode !== 200 || !isset($responseArray['lead_uuid'])) {
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
    if ($httpCode !== 200 || !isset($responseArray['lead_uuid'])) {
        $message = extractApiErrorMessage($responseArray);
        if ($message === '') {
            $message = 'An error occurred. Please try again.';
        }

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
