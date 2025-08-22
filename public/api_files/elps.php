<?php
include_once 'config.php'; // Include config to get BASE_URL
// Set headers for CORS and JSON content
header('Access-Control-Allow-Origin: ' . BASE_URL); // Allow requests from your BASE_URL
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-trackbox-username, x-trackbox-password, x-api-key');
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

    // Helper function to get value or empty string
    function getVal($arr, $key)
    {
        return isset($arr[$key]) ? $arr[$key] : '';
    }

    $dynamicCid = getVal($getData, 'cid') ?? '';
    $dynamicPid = getVal($getData, 'pid') ?? '';
    $dynamicSO = getVal($getData, 'so') ?? '';

    // Setup cURL to call the Zeus API
    $ch = curl_init('https://ep.elpistrack.io/api/signup/procform');

    // Prepare the data for Zeus API
    $data = array(
        'ai' => '',
        'ci' => '',
        'gi' => '',
        'userip' => getVal($postData, 'userip'),
        'firstname' => getVal($postData, 'firstname'),
        'lastname' => getVal($postData, 'lastname'),
        'email' => getVal($postData, 'email'),
        'password' => 'hardcodedpassword', // Ensure you handle passwords securely!
        'phone' => getVal($postData, 'phone'),
        'so' => $dynamicSO,
        'lg' => 'EN',
        'country' => getVal($postData, 'country'),
        'affClickId' => $dynamicCid,
        'MPC_3' => 'How old are you? | ' . getVal($postData, 'age'),
        'MPC_4' => 'Please estimate your total investable assets | ' . getVal($postData, 'assets'),
        'MPC_5' => 'What is your annual income? | ' . getVal($postData, 'income'),
        'MPC_6' => 'What is your zip code? | ' . getVal($postData, 'zipcode'),
    );

    $username = "";
    $password = "";
    $xapikey = "";

    // Set cURL options for the Zeus API request
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'x-trackbox-username: ' . $username,
        'x-trackbox-password: ' . $password,
        'x-api-key: ' . $xapikey
    ));

    // Error handling for cURL
    if (curl_errno($ch)) {
        // echo json_encode(['status' => 'error', 'message' => curl_error($ch)]);
        // exit();

        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode(curl_error($ch)));
        exit();
    }

    // Execute the cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $responseArray = json_decode($response, true);

    // Send data to Aweber for adding the subscriber
    $aweberResponse = sendToAweber($postData);

    // Filter and sanitize response for the client
    if ($httpCode !== 200 || !isset($responseArray['status']) || !$responseArray['status']) {
        // // Return error response if Zeus API call fails
        // echo json_encode([
        //     'status' => false,
        //     'message' => $responseArray['data'] ?? 'An error occurred. Please try again.',
        //     'aweber_message' => $aweberResponse
        // ]);

        // Fallback redirect to base URL if no referer is available
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($responseArray['data'] ?? 'An error occurred. Please try again.'));
        exit();
    } else {
        // Return success message if Zeus API call is successful
        // echo json_encode([
        //     'status' => true,
        //     'message' => $responseArray['message'] ?? 'Registration completed successfully.',
        //     'aweber_message' => $aweberResponse
        // ]);

        // Fallback redirect to base URL if no referer is available
        header('Location: ' . BASE_URL . '/api_files/thank_you.php?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO));
        exit();
    }
} else {
    // Handle method not allowed (only POST method is allowed)
    // http_response_code(405);
    // echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);

    // Fallback redirect to base URL if no referer is available
    header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('Method not allowed'));
    exit();
}

// Function to send data to Aweber API
function sendToAweber($data)
{
    unset($data['form_type']);
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
