<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';

// Set JSON header
header('Content-Type: application/json');

// Require business role
require_role('business');

// Get business details for validation
$stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Business not found']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate JSON input
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format']);
    exit;
}

// Validate CSRF token
if (!isset($input['csrf_token']) || !validate_csrf_token($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate input structure
if (!isset($input['changes']) || !is_array($input['changes'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input format - changes array required']);
    exit;
}

// Validate changes array is not empty
if (empty($input['changes'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No changes provided']);
    exit;
}

// Validate maximum number of changes (prevent abuse)
if (count($input['changes']) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Too many changes - maximum 100 items per request']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Prepare the update statement
    $stmt = $pdo->prepare("
        UPDATE master_items 
        SET suggested_price = ?,
            suggested_cost = ?,
            category = ?,
            status = ?,
            is_imported = ?,
            is_healthy = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    // Prepare audit log statement
    $auditStmt = $pdo->prepare("
        INSERT INTO item_audit_log (
            item_id, user_id, business_id, action, old_values, new_values, created_at
        ) VALUES (?, ?, ?, 'update', ?, ?, NOW())
    ");

    $updatedCount = 0;
    $errors = [];

    foreach ($input['changes'] as $index => $change) {
        // Validate required fields
        $requiredFields = ['id', 'suggested_price', 'suggested_cost'];
        foreach ($requiredFields as $field) {
            if (!isset($change[$field])) {
                $errors[] = "Item #" . ($index + 1) . ": Missing required field '$field'";
                continue 2; // Skip to next change
            }
        }

        // Validate and sanitize data
        $itemId = filter_var($change['id'], FILTER_VALIDATE_INT);
        if ($itemId === false || $itemId <= 0) {
            $errors[] = "Item #" . ($index + 1) . ": Invalid item ID";
            continue;
        }

        // Validate prices
        $suggestedPrice = filter_var($change['suggested_price'], FILTER_VALIDATE_FLOAT);
        $suggestedCost = filter_var($change['suggested_cost'], FILTER_VALIDATE_FLOAT);

        if ($suggestedPrice === false || $suggestedPrice < 0 || $suggestedPrice > 999.99) {
            $errors[] = "Item #" . ($index + 1) . ": Invalid suggested price (must be between 0 and 999.99)";
            continue;
        }

        if ($suggestedCost === false || $suggestedCost < 0 || $suggestedCost > 999.99) {
            $errors[] = "Item #" . ($index + 1) . ": Invalid suggested cost (must be between 0 and 999.99)";
            continue;
        }

        // Validate category
        $allowedCategories = ['snacks', 'beverages', 'candy', 'chips', 'energy_drinks', 'healthy', 'other'];
        $category = isset($change['category']) ? trim($change['category']) : 'snacks';
        if (!in_array($category, $allowedCategories)) {
            $category = 'other';
        }

        // Validate status
        $allowedStatuses = ['active', 'inactive'];
        $status = isset($change['status']) ? trim($change['status']) : 'active';
        if (!in_array($status, $allowedStatuses)) {
            $status = 'active';
        }

        // Validate boolean flags
        $isImported = isset($change['is_imported']) ? (bool)$change['is_imported'] : false;
        $isHealthy = isset($change['is_healthy']) ? (bool)$change['is_healthy'] : false;

        // Get current values for audit log
        $currentStmt = $pdo->prepare("SELECT * FROM master_items WHERE id = ?");
        $currentStmt->execute([$itemId]);
        $currentItem = $currentStmt->fetch();

        if (!$currentItem) {
            $errors[] = "Item #" . ($index + 1) . ": Item not found";
            continue;
        }

        // Prepare audit data
        $oldValues = json_encode([
            'suggested_price' => $currentItem['suggested_price'],
            'suggested_cost' => $currentItem['suggested_cost'],
            'category' => $currentItem['category'],
            'status' => $currentItem['status'],
            'is_imported' => $currentItem['is_imported'],
            'is_healthy' => $currentItem['is_healthy']
        ]);

        $newValues = json_encode([
            'suggested_price' => $suggestedPrice,
            'suggested_cost' => $suggestedCost,
            'category' => $category,
            'status' => $status,
            'is_imported' => $isImported,
            'is_healthy' => $isHealthy
        ]);

        // Execute update
        $updateResult = $stmt->execute([
            $suggestedPrice,
            $suggestedCost,
            $category,
            $status,
            $isImported ? 1 : 0,
            $isHealthy ? 1 : 0,
            $itemId
        ]);

        if ($updateResult && $stmt->rowCount() > 0) {
            $updatedCount++;
            
            // Log the update for audit trail
            $auditStmt->execute([
                $itemId,
                $_SESSION['user_id'],
                $business['id'],
                $oldValues,
                $newValues
            ]);

            // Log for debugging (remove in production)
            error_log("Updated item ID {$itemId}: Price={$suggestedPrice}, Cost={$suggestedCost}, Margin=" . ($suggestedPrice - $suggestedCost));
        } else {
            $errors[] = "Item #" . ($index + 1) . ": Failed to update item";
        }
    }

    // Check if we have any successful updates
    if ($updatedCount > 0) {
        $pdo->commit();
        
        $response = ['success' => true, 'updated_count' => $updatedCount];
        if (!empty($errors)) {
            $response['warnings'] = $errors;
            $response['message'] = "Updated {$updatedCount} item(s) with " . count($errors) . " warning(s)";
        } else {
            $response['message'] = "Successfully updated {$updatedCount} item(s)";
        }
        
        echo json_encode($response);
    } else {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'No items were updated',
            'errors' => $errors
        ]);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error updating items: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error updating items: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred']);
} 