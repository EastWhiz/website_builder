<?php
include_once 'config.php'; // Include config to get BASE_URL
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

    // Get the form type to determine which API file to include
    $formType = $postData['form_type']; // Default to novelix

    // Map form types to their corresponding API files
    $apiFiles = [
        'novelix' => 'novelix.php',
        'electra' => 'electra.php',
        'aweber' => 'aweber.php',
        'dark' => 'dark.php',
        'elps' => 'elps.php',
        'meeseeksmedia' => 'meeseeksmedia.php',
        'tigloo' => 'tigloo.php',
        'koi' => 'koi.php'
    ];

    // Check if the form type has a corresponding API file
    if (!isset($apiFiles[$formType])) {
        // echo json_encode([
        //     'status' => false,
        //     'message' => 'Invalid form type specified: ' . $formType
        // ]);
        // exit;

        header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('Invalid form type specified: ' . $formType));
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
} else {
    // http_response_code(405);
    // echo json_encode(['status' => false, 'message' => 'Method not allowed']);

    header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode('Method not allowed'));
    exit();
}
