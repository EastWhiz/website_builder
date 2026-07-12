<?php
include_once 'config.php'; // Include config to get BASE_URL
include_once 'otp_cleanup.php'; // Include OTP cleanup helper (Step 10)
include_once 'turnstile_verify.php'; // Include Turnstile verification helper
include_once 'save_lead_handler.php'; // Include CRM failed submission tracking
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

    $useTurnstile = strtolower(trim((string) ($postData['use_turnstile'] ?? '')));
    $turnstileRequested = in_array($useTurnstile, ['true', '1', 'yes', 'on'], true);
    if ($turnstileRequested) {
        $turnstileResult = turnstileVerifySubmission($postData, $_SERVER['REMOTE_ADDR'] ?? null);
        if (!($turnstileResult['success'] ?? false)) {
            $message = $turnstileResult['message'] ?? 'Turnstile verification failed.';
            $errorCodes = $turnstileResult['error_codes'] ?? [];
            if (!empty($errorCodes)) {
                $message .= ' (' . implode(', ', array_map('strval', $errorCodes)) . ')';
            }

            $turnstileApiName = trim((string) ($postData['save_lead_slug'] ?? ''));
            if ($turnstileApiName === '') {
                $turnstileApiName = trim((string) ($postData['form_type'] ?? ''));
            }
            if ($turnstileApiName === '') {
                $turnstileApiName = pathinfo(trim((string) ($postData['api_platform_file'] ?? '')), PATHINFO_FILENAME);
            }
            if ($turnstileApiName === '') {
                $turnstileApiName = 'turnstile';
            }

            $blockedIp = resolveRequestIp();
            saveLead(
                $postData,
                $getData,
                [
                    'status' => false,
                    'message' => 'Turnstile Failed',
                    'turnstile_message' => $message,
                    'turnstile_error_codes' => $errorCodes,
                ],
                $turnstileApiName,
                'failure',
                [
                    'turnstile' => [
                        'success' => false,
                        'error_codes' => array_values((array) $errorCodes),
                        'blocked_ip' => $blockedIp,
                        'status' => $turnstileResult['status'] ?? null,
                    ],
                ],
                [
                    'failure_reason' => 'Turnstile Failed',
                    'blocked_ip' => $blockedIp,
                ]
            );

            header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($message));
            exit();
        }
    }

    // New routing: use api_platform_file for deterministic platform file selection.
    $apiPlatformFile = isset($postData['api_platform_file']) ? trim((string) $postData['api_platform_file']) : '';
    $allowedApiPlatformFiles = [
        'trackbox.php',
        'irev.php',
        'leadgreed.php',
        'getlinked.php',
        'aweber.php',
    ];

    if ($apiPlatformFile !== '' && in_array($apiPlatformFile, $allowedApiPlatformFiles, true)) {
        $apiFile = $apiPlatformFile;
        $apiFilePath = __DIR__ . '/' . $apiFile;
    } else {
        // Backward compatibility: fallback to form_type mapping for older exports.
        $formType = isset($postData['form_type']) ? trim((string) $postData['form_type']) : '';

        $apiFiles = [
            'elps' => 'trackbox.php',
            'magicads' => 'trackbox.php',
            'newmedis' => 'trackbox.php',
            'pastile' => 'trackbox.php',
            'seamediaone' => 'trackbox.php',
            'dark' => 'trackbox.php',
            'tigloo' => 'trackbox.php',
            'nauta' => 'irev.php',
            'irev' => 'irev.php',
            'electra' => 'leadgreed.php',
            'riceleads' => 'leadgreed.php',
            'adzentric' => 'leadgreed.php',
            'koi' => 'getlinked.php',
            'meeseeksmedia' => 'getlinked.php',
            'aweber' => 'aweber.php',
        ];

        if ($formType === '' || !isset($apiFiles[$formType])) {
            $msg = $formType === '' ? 'Form type (form_type) is required.' : 'Invalid form type specified: ' . $formType;
            header('Location: ' . BASE_URL . '?cid=' . urlencode($dynamicCid) . '&pid=' . urlencode($dynamicPid) . '&so=' . urlencode($dynamicSO) . '&api_error=' . urlencode($msg));
            exit();
        }

        $apiFile = $apiFiles[$formType];
        $apiFilePath = __DIR__ . '/' . $apiFile;
    }

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
