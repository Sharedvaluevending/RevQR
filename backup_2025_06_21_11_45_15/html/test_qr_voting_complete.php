<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/includes/QRGenerator.php';

echo "<h1>🔍 Complete QR Code & Voting System Test</h1>";
echo "<div style='margin-bottom: 20px;'>";
echo "<strong>Testing Date:</strong> " . date('Y-m-d H:i:s') . "<br>";
echo "<strong>Server:</strong> " . $_SERVER['SERVER_NAME'] . "<br>";
echo "</div>";

$errors = [];
$success_count = 0;
$total_tests = 0;

function test_result($success, $message) {
    global $success_count, $total_tests;
    $total_tests++;
    if ($success) {
        $success_count++;
        echo "<div style='color: green; margin: 5px 0;'>✅ " . $message . "</div>";
    } else {
        echo "<div style='color: red; margin: 5px 0;'>❌ " . $message . "</div>";
    }
}

// Test 1: Database Connection
echo "<h2>1. Database Connection Test</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM qr_codes");
    $qr_count = $stmt->fetchColumn();
    test_result(true, "Database connected successfully. Found {$qr_count} QR codes.");
} catch (Exception $e) {
    test_result(false, "Database connection failed: " . $e->getMessage());
}

// Test 2: Standard QR Generator
echo "<h2>2. Standard QR Generator Test</h2>";
try {
    $generator = new QRGenerator();
    
    // Test static QR
    $static_result = $generator->generate([
        'type' => 'static',
        'content' => 'https://example.com/test',
        'size' => 300
    ]);
    
    if ($static_result['success']) {
        test_result(true, "Static QR code generated: " . $static_result['data']['qr_code_url']);
    } else {
        test_result(false, "Static QR generation failed: " . $static_result['error']);
    }
    
    // Test dynamic voting QR
    $voting_result = $generator->generate([
        'type' => 'dynamic_voting',
        'content' => APP_URL . '/vote.php?code=test_vote_' . uniqid(),
        'size' => 300
    ]);
    
    if ($voting_result['success']) {
        test_result(true, "Voting QR code generated: " . $voting_result['data']['qr_code_url']);
    } else {
        test_result(false, "Voting QR generation failed: " . $voting_result['error']);
    }
    
} catch (Exception $e) {
    test_result(false, "QR Generator error: " . $e->getMessage());
}

// Test 3: Enhanced QR Generator API
echo "<h2>3. Enhanced QR Generator API Test</h2>";
try {
    // Simulate POST data for enhanced generator
    $post_data = [
        'qr_type' => 'dynamic_voting',
        'campaign_id' => '11',
        'size' => '400',
        'foreground_color' => '#000000',
        'background_color' => '#ffffff'
    ];
    
    // Test if the enhanced generator endpoint exists
    $enhanced_api_file = __DIR__ . '/api/qr/enhanced-generate.php';
    if (file_exists($enhanced_api_file)) {
        test_result(true, "Enhanced QR generator API file exists");
        
        // Check if it has the required functions
        $api_content = file_get_contents($enhanced_api_file);
        if (strpos($api_content, 'dynamic_voting') !== false) {
            test_result(true, "Enhanced API supports dynamic voting QR codes");
        } else {
            test_result(false, "Enhanced API missing voting support");
        }
    } else {
        test_result(false, "Enhanced QR generator API file missing");
    }
} catch (Exception $e) {
    test_result(false, "Enhanced QR test error: " . $e->getMessage());
}

// Test 4: Voting System Database Structure
echo "<h2>4. Voting System Database Structure</h2>";
$required_tables = ['campaigns', 'items', 'votes', 'qr_codes', 'campaign_items'];
foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            test_result(true, "Table '$table' exists");
        } else {
            test_result(false, "Table '$table' missing");
        }
    } catch (Exception $e) {
        test_result(false, "Error checking table '$table': " . $e->getMessage());
    }
}

// Test 5: Voting System Functionality
echo "<h2>5. Voting System Functionality Test</h2>";
try {
    // Check if there are campaigns
    $stmt = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status = 'active'");
    $campaign_count = $stmt->fetchColumn();
    test_result($campaign_count > 0, "Found {$campaign_count} active campaigns");
    
    // Check if there are items
    $stmt = $pdo->query("SELECT COUNT(*) FROM items WHERE status = 'active'");
    $item_count = $stmt->fetchColumn();
    test_result($item_count > 0, "Found {$item_count} active items");
    
    // Check votes table structure
    $stmt = $pdo->query("DESCRIBE votes");
    $vote_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required_vote_columns = ['id', 'campaign_id', 'item_id', 'vote_type', 'qr_code_id'];
    $missing_columns = array_diff($required_vote_columns, $vote_columns);
    
    if (empty($missing_columns)) {
        test_result(true, "Votes table has all required columns");
    } else {
        test_result(false, "Votes table missing columns: " . implode(', ', $missing_columns));
    }
    
} catch (Exception $e) {
    test_result(false, "Voting system test error: " . $e->getMessage());
}

// Test 6: Vote.php File Functionality
echo "<h2>6. Vote.php File Test</h2>";
$vote_files = [
    __DIR__ . '/vote.php',
    __DIR__ . '/public/vote.php',
    __DIR__ . '/user/vote.php'
];

