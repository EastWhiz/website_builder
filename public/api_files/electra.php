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

    // Prepare the data for Nexl API
    $data = array(
        'affid' => '13',
        'first_name' => $postData['firstname'] ?? '',
        'last_name' => $postData['lastname'] ?? '',
        'email' => $postData['email'] ?? '',
        'password' => 'Tr5yLo92',
        'phone' => $postData['phone'] ?? '',
        'source' => BASE_URL,
        'hitid' => $dynamicCid,
        '_ip' => $postData['userip'] ?? '',
        'area_code' => $postData['area_code'] ?? '',
        'funnel'     => 'crowdedfunnel',
        'aff_sub'    => $dynamicCid,
        'aff_sub2'   => 'aff_sub2',
    );

    // Initialize cURL to call Nexl API
    $ch = curl_init('https://lcaapi.net/leads');

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // URL-encoded
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
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

    // Save lead to CRM - always call regardless of main API success/failure
    $leadSaveStatus = 'success';
    if (!in_array($httpCode, [200, 201]) || empty($responseArray['lead'])) {
        $leadSaveStatus = 'failure';
    }
    // Always save lead to CRM, even if main API failed
    saveLead($postData, $getData, $responseArray, 'electra', $leadSaveStatus);

    // Send data to Aweber (optional)
    $aweberResponse = sendToAweber($postData);

    if (!in_array($httpCode, [200, 201]) || empty($responseArray['lead'])) {
        // Default message
        $message = 'An error occurred. Please try again.';

        // If API returned an "errors" array, prefer it
        if (!empty($responseArray['errors']) && is_array($responseArray['errors'])) {
            $message = implode("\n", $responseArray['errors']);
        }

        // Handle known HTTP codes explicitly if no errors array is present
        if (empty($responseArray['errors'])) {
            switch ($httpCode) {
                case 400:
                    $message = 'Validation failed. Please check required fields.';
                    break;
                case 403:
                    $message = 'Access denied or lead validation failed.';
                    break;
                case 409:
                    $message = 'Lead registration conflict. Please try again later.';
                    break;
            }
        }

        // echo json_encode([
        //     'status' => false,
        //     'message' => $message,
        //     'aweber_message' => $aweberResponse
        // ]);

        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($message));
        exit();
    } else {
        // echo json_encode([
        //     'status' => true,
        //     'message' => 'Lead registered successfully.',
        //     'aweber_message' => $aweberResponse
        // ]);

        header('Location: ' . BASE_URL . '/api_files/thank_you.php?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO));
        exit();
    }
} else {
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
