<?php

/**
 * Reusable function to send lead data to CRM save-lead API
 * This should be included in all API files after getting the main API response
 */

function saveLead($postData, $getData, $apiResponse, $apiName, $apiResponseStatus = 'success', $data = null)
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
        'api_type' => $apiName,
        'web_builder_user_id' => $postData['web_builder_user_id'] ? ("U" . $postData['web_builder_user_id']) : 'Unknown',
        'sales_page_id' => $postData['sales_page_id'] ? $postData['sales_page_id'] : 'Unknown',
        'api_payload' => $data ?? [],
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
