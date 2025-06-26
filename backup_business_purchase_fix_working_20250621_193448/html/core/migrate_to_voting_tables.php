<?php
require_once __DIR__ . '/config.php';

function tableExists($pdo, $table) {
    // Only allow alphanumeric and underscore for table names
    if (!preg_match('/^\w+$/', $table)) return false;
    $sql = "SHOW TABLES LIKE '" . $table . "'";
    $stmt = $pdo->query($sql);
    return $stmt->fetchColumn() !== false;
}

// First, check if tables exist
$machinesExists = tableExists($pdo, 'machines');
$itemsExists = tableExists($pdo, 'items');

if (!$machinesExists || !$itemsExists) {
    echo "Nothing to migrate: 'machines' or 'items' table does not exist.\n";
    // Still create the views for compatibility
    try {
        $pdo->exec("
            CREATE OR REPLACE VIEW machines AS
            SELECT 
                id as id,
                business_id as business_id,
                name as name,
                description as description,
                created_at as created_at
            FROM voting_lists
        ");
        $pdo->exec("
            CREATE OR REPLACE VIEW items AS
            SELECT 
                id as id,
                voting_list_id as machine_id,
                item_name as name,
                item_category as type,
                retail_price as price,
                list_type as list_type,
                'active' as status,
                created_at as created_at
            FROM voting_list_items
        ");
        echo "Compatibility views created successfully.\n";
    } catch (Exception $e) {
        echo "Error creating views: " . $e->getMessage() . "\n";
    }
    exit(0);
}

// If we get here, both tables exist and we can proceed with migration
try {
    // Start transaction first
    $pdo->beginTransaction();
    // Disable foreign key checks after transaction starts
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // 1. Create temporary tables to store existing data
    $pdo->exec("
        CREATE TEMPORARY TABLE temp_machines AS
        SELECT * FROM machines
    ");
    
    $pdo->exec("
        CREATE TEMPORARY TABLE temp_items AS
        SELECT * FROM items
    ");
    
    // 2. Drop existing tables
    $pdo->exec("DROP TABLE IF EXISTS items");
    $pdo->exec("DROP TABLE IF EXISTS machines");
    
    // 3. Migrate machines to voting_lists
    $pdo->exec("
        INSERT INTO voting_lists (business_id, name, description, created_at)
        SELECT business_id, name, description, created_at
        FROM temp_machines
        WHERE NOT EXISTS (
            SELECT 1 FROM voting_lists vl 
            WHERE vl.business_id = temp_machines.business_id 
            AND vl.name = temp_machines.name
        )
    ");
    
    // 4. Migrate items to voting_list_items
    $pdo->exec("
        INSERT INTO voting_list_items (
            voting_list_id, item_name, list_type, item_category,
            retail_price, cost_price, popularity, shelf_life, created_at
        )
        SELECT 
            vl.id as voting_list_id,
            i.name as item_name,
            i.list_type,
            i.type as item_category,
            i.price as retail_price,
            0 as cost_price,
            'medium' as popularity,
            30 as shelf_life,
            i.created_at
        FROM temp_items i
        JOIN temp_machines m ON i.machine_id = m.id
        JOIN voting_lists vl ON m.business_id = vl.business_id 
            AND m.name = vl.name
        WHERE NOT EXISTS (
            SELECT 1 FROM voting_list_items vli 
            WHERE vli.voting_list_id = vl.id 
            AND vli.name = i.name
        )
    ");
    
    // 5. Create views for backward compatibility
    $pdo->exec("
        CREATE OR REPLACE VIEW machines AS
        SELECT 
            id as id,
            business_id as business_id,
            name as name,
            description as description,
            created_at as created_at
        FROM voting_lists
    ");
    
    $pdo->exec("
        CREATE OR REPLACE VIEW items AS
        SELECT 
            id as id,
            voting_list_id as machine_id,
            item_name as name,
            item_category as type,
            retail_price as price,
            list_type as list_type,
            'active' as status,
            created_at as created_at
        FROM voting_list_items
    ");
    
    // 6. Drop temporary tables
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_machines");
    $pdo->exec("DROP TEMPORARY TABLE IF EXISTS temp_items");
    
    // Commit transaction
    $pdo->commit();
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    // Rollback transaction if it was started
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Always re-enable foreign key checks
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    } catch (Exception $e2) {
        // Ignore errors when re-enabling foreign key checks
    }
    
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
} 