<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/qr_coin_manager.php';

// Require user role
require_role('user');

header('Content-Type: application/json');

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['level']) || !isset($input['rewards'])) {
        throw new Exception('Invalid request data');
    }
    
    $level = (int) $input['level'];
    $rewards = $input['rewards'];
    $user_id = $_SESSION['user_id'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if user has already received rewards for this level
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM level_up_rewards 
        WHERE user_id = ? AND level = ?
    ");
    $stmt->execute([$user_id, $level]);
    
    if ($stmt->fetchColumn() > 0) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => 'Rewards already granted for this level']);
        exit;
    }
    
    // Grant QR coins
    if (isset($rewards['qr_coins']) && $rewards['qr_coins'] > 0) {
        QRCoinManager::addTransaction(
            $user_id,
            'earning',
            'level_up_bonus',
            $rewards['qr_coins'],
            "Level {$level} achievement bonus",
            ['level' => $level, 'reward_type' => 'level_up']
        );
    }
    
    // Grant free spins and votes by adding to user rewards table
    if (isset($rewards['free_spin_wheel']) && $rewards['free_spin_wheel'] > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO user_rewards (user_id, reward_type, quantity, expires_at, description)
            VALUES (?, 'free_spin_wheel', ?, DATE_ADD(NOW(), INTERVAL 7 DAY), ?)
        ");
        $stmt->execute([$user_id, $rewards['free_spin_wheel'], "Level {$level} free wheel spin"]);
    }
    
    if (isset($rewards['free_slot_spin']) && $rewards['free_slot_spin'] > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO user_rewards (user_id, reward_type, quantity, expires_at, description)
            VALUES (?, 'free_slot_spin', ?, DATE_ADD(NOW(), INTERVAL 7 DAY), ?)
        ");
        $stmt->execute([$user_id, $rewards['free_slot_spin'], "Level {$level} free slot spin"]);
    }
    
    if (isset($rewards['free_vote']) && $rewards['free_vote'] > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO user_rewards (user_id, reward_type, quantity, expires_at, description)
            VALUES (?, 'free_vote', ?, DATE_ADD(NOW(), INTERVAL 7 DAY), ?)
        ");
        $stmt->execute([$user_id, $rewards['free_vote'], "Level {$level} free vote"]);
    }
    
    // Record that rewards were granted for this level
    $stmt = $pdo->prepare("
        INSERT INTO level_up_rewards (user_id, level, qr_coins_granted, rewards_data, granted_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id, 
        $level, 
        $rewards['qr_coins'] ?? 0,
        json_encode($rewards)
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Level up rewards granted successfully',
        'level' => $level,
        'rewards_granted' => $rewards
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Level rewards error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error granting rewards: ' . $e->getMessage()
    ]);
}
?> 