<?php
// Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Security headers — only send if headers not already sent
if (!headers_sent()) {
    header("Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
        "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
        "img-src 'self' data: https:; " .
        "font-src 'self' https://cdnjs.cloudflare.com; " .
        "connect-src 'self' https://api.imagga.com https://sandbox.safaricom.co.ke; " .
        "frame-ancestors 'none';"
    );
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header_remove("X-Powered-By");
}
?>