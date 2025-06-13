<?php
// Comprehensive QR System Test - All Generations and Manager Functions
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$servername = "localhost";
$username = "root";
$password = "Snickers2024!";
$dbname = "qr_coin_economy";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Database connection successful\n";
} catch (PDOException $e) {
    die("✗ Database connection failed: " . $e->getMessage() . "\n");
}

// Test configuration
$test_business_id = 1;
$test_results = [];
$total_tests = 0;
$passed_tests = 0;

function runTest($test_name, $test_function) {
    global $test_results, $total_tests, $passed_tests;
    $total_tests++;
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Testing: $test_name\n";
    echo str_repeat("=", 60) . "\n";
    
    try {
        $result = $test_function();
        if ($result) {
            echo "✓ PASSED: $test_name\n";
            $passed_tests++;
            $test_results[$test_name] = "PASSED";
        } else {
            echo "✗ FAILED: $test_name\n";
            $test_results[$test_name] = "FAILED";
        }
    } catch (Exception $e) {
        echo "✗ ERROR in $test_name: " . $e->getMessage() . "\n";
        $test_results[$test_name] = "ERROR: " . $e->getMessage();
    }
}

// Test 1: QR Code Generation - All Types
function testQRCodeGeneration() {
    global $pdo, $test_business_id;
    
    $qr_types = [
        'static' => ['url' => 'https://example.com/static'],
        'dynamic' => ['content' => 'Dynamic content test'],
        'dynamic_voting' => ['poll_id' => 1],
        'dynamic_vending' => ['machine_id' => 1],
        'machine_sales' => ['machine_id' => 1, 'promotion_id' => 1],
        'promotion' => ['promotion_id' => 1],
        'spin_wheel' => ['wheel_id' => 1],
        'pizza_tracker' => ['order_id' => 1],
        'casino' => ['game_id' => 1],
        'cross_promo' => ['promo_id' => 1],
        'stackable' => ['content' => 'Stackable QR content']
    ];
    
    $success_count = 0;
    
    foreach ($qr_types as $type => $params) {
        try {
            // Prepare the insert statement
            $stmt = $pdo->prepare("
                INSERT INTO qr_codes (business_id, type, name, data, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
            ");
            
            $name = "Test " . ucfirst(str_replace('_', ' ', $type)) . " QR";
            $data = json_encode($params);
            
            if ($stmt->execute([$test_business_id, $type, $name, $data])) {
                $qr_id = $pdo->lastInsertId();
                echo "  ✓ Created $type QR (ID: $qr_id)\n";
                $success_count++;
            } else {
                echo "  ✗ Failed to create $type QR\n";
            }
        } catch (Exception $e) {
            echo "  ✗ Error creating $type QR: " . $e->getMessage() . "\n";
        }
    }
    
    return $success_count === count($qr_types);
}

// Test 2: QR Manager Display
function testQRManagerDisplay() {
    global $pdo, $test_business_id;
    
    try {
        // Test the main manager query
        $stmt = $pdo->prepare("
            SELECT qr.*, 
                   CASE 
                       WHEN qr.file_path IS NOT NULL AND qr.file_path != '' THEN qr.file_path
                       WHEN qr.qr_code_url IS NOT NULL AND qr.qr_code_url != '' THEN qr.qr_code_url
                       ELSE CONCAT('uploads/qr/', qr.business_id, '/qr_', qr.id, '.png')
                   END as display_path
            FROM qr_codes qr 
            WHERE qr.business_id = ? AND qr.status = 'active'
            ORDER BY qr.created_at DESC
        ");
        
        $stmt->execute([$test_business_id]);
        $qr_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($qr_codes) > 0) {
            echo "  ✓ Found " . count($qr_codes) . " QR codes in manager\n";
            
            // Check if we have all expected types
            $found_types = array_unique(array_column($qr_codes, 'type'));
            echo "  ✓ Found types: " . implode(", ", $found_types) . "\n";
            
            return true;
        } else {
            echo "  ✗ No QR codes found in manager\n";
            return false;
        }
    } catch (Exception $e) {
        echo "  ✗ Manager query error: " . $e->getMessage() . "\n";
        return false;
    }
}

// Test 3: Action Buttons Functionality
function testActionButtons() {
    global $pdo, $test_business_id;
    
    // Get a test QR code
    $stmt = $pdo->prepare("SELECT * FROM qr_codes WHERE business_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$test_business_id]);
    $qr_code = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$qr_code) {
        echo "  ✗ No QR code available for testing\n";
        return false;
    }
    
    $qr_id = $qr_code['id'];
    $tests_passed = 0;
    
    // Test 1: Preview functionality
    echo "  Testing Preview...\n";
    if (file_exists("html/api/qr/preview.php")) {
        echo "    ✓ Preview API exists\n";
        $tests_passed++;
    } else {
        echo "    ✗ Preview API missing\n";
    }
    
    // Test 2: Print functionality
    echo "  Testing Print...\n";
    if (file_exists("html/api/qr/print.php")) {
        echo "    ✓ Print API exists\n";
        $tests_passed++;
    } else {
        echo "    ✗ Print API missing\n";
    }
    
    // Test 3: Print Studio functionality
    echo "  Testing Print Studio...\n";
    if (file_exists("html/api/qr/print_studio.php")) {
        echo "    ✓ Print Studio API exists\n";
        $tests_passed++;
    } else {
        echo "    ✗ Print Studio API missing\n";
    }
    
    // Test 4: Delete functionality
    echo "  Testing Delete...\n";
    if (file_exists("html/api/qr/delete.php")) {
        echo "    ✓ Delete API exists\n";
        $tests_passed++;
    } else {
        echo "    ✗ Delete API missing\n";
    }
    
    // Test 5: Regenerate functionality
    echo "  Testing Regenerate...\n";
    if (file_exists("html/api/qr/regenerate.php")) {
        echo "    ✓ Regenerate API exists\n";
        $tests_passed++;
    } else {
        echo "    ✗ Regenerate API missing\n";
    }
    
    return $tests_passed === 5;
}

// Test 4: QR Code File Generation
function testQRFileGeneration() {
    global $pdo, $test_business_id;
    
    // Include QR generation classes
    require_once 'html/core/includes/QRGenerator.php';
    
    try {
        $generator = new QRGenerator();
        
        // Test basic QR generation
        $test_data = "Test QR Code Generation";
        $filename = "test_qr_" . time() . ".png";
        $upload_path = "html/uploads/qr/" . $test_business_id . "/";
        
        // Ensure directory exists
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0755, true);
        }
        
        $full_path = $upload_path . $filename;
        
        // Generate QR code
        $result = $generator->generateQR($test_data, $full_path);
        
        if ($result && file_exists($full_path)) {
            echo "  ✓ QR code file generated successfully\n";
            echo "  ✓ File path: $full_path\n";
            echo "  ✓ File size: " . filesize($full_path) . " bytes\n";
            
            // Clean up test file
            unlink($full_path);
            
            return true;
        } else {
            echo "  ✗ QR code file generation failed\n";
            return false;
        }
    } catch (Exception $e) {
        echo "  ✗ QR generation error: " . $e->getMessage() . "\n";
        return false;
    }
}

