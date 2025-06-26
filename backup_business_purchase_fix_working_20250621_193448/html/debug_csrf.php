<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/csrf.php';

// Force no-cache headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

echo "<h1>🔍 CSRF Token Debug Tool</h1>";

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate fresh CSRF token
$csrf_token = generate_csrf_token();

echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>🛠️ Session & CSRF Debug Information</h2>";

echo "<h3>Session Information:</h3>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Inactive') . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Name:</strong> " . session_name() . "</p>";
echo "<p><strong>Session Save Path:</strong> " . session_save_path() . "</p>";

echo "<h3>Cookie Configuration:</h3>";
echo "<p><strong>Cookie Secure:</strong> " . (ini_get('session.cookie_secure') ? '✅ Enabled (HTTPS only)' : '❌ Disabled') . "</p>";
echo "<p><strong>Cookie HttpOnly:</strong> " . (ini_get('session.cookie_httponly') ? '✅ Enabled' : '❌ Disabled') . "</p>";
echo "<p><strong>Use Only Cookies:</strong> " . (ini_get('session.use_only_cookies') ? '✅ Enabled' : '❌ Disabled') . "</p>";

echo "<h3>CSRF Token Information:</h3>";
echo "<p><strong>Session CSRF Token:</strong> " . ($_SESSION['csrf_token'] ?? 'Not set') . "</p>";
echo "<p><strong>Generated Token:</strong> " . $csrf_token . "</p>";
echo "<p><strong>Tokens Match:</strong> " . (($_SESSION['csrf_token'] ?? '') === $csrf_token ? '✅ Yes' : '❌ No') . "</p>";

echo "<h3>Request Information:</h3>";
echo "<p><strong>Protocol:</strong> " . (isset($_SERVER['HTTPS']) ? 'HTTPS' : 'HTTP') . "</p>";
echo "<p><strong>Host:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "</p>";
echo "<p><strong>Request URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "</p>";

echo "</div>";

// Test form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>🧪 Form Submission Test Results:</h3>";
    
    $submitted_token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';
    
    echo "<p><strong>Submitted Token:</strong> " . htmlspecialchars($submitted_token) . "</p>";
    echo "<p><strong>Session Token:</strong> " . htmlspecialchars($session_token) . "</p>";
    echo "<p><strong>Tokens Match:</strong> " . ($submitted_token === $session_token ? '✅ Yes' : '❌ No') . "</p>";
    
    if (validate_csrf_token($submitted_token)) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0;'>";
        echo "✅ <strong>CSRF Token Validation: PASSED</strong>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0;'>";
        echo "❌ <strong>CSRF Token Validation: FAILED</strong>";
        echo "</div>";
        
        // Debug why it failed
        if (empty($session_token)) {
            echo "<p>❌ <strong>Reason:</strong> No CSRF token in session</p>";
        } elseif (empty($submitted_token)) {
            echo "<p>❌ <strong>Reason:</strong> No CSRF token submitted</p>";
        } else {
            echo "<p>❌ <strong>Reason:</strong> Token mismatch</p>";
        }
    }
    echo "</div>";
}

echo "<div style='background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>🧪 Test CSRF Token Validation</h3>";
echo "<form method='POST' action=''>";
echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf_token) . "'>";
echo "<button type='submit' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Test CSRF Token</button>";
echo "</form>";
echo "</div>";

// Session data debug
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>📊 Session Data:</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";
echo "</div>";

// POST data debug (if available)
if (!empty($_POST)) {
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>📊 POST Data:</h3>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    echo "</div>";
}

// Cookie debug
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>🍪 Cookies:</h3>";
echo "<pre>" . print_r($_COOKIE, true) . "</pre>";
echo "</div>";

// Solutions section
echo "<div style='background: #d1ecf1; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>🛠️ Troubleshooting Steps:</h3>";
echo "<ol>";
echo "<li><strong>Clear Browser Cache:</strong> Press Ctrl+F5 to force refresh</li>";
echo "<li><strong>Check HTTPS:</strong> Ensure you're using HTTPS (cookie_secure is enabled)</li>";
echo "<li><strong>Clear Session:</strong> <a href='?clear_session=1'>Clear Session</a></li>";
echo "<li><strong>Check Session Directory:</strong> Ensure session save path is writable</li>";
echo "<li><strong>Test Incognito:</strong> Try in a private browser window</li>";
echo "</ol>";
echo "</div>";

// Clear session functionality
if (isset($_GET['clear_session'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>🔗 Quick Actions:</h3>";
echo "<p><a href='login.php'>🔑 Go to Login Page</a></p>";
echo "<p><a href='?clear_session=1'>🗑️ Clear Session & Regenerate</a></p>";
echo "<p><a href='/clear_browser_cache.php'>🔄 Cache Clearing Instructions</a></p>";
echo "</div>";
?> 