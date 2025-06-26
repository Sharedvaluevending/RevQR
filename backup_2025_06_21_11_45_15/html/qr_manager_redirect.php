<?php
// Simple redirect to working QR gallery
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

// Check if user has business access
if (is_logged_in() && has_role('business')) {
    // Business user - redirect to full manager
    header('Location: qr_manager.php');
    exit();
} else {
    // Regular user or not logged in - redirect to public gallery
    header('Location: qr-display-public.php');
    exit();
}
?> 