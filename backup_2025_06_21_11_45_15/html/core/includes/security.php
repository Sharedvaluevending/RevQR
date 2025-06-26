<?php
// Set security headers
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com; " .
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
    "img-src 'self' data: https:; " .
    "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; " .
    "connect-src 'self'; " .
    "frame-ancestors 'none'; " .
    "form-action 'self'; " .
    "base-uri 'self'; " .
    "object-src 'none'; " .
    "worker-src 'self'");

// Prevent clickjacking
header("X-Frame-Options: DENY");

// Enable XSS protection
header("X-XSS-Protection: 1; mode=block");

// Prevent MIME type sniffing
header("X-Content-Type-Options: nosniff");

// Referrer policy
header("Referrer-Policy: strict-origin-when-cross-origin");

// Feature policy
header("Permissions-Policy: geolocation=(), microphone=(), camera=()"); 