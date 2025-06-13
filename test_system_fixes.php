<?php
/**
 * Comprehensive System Test Script
 * Tests all the fixes applied to the QR coin economy system
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/services/VotingService.php';

echo "=== QR COIN ECONOMY SYSTEM TEST SUITE ===\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

$tests_passed = 0;
$tests_failed = 0;

function test_result($test_name, $passed, $message = '') {
    global $tests_passed, $tests_failed;
    
    if ($passed) {
        echo "✅ PASS: $test_name\n";
        if ($message) echo "   → $message\n";
        $tests_passed++;
    } else {
        echo "❌ FAIL: $test_name\n";
        if ($message) echo "   → $message\n";
        $tests_failed++;
    }
    echo "\n";
}

// =====================================
// TEST 1: DATABASE SCHEMA CONSISTENCY
// =====================================

echo "🔍 Testing Database Schema Consistency...\n";

try {
    // Test vote type standardization
    $stmt = $pdo->query("SELECT DISTINCT vote_type FROM votes");
    $vote_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $valid_types = ['vote_in', 'vote_out'];
    $invalid_types = array_diff($vote_types, $valid_types);
    
    test_result(
        "Vote Type Standardization", 
        empty($invalid_types),
        empty($invalid_types) ? "All vote types are standardized" : "Invalid types found: " . implode(', ', $invalid_types)
    );
    
    // Test master item mappings
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN master_item_id IS NOT NULL THEN 1 ELSE 0 END) as mapped
        FROM voting_list_items
    ");
    $mapping_stats = $stmt->fetch();
    $mapping_percentage = ($mapping_stats['mapped'] / $mapping_stats['total']) * 100;
    
    test_result(
        "Master Item Mappings",
        $mapping_percentage >= 95,
        "Mapped: {$mapping_stats['mapped']}/{$mapping_stats['total']} ({$mapping_percentage}%)"
    );
    
    // Test QR code business relationships
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN business_id IS NOT NULL THEN 1 ELSE 0 END) as with_business
        FROM qr_codes
    ");
    $qr_stats = $stmt->fetch();
    $qr_percentage = $qr_stats['total'] > 0 ? ($qr_stats['with_business'] / $qr_stats['total']) * 100 : 100;
    
    test_result(
        "QR Code Business Relationships",
        $qr_percentage >= 80,
        "With business_id: {$qr_stats['with_business']}/{$qr_stats['total']} ({$qr_percentage}%)"
    );
    
} catch (Exception $e) {
    test_result("Database Schema Tests", false, "Error: " . $e->getMessage());
}

// =====================================
// TEST 2: VOTING SERVICE FUNCTIONALITY
// =====================================

echo "🗳️ Testing Voting Service Functionality...\n";

try {
    $votingService = new VotingService($pdo);
    
    // Test vote type standardization
    $test_types = [
        'in' => 'vote_in',
        'out' => 'vote_out', 
        'yes' => 'vote_in',
        'no' => 'vote_out',
        'up' => 'vote_in',
        'down' => 'vote_out'
    ];
    
    $standardization_passed = true;
    foreach ($test_types as $input => $expected) {
        $reflection = new ReflectionClass($votingService);
        $method = $reflection->getMethod('standardizeVoteType');
        $method->setAccessible(true);
        $result = $method->invoke($votingService, $input);
        
        if ($result !== $expected) {
            $standardization_passed = false;
            break;
        }
    }
    
    test_result(
        "Vote Type Standardization Logic",
        $standardization_passed,
        $standardization_passed ? "All vote types standardize correctly" : "Vote type standardization failed"
    );
    
    // Test vote count retrieval
    $stmt = $pdo->query("SELECT id FROM voting_list_items LIMIT 1");
    $test_item = $stmt->fetch();
    
    if ($test_item) {
        $counts = $votingService->getVoteCounts($test_item['id']);
        $counts_valid = isset($counts['vote_in_count']) && isset($counts['vote_out_count']) && isset($counts['total_votes']);
        
        test_result(
            "Vote Count Retrieval",
            $counts_valid,
            $counts_valid ? "Vote counts retrieved successfully" : "Vote count structure invalid"
        );
    } else {
        test_result("Vote Count Retrieval", false, "No test items available");
    }
    
} catch (Exception $e) {
    test_result("Voting Service Tests", false, "Error: " . $e->getMessage());
}

// =====================================
// TEST 3: QR CODE GENERATION SYSTEM
// =====================================

echo "📱 Testing QR Code Generation System...\n";

try {
    // Test if unified QR generator exists
    $unified_generator_exists = file_exists(__DIR__ . '/html/api/qr/unified-generate.php');
    test_result(
        "Unified QR Generator Exists",
        $unified_generator_exists,
        $unified_generator_exists ? "Unified generator found" : "Unified generator missing"
    );
    
    // Test if QR generator class exists
    $qr_class_exists = file_exists(__DIR__ . '/html/includes/QRGenerator.php');
    test_result(
        "QR Generator Class Exists",
        $qr_class_exists,
        $qr_class_exists ? "QR generator class found" : "QR generator class missing"
    );
    
    // Test QR code table structure
    $stmt = $pdo->query("DESCRIBE qr_codes");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required_columns = ['id', 'code', 'qr_type', 'business_id', 'campaign_id', 'created_at'];
    $missing_columns = array_diff($required_columns, $columns);
    
    test_result(
        "QR Code Table Structure",
        empty($missing_columns),
        empty($missing_columns) ? "All required columns present" : "Missing columns: " . implode(', ', $missing_columns)
    );
    
} catch (Exception $e) {
    test_result("QR Code Generation Tests", false, "Error: " . $e->getMessage());
}

// =====================================
// TEST 4: NAVIGATION SYSTEM
// =====================================

echo "🧭 Testing Navigation System...\n";

try {
    // Test if main navigation file exists
    $navbar_exists = file_exists(__DIR__ . '/html/core/includes/navbar.php');
    test_result(
        "Main Navigation File Exists",
        $navbar_exists,
        $navbar_exists ? "Navigation file found" : "Navigation file missing"
    );
    
    // Test if admin dashboard exists
    $admin_dashboard_exists = file_exists(__DIR__ . '/html/admin/dashboard_modular.php');
    test_result(
        "Admin Dashboard Exists",
        $admin_dashboard_exists,
        $admin_dashboard_exists ? "Admin dashboard found" : "Admin dashboard missing"
    );
    
    // Test if business navigation exists
    $business_nav_exists = file_exists(__DIR__ . '/html/includes/business_nav.php');
    test_result(
        "Business Navigation Exists",
        $business_nav_exists,
        $business_nav_exists ? "Business navigation found" : "Business navigation missing"
    );
    
} catch (Exception $e) {
    test_result("Navigation System Tests", false, "Error: " . $e->getMessage());
}

// =====================================
// TEST 5: CAMPAIGN/MACHINE RELATIONSHIPS
// =====================================

echo "🔗 Testing Campaign/Machine Relationships...\n";

try {
    // Test campaign_voting_lists table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'campaign_voting_lists'");
    $table_exists = $stmt->rowCount() > 0;
    
    test_result(
        "Campaign Voting Lists Table",
        $table_exists,
        $table_exists ? "Table exists" : "Table missing"
    );
    
    // Test voting lists have business relationships
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN business_id IS NOT NULL THEN 1 ELSE 0 END) as with_business
        FROM voting_lists
    ");
    $list_stats = $stmt->fetch();
    $list_percentage = $list_stats['total'] > 0 ? ($list_stats['with_business'] / $list_stats['total']) * 100 : 100;
    
    test_result(
        "Voting List Business Relationships",
        $list_percentage >= 90,
        "With business_id: {$list_stats['with_business']}/{$list_stats['total']} ({$list_percentage}%)"
    );
    
} catch (Exception $e) {
    test_result("Campaign/Machine Relationship Tests", false, "Error: " . $e->getMessage());
}

// =====================================
// TEST 6: PERFORMANCE INDEXES
// =====================================

echo "⚡ Testing Performance Indexes...\n";

try {
    // Check for critical indexes
    $critical_indexes = [
        'votes' => ['idx_item_vote_type'],
        'qr_codes' => ['idx_campaign_id', 'idx_business_id'],
        'voting_list_items' => ['idx_master_item_id']
    ];
    
    $all_indexes_exist = true;
    $missing_indexes = [];
    
    foreach ($critical_indexes as $table => $indexes) {
        foreach ($indexes as $index) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = 'revenueqr' 
                AND TABLE_NAME = ? 
                AND INDEX_NAME = ?
            ");
            $stmt->execute([$table, $index]);
            $exists = $stmt->fetchColumn() > 0;
            
            if (!$exists) {
                $all_indexes_exist = false;
                $missing_indexes[] = "$table.$index";
            }
        }
    }
    
    test_result(
        "Critical Performance Indexes",
        $all_indexes_exist,
        $all_indexes_exist ? "All critical indexes present" : "Missing: " . implode(', ', $missing_indexes)
    );
    
} catch (Exception $e) {
    test_result("Performance Index Tests", false, "Error: " . $e->getMessage());
}

// =====================================
// FINAL SUMMARY
// =====================================

echo "📊 TEST SUMMARY\n";
echo "=====================================\n";
echo "✅ Tests Passed: $tests_passed\n";
echo "❌ Tests Failed: $tests_failed\n";
echo "📈 Success Rate: " . round(($tests_passed / ($tests_passed + $tests_failed)) * 100, 1) . "%\n\n";

if ($tests_failed === 0) {
    echo "🎉 ALL TESTS PASSED! The QR coin economy system fixes have been successfully applied.\n\n";
    echo "✨ SYSTEM STATUS: FULLY OPERATIONAL\n";
    echo "🚀 Ready for production use!\n";
} else {
    echo "⚠️  Some tests failed. Please review the failed tests above and apply additional fixes.\n\n";
    echo "🔧 SYSTEM STATUS: NEEDS ATTENTION\n";
    echo "📋 Recommended actions:\n";
    echo "   1. Review failed test details above\n";
    echo "   2. Apply specific fixes for failed components\n";
    echo "   3. Re-run this test suite\n";
}

echo "\n=== END OF TEST SUITE ===\n";
?> 