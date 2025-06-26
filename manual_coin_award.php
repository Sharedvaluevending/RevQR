<?php
// Manual Coin Award Script
// Use this ONLY if you can confirm you won 500 coins but didn't receive them

require_once 'html/core/config.php';
require_once 'html/core/database.php';
require_once 'core/qr_coin_manager.php';

echo "🪙 MANUAL COIN AWARD TOOL\n";
echo "=========================\n\n";

// YOU MUST CHANGE THIS TO YOUR USER ID
$user_id = 5; // Change this to your actual user ID
$amount = 500;
$description = "Manual 500 coin award - Missing jackpot prize";

echo "WARNING: This will award {$amount} coins to User ID {$user_id}\n";
echo "Are you sure this is correct? Type 'YES' to proceed: ";

$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if ($confirmation !== 'YES') {
    echo "❌ Operation cancelled.\n";
    exit;
}

try {
    $pdo = get_db_connection();
    
    // Check current balance
    $current_balance = QRCoinManager::getBalance($user_id);
    echo "Current balance for User {$user_id}: {$current_balance} coins\n";
    
    // Award the coins
    $success = QRCoinManager::addTransaction(
        $user_id,
        'earning',
        'spinning',
        $amount,
        $description,
        ['manual_fix' => true, 'timestamp' => date('Y-m-d H:i:s')]
    );
    
    if ($success) {
        $new_balance = QRCoinManager::getBalance($user_id);
        echo "✅ Successfully awarded {$amount} coins!\n";
        echo "New balance: {$new_balance} coins\n";
        echo "Difference: " . ($new_balance - $current_balance) . " coins\n";
    } else {
        echo "❌ Failed to award coins. Check error logs.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?> 