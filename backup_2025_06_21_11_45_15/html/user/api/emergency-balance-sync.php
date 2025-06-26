<?php
/**
 * Emergency Balance Sync Endpoint
 * Forces balance recalculation and correction when normal sync fails
 */
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/qr_coin_manager.php';
require_once __DIR__ . '/../../core/balance_validator.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    // Check authentication
    if (!is_logged_in()) {
        echo json_encode([
            'success' => false,
            'error' => 'User not authenticated',
            'code' => 401,
            'action' => 'redirect_login'
        ]);
        exit;
    }
    
    // Check role
    if (!has_role('user')) {
        echo json_encode([
            'success' => false,
            'error' => 'Insufficient permissions',
            'code' => 403
        ]);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Get the sync mode from request
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $force_recovery = $input['force_recovery'] ?? false;
    
    // Initialize balance validator
    $validator = new BalanceValidator($pdo);
    
    if ($force_recovery) {
        // Emergency recovery mode - force balance correction
        error_log("Emergency balance recovery requested for user $user_id");
        
        $recovery_result = $validator->emergencyBalanceRecovery($user_id);
        
        if ($recovery_result['success']) {
            echo json_encode([
                'success' => true,
                'balance' => $recovery_result['balance'],
                'message' => 'Emergency balance recovery completed',
                'correction_applied' => $recovery_result['correction_applied'],
                'recovery_performed' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => $recovery_result['error'],
                'code' => 500
            ]);
        }
    } else {
        // Normal enhanced balance check with multiple verification points
        $attempts = [
            'qr_manager' => null,
            'direct_calculation' => null,
            'validation_result' => null
        ];
        
        // Method 1: QRCoinManager
        try {
            $attempts['qr_manager'] = QRCoinManager::getBalance($user_id);
        } catch (Exception $e) {
            error_log("QRCoinManager balance error for user $user_id: " . $e->getMessage());
        }
        
        // Method 2: Direct calculation
        try {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as balance 
                FROM qr_coin_transactions 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $attempts['direct_calculation'] = (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Direct calculation error for user $user_id: " . $e->getMessage());
        }
        
        // Check for consistency
        $consistent_balance = null;
        $needs_recovery = false;
        
        if ($attempts['qr_manager'] === $attempts['direct_calculation']) {
            $consistent_balance = $attempts['qr_manager'];
        } else {
            $needs_recovery = true;
            // Use direct calculation as source of truth
            $consistent_balance = $attempts['direct_calculation'];
            
            error_log("Balance inconsistency detected for user $user_id: QRManager={$attempts['qr_manager']}, Direct={$attempts['direct_calculation']}");
        }
        
        // If balance is null, we have a serious issue
        if ($consistent_balance === null) {
            echo json_encode([
                'success' => false,
                'error' => 'Unable to determine balance from any method',
                'code' => 500,
                'should_resync' => true,
                'needs_recovery' => true
            ]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'balance' => $consistent_balance,
            'needs_recovery' => $needs_recovery,
            'should_resync' => $needs_recovery,
            'debug_info' => [
                'qr_manager_balance' => $attempts['qr_manager'],
                'direct_calculation' => $attempts['direct_calculation'],
                'method_used' => $needs_recovery ? 'direct_calculation' : 'qr_manager'
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Emergency balance sync error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Emergency sync failed: ' . $e->getMessage(),
        'code' => 500,
        'should_resync' => true
    ]);
}
?> 