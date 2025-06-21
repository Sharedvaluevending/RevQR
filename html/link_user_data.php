<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/functions.php';

// Require user role
require_role('user');

$user_id = $_SESSION['user_id'];
$current_ip = get_client_ip();

echo "<h1>Link Historical Data for User ID: $user_id</h1>";
echo "<h3>Current IP: $current_ip</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Get all IPs that have been used by this user_id
        $stmt = $pdo->prepare("
            SELECT DISTINCT voter_ip 
            FROM votes 
            WHERE user_id = ? AND voter_ip IS NOT NULL
            UNION
            SELECT DISTINCT user_ip as voter_ip
            FROM spin_results 
            WHERE user_id = ? AND user_ip IS NOT NULL
        ");
        $stmt->execute([$user_id, $user_id]);
        $known_ips = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Add current IP to the list
        if (!in_array($current_ip, $known_ips)) {
            $known_ips[] = $current_ip;
        }
        
        echo "<h3>Linking data from these IPs:</h3>";
        echo "<ul>";
        foreach ($known_ips as $ip) {
            echo "<li>$ip</li>";
        }
        echo "</ul>";
        
        $total_votes_linked = 0;
        $total_spins_linked = 0;
        
        // Link votes from all known IPs to this user account
        foreach ($known_ips as $ip) {
            $stmt = $pdo->prepare("
                UPDATE votes 
                SET user_id = ? 
                WHERE voter_ip = ? AND user_id IS NULL
            ");
            $stmt->execute([$user_id, $ip]);
            $votes_linked = $stmt->rowCount();
            $total_votes_linked += $votes_linked;
            
            // Link spin results from all known IPs to this user account  
            $stmt = $pdo->prepare("
                UPDATE spin_results 
                SET user_id = ? 
                WHERE user_ip = ? AND user_id IS NULL
            ");
            $stmt->execute([$user_id, $ip]);
            $spins_linked = $stmt->rowCount();
            $total_spins_linked += $spins_linked;
            
            if ($votes_linked > 0 || $spins_linked > 0) {
                echo "<p>IP $ip: Linked $votes_linked votes and $spins_linked spins</p>";
            }
        }
        
        $pdo->commit();
        
        echo "<div class='alert alert-success'>";
        echo "<h4>Success!</h4>";
        echo "<p>Successfully linked $total_votes_linked votes and $total_spins_linked spins to your account.</p>";
        echo "<p><strong>Your points should now be synced across all pages!</strong></p>";
        echo "</div>";
        
        // Show updated stats
        $stats = getUserStats($user_id, $current_ip);
        echo "<h3>Updated Stats:</h3>";
        echo "<ul>";
        echo "<li>Total Votes: {$stats['voting_stats']['total_votes']}</li>";
        echo "<li>Total Spins: {$stats['spin_stats']['total_spins']}</li>";
        echo "<li>Total Points: {QRCoinManager::getBalance($user_id)}</li>";
        echo "</ul>";
        
        echo "<p><a href='user/dashboard.php' class='btn btn-primary'>Go to Dashboard</a></p>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger'>";
        echo "<h4>Error!</h4>";
        echo "<p>Failed to link historical data: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
} else {
    // Show current stats before linking
    $stats = getUserStats($user_id, $current_ip);
    
    echo "<h3>Current Stats (Before Linking):</h3>";
    echo "<ul>";
    echo "<li>Total Votes: {$stats['voting_stats']['total_votes']}</li>";
    echo "<li>Total Spins: {$stats['spin_stats']['total_spins']}</li>";
    echo "<li>Total Points: {QRCoinManager::getBalance($user_id)}</li>";
    echo "</ul>";
    
    // Check for orphaned data
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as orphaned_votes
        FROM votes 
        WHERE voter_ip = ? AND user_id IS NULL
    ");
    $stmt->execute([$current_ip]);
    $orphaned_votes = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as orphaned_spins
        FROM spin_results 
        WHERE user_ip = ? AND user_id IS NULL
    ");
    $stmt->execute([$current_ip]);
    $orphaned_spins = $stmt->fetchColumn();
    
    echo "<h3>Orphaned Data Found:</h3>";
    echo "<ul>";
    echo "<li>Votes not linked to your account: $orphaned_votes</li>";
    echo "<li>Spins not linked to your account: $orphaned_spins</li>";
    echo "</ul>";
    
    if ($orphaned_votes > 0 || $orphaned_spins > 0) {
        echo "<div class='alert alert-warning'>";
        echo "<p>There is activity from your IP address that is not linked to your user account. This is likely causing the points sync issue.</p>";
        echo "<p>Click the button below to link this historical data to your account.</p>";
        echo "</div>";
        
        echo "<form method='POST'>";
        echo "<button type='submit' class='btn btn-primary'>Link Historical Data</button>";
        echo "</form>";
    } else {
        echo "<div class='alert alert-info'>";
        echo "<p>No orphaned data found. Your points should already be in sync.</p>";
        echo "</div>";
    }
}
?> 