<?php

namespace App\Http\Controllers;

use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class OtpVerificationController extends Controller
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Generate and send OTP
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'email' => 'required|email',
            'otp_service_id' => 'required|integer|exists:otp_services,id',
            'form_identifier' => 'required|string',
            'web_builder_user_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = $request->phone;
        $email = $request->email;
        $serviceId = $request->otp_service_id;
        $formIdentifier = $request->form_identifier;
        $userId = str_replace('U', '', $request->web_builder_user_id); // Remove 'U' prefix if present

        // Generate 6-digit OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store in session
        // Form identifier is already MD5 hash generated on frontend (Step 9)
        $sessionKey = 'otp_verification_' . $formIdentifier;
        Session::put($sessionKey, [
            'otp' => $otp,
            'expires_at' => now()->addMinutes(5)->timestamp,
            'verified' => false,
            'attempts' => 0,
            'max_attempts' => 5,
            'form_identifier' => $formIdentifier,
            'phone' => $phone,
            'email' => $email,
            'otp_service_id' => $serviceId,
        ]);

        // Check if testing mode is enabled
        $testingMode = config('otp.testing_mode', false);
        
        if ($testingMode) {
            // Testing mode: Bypass SMS and return OTP in response
            return response()->json([
                'success' => true,
                'message' => 'OTP generated successfully (TEST MODE - SMS bypassed)',
                'test_otp' => $otp
            ], 200);
        }
        
        // Production mode: Send SMS
        $smsResult = $this->otpService->sendSms($phone, $otp, $serviceId, $userId);

        if (!$smsResult['success']) {
            Session::forget($sessionKey);
            return response()->json([
                'success' => false,
                'message' => $smsResult['message']
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully'
        ], 200);
    }

    /**
     * Verify OTP
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
            'email' => 'required|email',
            'form_identifier' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $otp = $request->otp;
        $email = $request->email;
        $formIdentifier = $request->form_identifier;

        // Form identifier is already MD5 hash generated on frontend (Step 9)
        $sessionKey = 'otp_verification_' . $formIdentifier;
        $sessionData = Session::get($sessionKey);

        if (!$sessionData) {
            return response()->json([
                'success' => false,
                'message' => 'OTP session not found. Please generate a new OTP.'
            ], 404);
        }

        // Step 10: Check expiry and cleanup
        if (now()->timestamp > $sessionData['expires_at']) {
            Session::forget($sessionKey);
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please generate a new one.'
            ], 400);
        }

        // Check if already verified
        if ($sessionData['verified']) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has already been used.'
            ], 400);
        }

        // Step 10: Check max attempts and cleanup
        if ($sessionData['attempts'] >= $sessionData['max_attempts']) {
            Session::forget($sessionKey);
            return response()->json([
                'success' => false,
                'message' => 'Maximum verification attempts exceeded. Please generate a new OTP.'
            ], 400);
        }

        // Verify OTP
        if ($sessionData['otp'] !== $otp) {
            $sessionData['attempts']++;
            Session::put($sessionKey, $sessionData);

            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. Please try again.',
                'attempts_remaining' => $sessionData['max_attempts'] - $sessionData['attempts']
            ], 400);
        }

        // Step 10: Mark as verified
        // Session is kept until form submission (cleaned up in backend.php after successful submission)
        // This allows backend.php to verify OTP was used before cleaning up
        $sessionData['verified'] = true;
        Session::put($sessionKey, $sessionData);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully'
        ], 200);
    }

    /**
     * Regenerate and resend OTP
     */
    public function regenerate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp_service_id' => 'required|integer|exists:otp_services,id',
            'form_identifier' => 'required|string',
            'web_builder_user_id' => 'required|string',
            'phone' => 'nullable|string', // Phone is optional if session exists, required if regenerating after max attempts
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $serviceId = $request->otp_service_id;
        $formIdentifier = $request->form_identifier;
        $userId = str_replace('U', '', $request->web_builder_user_id);
        $phone = $request->phone ?? ''; // Get phone from request if session doesn't exist

        // Get existing session data to preserve phone
        // Form identifier is already MD5 hash generated on frontend (Step 9)
        $sessionKey = 'otp_verification_' . $formIdentifier;
        $existingData = Session::get($sessionKey);

        // If session doesn't exist (e.g., after max attempts or expiry), allow regeneration with request data
        if (!$existingData) {
            // Validate that we have required data to create a new session
            if (empty($phone) || empty($email) || empty($serviceId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active OTP session found. Please try submitting the form again.'
                ], 404);
            }
            
            // Use request data for regeneration after max attempts/expiry
            $phone = $phone;
        } else {
            $phone = $existingData['phone'];
        }

        // Generate new OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Update session with new OTP
        Session::put($sessionKey, [
            'otp' => $otp,
            'expires_at' => now()->addMinutes(5)->timestamp,
            'verified' => false,
            'attempts' => 0,
            'max_attempts' => 5,
            'form_identifier' => $formIdentifier,
            'phone' => $phone,
            'email' => $email,
            'otp_service_id' => $serviceId,
        ]);

        // Check if testing mode is enabled
        $testingMode = config('otp.testing_mode', false);
        
        if ($testingMode) {
            // Testing mode: Bypass SMS and return OTP in response
            return response()->json([
                'success' => true,
                'message' => 'New OTP generated successfully (TEST MODE - SMS bypassed)',
                'test_otp' => $otp
            ], 200);
        }
        
        // Production mode: Send SMS
        $smsResult = $this->otpService->sendSms($phone, $otp, $serviceId, $userId);

        if (!$smsResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $smsResult['message']
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'New OTP sent successfully'
        ], 200);
    }
}


