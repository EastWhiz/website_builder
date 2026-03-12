<?php
// Base URL for web references
define("BASE_URL", "http://localhost/myAppFolder");

// Ensure session is started for storing cross-page data (e.g. broker redirect URL/config)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
