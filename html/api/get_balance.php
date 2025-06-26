<?php
// Simple balance API for debugging
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not logged in', 'balance' => 0]);
        exit;
    }
    
    require_once '../core/database.php';
    
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT SUM(amount) as balance FROM qr_coin_transactions WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $balance = $stmt->fetchColumn() ?? 0;
    
    echo json_encode([
        'success' => true,
        'balance' => (int)$balance,
        'user_id' => $_SESSION['user_id'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'balance' => 0]);
}
?>
