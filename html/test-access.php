<?php
// TEMPORARY TEST ACCESS - REMOVE IN PRODUCTION
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'business';
$_SESSION['username'] = 'test_user';

echo "✅ Test session created! Now try:<br>";
echo "<a href=\"/qr-generator.php\">QR Generator</a><br>";
echo "<a href=\"/qr-test.php\">QR Test Page</a><br>";
?>