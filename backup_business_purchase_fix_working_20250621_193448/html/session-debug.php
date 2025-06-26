<?php
// Session Debug Page
require_once __DIR__ . "/core/config.php";

// Start output buffering to prevent headers issues
ob_start();

echo "<h1>🔍 Session Debug Information</h1>";

// Check if session can start
if (session_status() === PHP_SESSION_NONE) {
    if (headers_sent($file, $line)) {
        echo "<div style=\"color: red;\">❌ Headers already sent in $file at line $line</div>";
    } else {
        session_start();
        echo "<div style=\"color: green;\">✅ Session started successfully</div>";
    }
} else {
    echo "<div style=\"color: blue;\">ℹ️ Session already active</div>";
}

echo "<h2>Session Information:</h2>";
echo "<pre>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Inactive") . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Save Path: " . session_save_path() . "\n";
echo "</pre>";

echo "<h2>Session Data:</h2>";
echo "<pre>" . print_r($_SESSION ?? [], true) . "</pre>";

echo "<h2>Authentication Check:</h2>";
echo "<pre>";
if (isset($_SESSION["user_id"])) {
    echo "✅ User ID: " . $_SESSION["user_id"] . "\n";
    echo "✅ Username: " . ($_SESSION["username"] ?? "Not set") . "\n";
    echo "✅ Role: " . ($_SESSION["role"] ?? "Not set") . "\n";
    echo "✅ Login Status: Logged in\n";
} else {
    echo "❌ Not logged in\n";
}
echo "</pre>";

echo "<h2>Quick Actions:</h2>";
echo "<a href=\"/login.php\" style=\"margin: 10px; padding: 10px; background: blue; color: white; text-decoration: none;\">Login Page</a>";
echo "<a href=\"/qr-generator.php\" style=\"margin: 10px; padding: 10px; background: green; color: white; text-decoration: none;\">QR Generator</a>";
echo "<a href=\"/qr_manager.php\" style=\"margin: 10px; padding: 10px; background: purple; color: white; text-decoration: none;\">QR Manager</a>";

// Create test session
echo "<h2>Create Test Session:</h2>";
if (isset($_GET[\"create_test\"])) {
    $_SESSION[\"user_id\"] = 1;
    $_SESSION[\"username\"] = \"test_user\";
    $_SESSION[\"role\"] = \"business\";
    echo "<div style=\"color: green; padding: 10px; background: #e8f5e8;\">✅ Test session created!</div>";
    echo "<script>setTimeout(() => location.reload(), 1000);</script>";
} else {
    echo "<a href=\"?create_test=1\" style=\"padding: 10px; background: orange; color: white; text-decoration: none;\">Create Test Session</a>";
}

ob_end_flush();
?>