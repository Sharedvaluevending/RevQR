<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';

// Require business role
require_role('business');

// Set JSON response header
header('Content-Type: application/json');

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['item_ids']) || !is_array($input['item_ids'])) {
        throw new Exception('Invalid input data');
    }

    // Validate CSRF token
    if (!validate_csrf_token($input['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token');
    }

    $itemIds = array_filter(array_map('intval', $input['item_ids']));
    
    if (empty($itemIds)) {
        throw new Exception('No valid item IDs provided');
    }

    // Get business details
    $stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $business = $stmt->fetch();

    if (!$business) {
        throw new Exception('Business not found');
    }

    $addedCount = 0;
    $skippedCount = 0;
    $errors = [];

    // Begin transaction
    $pdo->beginTransaction();

    foreach ($itemIds as $itemId) {
        try {
            // Verify the master item exists
            $stmt = $pdo->prepare("SELECT id, name, suggested_price, suggested_cost FROM master_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $masterItem = $stmt->fetch();

            if (!$masterItem) {
                $errors[] = "Item ID $itemId not found";
                continue;
            }

            // Check if item is already in user's catalog
            $stmt = $pdo->prepare("SELECT id FROM user_catalog_items WHERE user_id = ? AND master_item_id = ?");
            $stmt->execute([$_SESSION['user_id'], $itemId]);
            
            if ($stmt->fetch()) {
                $skippedCount++;
                continue;
            }

            // Calculate initial performance metrics
            $margin = $masterItem['suggested_price'] - $masterItem['suggested_cost'];
            $marginPercentage = $masterItem['suggested_cost'] > 0 ? 
                ($margin / $masterItem['suggested_cost']) * 100 : 0;

            // Determine priority level based on margin
            $priorityLevel = 'medium';
            if ($marginPercentage > 50) {
                $priorityLevel = 'high';
            } elseif ($marginPercentage > 100) {
                $priorityLevel = 'critical';
            } elseif ($marginPercentage < 20) {
                $priorityLevel = 'low';
            }

            // Add item to user's catalog
            $stmt = $pdo->prepare("
                INSERT INTO user_catalog_items (
                    user_id, 
                    master_item_id, 
                    custom_price, 
                    custom_cost, 
                    target_margin,
                    priority_level,
                    performance_rating
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $itemId,
                $masterItem['suggested_price'],
                $masterItem['suggested_cost'],
                $marginPercentage,
                $priorityLevel,
                min(5.0, max(1.0, $marginPercentage / 20)) // Convert margin % to 1-5 rating
            ]);

            $catalogItemId = $pdo->lastInsertId();

            // Create initial analytics entry
            $stmt = $pdo->prepare("
                INSERT INTO catalog_analytics (
                    user_id,
                    catalog_item_id,
                    metric_date,
                    margin_percentage
                ) VALUES (?, ?, CURDATE(), ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $catalogItemId,
                $marginPercentage
            ]);

            // Add automatic tags based on item characteristics
            $tags = [];
            
            if ($marginPercentage > 50) {
                $tags[] = 'high-margin';
            }
            if ($marginPercentage < 20) {
                $tags[] = 'low-margin';
            }
            if ($masterItem['suggested_price'] > 3.00) {
                $tags[] = 'premium';
            }
            if ($masterItem['suggested_price'] < 1.00) {
                $tags[] = 'budget';
            }

            // Insert tags
            foreach ($tags as $tag) {
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO catalog_item_tags (catalog_item_id, tag_name) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$catalogItemId, $tag]);
            }

            $addedCount++;

        } catch (Exception $e) {
            $errors[] = "Error adding item '{$masterItem['name']}': " . $e->getMessage();
        }
    }

    // Commit transaction
    $pdo->commit();

    // Log the action
    error_log("User {$_SESSION['user_id']} added $addedCount items to catalog, skipped $skippedCount");

    // Return success response
    echo json_encode([
        'success' => true,
        'added_count' => $addedCount,
        'skipped_count' => $skippedCount,
        'errors' => $errors,
        'message' => "Successfully processed " . count($itemIds) . " items"
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Error in add_to_catalog.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 