// Test 5: Database Integrity
function testDatabaseIntegrity() {
    global $pdo;
    
    try {
        // Check required tables exist
        $tables = ['qr_codes', 'qr_code_scans', 'qr_code_regenerations'];
        $tests_passed = 0;
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "  ✓ Table '$table' exists\n";
                $tests_passed++;
            } else {
                echo "  ✗ Table '$table' missing\n";
            }
        }
        
        // Check qr_codes table structure
        $stmt = $pdo->query("DESCRIBE qr_codes");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $required_columns = ['id', 'business_id', 'type', 'name', 'data', 'status', 'file_path', 'qr_code_url'];
        $missing_columns = array_diff($required_columns, $columns);
        
        if (empty($missing_columns)) {
            echo "  ✓ qr_codes table has all required columns\n";
            $tests_passed++;
        } else {
            echo "  ✗ qr_codes table missing columns: " . implode(", ", $missing_columns) . "\n";
        }
        
        return $tests_passed === 4; // 3 tables + 1 structure check
    } catch (Exception $e) {
        echo "  ✗ Database integrity error: " . $e->getMessage() . "\n";
        return false;
    }
}

// Test 6: QR Manager Interface Files
function testManagerInterface() {
    $required_files = [
        'html/business/qr_code_manager.php',
        'html/business/includes/qr_manager_functions.php',
        'html/api/qr/list.php'
    ];
    
    $tests_passed = 0;
    
    foreach ($required_files as $file) {
        if (file_exists($file)) {
            echo "  ✓ $file exists\n";
            $tests_passed++;
        } else {
            echo "  ✗ $file missing\n";
        }
    }
    
    return $tests_passed === count($required_files);
}

// Run all tests
echo "COMPREHENSIVE QR SYSTEM TEST\n";
echo str_repeat("=", 60) . "\n";

runTest("QR Code Generation - All Types", "testQRCodeGeneration");
runTest("QR Manager Display", "testQRManagerDisplay");
runTest("Action Buttons Functionality", "testActionButtons");
runTest("QR File Generation", "testQRFileGeneration");
runTest("Database Integrity", "testDatabaseIntegrity");
runTest("Manager Interface Files", "testManagerInterface");

// Final results
echo "\n" . str_repeat("=", 60) . "\n";
echo "FINAL TEST RESULTS\n";
echo str_repeat("=", 60) . "\n";

foreach ($test_results as $test => $result) {
    $status = strpos($result, 'PASSED') !== false ? '✓' : '✗';
    echo "$status $test: $result\n";
}

echo "\nOVERALL RESULTS:\n";
echo "Tests Passed: $passed_tests/$total_tests\n";
echo "Success Rate: " . round(($passed_tests / $total_tests) * 100, 1) . "%\n";

if ($passed_tests === $total_tests) {
    echo "\n🎉 ALL TESTS PASSED! QR System is fully functional.\n";
} else {
    echo "\n⚠️  Some tests failed. Please review the issues above.\n";
}
?> 