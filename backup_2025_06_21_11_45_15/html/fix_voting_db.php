<?php
/**
 * Database Repair Script for Voting System
 * Fixes foreign key constraint issues and missing columns
 */

require_once __DIR__ . '/core/config.php';

echo "<h2>🔧 Database Repair for Voting System</h2>\n";

// Disable foreign key checks temporarily
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "✅ Disabled foreign key checks<br>\n";
} catch (Exception $e) {
    echo "❌ Error disabling foreign key checks: " . $e->getMessage() . "<br>\n";
}

// Fix 1: Ensure voting_lists table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `voting_lists` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `business_id` INT NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `location` VARCHAR(255) NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_voting_lists_business` (`business_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Voting lists table exists<br>\n";
} catch (Exception $e) {
    echo "❌ Error creating voting_lists table: " . $e->getMessage() . "<br>\n";
}

// Fix 2: Update qr_codes table to remove invalid foreign key constraints
try {
    // Drop problematic foreign key constraints
    $constraints = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'revenueqr' 
        AND TABLE_NAME = 'qr_codes' 
        AND REFERENCED_TABLE_NAME = 'machines'
    ")->fetchAll();
    
    foreach ($constraints as $constraint) {
        $pdo->exec("ALTER TABLE qr_codes DROP FOREIGN KEY " . $constraint['CONSTRAINT_NAME']);
        echo "✅ Dropped foreign key constraint: " . $constraint['CONSTRAINT_NAME'] . "<br>\n";
    }
} catch (Exception $e) {
    echo "⚠️  Note: " . $e->getMessage() . "<br>\n";
}

// Fix 3: Ensure qr_codes has proper structure
try {
    // Add missing columns if they don't exist
    $columns = $pdo->query("SHOW COLUMNS FROM qr_codes")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('business_id', $columns)) {
        $pdo->exec("ALTER TABLE qr_codes ADD COLUMN business_id INT NULL AFTER id");
        echo "✅ Added business_id column to qr_codes<br>\n";
    }
    
    if (!in_array('url', $columns)) {
        $pdo->exec("ALTER TABLE qr_codes ADD COLUMN url VARCHAR(500) NULL");
        echo "✅ Added url column to qr_codes<br>\n";
    }
    
    if (!in_array('code', $columns)) {
        $pdo->exec("ALTER TABLE qr_codes ADD COLUMN code VARCHAR(255) NOT NULL DEFAULT ''");
        echo "✅ Added code column to qr_codes<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error updating qr_codes structure: " . $e->getMessage() . "<br>\n";
}

// Fix 4: Update vote types to ensure consistency
try {
    $pdo->exec("UPDATE votes SET vote_type = 'vote_in' WHERE vote_type IN ('in', 'IN', 'yes', 'YES', 'up', 'UP')");
    $pdo->exec("UPDATE votes SET vote_type = 'vote_out' WHERE vote_type IN ('out', 'OUT', 'no', 'NO', 'down', 'DOWN')");
    echo "✅ Standardized vote types<br>\n";
} catch (Exception $e) {
    echo "❌ Error standardizing vote types: " . $e->getMessage() . "<br>\n";
}

// Fix 5: Clean up orphaned records
try {
    // Set NULL for orphaned foreign key references
    $pdo->exec("UPDATE votes v LEFT JOIN qr_codes qr ON v.qr_code_id = qr.id SET v.qr_code_id = NULL WHERE qr.id IS NULL AND v.qr_code_id IS NOT NULL");
    echo "✅ Cleaned up orphaned vote references<br>\n";
} catch (Exception $e) {
    echo "❌ Error cleaning orphaned records: " . $e->getMessage() . "<br>\n";
}

// Fix 6: Add safe foreign key constraints
try {
    // Add business_id foreign key if businesses table exists
    $business_table_exists = $pdo->query("SHOW TABLES LIKE 'businesses'")->fetch();
    if ($business_table_exists) {
        $pdo->exec("
            ALTER TABLE qr_codes 
            ADD CONSTRAINT fk_qr_codes_business 
            FOREIGN KEY (business_id) REFERENCES businesses(id) 
            ON DELETE CASCADE
        ");
        echo "✅ Added safe business_id foreign key<br>\n";
    }
} catch (Exception $e) {
    echo "⚠️  Business foreign key: " . $e->getMessage() . "<br>\n";
}

// Re-enable foreign key checks
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "✅ Re-enabled foreign key checks<br>\n";
} catch (Exception $e) {
    echo "❌ Error re-enabling foreign key checks: " . $e->getMessage() . "<br>\n";
}

echo "<br><h3>🎉 Database repair completed!</h3>\n";
echo "<p>The voting system should now work properly. You can test it by visiting the vote page.</p>\n";
echo "<p><a href='vote.php?code=test'>Test Voting Page</a></p>\n";
?> 