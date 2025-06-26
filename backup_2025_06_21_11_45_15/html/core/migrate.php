<?php
require_once __DIR__ . '/config.php';

// Connect to both databases
try {
    $source_pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=qr_vending_app",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $target_pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=revenueqr",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Start transaction
    $target_pdo->beginTransaction();
    
    // 1. Migrate users
    $stmt = $source_pdo->query("SELECT * FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $stmt = $target_pdo->prepare("
            INSERT INTO users (username, password_hash, role, created_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['email'],
            $user['password'],
            $user['role'] === 'business' ? 'vendor' : 'admin',
            $user['created_at']
        ]);
    }
    
    // 2. Migrate businesses
    $stmt = $source_pdo->query("SELECT * FROM businesses");
    $businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($businesses as $business) {
        $stmt = $target_pdo->prepare("
            INSERT INTO businesses (name, email, slug, created_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $business['name'],
            $business['location'], // Using location as email temporarily
            strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $business['name'])),
            $business['created_at']
        ]);
    }
    
    // 3. Migrate items
    $stmt = $source_pdo->query("SELECT * FROM items");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as $item) {
        $stmt = $target_pdo->prepare("
            INSERT INTO items (machine_id, name, type, price, list_type, status, created_at)
            VALUES (?, ?, ?, ?, 'regular', 'active', ?)
        ");
        $stmt->execute([
            $item['business_id'],
            $item['name'],
            $item['type'],
            $item['price'],
            $item['created_at']
        ]);
    }
    
    // Commit transaction
    $target_pdo->commit();
    
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    if (isset($target_pdo)) {
        $target_pdo->rollBack();
    }
    die("Migration failed: " . $e->getMessage() . "\n");
} 