<?php
/**
 * LEGACY COMPATIBILITY LAYER
 * Provides backward compatibility for legacy balance functions
 * This should be temporary - all code should be updated to use QRCoinManager directly
 * 
 * @deprecated Use QRCoinManager directly
 */

require_once __DIR__ . '/qr_coin_manager.php';

/**
 * Legacy getUserStats function - DEPRECATED
 * @deprecated Use QRCoinManager::getBalance() instead
 */
function getUserStats($user_id) {
    error_log('DEPRECATED: getUserStats() called. Use QRCoinManager::getBalance() instead.');
    
    return [
        'user_points' => QRCoinManager::getBalance($user_id),
        'qr_balance' => QRCoinManager::getBalance($user_id)
    ];
}

/**
 * Legacy updateUserPoints function - DEPRECATED  
 * @deprecated Use QRCoinManager::addTransaction() instead
 */
function updateUserPoints($user_id, $points, $description = 'Legacy point update') {
    error_log('DEPRECATED: updateUserPoints() called. Use QRCoinManager::addTransaction() instead.');
    
    return QRCoinManager::addTransaction(
        $user_id,
        'earning',
        'legacy_update',
        $points,
        $description
    );
}

/**
 * Legacy deductUserPoints function - DEPRECATED
 * @deprecated Use QRCoinManager::spendCoins() instead  
 */
function deductUserPoints($user_id, $points, $description = 'Legacy point deduction') {
    error_log('DEPRECATED: deductUserPoints() called. Use QRCoinManager::spendCoins() instead.');
    
    return QRCoinManager::spendCoins(
        $user_id,
        $points,
        'legacy_deduction',
        $description
    );
}

// Add deprecation notices
if (function_exists('trigger_error')) {
    trigger_error('Legacy balance functions are deprecated. Update code to use QRCoinManager.', E_USER_DEPRECATED);
}

?>