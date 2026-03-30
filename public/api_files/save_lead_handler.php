<?php

/**
 * Reusable function to send lead data to CRM save-lead API
 * This should be included in all API files after getting the main API response
 */

function saveLead($postData, $getData, $apiResponse, $apiName, $apiResponseStatus = 'success', $data = null)
{
    // Helper function to get value or empty string
    function getValInside($arr, $key)
    {
        return isset($arr[$key]) ? $arr[$key] : '';
    }

    // Get request IP fallback
    $requestIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (strpos($requestIp, ',') !== false) {
        $requestIp = trim(explode(',', $requestIp)[0]);
    }

    // Extract names
    $firstName = $postData['firstname'] ?? '';
    $lastName = $postData['lastname'] ?? '';

    // Normalize IP from provider payloads: userip (trackbox), _ip (leadgreed), ip (irev/getlinked)
    $leadIp = '';
    if (is_array($data)) {
        $leadIp = $data['userip'] ?? $data['_ip'] ?? $data['ip'] ?? '';
    }
    if ($leadIp === '') {
        $leadIp = $postData['userip'] ?? '';
    }
    if ($leadIp === '') {
        $leadIp = $requestIp;
    }

    // Normalize country from provider payloads / POST
    $leadCountry = '';
    if (is_array($data)) {
        $leadCountry = $data['country'] ?? $data['country_code'] ?? '';
    }
    if ($leadCountry === '') {
        $leadCountry = $postData['country'] ?? '';
    }

    $apiPayload = is_array($data) ? $data : [];
    $apiPayload['aweber_form'] = [
        'use_aweber' => $postData['use_aweber'] ?? '',
        'aweber_user_api_instance_id' => $postData['aweber_user_api_instance_id'] ?? '',
        'aweber_list_ids' => $postData['aweber_list_ids'] ?? '',
    ];

    // Prepare lead data
    $leadData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $postData['email'] ?? '',
        'contact' => $postData['phone'] ?? '',
        'api_type' => $apiName,
        'web_builder_user_id' => $postData['web_builder_user_id'] ? ("U" . $postData['web_builder_user_id']) : 'Unknown',
        'sales_page_id' => $postData['sales_page_id'] ? $postData['sales_page_id'] : 'Unknown',
        'api_payload' => $apiPayload,
        'api_response' => $apiResponse,
        'api_response_status' => $apiResponseStatus,
        'is_self_hosted' => (isset($postData['is_self_hosted']) && $postData['is_self_hosted'] == "true") ? true : false,
        'so' => getValInside($getData, 'so') ?? '',
        'cid' => getValInside($getData, 'cid') ?? '',
        'ip_address' => $leadIp,
        'country' => $leadCountry,
    ];

    // Send to CRM save-lead API
    $ch = curl_init('https://crm.diy/api/v1/save-lead');

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($leadData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    // SSL verification is baked into exported zips by Builder.
    // Default is enabled; AngleTemplateController replaces these flags during export.
    $verifySsl = true; // __CRM_VERIFY_SSL__
    if (!$verifySsl) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
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
