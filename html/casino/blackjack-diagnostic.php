<?php
// BLACKJACK DIAGNOSTIC - Test version to fix blank page issue
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Blackjack Diagnostic</h1>";
echo "<h2>Testing Dependencies...</h2>";

try {
    // Test 1: Config file
    echo "<p>✅ Testing config.php... ";
    require_once __DIR__ . '/../core/config.php';
    echo "SUCCESS</p>";
    
    // Test 2: Database connection
    echo "<p>✅ Testing database connection... ";
    $test_query = $pdo->query("SELECT 1");
    echo "SUCCESS</p>";
    
    // Test 3: Session
    echo "<p>✅ Testing session... ";
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "SUCCESS (Status: " . session_status() . ")</p>";
    
    // Test 4: User login check
    echo "<p>🔍 Checking user session... ";
    if (!isset($_SESSION['user_id'])) {
        echo "NO USER LOGGED IN - This is likely the issue!</p>";
        echo "<p>🔧 <strong>Fix:</strong> You need to be logged in to access blackjack.</p>";
        echo "<p><a href='../user/login.php'>Login Here</a> or <a href='../user/register.php'>Register</a></p>";
        
        // For testing purposes, let's set a mock user
        echo "<h3>Mock User for Testing:</h3>";
        echo "<form method='post'>";
        echo "<input type='hidden' name='mock_user' value='1'>";
        echo "<button type='submit' class='btn btn-warning'>Use Mock User (Testing Only)</button>";
        echo "</form>";
        
        if (isset($_POST['mock_user'])) {
            $_SESSION['user_id'] = 1; // Use user ID 1 for testing
            echo "<p>✅ Mock user set! <a href='blackjack-diagnostic.php'>Refresh page</a></p>";
        }
    } else {
        $user_id = $_SESSION['user_id'];
        echo "SUCCESS (User ID: $user_id)</p>";
        
        // Test 5: Get user data
        echo "<p>✅ Testing user data... ";
        $stmt = $pdo->prepare("SELECT qr_coins, business_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        
        if ($user_data) {
            echo "SUCCESS (Balance: {$user_data['qr_coins']}, Business: {$user_data['business_id']})</p>";
            
            // Test 6: Try to load the actual blackjack page
            echo "<h2>🎯 All Tests Passed! Loading Blackjack...</h2>";
            echo "<p><a href='blackjack-simple.php' class='btn btn-success'>Try Simple Blackjack</a></p>";
            echo "<p><a href='blackjack.php' class='btn btn-primary'>Try Full Blackjack</a></p>";
            
        } else {
            echo "FAILED - User not found in database</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ ERROR: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>📋 System Info:</h2>";
echo "<ul>";
echo "<li>PHP Version: " . PHP_VERSION . "</li>";
echo "<li>Session ID: " . (session_id() ?: 'None') . "</li>";
echo "<li>Current User: " . ($_SESSION['user_id'] ?? 'Not logged in') . "</li>";
echo "<li>REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "</li>";
echo "<li>HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "</li>";
echo "</ul>";

echo "<h2>🔧 Quick Fixes to Try:</h2>";
echo "<ol>";
echo "<li><strong>Login Issue:</strong> Make sure you're logged in first</li>";
echo "<li><strong>Session Issue:</strong> Clear browser cookies and try again</li>";
echo "<li><strong>Database Issue:</strong> Check if users table exists</li>";
echo "<li><strong>Permission Issue:</strong> Check file permissions</li>";
echo "</ol>";

echo "<h2>🎮 Alternative Access:</h2>";
echo "<p><a href='blackjack-simple.php'>Simple Blackjack (No login required)</a></p>";
echo "<p><a href='../casino/'>Casino Home</a></p>";
echo "<p><a href='../'>Main Site</a></p>";

// Add basic styling
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }";
echo "h1 { color: #d9534f; }";
echo "h2 { color: #5bc0de; }";
echo ".btn { padding: 10px 20px; margin: 5px; text-decoration: none; color: white; border-radius: 5px; display: inline-block; }";
echo ".btn-success { background: #5cb85c; }";
echo ".btn-primary { background: #337ab7; }";
echo ".btn-warning { background: #f0ad4e; }";
echo "</style>";
?> 