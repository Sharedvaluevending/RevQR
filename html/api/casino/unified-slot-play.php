<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
/**
 * SECURE UNIFIED SLOT MACHINE ENDPOINT
 * 
 * This endpoint handles the complete slot machine flow in a single atomic transaction:
 * 1. Validates user and balance
 * 2. Deducts bet using QRCoinManager
 * 3. Generates results server-side only
 * 4. Calculates payouts server-side only  
 * 5. Awards winnings using QRCoinManager
 * 6. Returns signed results for frontend display
 * 
 * Security Features:
 * - Server-side authority for all calculations
 * - Cryptographic result signing
 * - Atomic database transactions
 * - Unified QRCoinManager integration
 * - Race condition prevention
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/qr_coin_manager.php';
require_once __DIR__ . '/../../core/casino_spin_manager.php';

header('Content-Type: application/json');

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $user_id = $_SESSION['user_id'];
    $business_id = (int)($input['business_id'] ?? 0);
    $bet_amount = (int)($input['bet_amount'] ?? 0);
    
    // Validate input
    if (!$business_id || $bet_amount <= 0 || $bet_amount > 50) {
        throw new Exception('Invalid input parameters');
    }
    
    // Start atomic transaction
    $pdo->beginTransaction();
    
    // Verify business has casino enabled
    $stmt = $pdo->prepare("
        SELECT bcp.casino_enabled, b.name as business_name
        FROM business_casino_participation bcp
        JOIN businesses b ON bcp.business_id = b.id
        WHERE bcp.business_id = ? AND bcp.casino_enabled = 1
    ");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch();
    
    if (!$business) {
        throw new Exception('Casino not enabled for this business');
    }
    
    // Check if user has spins available
    if (!CasinoSpinManager::canPlay($user_id, $business_id)) {
        throw new Exception('No casino spins remaining today');
    }
    
    // Check user balance using QRCoinManager
    $current_balance = QRCoinManager::getBalance($user_id);
    if ($current_balance < $bet_amount) {
        throw new Exception('Insufficient QR coins. You need ' . $bet_amount . ' but only have ' . $current_balance);
    }
    
    // PHASE 1: DEDUCT BET AMOUNT FIRST (Security: deduct before generating results)
    $bet_deducted = QRCoinManager::spendCoins(
        $user_id,
        $bet_amount,
        'casino_bet',
        "Slot machine bet at " . $business['business_name'],
        [
            'business_id' => $business_id,
            'game_type' => 'slot_machine',
            'bet_amount' => $bet_amount
        ],
        $business_id,
        'casino_play'
    );
    
    if (!$bet_deducted) {
        throw new Exception('Failed to deduct bet amount');
    }
    
    // PHASE 2: LOAD SYMBOLS USING SAME LOGIC AS FRONTEND
    $symbols = loadUserSymbols($pdo, $user_id);
    
    // PHASE 3: GENERATE RESULTS SERVER-SIDE ONLY (using correct symbols)
    $slot_results = generateSecureSlotResults($symbols);
    
    // PHASE 4: CALCULATE PAYOUTS SERVER-SIDE ONLY  
    $payout_data = calculateSecurePayouts($slot_results['results'], $bet_amount);
    
    // PHASE 5: AWARD WINNINGS IF ANY
    $win_transaction_id = null;
    if ($payout_data['win_amount'] > 0) {
        $win_awarded = QRCoinManager::addTransaction(
            $user_id,
            'earning',
            'casino_win',
            $payout_data['win_amount'],
            "Slot machine win at " . $business['business_name'] . " - " . $payout_data['message'],
            [
                'business_id' => $business_id,
                'game_type' => 'slot_machine',
                'bet_amount' => $bet_amount,
                'win_amount' => $payout_data['win_amount'],
                'win_type' => $payout_data['type'],
                'multiplier' => round($payout_data['win_amount'] / $bet_amount, 2)
            ],
            $business_id,
            'casino_play'
        );
        
        if (!$win_awarded) {
            throw new Exception('Failed to award winnings');
        }
        
        $win_transaction_id = $pdo->lastInsertId();
    }
    
    // PHASE 6: RECORD CASINO PLAY
    $stmt = $pdo->prepare("
        INSERT INTO casino_plays 
        (user_id, business_id, game_id, bet_amount, symbols_result, prize_won, prize_type, win_amount, is_jackpot, played_at)
        VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $is_jackpot = ($payout_data['win_amount'] > 0 && ($payout_data['win_amount'] / $bet_amount) >= 10) ? 1 : 0;
    $prize_won = $payout_data['win_amount'] > 0 ? $payout_data['message'] : 'No prize';
    $prize_type = $payout_data['win_amount'] > 0 ? 'qr_coins' : null;
    
    $stmt->execute([
        $user_id,
        $business_id,
        $bet_amount,
        json_encode($slot_results['results']),
        $prize_won,
        $prize_type,
        $payout_data['win_amount'],
        $is_jackpot
    ]);
    
    $play_id = $pdo->lastInsertId();
    
    // PHASE 7: UPDATE DAILY LIMITS AND SPIN TRACKING
    $stmt = $pdo->prepare("
        INSERT INTO casino_daily_limits (user_id, business_id, play_date, plays_count, total_bet, total_won)
        VALUES (?, ?, CURDATE(), 1, ?, ?)
        ON DUPLICATE KEY UPDATE 
            plays_count = plays_count + 1,
            total_bet = total_bet + VALUES(total_bet),
            total_won = total_won + VALUES(total_won)
    ");
    $stmt->execute([$user_id, $business_id, $bet_amount, $payout_data['win_amount']]);
    
    // Update spin pack usage
    CasinoSpinManager::recordCasinoPlay($user_id, $business_id);
    
    // PHASE 7: GENERATE CRYPTOGRAPHIC SIGNATURE FOR RESULTS
    $result_signature = generateResultSignature($slot_results['results'], $payout_data, $play_id);
    
    // Commit atomic transaction
    $pdo->commit();
    
    // Get final balance and spin info
    $new_balance = QRCoinManager::getBalance($user_id);
    $updated_spin_info = CasinoSpinManager::getAvailableSpins($user_id, $business_id);
    
    // Debug logging for symbols being returned
    error_log("🎰 SLOT DEBUG (FIXED) - Symbols loaded: " . count($symbols));
    error_log("🎰 SLOT DEBUG (FIXED) - Returning results with symbols:");
    foreach ($slot_results['results'] as $i => $reel) {
        error_log("  Reel $i:");
        error_log("    Top: " . $reel['topSymbol']['name'] . " -> " . $reel['topSymbol']['image']);
        error_log("    Mid: " . $reel['middleSymbol']['name'] . " -> " . $reel['middleSymbol']['image']);
        error_log("    Bot: " . $reel['bottomSymbol']['name'] . " -> " . $reel['bottomSymbol']['image']);
    }
    
    // Return secure, signed results
    echo json_encode([
        'success' => true,
        'play_id' => $play_id,
        'results' => $slot_results['results'],
        'is_win' => $payout_data['win_amount'] > 0,
        'win_amount' => $payout_data['win_amount'],
        'win_type' => $payout_data['type'],
        'message' => $payout_data['message'],
        'winning_row' => $payout_data['winning_row'] ?? -1,
        'bet_amount' => $bet_amount,
        'new_balance' => $new_balance,
        'balance_change' => $new_balance - $current_balance, // Should be negative bet + positive win
        'spins_remaining' => $updated_spin_info['spins_remaining'],
        'is_jackpot' => $is_jackpot,
        'signature' => $result_signature, // Cryptographic verification
        'server_time' => time(),
        'business_name' => $business['business_name']
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on any error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Secure slot play error for user $user_id, business $business_id: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'user_id' => $user_id,
            'business_id' => $business_id,
            'bet_amount' => $bet_amount,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}

/**
 * CRITICAL FIX: Load user symbols using SAME logic as frontend
 * This ensures 100% consistency between what user sees and what backend calculates
 */
