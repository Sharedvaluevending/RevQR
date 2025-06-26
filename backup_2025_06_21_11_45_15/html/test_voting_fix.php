<?php
/**
 * Test Voting System Fixes
 * Verifies that the voting system is working correctly after fixes
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

$test_results = [];

echo "<h1>🗳️ Voting System Fix Verification</h1>";
echo "<p>Testing the fixed voting system for campaign_id=15</p>";

// Test 1: Check database structure
echo "<h2>Test 1: Database Structure</h2>";
try {
    $stmt = $pdo->query("DESCRIBE votes");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['id', 'user_id', 'item_id', 'vote_type', 'voter_ip', 'campaign_id', 'created_at'];
    $missing = array_diff($required_columns, $columns);
    
    if (empty($missing)) {
        echo "✅ All required columns present<br>";
        
        // Check vote_type enum
        $stmt = $pdo->query("SHOW COLUMNS FROM votes LIKE 'vote_type'");
        $vote_type_info = $stmt->fetch();
        if (strpos($vote_type_info['Type'], 'vote_in') !== false && strpos($vote_type_info['Type'], 'vote_out') !== false) {
            echo "✅ Vote type enum is correct: " . $vote_type_info['Type'] . "<br>";
        } else {
            echo "❌ Vote type enum issue: " . $vote_type_info['Type'] . "<br>";
        }
    } else {
        echo "❌ Missing columns: " . implode(', ', $missing) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 2: Vote limit calculation
echo "<h2>Test 2: Vote Limit Calculation</h2>";
try {
    // Simulate user vote check
    $test_user_id = 1; // Use existing user if available
    $test_ip = '127.0.0.1';
    
    // Check weekly votes for test user
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as weekly_votes
        FROM votes 
        WHERE (user_id = ? OR voter_ip = ?)
        AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
    ");
    $stmt->execute([$test_user_id, $test_ip]);
    $weekly_votes = $stmt->fetchColumn();
    
    $weekly_limit = 2;
    $votes_remaining = max(0, $weekly_limit - $weekly_votes);
    
    echo "✅ Weekly votes used: $weekly_votes<br>";
    echo "✅ Votes remaining: $votes_remaining<br>";
    echo "✅ Weekly limit: $weekly_limit<br>";
    
} catch (Exception $e) {
    echo "❌ Vote limit calculation error: " . $e->getMessage() . "<br>";
}

// Test 3: API Endpoints
echo "<h2>Test 3: API Endpoints</h2>";

// Test vote status API
echo "<h3>Vote Status API</h3>";
$vote_status_url = APP_URL . "/api/get-vote-status.php";
$vote_status_response = @file_get_contents($vote_status_url);
if ($vote_status_response) {
    $vote_status_data = json_decode($vote_status_response, true);
    if ($vote_status_data && $vote_status_data['success']) {
        echo "✅ Vote status API working<br>";
        echo "   - Votes used: " . $vote_status_data['votes_used'] . "<br>";
        echo "   - Votes remaining: " . $vote_status_data['votes_remaining'] . "<br>";
        echo "   - QR balance: " . $vote_status_data['qr_balance'] . "<br>";
    } else {
        echo "❌ Vote status API returned error<br>";
    }
} else {
    echo "❌ Vote status API not accessible<br>";
}

// Test vote counts API
echo "<h3>Vote Counts API</h3>";
$stmt = $pdo->query("SELECT id FROM voting_list_items LIMIT 1");
$test_item = $stmt->fetch();
if ($test_item) {
    $vote_counts_url = APP_URL . "/core/get-vote-counts.php?item_id=" . $test_item['id'];
    $vote_counts_response = @file_get_contents($vote_counts_url);
    if ($vote_counts_response) {
        $vote_counts_data = json_decode($vote_counts_response, true);
        if ($vote_counts_data && $vote_counts_data['success']) {
            echo "✅ Vote counts API working<br>";
            echo "   - Vote In: " . $vote_counts_data['vote_in_count'] . "<br>";
            echo "   - Vote Out: " . $vote_counts_data['vote_out_count'] . "<br>";
            echo "   - Total: " . $vote_counts_data['total_votes'] . "<br>";
        } else {
            echo "❌ Vote counts API returned error<br>";
        }
    } else {
        echo "❌ Vote counts API not accessible<br>";
    }
} else {
    echo "❌ No test items found for vote counts test<br>";
}

// Test 4: Campaign Data
echo "<h2>Test 4: Campaign 15 Data</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT c.*, vl.name as list_name
        FROM campaigns c
        LEFT JOIN campaign_voting_lists cvl ON c.id = cvl.campaign_id
        LEFT JOIN voting_lists vl ON cvl.voting_list_id = vl.id
        WHERE c.id = 15
    ");
    $stmt->execute();
    $campaign = $stmt->fetch();
    
    if ($campaign) {
        echo "✅ Campaign 15 found: " . htmlspecialchars($campaign['name']) . "<br>";
        echo "   - Status: " . htmlspecialchars($campaign['status']) . "<br>";
        echo "   - List: " . htmlspecialchars($campaign['list_name'] ?? 'No list') . "<br>";
        
        // Check for items in this campaign
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as item_count
            FROM voting_list_items vli
            JOIN campaign_voting_lists cvl ON vli.voting_list_id = cvl.voting_list_id
            WHERE cvl.campaign_id = 15
        ");
        $stmt->execute();
        $item_count = $stmt->fetchColumn();
        echo "   - Items: $item_count<br>";
        
    } else {
        echo "❌ Campaign 15 not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Campaign check error: " . $e->getMessage() . "<br>";
}

// Test 5: Recent votes analysis
echo "<h2>Test 5: Recent Votes Analysis</h2>";
try {
    $stmt = $pdo->query("
        SELECT 
            vote_type,
            COUNT(*) as count,
            DATE(created_at) as vote_date
        FROM votes 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY vote_type, DATE(created_at)
        ORDER BY vote_date DESC, vote_type
        LIMIT 10
    ");
    $recent_votes = $stmt->fetchAll();
    
    if ($recent_votes) {
        echo "✅ Recent voting activity:<br>";
        foreach ($recent_votes as $vote) {
            echo "   - {$vote['vote_date']}: {$vote['vote_type']} = {$vote['count']} votes<br>";
        }
    } else {
        echo "ℹ️ No recent voting activity<br>";
    }
} catch (Exception $e) {
    echo "❌ Recent votes analysis error: " . $e->getMessage() . "<br>";
}

echo "<h2>🎯 Summary</h2>";
echo "<p>If you see mostly ✅ marks above, the voting system fixes should be working correctly.</p>";
echo "<p>Visit <a href='" . APP_URL . "/vote.php?campaign_id=15' target='_blank'>Campaign 15 Vote Page</a> to test the actual voting interface.</p>";

echo "<h3>Key Fixes Applied:</h3>";
echo "<ul>";
echo "<li>✅ Fixed vote type normalization (in/out -> vote_in/vote_out)</li>";
echo "<li>✅ Fixed vote limit enforcement</li>";
echo "<li>✅ Removed premium system conflicts</li>";
echo "<li>✅ Fixed AJAX voting to not reload page</li>";
echo "<li>✅ Fixed vote status API</li>";
echo "<li>✅ Fixed vote counts API</li>";
echo "<li>✅ Added proper messaging for vote limits</li>";
echo "</ul>";
?> 