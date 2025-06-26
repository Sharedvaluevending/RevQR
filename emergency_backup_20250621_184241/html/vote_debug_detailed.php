<?php
require_once __DIR__ . '/core/config.php';

echo "<h1>Detailed Vote Debug Script</h1>";

// Simulate the exact POST data that would be sent
$_POST = [
    'vote' => 'vote',
    'code' => 'qr_68349acfad24a2.72802277',
    'item_id' => '31',
    'vote_type' => 'in',
    'list_id' => '225',
    'campaign_id' => '7'
];

$_GET['code'] = 'qr_68349acfad24a2.72802277';

echo "<h2>Simulated POST Data:</h2>";
echo "<pre>" . print_r($_POST, true) . "</pre>";

// Simulate the vote submission logic from vote.php
$item_id = (int)$_POST['item_id'];
$vote_type = $_POST['vote_type'];
$list_id = (int)$_POST['list_id'];
$campaign_id = isset($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : null;

echo "<h2>Parsed Variables:</h2>";
echo "<ul>";
echo "<li>item_id: $item_id</li>";
echo "<li>vote_type: $vote_type</li>";
echo "<li>list_id: $list_id</li>";
echo "<li>campaign_id: $campaign_id</li>";
echo "</ul>";

// Test validation
echo "<h2>Testing Validation:</h2>";
$valid = false;
if (isset($_POST['code'])) {
    echo "<p>✅ Code is set: " . $_POST['code'] . "</p>";
    
    $stmt = $pdo->prepare("
        SELECT qr.id, COALESCE(vl.business_id, c.business_id) as business_id
        FROM qr_codes qr
        LEFT JOIN voting_lists vl ON qr.machine_id = vl.id
        LEFT JOIN campaigns c ON qr.campaign_id = c.id
        LEFT JOIN campaign_voting_lists cvl ON c.id = cvl.campaign_id
        WHERE qr.code = ? AND (
            (qr.machine_id IS NOT NULL AND vl.id = ?) OR 
            (qr.campaign_id IS NOT NULL AND c.id = ? AND cvl.voting_list_id = ?)
        )
    ");
    
    echo "<p><strong>Validation Query Parameters:</strong></p>";
    echo "<ul>";
    echo "<li>code: " . $_POST['code'] . "</li>";
    echo "<li>list_id: $list_id</li>";
    echo "<li>campaign_id: $campaign_id</li>";
    echo "<li>list_id (again): $list_id</li>";
    echo "</ul>";
    
    $stmt->execute([$_POST['code'], $list_id, $campaign_id, $list_id]);
    $valid = $stmt->fetch();
    
    echo "<p><strong>Validation Result:</strong></p>";
    echo "<pre>" . print_r($valid, true) . "</pre>";
}

if ($valid) {
    echo "<p>✅ Validation PASSED</p>";
    
    // Check vote limit
    echo "<h2>Testing Vote Limit Check:</h2>";
    $ip_address = '127.0.0.1'; // Simulate IP
    
    $vote_check_query = "SELECT COUNT(*) as vote_count FROM votes WHERE item_id = ? AND (voter_ip = ? OR (user_agent IS NOT NULL AND user_agent = ?)) AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)";
    $vote_check_params = [$item_id, $ip_address, 'debug-script'];
    if ($campaign_id) {
        $vote_check_query .= " AND campaign_id = ?";
        $vote_check_params[] = $campaign_id;
    }
    
    echo "<p><strong>Vote Check Query:</strong> $vote_check_query</p>";
    echo "<p><strong>Parameters:</strong> " . implode(', ', $vote_check_params) . "</p>";
    
    $stmt = $pdo->prepare($vote_check_query);
    $stmt->execute($vote_check_params);
    $weekly_vote_count = $stmt->fetchColumn();
    
    echo "<p><strong>Weekly Vote Count:</strong> $weekly_vote_count</p>";
    
    if ($weekly_vote_count < 2) {
        echo "<p>✅ Vote limit check PASSED</p>";
        
        // Test vote insertion
        echo "<h2>Testing Vote Insertion:</h2>";
        
        $vote_type_db = $vote_type === 'in' ? 'vote_in' : 'vote_out';
        $machine_id_value = $list_id;
        
        echo "<p><strong>Vote Insertion Data:</strong></p>";
        echo "<ul>";
        echo "<li>item_id: $item_id</li>";
        echo "<li>vote_type_db: $vote_type_db</li>";
        echo "<li>voter_ip: $ip_address</li>";
        echo "<li>campaign_id: $campaign_id</li>";
        echo "<li>machine_id_value: $machine_id_value</li>";
        echo "<li>user_agent: debug-script</li>";
        echo "</ul>";
        
        try {
            // Temporarily disable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            
            $insert_query = "INSERT INTO votes (item_id, vote_type, voter_ip, campaign_id, machine_id, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
            $insert_params = [$item_id, $vote_type_db, $ip_address, $campaign_id, $machine_id_value, 'debug-script'];
            $stmt = $pdo->prepare($insert_query);
            $vote_success = $stmt->execute($insert_params);
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            
            if ($vote_success) {
                echo "<p>✅ Vote insertion SUCCESSFUL!</p>";
                
                // Clean up the test vote
                $pdo->prepare("DELETE FROM votes WHERE voter_ip = ? AND user_agent = 'debug-script'")->execute([$ip_address]);
                echo "<p>🧹 Test vote cleaned up</p>";
                
                echo "<h2>🎉 CONCLUSION: Vote submission should work!</h2>";
                echo "<p>The issue might be in the actual form submission or session handling.</p>";
            } else {
                echo "<p>❌ Vote insertion FAILED</p>";
                echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
            }
        } catch (Exception $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            echo "<p>❌ Vote insertion ERROR: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p>❌ Vote limit exceeded: $weekly_vote_count votes this week</p>";
    }
    
} else {
    echo "<p>❌ Validation FAILED - This is the source of 'Invalid QR code, campaign, or list' error</p>";
}

?> 