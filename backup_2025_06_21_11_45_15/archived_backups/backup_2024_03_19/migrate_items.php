<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo->beginTransaction();

    // First, ensure we have at least one machine per business
    $stmt = $pdo->prepare("
        INSERT INTO machines (business_id, name, slug, description)
        SELECT 
            b.id,
            CONCAT(b.name, ' - Main Machine'),
            CONCAT(LOWER(REPLACE(b.name, ' ', '-')), '-main'),
            'Default machine for migrated items'
        FROM businesses b
        LEFT JOIN machines m ON m.business_id = b.id
        WHERE m.id IS NULL
    ");
    $stmt->execute();

    // Get all machines for mapping
    $stmt = $pdo->query("SELECT id, business_id FROM machines");
    $machines = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Migrate items
    $stmt = $pdo->prepare("
        INSERT INTO items (
            machine_id,
            name,
            type,
            price,
            list_type,
            status,
            created_at
        )
        SELECT 
            m.id as machine_id,
            i.name,
            CASE 
                WHEN i.type = 'snack' THEN 'snack'
                WHEN i.type = 'drink' THEN 'drink'
                WHEN i.type = 'pizza' THEN 'pizza'
                WHEN i.type = 'side' THEN 'side'
                ELSE 'other'
            END as type,
            COALESCE(i.retail_price, 1.25) as price,
            'regular' as list_type,
            'active' as status,
            COALESCE(i.created_at, CURRENT_TIMESTAMP) as created_at
        FROM old_items i
        JOIN machines m ON m.business_id = i.business_id
        WHERE i.status = 'active'
    ");
    $stmt->execute();

    // Commit transaction
    $pdo->commit();
    echo "Items migration completed successfully!\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Migration failed: " . $e->getMessage() . "\n");
} 