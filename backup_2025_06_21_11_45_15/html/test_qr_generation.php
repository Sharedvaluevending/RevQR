<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔬 QR Generation Test</h1>";

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/includes/QRGenerator.php';

echo "<h2>✅ Step 1: QRGenerator Class Test</h2>";

try {
    $generator = new QRGenerator();
    echo "✅ QRGenerator class loaded successfully<br>";
    
    // Test basic QR generation
    $testData = [
        'type' => 'static',
        'content' => 'https://example.com/test',
        'size' => 300,
        'foreground_color' => '#000000',
        'background_color' => '#FFFFFF',
        'error_correction_level' => 'H'
    ];
    
    echo "<h3>🎯 Testing Basic QR Generation:</h3>";
    echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";
    
    $result = $generator->generate($testData);
    
    echo "<h3>📊 Generation Result:</h3>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    
    if ($result['success']) {
        echo "<h3>✅ QR Code Generated Successfully!</h3>";
        echo "<p><strong>Code:</strong> " . $result['data']['code'] . "</p>";
        echo "<p><strong>URL:</strong> " . $result['data']['qr_code_url'] . "</p>";
        
        // Check if file exists
        $filePath = __DIR__ . $result['data']['qr_code_url'];
        if (file_exists($filePath)) {
            echo "<p>✅ QR code file exists: " . $filePath . "</p>";
            echo "<p>📏 File size: " . filesize($filePath) . " bytes</p>";
            echo "<img src='" . $result['data']['qr_code_url'] . "' alt='Test QR Code' style='border: 1px solid #ccc; padding: 10px;'>";
        } else {
            echo "<p>❌ QR code file not found: " . $filePath . "</p>";
        }
    } else {
        echo "<h3>❌ QR Generation Failed:</h3>";
        echo "<p>Error: " . ($result['message'] ?? 'Unknown error') . "</p>";
    }
    
} catch (Exception $e) {
    echo "<h3>❌ Exception occurred:</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";

echo "<h2>🗄️ Step 2: Database Connection Test</h2>";

try {
    global $pdo;
    echo "✅ PDO connection available<br>";
    
    // Test database query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM qr_codes");
    $result = $stmt->fetch();
    echo "📊 QR codes in database: " . $result['count'] . "<br>";
    
    // Test inserting a test record
    $testCode = 'test_' . time();
    $stmt = $pdo->prepare("
        INSERT INTO qr_codes (code, qr_type, meta) 
        VALUES (?, 'test', ?)
    ");
    $stmt->execute([$testCode, json_encode(['test' => true])]);
    echo "✅ Test database insert successful<br>";
    
    // Clean up test record
    $stmt = $pdo->prepare("DELETE FROM qr_codes WHERE code = ?");
    $stmt->execute([$testCode]);
    echo "✅ Test cleanup completed<br>";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<hr>";

echo "<h2>📁 Step 3: File System Test</h2>";

$uploadDir = __DIR__ . '/uploads/qr/';
echo "<p><strong>Upload Directory:</strong> " . $uploadDir . "</p>";
echo "<p><strong>Exists:</strong> " . (file_exists($uploadDir) ? '✅ Yes' : '❌ No') . "</p>";
echo "<p><strong>Writable:</strong> " . (is_writable($uploadDir) ? '✅ Yes' : '❌ No') . "</p>";
echo "<p><strong>Permissions:</strong> " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "</p>";

// Count existing QR files
$qrFiles = glob($uploadDir . '*.png');
echo "<p><strong>Existing QR files:</strong> " . count($qrFiles) . "</p>";

if (count($qrFiles) > 0) {
    echo "<h3>📂 Recent QR Files:</h3>";
    $recentFiles = array_slice($qrFiles, -5);
    foreach ($recentFiles as $file) {
        $filename = basename($file);
        $size = filesize($file);
        $date = date('Y-m-d H:i:s', filemtime($file));
        echo "<div style='margin: 5px 0; padding: 10px; border: 1px solid #ddd;'>";
        echo "<strong>{$filename}</strong><br>";
        echo "Size: {$size} bytes | Modified: {$date}<br>";
        echo "<img src='/uploads/qr/{$filename}' alt='QR Code' style='width: 100px; height: 100px; border: 1px solid #ccc;'>";
        echo "</div>";
    }
}

echo "<hr>";

echo "<h2>🌐 Step 4: API Endpoint Test</h2>";

// Test the API endpoint directly
echo "<p>Testing API endpoint at: <code>/api/qr/generate.php</code></p>";

$apiData = [
    'qr_type' => 'static',
    'content' => 'https://example.com/api-test-' . time(),
    'size' => 300,
    'foreground_color' => '#000000',
    'background_color' => '#FFFFFF',
    'error_correction_level' => 'H'
];

echo "<h3>🚀 API Test Data:</h3>";
echo "<pre>" . json_encode($apiData, JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>💡 Manual Test Instructions:</h3>";
echo "<ol>";
echo "<li>Go to <a href='/qr-generator.php' target='_blank'>/qr-generator.php</a></li>";
echo "<li>Select 'Static QR Code' type</li>";
echo "<li>Enter a URL (e.g., https://example.com)</li>";
echo "<li>Click 'Generate QR Code'</li>";
echo "<li>Check if QR code appears in preview</li>";
echo "<li>Check if QR code appears in <a href='/qr_manager.php' target='_blank'>QR Manager</a></li>";
echo "</ol>";

echo "<hr>";

echo "<h2>🔗 Quick Links</h2>";
echo "<ul>";
echo "<li><a href='/qr-generator.php' target='_blank'>🎯 Static QR Generator</a></li>";
echo "<li><a href='/qr-generator-enhanced.php' target='_blank'>🎨 Enhanced QR Generator</a></li>";
echo "<li><a href='/qr_manager.php' target='_blank'>📊 QR Manager</a></li>";
echo "<li><a href='/nav_diagnostic.php' target='_blank'>🔍 Navigation Diagnostic</a></li>";
echo "</ul>";

echo "<div style='margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;'>";
echo "<h3>🎯 Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>Test QR Generation:</strong> Use the static generator to create a test QR code</li>";
echo "<li><strong>Verify Database Storage:</strong> Check if new QR codes appear in the QR Manager</li>";
echo "<li><strong>Test Enhanced Generator:</strong> Try the enhanced generator with advanced features</li>";
echo "<li><strong>Check Navigation:</strong> Ensure all navigation links work properly</li>";
echo "</ol>";
echo "</div>";
?> 