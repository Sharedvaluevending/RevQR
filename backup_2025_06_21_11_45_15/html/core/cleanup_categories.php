<?php
require_once __DIR__ . '/config.php';

try {
    $pdo->beginTransaction();

    // Get all categories ordered by name
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name, id");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group categories by name
    $grouped = [];
    foreach ($categories as $cat) {
        if (!isset($grouped[$cat['name']])) {
            $grouped[$cat['name']] = [];
        }
        $grouped[$cat['name']][] = $cat['id'];
    }

    // For each group, keep the lowest ID and update items to use that ID
    foreach ($grouped as $name => $ids) {
        if (count($ids) > 1) {
            $keepId = min($ids);
            $deleteIds = array_diff($ids, [$keepId]);
            
            // Update items to use the kept category ID
            $updateStmt = $pdo->prepare("UPDATE items SET category_id = ? WHERE category_id IN (" . implode(',', $deleteIds) . ")");
            $updateStmt->execute([$keepId]);
            
            // Delete duplicate categories
            $deleteStmt = $pdo->prepare("DELETE FROM categories WHERE id IN (" . implode(',', $deleteIds) . ")");
            $deleteStmt->execute();
        }
    }

    $pdo->commit();
    echo "Categories deduplicated successfully!\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
} 