<?php
// controller/csrf_helper.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a CSRF token and store it in the session.
 * @return string The generated token.
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify the CSRF token from a request (POST or GET).
 * @return bool True if valid, False otherwise.
 */
function verifyCsrfToken($token = null)
{
    if ($token === null) {
        // Try to get token from POST, then from Headers (for AJAX)
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token)) {
            $headers = getallheaders();
            $token = $headers['X-CSRF-Token'] ?? '';
        }
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}
?>