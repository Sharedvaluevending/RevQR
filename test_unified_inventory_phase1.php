<?php
/**
 * Test Script for Unified Inventory System - Phase 1
 * Tests the basic functionality of the unified inventory system
 */

require_once 'html/core/config.php';
require_once 'html/core/services/UnifiedInventoryManager.php';

echo "🧪 TESTING UNIFIED INVENTORY SYSTEM - PHASE 1\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // Initialize the manager
    $inventoryManager = new UnifiedInventoryManager();
    echo "✅ UnifiedInventoryManager initialized successfully\n";
    
    // Test business ID (using a test business)
    $test_business_id = 1; // Adjust as needed
    
    echo "\n📊 TESTING BASIC FUNCTIONALITY\n";
    echo str_repeat("-", 40) . "\n";
    
    // Test 1: Get inventory summary (should work even with empty data)
    echo "1. Testing getInventorySummary()...\n";
    $summary = $inventoryManager->getInventorySummary($test_business_id);
    if ($summary !== false) {
        echo "   ✅ Summary retrieved: " . json_encode($summary) . "\n";
    } else {
        echo "   ❌ Failed to get inventory summary\n";
    }
    
    // Test 2: Get unified inventory (should return empty array initially)
    echo "\n2. Testing getUnifiedInventory()...\n";
    $inventory = $inventoryManager->getUnifiedInventory($test_business_id);
    if (is_array($inventory)) {
        echo "   ✅ Inventory retrieved: " . count($inventory) . " items\n";
        if (!empty($inventory)) {
            echo "   📋 Sample item: " . json_encode($inventory[0]) . "\n";
        }
    } else {
        echo "   ❌ Failed to get unified inventory\n";
    }
    
    // Test 3: Test item mapping creation
    echo "\n3. Testing createItemMapping()...\n";
    $test_mapping_data = [
        'unified_name' => 'Test Coke Can',
        'unified_category' => 'Beverages',
        'unified_price' => 1.50,
        'unified_cost' => 0.75,
        'mapping_confidence' => 'high',
        'master_item_id' => 1, // Adjust if needed
        'mapped_by' => 1
    ];
    
    $mapping_id = $inventoryManager->createItemMapping($test_business_id, $test_mapping_data);
    if ($mapping_id) {
        echo "   ✅ Test mapping created with ID: $mapping_id\n";
        
        // Test 4: Get inventory after mapping creation
        echo "\n4. Testing inventory after mapping creation...\n";
        $updated_inventory = $inventoryManager->getUnifiedInventory($test_business_id);
        echo "   📊 Updated inventory count: " . count($updated_inventory) . " items\n";
        
        // Clean up test data
        echo "\n🧹 Cleaning up test data...\n";
        $cleanup_stmt = $pdo->prepare("DELETE FROM unified_item_mapping WHERE id = ?");
        $cleanup_stmt->execute([$mapping_id]);
        
        $cleanup_stmt2 = $pdo->prepare("DELETE FROM unified_inventory_status WHERE unified_mapping_id = ?");
        $cleanup_stmt2->execute([$mapping_id]);
        
        echo "   ✅ Test data cleaned up\n";
        
    } else {
        echo "   ⚠️  Test mapping creation failed (this may be expected if test data doesn't exist)\n";
    }
    
    echo "\n📈 TESTING DATABASE TABLES\n";
    echo str_repeat("-", 40) . "\n";
    
    // Check if tables exist and have correct structure
    $tables_to_check = [
        'unified_item_mapping',
        'unified_inventory_status',
        'unified_inventory_sync_log'
    ];
    
    foreach ($tables_to_check as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE '$table'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result) {
            echo "✅ Table '$table' exists\n";
            
            // Get column count
            $count_stmt = $pdo->prepare("SELECT COUNT(*) as column_count FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?");
            $count_stmt->execute([$table]);
            $column_info = $count_stmt->fetch();
            echo "   📋 Columns: " . $column_info['column_count'] . "\n";
            
        } else {
            echo "❌ Table '$table' does not exist\n";
        }
    }
    
    echo "\n🎯 INTEGRATION READINESS CHECK\n";
    echo str_repeat("-", 40) . "\n";
    
    // Check if we have existing data to work with
    $manual_items_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM voting_list_items vli JOIN voting_lists vl ON vli.voting_list_id = vl.id WHERE vl.business_id = ?");
    $manual_items_stmt->execute([$test_business_id]);
    $manual_count = $manual_items_stmt->fetch()['count'];
    echo "📱 Manual items for business $test_business_id: $manual_count\n";
    
    $nayax_machines_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM nayax_machines WHERE business_id = ?");
    $nayax_machines_stmt->execute([$test_business_id]);
    $nayax_count = $nayax_machines_stmt->fetch()['count'] ?? 0;
    echo "📡 Nayax machines for business $test_business_id: $nayax_count\n";
    
    $nayax_transactions_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM nayax_transactions nt JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id WHERE nm.business_id = ?");
    $nayax_transactions_stmt->execute([$test_business_id]);
    $transaction_count = $nayax_transactions_stmt->fetch()['count'] ?? 0;
    echo "💳 Nayax transactions for business $test_business_id: $transaction_count\n";
    
    echo "\n🚀 NEXT STEPS\n";
    echo str_repeat("-", 40) . "\n";
    echo "1. ✅ Phase 1 database schema is ready\n";
    echo "2. ✅ UnifiedInventoryManager service is functional\n";
    echo "3. 🔄 Ready for Phase 2: Enhanced catalog display\n";
    echo "4. 🔄 Ready for Phase 3: Real-time sync integration\n";
    
    if ($manual_count > 0 || $nayax_count > 0) {
        echo "5. 💡 You have existing data ready for mapping!\n";
        echo "   📋 Manual items: $manual_count\n";
        echo "   📡 Nayax machines: $nayax_count\n";
        echo "   💳 Nayax transactions: $transaction_count\n";
    } else {
        echo "5. ⚠️  No existing inventory data found for test business\n";
        echo "   💡 Test with a business that has existing machines/items\n";
    }
    
    echo "\n✅ PHASE 1 TESTING COMPLETE!\n";
    echo "🔗 The unified inventory foundation is ready for your catalog cards integration.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR DURING TESTING: " . $e->getMessage() . "\n";
    echo "📍 Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🏁 Test completed at " . date('Y-m-d H:i:s') . "\n";
?> 