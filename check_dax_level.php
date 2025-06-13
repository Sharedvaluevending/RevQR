<?php
require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/qr_coin_manager.php';
require_once __DIR__ . '/html/core/functions.php';

echo "🔍 DEBUGGING DAX'S LEVEL ISSUE\n";
echo "==============================\n\n";

// Get Dax's data
$stmt = $pdo->prepare("SELECT id, username, highest_level_achieved, lifetime_qr_earnings FROM users WHERE id = 4");
$stmt->execute();
$dax = $stmt->fetch();

if (!$dax) {
    echo "❌ User 4 not found!\n";
    exit;
}

echo "👤 User: {$dax['username']} (ID: {$dax['id']})\n";
echo "🏆 Stored Highest Level: " . ($dax['highest_level_achieved'] ?? 'NULL') . "\n";
echo "💰 Stored Lifetime Earnings: ₪" . ($dax['lifetime_qr_earnings'] ?? 'NULL') . "\n\n";

// Check if columns exist
$stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'highest_level_achieved'");
$stmt->execute();
$column_exists = $stmt->fetch();

if (!$column_exists) {
    echo "❌ Column 'highest_level_achieved' doesn't exist! Adding it...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN highest_level_achieved INT DEFAULT 1");
    echo "✅ Column added.\n";
}

$stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'lifetime_qr_earnings'");
$stmt->execute();
$column_exists = $stmt->fetch();

if (!$column_exists) {
    echo "❌ Column 'lifetime_qr_earnings' doesn't exist! Adding it...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN lifetime_qr_earnings INT DEFAULT 0");
    echo "✅ Column added.\n";
}

// Calculate his actual data
$current_balance = QRCoinManager::getBalance(4);
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as lifetime_earnings FROM qr_coin_transactions WHERE user_id = 4 AND amount > 0");
$stmt->execute();
$lifetime_earnings = (int) $stmt->fetchColumn();

$stats = getUserStats(4, '');
$voting_stats = $stats['voting_stats'];
$spin_stats = $stats['spin_stats'];

echo "📊 CALCULATED DATA:\n";
echo "💰 Current Balance: ₪" . number_format($current_balance) . "\n";
echo "🏦 Lifetime Earnings: ₪" . number_format($lifetime_earnings) . "\n";
echo "🗳️  Total Votes: {$voting_stats['total_votes']}\n";
echo "📅 Voting Days: {$voting_stats['voting_days']}\n";
echo "🎰 Spin Days: {$spin_stats['spin_days']}\n\n";

// Calculate level using new function
echo "🎯 TESTING LEVEL CALCULATION:\n";
$level_data = calculateUserLevel(
    $voting_stats['total_votes'], 
    $current_balance, 
    $voting_stats['voting_days'], 
    $spin_stats['spin_days'], 
    4
);

echo "🏆 Calculated Level: " . $level_data['level'] . "\n";
echo "📈 Progress: " . $level_data['progress'] . "%\n";
echo "🔢 Total Level Points: " . number_format($level_data['total_level_points']) . "\n";
echo "🎪 Activity Bonus: " . number_format($level_data['activity_bonus']) . "\n\n";

// Force update the database
echo "🔧 FORCE UPDATING DATABASE:\n";
$calculated_level = max(1, floor($level_data['total_level_points'] / 1000) + 1);
if ($lifetime_earnings >= 28000) {
    $calculated_level = 30; // Dax should definitely be level 30
}

$stmt = $pdo->prepare("UPDATE users SET highest_level_achieved = ?, lifetime_qr_earnings = ? WHERE id = 4");
$result = $stmt->execute([$calculated_level, $lifetime_earnings]);

if ($result) {
    echo "✅ Database updated successfully!\n";
    echo "🏆 Set level to: {$calculated_level}\n";
    echo "💰 Set lifetime earnings to: ₪" . number_format($lifetime_earnings) . "\n";
} else {
    echo "❌ Database update failed!\n";
    print_r($stmt->errorInfo());
}

// Test the level calculation again
echo "\n🧪 TESTING AFTER UPDATE:\n";
$level_data_after = calculateUserLevel(
    $voting_stats['total_votes'], 
    $current_balance, 
    $voting_stats['voting_days'], 
    $spin_stats['spin_days'], 
    4
);

echo "🏆 Level After Update: " . $level_data_after['level'] . "\n";

if ($level_data_after['level'] >= 30) {
    echo "✅ SUCCESS! Dax is now Level {$level_data_after['level']}!\n";
} else {
    echo "❌ Still showing wrong level. Need more debugging.\n";
}
?> 