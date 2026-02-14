<?php
include_once 'config.php';
include_once 'otp_cleanup.php'; // Include OTP cleanup helper (Step 10)

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
        logOtpError('Invalid request data - JSON decode failed', '', '', 'otp_verify - input validation');
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again or contact us for assistance.']);
        exit();
    }

    $otp = $input['otp'] ?? '';
    $email = $input['email'] ?? '';
    $formIdentifier = $input['form_identifier'] ?? '';

    // Step 11: Enhanced validation with generic error messages
    if (empty($otp)) {
        logOtpError('OTP code is empty', '', '', 'otp_verify - validation');
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again or contact us for assistance.']);
        exit();
    }
    
    if (empty($formIdentifier)) {
        logOtpError('Form identifier is empty', '', '', 'otp_verify - validation');
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please refresh the page and try again.']);
        exit();
    }

    // Validate OTP format (6 digits)
    if (!preg_match('/^\d{6}$/', $otp)) {
        logOtpError('Invalid OTP format: ' . $otp, '', '', 'otp_verify - validation');
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again or contact us for assistance.']);
        exit();
    }

    $sessionKey = 'otp_verification_' . $formIdentifier;
    $otpData = $_SESSION[$sessionKey] ?? null;
    
    $serviceId = $otpData['otp_service_id'] ?? '';
    $userEmail = $otpData['email'] ?? '';

    if (!$otpData) {
        logOtpError('OTP session not found for form identifier: ' . $formIdentifier, '', '', 'otp_verify - session');
        echo json_encode([
            'success' => false,
            'message' => 'Something went wrong. Please try again or contact us for assistance.',
        ]);
        exit();
    }

    // Increment attempts
    $otpData['attempts']++;
    $_SESSION[$sessionKey] = $otpData;

    // Check max attempts (Step 10: Cleanup on max attempts)
    if ($otpData['attempts'] > $otpData['max_attempts']) {
        logOtpError('Maximum OTP attempts exceeded - attempts: ' . $otpData['attempts'] . ', max: ' . $otpData['max_attempts'], $serviceId, '', 'otp_verify - max attempts');
        cleanupOtpSession($formIdentifier);
        echo json_encode([
            'success' => false,
            'message' => 'Something went wrong. Please try again or contact us for assistance.',
        ]);
        exit();
    }

    // Check expiry (Step 10: Cleanup on expiry)
    if ($otpData['expires_at'] < time()) {
        logOtpError('OTP expired - expires_at: ' . $otpData['expires_at'] . ', current: ' . time(), $serviceId, '', 'otp_verify - expiry');
        cleanupOtpSession($formIdentifier);
        echo json_encode([
            'success' => false,
            'message' => 'Something went wrong. Please try again or contact us for assistance.',
        ]);
        exit();
    }

    // Check if already verified
    if ($otpData['verified']) {
        logOtpError('OTP already verified', $serviceId, '', 'otp_verify - already verified');
        echo json_encode([
            'success' => false,
            'message' => 'Something went wrong. Please try again or contact us for assistance.',
        ]);
        exit();
    }

    // Verify OTP
    if ($otpData['otp'] === $otp) {
        $otpData['verified'] = true;
        $_SESSION[$sessionKey] = $otpData;
        
        // Step 10: OTP is verified and form will submit immediately
        // Session is kept until form submission (cleaned up in backend.php)
        // This allows backend.php to verify OTP was used before cleaning up
        echo json_encode([
            'success' => true,
            'message' => 'OTP verified successfully.',
        ]);
    } else {
        $remainingAttempts = $otpData['max_attempts'] - $otpData['attempts'];
        logOtpError('Invalid OTP provided - remaining attempts: ' . $remainingAttempts, $serviceId, '', 'otp_verify - invalid OTP');
        echo json_encode([
            'success' => false,
            'message' => 'Something went wrong. Please try again or contact us for assistance.',
        ]);
    }
} else {
    logOtpError('Invalid request method: ' . $_SERVER['REQUEST_METHOD'], '', '', 'otp_verify - method validation');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again or contact us for assistance.']);
}


