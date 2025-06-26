<?php
require_once __DIR__ . '/core/session.php';

// Destroy the session
destroy_session();

// Redirect to login page
header('Location: ' . APP_URL . '/login.php');
exit();
?> 