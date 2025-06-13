<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Function to determine item type based on category
function getItemType($category) {
    $drinkCategories = [
        'Energy Drinks',
        'Juices and Bottled Teas',
        'Soft Drinks and Carbonated Beverages',
        'Water and Flavored Water'
    ];
    
    return in_array($category, $drinkCategories) ? 'drink' : 'snack';
}

// Function to import items from a CSV file
function importItemsFromCSV($filePath, $pdo) {
    if (!file_exists($filePath)) {
        echo "File not found: $filePath\n";
        return;
    }

    $file = fopen($filePath, 'r');
    if (!$file) {
        echo "Could not open file: $filePath\n";
        return;
    }

    // Skip header row
    fgetcsv($file);

    $count = 0;
    while (($data = fgetcsv($file)) !== FALSE) {
        $category = $data[0];
        $name = $data[1];
        $type = getItemType($category);
        $brand = '';  // Brand not in CSV
        $suggested_price = 0.00;  // Default price
        $suggested_cost = 0.00;   // Default cost
        $popularity = 'medium';   // Default popularity
        $shelf_life = 0;          // Default shelf life
        $is_seasonal = 0;         // Default not seasonal
        $is_imported = 0;         // Default not imported
        $is_healthy = 0;          // Default not healthy
        $status = 'active';       // Default active

        // Insert into master_items only
        $stmt = $pdo->prepare("
            INSERT INTO master_items (
                name, category, type, brand, 
                suggested_price, suggested_cost, 
                popularity, shelf_life, 
                is_seasonal, is_imported, is_healthy, 
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name, $category, $type, $brand,
            $suggested_price, $suggested_cost,
            $popularity, $shelf_life,
            $is_seasonal, $is_imported, $is_healthy,
            $status
        ]);

        $count++;
    }

    fclose($file);
    echo "Imported $count items from $filePath\n";
}

// Get all CSV files from the assets/js directory
$csvDir = __DIR__ . '/../assets/js/';
$csvFiles = glob($csvDir . '*.csv');

// Import items from each CSV file
foreach ($csvFiles as $file) {
    $filename = basename($file);
    if ($filename === 'vending_categories.csv') continue; // Skip the categories file
    
    echo "Processing $filename...\n";
    importItemsFromCSV($file, $pdo);
}

echo "Import completed!\n"; 