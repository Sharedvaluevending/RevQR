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
    echo "Starting rollback...\n\n";

    // 1. Drop views
    echo "1. Dropping views...\n";
    executeSQL($pdo, "DROP VIEW IF EXISTS campaign_view");
    executeSQL($pdo, "DROP VIEW IF EXISTS campaign_items_view");

    // 2. Remove indexes and constraints from winners table
    echo "\n2. Removing winners table changes...\n";
    executeSQL($pdo, "ALTER TABLE winners DROP INDEX IF EXISTS idx_machine_week");
    executeSQL($pdo, "ALTER TABLE winners DROP FOREIGN KEY IF EXISTS fk_winners_item");

    // 3. Remove updated_at from machines table
    echo "\n3. Removing machines table changes...\n";
    executeSQL($pdo, "ALTER TABLE machines DROP COLUMN IF EXISTS updated_at");

    // 4. Remove changes from qr_codes table
    echo "\n4. Removing qr_codes table changes...\n";
    executeSQL($pdo, "ALTER TABLE qr_codes DROP COLUMN IF EXISTS campaign_type");
    executeSQL($pdo, "ALTER TABLE qr_codes DROP COLUMN IF EXISTS static_url");
    executeSQL($pdo, "ALTER TABLE qr_codes DROP COLUMN IF EXISTS updated_at");

    // 5. Remove changes from items table
    echo "\n5. Removing items table changes...\n";
    executeSQL($pdo, "ALTER TABLE items DROP INDEX IF EXISTS idx_machine_status");
    executeSQL($pdo, "ALTER TABLE items DROP COLUMN IF EXISTS updated_at");

    // 6. Remove changes from votes table
    echo "\n6. Removing votes table changes...\n";
    executeSQL($pdo, "ALTER TABLE votes DROP INDEX IF EXISTS idx_machine_vote_type");
    executeSQL($pdo, "ALTER TABLE votes DROP COLUMN IF EXISTS updated_at");

    // Commit transaction
    $pdo->commit();
    echo "\n✓ Rollback completed successfully!\n";

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo "\n✗ Rollback failed: " . $e->getMessage() . "\n";
    exit(1);
} 