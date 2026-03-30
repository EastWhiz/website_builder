<?php

/**
 * POST form data to local aweber.php (JSON body).
 *
 * @return array Decoded JSON response from aweber.php
 */
function aweberHttpPostToLocalAweberPhp(array $postData): array
{
    $copy = $postData;
    unset($copy['form_type'], $copy['web_builder_user_id'], $copy['project_directory'], $copy['sales_page_id']);

    if (!defined('BASE_URL')) {
        return ['status' => false, 'message' => 'BASE_URL is not defined'];
    }

    $aweberUrl = rtrim((string) BASE_URL, '/') . '/api_files/aweber.php';
    $ch = curl_init($aweberUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($copy));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['status' => false, 'message' => 'AWeber API Error: ' . $err];
    }
    curl_close($ch);

    $decoded = json_decode($response, true);

    return is_array($decoded) ? $decoded : ['status' => false, 'message' => 'Invalid response from AWeber API'];
}

/**
 * Optional AWeber signup alongside the main platform API.
 *
 * - New exports: hidden input use_aweber=yes|no. Only yes triggers AWeber; aweber_list_ids required when yes.
 * - Old exports: no use_aweber field → same behavior as before (always attempt AWeber; lists from country logic in aweber.php).
 */
function sendToAweberIfEnabled(array $postData): ?array
{
    if (!array_key_exists('use_aweber', $postData)) {
        return aweberHttpPostToLocalAweberPhp($postData);
    }

    if (strtolower(trim((string) $postData['use_aweber'])) !== 'yes') {
        return null;
    }

    $lists = trim((string) ($postData['aweber_list_ids'] ?? ''));
    if ($lists === '') {
        return ['status' => false, 'message' => 'AWeber is enabled but no list IDs were submitted.'];
    }

    return aweberHttpPostToLocalAweberPhp($postData);
}
