<?php
/**
 * Web-based Session Test - For testing balance-sync.js authentication
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

// Set content type for JSON if requested
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
}

echo "<h1>🔍 Web Session Test</h1>";
echo "<p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>Session Status</h2>";
echo "<ul>";
echo "<li><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "</li>";
echo "<li><strong>Session ID:</strong> " . session_id() . "</li>";
echo "<li><strong>Logged in:</strong> " . (is_logged_in() ? "YES" : "NO") . "</li>";

if (isset($_SESSION['user_id'])) {
    echo "<li>✅ <strong>User ID:</strong> " . $_SESSION['user_id'] . "</li>";
    echo "<li>✅ <strong>Role:</strong> " . ($_SESSION['role'] ?? 'none') . "</li>";
    echo "<li>✅ <strong>Business ID:</strong> " . ($_SESSION['business_id'] ?? 'none') . "</li>";
    echo "<li>✅ <strong>Last activity:</strong> " . date('Y-m-d H:i:s', $_SESSION['last_activity'] ?? 0) . "</li>";
} else {
    echo "<li>❌ No user_id in session</li>";
}
echo "</ul>";

echo "<h2>Session Data</h2>";
echo "<pre>" . htmlspecialchars(print_r($_SESSION, true)) . "</pre>";

// Test balance if logged in
if (isset($_SESSION['user_id'])) {
    echo "<h2>Balance Test</h2>";
    try {
        require_once __DIR__ . '/core/qr_coin_manager.php';
        $balance = QRCoinManager::getBalance($_SESSION['user_id']);
        echo "<p>✅ <strong>QR Balance:</strong> " . number_format($balance) . " coins</p>";
    } catch (Exception $e) {
        echo "<p>❌ <strong>Balance error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Test the balance-check.php endpoint
    echo "<h2>Balance API Test</h2>";
    echo "<p><a href='/user/balance-check.php' target='_blank'>Test balance-check.php endpoint</a></p>";
    
    // JavaScript test
    echo "<h2>Live Balance Test (JavaScript)</h2>";
    echo "<div id='balance-test'>Testing...</div>";
    echo "<button onclick='testBalance()'>Refresh Balance</button>";
    
    echo "<script>
    async function testBalance() {
        const div = document.getElementById('balance-test');
        div.innerHTML = 'Loading...';
        
        try {
            const response = await fetch('/user/balance-check.php', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                div.innerHTML = '✅ Balance API working! Balance: ' + data.balance + ' coins';
                div.style.color = 'green';
            } else {
                div.innerHTML = '❌ Balance API error: ' + (data.error || 'Unknown error');
                div.style.color = 'red';
            }
        } catch (error) {
            div.innerHTML = '❌ JavaScript error: ' + error.message;
            div.style.color = 'red';
        }
    }
    
    // Auto-test on page load
    testBalance();
    </script>";
    
} else {
    echo "<h2>Login Required</h2>";
    echo "<p>❌ You need to log in first to test the balance functionality.</p>";
    echo "<p><a href='/login.php'>Login here</a></p>";
    
    // Quick login form for testing
    echo "<h3>Quick Test Login</h3>";
    echo "<form method='POST' action='#'>";
    echo "<input type='text' name='username' placeholder='Username (Mike)' value='Mike'>";
    echo "<input type='password' name='password' placeholder='Password (test123)' value='test123'>";
    echo "<input type='submit' value='Login'>";
    echo "</form>";
    
    // Handle login
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        echo "<h4>Login Attempt</h4>";
        
        $auth_result = authenticate_user($username, $password);
        
        if ($auth_result) {
            set_session_data(
                $auth_result['user_id'],
                $auth_result['role'],
                [
                    'username' => $username,
                    'business_id' => $auth_result['business_id']
                ]
            );
            
            echo "<p>✅ Login successful! <a href='#' onclick='location.reload()'>Refresh page</a></p>";
        } else {
            echo "<p>❌ Login failed - Invalid credentials</p>";
        }
    }
}

// Show available testing links
echo "<h2>Testing Links</h2>";
echo "<ul>";
echo "<li><a href='/business/store.php'>Business Store (Cards Layout Test)</a></li>";
echo "<li><a href='/nayax/discount-store.php'>Discount Store (Purchase Test)</a></li>";
echo "<li><a href='/user/balance-check.php'>Direct Balance Check API</a></li>";
echo "<li><a href='?refresh=" . time() . "'>Refresh This Page</a></li>";
echo "</ul>";

?> 