<?php
// Temporary access bypass for QR pages
ob_start();

// Force session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create business session
$_SESSION["user_id"] = 1;
$_SESSION["username"] = "temp_business";
$_SESSION["role"] = "business";
$_SESSION["business_id"] = 1;
$_SESSION["login_time"] = time();

echo "<h1>🔑 Temporary Access Granted</h1>";
echo "<p>Business session created temporarily for testing.</p>";
echo "<div style=\"background: #e8f5e8; padding: 15px; margin: 10px 0; border-radius: 5px;\">";
echo "✅ User ID: " . $_SESSION["user_id"] . "<br>";
echo "✅ Role: " . $_SESSION["role"] . "<br>";
echo "✅ Session active until browser closes<br>";
echo "</div>";

echo "<h2>🎯 Test These Pages Now:</h2>";
echo "<a href=\"/qr-generator.php\" style=\"display: block; margin: 10px 0; padding: 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; text-align: center;\">🔗 QR Generator</a>";
echo "<a href=\"/qr_manager.php\" style=\"display: block; margin: 10px 0; padding: 15px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; text-align: center;\">📊 QR Manager</a>";
echo "<a href=\"/qr-test.php\" style=\"display: block; margin: 10px 0; padding: 15px; background: #ffc107; color: black; text-decoration: none; border-radius: 5px; text-align: center;\">🧪 QR Test Page</a>";

echo "<p><small>⚠️ This is for testing only. For production, use proper login.</small></p>";

ob_end_flush();
?>