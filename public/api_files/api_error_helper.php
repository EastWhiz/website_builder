<?php

/**
 * Build a user-visible error string from common lead-API JSON shapes (LeadGreed, Laravel validation,
 * GetLinked, Trackbox, etc.) for redirect ?api_error=...
 */
if (!function_exists('formatApiErrorForRedirect')) {
    function formatApiErrorForRedirect($responseArray, $httpCode = null, $curlError = '')
    {
        $curlError = trim((string) $curlError);
        if ($curlError !== '') {
            return $curlError;
        }

        if (!is_array($responseArray)) {
            $responseArray = [];
        }

        $lines = [];

        $appendErrorsBlock = function ($block) use (&$lines) {
            if (!is_array($block)) {
                if (is_string($block) && $block !== '') {
                    $lines[] = $block;
                }

                return;
            }
            foreach ($block as $key => $value) {
                if (is_int($key)) {
                    if (is_string($value)) {
                        $lines[] = $value;
                    } elseif (is_array($value)) {
                        if (isset($value['message']) && is_string($value['message'])) {
                            $lines[] = $value['message'];
                        } elseif (isset($value['detail']) && is_string($value['detail'])) {
                            $lines[] = $value['detail'];
                        }
                    }
                } else {
                    if (is_array($value)) {
                        foreach ($value as $msg) {
                            if (is_string($msg)) {
                                // Keep only message text (no key/field prefixes)
                                $lines[] = $msg;
                            }
                        }
                    } elseif (is_string($value)) {
                        // Keep only message text (no key/field prefixes)
                        $lines[] = $value;
                    }
                }
            }
        };

        if (!empty($responseArray['errors'])) {
            $appendErrorsBlock($responseArray['errors']);
        }

        if (!empty($responseArray['body']) && is_array($responseArray['body'])) {
            $body = $responseArray['body'];
            if (!empty($body['errors'])) {
                $appendErrorsBlock($body['errors']);
            }
            if (!empty($body['message']) && is_string($body['message'])) {
                $lines[] = $body['message'];
            }
        }

        foreach (['message', 'detail', 'title', 'description'] as $k) {
            if (!empty($responseArray[$k]) && is_string($responseArray[$k])) {
                $lines[] = $responseArray[$k];
            }
        }

        if (!empty($responseArray['error'])) {
            if (is_string($responseArray['error'])) {
                $lines[] = $responseArray['error'];
            } elseif (is_array($responseArray['error']) && !empty($responseArray['error']['message'])) {
                $lines[] = (string) $responseArray['error']['message'];
            }
        }

        if (!empty($responseArray['data'])) {
            if (is_string($responseArray['data']) && trim($responseArray['data']) !== '') {
                $lines[] = $responseArray['data'];
            }
        }

        $lines = array_values(array_unique(array_filter(array_map('trim', $lines))));
        $text = implode("\n", $lines);

        if ($text !== '') {
            return $text;
        }

        // Last resort: extract any string leaf values from the decoded response.
        // This prevents showing JSON/arrays or custom wrapper text to the user.
        $leafStrings = [];
        $stack = [$responseArray];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (is_string($current)) {
                $leafStrings[] = $current;
                continue;
            }
            if (!is_array($current)) {
                continue;
            }
            foreach ($current as $v) {
                if (is_array($v) || is_string($v)) {
                    $stack[] = $v;
                }
            }
        }

        $leafStrings = array_values(array_unique(array_filter(array_map('trim', $leafStrings))));
        if (!empty($leafStrings)) {
            return implode("\n", $leafStrings);
        }

        return '';
    }
}
