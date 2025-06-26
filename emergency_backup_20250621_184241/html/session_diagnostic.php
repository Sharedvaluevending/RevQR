<?php
require_once __DIR__ . '/core/config.php';

echo "<h1>🔍 Session Diagnostic Tool</h1>";

// Check environment
echo "<h2>🌐 Environment Check</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<p><strong>Current URL:</strong> " . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p><strong>HTTPS:</strong> " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '✅ YES' : '❌ NO') . "</p>";
echo "<p><strong>Request Scheme:</strong> " . $_SERVER['REQUEST_SCHEME'] . "</p>";
echo "<p><strong>Server Port:</strong> " . $_SERVER['SERVER_PORT'] . "</p>";
echo "<p><strong>Domain:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";
echo "</div>";

// Session configuration check
echo "<h2>⚙️ Session Configuration</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<p><strong>Session Name:</strong> " . session_name() . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . session_status() . " (1=disabled, 2=active)</p>";
echo "<p><strong>Session Cookie Secure:</strong> " . ini_get('session.cookie_secure') . " (1=secure required, 0=not required)</p>";
echo "<p><strong>Session Cookie HTTPOnly:</strong> " . ini_get('session.cookie_httponly') . "</p>";
echo "<p><strong>Session Use Only Cookies:</strong> " . ini_get('session.use_only_cookies') . "</p>";
echo "<p><strong>Session Cookie Lifetime:</strong> " . ini_get('session.cookie_lifetime') . " seconds</p>";

$cookie_params = session_get_cookie_params();
echo "<p><strong>Cookie Path:</strong> " . $cookie_params['path'] . "</p>";
echo "<p><strong>Cookie Domain:</strong> " . $cookie_params['domain'] . "</p>";
echo "<p><strong>Cookie Secure:</strong> " . ($cookie_params['secure'] ? 'YES' : 'NO') . "</p>";
echo "<p><strong>Cookie HTTPOnly:</strong> " . ($cookie_params['httponly'] ? 'YES' : 'NO') . "</p>";
echo "</div>";

// Session data check
echo "<h2>📋 Current Session Data</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
if (empty($_SESSION)) {
    echo "<p>❌ <strong>Session is EMPTY</strong></p>";
    echo "<p>This explains why you're being redirected to login!</p>";
} else {
    echo "<p>✅ <strong>Session contains data:</strong></p>";
    echo "<pre style='background: white; padding: 10px; border-radius: 5px;'>";
    foreach ($_SESSION as $key => $value) {
        echo "<strong>{$key}:</strong> " . (is_array($value) ? json_encode($value) : $value) . "\n";
    }
    echo "</pre>";
}
echo "</div>";

// Cookie check
echo "<h2>🍪 Cookie Analysis</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<p><strong>All Cookies:</strong></p>";
if (empty($_COOKIE)) {
    echo "<p>❌ No cookies found</p>";
} else {
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #dee2e6;'>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Cookie Name</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Value</th>";
    echo "</tr>";
    foreach ($_COOKIE as $name => $value) {
        echo "<tr>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$name}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd; font-family: monospace; font-size: 12px;'>" . substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$session_cookie_name = session_name();
if (isset($_COOKIE[$session_cookie_name])) {
    echo "<p>✅ <strong>Session cookie '{$session_cookie_name}' found</strong></p>";
} else {
    echo "<p>❌ <strong>Session cookie '{$session_cookie_name}' NOT found</strong></p>";
    echo "<p>This is likely the root cause of the login redirect issue!</p>";
}
echo "</div>";

// Manual authentication test
echo "<h2>🔐 Manual Authentication Test</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";

if (isset($_POST['test_login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        require_once __DIR__ . '/core/auth.php';
        
        echo "<p><strong>Testing login with:</strong> {$username}</p>";
        
        $auth_result = authenticate_user($username, $password);
        
        if ($auth_result) {
            echo "<p>✅ <strong>Authentication successful!</strong></p>";
            echo "<pre>" . json_encode($auth_result, JSON_PRETTY_PRINT) . "</pre>";
            
            // Set session data
            $_SESSION['user_id'] = $auth_result['user_id'];
            $_SESSION['role'] = $auth_result['role'];
            $_SESSION['username'] = $username;
            $_SESSION['business_id'] = $auth_result['business_id'];
            
            echo "<p>✅ <strong>Session data set successfully!</strong></p>";
            echo "<p><a href='/qr_manager.php' target='_blank'>Test QR Manager Now</a></p>";
            
        } else {
            echo "<p>❌ <strong>Authentication failed!</strong></p>";
        }
    }
}

echo "<form method='POST' style='margin: 10px 0;'>";
echo "<p><strong>Test login manually:</strong></p>";
echo "<input type='text' name='username' placeholder='Username' style='padding: 8px; margin: 5px;' value='sharedvaluevending'><br>";
echo "<input type='password' name='password' placeholder='Password' style='padding: 8px; margin: 5px;'><br>";
echo "<button type='submit' name='test_login' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Test Login</button>";
echo "</form>";
echo "</div>";

// Potential fixes
echo "<h2>🛠️ Potential Fixes</h2>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h3>Based on the diagnostics above, try these solutions:</h3>";

echo "<h4>1. Session Cookie Security Issue</h4>";
echo "<p>If you're accessing the site without HTTPS or with SSL issues:</p>";
echo "<ul>";
echo "<li>Check if you're using HTTPS: https://revenueqr.sharedvaluevending.com/</li>";
echo "<li>Verify SSL certificate is valid</li>";
echo "<li>Try accessing in incognito/private browsing mode</li>";
echo "</ul>";

echo "<h4>2. Session Configuration Fix</h4>";
echo "<p>If the session cookie is not being set, the server config may need adjustment.</p>";

echo "<h4>3. Browser Issues</h4>";
echo "<p>Clear all cookies and site data for revenueqr.sharedvaluevending.com</p>";

echo "<h4>4. Manual Login</h4>";
echo "<p>Use the manual login form above to set session data directly</p>";

echo "</div>";

echo "<h2>🔗 Quick Navigation</h2>";
echo "<div style='background: #cce5ff; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<ul>";
echo "<li><a href='/login.php'>Regular Login Page</a></li>";
echo "<li><a href='/qr_manager.php' target='_blank'>QR Manager (test after login)</a></li>";
echo "<li><a href='/qr_manager_debug.php'>QR Manager Debug</a></li>";
echo "<li><a href='/test_qr_system_fix.php'>QR System Test</a></li>";
echo "</ul>";
echo "</div>";

?> 