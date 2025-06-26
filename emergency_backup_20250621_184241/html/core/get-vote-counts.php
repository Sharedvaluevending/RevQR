<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/services/VotingService.php';

// Set JSON header
header('Content-Type: application/json');

// Initialize voting service
VotingService::init($pdo);

try {
    // Get parameters
    $item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
    $campaign_id = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : null;
    $machine_id = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : null;
    
    if (!$item_id) {
        throw new Exception('Missing required item_id parameter');
    }
    
    // Get vote counts using unified service
    $result = VotingService::getVoteCounts($item_id, $campaign_id, $machine_id);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'vote_in_count' => $result['vote_in_count'],
            'vote_out_count' => $result['vote_out_count'],
            'total_votes' => $result['total_votes']
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Failed to get vote counts');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 