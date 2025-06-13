<?php
/**
 * Get Real-time Vote Status API
 * Returns current user's vote availability for AJAX updates
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/services/VotingService.php';

// Set JSON header
header('Content-Type: application/json');

try {
    // Initialize voting service
    VotingService::init($pdo);
    
    // Get user info
    $user_id = $_SESSION['user_id'] ?? null;
    $voter_ip = $_SERVER['REMOTE_ADDR'];
    
    // Get current vote status
    $vote_status = VotingService::getUserVoteStatus($user_id, $voter_ip);
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'daily_free_remaining' => $vote_status['daily_free_remaining'],
        'weekly_bonus_remaining' => $vote_status['weekly_bonus_remaining'],
        'premium_votes_available' => $vote_status['premium_votes_available'],
        'qr_balance' => $vote_status['qr_balance'],
        'total_votes_today' => $vote_status['total_votes_today'],
        'vote_pack_votes' => $vote_status['vote_pack_votes'],
        'premium_from_coins' => $vote_status['premium_from_coins']
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