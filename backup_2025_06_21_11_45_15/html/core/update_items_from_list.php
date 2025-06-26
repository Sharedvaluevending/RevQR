<?php
require_once __DIR__ . '/config.php';

try {
    $pdo->beginTransaction();

    // Delete all existing items instead of truncating
    $pdo->exec("DELETE FROM items");

    // Read the full list from itemlist.txt
    $itemListFile = __DIR__ . '/../assets/js/itemlist.txt';
    if (!file_exists($itemListFile)) {
        throw new Exception("Item list file not found: $itemListFile");
    }

    $itemList = file_get_contents($itemListFile);
    $items = explode("\n", trim($itemList));

    // Prepare insert statement
    $stmt = $pdo->prepare("
        INSERT INTO items (business_id, name, category_id, brand, retail_price, cost_price, popularity, shelf_life, is_seasonal, is_imported, is_healthy, status)
        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");

    foreach ($items as $item) {
        $item = trim($item);
        // Skip empty items, nayax.com, saveur.com, and items with no valid name
        if (empty($item) || 
            stripos($item, 'nayax.com') !== false || 
            stripos($item, 'saveur.com') !== false ||
            trim(str_replace('.', '', $item)) === '') {
            continue;
        }

        // Remove dots from the item name and truncate to 100 characters
        $name = substr(str_replace('.', '', $item), 0, 100);
        
        // Skip if the name is empty after cleaning
        if (trim($name) === '') {
            continue;
        }

        $categoryId = 1; // Default category ID, adjust as needed
        $brand = explode(' ', $name)[0]; // Simple brand extraction
        $retailPrice = 1.25; // Default retail price, adjust as needed
        $costPrice = 0.90; // Default cost price, adjust as needed
        $popularity = 'medium'; // Default popularity, adjust as needed
        $shelfLife = 180; // Default shelf life, adjust as needed
        $isSeasonal = 0;
        $isImported = 0;
        $isHealthy = 0;

        $stmt->execute([
            $name, $categoryId, $brand, $retailPrice, $costPrice, $popularity, $shelfLife, $isSeasonal, $isImported, $isHealthy
        ]);
    }

    $pdo->commit();
    echo "Items updated successfully from the full list!\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
} 