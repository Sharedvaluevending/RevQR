<?php
/**
 * Get Casino Spin Information API
 * Returns current spin availability including spin pack bonuses
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/functions.php';
require_once __DIR__ . '/../../core/casino_spin_manager.php';

// Set JSON header
header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!is_logged_in()) {
        throw new Exception('User not logged in');
    }
    
    $user_id = $_SESSION['user_id'];
    $business_id = $_GET['business_id'] ?? null;
    
    if (!$business_id) {
        throw new Exception('Business ID required');
    }
    
    // Initialize casino spin manager
    CasinoSpinManager::init($pdo);
    
    // Get current spin availability
    $spin_info = CasinoSpinManager::getAvailableSpins($user_id, $business_id);
    
    // Get spin pack status for additional info
    $pack_status = CasinoSpinManager::getSpinPackStatus($user_id);
    
    // Format response for slot machine
    $response = [
        'success' => true,
        'spinInfo' => [
            'base_spins' => $spin_info['base_spins'],
            'bonus_spins' => $spin_info['bonus_spins'],
            'total_spins' => $spin_info['total_spins'],
            'spins_used' => $spin_info['spins_used'],
            'spins_remaining' => $spin_info['spins_remaining'],
            'has_spin_pack' => $pack_status['has_packs'],
            'active_packs' => $spin_info['active_packs'],
            'pack_message' => $pack_status['message']
        ],
        'timestamp' => time()
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Get Spin Info API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 