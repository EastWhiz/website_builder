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

    // Helper function to get value or empty string
    function getVal($arr, $key) {
        return isset($arr[$key]) ? $arr[$key] : '';
    }

    // Setup cURL to call the Zeus API
    $ch = curl_init('https://ep.elpistrack.io/api/signup/procform');

    // Prepare the data for Zeus API
    $data = array(
        'ai' => '2958198',
        'ci' => '1',
        'gi' => '173',
        'userip' => $postData['userip'],
        'firstname' => $postData['firstname'],
        'lastname' => $postData['lastname'],
        'email' => $postData['email'],
        'password' => 'hardcodedpassword',
        'phone' => $postData['phone'],
        'so' => $postData['so'],
        'lg' => 'EN',
        'country' => $postData['country'],
        'affClickId' => $postData['cid']
    );

    // Set cURL options for the Zeus API request
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'x-trackbox-username: cfff',
        'x-trackbox-password: 1YAnplgj!',
        'x-api-key: 2643889w34df345676ssdas323tgc738'
    ));

    // Error handling for cURL
    if (curl_errno($ch)) {
        echo json_encode(['status' => 'error', 'message' => curl_error($ch)]);
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
        // Return error response if Zeus API call fails
        echo json_encode([
            'status' => false,
            'message' => $responseArray['data'] ?? 'An error occurred. Please try again.',
            'aweber_message' => $aweberResponse
        ]);
    } else {
        // Return success message if Zeus API call is successful
        echo json_encode([
            'status' => true,
            'message' => $responseArray['message'] ?? 'Registration completed successfully.',
            'aweber_message' => $aweberResponse
        ]);
    }
} else {
    // Handle method not allowed (only POST method is allowed)
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}

// Function to send data to Aweber API
function sendToAweber($data) {
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
?>
