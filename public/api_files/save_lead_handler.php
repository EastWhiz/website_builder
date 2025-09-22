<?php

/**
 * Reusable function to send lead data to CRM save-lead API
 * This should be included in all API files after getting the main API response
 */

function saveLead($postData, $getData, $apiResponse, $apiName, $apiResponseStatus = 'success')
{
    // Get user IP
    $userIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (strpos($userIp, ',') !== false) {
        $userIp = trim(explode(',', $userIp)[0]);
    }

    // Get user agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Get current timestamp
    $submittedAt = date('c'); // ISO 8601 format

    // Extract names
    $firstName = $postData['firstname'] ?? '';
    $lastName = $postData['lastname'] ?? '';

    // Prepare lead data
    $leadData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $postData['email'] ?? '',
        'contact' => $postData['phone'] ?? '',
        'api_type' => 'web_form',
        'web_builder_user_id' => $postData['web_builder_user_id'] ? ("U" . $postData['web_builder_user_id']) : 'Unknown',
        'api_payload' => [
            'form_id' => $apiName . '_form',
            'page_url' => BASE_URL,
            'utm_source' => $getData['utm_source'] ?? 'direct',
            'utm_campaign' => $getData['utm_campaign'] ?? 'default_campaign',
            'user_agent' => $userAgent,
            'ip_address' => $userIp,
            'submitted_at' => $submittedAt,
            'browser_info' => [
                'language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en-US',
                'timezone' => 'UTC' // Default timezone
            ],
            'cid' => $getData['cid'] ?? '',
            'pid' => $getData['pid'] ?? '',
            'so' => $getData['so'] ?? ''
        ],
        'api_response' => $apiResponse,
        'api_response_status' => $apiResponseStatus
    ];

    // Send to CRM save-lead API
    $ch = curl_init('https://crm.diy/api/v1/save-lead');

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($leadData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        // Log error but don't stop the main flow
        error_log("Save Lead API Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    // Log the response for debugging (optional)
    if ($response) {
        error_log("Save Lead API Response: " . $response);
    }

    return ($httpCode >= 200 && $httpCode < 300);
}
