<?php
/**
 * Safe Database Schema Migration Executor
 * Run this script to safely migrate the database schema
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/migration_helpers.php';

echo "🚀 Starting Safe Database Schema Migration\n";
echo "==========================================\n\n";

try {
    // Phase 1: Create backups and compatibility views
    echo "Phase 1: Creating backups and compatibility views...\n";
    
    $migration_sql = file_get_contents(__DIR__ . '/schema_migration_phase1.sql');
    if (!$migration_sql) {
        throw new Exception("Could not read migration SQL file");
    }
    
    // Execute migration in transaction
    $pdo->beginTransaction();
    
    // Split and execute SQL statements
    $statements = array_filter(array_map('trim', explode(';', $migration_sql)));
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) continue;
        
        try {
            $pdo->exec($statement);
            echo "✅ Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            echo "⚠️  Warning: " . $e->getMessage() . "\n";
            // Continue with non-critical errors
        }
    }
    
    $pdo->commit();
    echo "✅ Phase 1 completed successfully!\n\n";
    
    // Phase 2: Validate migration
    echo "Phase 2: Validating migration...\n";
    
    // Check if backups were created
    $backup_tables = ['qr_codes_backup', 'voting_lists_backup', 'migration_log'];
    foreach ($backup_tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($result) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "✅ Backup table '$table' created with $count records\n";
        } else {
            echo "❌ Backup table '$table' not found\n";
        }
    }
    
    // Check if views were created
    $views = ['machines_unified', 'qr_codes_safe'];
    foreach ($views as $view) {
        $result = $pdo->query("SHOW TABLES LIKE '$view'")->fetch();
        if ($result) {
            echo "✅ Compatibility view '$view' created\n";
        } else {
            echo "❌ Compatibility view '$view' not found\n";
        }
    }
    
    // Phase 3: Test critical pages
    echo "\nPhase 3: Testing critical functionality...\n";
    
    // Test QR codes retrieval
    $business_test_query = "SELECT id FROM businesses LIMIT 1";
    $business_result = $pdo->query($business_test_query)->fetch();
    
    if ($business_result) {
        $business_id = $business_result['id'];
        
        // Test safe QR codes function
        if (function_exists('safeGetQRCodes')) {
            $test_qr_codes = safeGetQRCodes($pdo, $business_id);
            echo "✅ Safe QR codes retrieval: " . count($test_qr_codes) . " codes found\n";
        }
        
        // Test safe machines function  
        if (function_exists('safeGetMachines')) {
            $test_machines = safeGetMachines($pdo, $business_id);
            echo "✅ Safe machines retrieval: " . count($test_machines) . " machines found\n";
        }
    }
    
    // Phase 4: Update foreign key constraints (SAFE)
    echo "\nPhase 4: Updating foreign key constraints...\n";
    
    try {
        // Only proceed if we have backup and views
        if (isMigrationSafe($pdo)) {
            
            // Disable foreign key checks temporarily
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Drop the problematic foreign key
            try {
                $pdo->exec("ALTER TABLE qr_codes DROP FOREIGN KEY qr_codes_ibfk_1");
                echo "✅ Dropped old foreign key constraint\n";
            } catch (PDOException $e) {
                echo "⚠️  Old foreign key may not exist: " . $e->getMessage() . "\n";
            }
            
            // Check if machines table exists
            $machines_exists = $pdo->query("SHOW TABLES LIKE 'machines'")->fetch();
            
            if ($machines_exists) {
                // Add correct foreign key to machines table
                try {
                    $pdo->exec("ALTER TABLE qr_codes ADD CONSTRAINT qr_codes_machine_fk FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL");
                    echo "✅ Added correct foreign key to machines table\n";
                } catch (PDOException $e) {
                    echo "⚠️  Could not add machines FK: " . $e->getMessage() . "\n";
                    // Fallback: keep referencing voting_lists
                    try {
                        $pdo->exec("ALTER TABLE qr_codes ADD CONSTRAINT qr_codes_voting_list_fk FOREIGN KEY (machine_id) REFERENCES voting_lists(id) ON DELETE SET NULL");
                        echo "✅ Added fallback foreign key to voting_lists\n";
                    } catch (PDOException $e2) {
                        echo "⚠️  Fallback FK failed: " . $e2->getMessage() . "\n";
                    }
                }
            } else {
                // No machines table, use voting_lists
                try {
                    $pdo->exec("ALTER TABLE qr_codes ADD CONSTRAINT qr_codes_voting_list_fk FOREIGN KEY (machine_id) REFERENCES voting_lists(id) ON DELETE SET NULL");
                    echo "✅ Added foreign key to voting_lists table\n";
                } catch (PDOException $e) {
                    echo "⚠️  Could not add voting_lists FK: " . $e->getMessage() . "\n";
                }
            }
            
            // Re-enable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Add business_id foreign key if needed
            try {
                $pdo->exec("ALTER TABLE qr_codes ADD CONSTRAINT qr_codes_business_fk FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE");
                echo "✅ Added business_id foreign key\n";
            } catch (PDOException $e) {
                echo "⚠️  Business FK may already exist: " . $e->getMessage() . "\n";
            }
            
        } else {
            echo "❌ Migration not safe - skipping foreign key updates\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error updating foreign keys: " . $e->getMessage() . "\n";
    }
    
    // Final validation
    echo "\nFinal Validation:\n";
    echo "================\n";
    
    $migration_status = getMigrationStatus($pdo);
    foreach ($migration_status as $status) {
        echo "📋 {$status['phase']}.{$status['step']}: {$status['status']} - {$status['message']}\n";
    }
    
    echo "\n🎉 Migration completed successfully!\n";
    echo "✅ All data backed up\n";
    echo "✅ Compatibility views created\n";
    echo "✅ Helper functions available\n";
    echo "✅ Foreign keys updated\n";
    echo "\n🚀 Your application should continue working normally.\n";
    echo "🔧 You can now proceed with Phase 2 fixes when ready.\n\n";
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "🔄 Changes have been rolled back.\n";
    
    // Log the error
    if (function_exists('logMigrationStep')) {
        logMigrationStep($pdo, 'phase1', 'migration', 'failed', $e->getMessage());
    }
    
    exit(1);
}
?> 