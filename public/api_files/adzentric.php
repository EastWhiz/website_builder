<?php
include_once 'config.php'; // Include config to get BASE_URL
include_once 'save_lead_handler.php'; // Include save lead functionality
// Set headers for CORS and JSON content
header('Access-Control-Allow-Origin: ' . BASE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-api-key');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data
    $postData = $_POST;
    $getData = $_GET;

    $dynamicCid = $getData['cid'] ?? '';
    $dynamicPid = $getData['pid'] ?? '';
    $dynamicSO = $getData['so'] ?? '';

    // Prepare the data for AdZentric API (RiceLeads-style: affid, funnel=SO, aff_sub=CID)
    $data = array(
        'affid' => '',
        'first_name' => $postData['firstname'] ?? '',
        'last_name' => $postData['lastname'] ?? '',
        'email' => $postData['email'] ?? '',
        'password' => $postData['password'] ?? 'DefaultPass1',
        'phone' => $postData['phone'] ?? '',
        'source' => BASE_URL,
        '_ip' => $postData['userip'] ?? '',
        'area_code' => $postData['area_code'] ?? '',
        'funnel'     => $dynamicSO,
        'aff_sub'    => $dynamicCid,
        'aff_sub2'   => $dynamicCid,
        'aff_sub5'   => $dynamicCid,
    );

    $xapikey = "";

    // Check if self-hosted mode
    $isSelfHosted = (isset($postData['is_self_hosted']) && $postData['is_self_hosted'] == "true") ? true : false;

    if ($isSelfHosted) {
        $responseArray = [
            'status' => true,
            'message' => 'Lead processed successfully (self-hosted)',
            'is_self_hosted' => true
        ];
        saveLead($postData, $getData, $responseArray, 'adzentric', 'success', $data);
        header('Location: ' . BASE_URL . '/api_files/thank_you.php?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO));
        exit();
    }

    // Initialize cURL to call AdZentric API (update endpoint when available)
    $endpoint = 'https://ldlgapi.com/leads';
    $ch = curl_init($endpoint);

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Api-Key: ' . $xapikey
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode(curl_error($ch)));
        exit();
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseArray = json_decode($response, true);

    $leadSaveStatus = 'success';
    if (!in_array($httpCode, [200, 201])) {
        $leadSaveStatus = 'failure';
    }
    saveLead($postData, $getData, $responseArray, 'adzentric', $leadSaveStatus, $data);

    $aweberResponse = sendToAweber($postData);

    if (!in_array($httpCode, [200, 201])) {
        $message = 'An error occurred. Please try again.';
        if (!empty($responseArray['errors']) && is_array($responseArray['errors'])) {
            $message = implode("\n", $responseArray['errors']);
        }
        if (empty($responseArray['errors'])) {
            switch ($httpCode) {
                case 400: $message = 'Validation failed. Please check required fields.'; break;
                case 403: $message = 'Access denied or lead validation failed.'; break;
                case 409: $message = 'Lead registration conflict. Please try again later.'; break;
            }
        }
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($message));
        exit();
    }
    header('Location: ' . BASE_URL . '/api_files/thank_you.php?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO));
    exit();
} else {
    $dynamicCid = $_GET['cid'] ?? '';
    $dynamicPid = $_GET['pid'] ?? '';
    $dynamicSO = $_GET['so'] ?? '';
    header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('Method not allowed'));
    exit();
}

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
