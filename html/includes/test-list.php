<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Test data
$test_data = [
    'name' => 'Test List ' . time(),
    'description' => 'Test list created by automated test',
    'items' => [
        [
            'name' => 'Test Item 1',
            'type' => 'snack',
            'category' => 'Snacks',
            'suggested_price' => 1.99,
            'suggested_cost' => 0.99,
            'popularity' => 'medium',
            'shelf_life' => 30
        ],
        [
            'name' => 'Test Item 2',
            'type' => 'drink',
            'category' => 'Beverages',
            'suggested_price' => 2.99,
            'suggested_cost' => 1.49,
            'popularity' => 'high',
            'shelf_life' => 60
        ]
    ]
];

try {
    $pdo->beginTransaction();
    
    // Create test business if not exists
    $stmt = $pdo->prepare("SELECT id FROM businesses WHERE name = 'Test Business'");
    $stmt->execute();
    $business = $stmt->fetch();
    
    if (!$business) {
        $stmt = $pdo->prepare("INSERT INTO businesses (name, slug) VALUES (?, ?)");
        $slug = 'test-business-' . time();
        $stmt->execute(['Test Business', $slug]);
        $business_id = $pdo->lastInsertId();
    } else {
        $business_id = $business['id'];
    }
    
    // Create the machine
    $stmt = $pdo->prepare("
        INSERT INTO machines (business_id, name, slug, description, qr_type)
        VALUES (?, ?, ?, ?, 'static')
    ");
    $slug = 'test-machine-' . time();
    $stmt->execute([
        $business_id,
        $test_data['name'],
        $slug,
        $test_data['description']
    ]);
    
    $machine_id = $pdo->lastInsertId();
    
    // Insert master items
    $stmt = $pdo->prepare("
        INSERT INTO master_items (
            name, category, type, suggested_price, suggested_cost,
            popularity, shelf_life, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    
    $master_item_ids = [];
    foreach ($test_data['items'] as $item) {
        $stmt->execute([
            $item['name'],
            $item['category'],
            $item['type'],
            $item['suggested_price'],
            $item['suggested_cost'],
            $item['popularity'],
            $item['shelf_life']
        ]);
        $master_item_ids[] = $pdo->lastInsertId();
    }
    
    // Create items and map them
    $stmt = $pdo->prepare("
        INSERT INTO items (
            machine_id, name, type, price,
            list_type, status
        ) VALUES (?, ?, ?, ?, 'regular', 'active')
    ");
    
    $item_ids = [];
    foreach ($test_data['items'] as $index => $item) {
        $stmt->execute([
            $machine_id,
            $item['name'],
            $item['type'],
            $item['suggested_price']
        ]);
        $item_ids[] = $pdo->lastInsertId();
    }
    
    // Create item mappings
    $stmt = $pdo->prepare("
        INSERT INTO item_mapping (master_item_id, item_id)
        VALUES (?, ?)
    ");
    
    foreach ($master_item_ids as $index => $master_id) {
        $stmt->execute([$master_id, $item_ids[$index]]);
    }
    
    $pdo->commit();
    echo "Test list created successfully!\n";
    echo "Business ID: $business_id\n";
    echo "Machine ID: $machine_id\n";
    echo "Master Items: " . implode(', ', $master_item_ids) . "\n";
    echo "Items: " . implode(', ', $item_ids) . "\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error creating test list: " . $e->getMessage() . "\n";
} 