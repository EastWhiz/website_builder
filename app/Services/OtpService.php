<?php

namespace App\Services;

use App\Models\OtpService;
use App\Models\OtpServiceCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    /**
     * Send SMS via OTP service
     *
     * @param string $phone Phone number to send SMS to
     * @param string $otp OTP code to send
     * @param int $serviceId OTP service ID
     * @param int $userId User ID who owns the OTP service credentials
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendSms($phone, $otp, $serviceId, $userId)
    {
        try {
            // Get service definition
            $service = OtpService::find($serviceId);
            if (!$service || !$service->is_active) {
                return ['success' => false, 'message' => 'OTP service not found or inactive'];
            }

            // Get user credentials for this service
            $credential = OtpServiceCredential::where('user_id', $userId)
                ->where('service_id', $serviceId)
                ->with('service')
                ->first();

            if (!$credential) {
                return ['success' => false, 'message' => 'OTP service credentials not found'];
            }

            // Get decrypted credentials
            $credentials = $credential->decrypted_credentials;

            // Build SMS message
            $message = $this->buildSmsMessage($otp, $service->fields);

            // Send SMS based on service name
            return $this->sendViaService($service->name, $phone, $message, $credentials);

        } catch (\Exception $e) {
            Log::error('OTP SMS sending failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send SMS: ' . $e->getMessage()];
        }
    }

    /**
     * Build SMS message text
     *
     * @param string $otp
     * @param array $serviceFields
     * @return string
     */
    private function buildSmsMessage($otp, $serviceFields)
    {
        return "Your verification code is {$otp}.";
    }

    /**
     * Send SMS via specific service
     *
     * @param string $serviceName
     * @param string $phone
     * @param string $message
     * @param array $credentials
     * @return array
     */
    private function sendViaService($serviceName, $phone, $message, $credentials)
    {
        switch (strtolower($serviceName)) {
            case 'unimatrix':
                return $this->sendViaUniMatrix($phone, $message, $credentials);
            
            default:
                return ['success' => false, 'message' => 'Unsupported OTP service: ' . $serviceName];
        }
    }

    /**
     * Send SMS via UniMatrix
     *
     * @param string $phone
     * @param string $message
     * @param array $credentials
     * @return array
     */
    private function sendViaUniMatrix($phone, $message, $credentials)
    {
        $accessKey = $credentials['access_key'] ?? null;
        $endpointUrl = $credentials['endpoint_url'] ?? null;

        if (!$accessKey) {
            return ['success' => false, 'message' => 'UniMatrix access key is required'];
        }

        // Construct endpoint URL if not provided or if it's just the base URL
        if (!$endpointUrl || $endpointUrl === 'https://api.unimtx.com') {
            $endpointUrl = 'https://api.unimtx.com/?action=sms.message.send&accessKeyId=' . urlencode($accessKey);
        } else {
            // If endpoint_url is provided, use it as-is (user may have customized it)
            $endpointUrl = $endpointUrl;
        }

        try {
            $response = Http::timeout(10)->post($endpointUrl, [
                'to' => $phone,
                'text' => $message
            ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'SMS sent successfully'];
            }

            return ['success' => false, 'message' => 'Failed to send SMS: ' . $response->body()];

        } catch (\Exception $e) {
            Log::error('UniMatrix SMS error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'SMS service error: ' . $e->getMessage()];
        }
    }
}

