<?php
// ============================================================
// UMS SECURITY HEADERS
// ============================================================

// ---- 1. Session Cookie Security ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,                // Session cookie — expires when browser closes
        'path'     => '/',
        'domain'   => 'localhost',
        'secure'   => false,            // Set to true when on HTTPS
        'httponly' => true,             // Prevents JavaScript access to session cookie
        'samesite' => 'Strict'          // Prevents CSRF attacks
    ]);
    session_start();
}

// ---- 2. Content Security Policy ----
// Prevents XSS by controlling which resources can load
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
    "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
    "img-src 'self' data: https:; " .
    "font-src 'self' https://cdnjs.cloudflare.com; " .
    "connect-src 'self' https://api.imagga.com https://sandbox.safaricom.co.ke; " .
    "frame-ancestors 'none';"
);

// ---- 3. Prevent Clickjacking ----
header("X-Frame-Options: DENY");

// ---- 4. Prevent MIME Sniffing ----
header("X-Content-Type-Options: nosniff");

// ---- 5. XSS Protection (older browsers) ----
header("X-XSS-Protection: 1; mode=block");

// ---- 6. Referrer Policy ----
header("Referrer-Policy: strict-origin-when-cross-origin");

// ---- 7. Permissions Policy ----
// Controls which browser features can be used
header("Permissions-Policy: " .
    "camera=(self), " .         // Allow camera only on our site (for photo capture)
    "microphone=(), " .         // Block microphone
    "geolocation=(), " .        // Block location
    "payment=()"                // Block payment APIs (we use M-Pesa directly)
);

// ---- 8. Remove PHP version header ----
header_remove("X-Powered-By");
?>
