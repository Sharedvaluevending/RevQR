<?php
require_once 'html/core/config.php';
echo "=== VOTING SYSTEM ANALYSIS ===\n";

// Check key files
$files = [
    'html/vote.php' => 'Main voting page',
    'html/public/vote.php' => 'Public voting page',
    'html/user/vote.php' => 'User voting page',
    'html/api/get-vote-status.php' => 'Vote status API',
    'html/core/get-vote-counts.php' => 'Vote count API',
    'html/core/qr_coin_manager.php' => 'QR Coin Manager'
];

echo "\n1. FILE STRUCTURE:\n";
foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        echo "✅ $desc\n";
    } else {
        echo "❌ $desc (Missing)\n";
    }
}

// Check database
try {
    echo "\n2. DATABASE ANALYSIS:\n";
    $stmt = $pdo->query("SELECT DISTINCT vote_type, COUNT(*) as count FROM votes GROUP BY vote_type");
    $vote_types = $stmt->fetchAll();
    
    echo "Vote types found:\n";
    foreach ($vote_types as $type) {
        $valid = in_array($type['vote_type'], ['vote_in', 'vote_out']) ? '✅' : '❌';
        echo "$valid {$type['vote_type']}: {$type['count']} votes\n";
    }
    
    // Check recent activity
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM votes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)");
    $recent = $stmt->fetchColumn();
    echo "Recent votes (7 days): $recent\n";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n3. MANUAL TESTS NEEDED:\n";
echo "- Test vote submission shows result immediately\n";
echo "- Check coin balance updates after voting\n";
echo "- Verify vote limits work (2 per week)\n";
echo "- Test extra vote purchases (50 coins)\n";
echo "- Check real-time vote count updates\n";

echo "\n4. TEST URLS:\n";
echo "- Main Vote: html/vote.php?campaign_id=1\n";
echo "- Public Vote: html/public/vote.php\n";
echo "- User Vote: html/user/vote.php\n";
echo "- Vote Status API: html/api/get-vote-status.php\n";

echo "\nRun manual tests on these URLs to verify functionality.\n";
?>
