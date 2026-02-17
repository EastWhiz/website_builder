<?php
include_once 'config.php';

// OTP Testing Mode (injected during export or set manually)
// Set $GLOBALS['otp_testing_mode'] = true; to enable testing mode
if (!isset($GLOBALS['otp_testing_mode'])) {
    $GLOBALS['otp_testing_mode'] = false;
}

/**
 * Log OTP errors with service ID and user ID
 * @param string $errorMessage Original error message
 * @param string $serviceId Service ID
 * @param string $userId User ID (optional)
 * @param string $context Additional context (optional)
 */
function logOtpError($errorMessage, $serviceId = '', $userId = '', $context = '') {
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/otp_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] OTP Error - Service ID: {$serviceId}, User ID: {$userId}, Context: {$context}, Error: {$errorMessage}" . PHP_EOL;
    
    @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Set headers for CORS and JSON content
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        logOtpError('Invalid request data - JSON decode failed', '', '', 'otp_regenerate - input validation');
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again or contact us for assistance.']);
        exit();
    }

    $email = $input['email'] ?? '';
    $otpServiceId = $input['otp_service_id'] ?? '';
    $formIdentifier = $input['form_identifier'] ?? '';
    $phone = $input['phone'] ?? ''; // Get phone from request if session doesn't exist
    $webBuilderUserId = $input['web_builder_user_id'] ?? '';

    // Validate required fields
    if (empty($formIdentifier)) {
        logOtpError('Form identifier is empty', $otpServiceId, $webBuilderUserId, 'otp_regenerate - validation');
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again or contact us for assistance.']);
        exit();
    }

    $sessionKey = 'otp_verification_' . $formIdentifier;
    $otpData = $_SESSION[$sessionKey] ?? null;

    // If session doesn't exist (e.g., after max attempts or expiry), allow regeneration with request data
    if (!$otpData) {
        // Validate that we have required data to create a new session
        if (empty($phone) || empty($email) || empty($otpServiceId)) {
            logOtpError('No active OTP session and missing required data - phone: ' . (empty($phone) ? 'empty' : 'provided') . ', email: ' . (empty($email) ? 'empty' : 'provided') . ', service_id: ' . (empty($otpServiceId) ? 'empty' : 'provided'), $otpServiceId, $webBuilderUserId, 'otp_regenerate - session validation');
            echo json_encode([
                'success' => false,
                'message' => 'Something went wrong. Please try again or contact us for assistance.',
            ]);
            exit();
        }
        
        // Create new session for regeneration after max attempts/expiry
        $otpData = [
            'phone' => $phone,
            'email' => $email,
            'otp_service_id' => $otpServiceId,
        ];
    }

    // Generate new OTP
    $newOtp = strval(rand(100000, 999999));
    $otpData['otp'] = $newOtp;
    $otpData['expires_at'] = time() + (5 * 60); // 5 minutes from now
    $otpData['verified'] = false;
    $otpData['attempts'] = 0; // Reset attempts
    $otpData['max_attempts'] = 5;
    $_SESSION[$sessionKey] = $otpData;

    // Check if testing mode is enabled (injected during export or set via GLOBAL)
    $testingMode = isset($GLOBALS['otp_testing_mode']) ? $GLOBALS['otp_testing_mode'] : false;
    
    if ($testingMode) {
        // Testing mode: Bypass SMS and return OTP in response
        echo json_encode([
            'success' => true,
            'message' => 'New OTP generated successfully (TEST MODE - SMS bypassed)',
            'form_identifier' => $formIdentifier,
            'test_otp' => $newOtp
        ]);
        exit();
    }
    
    // Production mode: Send new OTP via SMS
    $smsResult = sendOtpSms($otpData['phone'], $newOtp, $otpData['otp_service_id']);

    if (!$smsResult['success']) {
        // Get original error message for logging
        $originalError = $smsResult['message'] ?? 'Unknown SMS error';
        
        // Log the original error with service ID and user ID
        logOtpError($originalError, $otpData['otp_service_id'], $webBuilderUserId, 'otp_regenerate - SMS failure');
        
        // Return actual error message from API response
        $errorMessage = $smsResult['message'] ?? 'Something went wrong. Please try again or contact us for assistance.';
        
        echo json_encode([
            'success' => false,
            'message' => $errorMessage,
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'New OTP sent successfully.',
        'form_identifier' => $formIdentifier,
    ]);
} else {
    logOtpError('Invalid request method: ' . $_SERVER['REQUEST_METHOD'], '', '', 'otp_regenerate - method validation');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again or contact us for assistance.']);
}

