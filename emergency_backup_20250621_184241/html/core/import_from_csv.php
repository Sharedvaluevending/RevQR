<?php
require_once __DIR__ . '/config.php';

try {
    $pdo->beginTransaction();

    // First, clear existing data
    $pdo->exec("DELETE FROM items");
    $pdo->exec("DELETE FROM categories");

    // Import categories
    $categoriesFile = __DIR__ . '/../assets/js/vending_categories.csv';
    if (!file_exists($categoriesFile)) {
        throw new Exception("Categories file not found: $categoriesFile");
    }

    $categories = array_map('str_getcsv', file($categoriesFile));
    array_shift($categories); // Remove header row

    $categoryStmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    $categoryMap = [];

    foreach ($categories as $category) {
        $name = trim($category[0]);
        if (empty($name)) continue;

        $categoryStmt->execute([$name, "Items in the $name category"]);
        $categoryMap[$name] = $pdo->lastInsertId();
    }

    // CSV files to process
    $csvFiles = [
        'Cookies (Brand-Name & Generic)' => 'Full_Cookie_List.csv',
        'Candy and Chocolate Bars' => 'Candy_and_Chocolate_Bars.csv',
        'Chips and Savory Snacks' => 'Chips_and_Savory_Snacks.csv',
        'Soft Drinks and Carbonated Beverages' => 'Soft_Drinks_and_Carbonated_Beverages.csv',
        'Energy Drinks' => 'Energy_Drinks.csv',
        'Juices and Bottled Teas' => 'Juices_and_Bottled_Teas.csv',
        'Water and Flavored Water' => 'Water_and_Flavored_Water.csv',
        'Healthy Snacks' => 'Healthy_Snacks.csv',
        'Protein and Meal Replacement Bars' => 'Protein_and_Meal_Replacement_Bars.csv',
        'Odd or Unique Items (Novelty & Imports)' => 'Odd_or_Unique_Items.csv'
    ];

    // Prepare item insert statement
    $itemStmt = $pdo->prepare("
        INSERT INTO items (
            business_id, name, category_id, brand, retail_price, cost_price, 
            popularity, shelf_life, is_seasonal, is_imported, is_healthy, status
        ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");

    // Process each CSV file
    foreach ($csvFiles as $category => $filename) {
        $filepath = __DIR__ . '/../assets/js/' . $filename;
        if (!file_exists($filepath)) {
            echo "Warning: File not found: $filepath\n";
            continue;
        }

        $items = array_map('str_getcsv', file($filepath));
        // Always skip the first row (header)
        if (count($items) > 0) {
            array_shift($items);
        }

        foreach ($items as $item) {
            // Ensure there are at least two columns
            if (count($item) < 2) continue;
            $csvCategory = trim($item[0]);
            $name = trim($item[1]);
            if (empty($name) || empty($csvCategory)) continue;

            // Remove dots and clean up the name
            $name = str_replace('.', '', $name);
            $name = substr(trim($name), 0, 100);
            if (empty($name)) continue;

            // Extract brand (first word of the name)
            $brand = explode(' ', $name)[0];

            // Set default values
            $retailPrice = 1.25;
            $costPrice = 0.90;
            $popularity = 'medium';
            $shelfLife = 180;
            $isSeasonal = 0;
            $isImported = 0;
            $isHealthy = 0;

            // Adjust values based on category
            switch ($category) {
                case 'Water and Flavored Water':
                    $retailPrice = 1.00;
                    $costPrice = 0.50;
                    $shelfLife = 365;
                    break;
                case 'Energy Drinks':
                    $retailPrice = 2.50;
                    $costPrice = 1.50;
                    $shelfLife = 180;
                    break;
                case 'Healthy Snacks':
                    $retailPrice = 1.75;
                    $costPrice = 1.00;
                    $isHealthy = 1;
                    break;
                case 'Protein and Meal Replacement Bars':
                    $retailPrice = 2.25;
                    $costPrice = 1.25;
                    $isHealthy = 1;
                    break;
            }

            // Check for seasonal items
            if (stripos($name, 'holiday') !== false || 
                stripos($name, 'christmas') !== false || 
                stripos($name, 'halloween') !== false) {
                $isSeasonal = 1;
            }

            // Check for imported items
            if (stripos($name, 'imported') !== false || 
                stripos($name, 'international') !== false) {
                $isImported = 1;
            }

            // Insert the item, using the category from the CSV row
            if (!isset($categoryMap[$csvCategory])) continue;
            $itemStmt->execute([
                $name,
                $categoryMap[$csvCategory],
                $brand,
                $retailPrice,
                $costPrice,
                $popularity,
                $shelfLife,
                $isSeasonal,
                $isImported,
                $isHealthy
            ]);
        }
    }

    $pdo->commit();
    echo "Categories and items imported successfully!\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
} 