<?php
require_once 'html/core/config.php';
require_once 'html/core/qr_coin_manager.php';

$qr_manager = new QRCoinManager();

echo "=== USER 4 TRANSACTION HISTORY ===\n";
$stmt = $pdo->prepare("SELECT * FROM qr_coin_transactions WHERE user_id = 4 ORDER BY created_at DESC");
$stmt->execute();
$transactions = $stmt->fetchAll();

$total_earned = 0;
$total_spent = 0;

foreach($transactions as $tx) {
    echo "[{$tx['created_at']}] {$tx['transaction_type']}: {$tx['amount']} | {$tx['description']}\n";
    if($tx['amount'] > 0) {
        $total_earned += $tx['amount'];
    } else {
        $total_spent += abs($tx['amount']);
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total Earned: $total_earned QR Coins\n";
echo "Total Spent: $total_spent QR Coins\n";
echo "Expected Balance: " . ($total_earned - $total_spent) . " QR Coins\n";

$current_balance = $qr_manager->getBalance(4);
echo "Current Balance: $current_balance QR Coins\n";

echo "\n=== SPIN PACK PURCHASES ===\n";
$stmt = $pdo->prepare("
    SELECT usp.*, qsi.item_name, qsi.qr_coin_cost as cost 
    FROM user_qr_store_purchases usp 
    LEFT JOIN qr_store_items qsi ON usp.qr_store_item_id = qsi.id 
    WHERE usp.user_id = 4 
    ORDER BY usp.created_at DESC
");
$stmt->execute();
$purchases = $stmt->fetchAll();

foreach($purchases as $purchase) {
    $item_name = $purchase['item_name'] ?? 'Unknown Item';
    $cost = $purchase['qr_coins_spent'] ?? $purchase['cost'] ?? 0;
    echo "[{$purchase['created_at']}] {$item_name}: {$cost} coins - Status: {$purchase['status']}\n";
}

echo "\n=== AVATAR UNLOCKS ===\n";
$stmt = $pdo->prepare("SELECT * FROM user_avatars WHERE user_id = 4");
$stmt->execute();
$avatars = $stmt->fetchAll();

foreach($avatars as $avatar) {
    echo "Avatar ID: {$avatar['avatar_id']} - Unlocked: {$avatar['unlocked_at']}\n";
}
?> 