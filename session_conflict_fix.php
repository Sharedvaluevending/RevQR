<?php
/**
 * Session Conflict Fix Script
 * 
 * This script identifies and resolves session handling conflicts
 * that are causing authentication failures with balance-sync.js
 */

echo "🔧 SESSION CONFLICT DIAGNOSIS & FIX\n";
echo "=================================\n\n";

// Test 1: Check session configuration
echo "📊 Testing Session Configuration...\n";
echo "-----------------------------------\n";

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Cookie Params: " . json_encode(session_get_cookie_params()) . "\n";
echo "Session Data: " . json_encode($_SESSION) . "\n\n";

// Test 2: Check for conflicting files
echo "🔍 Checking for Conflicting Auth Files...\n";
echo "------------------------------------------\n";

$auth_files = [
    '/var/www/html/core/session.php',
    '/var/www/html/core/session_fixed.php', 
    '/var/www/html/core/auth.php',
    '/var/www/html/core/auth_improved.php',
    '/var/www/html/includes/auth.php'
];

foreach ($auth_files as $file) {
    if (file_exists($file)) {
        echo "✅ Found: " . basename($file) . "\n";
        $content = file_get_contents($file);
        if (strpos($content, 'session_start') !== false) {
            echo "   ⚠️  Contains session_start()\n";
        }
        if (strpos($content, 'user_id') !== false) {
            echo "   ✓ Contains user_id authentication\n";
        }
    } else {
        echo "❌ Missing: " . basename($file) . "\n";
    }
}
echo "\n";

// Test 3: Check balance-check.php endpoint
echo "🌐 Testing Balance Check Endpoint...\n";
echo "-----------------------------------\n";

// Simulate what balance-sync.js does
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Cookie: " . (isset($_COOKIE[session_name()]) ? session_name() . "=" . session_id() : ""),
    ]
]);

$result = @file_get_contents('http://localhost/user/balance-check.php', false, $context);
if ($result) {
    echo "✅ Balance endpoint response: " . $result . "\n";
} else {
    echo "❌ Balance endpoint failed\n";
    echo "Error: " . error_get_last()['message'] . "\n";
}
echo "\n";

// Test 4: Session lifetime check  
echo "⏰ Session Lifetime Check...\n";
echo "----------------------------\n";

$session_lifetime = ini_get('session.gc_maxlifetime');
echo "Session GC Max Lifetime: " . $session_lifetime . " seconds (" . ($session_lifetime/60) . " minutes)\n";

if (isset($_SESSION['created_at'])) {
    $age = time() - $_SESSION['created_at'];
    echo "Current session age: " . $age . " seconds (" . ($age/60) . " minutes)\n";
    if ($age > $session_lifetime) {
        echo "⚠️  Session has exceeded lifetime!\n";
    }
} else {
    $_SESSION['created_at'] = time();
    echo "✓ Set session creation timestamp\n";
}
echo "\n";

// Test 5: Create unified auth check
echo "🔧 Creating Unified Auth Fix...\n";
echo "-------------------------------\n";

// Create a fixed balance-check endpoint
$fixed_balance_check = '<?php
// FIXED Balance Check - Session Conflict Resolution
header("Content-Type: application/json; charset=utf-8");

// Prevent any output before headers
ob_start();

// Start session safely with proper error handling
if (session_status() === PHP_SESSION_NONE) {
    try {
        session_start();
    } catch (Exception $e) {
        error_log("Session start failed: " . $e->getMessage());
        echo json_encode([
            "success" => false,
            "error" => "Session initialization failed",
            "balance" => 0
        ]);
        exit;
    }
}

// Clean any output buffer
ob_clean();

// Enhanced authentication check
$user_id = $_SESSION["user_id"] ?? null;

// Debug logging
error_log("Balance check - User ID: " . ($user_id ?: "null"));
error_log("Balance check - Session data: " . json_encode($_SESSION));

if (!$user_id || empty($user_id)) {
    echo json_encode([
        "success" => false,
        "error" => "User not authenticated",
        "balance" => 0,
        "debug" => [
            "session_id" => session_id(),
            "has_user_id" => isset($_SESSION["user_id"]),
            "user_id_value" => $user_id
        ]
    ]);
    exit;
}

try {
    require_once __DIR__ . "/../core/config.php";
    require_once __DIR__ . "/../core/qr_coin_manager.php";
    
    $balance = QRCoinManager::getBalance($user_id);
    
    echo json_encode([
        "success" => true,
        "balance" => (int) $balance,
        "user_id" => $user_id,
        "timestamp" => time()
    ]);
    
} catch (Exception $e) {
    error_log("Balance check error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "error" => "Server error: " . $e->getMessage(),
        "balance" => 0
    ]);
}
?>';

file_put_contents('/var/www/html/user/balance-check-fixed.php', $fixed_balance_check);
echo "✅ Created fixed balance check endpoint\n";

// Test 6: Backup and replace problematic balance-check.php
echo "\n🔄 Replacing balance-check.php...\n";
echo "-----------------------------------\n";

if (file_exists('/var/www/html/user/balance-check.php')) {
    copy('/var/www/html/user/balance-check.php', '/var/www/html/user/balance-check.php.backup.' . time());
    echo "✅ Backed up original balance-check.php\n";
    
    file_put_contents('/var/www/html/user/balance-check.php', $fixed_balance_check);
    echo "✅ Replaced with fixed version\n";
} else {
    echo "❌ Original balance-check.php not found\n";
}

echo "\n🎉 SESSION CONFLICT FIX COMPLETE!\n";
echo "================================\n";
echo "Solutions applied:\n";
echo "1. ✅ Enhanced session error handling\n";
echo "2. ✅ Improved authentication checks\n";
echo "3. ✅ Added debug logging\n";
echo "4. ✅ Fixed output buffer issues\n";
echo "5. ✅ Created backup of original file\n\n";

echo "📝 NEXT STEPS:\n";
echo "1. Test the balance-sync.js in your browser\n";
echo "2. Check browser console for errors\n";
echo "3. Check server error logs if issues persist\n";
echo "4. If still having issues, check PHP session configuration\n\n";

// Test the fix immediately
echo "🧪 Testing the fix...\n";
echo "---------------------\n";

$test_result = @file_get_contents('http://localhost/user/balance-check.php');
if ($test_result) {
    $decoded = json_decode($test_result, true);
    if ($decoded && $decoded['success']) {
        echo "✅ Fixed endpoint working correctly!\n";
        echo "Response: " . $test_result . "\n";
    } else {
        echo "⚠️  Fixed endpoint returns error: " . $test_result . "\n";
    }
} else {
    echo "❌ Could not test fixed endpoint\n";
}
?> 