<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';

header('Content-Type: application/json');

try {
    $community_activity = [];
    
    // Get recent QR coin earnings (last 30 minutes, significant amounts)
    $stmt = $pdo->prepare("
        SELECT 
            'coin_earned' as type,
            CASE 
                WHEN u.username IS NOT NULL 
                THEN CONCAT(SUBSTRING(u.username, 1, 1), '***', SUBSTRING(u.username, -1, 1))
                ELSE 'Someone'
            END as anonymous_name,
            t.amount,
            t.created_at
        FROM qr_coin_transactions t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) 
        AND t.amount > 0
        AND t.amount >= 25
        ORDER BY t.created_at DESC
        LIMIT 3
    ");
    $stmt->execute();
    $recent_earnings = $stmt->fetchAll();
    
    foreach ($recent_earnings as $earning) {
        $community_activity[] = [
            'message' => "{$earning['anonymous_name']} just earned {$earning['amount']} QR coins!",
            'time' => $earning['created_at'],
            'type' => 'coin_earned'
        ];
    }
    
    // Get users who achieved personal bests today (10+ votes or 5+ spins)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT voter_ip) as pb_users
        FROM (
            SELECT voter_ip, COUNT(*) as daily_count
            FROM votes 
            WHERE DATE(created_at) = CURDATE()
            GROUP BY voter_ip
            HAVING daily_count >= 5
            UNION
            SELECT user_ip as voter_ip, COUNT(*) as daily_count
            FROM spin_results 
            WHERE DATE(spin_time) = CURDATE()
            GROUP BY user_ip
            HAVING daily_count >= 3
        ) as achievers
    ");
    $stmt->execute();
    $pb_count = $stmt->fetchColumn();
    
    if ($pb_count > 0) {
        $community_activity[] = [
            'message' => "{$pb_count} users beat their personal best today!",
            'time' => date('Y-m-d H:i:s'),
            'type' => 'achievement'
        ];
    }
    
    // Get recent big wins from spin wheel (last hour)
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN u.username IS NOT NULL 
                THEN CONCAT(SUBSTRING(u.username, 1, 1), '***', SUBSTRING(u.username, -1, 1))
                ELSE 'Someone'
            END as anonymous_name,
            sr.prize_won,
            sr.spin_time
        FROM spin_results sr
        LEFT JOIN users u ON sr.user_id = u.id
        WHERE sr.spin_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND sr.prize_won NOT IN ('No Prize', 'Try Again', 'Lose All Votes')
        AND sr.prize_points >= 50
        ORDER BY sr.spin_time DESC
        LIMIT 2
    ");
    $stmt->execute();
    $big_wins = $stmt->fetchAll();
    
    foreach ($big_wins as $win) {
        $community_activity[] = [
            'message' => "{$win['anonymous_name']} won {$win['prize_won']} on spin wheel!",
            'time' => $win['spin_time'],
            'type' => 'level_up'
        ];
    }
    
    // Get recent voting milestones
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN u.username IS NOT NULL 
                THEN CONCAT(SUBSTRING(u.username, 1, 1), '***', SUBSTRING(u.username, -1, 1))
                ELSE 'Someone'
            END as anonymous_name,
            COUNT(*) as vote_count
        FROM votes v
        LEFT JOIN users u ON v.user_id = u.id
        WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        GROUP BY COALESCE(v.user_id, v.voter_ip)
        HAVING vote_count >= 10
        ORDER BY v.created_at DESC
        LIMIT 2
    ");
    $stmt->execute();
    $vote_milestones = $stmt->fetchAll();
    
    foreach ($vote_milestones as $milestone) {
        $community_activity[] = [
            'message' => "{$milestone['anonymous_name']} reached {$milestone['vote_count']} votes milestone!",
            'time' => date('Y-m-d H:i:s'),
            'type' => 'achievement'
        ];
    }
    
    // Sort by time (most recent first)
    usort($community_activity, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    // Return only top 5 most recent
    $community_activity = array_slice($community_activity, 0, 5);
    
    echo json_encode([
        'success' => true,
        'activities' => $community_activity,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    error_log("Community activity API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'activities' => [],
        'error' => 'Failed to fetch community activity'
    ]);
}
?> 