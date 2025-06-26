<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';

// Require business role
require_role('business');

// Verify CSRF token
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['changes']) || !is_array($data['changes'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Prepare update statement
    $stmt = $pdo->prepare("
        UPDATE master_items 
        SET type = :type,
            suggested_price = :suggested_price,
            suggested_cost = :suggested_cost,
            status = :status,
            is_seasonal = :is_seasonal,
            is_imported = :is_imported,
            is_healthy = :is_healthy
        WHERE id = :id
    ");

    // Update each item
    foreach ($data['changes'] as $change) {
        $stmt->execute([
            'id' => $change['id'],
            'type' => $change['type'],
            'suggested_price' => $change['suggested_price'],
            'suggested_cost' => $change['suggested_cost'],
            'status' => $change['status'],
            'is_seasonal' => $change['is_seasonal'] ?? false,
            'is_imported' => $change['is_imported'] ?? false,
            'is_healthy' => $change['is_healthy'] ?? false
        ]);
    }

    // Commit transaction
    $pdo->commit();

    // Log the update
    error_log("Master items updated: " . json_encode($data['changes']));

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log("Error updating master items: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating items: ' . $e->getMessage()
    ]);
} 