<?php
/**
 * User Balance API Endpoint
 * Returns current user's QR coin balance
 */

// Start session and check auth
session_start();
header('Content-Type: application/json; charset=utf-8');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    require_once __DIR__ . '/../core/config.php';
    require_once __DIR__ . '/../core/qr_coin_manager.php';
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $balance = QRCoinManager::getBalance($user_id);
    
    // Get additional user info if requested
    $include_details = $_GET['details'] ?? false;
    $response = [
        'success' => true,
        'balance' => $balance,
        'formatted_balance' => number_format($balance),
        'user_id' => $user_id
    ];
    
    if ($include_details) {
        // Get recent transactions
        $stmt = $pdo->prepare("
            SELECT transaction_type, amount, description, created_at, reference_type
            FROM qr_coin_transactions 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get spending stats
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN transaction_type = 'earning' THEN amount ELSE 0 END) as total_earned,
                SUM(CASE WHEN transaction_type = 'spending' THEN ABS(amount) ELSE 0 END) as total_spent,
                COUNT(*) as total_transactions
            FROM qr_coin_transactions 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $response['recent_transactions'] = $recent_transactions;
        $response['stats'] = $stats;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("User balance API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 