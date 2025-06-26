<?php
/**
 * Dedicated Blackjack Balance Update API
 * Handles balance updates with proper QRCoinManager methods
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'OK']);
    exit;
}

try {
    require_once __DIR__ . '/../../core/config.php';
    require_once __DIR__ . '/../../core/session.php';
    require_once __DIR__ . '/../../core/qr_coin_manager.php';
    
    // Log incoming POST data for debugging
    $raw_post = file_get_contents('php://input');
    error_log("blackjack-balance-update.php called. POST: " . $raw_post);
    
    // Check if user is logged in
    if (!is_logged_in()) {
        throw new Exception('User not authenticated');
    }
    
    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    // Get JSON input
    $input = json_decode($raw_post, true);
    if (!$input) {
        throw new Exception('Invalid JSON input: ' . $raw_post);
    }
    
    // Validate input
    $bet_amount = (int)($input['bet_amount'] ?? 0);
    $win_amount = (int)($input['win_amount'] ?? 0);
    $game_result = $input['result'] ?? 'unknown';
    $business_id = (int)($input['business_id'] ?? 1);
    
    if ($bet_amount <= 0) {
        throw new Exception('Invalid bet amount: ' . $bet_amount);
    }
    
    // Get current balance
    $current_balance = QRCoinManager::getBalance($_SESSION['user_id']);
    
    // Step 1: Deduct the bet using spendCoins (this creates the proper spending transaction)
    $bet_result = QRCoinManager::spendCoins(
        $_SESSION['user_id'],
        $bet_amount,
        'casino_bet',
        "Blackjack bet - Business #$business_id",
        ['game_type' => 'blackjack', 'business_id' => $business_id],
        $business_id,
        'business'
    );
    
    if (!$bet_result['success']) {
        throw new Exception($bet_result['error']);
    }
    
    $balance_after_bet = $bet_result['balance'];
    error_log("Blackjack bet deducted: $bet_amount coins. Balance: $current_balance -> $balance_after_bet");
    
    // Step 2: Add winnings if any (using addTransaction for earnings)
    $final_balance = $balance_after_bet;
    
    if ($win_amount > 0) {
        $win_result = QRCoinManager::addTransaction(
            $_SESSION['user_id'],
            'earning',
            'casino_win',
            $win_amount,
            "Blackjack win ($game_result) - Business #$business_id",
            ['game_type' => 'blackjack', 'business_id' => $business_id, 'result' => $game_result],
            $business_id,
            'business'
        );
        
        if (!$win_result['success']) {
            error_log("Warning: Blackjack win transaction failed: " . $win_result['error']);
        } else {
            $final_balance = $win_result['balance'];
            error_log("Blackjack winnings added: $win_amount coins. Balance: $balance_after_bet -> $final_balance");
        }
    }
    
    // Step 3: Log the game play for analytics
    $stmt = $pdo->prepare("
        INSERT INTO casino_plays 
        (user_id, business_id, game_id, game_type, bet_amount, win_amount, played_at)
        VALUES (?, ?, 2, 'blackjack', ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $business_id, $bet_amount, $win_amount]);
    
    // Calculate net change for response
    $net_change = $win_amount - $bet_amount;
    
    // Return success with new balance
    echo json_encode([
        'success' => true,
        'new_balance' => $final_balance,
        'bet_amount' => $bet_amount,
        'win_amount' => $win_amount,
        'net_change' => $net_change,
        'game_result' => $game_result,
        'message' => 'Balance updated successfully',
        'debug' => [
            'initial_balance' => $current_balance,
            'after_bet' => $balance_after_bet,
            'final_balance' => $final_balance
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    // Return detailed error for debugging
    echo json_encode([
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?> 