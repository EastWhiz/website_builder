<?php
// Base URL for web references
define("BASE_URL", "http://localhost/myAppFolder");

// Cloudflare Turnstile settings are injected during export.
if (!defined('TURNSTILE_ENABLED')) {
    define('TURNSTILE_ENABLED', false);
}
if (!defined('TURNSTILE_SECRET_KEY')) {
    define('TURNSTILE_SECRET_KEY', '');
}

// Ensure session is started for storing cross-page data (e.g. broker redirect URL/config)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
