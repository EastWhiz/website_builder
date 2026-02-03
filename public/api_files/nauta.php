<?php
include_once 'config.php'; // Include config to get BASE_URL
include_once 'save_lead_handler.php'; // Include save lead functionality
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data
    $postData = $_POST;
    $getData = $_GET;

    $dynamicCid = $getData['cid'] ?? '';
    $dynamicPid = $getData['pid'] ?? '';
    $dynamicSO = $getData['so'] ?? '';

    // Regular mode: Continue with external API calls
    // Setup cURL to call the Nauta API
    $ch = curl_init('https://yourleads.org/api/affiliates/v2/leads');

    // Prepare the data for Nauta API (JSON format)
    $data = array(
        'ip' => $postData['userip'] ?? '',
        'email' => $postData['email'] ?? '',
        'first_name' => $postData['firstname'] ?? '',
        'last_name' => $postData['lastname'] ?? '',
        'password' => $postData['password'] ?? 'DefaultPassword123',
        'phone' => $postData['phone'] ?? '',
        'country_code' => $postData['country'] ?? '',
        'lead_language' => $postData['lang'] ?? 'en',
        'is_test' => false,
    );

    // Add optional fields if available
    if (!empty($dynamicCid)) {
        $data['affiliate_id'] = $dynamicCid;
    }
    if (!empty($dynamicPid)) {
        $data['offer_id'] = $dynamicPid;
    }

    $nautaApiToken = "";

    // Check if self-hosted mode
    $isSelfHosted = (isset($postData['is_self_hosted']) && $postData['is_self_hosted'] == "true") ? true : false;

    if ($isSelfHosted) {
        // Self-hosted mode: Skip external API calls, only save to CRM
        $responseArray = [
            'status' => true,
            'message' => 'Lead processed successfully (self-hosted)',
            'is_self_hosted' => true
        ];

        // Save lead to CRM directly
        saveLead($postData, $getData, $responseArray, 'nauta', 'success', $data);

        // Redirect to thank you page
        header('Location: ' . BASE_URL . '/api_files/thank_you.php?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO));
        exit();
    }

    // Set cURL options for the Nauta API request
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $nautaApiToken,
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

    $responseArray = json_decode($response, true);

    // Check if response is valid JSON
    if ($responseArray === null && json_last_error() !== JSON_ERROR_NONE) {
        $message = 'Invalid response from Nauta API. Please try again.';
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($message));
        exit();
    }

    // Save lead to CRM - always call regardless of main API success/failure
    $leadSaveStatus = 'success';
    // Nauta API success response contains: lead_uuid, auto_login_url, advertiser_uuid (optional), advertiser_name (optional)
    if ($httpCode !== 200 || !isset($responseArray['lead_uuid'])) {
        $leadSaveStatus = 'failure';
    }
    saveLead($postData, $getData, $responseArray, 'nauta', $leadSaveStatus, $data);

    // Send data to Aweber for adding the subscriber
    $aweberResponse = sendToAweber($postData);

    // Filter and sanitize response for the client
    // Success response structure: { lead_uuid, auto_login_url, advertiser_uuid (optional), advertiser_name (optional) }
    if ($httpCode !== 200 || !isset($responseArray['lead_uuid'])) {

        // Default fallback message
        $message = 'An error occurred. Please try again.';

        // Use general API message if exists
        if (!empty($responseArray['message'])) {
            $message = $responseArray['message'];
        }

        // Add detailed error messages (without codes)
        if (!empty($responseArray['errors']) && is_array($responseArray['errors'])) {
            $errorMessages = array_column($responseArray['errors'], 'message');
            $message .= "\n" . implode("\n", $errorMessages);
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
    // Handle method not allowed (only POST method is allowed)
    // http_response_code(405);
    // echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);

    header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('Method not allowed'));
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

