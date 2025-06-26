<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';

// Require business role
require_role('business');

$business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);

echo "<h1>🔧 QR System Fix Verification</h1>";

echo "<h2>📊 Current QR Codes Status</h2>";

// Check QR codes by business_id
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN business_id IS NULL THEN 1 ELSE 0 END) as null_business_id,
        SUM(CASE WHEN business_id = ? THEN 1 ELSE 0 END) as my_business,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_codes
    FROM qr_codes
");
$stmt->execute([$business_id]);
$stats = $stmt->fetch();

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h3>📈 Database Statistics</h3>";
echo "<p><strong>Total QR Codes:</strong> {$stats['total']}</p>";
echo "<p><strong>NULL business_id:</strong> {$stats['null_business_id']} " . ($stats['null_business_id'] > 0 ? "❌" : "✅") . "</p>";
echo "<p><strong>My Business QR Codes:</strong> {$stats['my_business']}</p>";
echo "<p><strong>Active QR Codes:</strong> {$stats['active_codes']}</p>";
echo "</div>";

// Test QR display query
echo "<h2>🔍 QR Display Query Test</h2>";

$stmt = $pdo->prepare("
    SELECT qc.id, qc.business_id, qc.qr_type, qc.machine_name, qc.code, qc.status,
           COALESCE(
               JSON_UNQUOTE(JSON_EXTRACT(qc.meta, '$.file_path')),
               CONCAT('/uploads/qr/', qc.code, '.png')
           ) as qr_url
    FROM qr_codes qc
    WHERE qc.business_id = ? 
    AND qc.status = 'active'
    ORDER BY qc.created_at DESC
    LIMIT 5
");
$stmt->execute([$business_id]);
$qr_codes = $stmt->fetchAll();

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h3>🎯 Recent QR Codes for Business ID: {$business_id}</h3>";

if (empty($qr_codes)) {
    echo "<p>❌ No QR codes found for your business. This could indicate:</p>";
    echo "<ul>";
    echo "<li>No QR codes have been created yet</li>";
    echo "<li>QR codes are missing business_id</li>";
    echo "<li>All QR codes are inactive/deleted</li>";
    echo "</ul>";
} else {
    echo "<p>✅ Found " . count($qr_codes) . " active QR codes:</p>";
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #dee2e6;'>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>ID</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Type</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Machine</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Code</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Preview</th>";
    echo "</tr>";
    
    foreach ($qr_codes as $qr) {
        echo "<tr>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$qr['id']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$qr['qr_type']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . ($qr['machine_name'] ?: '-') . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd; font-family: monospace; font-size: 12px;'>" . substr($qr['code'], 0, 20) . "...</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>";
        if (file_exists(__DIR__ . $qr['qr_url'])) {
            echo "<img src='{$qr['qr_url']}' style='width: 50px; height: 50px;' alt='QR Code'>";
        } else {
            echo "❌ File missing";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// Test API endpoint
echo "<h2>🧪 API Test</h2>";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h3>🚀 Test QR Generation</h3>";
echo "<p>Click the button below to test creating a static QR code:</p>";

echo "<form method='POST' style='margin: 10px 0;'>";
echo "<input type='hidden' name='test_qr' value='1'>";
echo "<button type='submit' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Generate Test QR Code</button>";
echo "</form>";

if (isset($_POST['test_qr'])) {
    $test_url = 'https://example.com/test-' . time();
    
    // Test API call
    $api_data = [
        'qr_type' => 'static',
        'content' => $test_url,
        'size' => 300,
        'foreground_color' => '#000000',
        'background_color' => '#FFFFFF',
        'error_correction_level' => 'H'
    ];
    
    // Simulate API call
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, APP_URL . '/api/qr/generate.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Cookie: ' . $_SERVER['HTTP_COOKIE']
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<div style='background: " . ($http_code === 200 ? "#d4edda" : "#f8d7da") . "; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>" . ($http_code === 200 ? "✅" : "❌") . " API Test Result (HTTP {$http_code})</h4>";
    echo "<p><strong>Test URL:</strong> {$test_url}</p>";
    echo "<p><strong>Response:</strong></p>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto;'>" . htmlspecialchars($response) . "</pre>";
    echo "</div>";
    
    // Refresh to show new QR code
    echo "<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
}

echo "</div>";

// Quick links
echo "<h2>🔗 Quick Actions</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<p>Test the QR system with these links:</p>";
echo "<ul>";
echo "<li><a href='/qr-generator.php' target='_blank'>🎯 Basic QR Generator</a></li>";
echo "<li><a href='/qr-generator-enhanced.php' target='_blank'>🎨 Enhanced QR Generator</a></li>";
echo "<li><a href='/qr-display.php' target='_blank'>📺 QR Display Page</a></li>";
echo "<li><a href='/qr_manager.php' target='_blank'>📊 QR Manager</a></li>";
echo "</ul>";
echo "</div>";

echo "<h2>✅ Expected Behavior After Fix</h2>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<ol>";
echo "<li><strong>Create QR Code:</strong> Both basic and enhanced generators should save business_id</li>";
echo "<li><strong>Display QR Codes:</strong> All created QR codes should appear in QR Display and QR Manager</li>";
echo "<li><strong>Multi-tenant Isolation:</strong> Users only see their own business QR codes</li>";
echo "<li><strong>File Access:</strong> QR code images should be accessible and display properly</li>";
echo "</ol>";
echo "</div>";

?> 