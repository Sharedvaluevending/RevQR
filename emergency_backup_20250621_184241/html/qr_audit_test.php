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
// PHASE 2: VOTING SYSTEM TESTING
// =================
echo "<div class='test-section'>
    <h2><i class='bi bi-ballot'></i> Phase 2: Voting System Testing</h2>";

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

echo "            </div>
        </div>
    </div>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?> 