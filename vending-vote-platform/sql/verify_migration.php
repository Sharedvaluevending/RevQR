<?php
require_once __DIR__ . '/../includes/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

function verifyTable($pdo, $table, $expectedColumns) {
    echo "Verifying table: $table\n";
    
    try {
        // Get table structure
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Check each expected column
        foreach ($expectedColumns as $column) {
            if (!in_array($column, $columns)) {
                echo "✗ Missing column: $column in table $table\n";
                return false;
            }
        }
        
        echo "✓ Table $table verified successfully\n";
        return true;
    } catch (PDOException $e) {
        echo "✗ Error verifying table $table: " . $e->getMessage() . "\n";
        return false;
    }
}

function verifyIndex($pdo, $table, $index) {
    echo "Verifying index: $index on table $table\n";
    
    try {
        $stmt = $pdo->query("SHOW INDEX FROM $table WHERE Key_name = '$index'");
        $result = $stmt->fetch();
        
        if (!$result) {
            echo "✗ Missing index: $index on table $table\n";
            return false;
        }
        
        echo "✓ Index $index verified successfully\n";
        return true;
    } catch (PDOException $e) {
        echo "✗ Error verifying index $index: " . $e->getMessage() . "\n";
        return false;
    }
}

function verifyView($pdo, $view) {
    echo "Verifying view: $view\n";
    
    try {
        $stmt = $pdo->query("SHOW CREATE VIEW $view");
        $result = $stmt->fetch();
        
        if (!$result) {
            echo "✗ Missing view: $view\n";
            return false;
        }
        
        echo "✓ View $view verified successfully\n";
        return true;
    } catch (PDOException $e) {
        echo "✗ Error verifying view $view: " . $e->getMessage() . "\n";
        return false;
    }
}

// Start verification
echo "Starting migration verification...\n\n";

$success = true;

// 1. Verify winners table
$success &= verifyTable($pdo, 'winners', [
    'machine_id',
    'item_id',
    'vote_type',
    'week_start',
    'week_end',
    'votes_count'
]);
$success &= verifyIndex($pdo, 'winners', 'idx_machine_week');

// 2. Verify machines table
$success &= verifyTable($pdo, 'machines', [
    'updated_at'
]);

// 3. Verify qr_codes table
$success &= verifyTable($pdo, 'qr_codes', [
    'campaign_type',
    'static_url',
    'updated_at'
]);

// 4. Verify items table
$success &= verifyTable($pdo, 'items', [
    'updated_at'
]);
$success &= verifyIndex($pdo, 'items', 'idx_machine_status');

// 5. Verify votes table
$success &= verifyTable($pdo, 'votes', [
    'updated_at'
]);
$success &= verifyIndex($pdo, 'votes', 'idx_machine_vote_type');

// 6. Verify views
$success &= verifyView($pdo, 'campaign_view');
$success &= verifyView($pdo, 'campaign_items_view');

// Final result
if ($success) {
    echo "\n✓ All verifications passed successfully!\n";
    exit(0);
} else {
    echo "\n✗ Some verifications failed. Please check the errors above.\n";
    exit(1);
} 