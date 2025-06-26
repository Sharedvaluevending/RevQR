<?php
/**
 * Simple Balance Update - Direct database approach
 * Bypasses all session complexity
 */

header('Content-Type: application/json');

try {
    // Direct database connection without session includes
    $pdo = new PDO(
        "mysql:host=localhost;dbname=revenueqr",
        "root",
        "root",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Get user ID from input (we'll pass it from JavaScript)
    $user_id = (int)($input['user_id'] ?? 0);
    $balance_change = (int)($input['balance_change'] ?? 0);
    $description = $input['description'] ?? 'Blackjack game';
    
    if ($user_id <= 0) {
        throw new Exception('Invalid user ID');
    }
    
    if ($balance_change == 0) {
        throw new Exception('No balance change specified');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get current balance (we store spendings as negative amounts, so a simple SUM works)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS balance FROM qr_coin_transactions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $current_balance = (int) $stmt->fetch()['balance'];
    
    // Add the transaction
    // Determine transaction type and preserve the sign of the balance change so that
    // earnings are positive and spendings are negative. This keeps the schema
    // consistent with QRCoinManager where spending rows are stored with a
    // negative amount value.
    $transaction_type = $balance_change > 0 ? 'earning' : 'spending';
    // KEEP the original sign for the amount so balance math works globally
    $transaction_amount = $balance_change;
    $transaction_category = $balance_change > 0 ? 'casino_win' : 'casino_bet';
    
    $stmt = $pdo->prepare("
        INSERT INTO qr_coin_transactions 
        (user_id, transaction_type, category, amount, description, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $transaction_type,
        $transaction_category,
        $transaction_amount,
        $description
    ]);
    
    // Get new balance
    $new_balance = $current_balance + $balance_change;
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'old_balance' => $current_balance,
        'new_balance' => $new_balance,
        'change' => $balance_change,
        'message' => 'Balance updated successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?> 