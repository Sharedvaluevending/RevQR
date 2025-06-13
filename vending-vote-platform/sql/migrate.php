<?php
require_once __DIR__ . '/../includes/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to execute SQL safely
function executeSQL($pdo, $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ Successfully executed: " . substr($sql, 0, 50) . "...\n";
        return true;
    } catch (PDOException $e) {
        echo "✗ Error executing: " . substr($sql, 0, 50) . "...\n";
        echo "  Error: " . $e->getMessage() . "\n";
        return false;
    }
}

// Start transaction
$pdo->beginTransaction();

try {
    echo "Starting migration...\n\n";

    // 1. Add indexes and constraints to winners table
    echo "1. Updating winners table...\n";
    $sql = "ALTER TABLE winners
            ADD INDEX idx_machine_week (machine_id, week_start),
            ADD CONSTRAINT fk_winners_item
                FOREIGN KEY (item_id)
                REFERENCES items(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE";
    executeSQL($pdo, $sql);

    // 2. Add updated_at to machines table
    echo "\n2. Updating machines table...\n";
    $sql = "ALTER TABLE machines
            ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    executeSQL($pdo, $sql);

    // 3. Update qr_codes table
    echo "\n3. Updating qr_codes table...\n";
    $sql = "ALTER TABLE qr_codes
            ADD COLUMN campaign_type ENUM('static','dynamic','cross_promo','stackable') 
                NOT NULL DEFAULT 'static' AFTER qr_type,
            ADD COLUMN static_url VARCHAR(255) NULL AFTER campaign_type,
            ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    executeSQL($pdo, $sql);

    // 4. Update items table
    echo "\n4. Updating items table...\n";
    $sql = "ALTER TABLE items
            ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ADD INDEX idx_machine_status (machine_id, status)";
    executeSQL($pdo, $sql);

    // 5. Update votes table
    echo "\n5. Updating votes table...\n";
    $sql = "ALTER TABLE votes
            ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ADD INDEX idx_machine_vote_type (machine_id, vote_type)";
    executeSQL($pdo, $sql);

    // 6. Create campaign view
    echo "\n6. Creating campaign view...\n";
    $sql = "CREATE OR REPLACE VIEW campaign_view AS
            SELECT 
                m.id as campaign_id,
                m.business_id,
                m.name as campaign_name,
                m.description as campaign_description,
                m.type as campaign_type,
                m.is_active,
                m.tooltip,
                m.created_at as campaign_created_at,
                m.updated_at as campaign_updated_at
            FROM machines m
            WHERE m.type IN ('vote', 'promo')";
    executeSQL($pdo, $sql);

    // 7. Create campaign items view
    echo "\n7. Creating campaign items view...\n";
    $sql = "CREATE OR REPLACE VIEW campaign_items_view AS
            SELECT 
                m.id as campaign_id,
                i.id as item_id,
                i.name as item_name,
                i.type as item_type,
                i.price,
                i.list_type,
                i.status
            FROM machines m
            JOIN items i ON i.machine_id = m.id
            WHERE m.type IN ('vote', 'promo')";
    executeSQL($pdo, $sql);

    // Commit transaction
    $pdo->commit();
    echo "\n✓ Migration completed successfully!\n";

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
} 