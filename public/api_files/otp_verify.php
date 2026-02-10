<?php
include_once 'config.php';
include_once 'otp_cleanup.php'; // Include OTP cleanup helper (Step 10)

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

    $otp = $input['otp'] ?? '';
    $email = $input['email'] ?? '';
    $formIdentifier = $input['form_identifier'] ?? '';

    // Step 11: Enhanced validation with specific error messages
    if (empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'OTP code is required.']);
        exit();
    }
    
    if (empty($formIdentifier)) {
        echo json_encode(['success' => false, 'message' => 'Form identifier is missing. Please refresh the page and try again.']);
        exit();
    }

    // Validate OTP format (6 digits)
    if (!preg_match('/^\d{6}$/', $otp)) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP format']);
        exit();
    }

    $sessionKey = 'otp_verification_' . $formIdentifier;
    $otpData = $_SESSION[$sessionKey] ?? null;

    if (!$otpData) {
        echo json_encode([
            'success' => false,
            'message' => 'OTP session expired or not found. Please regenerate OTP.',
        ]);
        exit();
    }

    // Increment attempts
    $otpData['attempts']++;
    $_SESSION[$sessionKey] = $otpData;

    // Check max attempts (Step 10: Cleanup on max attempts)
    if ($otpData['attempts'] > $otpData['max_attempts']) {
        cleanupOtpSession($formIdentifier);
        echo json_encode([
            'success' => false,
            'message' => 'Maximum OTP attempts exceeded. Please regenerate OTP.',
        ]);
        exit();
    }

    // Check expiry (Step 10: Cleanup on expiry)
    if ($otpData['expires_at'] < time()) {
        cleanupOtpSession($formIdentifier);
        echo json_encode([
            'success' => false,
            'message' => 'OTP has expired. Please regenerate OTP.',
        ]);
        exit();
    }

    // Check if already verified
    if ($otpData['verified']) {
        echo json_encode([
            'success' => false,
            'message' => 'OTP already used. Please regenerate OTP if you need to resubmit.',
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
        echo json_encode([
            'success' => false,
            'message' => 'Invalid OTP. Attempts remaining: ' . $remainingAttempts,
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}


