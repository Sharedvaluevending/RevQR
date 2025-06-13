<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

echo "<h1>Login Debug Test</h1>";

// Test authentication directly
$username = 'sharedvaluevending';
$password = 'password';

echo "<h2>Testing Authentication:</h2>";
echo "Username: " . $username . "<br>";
echo "Password: " . $password . "<br>";

$auth_result = authenticate_user($username, $password);

if ($auth_result) {
    echo "<div style='color: green;'>✅ Authentication SUCCESSFUL</div>";
    echo "User ID: " . $auth_result['user_id'] . "<br>";
    echo "Role: " . $auth_result['role'] . "<br>";
    echo "Business ID: " . $auth_result['business_id'] . "<br>";
    
    // Set session data
    set_session_data(
        $auth_result['user_id'],
        $auth_result['role'],
        [
            'username' => $username,
            'business_id' => $auth_result['business_id']
        ]
    );
    
    echo "<h2>Session Data Set:</h2>";
    echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
    echo "Session role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";
    echo "Session business_id: " . ($_SESSION['business_id'] ?? 'NOT SET') . "<br>";
    echo "Session username: " . ($_SESSION['username'] ?? 'NOT SET') . "<br>";
    
    echo "<h2>Test Login Status:</h2>";
    echo "is_logged_in(): " . (is_logged_in() ? 'TRUE' : 'FALSE') . "<br>";
    echo "is_business_user(): " . (is_business_user() ? 'TRUE' : 'FALSE') . "<br>";
    
    echo "<h2>Quick Links:</h2>";
    echo "<a href='/business/dashboard.php'>Go to Business Dashboard</a><br>";
    echo "<a href='/business/cross_references_details.php'>Go to Cross References Details</a><br>";
    
} else {
    echo "<div style='color: red;'>❌ Authentication FAILED</div>";
}

echo "<h2>Database Check:</h2>";
$stmt = $pdo->prepare("SELECT username, role, business_id FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();
if ($user) {
    echo "User found in database:<br>";
    echo "Username: " . $user['username'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    echo "Business ID: " . $user['business_id'] . "<br>";
} else {
    echo "❌ User NOT found in database<br>";
}
?> 