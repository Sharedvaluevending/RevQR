<?php
/**
 * Server-Side Slot Machine Result Generator
 * This ensures the visual animation matches the server-determined outcome
 */

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
    $bet_amount = $input['bet_amount'] ?? 1;
    
    if (!$business_id) {
        throw new Exception('Business ID required');
    }
    
    $user_id = $_SESSION['user_id'];
    $bet_amount = (int) $bet_amount;
    
    // Define slot machine symbols (same as client-side)
    $symbols = [
        ['name' => 'QR Coin', 'image' => '/assets/img/qrCoin.png', 'level' => 1, 'value' => 1, 'rarity' => 'common', 'isWild' => false],
        ['name' => 'QR Bob', 'image' => '/assets/img/avatars/qrbob.png', 'level' => 2, 'value' => 2, 'rarity' => 'common', 'isWild' => false],
        ['name' => 'QR James', 'image' => '/assets/img/avatars/qrjames.png', 'level' => 3, 'value' => 3, 'rarity' => 'rare', 'isWild' => false],
        ['name' => 'QR Terry', 'image' => '/assets/img/avatars/qrterry.png', 'level' => 4, 'value' => 4, 'rarity' => 'rare', 'isWild' => false],
        ['name' => 'QR Easybake', 'image' => '/assets/img/avatars/qreasybake.png', 'level' => 5, 'value' => 5, 'rarity' => 'epic', 'isWild' => false],
        ['name' => 'QR Ned', 'image' => '/assets/img/avatars/qrned.png', 'level' => 6, 'value' => 6, 'rarity' => 'epic', 'isWild' => false],
        ['name' => 'QR ED', 'image' => '/assets/img/avatars/qred.png', 'level' => 7, 'value' => 7, 'rarity' => 'legendary', 'isWild' => false],
        ['name' => 'Lord Pixel', 'image' => '/assets/img/avatars/lordpixel.png', 'level' => 8, 'value' => 8, 'rarity' => 'mythical', 'isWild' => false],
        ['name' => 'Wild Symbol', 'image' => '/assets/img/avatars/wild.png', 'level' => 10, 'value' => 10, 'rarity' => 'wild', 'isWild' => true]
    ];
    
    // Generate results using server-side logic
    $results = generateSlotResults($symbols);
    
    // Calculate win amount based on results
    $winData = calculateWinAmount($results, $bet_amount, 6); // 6 is jackpot multiplier
    
    // Success response
    echo json_encode([
        'success' => true,
        'results' => $results,
        'win_amount' => $winData['amount'],
        'is_win' => $winData['isWin'],
        'win_type' => $winData['type'],
        'message' => $winData['message'],
        'winning_row' => $winData['winningRow'] ?? null
    ]);
    
} catch (Exception $e) {
    error_log("Slot result generation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Generate slot machine results using server-side logic
 */
function generateSlotResults($symbols) {
    // First, decide if this should be a winning spin based on probability
    $winChance = mt_rand(1, 100) / 100;
    
    // Balanced casino win probability: ~35% chance for any win (better player experience)
    if ($winChance < 0.35) {
        return generateWinningResults($symbols);
    } else {
        return generateLosingResults($symbols);
    }
}

/**
 * Generate winning slot results
 */
function generateWinningResults($symbols) {
    $winType = mt_rand(1, 100) / 100;
    
    // Create a 3x3 grid for the slot machine
    $grid = [
        [getRandomSymbol($symbols), getRandomSymbol($symbols), getRandomSymbol($symbols)], // Row 0 (top)
        [getRandomSymbol($symbols), getRandomSymbol($symbols), getRandomSymbol($symbols)], // Row 1 (middle)
        [getRandomSymbol($symbols), getRandomSymbol($symbols), getRandomSymbol($symbols)]  // Row 2 (bottom)
    ];
    
    if ($winType < 0.4) {
        // 40% of wins are horizontal line wins
        $winningRow = mt_rand(0, 2); // 0, 1, or 2
        $winningSymbol = getWeightedRandomSymbol($symbols);
        
        // Fill the winning row with the same symbol (possibly with wilds)
        $useWild = (mt_rand(1, 100) / 100) < 0.2; // 20% chance to include wild
        $wildSymbol = findWildSymbol($symbols);
        
        for ($col = 0; $col < 3; $col++) {
            if ($useWild && (mt_rand(1, 100) / 100) < 0.3) {
                $grid[$winningRow][$col] = $wildSymbol;
            } else {
                $grid[$winningRow][$col] = $winningSymbol;
            }
        }
        
        return convertGridToResults($grid, $winningRow, 'horizontal');
        
    } elseif ($winType < 0.7) {
        // 30% of wins are diagonal wins
        $isDiagonalTL = (mt_rand(1, 100) / 100) < 0.5; // Top-left to bottom-right vs top-right to bottom-left
        $winningSymbol = getWeightedRandomSymbol($symbols);
        
        if ($isDiagonalTL) {
            // Top-left to bottom-right diagonal: [0,0], [1,1], [2,2]
            $grid[0][0] = $winningSymbol;
            $grid[1][1] = $winningSymbol;
            $grid[2][2] = $winningSymbol;
        } else {
            // Top-right to bottom-left diagonal: [0,2], [1,1], [2,0]
            $grid[0][2] = $winningSymbol;
            $grid[1][1] = $winningSymbol;
            $grid[2][0] = $winningSymbol;
        }
        
        return convertGridToResults($grid, -1, $isDiagonalTL ? 'diagonal_tl' : 'diagonal_tr');
        
    } elseif ($winType < 0.9) {
        // 20% of wins are rarity line wins
        $winningRow = mt_rand(0, 2);
        $rarities = ['rare', 'epic', 'legendary'];
        $rarity = $rarities[mt_rand(0, 2)];
        $sameRaritySymbols = array_filter($symbols, function($s) use ($rarity) { return $s['rarity'] === $rarity; });
        
        if (count($sameRaritySymbols) >= 3) {
            $sameRarityArray = array_values($sameRaritySymbols);
            for ($col = 0; $col < 3; $col++) {
                $grid[$winningRow][$col] = $sameRarityArray[mt_rand(0, count($sameRarityArray) - 1)];
            }
            return convertGridToResults($grid, $winningRow, 'rarity');
        }
    } else {
        // 10% of wins are wild-based wins
        $wildSymbol = findWildSymbol($symbols);
        $winningRow = mt_rand(0, 2);
        
        // Place 2-3 wilds in the winning row
        $wildCount = (mt_rand(1, 100) / 100) < 0.5 ? 2 : 3;
        $positions = [0, 1, 2];
        shuffle($positions);
        $positions = array_slice($positions, 0, $wildCount);
        
        foreach ($positions as $pos) {
            $grid[$winningRow][$pos] = $wildSymbol;
        }
        
        return convertGridToResults($grid, $winningRow, 'wild');
    }
    
    // Fallback to horizontal line win
    $winningSymbol = getRandomSymbol($symbols);
    $grid[1][0] = $winningSymbol;
    $grid[1][1] = $winningSymbol;
    $grid[1][2] = $winningSymbol;
    
    return convertGridToResults($grid, 1, 'horizontal');
}

/**
 * Generate losing slot results
 */
function generateLosingResults($symbols) {
    $results = [];
    
    // Generate 3 symbols that definitely don't match any winning pattern
    for ($i = 0; $i < 3; $i++) {
        // Reduce wild appearance in losing combinations
        $attempts = 0;
        do {
            $symbol = getRandomSymbol($symbols);
            $attempts++;
        } while ($symbol['isWild'] && $attempts < 5 && (mt_rand(1, 100) / 100) < 0.7); // Reduce wild chance in losses
        
        $results[] = $symbol;
    }
    
    // Make sure it's actually a losing combination
    // If we accidentally created a win, modify it
    $testWin = calculateWinAmount($results, 1, 6);
    if ($testWin['isWin']) {
        // Make the middle symbol different to break any winning pattern
        $results[1] = getRandomSymbolDifferentFrom($symbols, [$results[0], $results[2]]);
    }
    
    return $results;
}

/**
 * Convert 3x3 grid to results format
 */
function convertGridToResults($grid, $winningRow, $winType) {
    $results = [];
    
    for ($col = 0; $col < 3; $col++) {
        $results[] = [
            'topSymbol' => $grid[0][$col],
            'middleSymbol' => $grid[1][$col],
            'bottomSymbol' => $grid[2][$col],
            'winningRow' => $winningRow,
            'winType' => $winType
        ];
    }
    
    return $results;
}

/**
 * Calculate win amount based on results
 */
function calculateWinAmount($results, $betAmount, $jackpotMultiplier) {
    // Convert results to 3x3 grid format
    $grid = [
        [$results[0]['topSymbol'] ?? $results[0], $results[1]['topSymbol'] ?? $results[1], $results[2]['topSymbol'] ?? $results[2]],       // Row 0 (top)
        [$results[0]['middleSymbol'] ?? $results[0], $results[1]['middleSymbol'] ?? $results[1], $results[2]['middleSymbol'] ?? $results[2]], // Row 1 (middle)
        [$results[0]['bottomSymbol'] ?? $results[0], $results[1]['bottomSymbol'] ?? $results[1], $results[2]['bottomSymbol'] ?? $results[2]]  // Row 2 (bottom)
    ];
    
    // Helper function to check if two symbols match (considering wilds)
    $symbolsMatch = function($sym1, $sym2) {
        return $sym1['isWild'] || $sym2['isWild'] || $sym1['image'] === $sym2['image'];
    };
    
    // Check for line matches in the 3x3 grid
    $checkLine = function($line) use ($symbolsMatch) {
        $s1 = $line[0];
        $s2 = $line[1];
        $s3 = $line[2];
        return $symbolsMatch($s1, $s2) && $symbolsMatch($s2, $s3) && $symbolsMatch($s1, $s3);
    };
    
    // Check all possible winning lines
    $lines = [
        // Horizontal lines
        ['symbols' => $grid[0], 'name' => 'Top Row', 'type' => 'horizontal', 'row' => 0],
        ['symbols' => $grid[1], 'name' => 'Middle Row', 'type' => 'horizontal', 'row' => 1],
        ['symbols' => $grid[2], 'name' => 'Bottom Row', 'type' => 'horizontal', 'row' => 2],
        // Diagonal lines
        ['symbols' => [$grid[0][0], $grid[1][1], $grid[2][2]], 'name' => 'Top-Left Diagonal', 'type' => 'diagonal_tl', 'row' => -1],
        ['symbols' => [$grid[0][2], $grid[1][1], $grid[2][0]], 'name' => 'Top-Right Diagonal', 'type' => 'diagonal_tr', 'row' => -1]
    ];
    
    // Find winning lines
    $winningLines = array_filter($lines, function($line) use ($checkLine) {
        return $checkLine($line['symbols']);
    });
    
    if (!empty($winningLines)) {
        // Use the first winning line found
        $winningLine = array_values($winningLines)[0];
        $symbols = $winningLine['symbols'];
        
        // Determine the base symbol (non-wild) for payout calculation
        $baseSymbol = $symbols[0]['isWild'] ? ($symbols[1]['isWild'] ? $symbols[2] : $symbols[1]) : $symbols[0];
        $wildCount = count(array_filter($symbols, function($s) { return $s['isWild']; }));
        
        // THREE WILDS - MEGA JACKPOT!
        if ($wildCount === 3) {
            return [
                'isWin' => true,
                'amount' => $betAmount * $jackpotMultiplier * 2,
                'type' => 'wild_line',
                'message' => '🌟 TRIPLE WILD MEGA JACKPOT! 🌟',
                'winningRow' => $winningLine['row']
            ];
        }
        
        // MYTHICAL JACKPOT (Lord Pixel line)
        if ($baseSymbol['rarity'] === 'mythical') {
            return [
                'isWin' => true,
                'amount' => $betAmount * $jackpotMultiplier * 1.5,
                'type' => 'mythical_jackpot',
                'message' => "💎 MYTHICAL {$winningLine['name']} JACKPOT! 💎",
                'winningRow' => $winningLine['row']
            ];
        }
        
        // Regular line wins
        $multiplier = $baseSymbol['level'] >= 8 ? $jackpotMultiplier : ($baseSymbol['level'] * 2);
        $wildBonus = $wildCount * 1;
        $diagonalBonus = strpos($winningLine['type'], 'diagonal') !== false ? 2 : 0;
        
        return [
            'isWin' => true,
            'amount' => $betAmount * ($multiplier + $wildBonus + $diagonalBonus),
            'type' => strpos($winningLine['type'], 'diagonal') !== false ? 'diagonal_exact' : 'straight_line',
            'message' => $wildCount > 0 ? 
                "🌟 WILD {$winningLine['name']}! 🌟" : 
                "🎯 {$winningLine['name']} WIN! 🎯",
            'winningRow' => $winningLine['row']
        ];
    }
    
    return [
        'isWin' => false,
        'amount' => 0,
        'type' => 'loss',
        'message' => 'Line up 3 across, hit the diagonals, or get wilds!'
    ];
}

/**
 * Helper functions
 */
function getRandomSymbol($symbols) {
    return $symbols[mt_rand(0, count($symbols) - 1)];
}

function getWeightedRandomSymbol($symbols) {
    $weights = array_map(function($s) { return max(1, 5 - $s['level']); }, $symbols);
    $totalWeight = array_sum($weights);
    $random = mt_rand(1, $totalWeight);
    $currentWeight = 0;
    
    foreach ($symbols as $symbol) {
        $currentWeight += max(1, 5 - $symbol['level']);
        if ($random <= $currentWeight) {
            return $symbol;
        }
    }
    
    return $symbols[count($symbols) - 1];
}

function findWildSymbol($symbols) {
    foreach ($symbols as $symbol) {
        if ($symbol['isWild']) {
            return $symbol;
        }
    }
    return $symbols[0]; // Fallback
}

function getRandomSymbolDifferentFrom($symbols, $excludeSymbols) {
    $attempts = 0;
    do {
        $symbol = getRandomSymbol($symbols);
        $attempts++;
    } while ($attempts < 10 && array_filter($excludeSymbols, function($excluded) use ($symbol) {
        return $excluded['image'] === $symbol['image'] || 
               ($excluded['rarity'] === $symbol['rarity'] && !$symbol['isWild']);
    }));
    
    return $symbol;
}
?> 