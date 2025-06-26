<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/includes/QRGenerator.php';

echo "<h1>QR Code Generation System Test</h1>\n";

// Test 1: Basic QR Generation
echo "<h2>Test 1: Basic QR Code Generation</h2>\n";
try {
    $generator = new QRGenerator();
    
    $basic_options = [
        'type' => 'static',
        'content' => 'https://example.com/test',
        'size' => 300,
        'foreground_color' => '#000000',
        'background_color' => '#FFFFFF',
        'error_correction_level' => 'H',
        'preview' => true
    ];
    
    $result = $generator->generate($basic_options);
    if ($result['success']) {
        echo "✅ Basic QR generation: SUCCESS<br>\n";
        echo "Preview URL length: " . strlen($result['url']) . " characters<br>\n";
    } else {
        echo "❌ Basic QR generation: FAILED - " . $result['message'] . "<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Basic QR generation: ERROR - " . $e->getMessage() . "<br>\n";
}

// Test 2: Advanced Features QR Generation
echo "<h2>Test 2: Advanced Features QR Code Generation</h2>\n";
try {
    $advanced_options = [
        'type' => 'dynamic_voting',
        'content' => '/public/vote.php?campaign=1',
        'size' => 400,
        'foreground_color' => '#1a5490',
        'background_color' => '#f8f9fa',
        'error_correction_level' => 'H',
        'preview' => true,
        
        // Module customization
        'module_shape' => 'rounded',
        'module_size' => 2,
        'module_spacing' => 1,
        'module_glow' => true,
        'module_glow_color' => '#0066cc',
        'module_glow_intensity' => 3,
        
        // Gradient
        'gradient' => [
            'type' => 'linear',
            'start' => '#1a5490',
            'end' => '#0066cc',
            'angle' => 45,
            'opacity' => 0.8
        ],
        
        // Eye customization
        'eye' => [
            'style' => 'rounded',
            'color' => '#0066cc',
            'size' => 2,
            'border' => [
                'color' => '#1a5490',
                'width' => 2
            ],
            'glow' => [
                'color' => '#0066cc',
                'intensity' => 5
            ]
        ],
        
        // Frame
        'frame' => [
            'style' => 'solid',
            'color' => '#1a5490',
            'width' => 3,
            'radius' => 10,
            'glow' => [
                'color' => '#0066cc',
                'intensity' => 2
            ]
        ],
        
        // Text
        'label' => [
            'text' => 'Vote for Your Favorites!',
            'font' => 'Arial',
            'size' => 14,
            'color' => '#1a5490',
            'alignment' => 'center',
            'rotation' => 0,
            'glow' => [
                'color' => '#ffffff',
                'intensity' => 3
            ]
        ],
        
        'bottom_text' => [
            'text' => 'Scan to Start Voting',
            'font' => 'Arial',
            'size' => 12,
            'color' => '#666666',
            'alignment' => 'center',
            'rotation' => 0
        ],
        
        // Effects
        'shadow' => [
            'color' => '#000000',
            'blur' => 8,
            'offset_x' => 3,
            'offset_y' => 3,
            'opacity' => 0.3
        ]
    ];
    
    $result = $generator->generate($advanced_options);
    if ($result['success']) {
        echo "✅ Advanced features QR generation: SUCCESS<br>\n";
        echo "Preview URL length: " . strlen($result['url']) . " characters<br>\n";
    } else {
        echo "❌ Advanced features QR generation: FAILED - " . $result['message'] . "<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Advanced features QR generation: ERROR - " . $e->getMessage() . "<br>\n";
}

// Test 3: Database Schema Verification
echo "<h2>Test 3: Database Schema Verification</h2>\n";
try {
    // Check votes table structure
    $stmt = $pdo->query("DESCRIBE votes");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['id', 'campaign_id', 'item_id', 'vote_type', 'voter_ip', 'created_at'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (empty($missing_columns)) {
        echo "✅ Votes table structure: COMPLETE<br>\n";
        echo "Columns: " . implode(', ', $columns) . "<br>\n";
    } else {
        echo "❌ Votes table structure: MISSING COLUMNS - " . implode(', ', $missing_columns) . "<br>\n";
    }
    
    // Check campaign_id index
    $stmt = $pdo->query("SHOW INDEX FROM votes WHERE Key_name = 'idx_votes_campaign'");
    $index = $stmt->fetch();
    
    if ($index) {
        echo "✅ Campaign ID index: EXISTS<br>\n";
    } else {
        echo "❌ Campaign ID index: MISSING<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database schema verification: ERROR - " . $e->getMessage() . "<br>\n";
}

// Test 4: Campaign-Voting List Relationships
echo "<h2>Test 4: Campaign-Voting List Relationships</h2>\n";
try {
    // Check if we have any campaigns
    $stmt = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status = 'active'");
    $campaign_count = $stmt->fetchColumn();
    
    echo "Active campaigns: $campaign_count<br>\n";
    
    // Check if we have any voting lists
    $stmt = $pdo->query("SELECT COUNT(*) FROM voting_lists");
    $list_count = $stmt->fetchColumn();
    
    echo "Voting lists: $list_count<br>\n";
    
    // Check junction table
    $stmt = $pdo->query("SELECT COUNT(*) FROM campaign_voting_lists");
    $junction_count = $stmt->fetchColumn();
    
    echo "Campaign-list relationships: $junction_count<br>\n";
    
    if ($campaign_count > 0 && $list_count > 0) {
        echo "✅ Basic data structure: READY<br>\n";
    } else {
        echo "⚠️ Basic data structure: NEEDS SAMPLE DATA<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Campaign-voting list relationships: ERROR - " . $e->getMessage() . "<br>\n";
}

// Test 5: QR Code URL Generation Test
echo "<h2>Test 5: QR Code URL Generation Patterns</h2>\n";
$url_patterns = [
    'static' => 'https://example.com/static-test',
    'dynamic' => 'https://example.com/dynamic-test',
    'dynamic_voting' => '/public/vote.php?campaign=123',
    'dynamic_vending' => '/public/vote.php?campaign=123&machine=Machine1',
    'machine_sales' => '/public/machine-sales.php?machine=Machine1',
    'promotion' => '/public/machine-sales.php?machine=Machine1&view=promotions',
    'cross_promo' => '/public/cross-promo.php?campaign=123',
    'stackable' => '/public/stackable.php?campaign=123'
];

foreach ($url_patterns as $type => $expected_pattern) {
    echo "QR Type: <strong>$type</strong> → $expected_pattern<br>\n";
}

// Test 6: File Permissions
echo "<h2>Test 6: File Permissions</h2>\n";
$upload_dir = __DIR__ . '/uploads/qr/';

if (is_dir($upload_dir)) {
    if (is_writable($upload_dir)) {
        echo "✅ QR upload directory: WRITABLE<br>\n";
    } else {
        echo "❌ QR upload directory: NOT WRITABLE<br>\n";
    }
    
    $perms = substr(sprintf('%o', fileperms($upload_dir)), -4);
    echo "Directory permissions: $perms<br>\n";
} else {
    echo "❌ QR upload directory: DOES NOT EXIST<br>\n";
}

echo "<h2>Summary</h2>\n";
echo "✅ = Working correctly<br>\n";
echo "❌ = Needs attention<br>\n";
echo "⚠️ = Warning/recommended action<br>\n";

echo "<hr>\n";
echo "<p><strong>Next Steps:</strong></p>\n";
echo "<ul>\n";
echo "<li>Test the QR generator interface at <a href='/qr-generator.php'>/qr-generator.php</a></li>\n";
echo "<li>Test voting functionality at <a href='/vote.php?campaign=1'>/vote.php?campaign=1</a></li>\n";
echo "<li>Check preview functionality in the QR generator</li>\n";
echo "<li>Verify all customization features apply to both preview and final images</li>\n";
echo "</ul>\n";
?> 