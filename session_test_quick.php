<?php
require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/session.php';

echo "🔍 Quick Session Test\n";
echo "===================\n\n";

echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "\n";
echo "Session ID: " . session_id() . "\n";
echo "User logged in: " . (is_logged_in() ? "YES" : "NO") . "\n";

if (isset($_SESSION['user_id'])) {
    echo "✅ User ID in session: " . $_SESSION['user_id'] . "\n";
    echo "✅ User role: " . ($_SESSION['role'] ?? 'none') . "\n";
    echo "✅ Business ID: " . ($_SESSION['business_id'] ?? 'none') . "\n";
    echo "✅ Last activity: " . date('Y-m-d H:i:s', $_SESSION['last_activity'] ?? 0) . "\n";
    
    // Test balance
    try {
        require_once __DIR__ . '/html/core/qr_coin_manager.php';
        $balance = QRCoinManager::getBalance($_SESSION['user_id']);
        echo "✅ QR Balance: " . $balance . "\n";
    } catch (Exception $e) {
        echo "❌ Balance error: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ No user_id in session\n";
    echo "Session data: " . json_encode($_SESSION) . "\n";
    
    // Create a test session for debugging
    echo "\n🧪 Creating test session...\n";
    $_SESSION['user_id'] = 1; // Use user 1 for testing
    $_SESSION['role'] = 'user';
    $_SESSION['last_activity'] = time();
    echo "✅ Test session created\n";
    
    // Test balance with test session
    try {
        require_once __DIR__ . '/html/core/qr_coin_manager.php';
        $balance = QRCoinManager::getBalance($_SESSION['user_id']);
        echo "✅ Test balance: " . $balance . "\n";
    } catch (Exception $e) {
        echo "❌ Test balance error: " . $e->getMessage() . "\n";
    }
}
?> 