<?php
include_once 'config.php';

// OTP Testing Mode (injected during export or set manually)
// Set $GLOBALS['otp_testing_mode'] = true; to enable testing mode
if (!isset($GLOBALS['otp_testing_mode'])) {
    $GLOBALS['otp_testing_mode'] = false;
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
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit();
    }

    $email = $input['email'] ?? '';
    $otpServiceId = $input['otp_service_id'] ?? '';
    $formIdentifier = $input['form_identifier'] ?? '';
    $phone = $input['phone'] ?? ''; // Get phone from request if session doesn't exist

    // Validate required fields
    if (empty($formIdentifier)) {
        echo json_encode(['success' => false, 'message' => 'Missing form identifier']);
        exit();
    }

    $sessionKey = 'otp_verification_' . $formIdentifier;
    $otpData = $_SESSION[$sessionKey] ?? null;

    // If session doesn't exist (e.g., after max attempts or expiry), allow regeneration with request data
    if (!$otpData) {
        // Validate that we have required data to create a new session
        if (empty($phone) || empty($email) || empty($otpServiceId)) {
            echo json_encode([
                'success' => false,
                'message' => 'No active OTP session found. Please try submitting the form again.',
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
        echo json_encode([
            'success' => false,
            'message' => $smsResult['message'],
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'New OTP sent successfully.',
        'form_identifier' => $formIdentifier,
    ]);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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
    
    // Verify this is the correct service (must match injected service ID)
    if (!$injectedServiceId || $otpServiceId != $injectedServiceId) {
        return ['success' => false, 'message' => 'OTP service not configured for this exported page'];
    }
    
    // Verify credentials are available
    if (empty($accessKey)) {
        return ['success' => false, 'message' => 'OTP service access key not configured'];
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
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'message' => 'SMS service error: ' . $error];
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'message' => 'SMS sent successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to send SMS. HTTP Code: ' . $httpCode];
    }
    
    // Add support for other services here in the future
    return ['success' => false, 'message' => 'Unsupported OTP service type: ' . $injectedServiceName];
}

