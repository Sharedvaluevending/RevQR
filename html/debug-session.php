<?php
// Session Debug Tool - NO AUTH REQUIRED
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Session & Authentication Debug</h1>";

// Check if session is started
echo "<h2>Session Status</h2>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Not Active") . "</p>";

// Start session to check
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Name:</strong> " . session_name() . "</p>";

// Check session data
echo "<h2>Session Data</h2>";
if (empty($_SESSION)) {
    echo "<p style='color: red;'><strong>No session data found!</strong></p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Key</th><th>Value</th></tr>";
    foreach ($_SESSION as $key => $value) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($key) . "</td>";
        echo "<td>" . htmlspecialchars(is_array($value) ? json_encode($value) : $value) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check login status
echo "<h2>Authentication Status</h2>";
$logged_in = is_logged_in();
echo "<p><strong>Logged In:</strong> " . ($logged_in ? "Yes" : "No") . "</p>";

if (isset($_SESSION['user_id'])) {
    echo "<p><strong>User ID:</strong> " . $_SESSION['user_id'] . "</p>";
    echo "<p><strong>Role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "</p>";
    echo "<p><strong>Login Time:</strong> " . (isset($_SESSION['login_time']) ? date('Y-m-d H:i:s', $_SESSION['login_time']) : 'Not set') . "</p>";
    echo "<p><strong>Last Activity:</strong> " . (isset($_SESSION['last_activity']) ? date('Y-m-d H:i:s', $_SESSION['last_activity']) : 'Not set') . "</p>";
    
    // Check if session is expired
    $last_activity = $_SESSION['last_activity'] ?? 0;
    $session_expired = $last_activity > 0 && (time() - $last_activity) > SESSION_LIFETIME;
    echo "<p><strong>Session Expired:</strong> " . ($session_expired ? "Yes" : "No") . "</p>";
    
    if ($last_activity > 0) {
        $time_since_activity = time() - $last_activity;
        echo "<p><strong>Time Since Last Activity:</strong> " . $time_since_activity . " seconds (" . round($time_since_activity/60, 1) . " minutes)</p>";
        echo "<p><strong>Session Lifetime:</strong> " . SESSION_LIFETIME . " seconds (" . round(SESSION_LIFETIME/3600, 1) . " hours)</p>";
    }
}

// Check cookies
echo "<h2>Cookies</h2>";
if (empty($_COOKIE)) {
    echo "<p style='color: red;'><strong>No cookies found!</strong></p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Cookie Name</th><th>Value</th></tr>";
    foreach ($_COOKIE as $name => $value) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($name) . "</td>";
        echo "<td>" . htmlspecialchars(substr($value, 0, 50)) . (strlen($value) > 50 ? '...' : '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check session configuration
echo "<h2>Session Configuration</h2>";
$session_params = session_get_cookie_params();
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Parameter</th><th>Value</th></tr>";
foreach ($session_params as $param => $value) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($param) . "</td>";
    echo "<td>" . htmlspecialchars(is_bool($value) ? ($value ? 'true' : 'false') : $value) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check server environment
echo "<h2>Server Environment</h2>";
$server_vars = [
    'HTTPS',
    'HTTP_X_FORWARDED_PROTO',
    'HTTP_X_FORWARDED_SSL',
    'SERVER_PORT',
    'HTTP_HOST',
    'REQUEST_URI',
    'HTTP_USER_AGENT'
];

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Variable</th><th>Value</th></tr>";
foreach ($server_vars as $var) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($var) . "</td>";
    echo "<td>" . htmlspecialchars($_SERVER[$var] ?? 'Not set') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test login function
echo "<h2>Test Login</h2>";
if (isset($_POST['test_login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        require_once __DIR__ . '/core/auth.php';
        
        echo "<p>Testing login for username: " . htmlspecialchars($username) . "</p>";
        
        $auth_result = authenticate_user($username, $password);
        
        if ($auth_result) {
            echo "<p style='color: green;'><strong>Authentication successful!</strong></p>";
            echo "<pre>" . print_r($auth_result, true) . "</pre>";
            
            // Set session data
            set_session_data($auth_result['user_id'], $auth_result['role'], [
                'business_id' => $auth_result['business_id']
            ]);
            
            echo "<p><strong>Session data set. <a href='?'>Refresh page</a> to see updated session.</strong></p>";
        } else {
            echo "<p style='color: red;'><strong>Authentication failed!</strong></p>";
        }
    }
}

echo "<form method='post' style='margin-top: 20px;'>";
echo "<h3>Test Login Form</h3>";
echo "<p>Username: <input type='text' name='username' value='" . ($_POST['username'] ?? '') . "'></p>";
echo "<p>Password: <input type='password' name='password'></p>";
echo "<p><input type='submit' name='test_login' value='Test Login'></p>";
echo "</form>";

// Test logout
if (isset($_GET['logout'])) {
    destroy_session();
    echo "<p style='color: orange;'><strong>Session destroyed. <a href='?'>Refresh page</a></strong></p>";
}

echo "<p><a href='?logout=1'>Test Logout</a></p>";

echo "<hr>";
echo "<p><strong>Debug completed at:</strong> " . date('Y-m-d H:i:s') . "</p>";
?> 