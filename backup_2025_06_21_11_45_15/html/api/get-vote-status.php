<?php
/**
 * Get Real-time Vote Status API
 * Returns current user's vote availability for AJAX updates
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';
// Simple weekly vote limit system (2 votes per week)

// Set JSON header
header('Content-Type: application/json');

try {
    // Get user info
    $user_id = $_SESSION['user_id'] ?? null;
    $voter_ip = $_SERVER['REMOTE_ADDR'];
    
    // Get user's weekly vote count
    $weekly_votes_used = 0;
    $weekly_vote_limit = 2;

    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as weekly_votes
            FROM votes 
            WHERE user_id = ? 
            AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
        ");
        $stmt->execute([$user_id]);
        $weekly_votes_used = (int) $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as weekly_votes
            FROM votes 
            WHERE voter_ip = ? 
            AND user_id IS NULL
            AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
        ");
        $stmt->execute([$voter_ip]);
        $weekly_votes_used = (int) $stmt->fetchColumn();
    }

    $votes_remaining = max(0, $weekly_vote_limit - $weekly_votes_used);
    
    // Get QR coin balance
    $qr_balance = 0;
    if ($user_id) {
        require_once __DIR__ . '/../core/qr_coin_manager.php';
        $qr_balance = QRCoinManager::getBalance($user_id);
    }
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'votes_used' => $weekly_votes_used,
        'votes_remaining' => $votes_remaining,
        'weekly_limit' => $weekly_vote_limit,
        'qr_balance' => $qr_balance
    ]);
    
} catch (Exception $e) {
    error_log("Get Vote Status API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Unable to get vote status',
        'error' => $e->getMessage()
    ]);
}
?> 