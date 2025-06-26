<?php
require_once __DIR__ . '/../../core/config/database.php';
require_once __DIR__ . '/../../core/auth.php';

header('Content-Type: application/json');

// Check if user is logged in and is a business
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$race_id = intval($_POST['race_id'] ?? 0);
$business_id = $_SESSION['business_id'];

if (!$race_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid race ID']);
    exit;
}

try {
    $conn->beginTransaction();
    
    // Verify the race belongs to this business and can be cancelled
    $stmt = $conn->prepare("
        SELECT id, race_name, status, business_id, prize_pool_qr_coins 
        FROM business_races 
        WHERE id = ? AND business_id = ? AND status IN ('active', 'approved', 'pending')
    ");
    $stmt->execute([$race_id, $business_id]);
    $race = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$race) {
        throw new Exception('Race not found or cannot be cancelled');
    }
    
    // Get all bets for this race
    $stmt = $conn->prepare("
        SELECT rb.id, rb.user_id, rb.bet_amount_qr_coins, rb.horse_id,
               u.username, rh.horse_name
        FROM race_bets rb
        JOIN users u ON rb.user_id = u.id  
        JOIN race_horses rh ON rb.horse_id = rh.id
        WHERE rb.race_id = ? AND rb.status = 'active'
    ");
    $stmt->execute([$race_id]);
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_refunded = 0;
    $refund_count = 0;
    
    // Process refunds for each bet
    foreach ($bets as $bet) {
        // Mark bet as cancelled
        $stmt = $conn->prepare("
            UPDATE race_bets 
            SET status = 'cancelled', updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$bet['id']]);
        
        // Refund QR coins to user's wallet
        $stmt = $conn->prepare("
            UPDATE users 
            SET qr_coins = qr_coins + ? 
            WHERE id = ?
        ");
        $stmt->execute([$bet['bet_amount_qr_coins'], $bet['user_id']]);
        
        // Log the refund transaction
        $stmt = $conn->prepare("
            INSERT INTO qr_coin_transactions (user_id, amount, transaction_type, description, created_at)
            VALUES (?, ?, 'refund', ?, NOW())
        ");
        $refund_description = "Race cancelled - Bet refund for '{$race['race_name']}' on horse '{$bet['horse_name']}'";
        $stmt->execute([$bet['user_id'], $bet['bet_amount_qr_coins'], $refund_description]);
        
        $total_refunded += $bet['bet_amount_qr_coins'];
        $refund_count++;
    }
    
    // Update race status to cancelled
    $stmt = $conn->prepare("
        UPDATE business_races 
        SET status = 'cancelled', 
            end_time = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$race_id]);
    
    // Refund prize pool to business wallet if they have one
    $stmt = $conn->prepare("
        UPDATE business_accounts 
        SET qr_coin_balance = qr_coin_balance + ?
        WHERE id = ?
    ");
    $stmt->execute([$race['prize_pool_qr_coins'], $business_id]);
    
    // Log business refund transaction
    $stmt = $conn->prepare("
        INSERT INTO business_wallet_transactions (business_id, amount, transaction_type, description, created_at)
        VALUES (?, ?, 'refund', ?, NOW())
    ");
    $business_refund_description = "Prize pool refund - Race '{$race['race_name']}' cancelled";
    $stmt->execute([$business_id, $race['prize_pool_qr_coins'], $business_refund_description]);
    
    // Log the race cancellation for audit
    $stmt = $conn->prepare("
        INSERT INTO race_audit_log (race_id, business_id, action_type, description, created_at)
        VALUES (?, ?, 'cancelled', ?, NOW())
    ");
    $audit_description = "Race cancelled by business. {$refund_count} bets refunded totaling {$total_refunded} QR coins.";
    $stmt->execute([$race_id, $business_id, $audit_description]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Race cancelled successfully. {$refund_count} bets refunded totaling {$total_refunded} QR coins.",
        'refund_count' => $refund_count,
        'total_refunded' => $total_refunded
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error cancelling race: ' . $e->getMessage()]);
}
?> 