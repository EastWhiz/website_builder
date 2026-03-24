<?php

/**
 * Extract a readable API error message from a decoded JSON response.
 * This is used by multiple api_files/* scripts that redirect back with `api_error=...`.
 */
function extractApiErrorMessage($responseArray)
{
    if (!is_array($responseArray)) {
        return '';
    }

    $priorityKeys = [
        'message',
        'error',
        'detail',
        'details',
        'reason',
        'description',
        'status_message',
    ];

    foreach ($priorityKeys as $key) {
        if (isset($responseArray[$key]) && is_string($responseArray[$key]) && trim($responseArray[$key]) !== '') {
            return trim($responseArray[$key]);
        }
    }

    // Common "errors" container formats
    if (isset($responseArray['errors'])) {
        $errors = $responseArray['errors'];
        if (is_string($errors) && trim($errors) !== '') {
            return trim($errors);
        }
        if (is_array($errors)) {
            foreach ($errors as $err) {
                if (is_string($err) && trim($err) !== '') {
                    return trim($err);
                }
                if (is_array($err)) {
                    foreach (['message', 'error', 'detail', 'details', 'reason'] as $k) {
                        if (isset($err[$k]) && is_string($err[$k]) && trim($err[$k]) !== '') {
                            return trim($err[$k]);
                        }
                    }
                }
            }
        }
    }

    // Direct nested common keys
    foreach (['body', 'data', 'response', 'meta'] as $containerKey) {
        if (isset($responseArray[$containerKey]) && is_array($responseArray[$containerKey])) {
            $msg = extractApiErrorMessage($responseArray[$containerKey]);
            if ($msg !== '') {
                return $msg;
            }
        }
    }

    // Fallback: recursive scan for message-like keys (bounded depth)
    $scan = function ($value, $depth) use (&$scan) {
        if ($depth > 6) {
            return '';
        }
        if (!is_array($value)) {
            return '';
        }

        $messageKeys = ['message', 'error', 'detail', 'details', 'reason', 'description'];
        foreach ($value as $k => $v) {
            if (in_array($k, $messageKeys, true) && is_string($v) && trim($v) !== '') {
                return trim($v);
            }
            if (is_array($v)) {
                $found = $scan($v, $depth + 1);
                if ($found !== '') {
                    return $found;
                }
            }
        }
        return '';
    };

    $found = $scan($responseArray, 0);
    return $found;
}

if (!function_exists('findBrokerRedirectUrl')) {
    /**
     * Extract a broker redirect URL from common API response shapes (LeadGreed, Trackbox, iRev, etc.).
     */
    function findBrokerRedirectUrl(array $response)
    {
        $candidates = [];

        foreach (['broker_url', 'brokerUrl', 'redirect_url', 'redirectUrl', 'url'] as $key) {
            if (isset($response[$key]) && is_string($response[$key])) {
                $candidates[] = $response[$key];
            }
        }

        if (isset($response['body']['extras']['redirect']['url'])) {
            $candidates[] = $response['body']['extras']['redirect']['url'];
        }

        if (isset($response['body']['data']) && is_string($response['body']['data'])) {
            $candidates[] = $response['body']['data'];
        }
        if (isset($response['body']['addonData']['data']['loginURL'])) {
            $candidates[] = $response['body']['addonData']['data']['loginURL'];
        }
        if (isset($response['body']['addonData']['data']['brokerUrl'])) {
            $candidates[] = $response['body']['addonData']['data']['brokerUrl'];
        }

        if (isset($response['details']['redirect']['url'])) {
            $candidates[] = $response['details']['redirect']['url'];
        }
        if (isset($response['body']['details']['redirect']['url'])) {
            $candidates[] = $response['body']['details']['redirect']['url'];
        }

        if (isset($response['body']['auto_login_url'])) {
            $candidates[] = $response['body']['auto_login_url'];
        }

        if (isset($response['body']) && is_string($response['body'])) {
            $candidates[] = $response['body'];
        }

        foreach ($candidates as $url) {
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        $stack = [$response];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (!is_array($current)) {
                continue;
            }
            foreach ($current as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                } elseif (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                    return $value;
                }
            }
        }

        return null;
    }
}
