<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
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
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $business_id = $input['business_id'] ?? null;
    $bet_amount = $input['bet_amount'] ?? null;
    $win_amount = $input['win_amount'] ?? 0;
    $results = $input['results'] ?? [];
    
    if (!$business_id || !is_numeric($bet_amount) || $bet_amount <= 0) {
        throw new Exception('Invalid input parameters');
    }
    
    $user_id = $_SESSION['user_id'];
    $bet_amount = (int) $bet_amount;
    $win_amount = (int) $win_amount;
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Verify business has casino enabled
    $stmt = $pdo->prepare("
        SELECT casino_enabled, max_daily_plays 
        FROM business_casino_settings 
        WHERE business_id = ?
    ");
    $stmt->execute([$business_id]);
    $casino_settings = $stmt->fetch();
    
    if (!$casino_settings || !$casino_settings['casino_enabled']) {
        throw new Exception('Casino not enabled for this business');
    }
    
    // OLD: Check daily play limit - REMOVED because CasinoSpinManager handles this now
    // Get current play count for response calculation (still needed for response)
    $stmt = $pdo->prepare("
        SELECT COALESCE(plays_count, 0) as plays_today 
        FROM casino_daily_limits 
        WHERE user_id = ? AND business_id = ? AND play_date = CURDATE()
    ");
    $stmt->execute([$user_id, $business_id]);
    $plays_today = $stmt->fetchColumn() ?: 0;
    
    // Verify user has enough balance (this should already be checked client-side)
    require_once __DIR__ . '/../../core/qr_coin_manager.php';
    require_once __DIR__ . '/../../core/casino_spin_manager.php';
    
    $current_balance = QRCoinManager::getBalance($user_id);
    
    if ($current_balance < $bet_amount) {
        throw new Exception('Insufficient QR coin balance');
    }
    
    // Check if user has spins available (including spin packs)
    if (!CasinoSpinManager::canPlay($user_id, $business_id)) {
        throw new Exception('No casino spins remaining today');
    }
    
    // Deduct bet amount from user's balance
    $deduct_result = QRCoinManager::spendCoins(
        $user_id, 
        $bet_amount, 
        'casino_bet', 
        "Casino bet at business #" . $business_id,
        ['business_id' => $business_id],
        $business_id,
        'casino_play'
    );
    
    if (!$deduct_result['success']) {
        throw new Exception($deduct_result['error'] ?? 'Failed to deduct bet amount');
    }
    
    // Determine prize details
    $prize_won = $win_amount > 0 ? "Won {$win_amount} QR Coins" : "No prize";
    $prize_type = $win_amount > 0 ? 'qr_coins' : null;
    $is_jackpot = 0;
    
    // Check if it's a jackpot based on win amount vs bet amount
    if ($win_amount > 0 && count($results) > 0) {
        $multiplier = $win_amount / $bet_amount;
        $is_jackpot = ($multiplier >= 10) ? 1 : 0; // 10x or more = jackpot
    }

    // Record the casino play
    $stmt = $pdo->prepare("
        INSERT INTO casino_plays 
        (user_id, business_id, game_id, bet_amount, symbols_result, prize_won, prize_type, win_amount, is_jackpot, played_at)
        VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        $business_id,
        $bet_amount,
        json_encode($results),
        $prize_won,
        $prize_type,
        $win_amount,
        $is_jackpot
    ]);
    
    $play_id = $pdo->lastInsertId();
    
    // Add win amount to user's balance if they won
    if ($win_amount > 0) {
        $add_success = QRCoinManager::addTransaction(
            $user_id,
            'earning',
            'casino_win',
            $win_amount,
            "Casino win at business #" . $business_id,
            [
                'business_id' => $business_id,
                'play_id' => $play_id,
                'bet_amount' => $bet_amount,
                'win_type' => $results[0]['rarity'] ?? 'unknown'
            ],
            $play_id,
            'casino_play'
        );
        
        if (!$add_success) {
            throw new Exception('Failed to add win amount');
        }
    }
    
    // Update daily limits tracking
    $stmt = $pdo->prepare("
        INSERT INTO casino_daily_limits (user_id, business_id, play_date, plays_count, total_bet, total_won)
        VALUES (?, ?, CURDATE(), 1, ?, ?)
        ON DUPLICATE KEY UPDATE 
            plays_count = plays_count + 1,
            total_bet = total_bet + VALUES(total_bet),
            total_won = total_won + VALUES(total_won)
    ");
    $stmt->execute([$user_id, $business_id, $bet_amount, $win_amount]);
    
    // Update spin pack usage
    CasinoSpinManager::recordCasinoPlay($user_id, $business_id);
    
    // Commit transaction
    $pdo->commit();
    
    // Get updated balance and spin info
    $new_balance = QRCoinManager::getBalance($user_id);
    $updated_spin_info = CasinoSpinManager::getAvailableSpins($user_id, $business_id);
    
    // Success response
    echo json_encode([
        'success' => true,
        'play_id' => $play_id,
        'new_balance' => $new_balance,
        'bet_amount' => $bet_amount,
        'win_amount' => $win_amount,
        'plays_remaining' => $updated_spin_info['spins_remaining']
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    // Log the error for debugging
    error_log("Casino record-play error for user $user_id, business $business_id: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'debug_info' => [
            'user_id' => $user_id,
            'business_id' => $business_id,
            'bet_amount' => $bet_amount,
            'win_amount' => $win_amount
        ]
    ]);
} 