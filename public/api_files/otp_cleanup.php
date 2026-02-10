<?php
/**
 * OTP Session Cleanup Helper
 * 
 * This function cleans up OTP session data after successful form submission
 * or when OTP is no longer needed. Works in standalone mode (no Laravel).
 * 
 * @param string $formIdentifier The form identifier (MD5 hash from frontend)
 * @return void
 */
function cleanupOtpSession($formIdentifier)
{
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Only proceed if form identifier is provided
    if (empty($formIdentifier)) {
        return;
    }
    
    // Session key matches the format used in otp_generate.php, otp_verify.php
    $sessionKey = 'otp_verification_' . $formIdentifier;
    
    // Remove OTP session data
    if (isset($_SESSION[$sessionKey])) {
        unset($_SESSION[$sessionKey]);
    }
}

/**
 * Cleanup expired OTP sessions
 * This can be called periodically to clean up old sessions
 * 
 * @return int Number of sessions cleaned up
 */
function cleanupExpiredOtpSessions()
{
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $cleanedCount = 0;
    $currentTime = time();
    $prefix = 'otp_verification_';
    
    // Iterate through all session keys
    foreach ($_SESSION as $key => $value) {
        // Check if this is an OTP session
        if (strpos($key, $prefix) === 0 && is_array($value)) {
            // Check if session has expired
            if (isset($value['expires_at']) && $value['expires_at'] < $currentTime) {
                unset($_SESSION[$key]);
                $cleanedCount++;
            }
        }
    }
    
    return $cleanedCount;
}

