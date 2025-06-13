<?php
// TEMPORARY SESSION FIX FOR TESTING
// This script temporarily disables secure cookie requirements

echo "<h1>🔧 Session Fix - Testing Mode</h1>";

// Temporarily override session settings for testing
ini_set('session.cookie_secure', 0); // Disable secure cookie requirement
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Custom session configuration for testing
session_name('REVENUEQR_SESS');
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => false,  // Allow non-HTTPS for testing
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<p>⚠️ <strong>This is a TESTING script that temporarily disables secure cookie requirements.</strong></p>";
echo "<p>Session settings have been modified for testing purposes.</p>";
echo "</div>";

// Manual login form
if (isset($_POST['test_login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        // Include auth system with modified config
        require_once __DIR__ . '/core/config.php';
        require_once __DIR__ . '/core/auth.php';
        
        echo "<p><strong>Testing login with modified session settings...</strong></p>";
        
        $auth_result = authenticate_user($username, $password);
        
        if ($auth_result) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
            echo "<p>✅ <strong>Authentication successful!</strong></p>";
            echo "<pre>" . json_encode($auth_result, JSON_PRETTY_PRINT) . "</pre>";
            
            // Set session data
            $_SESSION['user_id'] = $auth_result['user_id'];
            $_SESSION['role'] = $auth_result['role'];
            $_SESSION['username'] = $username;
            $_SESSION['business_id'] = $auth_result['business_id'];
            
            echo "<p>✅ <strong>Session data set with modified settings!</strong></p>";
            echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
            echo "<p><strong>Cookie Parameters:</strong></p>";
            $params = session_get_cookie_params();
            echo "<pre>" . json_encode($params, JSON_PRETTY_PRINT) . "</pre>";
            
            echo "<h3>🎯 Test Links (with fixed session):</h3>";
            echo "<ul>";
            echo "<li><a href='/qr_manager.php' target='_blank'>QR Manager</a></li>";
            echo "<li><a href='/qr-display.php' target='_blank'>QR Display</a></li>";
            echo "<li><a href='/qr-generator.php' target='_blank'>QR Generator</a></li>";
            echo "</ul>";
            echo "</div>";
            
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
            echo "<p>❌ <strong>Authentication failed!</strong></p>";
            echo "<p>Please check your username and password.</p>";
            echo "</div>";
        }
    }
}

echo "<h2>🔐 Login with Fixed Session Settings</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<form method='POST' style='margin: 10px 0;'>";
echo "<p><strong>Login with modified session settings:</strong></p>";
echo "<input type='text' name='username' placeholder='Username' style='padding: 8px; margin: 5px; width: 200px;' value='sharedvaluevending'><br>";
echo "<input type='password' name='password' placeholder='Password' style='padding: 8px; margin: 5px; width: 200px;'><br>";
echo "<button type='submit' name='test_login' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px;'>Login with Fixed Settings</button>";
echo "</form>";
echo "</div>";

// Show current session status
echo "<h2>📋 Current Session Status</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "</p>";

if (!empty($_SESSION)) {
    echo "<p>✅ <strong>Session Data:</strong></p>";
    echo "<pre style='background: white; padding: 10px; border-radius: 5px;'>";
    foreach ($_SESSION as $key => $value) {
        echo "<strong>{$key}:</strong> " . (is_array($value) ? json_encode($value) : $value) . "\n";
    }
    echo "</pre>";
} else {
    echo "<p>❌ <strong>Session is empty</strong></p>";
}

echo "<p><strong>Cookie Settings (Modified):</strong></p>";
$params = session_get_cookie_params();
echo "<pre style='background: white; padding: 10px; border-radius: 5px;'>";
echo "Secure: " . ($params['secure'] ? 'YES' : 'NO') . " (Modified to NO for testing)\n";
echo "HTTPOnly: " . ($params['httponly'] ? 'YES' : 'NO') . "\n";
echo "Lifetime: " . $params['lifetime'] . " seconds\n";
echo "Path: " . $params['path'] . "\n";
echo "Domain: " . ($params['domain'] ?: '(empty)') . "\n";
echo "</pre>";
echo "</div>";

echo "<h2>🎯 Next Steps</h2>";
echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<p><strong>After successful login with this script:</strong></p>";
echo "<ol>";
echo "<li>Use the test links above to access QR Manager</li>";
echo "<li>If it works, the issue is confirmed to be SSL/secure cookie related</li>";
echo "<li>The permanent fix would be to either:</li>";
echo "<ul>";
echo "<li>Fix SSL certificate issues</li>";
echo "<li>Or modify the session configuration permanently</li>";
echo "</ul>";
echo "</ol>";
echo "</div>";

echo "<h2>⚠️ Important Note</h2>";
echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<p><strong>This is a temporary testing fix.</strong></p>";
echo "<p>For production use, you should:</p>";
echo "<ul>";
echo "<li>Ensure proper SSL certificate is installed</li>";
echo "<li>Use secure cookies for security</li>";
echo "<li>This script is for debugging purposes only</li>";
echo "</ul>";
echo "</div>";

?> 