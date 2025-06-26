<?php
// Minimal QR Manager Test - Step by step
echo "Test 1: Basic PHP execution<br>";

require_once __DIR__ . '/core/config.php';
echo "Test 2: Config loaded<br>";

require_once __DIR__ . '/core/session.php';
echo "Test 3: Session loaded<br>";

require_once __DIR__ . '/core/auth.php';
echo "Test 4: Auth loaded<br>";

echo "Test 5: Checking login status - is_logged_in(): " . (is_logged_in() ? "YES" : "NO") . "<br>";
echo "Test 6: Checking role - has_role('business'): " . (has_role('business') ? "YES" : "NO") . "<br>";

if (!is_logged_in()) {
    echo "FAIL: Not logged in - this would cause redirect<br>";
    exit;
}

if (!has_role('business')) {
    echo "FAIL: Not business role - this would cause redirect<br>";
    exit;
}

echo "Test 7: Authentication passed, loading business utils<br>";
require_once __DIR__ . '/core/business_utils.php';

echo "Test 8: Getting business ID<br>";
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
    echo "Test 9: Business ID retrieved: " . $business_id . "<br>";
} catch (Exception $e) {
    echo "FAIL: Business ID error: " . $e->getMessage() . "<br>";
    exit;
}

echo "Test 10: About to load header<br>";
// Comment out header for now to isolate the issue
// require_once __DIR__ . '/core/includes/header.php';
echo "Test 11: SUCCESS - All components loaded without redirect!<br>";

echo "<h2>Conclusion</h2>";
echo "If you see this, the authentication and business logic work.<br>";
echo "The issue might be in the header file or something that happens after require_role.<br>";
?> 