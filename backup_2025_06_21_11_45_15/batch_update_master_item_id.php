<?php
require_once __DIR__ . '/html/core/config.php';

echo "Fixing missing item mappings...\n";

try {
    $pdo->beginTransaction();
    
    // Get all items without mappings
    $stmt = $pdo->query("
        SELECT i.* 
        FROM items i 
        LEFT JOIN item_mapping im ON i.id = im.item_id 
        WHERE im.item_id IS NULL
    ");
    $unmapped_items = $stmt->fetchAll();
    
    echo "Found " . count($unmapped_items) . " items without mappings\n";
    
    foreach ($unmapped_items as $item) {
        echo "Processing item ID: {$item['id']}, Name: {$item['name']}\n";
        
        // Determine category based on item name and type
        $category = 'Odd or Unique Items'; // default
        $name_lower = strtolower($item['name']);
        
        if (stripos($name_lower, 'chip') !== false || stripos($name_lower, 'crisp') !== false || 
            stripos($name_lower, 'cheeto') !== false || stripos($name_lower, 'dorito') !== false) {
            $category = 'Chips and Savory Snacks';
        } elseif (stripos($name_lower, 'candy') !== false || stripos($name_lower, 'chocolate') !== false || 
                  stripos($name_lower, 'gum') !== false || stripos($name_lower, 'mint') !== false) {
            $category = 'Candy and Chocolate Bars';
        } elseif (stripos($name_lower, 'cookie') !== false || stripos($name_lower, 'oreo') !== false) {
            $category = 'Cookies (Brand-Name & Generic)';
        } elseif (stripos($name_lower, 'energy') !== false || stripos($name_lower, 'monster') !== false || 
                  stripos($name_lower, 'red bull') !== false || stripos($name_lower, 'jolt') !== false) {
            $category = 'Energy Drinks';
        } elseif (stripos($name_lower, 'water') !== false || stripos($name_lower, 'sparkling') !== false) {
            $category = 'Water and Flavored Water';
        } elseif (stripos($name_lower, 'soda') !== false || stripos($name_lower, 'cola') !== false || 
                  stripos($name_lower, 'pepsi') !== false || stripos($name_lower, 'coke') !== false ||
                  stripos($name_lower, 'sprite') !== false) {
            $category = 'Soft Drinks and Carbonated Beverages';
        } elseif (stripos($name_lower, 'juice') !== false || stripos($name_lower, 'tea') !== false) {
            $category = 'Juices and Bottled Teas';
        } elseif (stripos($name_lower, 'protein') !== false || stripos($name_lower, 'quest') !== false || 
                  stripos($name_lower, 'clif') !== false || stripos($name_lower, 'kind') !== false) {
            $category = 'Protein and Meal Replacement Bars';
        } elseif (stripos($name_lower, 'healthy') !== false || stripos($name_lower, 'organic') !== false) {
            $category = 'Healthy Snacks';
        }
        
        // Normalize type for master_items
        $normalized_type = 'other';
        if (in_array($item['type'], ['snack', 'drink', 'pizza', 'side'])) {
            $normalized_type = $item['type'];
        } elseif (in_array($item['type'], ['Beverages', 'Energy', 'Sports'])) {
            $normalized_type = 'drink';
        } elseif (in_array($item['type'], ['Snacks', 'Candy', 'Healthy'])) {
            $normalized_type = 'snack';
        }
        
        echo "  Category: {$category}, Type: {$normalized_type}\n";
        
        // Check if master_item already exists
        $stmt = $pdo->prepare("SELECT id FROM master_items WHERE name = ? AND type = ?");
        $stmt->execute([$item['name'], $normalized_type]);
        $master_item_id = $stmt->fetchColumn();
        
        if (!$master_item_id) {
            // Create master_item
            $stmt = $pdo->prepare("
                INSERT INTO master_items (name, category, type, suggested_price, suggested_cost, status) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $item['name'], 
                $category, 
                $normalized_type, 
                $item['price'], 
                $item['price'] * 0.7, 
                $item['status']
            ]);
            $master_item_id = $pdo->lastInsertId();
            echo "  Created master_item ID: {$master_item_id}\n";
        } else {
            echo "  Found existing master_item ID: {$master_item_id}\n";
        }
        
        // Verify the item ID exists before creating mapping
        $check_stmt = $pdo->prepare("SELECT id FROM items WHERE id = ?");
        $check_stmt->execute([$item['id']]);
        if (!$check_stmt->fetchColumn()) {
            echo "  ERROR: Item ID {$item['id']} not found in items table!\n";
            continue;
        }
        
        // Create mapping
        try {
            $stmt = $pdo->prepare("INSERT INTO item_mapping (master_item_id, item_id) VALUES (?, ?)");
            $stmt->execute([$master_item_id, $item['id']]);
            echo "  Successfully mapped item {$item['id']}\n";
        } catch (Exception $e) {
            echo "  ERROR creating mapping for item {$item['id']}: " . $e->getMessage() . "\n";
            break;
        }
    }
    
    $pdo->commit();
    echo "Successfully fixed all item mappings!\n";
    
    // Show final counts
    $stmt = $pdo->query("SELECT COUNT(*) FROM items");
    $total_items = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM item_mapping");
    $mapped_items = $stmt->fetchColumn();
    
    echo "Final result: {$mapped_items}/{$total_items} items now have category mappings\n";
    
} catch (Exception $e) {
    $pdo->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}
?> 