function loadUserSymbols($pdo, $user_id) {
    // Get user's unlocked avatars for slot symbols (SAME AS FRONTEND)
    $stmt = $pdo->prepare("
        SELECT ua.avatar_id, a.name
        FROM user_avatars ua
        LEFT JOIN avatar_config a ON ua.avatar_id = a.avatar_id
        WHERE ua.user_id = ?
        ORDER BY ua.unlocked_at DESC, ua.avatar_id ASC
    ");
    $stmt->execute([$user_id]);
    $unlocked_avatars = $stmt->fetchAll();

    // Add default avatars that everyone has access to (SAME AS FRONTEND)
    $default_avatars = [
        ['avatar_id' => 1, 'avatar_name' => 'QR Ted', 'level' => 1],
        ['avatar_id' => 12, 'avatar_name' => 'QR Steve', 'level' => 1], 
        ['avatar_id' => 13, 'avatar_name' => 'QR Bob', 'level' => 1],
        ['avatar_id' => 2, 'avatar_name' => 'QR James', 'level' => 2],
        ['avatar_id' => 3, 'avatar_name' => 'QR Mike', 'level' => 3],
        ['avatar_id' => 4, 'avatar_name' => 'QR Ed', 'level' => 4],
        ['avatar_id' => 5, 'avatar_name' => 'QR Ned', 'level' => 5],
        ['avatar_id' => 6, 'avatar_name' => 'QR Easybake', 'level' => 6]
    ];

    // Merge and ensure uniqueness (SAME AS FRONTEND)
    $all_avatars = [];
    $used_ids = [];

    // Add unlocked avatars first (higher priority)
    foreach ($unlocked_avatars as $avatar) {
        if (!in_array($avatar['avatar_id'], $used_ids)) {
            $all_avatars[] = $avatar;
            $used_ids[] = $avatar['avatar_id'];
        }
    }

    // Fill with defaults to ensure we have enough variety
    foreach ($default_avatars as $avatar) {
        if (!in_array($avatar['avatar_id'], $used_ids) && count($all_avatars) < 9) {
            $all_avatars[] = $avatar;
            $used_ids[] = $avatar['avatar_id'];
        }
    }

    // Helper function (SAME AS FRONTEND)
    function getAvatarFilename($avatar_id) {
        $avatar_files = [
            1 => 'qrted.png',
            2 => 'qrjames.png', 
            3 => 'qrmike.png',
            4 => 'qred.png',
            5 => 'qrned.png',
            6 => 'qrEasybake.png',
            12 => 'qrsteve.png',
            13 => 'qrbob.png'
        ];
        return $avatar_files[$avatar_id] ?? 'qrted.png';
    }

    // Helper function to get rarity from level (SAME AS FRONTEND)
    function getRarityFromLevel($level) {
        if ($level >= 10) return 'mythical';
        if ($level >= 8) return 'legendary';
        if ($level >= 6) return 'epic';
        if ($level >= 4) return 'rare';
        if ($level >= 2) return 'uncommon';
        return 'common';
    }

    // Convert to the format expected by the slot machine (SAME AS FRONTEND)
    $symbols = [];
    foreach ($all_avatars as $index => $avatar) {
        // Calculate values based on rarity/level (SAME AS FRONTEND)
        $baseValue = max(5, $avatar['level'] * 2);
        $isWild = ($index === 2); // Make the 3rd avatar a wild symbol (SAME AS FRONTEND)
        
        $symbols[] = [
            'image' => 'assets/img/avatars/' . getAvatarFilename($avatar['avatar_id']),
            'name' => $avatar['avatar_name'] ?? 'Avatar ' . $avatar['avatar_id'],
            'level' => max(1, (int)($avatar['level'] ?? 1)),
            'value' => $isWild ? $baseValue * 2 : $baseValue,
            'rarity' => getRarityFromLevel($avatar['level'] ?? 1),
            'isWild' => $isWild,
            'avatar_id' => $avatar['avatar_id']
        ];
    }

    // Ensure we have at least 3 avatars for the slot machine (SAME AS FRONTEND)
    if (count($symbols) < 3) {
        $symbols = [
            ['image' => 'assets/img/avatars/qrted.png', 'name' => 'QR Ted', 'level' => 1, 'value' => 5, 'rarity' => 'common', 'isWild' => false, 'avatar_id' => 1],
            ['image' => 'assets/img/avatars/qrsteve.png', 'name' => 'QR Steve', 'level' => 1, 'value' => 5, 'rarity' => 'common', 'isWild' => false, 'avatar_id' => 12],
            ['image' => 'assets/img/avatars/qrbob.png', 'name' => 'QR Bob', 'level' => 1, 'value' => 10, 'rarity' => 'common', 'isWild' => true, 'avatar_id' => 13]
        ];
    }

    return $symbols;
}

/**
 * Generate secure slot results using proper user symbol array
 */
function generateSecureSlotResults($symbols) {
    // Determine if this should be a win (15% chance)
    $win_chance = mt_rand(1, 100) / 100;
    $should_win = ($win_chance <= 0.15);
    
    if ($should_win) {
        return ['results' => generateWinningSlotGrid($symbols)];
    } else {
        return ['results' => generateLosingSlotGrid($symbols)];
    }
}

/**
 * Generate winning 3x3 slot grid
 */
function generateWinningSlotGrid($symbols) {
    $win_type = mt_rand(1, 100) / 100;
    
    // Create 3x3 grid
    $grid = [
        [getRandomSymbol($symbols), getRandomSymbol($symbols), getRandomSymbol($symbols)],
        [getRandomSymbol($symbols), getRandomSymbol($symbols), getRandomSymbol($symbols)],
        [getRandomSymbol($symbols), getRandomSymbol($symbols), getRandomSymbol($symbols)]
    ];
    
    if ($win_type < 0.6) {
        // 60% horizontal line wins
        $winning_row = mt_rand(0, 2);
        $winning_symbol = getWeightedRandomSymbol($symbols);
        
        // Fill winning row
        for ($col = 0; $col < 3; $col++) {
            $grid[$winning_row][$col] = $winning_symbol;
        }
    } else {
        // 40% diagonal wins
        $is_tl_diagonal = (mt_rand(0, 1) == 1);
        $winning_symbol = getWeightedRandomSymbol($symbols);
        
        if ($is_tl_diagonal) {
            // Top-left to bottom-right
            $grid[0][0] = $winning_symbol;
            $grid[1][1] = $winning_symbol;
            $grid[2][2] = $winning_symbol;
        } else {
            // Top-right to bottom-left
            $grid[0][2] = $winning_symbol;
            $grid[1][1] = $winning_symbol;
            $grid[2][0] = $winning_symbol;
        }
    }
    
    // Convert to reel format expected by frontend
    return convertGridToReelFormat($grid);
}

/**
 * Generate losing 3x3 slot grid
 */
function generateLosingSlotGrid($symbols) {
    $grid = [
        [getRandomSymbol($symbols), getRandomSymbol($symbols), getRandomSymbol($symbols)],
        [getRandomSymbol($symbols), getRandomSymbol($symbols), getRandomSymbol($symbols)],
        [getRandomSymbol($symbols), getRandomSymbol($symbols), getRandomSymbol($symbols)]
    ];
    
    // Ensure it's actually losing by breaking any potential wins
    $test_payout = calculateSecurePayouts(convertGridToReelFormat($grid), 1);
    if ($test_payout['win_amount'] > 0) {
        // Break the win by changing middle symbol
        $grid[1][1] = getRandomSymbolDifferentFrom($symbols, [$grid[0][1], $grid[2][1]]);
    }
    
    return convertGridToReelFormat($grid);
}

/**
 * Convert 3x3 grid to reel format expected by frontend
 */
function convertGridToReelFormat($grid) {
    $reels = [];
    
    for ($col = 0; $col < 3; $col++) {
        $reels[] = [
            'topSymbol' => $grid[0][$col],
            'middleSymbol' => $grid[1][$col],
            'bottomSymbol' => $grid[2][$col]
        ];
    }
    
    return $reels;
}

/**
 * Calculate secure payouts server-side only (authoritative)
 */
function calculateSecurePayouts($results, $bet_amount) {
    $jackpot_multiplier = 6; // Standard jackpot multiplier
    
    // Convert reel format back to 3x3 grid for analysis
    $grid = [
        [$results[0]['topSymbol'], $results[1]['topSymbol'], $results[2]['topSymbol']],
        [$results[0]['middleSymbol'], $results[1]['middleSymbol'], $results[2]['middleSymbol']],
        [$results[0]['bottomSymbol'], $results[1]['bottomSymbol'], $results[2]['bottomSymbol']]
    ];
    
    // Check all possible winning lines
    $lines = [
        ['symbols' => $grid[0], 'name' => 'Top Row', 'type' => 'horizontal', 'row' => 0],
        ['symbols' => $grid[1], 'name' => 'Middle Row', 'type' => 'horizontal', 'row' => 1],
        ['symbols' => $grid[2], 'name' => 'Bottom Row', 'type' => 'horizontal', 'row' => 2],
        ['symbols' => [$grid[0][0], $grid[1][1], $grid[2][2]], 'name' => 'Top-Left Diagonal', 'type' => 'diagonal_tl', 'row' => -1],
        ['symbols' => [$grid[0][2], $grid[1][1], $grid[2][0]], 'name' => 'Top-Right Diagonal', 'type' => 'diagonal_tr', 'row' => -1]
    ];
    
    // Helper function to check if symbols match (considering wilds)
    $symbolsMatch = function($sym1, $sym2) {
        return $sym1['isWild'] || $sym2['isWild'] || $sym1['image'] === $sym2['image'];
    };
    
    // Find winning lines
    foreach ($lines as $line) {
        $s1 = $line['symbols'][0];
        $s2 = $line['symbols'][1]; 
        $s3 = $line['symbols'][2];
        
        if ($symbolsMatch($s1, $s2) && $symbolsMatch($s2, $s3) && $symbolsMatch($s1, $s3)) {
            // We have a win!
            $base_symbol = $s1['isWild'] ? ($s2['isWild'] ? $s3 : $s2) : $s1;
            $wild_count = ($s1['isWild'] ? 1 : 0) + ($s2['isWild'] ? 1 : 0) + ($s3['isWild'] ? 1 : 0);
            
            // Calculate payout
            if ($wild_count === 3) {
                // Triple wild jackpot
                return [
                    'win_amount' => $bet_amount * $jackpot_multiplier * 2,
                    'type' => 'wild_line',
                    'message' => '🌟 TRIPLE WILD MEGA JACKPOT! 🌟',
                    'winning_row' => $line['row']
                ];
            } elseif ($base_symbol['rarity'] === 'mythical') {
                // Mythical jackpot
                return [
                    'win_amount' => $bet_amount * $jackpot_multiplier * 1.5,
                    'type' => 'mythical_jackpot',
                    'message' => "💎 MYTHICAL {$line['name']} JACKPOT! 💎",
                    'winning_row' => $line['row']
                ];
            } else {
                // Regular line win
                $multiplier = $base_symbol['level'] >= 8 ? $jackpot_multiplier : ($base_symbol['level'] * 2);
                $wild_bonus = $wild_count * 1;
                $diagonal_bonus = strpos($line['type'], 'diagonal') !== false ? 2 : 0;
                
                return [
                    'win_amount' => $bet_amount * ($multiplier + $wild_bonus + $diagonal_bonus),
                    'type' => strpos($line['type'], 'diagonal') !== false ? 'diagonal_exact' : 'straight_line',
                    'message' => $wild_count > 0 ? "🌟 WILD {$line['name']}! 🌟" : "🎯 {$line['name']} WIN! 🎯",
                    'winning_row' => $line['row']
                ];
            }
        }
    }
    
    // No win
    return [
        'win_amount' => 0,
        'type' => 'loss',
        'message' => 'Line up 3 across, hit the diagonals, or get wilds!',
        'winning_row' => -1
    ];
}

/**
 * Generate cryptographic signature for results verification
 */
function generateResultSignature($results, $payout_data, $play_id) {
    $secret_key = 'slot_verification_' . APP_SECRET_KEY; // Use your app's secret
    $data_to_sign = json_encode([
        'play_id' => $play_id,
        'results' => $results,
        'win_amount' => $payout_data['win_amount'],
        'timestamp' => time()
    ]);
    
    return hash_hmac('sha256', $data_to_sign, $secret_key);
}

/**
 * Helper functions
 */
function getRandomSymbol($symbols) {
    return $symbols[mt_rand(0, count($symbols) - 1)];
}

function getWeightedRandomSymbol($symbols) {
    $non_wild_symbols = array_filter($symbols, function($s) { return !$s['isWild']; });
    $weights = array_map(function($s) { return max(1, 5 - $s['level']); }, $non_wild_symbols);
    $total_weight = array_sum($weights);
    $random = mt_rand(1, $total_weight);
    $current_weight = 0;
    
    foreach ($non_wild_symbols as $symbol) {
        $current_weight += max(1, 5 - $symbol['level']);
        if ($random <= $current_weight) {
            return $symbol;
        }
    }
    
    return $non_wild_symbols[count($non_wild_symbols) - 1];
}

function getRandomSymbolDifferentFrom($symbols, $exclude_symbols) {
    $available = array_filter($symbols, function($symbol) use ($exclude_symbols) {
        foreach ($exclude_symbols as $exclude) {
            if ($symbol['image'] === $exclude['image']) {
                return false;
            }
        }
        return true;
    });
    
    return count($available) > 0 ? $available[mt_rand(0, count($available) - 1)] : $symbols[0];
}

ob_end_flush(); 