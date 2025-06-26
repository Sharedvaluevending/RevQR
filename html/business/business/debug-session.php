<?php
session_start();
echo "<h1>Session Debug</h1>";
echo "<h2>Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>User Status:</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . "<br>";

// Check if logged in
if (isset($_SESSION['user_id'])) {
    echo "✅ User logged in: " . $_SESSION['user_id'] . "<br>";
    echo "✅ User type: " . ($_SESSION['user_type'] ?? 'not set') . "<br>";
    echo "✅ Business ID: " . ($_SESSION['business_id'] ?? 'not set') . "<br>";
    echo "✅ Role: " . ($_SESSION['role'] ?? 'not set') . "<br>";
} else {
    echo "❌ User NOT logged in<br>";
}

echo "<h2>Test Links:</h2>";
echo '<a href="/business/nayax-settings.php">Test Nayax Settings</a><br>';
echo '<a href="/html/business/nayax-settings.php">Test HTML Nayax Settings</a><br>';
echo '<a href="/login.php">Test Login</a><br>';
?> 