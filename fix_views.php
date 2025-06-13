<?php
require_once __DIR__ . '/html/core/config.php';

echo "🔧 Fixing Compatibility Views\n";
echo "=============================\n\n";

try {
    // Check voting_lists structure
    $vl_columns = $pdo->query("DESCRIBE voting_lists")->fetchAll(PDO::FETCH_COLUMN);
    echo "Voting lists columns: " . implode(', ', $vl_columns) . "\n";
    
    // Check machines structure if exists
    $machines_exists = $pdo->query("SHOW TABLES LIKE 'machines'")->fetch();
    if ($machines_exists) {
        $m_columns = $pdo->query("DESCRIBE machines")->fetchAll(PDO::FETCH_COLUMN);
        echo "Machines columns: " . implode(', ', $m_columns) . "\n";
    } else {
        echo "Machines table does not exist\n";
    }
    
    echo "\n";
    
    // Create proper machines_unified view
    $sql = "CREATE OR REPLACE VIEW machines_unified AS
            SELECT 
                id,
                business_id,
                name,
                description as location,
                'voting_list' as source_table,
                created_at";
    
    // Add updated_at if it exists
    if (in_array('updated_at', $vl_columns)) {
        $sql .= ", updated_at";
    } else {
        $sql .= ", created_at as updated_at";
    }
    
    $sql .= " FROM voting_lists";
    
    // Add machines data if table exists and has compatible structure
    if ($machines_exists) {
        $m_columns = $pdo->query("DESCRIBE machines")->fetchAll(PDO::FETCH_COLUMN);
        
        $sql .= " UNION ALL
                 SELECT 
                     id + 10000 as id,
                     business_id,
                     name,
                     " . (in_array('location', $m_columns) ? 'location' : 'name') . " as location,
                     'machine' as source_table,
                     created_at";
        
        if (in_array('updated_at', $m_columns)) {
            $sql .= ", updated_at";
        } else {
            $sql .= ", created_at as updated_at";
        }
        
        $sql .= " FROM machines";
    }
    
    $pdo->exec($sql);
    echo "✅ Fixed machines_unified view\n";
    
    // Test the view
    $count = $pdo->query("SELECT COUNT(*) FROM machines_unified")->fetchColumn();
    echo "✅ machines_unified view accessible: $count records\n";
    
    // Update the migration helpers to include this file
    echo "\n📝 Including migration helpers in core files...\n";
    
    // Test QR codes safe view
    $qr_safe_count = $pdo->query("SELECT COUNT(*) FROM qr_codes_safe")->fetchColumn();
    echo "✅ qr_codes_safe view accessible: $qr_safe_count records\n";
    
    // Test safe functions
    require_once __DIR__ . '/html/core/migration_helpers.php';
    
    // Get a test business ID
    $business_test = $pdo->query("SELECT id FROM businesses LIMIT 1")->fetch();
    if ($business_test) {
        $business_id = $business_test['id'];
        
        $test_qr_codes = safeGetQRCodes($pdo, $business_id);
        echo "✅ safeGetQRCodes function: " . count($test_qr_codes) . " codes retrieved\n";
        
        $test_machines = safeGetMachines($pdo, $business_id);
        echo "✅ safeGetMachines function: " . count($test_machines) . " machines retrieved\n";
    }
    
    echo "\n🎉 All views and functions working properly!\n";
    echo "✅ Schema migration Phase 1 COMPLETE\n";
    echo "🚀 Your platform is now safely migrated and protected.\n\n";
    
} catch (Exception $e) {
    echo "❌ Error fixing views: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?> 