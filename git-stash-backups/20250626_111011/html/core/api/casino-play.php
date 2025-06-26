<?php
session_start();
require_once __DIR__ . '/../config.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit();
}

// Validate required fields
$required_fields = ['game_type', 'bet_amount', 'win_amount', 'business_id'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

$game_type = $input['game_type'];
$bet_amount = (int)$input['bet_amount'];
$win_amount = (int)$input['win_amount'];
$business_id = (int)$input['business_id'];
$result = $input['result'] ?? '';

// Validate game type
$valid_games = ['slot_machine', 'blackjack', 'roulette'];
if (!in_array($game_type, $valid_games)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid game type']);
    exit();
}

// Validate bet amount
if ($bet_amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid bet amount']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Include QRCoinManager for proper balance handling
    require_once __DIR__ . '/../qr_coin_manager.php';
    
    // Check user's current balance using QRCoinManager
    $current_balance = QRCoinManager::getBalance($user_id);
    
    // Validate bet amount against balance
    if ($current_balance < $bet_amount) {
        throw new Exception('Insufficient balance');
    }
    
    // Deduct bet amount first
    $deduct_result = QRCoinManager::spendCoins(
        $user_id,
        $bet_amount,
        'casino_bet',
        "Casino bet - {$game_type}",
        [
            'business_id' => $business_id,
            'game_type' => $game_type,
            'bet_amount' => $bet_amount
        ]
    );
    
    if (!$deduct_result['success']) {
        throw new Exception('Failed to deduct bet amount: ' . $deduct_result['error']);
    }
    
    // Calculate new balance after bet deduction
    $balance_after_bet = QRCoinManager::getBalance($user_id);
    
    // Add winnings if any
    if ($win_amount > 0) {
        $win_result = QRCoinManager::addTransaction(
            $user_id,
            'earning',
            'casino_win',
            $win_amount,
            "Casino winnings - {$game_type}",
            [
                'business_id' => $business_id,
                'game_type' => $game_type,
                'win_amount' => $win_amount,
                'result' => $result
            ]
        );
        
        if (!$win_result['success']) {
            throw new Exception('Failed to credit winnings: ' . $win_result['error']);
        }
    }
    
    // Get final balance
    $new_balance = QRCoinManager::getBalance($user_id);
    
    // Record the casino play
    $stmt = $pdo->prepare("
        INSERT INTO casino_plays 
        (user_id, business_id, game_type, bet_amount, win_amount, result, balance_before, balance_after, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        $business_id,
        $game_type,
        $bet_amount,
        $win_amount,
        $result,
        $current_balance,
        $new_balance
    ]);
    
    $play_id = $pdo->lastInsertId();
    
    // For blackjack, record additional game details if provided
    if ($game_type === 'blackjack' && isset($input['game_details'])) {
        $game_details = json_encode($input['game_details']);
        $stmt = $pdo->prepare("
            UPDATE casino_plays 
            SET game_details = ? 
            WHERE id = ?
        ");
        $stmt->execute([$game_details, $play_id]);
    }
    
    // Update casino statistics
    updateCasinoStats($business_id, $game_type, $bet_amount, $win_amount);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'play_id' => $play_id,
        'balance_before' => $current_balance,
        'balance_after' => $new_balance,
        'net_change' => $win_amount - $bet_amount,
        'message' => 'Game recorded successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollback();
    
    error_log("Casino play error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to record game: ' . $e->getMessage()]);
}

// Helper function to update casino statistics
function updateCasinoStats($business_id, $game_type, $bet_amount, $win_amount) {
    global $pdo;
    
    try {
        // Check if stats record exists
        $stmt = $pdo->prepare("
            SELECT id FROM casino_stats 
            WHERE business_id = ? AND game_type = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$business_id, $game_type]);
        $stats = $stmt->fetch();
        
        if ($stats) {
            // Update existing stats
            $stmt = $pdo->prepare("
                UPDATE casino_stats 
                SET 
                    total_bets = total_bets + 1,
                    total_bet_amount = total_bet_amount + ?,
                    total_win_amount = total_win_amount + ?,
                    house_profit = house_profit + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $bet_amount,
                $win_amount,
                $bet_amount - $win_amount, // House profit
                $stats['id']
            ]);
        } else {
            // Create new stats record
            $stmt = $pdo->prepare("
                INSERT INTO casino_stats 
                (business_id, game_type, total_bets, total_bet_amount, total_win_amount, house_profit, created_at) 
                VALUES (?, ?, 1, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $business_id,
                $game_type,
                $bet_amount,
                $win_amount,
                $bet_amount - $win_amount
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Casino stats update error: " . $e->getMessage());
        // Don't throw error as this is not critical for the main transaction
    }
}
?> 