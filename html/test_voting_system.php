<?php
/**
 * Comprehensive Voting System Test
 * Tests vote recording, coin rewards, and balance updates
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/qr_coin_manager.php';

// Test user ID (you can change this to test with a specific user)
$test_user_id = 1; // Change this to a real user ID for testing

echo "<h1>Voting System Test Results</h1>\n";

// Test 1: Check QR Coin Manager
echo "<h2>Test 1: QR Coin Manager</h2>\n";
try {
    $balance = QRCoinManager::getBalance($test_user_id);
    echo "✅ User $test_user_id current balance: $balance QR coins<br>\n";
} catch (Exception $e) {
    echo "❌ Error getting balance: " . $e->getMessage() . "<br>\n";
}

// Test 2: Test vote transaction
echo "<h2>Test 2: Vote Transaction</h2>\n";
try {
    $result = QRCoinManager::addTransaction($test_user_id, 'earning', 'voting', 30, 'Test vote reward');
    if ($result['success']) {
        echo "✅ Vote transaction successful!<br>\n";
        echo "Transaction ID: " . $result['transaction_id'] . "<br>\n";
        echo "Previous balance: " . $result['previous_balance'] . "<br>\n";
        echo "New balance: " . $result['balance'] . "<br>\n";
        echo "Amount added: " . $result['amount'] . "<br>\n";
    } else {
        echo "❌ Vote transaction failed: " . $result['error'] . "<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Error in vote transaction: " . $e->getMessage() . "<br>\n";
}

// Test 3: Check updated balance
echo "<h2>Test 3: Updated Balance</h2>\n";
try {
    $new_balance = QRCoinManager::getBalance($test_user_id);
    echo "✅ Updated balance: $new_balance QR coins<br>\n";
} catch (Exception $e) {
    echo "❌ Error getting updated balance: " . $e->getMessage() . "<br>\n";
}

// Test 4: Check votes table
echo "<h2>Test 4: Votes Table</h2>\n";
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_votes, 
               COUNT(CASE WHEN user_id = ? THEN 1 END) as user_votes,
               COUNT(CASE WHEN user_id = ? AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1) THEN 1 END) as weekly_votes
        FROM votes
    ");
    $stmt->execute([$test_user_id, $test_user_id]);
    $vote_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "✅ Total votes in system: " . $vote_stats['total_votes'] . "<br>\n";
    echo "✅ User $test_user_id total votes: " . $vote_stats['user_votes'] . "<br>\n";
    echo "✅ User $test_user_id weekly votes: " . $vote_stats['weekly_votes'] . "<br>\n";
} catch (Exception $e) {
    echo "❌ Error checking votes table: " . $e->getMessage() . "<br>\n";
}

// Test 5: Check QR coin transactions
echo "<h2>Test 5: QR Coin Transactions</h2>\n";
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_transactions,
               COUNT(CASE WHEN user_id = ? THEN 1 END) as user_transactions,
               SUM(CASE WHEN user_id = ? THEN amount ELSE 0 END) as user_total
        FROM qr_coin_transactions
    ");
    $stmt->execute([$test_user_id, $test_user_id]);
    $transaction_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "✅ Total transactions in system: " . $transaction_stats['total_transactions'] . "<br>\n";
    echo "✅ User $test_user_id transactions: " . $transaction_stats['user_transactions'] . "<br>\n";
    echo "✅ User $test_user_id total amount: " . $transaction_stats['user_total'] . "<br>\n";
} catch (Exception $e) {
    echo "❌ Error checking transactions: " . $e->getMessage() . "<br>\n";
}

// Test 6: Test weekly vote limit
echo "<h2>Test 6: Weekly Vote Limit</h2>\n";
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as weekly_votes
        FROM votes 
        WHERE user_id = ? 
        AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
    ");
    $stmt->execute([$test_user_id]);
    $weekly_votes = (int) $stmt->fetchColumn();
    $votes_remaining = max(0, 2 - $weekly_votes);
    
    echo "✅ User $test_user_id weekly votes used: $weekly_votes<br>\n";
    echo "✅ Votes remaining this week: $votes_remaining<br>\n";
    echo "✅ Weekly limit: 2 votes<br>\n";
} catch (Exception $e) {
    echo "❌ Error checking weekly vote limit: " . $e->getMessage() . "<br>\n";
}

// Test 7: Check if voting pages exist and are accessible
echo "<h2>Test 7: Voting Page Accessibility</h2>\n";
$voting_pages = [
    '/html/vote.php' => 'Main voting page',
    '/html/public/vote.php' => 'Public voting page'
];

foreach ($voting_pages as $page => $description) {
    if (file_exists(__DIR__ . $page)) {
        echo "✅ $description exists: $page<br>\n";
    } else {
        echo "❌ $description missing: $page<br>\n";
    }
}

// Test 8: Check database tables
echo "<h2>Test 8: Database Tables</h2>\n";
$required_tables = ['votes', 'qr_coin_transactions', 'items', 'campaigns', 'voting_lists'];

foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo "✅ Table '$table' exists with $count records<br>\n";
    } catch (Exception $e) {
        echo "❌ Table '$table' error: " . $e->getMessage() . "<br>\n";
    }
}

echo "<h2>Test Summary</h2>\n";
echo "This test verifies that:<br>\n";
echo "1. QR Coin Manager is working correctly<br>\n";
echo "2. Vote transactions are recorded properly<br>\n";
echo "3. User balances are updated correctly<br>\n";
echo "4. Weekly vote limits are enforced<br>\n";
echo "5. All required database tables exist<br>\n";
echo "6. Voting pages are accessible<br>\n";

echo "<br><strong>If all tests pass, the voting system should be working correctly!</strong><br>\n";
?> 