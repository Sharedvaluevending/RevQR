<?php
require_once __DIR__ . '/config.php';

$categories = [
    [
        'name' => 'Cookies (Brand-Name & Generic)',
        'description' => 'Includes major cookie brands and local Canadian cookies, plus generic/store-brand options'
    ],
    [
        'name' => 'Candy and Chocolate Bars',
        'description' => 'A broad selection of chocolate bars, candies, and gum – from iconic brands to Canadian-exclusive treats'
    ],
    [
        'name' => 'Chips and Savory Snacks',
        'description' => 'A diverse range of chips and salty snacks – including Canadian-exclusive chip flavors – plus pretzels, popcorn, nuts, and jerky'
    ],
    [
        'name' => 'Soft Drinks and Carbonated Beverages',
        'description' => 'A selection of sodas and fizzy drinks commonly found in Canada'
    ],
    [
        'name' => 'Energy Drinks',
        'description' => 'Common energy drink brands and flavors, including sugar-free options'
    ],
    [
        'name' => 'Juices and Bottled Teas',
        'description' => 'Non-carbonated beverages: fruit juices, juice drinks, iced teas, and sports drinks'
    ],
    [
        'name' => 'Water and Flavored Water',
        'description' => 'Still and sparkling waters, including mineral waters and flavored seltzers'
    ],
    [
        'name' => 'Healthy Snacks',
        'description' => 'Better-for-you snack options including nuts, dried fruits, veggie snacks, and bars'
    ],
    [
        'name' => 'Protein and Meal Replacement Bars',
        'description' => 'High-protein bars and meal-substitute snacks'
    ],
    [
        'name' => 'Odd or Unique Items',
        'description' => 'Unusual or specialty products to add variety – from Japanese treats to nostalgic retro snacks'
    ]
];

try {
    // Insert new categories
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO categories (name, description) 
        VALUES (?, ?)
    ");

    foreach ($categories as $category) {
        $stmt->execute([$category['name'], $category['description']]);
    }

    echo "Categories populated successfully!\n";
} catch (Exception $e) {
    echo "Error populating categories: " . $e->getMessage() . "\n";
} 