<?php
/**
 * Fix Missing Casino Transactions
 * Creates missing QR coin transactions for casino plays
 */

session_start();
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['play_id'])) {
        throw new Exception('Invalid input - play_id required');
    }
    
    $play_id = (int) $input['play_id'];
    $user_id = $_SESSION['user_id'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get the casino play details
    $stmt = $pdo->prepare("
        SELECT * FROM casino_plays 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$play_id, $user_id]);
    $play = $stmt->fetch();
    
    if (!$play) {
        throw new Exception('Casino play not found or not owned by user');
    }
    
    // Check if transactions already exist
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM qr_coin_transactions 
        WHERE reference_id = ? AND reference_type = 'casino_play'
    ");
    $stmt->execute([$play_id]);
    $existing_count = $stmt->fetchColumn();
    
    if ($existing_count > 0) {
        throw new Exception('Transactions already exist for this casino play');
    }
    
    // Create the bet deduction transaction
    $bet_success = QRCoinManager::addTransaction(
        $user_id,
        'spending',
        'casino_bet',
        -$play['bet_amount'],
        "Casino bet fix for play #{$play_id}",
        [
            'business_id' => $play['business_id'],
            'play_id' => $play_id,
            'fix_transaction' => true
        ],
        $play_id,
        'casino_play'
    );
    
    if (!$bet_success) {
        throw new Exception('Failed to create bet transaction');
    }
    
    // Create the win transaction if there was a win
    if ($play['win_amount'] > 0) {
        $win_success = QRCoinManager::addTransaction(
            $user_id,
            'earning',
            'casino_win',
            $play['win_amount'],
            "Casino win fix for play #{$play_id}",
            [
                'business_id' => $play['business_id'],
                'play_id' => $play_id,
                'bet_amount' => $play['bet_amount'],
                'fix_transaction' => true
            ],
            $play_id,
            'casino_play'
        );
        
        if (!$win_success) {
            throw new Exception('Failed to create win transaction');
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Get updated balance
    $new_balance = QRCoinManager::getBalance($user_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Transactions created successfully',
        'new_balance' => $new_balance,
        'transactions_created' => $play['win_amount'] > 0 ? 2 : 1
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Fix casino transaction error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?> 