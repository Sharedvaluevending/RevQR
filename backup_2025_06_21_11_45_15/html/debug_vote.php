<?php
require_once __DIR__ . '/core/config.php';

echo "<h1>Vote Debug Script</h1>";

// Test data
$code = 'qr_68349acfad24a2.72802277';
$item_id = 31;
$vote_type = 'in';
$list_id = 225;
$campaign_id = 7;

echo "<h2>Testing QR Code Validation</h2>";

// Test the validation query
$stmt = $pdo->prepare("
    SELECT qr.id, qr.campaign_id, qr.machine_id, COALESCE(vl.business_id, c.business_id) as business_id
    FROM qr_codes qr
    LEFT JOIN voting_lists vl ON qr.machine_id = vl.id
    LEFT JOIN campaigns c ON qr.campaign_id = c.id
    LEFT JOIN campaign_voting_lists cvl ON c.id = cvl.campaign_id
    WHERE qr.code = ?
");
$stmt->execute([$code]);
$qr_result = $stmt->fetch();

echo "<p><strong>QR Code Data:</strong></p>";
echo "<pre>" . print_r($qr_result, true) . "</pre>";

if ($qr_result) {
    echo "<p>✅ QR Code found</p>";
    
    // Test validation logic
    $validation_stmt = $pdo->prepare("
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
    $validation_stmt->execute([$code, $list_id, $campaign_id, $list_id]);
    $validation_result = $validation_stmt->fetch();
    
    echo "<p><strong>Validation Result:</strong></p>";
    echo "<pre>" . print_r($validation_result, true) . "</pre>";
    
    if ($validation_result) {
        echo "<p>✅ Validation passed</p>";
        
        echo "<h2>Testing Vote Insertion</h2>";
        
        // Test vote insertion
        $vote_type_db = $vote_type === 'in' ? 'vote_in' : 'vote_out';
        $machine_id_value = $list_id; // Use the list_id as machine_id
        $ip_address = '127.0.0.1';
        
        echo "<p><strong>Vote Data:</strong></p>";
        echo "<ul>";
        echo "<li>item_id: $item_id</li>";
        echo "<li>vote_type: $vote_type -> $vote_type_db</li>";
        echo "<li>voter_ip: $ip_address</li>";
        echo "<li>campaign_id: $campaign_id</li>";
        echo "<li>machine_id: $machine_id_value</li>";
        echo "</ul>";
        
        try {
            $insert_query = "INSERT INTO votes (item_id, vote_type, voter_ip, campaign_id, machine_id, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
            $insert_params = [$item_id, $vote_type_db, $ip_address, $campaign_id, $machine_id_value, 'debug-script'];
            $stmt = $pdo->prepare($insert_query);
            
            if ($stmt->execute($insert_params)) {
                echo "<p>✅ Vote inserted successfully!</p>";
                
                // Clean up
                $pdo->prepare("DELETE FROM votes WHERE voter_ip = ? AND user_agent = 'debug-script'")->execute([$ip_address]);
                echo "<p>🧹 Test vote cleaned up</p>";
            } else {
                echo "<p>❌ Vote insertion failed</p>";
                echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
            }
        } catch (Exception $e) {
            echo "<p>❌ Vote insertion error: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p>❌ Validation failed</p>";
    }
} else {
    echo "<p>❌ QR Code not found</p>";
}

echo "<h2>Database Schema Check</h2>";

// Check votes table structure
$stmt = $pdo->query("DESCRIBE votes");
$columns = $stmt->fetchAll();
echo "<p><strong>Votes table structure:</strong></p>";
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "<td>{$col['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

?> 