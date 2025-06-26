<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Get JSON data from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    // Get business ID
    $stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $business = $stmt->fetch();

    if (!$business) {
        throw new Exception('Business not found');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Handle batch updates
    if (isset($data['updates']) && is_array($data['updates'])) {
        $stmt = $pdo->prepare("
            UPDATE business_items 
            SET retail_price = ?, 
                cost_price = ?, 
                popularity = ?, 
                shelf_life = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE business_id = ? AND name = ?
        ");

        foreach ($data['updates'] as $update) {
            // Validate required fields
            if (!isset($update['name']) || !isset($update['retail_price']) || 
                !isset($update['cost_price']) || !isset($update['popularity']) || 
                !isset($update['shelf_life'])) {
                throw new Exception('Missing required fields in update data');
            }

            // Validate prices
            if ($update['cost_price'] > $update['retail_price']) {
                throw new Exception("Cost price cannot be higher than retail price for {$update['name']}");
            }

            $stmt->execute([
                $update['retail_price'],
                $update['cost_price'],
                $update['popularity'],
                $update['shelf_life'],
                $business['id'],
                $update['name']
            ]);
        }
    } else {
        // Handle single item update (backward compatibility)
        if (!isset($data['name']) || !isset($data['retail_price']) || 
            !isset($data['cost_price']) || !isset($data['popularity']) || 
            !isset($data['shelf_life'])) {
            throw new Exception('Missing required fields');
        }

        if ($data['cost_price'] > $data['retail_price']) {
            throw new Exception('Cost price cannot be higher than retail price');
        }

        $stmt = $pdo->prepare("
            UPDATE business_items 
            SET retail_price = ?, 
                cost_price = ?, 
                popularity = ?, 
                shelf_life = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE business_id = ? AND name = ?
        ");
        
        $stmt->execute([
            $data['retail_price'],
            $data['cost_price'],
            $data['popularity'],
            $data['shelf_life'],
            $business['id'],
            $data['name']
        ]);
    }

    // Commit transaction
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 