/**
 * Send OTP SMS via the configured service
 * 
 * @param string $phone
 * @param string $otp
 * @param string $otpServiceId
 * @return array
 */
function sendOtpSms($phone, $otp, $otpServiceId) {
    // OTP service credentials are injected during export (standalone mode only)
    // These variables are set by modifyApiFileContent in AngleTemplateController during export
    // Variables: $otp_service_id, $otp_service_name, $otp_access_key, $otp_endpoint_url
    
    // Get injected service configuration (only available in exported standalone pages)
    $injectedServiceId = isset($GLOBALS['otp_service_id']) ? $GLOBALS['otp_service_id'] : null;
    $injectedServiceName = isset($GLOBALS['otp_service_name']) ? $GLOBALS['otp_service_name'] : '';
    $accessKey = isset($GLOBALS['otp_access_key']) ? $GLOBALS['otp_access_key'] : '';
    $endpointUrl = isset($GLOBALS['otp_endpoint_url']) ? $GLOBALS['otp_endpoint_url'] : '';
    
    // Verify injected credentials are available (don't compare service_id - use injected credentials regardless of form's service_id)
    // The form's otp_service_id is just metadata; actual credentials come from injected GLOBALS
    if (empty($injectedServiceId) || empty($accessKey)) {
        $errorMessage = 'OTP service is not configured. Please contact support.';
        logOtpError('OTP service not configured - injectedServiceId or accessKey is empty', $otpServiceId, '', 'sendOtpSms - credential validation');
        return ['success' => false, 'message' => $errorMessage];
    }
    
    // Handle different service types based on injected service name
    $serviceName = strtolower($injectedServiceName);
    
    if ($serviceName === 'unimatrix') {
        // Construct UniMatrix endpoint URL
        if (empty($endpointUrl) || $endpointUrl === 'https://api.unimtx.com') {
            $fullEndpointUrl = 'https://api.unimtx.com/?action=sms.message.send&accessKeyId=' . urlencode($accessKey);
        } else {
            // Use custom endpoint URL if provided
            $fullEndpointUrl = $endpointUrl;
            // If endpoint doesn't have accessKeyId, append it
            if (strpos($fullEndpointUrl, 'accessKeyId') === false) {
                $separator = (strpos($fullEndpointUrl, '?') === false) ? '?' : '&';
                $fullEndpointUrl .= $separator . 'action=sms.message.send&accessKeyId=' . urlencode($accessKey);
            }
        }
        
        $message = "Your verification code is {$otp}.";
        
        // Send SMS via cURL
        $ch = curl_init($fullEndpointUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'to' => $phone,
            'text' => $message
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        // Fix SSL certificate issues for localhost/development
        // Check if running on localhost or development environment
        $isLocalhost = (
            isset($_SERVER['HTTP_HOST']) && 
            (
                strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
                strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
                strpos($_SERVER['HTTP_HOST'], '.local') !== false ||
                strpos($_SERVER['HTTP_HOST'], '.test') !== false ||
                (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === '127.0.0.1')
            )
        );
        
        if ($isLocalhost) {
            // Disable SSL verification for localhost (development only)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $errorMessage = 'SMS service error: ' . $error;
            logOtpError('SMS service cURL error: ' . $error, $otpServiceId, '', 'sendOtpSms - unimatrix cURL');
            return ['success' => false, 'message' => $errorMessage];
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            // Try to parse response to get actual API message if available
            $responseData = json_decode($response, true);
            if ($responseData && isset($responseData['message'])) {
                return ['success' => true, 'message' => $responseData['message']];
            }
            return ['success' => true, 'message' => 'SMS sent successfully'];
        }
        
        // Parse error response from API
        $errorMessage = 'SMS service error';
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['message'])) {
            $errorMessage = $responseData['message'];
        } elseif ($responseData && isset($responseData['error'])) {
            $errorMessage = $responseData['error'];
        } elseif (!empty($response)) {
            $errorMessage = 'HTTP ' . $httpCode . ': ' . substr(strip_tags($response), 0, 200);
        } else {
            $errorMessage = 'HTTP ' . $httpCode . ': SMS service returned an error';
        }
        
        logOtpError('SMS service HTTP error - Code: ' . $httpCode . ', Response: ' . substr($response, 0, 200), $otpServiceId, '', 'sendOtpSms - unimatrix HTTP');
        return ['success' => false, 'message' => $errorMessage];
    }
    
    // Add support for other services here in the future
    $errorMessage = 'Unsupported OTP service type: ' . $injectedServiceName;
    logOtpError('Unsupported OTP service type: ' . $injectedServiceName, $otpServiceId, '', 'sendOtpSms - unsupported service');
    return ['success' => false, 'message' => $errorMessage];
}

