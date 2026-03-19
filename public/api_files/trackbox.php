<?php
include_once 'config.php';
include_once 'save_lead_handler.php';
include_once 'api_error_helper.php';

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

    // GetLinked style: body.details.redirect.url
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

    $data = [
        'ai' => '',
        'ci' => '',
        'gi' => '',
        'userip' => getVal($postData, 'userip'),
        'firstname' => getVal($postData, 'firstname'),
        'lastname' => getVal($postData, 'lastname'),
        'email' => getVal($postData, 'email'),
        'password' => 'hardcodedpassword',
        'phone' => getVal($postData, 'phone'),
        'so' => $dynamicSO,
        'lg' => 'EN',
        'country' => getVal($postData, 'country'),
    ];
    if ($formType === 'magicads') {
        $data['cid'] = $dynamicCid;
        $data['sub'] = getVal($postData, 'sub') ?? '';
        $data['ad'] = getVal($postData, 'ad') ?? '';
        $data['term'] = getVal($postData, 'term') ?? '';
        $data['campaign'] = getVal($postData, 'campaign') ?? '';
        $data['medium'] = getVal($postData, 'medium') ?? '';
    } else {
        $data['affClickId'] = $dynamicCid;
    }

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

    $responseArray = json_decode($response, true);
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
        $apiMsg = formatApiErrorForRedirect(is_array($responseArray) ? $responseArray : [], $httpCode, '');
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($apiMsg));
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
