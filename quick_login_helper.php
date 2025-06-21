<?php
/**
 * Quick Login Helper - For Testing Authentication
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/session.php';
require_once __DIR__ . '/html/core/auth.php';

echo "🔐 Quick Login Helper\n";
echo "====================\n\n";

// Check current session status
echo "Current Session Status:\n";
echo "- Session ID: " . session_id() . "\n";
echo "- Logged in: " . (is_logged_in() ? "YES" : "NO") . "\n";

if (isset($_SESSION['user_id'])) {
    echo "- User ID: " . $_SESSION['user_id'] . "\n";
    echo "- Role: " . ($_SESSION['role'] ?? 'none') . "\n";
} else {
    echo "- No active session\n";
}

echo "\n";

// Show available users
echo "📋 Available Test Users:\n";
echo "------------------------\n";

try {
    $stmt = $pdo->prepare("SELECT id, username, role FROM users LIMIT 5");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "❌ No users found in database\n";
        
        // Create a test user
        echo "\n🔨 Creating test user...\n";
        $test_password = 'test123';
        $result = create_user('testuser', $test_password, 'user', null);
        
        if ($result) {
            echo "✅ Created test user: testuser / test123\n";
        } else {
            echo "❌ Failed to create test user\n";
        }
    } else {
        foreach ($users as $user) {
            echo "- ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n";

// Quick login function
if (isset($argv[1]) && isset($argv[2])) {
    $username = $argv[1];
    $password = $argv[2];
    
    echo "🚀 Attempting login: $username\n";
    echo "--------------------------------\n";
    
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
        
        echo "✅ Login successful!\n";
        echo "- User ID: " . $auth_result['user_id'] . "\n";
        echo "- Role: " . $auth_result['role'] . "\n";
        echo "- Session ID: " . session_id() . "\n";
        
        // Test balance
        try {
            require_once __DIR__ . '/html/core/qr_coin_manager.php';
            $balance = QRCoinManager::getBalance($auth_result['user_id']);
            echo "- QR Balance: " . $balance . "\n";
        } catch (Exception $e) {
            echo "- Balance error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Login failed - Invalid credentials\n";
    }
} else {
    echo "💡 Usage Examples:\n";
    echo "----------------\n";
    echo "php quick_login_helper.php [username] [password]\n";
    echo "php quick_login_helper.php testuser test123\n";
    echo "php quick_login_helper.php admin admin123\n";
}

echo "\n🧪 Test Commands After Login:\n";
echo "-----------------------------\n";
echo "1. Test balance: php session_test_quick.php\n";
echo "2. Test discounts: php test_fixes_comprehensive.php\n";
echo "3. Visit: http://your-domain/user/balance-check.php\n";
echo "4. Check cards: http://your-domain/business/store.php\n";

?> 