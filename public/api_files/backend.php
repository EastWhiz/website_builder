<?php
include_once 'config.php'; // Include config to get BASE_URL
include_once 'otp_cleanup.php'; // Include OTP cleanup helper (Step 10)
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

    // Step 10.2: Support both form_type (primary) and api_category_id. Routing uses form_type to
    // resolve platform file; api_category_id and user_api_instance_id are passed through in POST.
    $formType = isset($postData['form_type']) ? trim((string) $postData['form_type']) : '';

    // Map form types to platform integration files (one file per platform; Trackbox handles multiple APIs)
    $apiFiles = [
        'novelix' => 'novelix.php',
        'electra' => 'electra.php',
        'aweber' => 'aweber.php',
        'dark' => 'dark.php',
        'elps' => 'trackbox.php',
        'meeseeksmedia' => 'meeseeksmedia.php',
        'tigloo' => 'trackbox.php',
        'koi' => 'koi.php',
        'pastile' => 'trackbox.php',
        'riceleads' => 'riceleads.php',
        'newmedis' => 'trackbox.php',
        'seamediaone' => 'trackbox.php',
        'nauta' => 'nauta.php',
        'irev' => 'irev.php',
        'magicads' => 'trackbox.php',
        'adzentric' => 'adzentric.php',
    ];

    // Require form_type for file selection (api_category_id is passed through in POST for platform files)
    if ($formType === '' || !isset($apiFiles[$formType])) {
        $msg = $formType === '' ? 'Form type (form_type) is required.' : 'Invalid form type specified: ' . $formType;
        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($msg));
        exit();
    }

    $apiFile = $apiFiles[$formType];
    $apiFilePath = __DIR__ . '/' . $apiFile;

    // Check if the API file exists
    if (!file_exists($apiFilePath)) {
        // echo json_encode([
        //     'status' => false,
        //     'message' => 'API file not found: ' . $apiFile
        // ]);
        // exit;

        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('API file not found: ' . $apiFile));
        exit();
    }

    // Include and execute the API file
    include $apiFilePath;
    
    // Cleanup OTP session after form processing (Step 10)
    // This is safe to call - if form_identifier exists and OTP was used, it will be cleaned up
    // If no OTP was used, no session exists and nothing happens
    if (isset($postData['form_identifier']) && !empty($postData['form_identifier'])) {
        cleanupOtpSession($postData['form_identifier']);
    }
} else {
    // http_response_code(405);
    // echo json_encode(['status' => false, 'message' => 'Method not allowed']);

    header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('Method not allowed'));
    exit();
}
