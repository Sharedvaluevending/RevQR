<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

// Create a test session for QR generator testing
$_SESSION['user_id'] = 999;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'test_user';
$_SESSION['business_id'] = null;

echo "Test session created. You can now test the QR generator.<br>";
echo "Session data:<br>";
echo "User ID: " . $_SESSION['user_id'] . "<br>";
echo "Role: " . $_SESSION['role'] . "<br>";
echo "Username: " . $_SESSION['username'] . "<br>";
echo "<br>";
echo '<a href="/qr-generator.php">Go to QR Generator</a>';
?> 