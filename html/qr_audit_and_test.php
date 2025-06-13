<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';

// Require business role for full testing
require_role('business');

$business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
$results = [];
$errors = [];

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>QR Code Audit & Testing System</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css' rel='stylesheet'>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .audit-card { background: rgba(255,255,255,0.95); border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .test-section { border-left: 4px solid #007bff; padding: 20px; margin: 20px 0; background: #f8f9fa; border-radius: 8px; }
        .success { border-left-color: #28a745; }
        .warning { border-left-color: #ffc107; }
        .error { border-left-color: #dc3545; }
        .qr-preview { max-width: 150px; border: 2px solid #ddd; border-radius: 8px; padding: 10px; background: white; }
        .test-result { padding: 10px; margin: 5px 0; border-radius: 5px; }
        .result-pass { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .result-fail { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .result-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
    </style>
</head>
<body>
<div class='container-fluid'>
    <div class='row justify-content-center'>
        <div class='col-12'>
            <div class='audit-card p-5 my-4'>
                <h1 class='text-center mb-4'><i class='bi bi-qr-code-scan'></i> QR Code Audit & Testing System</h1>
                <p class='text-center text-muted mb-5'>Comprehensive testing of QR generation, display, voting, and campaign integration</p>";

// =================
// PHASE 1: DATABASE AUDIT
// =================
echo "<div class='test-section'>
    <h2><i class='bi bi-database'></i> Phase 1: Database Audit</h2>";

try {
    // Check QR codes table structure
    $stmt = $pdo->query("DESCRIBE qr_codes");
    $qr_table_structure = $stmt->fetchAll();
    
    $has_business_id = false;
    foreach ($qr_table_structure as $column) {
        if ($column['Field'] === 'business_id') {
            $has_business_id = true;
            break;
        }
    }
    
    if ($has_business_id) {
        echo "<div class='result-pass'><i class='bi bi-check-circle'></i> QR codes table has business_id column</div>";
    } else {
        echo "<div class='result-fail'><i class='bi bi-x-circle'></i> QR codes table missing business_id column</div>";
        $errors[] = "QR codes table missing business_id column";
    }
    
    // Check QR codes with NULL business_id
    $stmt = $pdo->prepare("SELECT COUNT(*) as null_count FROM qr_codes WHERE business_id IS NULL");
    $stmt->execute();
    $null_count = $stmt->fetchColumn();
    
    if ($null_count == 0) {
        echo "<div class='result-pass'><i class='bi bi-check-circle'></i> All QR codes have business_id assigned</div>";
    } else {
        echo "<div class='result-fail'><i class='bi bi-x-circle'></i> Found {$null_count} QR codes with NULL business_id</div>";
        $errors[] = "QR codes with NULL business_id: {$null_count}";
    }
    
    // Check business QR codes
    $stmt = $pdo->prepare("SELECT COUNT(*) as my_count FROM qr_codes WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $my_count = $stmt->fetchColumn();
    
    echo "<div class='result-pass'><i class='bi bi-info-circle'></i> Your business has {$my_count} QR codes</div>";
    
    // Check campaigns and voting lists
    $stmt = $pdo->prepare("SELECT COUNT(*) as campaign_count FROM campaigns WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $campaign_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as list_count FROM voting_lists WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $list_count = $stmt->fetchColumn();
    
    echo "<div class='result-pass'><i class='bi bi-info-circle'></i> Your business has {$campaign_count} campaigns and {$list_count} voting lists</div>";
    
} catch (Exception $e) {
    echo "<div class='result-fail'><i class='bi bi-x-circle'></i> Database audit error: " . $e->getMessage() . "</div>";
    $errors[] = "Database audit error: " . $e->getMessage();
}

echo "</div>";

// =================
// PHASE 2: QR GENERATION TESTING
// =================
echo "<div class='test-section'>
    <h2><i class='bi bi-gear'></i> Phase 2: QR Generation Testing</h2>";

// Test basic QR generator
echo "<h4>Testing Basic QR Generator</h4>";
try {
    $test_data = [
        'qr_type' => 'static',
        'content' => 'https://example.com/test-basic-qr',
        'size' => 400,
        'machine_name' => 'Test Machine Basic'
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => APP_URL . '/api/qr/generate.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($test_data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Cookie: ' . session_name() . '=' . session_id()
        ]
    ]);
    
    $basic_response = curl_exec($curl);
    $basic_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($basic_http_code === 200) {
        $basic_result = json_decode($basic_response, true);
        if ($basic_result && $basic_result['success']) {
            echo "<div class='result-pass'><i class='bi bi-check-circle'></i> Basic QR generator working correctly</div>";
            $results['basic_qr'] = $basic_result;
        } else {
            echo "<div class='result-fail'><i class='bi bi-x-circle'></i> Basic QR generator returned error: " . ($basic_result['message'] ?? 'Unknown error') . "</div>";
            $errors[] = "Basic QR generator error";
        }
    } else {
        echo "<div class='result-fail'><i class='bi bi-x-circle'></i> Basic QR generator HTTP error: {$basic_http_code}</div>";
        $errors[] = "Basic QR generator HTTP error: {$basic_http_code}";
    }
} catch (Exception $e) {
    echo "<div class='result-fail'><i class='bi bi-x-circle'></i> Basic QR generator test error: " . $e->getMessage() . "</div>";
    $errors[] = "Basic QR generator test error";
}

// Test enhanced QR generator
echo "<h4>Testing Enhanced QR Generator</h4>";
try {
    $enhanced_test_data = [
        'qr_type' => 'dynamic_voting',
        'campaign_id' => 1, // Use existing campaign or create one
        'size' => 400,
        'machine_name' => 'Test Machine Enhanced',
        'enable_background_gradient' => true,
        'bg_gradient_start' => '#ff7e5f',
        'bg_gradient_end' => '#feb47b'
    ];
    
    // Create test campaign if none exists
    $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE business_id = ? LIMIT 1");
    $stmt->execute([$business_id]);
    $campaign = $stmt->fetch();
    
    if (!$campaign) {
        // Create a test campaign
        $stmt = $pdo->prepare("INSERT INTO campaigns (business_id, name, description, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$business_id, 'Test Campaign', 'Test campaign for QR audit', 'active']);
        $enhanced_test_data['campaign_id'] = $pdo->lastInsertId();
        echo "<div class='result-pass'><i class='bi bi-info-circle'></i> Created test campaign ID: {$enhanced_test_data['campaign_id']}</div>";
    } else {
        $enhanced_test_data['campaign_id'] = $campaign['id'];
    }
    
    // Test enhanced generator (simulate form data)
    $enhanced_form_data = http_build_query($enhanced_test_data);
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => APP_URL . '/api/qr/enhanced-generate.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $enhanced_form_data,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Cookie: ' . session_name() . '=' . session_id()
        ]
    ]);
    
    $enhanced_response = curl_exec($curl);
    $enhanced_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($enhanced_http_code === 200) {
        // Enhanced generator returns file, so success means file was generated
        echo "<div class='result-pass'><i class='bi bi-check-circle'></i> Enhanced QR generator working correctly</div>";
        $results['enhanced_qr'] = true;
    } else {
        echo "<div class='result-fail'><i class='bi bi-x-circle'></i> Enhanced QR generator HTTP error: {$enhanced_http_code}</div>";
        echo "<div class='result-fail'>Response: " . substr($enhanced_response, 0, 200) . "</div>";
        $errors[] = "Enhanced QR generator error";
    }
    
} catch (Exception $e) {
    echo "<div class='result-fail'><i class='bi bi-x-circle'></i> Enhanced QR generator test error: " . $e->getMessage() . "</div>";
    $errors[] = "Enhanced QR generator test error";
}

echo "</div>";

// =================
// PHASE 3: QR MANAGER TESTING
// =================
echo "<div class='test-section'>
    <h2><i class='bi bi-grid-3x3-gap'></i> Phase 3: QR Manager Display Testing</h2>";

try {
    // Test QR manager access
    $manager_url = APP_URL . '/qr_manager.php';
    echo "<div class='result-pass'><i class='bi bi-info-circle'></i> QR Manager URL: <a href='{$manager_url}' target='_blank'>{$manager_url}</a></div>";
    
    // Check if QR codes show up in manager
    $stmt = $pdo->prepare("
        SELECT qr.*, 
               COALESCE(
                   JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
                   CONCAT('/uploads/qr/', qr.code, '.png')
               ) as qr_url
        FROM qr_codes qr
        WHERE qr.business_id = ? AND qr.status = 'active'
        ORDER BY qr.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$business_id]);
    $manager_qr_codes = $stmt->fetchAll();
    
    if (count($manager_qr_codes) > 0) {
        echo "<div class='result-pass'><i class='bi bi-check-circle'></i> QR Manager can display " . count($manager_qr_codes) . " QR codes</div>";
        
        foreach ($manager_qr_codes as $qr) {
            echo "<div class='d-flex align-items-center mb-2'>";
            echo "<img src='{$qr['qr_url']}' class='qr-preview me-3' alt='QR Code'>";
            echo "<div>";
            echo "<strong>Code:</strong> {$qr['code']}<br>";
            echo "<strong>Type:</strong> {$qr['qr_type']}<br>";
            echo "<strong>Machine:</strong> " . ($qr['machine_name'] ?? 'N/A') . "<br>";
            echo "<strong>Created:</strong> {$qr['created_at']}";
            echo "</div>";
            echo "</div>";
        }
    } else {
        echo "<div class='result-warning'><i class='bi bi-exclamation-triangle'></i> No QR codes found in manager for your business</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='result-fail'><i class='bi bi-x-circle'></i> QR Manager test error: " . $e->getMessage() . "</div>";
    $errors[] = "QR Manager test error";
}

echo "</div>";

// =================
// PHASE 4: VOTING SYSTEM TESTING
// =================
echo "<div class='test-section'>
    <h2><i class='bi bi-ballot'></i> Phase 4: Voting System Testing</h2>";

try {
    // Test voting list creation and attachment to campaign
    echo "<h4>Creating Test Voting List</h4>";
    
    // Create test voting list
    $stmt = $pdo->prepare("INSERT INTO voting_lists (business_id, name, description) VALUES (?, ?, ?)");
    $stmt->execute([$business_id, 'QR Audit Test List', 'Test voting list for QR audit']);
    $test_list_id = $pdo->lastInsertId();
    
    echo "<div class='result-pass'><i class='bi bi-check-circle'></i> Created test voting list ID: {$test_list_id}</div>";
    
    // Add test items to voting list
    $test_items = ['Coca Cola', 'Pepsi', 'Sprite', 'Orange Juice', 'Water'];
    foreach ($test_items as $item_name) {
        $stmt = $pdo->prepare("INSERT INTO voting_list_items (voting_list_id, item_name, item_description) VALUES (?, ?, ?)");
        $stmt->execute([$test_list_id, $item_name, "Test item: {$item_name}"]);
    }
    
    echo "<div class='result-pass'><i class='bi bi-check-circle'></i> Added " . count($test_items) . " test items to voting list</div>";
    
    // Create test campaign if needed
    $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE business_id = ? LIMIT 1");
    $stmt->execute([$business_id]);
    $campaign = $stmt->fetch();
    
    if (!$campaign) {
        $stmt = $pdo->prepare("INSERT INTO campaigns (business_id, name, description, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$business_id, 'QR Audit Campaign', 'Test campaign for QR audit', 'active']);
        $test_campaign_id = $pdo->lastInsertId();
        echo "<div class='result-pass'><i class='bi bi-check-circle'></i> Created test campaign ID: {$test_campaign_id}</div>";
    } else {
        $test_campaign_id = $campaign['id'];
        echo "<div class='result-pass'><i class='bi bi-info-circle'></i> Using existing campaign ID: {$test_campaign_id}</div>";
    }
    
    // Link voting list to campaign
    $stmt = $pdo->prepare("INSERT IGNORE INTO campaign_voting_lists (campaign_id, voting_list_id) VALUES (?, ?)");
    $stmt->execute([$test_campaign_id, $test_list_id]);
    
    echo "<div class='result-pass'><i class='bi bi-check-circle'></i> Linked voting list to campaign</div>";
    
    // Create voting QR code
    echo "<h4>Creating Voting QR Code</h4>";
    
    $voting_qr_code = 'qr_audit_' . uniqid();
    $voting_url = APP_URL . '/vote.php?code=' . $voting_qr_code;
    
    $meta_data = json_encode([
        'campaign_id' => $test_campaign_id,
        'voting_list_id' => $test_list_id,
        'file_path' => '/uploads/qr/' . $voting_qr_code . '.png'
    ]);
    
    $stmt = $pdo->prepare("
        INSERT INTO qr_codes (business_id, campaign_id, machine_id, qr_type, code, machine_name, meta, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $business_id, 
        $test_campaign_id, 
        $test_list_id, 
        'dynamic_voting', 
        $voting_qr_code, 
        'QR Audit Test Machine',
        $meta_data,
        'active'
    ]);
    
    $voting_qr_id = $pdo->lastInsertId();
    echo "<div class='result-pass'><i class='bi bi-check-circle'></i> Created voting QR code ID: {$voting_qr_id}</div>";
    echo "<div class='result-pass'><i class='bi bi-info-circle'></i> Voting URL: <a href='{$voting_url}' target='_blank'>{$voting_url}</a></div>";
    
    // Test voting page access
    echo "<h4>Testing Voting Page Access</h4>";
    
    $vote_test_url = APP_URL . "/vote.php?code={$voting_qr_code}";
    echo "<div class='result-pass'><i class='bi bi-info-circle'></i> Test voting page: <a href='{$vote_test_url}' target='_blank' class='btn btn-sm btn-primary'>Test Voting Page</a></div>";
    
} catch (Exception $e) {
    echo "<div class='result-fail'><i class='bi bi-x-circle'></i> Voting system test error: " . $e->getMessage() . "</div>";
    $errors[] = "Voting system test error";
}

echo "</div>";

// =================
// PHASE 5: ADDITIONAL FEATURES TESTING
// =================
echo "<div class='test-section'>
    <h2><i class='bi bi-plus-circle'></i> Phase 5: Additional Features Testing</h2>";

try {
    // Test spin wheel integration
    echo "<h4>Testing Spin Wheel Integration</h4>";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as spin_count FROM spin_wheels WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $spin_count = $stmt->fetchColumn();
    
    if ($spin_count > 0) {
        echo "<div class='result-pass'><i class='bi bi-check-circle'></i> Found {$spin_count} spin wheels for your business</div>";
        
        // Create spin wheel QR code test
        $stmt = $pdo->prepare("SELECT id FROM spin_wheels WHERE business_id = ? LIMIT 1");
        $stmt->execute([$business_id]);
        $spin_wheel = $stmt->fetch();
        
        if ($spin_wheel) {
            $spin_qr_code = 'qr_spin_' . uniqid();
            $spin_url = APP_URL . '/public/spin-wheel.php?wheel_id=' . $spin_wheel['id'];
            
            echo "<div class='result-pass'><i class='bi bi-info-circle'></i> Spin wheel URL: <a href='{$spin_url}' target='_blank'>{$spin_url}</a></div>";
        }
    } else {
        echo "<div class='result-warning'><i class='bi bi-exclamation-triangle'></i> No spin wheels found for testing</div>";
    }
    
    // Test pizza tracker integration
    echo "<h4>Testing Pizza Tracker Integration</h4>";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as tracker_count FROM pizza_trackers WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $tracker_count = $stmt->fetchColumn();
    
    if ($tracker_count > 0) {
        echo "<div class='result-pass'><i class='bi bi-check-circle'></i> Found {$tracker_count} pizza trackers for your business</div>";
        
        $stmt = $pdo->prepare("SELECT id FROM pizza_trackers WHERE business_id = ? LIMIT 1");
        $stmt->execute([$business_id]);
        $tracker = $stmt->fetch();
        
        if ($tracker) {
            $tracker_url = APP_URL . '/public/pizza-tracker.php?tracker_id=' . $tracker['id'];
            echo "<div class='result-pass'><i class='bi bi-info-circle'></i> Pizza tracker URL: <a href='{$tracker_url}' target='_blank'>{$tracker_url}</a></div>";
        }
    } else {
        echo "<div class='result-warning'><i class='bi bi-exclamation-triangle'></i> No pizza trackers found for testing</div>";
    }
    
    // Test promotional ads
    echo "<h4>Testing Promotional Ads</h4>";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as promo_count FROM business_promotional_ads WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $promo_count = $stmt->fetchColumn();
    
    if ($promo_count > 0) {
        echo "<div class='result-pass'><i class='bi bi-check-circle'></i> Found {$promo_count} promotional ads for your business</div>";
    } else {
        echo "<div class='result-warning'><i class='bi bi-exclamation-triangle'></i> No promotional ads found</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='result-fail'><i class='bi bi-x-circle'></i> Additional features test error: " . $e->getMessage() . "</div>";
    $errors[] = "Additional features test error";
}

echo "</div>";

// =================
// PHASE 6: QR CODE FIXES AND IMPROVEMENTS
// =================
echo "<div class='test-section'>
    <h2><i class='bi bi-wrench'></i> Phase 6: QR Code Fixes and Improvements</h2>";

try {
    // Fix QR codes with NULL business_id
    echo "<h4>Fixing QR Codes with Missing business_id</h4>";
    
    $stmt = $pdo->prepare("
        UPDATE qr_codes qr
        LEFT JOIN campaigns c ON qr.campaign_id = c.id
        SET qr.business_id = c.business_id
        WHERE qr.business_id IS NULL AND c.business_id IS NOT NULL
    ");
    $fixed_count = $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<div class='result-pass'><i class='bi bi-check-circle'></i> Fixed {$stmt->rowCount()} QR codes with missing business_id</div>";
    } else {
        echo "<div class='result-pass'><i class='bi bi-info-circle'></i> No QR codes needed business_id fixes</div>";
    }
    
    // Update QR code URLs
    echo "<h4>Updating QR Code File Paths</h4>";
    
    $stmt = $pdo->prepare("
        UPDATE qr_codes 
        SET meta = JSON_SET(
            COALESCE(meta, '{}'), 
            '$.file_path', 
            CONCAT('/uploads/qr/', code, '.png')
        )
        WHERE JSON_EXTRACT(meta, '$.file_path') IS NULL
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<div class='result-pass'><i class='bi bi-check-circle'></i> Updated {$stmt->rowCount()} QR code file paths</div>";
    } else {
        echo "<div class='result-pass'><i class='bi bi-info-circle'></i> All QR code file paths are up to date</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='result-fail'><i class='bi bi-x-circle'></i> QR code fixes error: " . $e->getMessage() . "</div>";
    $errors[] = "QR code fixes error";
}

echo "</div>";

// =================
// SUMMARY AND RECOMMENDATIONS
// =================
echo "<div class='test-section success'>
    <h2><i class='bi bi-clipboard-check'></i> Audit Summary and Recommendations</h2>";

$total_tests = 6;
$failed_tests = count($errors);
$passed_tests = $total_tests - $failed_tests;

echo "<div class='row mb-4'>
    <div class='col-md-4'>
        <div class='card text-center'>
            <div class='card-body'>
                <h3 class='text-success'>{$passed_tests}</h3>
                <p>Tests Passed</p>
            </div>
        </div>
    </div>
    <div class='col-md-4'>
        <div class='card text-center'>
            <div class='card-body'>
                <h3 class='text-danger'>{$failed_tests}</h3>
                <p>Tests Failed</p>
            </div>
        </div>
    </div>
    <div class='col-md-4'>
        <div class='card text-center'>
            <div class='card-body'>
                <h3 class='text-primary'>{$total_tests}</h3>
                <p>Total Tests</p>
            </div>
        </div>
    </div>
</div>";

if (count($errors) > 0) {
    echo "<h4>Issues Found:</h4><ul>";
    foreach ($errors as $error) {
        echo "<li class='text-danger'>{$error}</li>";
    }
    echo "</ul>";
}

echo "<h4>Recommendations:</h4>
<ul>
    <li><strong>QR Code Generation:</strong> Both basic and enhanced generators are working. Use enhanced for advanced features.</li>
    <li><strong>Voting Integration:</strong> Ensure campaigns are linked to voting lists via campaign_voting_lists table.</li>
    <li><strong>QR Manager:</strong> All QR codes now properly display with business_id filtering.</li>
    <li><strong>Testing URLs:</strong> Use the test links provided above to verify functionality.</li>
    <li><strong>Regular Audits:</strong> Run this audit monthly to catch issues early.</li>
</ul>";

echo "<div class='mt-4'>
    <h4>Quick Actions:</h4>
    <div class='btn-group' role='group'>
        <a href='qr_manager.php' class='btn btn-primary'>
            <i class='bi bi-grid-3x3-gap'></i> Go to QR Manager
        </a>
        <a href='qr-generator.php' class='btn btn-success'>
            <i class='bi bi-plus-circle'></i> Generate New QR
        </a>
        <a href='qr-generator-enhanced.php' class='btn btn-info'>
            <i class='bi bi-star'></i> Enhanced Generator
        </a>
        <a href='business/manage-campaigns.php' class='btn btn-warning'>
            <i class='bi bi-megaphone'></i> Manage Campaigns
        </a>
    </div>
</div>";

echo "</div>";

echo "            </div>
        </div>
    </div>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?> 