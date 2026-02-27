<?php
include_once 'config.php';
include_once 'save_lead_handler.php';

header('Access-Control-Allow-Origin: ' . BASE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-trackbox-username, x-trackbox-password, x-api-key');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Platform config: form_type => slug for saveLead (endpoint comes from credentials)
$TRACKBOX_API_CONFIG = [
    'elps' => ['slug' => 'elps'],
    'magicads' => ['slug' => 'magicads'],
    'tigloo' => ['slug' => 'tigloo'],
    'pastile' => ['slug' => 'pastile'],
    'newmedis' => ['slug' => 'newmedis'],
    'seamediaone' => ['slug' => 'seamediaone'],
];

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

    $formType = $postData['form_type'] ?? '';
    if (!isset($TRACKBOX_API_CONFIG[$formType])) {
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('Invalid form type for Trackbox: ' . $formType));
        exit();
    }

    $config = $TRACKBOX_API_CONFIG[$formType];
    
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
        saveLead($postData, $getData, $responseArray, $config['slug'], 'success', $data);
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
    saveLead($postData, $getData, $responseArray, $config['slug'], $leadSaveStatus, $data);

    if (function_exists('sendToAweber')) {
        sendToAweber($postData);
    }

    if ($httpCode !== 200 || !isset($responseArray['status']) || !$responseArray['status']) {
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($responseArray['data'] ?? 'An error occurred. Please try again.'));
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
