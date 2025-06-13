<?php
require_once __DIR__ . '/config.php';

// Function to get category ID by name
function getCategoryId($pdo, $categoryName) {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute([$categoryName]);
    return $stmt->fetchColumn();
}

// Function to determine popularity based on brand and type
function determinePopularity($name, $brand) {
    $highPopularityBrands = ['Oreo', 'Chips Ahoy', 'Lay\'s', 'Doritos', 'Coca-Cola', 'Pepsi', 'Red Bull', 'Monster'];
    $lowPopularityKeywords = ['sugar-free', 'diet', 'low-carb', 'keto', 'vegan', 'gluten-free'];
    
    foreach ($highPopularityBrands as $brand) {
        if (stripos($name, $brand) !== false) {
            return 'high';
        }
    }
    
    foreach ($lowPopularityKeywords as $keyword) {
        if (stripos($name, $keyword) !== false) {
            return 'low';
        }
    }
    
    return 'medium';
}

// Function to determine shelf life based on category and type
function determineShelfLife($category, $name) {
    if (stripos($category, 'Water') !== false) {
        return 365;
    }
    if (stripos($category, 'Chips') !== false || stripos($category, 'Snacks') !== false) {
        return 90;
    }
    if (stripos($category, 'Cookies') !== false || stripos($category, 'Candy') !== false) {
        return 180;
    }
    if (stripos($category, 'Drinks') !== false) {
        return 180;
    }
    return 180; // Default
}

// Function to determine if item is seasonal
function isSeasonal($name) {
    $seasonalKeywords = ['holiday', 'christmas', 'halloween', 'easter', 'valentine', 'summer', 'winter'];
    foreach ($seasonalKeywords as $keyword) {
        if (stripos($name, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

// Function to determine if item is imported
function isImported($name) {
    $importedKeywords = ['import', 'japanese', 'korean', 'european', 'uk', 'australian'];
    foreach ($importedKeywords as $keyword) {
        if (stripos($name, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

// Function to determine if item is healthy
function isHealthy($name) {
    $healthyKeywords = ['organic', 'natural', 'healthy', 'protein', 'vitamin', 'nutrient', 'low-sugar', 'low-carb', 'keto'];
    foreach ($healthyKeywords as $keyword) {
        if (stripos($name, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

try {
    $pdo->beginTransaction();

    // Get all categories
    $stmt = $pdo->query("SELECT id, name FROM categories");
    $categories = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Prepare insert statement
    $stmt = $pdo->prepare("
        INSERT INTO items (
            business_id, name, category_id, brand, 
            retail_price, cost_price, popularity, 
            shelf_life, is_seasonal, is_imported, 
            is_healthy, status
        ) VALUES (
            ?, ?, ?, ?, 
            ?, ?, ?, 
            ?, ?, ?, 
            ?, 'active'
        )
    ");

    // Process each category and its items
    foreach ($categories as $categoryId => $categoryName) {
        // Get items for this category from the JSON file
        $jsonFile = __DIR__ . '/../assets/js/vending_items_list.json';
        $jsonData = json_decode(file_get_contents($jsonFile), true);
        
        if (isset($jsonData[$categoryName])) {
            foreach ($jsonData[$categoryName] as $item) {
                $name = $item['name'];
                $brand = explode(' ', $name)[0]; // Simple brand extraction
                
                // Calculate prices (you may want to adjust these based on your needs)
                $retailPrice = $item['metadata']['retail_price'];
                $costPrice = $item['metadata']['cost_price'];
                
                $stmt->execute([
                    1, // Default business_id
                    $name,
                    $categoryId,
                    $brand,
                    $retailPrice,
                    $costPrice,
                    determinePopularity($name, $brand),
                    determineShelfLife($categoryName, $name),
                    isSeasonal($name) ? 1 : 0,
                    isImported($name) ? 1 : 0,
                    isHealthy($name) ? 1 : 0
                ]);
            }
        }
    }

    $pdo->commit();
    echo "Items imported successfully!\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error importing items: " . $e->getMessage() . "\n";
} 