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
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('Invalid input data');
    }

    // Validate CSRF token
    if (!validate_csrf_token($input['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token');
    }

    $action = $input['action'];
    $itemId = intval($input['item_id'] ?? 0);

    if ($itemId <= 0) {
        throw new Exception('Invalid item ID');
    }

    // Verify the catalog item belongs to the user
    $stmt = $pdo->prepare("SELECT id FROM user_catalog_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$itemId, $_SESSION['user_id']]);
    $catalogItem = $stmt->fetch();

    if (!$catalogItem) {
        throw new Exception('Catalog item not found or access denied');
    }

    switch ($action) {
        case 'toggle_favorite':
            $stmt = $pdo->prepare("
                UPDATE user_catalog_items 
                SET is_favorite = NOT is_favorite, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$itemId, $_SESSION['user_id']]);
            
            // Get the new favorite status
            $stmt = $pdo->prepare("SELECT is_favorite FROM user_catalog_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $newStatus = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'is_favorite' => (bool)$newStatus,
                'message' => $newStatus ? 'Added to favorites' : 'Removed from favorites'
            ]);
            break;

        case 'remove_item':
            // Begin transaction
            $pdo->beginTransaction();
            
            try {
                // Remove related analytics
                $stmt = $pdo->prepare("DELETE FROM catalog_analytics WHERE catalog_item_id = ?");
                $stmt->execute([$itemId]);
                
                // Remove related tags
                $stmt = $pdo->prepare("DELETE FROM catalog_item_tags WHERE catalog_item_id = ?");
                $stmt->execute([$itemId]);
                
                // Remove price history
                $stmt = $pdo->prepare("DELETE FROM catalog_price_history WHERE catalog_item_id = ?");
                $stmt->execute([$itemId]);
                
                // Remove from promotions
                $stmt = $pdo->prepare("DELETE FROM catalog_promotion_items WHERE catalog_item_id = ?");
                $stmt->execute([$itemId]);
                
                // Remove from combos
                $stmt = $pdo->prepare("DELETE FROM catalog_combo_items WHERE catalog_item_id = ?");
                $stmt->execute([$itemId]);
                
                // Remove benchmarks
                $stmt = $pdo->prepare("DELETE FROM catalog_benchmarks WHERE catalog_item_id = ?");
                $stmt->execute([$itemId]);
                
                // Finally remove the catalog item
                $stmt = $pdo->prepare("DELETE FROM user_catalog_items WHERE id = ? AND user_id = ?");
                $stmt->execute([$itemId, $_SESSION['user_id']]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Item removed from catalog successfully'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;

        case 'update_priority':
            $priority = $input['priority'] ?? '';
            if (!in_array($priority, ['low', 'medium', 'high', 'critical'])) {
                throw new Exception('Invalid priority level');
            }
            
            $stmt = $pdo->prepare("
                UPDATE user_catalog_items 
                SET priority_level = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$priority, $itemId, $_SESSION['user_id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Priority updated successfully'
            ]);
            break;

        case 'update_pricing':
            $customPrice = floatval($input['custom_price'] ?? 0);
            $customCost = floatval($input['custom_cost'] ?? 0);
            
            if ($customPrice < 0 || $customCost < 0) {
                throw new Exception('Price and cost must be non-negative');
            }
            
            if ($customPrice > 999.99 || $customCost > 999.99) {
                throw new Exception('Price and cost must be less than $1000');
            }
            
            // Get current values for price history
            $stmt = $pdo->prepare("SELECT custom_price, custom_cost FROM user_catalog_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $currentValues = $stmt->fetch();
            
            // Update pricing
            $stmt = $pdo->prepare("
                UPDATE user_catalog_items 
                SET custom_price = ?, custom_cost = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$customPrice, $customCost, $itemId, $_SESSION['user_id']]);
            
            // Record price history
            if ($currentValues['custom_price'] != $customPrice || $currentValues['custom_cost'] != $customCost) {
                $stmt = $pdo->prepare("
                    INSERT INTO catalog_price_history (
                        catalog_item_id, old_price, new_price, old_cost, new_cost, 
                        change_reason, changed_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $itemId,
                    $currentValues['custom_price'],
                    $customPrice,
                    $currentValues['custom_cost'],
                    $customCost,
                    'Manual update via catalog interface',
                    $_SESSION['user_id']
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Pricing updated successfully'
            ]);
            break;

        case 'add_tag':
            $tagName = trim($input['tag_name'] ?? '');
            if (empty($tagName) || strlen($tagName) > 50) {
                throw new Exception('Tag name must be 1-50 characters');
            }
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO catalog_item_tags (catalog_item_id, tag_name) 
                VALUES (?, ?)
            ");
            $stmt->execute([$itemId, $tagName]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Tag added successfully'
            ]);
            break;

        case 'remove_tag':
            $tagName = trim($input['tag_name'] ?? '');
            if (empty($tagName)) {
                throw new Exception('Tag name is required');
            }
            
            $stmt = $pdo->prepare("
                DELETE FROM catalog_item_tags 
                WHERE catalog_item_id = ? AND tag_name = ?
            ");
            $stmt->execute([$itemId, $tagName]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Tag removed successfully'
            ]);
            break;

        case 'update_notes':
            $notes = trim($input['notes'] ?? '');
            if (strlen($notes) > 1000) {
                throw new Exception('Notes must be less than 1000 characters');
            }
            
            $stmt = $pdo->prepare("
                UPDATE user_catalog_items 
                SET notes = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notes, $itemId, $_SESSION['user_id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Notes updated successfully'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    error_log("Error in catalog_actions.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 