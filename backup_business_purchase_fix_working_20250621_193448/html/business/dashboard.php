<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Unified Dashboard - Always redirect to enhanced dashboard (no more legacy options)
header('Location: ' . APP_URL . '/business/dashboard_enhanced.php');
exit;
?> 