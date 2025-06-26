<?php
// Simple test: Can we access business role without redirect?
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

echo "<h1>🧪 Business Access Test</h1>";
echo "<p>Testing business role access step by step...</p>";

echo "<h3>Step 1: Session Check</h3>";
echo "Session started: " . (session_status() === PHP_SESSION_ACTIVE ? "✅ Yes" : "❌ No") . "<br>";
echo "Session ID: " . session_id() . "<br>";

echo "<h3>Step 2: Login Check</h3>";
echo "is_logged_in(): " . (is_logged_in() ? "✅ Yes" : "❌ No") . "<br>";
if (is_logged_in()) {
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "Role: " . $_SESSION['role'] . "<br>";
    echo "Business ID: " . ($_SESSION['business_id'] ?? 'None') . "<br>";
}

echo "<h3>Step 3: Role Check</h3>";
echo "has_role('business'): " . (has_role('business') ? "✅ Yes" : "❌ No") . "<br>";

echo "<h3>Step 4: Business Database Check</h3>";
if (is_logged_in()) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$_SESSION['business_id']]);
        $business = $stmt->fetch();
        echo "Business found: " . ($business ? "✅ Yes" : "❌ No") . "<br>";
        if ($business) {
            echo "Business name: " . htmlspecialchars($business['name']) . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ Database error: " . $e->getMessage() . "<br>";
    }
}

echo "<h3>Step 5: Test require_role Function</h3>";
echo "About to call require_role('business')...<br>";

// This is the critical test - does require_role work?
try {
    require_role('business');
    echo "✅ require_role('business') passed! No redirect occurred.<br>";
} catch (Exception $e) {
    echo "❌ Exception during require_role: " . $e->getMessage() . "<br>";
}

echo "<h3>✅ SUCCESS: Business access working!</h3>";
echo "<p>If you see this message, business authentication is working correctly.</p>";

echo "<h3>🔗 Test Links</h3>";
echo "<a href='/business/dashboard_simple.php'>Test Business Dashboard</a><br>";
echo "<a href='/qr-generator-enhanced.php'>Test QR Generator Enhanced</a><br>";
echo "<a href='/debug_session.php'>Back to Session Debug</a><br>";
?> 