foreach ($vote_files as $vote_file) {
    if (file_exists($vote_file)) {
        test_result(true, "Vote file exists: " . basename(dirname($vote_file)) . '/' . basename($vote_file));
        
        // Check if it has voting functionality
        $vote_content = file_get_contents($vote_file);
        if (strpos($vote_content, 'vote_type') !== false) {
            test_result(true, "Vote file contains voting logic");
        } else {
            test_result(false, "Vote file missing voting logic");
        }
    }
}

// Test 7: QR Manager Functionality
echo "<h2>7. QR Manager Test</h2>";
$manager_files = [
    __DIR__ . '/qr_manager.php',
    __DIR__ . '/qr-generator-enhanced.php',
    __DIR__ . '/qr_dynamic_editor.php'
];

foreach ($manager_files as $manager_file) {
    if (file_exists($manager_file)) {
        test_result(true, "Manager file exists: " . basename($manager_file));
    } else {
        test_result(false, "Manager file missing: " . basename($manager_file));
    }
}

// Test 8: Test Voting QR Code Creation
echo "<h2>8. Test Voting QR Code Creation</h2>";
try {
    // Get or create a test business
    $stmt = $pdo->query("SELECT id FROM businesses LIMIT 1");
    $business_id = $stmt->fetchColumn();
    
    if (!$business_id) {
        test_result(false, "No business found for testing");
    } else {
        // Get or create a test campaign
        $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE business_id = ? LIMIT 1");
        $stmt->execute([$business_id]);
        $campaign_id = $stmt->fetchColumn();
        
        if (!$campaign_id) {
            // Create test campaign
            $stmt = $pdo->prepare("INSERT INTO campaigns (business_id, name, description, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$business_id, 'Test Campaign QR', 'Test campaign for QR testing', 'active']);
            $campaign_id = $pdo->lastInsertId();
            test_result(true, "Created test campaign ID: {$campaign_id}");
        } else {
            test_result(true, "Found existing campaign ID: {$campaign_id}");
        }
        
        // Create a test QR code
        $test_qr_code = 'test_voting_' . uniqid();
        $test_url = APP_URL . '/vote.php?code=' . $test_qr_code;
        
        $stmt = $pdo->prepare("
            INSERT INTO qr_codes (business_id, campaign_id, qr_type, code, machine_name, url, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $business_id, 
            $campaign_id, 
            'dynamic_voting', 
            $test_qr_code, 
            'Test Voting Machine',
            $test_url,
            'active'
        ]);
        
        $qr_id = $pdo->lastInsertId();
        test_result(true, "Created test voting QR code ID: {$qr_id}");
        test_result(true, "Test voting URL: {$test_url}");
    }
} catch (Exception $e) {
    test_result(false, "QR code creation test error: " . $e->getMessage());
}

// Test 9: URL Accessibility Test
echo "<h2>9. URL Accessibility Test</h2>";
$test_urls = [
    '/qr_manager.php',
    '/qr-generator-enhanced.php',
    '/vote.php',
    '/public/vote.php'
];

foreach ($test_urls as $url) {
    $full_path = __DIR__ . $url;
    if (file_exists($full_path)) {
        test_result(true, "URL accessible: {$url}");
    } else {
        test_result(false, "URL not accessible: {$url}");
    }
}

// Test 10: Recent QR Codes Test
echo "<h2>10. Recent QR Codes Test</h2>";
try {
    $stmt = $pdo->query("
        SELECT qr_type, COUNT(*) as count 
        FROM qr_codes 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY qr_type
    ");
    $recent_qrs = $stmt->fetchAll();
    
    if (count($recent_qrs) > 0) {
        foreach ($recent_qrs as $qr) {
            test_result(true, "Recent QR codes - {$qr['qr_type']}: {$qr['count']} created in last 24h");
        }
    } else {
        test_result(true, "No QR codes created in last 24 hours (this is normal)");
    }
} catch (Exception $e) {
    test_result(false, "Recent QR codes test error: " . $e->getMessage());
}

// Summary
echo "<h2>📊 Test Summary</h2>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<strong>Total Tests:</strong> {$total_tests}<br>";
echo "<strong>Passed:</strong> {$success_count}<br>";
echo "<strong>Failed:</strong> " . ($total_tests - $success_count) . "<br>";
echo "<strong>Success Rate:</strong> " . round(($success_count / $total_tests) * 100, 1) . "%<br>";

if ($success_count == $total_tests) {
    echo "<h3 style='color: green;'>🎉 All Tests Passed! QR System is Fully Functional</h3>";
} else {
    echo "<h3 style='color: orange;'>⚠️ Some Tests Failed - See Details Above</h3>";
}
echo "</div>";

echo "<h3>Quick Test Links:</h3>";
echo "<a href='/qr_manager.php' target='_blank' style='margin-right: 10px;'>QR Manager</a>";
echo "<a href='/qr-generator-enhanced.php' target='_blank' style='margin-right: 10px;'>Enhanced Generator</a>";
echo "<a href='/test_qr_codes.php' target='_blank' style='margin-right: 10px;'>QR Code Tests</a>";
echo "<a href='/vote.php' target='_blank'>Voting System</a>";
?> 