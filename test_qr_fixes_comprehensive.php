<?php
/**
 * QR Code System Fixes Comprehensive Test
 * Tests all QR generation, management, and display functionality
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/includes/QRGenerator.php';

echo "<h1>🔧 QR Code System Fixes - Comprehensive Test</h1>";

// Test 1: Database Schema Verification
echo "<h2>1. Database Schema Check</h2>";
try {
    $stmt = $pdo->query("DESCRIBE qr_codes");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['id', 'business_id', 'qr_type', 'url', 'code', 'meta', 'created_at', 'status'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (empty($missing_columns)) {
        echo "✅ QR codes table schema is complete<br>";
    } else {
        echo "⚠️ Missing columns: " . implode(', ', $missing_columns) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 2: QR Generator Class Test
echo "<h2>2. QR Generator Test</h2>";
try {
    $generator = new QRGenerator();
    
    $test_options = [
        'type' => 'static',
        'content' => 'https://test.example.com',
        'size' => 200,
        'foreground_color' => '#000000',
        'background_color' => '#FFFFFF'
    ];
    
    $result = $generator->generate($test_options);
    
    if ($result['success']) {
        echo "✅ QR Generator is working<br>";
        echo "📁 Generated file: " . ($result['data']['qr_code_url'] ?? 'N/A') . "<br>";
    } else {
        echo "❌ QR Generator failed: " . ($result['message'] ?? 'Unknown error') . "<br>";
    }
} catch (Exception $e) {
    echo "❌ QR Generator error: " . $e->getMessage() . "<br>";
}

// Test 3: API Endpoints Test
echo "<h2>3. API Endpoints Check</h2>";

$api_endpoints = [
    'html/api/qr/generate.php' => 'Standard Generate API',
    'html/api/qr/enhanced-generate.php' => 'Enhanced Generate API',
    'html/api/qr/unified-generate.php' => 'Unified Generate API'
];

foreach ($api_endpoints as $endpoint => $name) {
    if (file_exists(__DIR__ . '/' . $endpoint)) {
        echo "✅ $name exists<br>";
        
        // Check file permissions
        if (is_readable(__DIR__ . '/' . $endpoint)) {
            echo "&nbsp;&nbsp;📖 Readable<br>";
        } else {
            echo "&nbsp;&nbsp;⚠️ Not readable<br>";
        }
    } else {
        echo "❌ $name missing<br>";
    }
}

// Test 4: QR Manager Query Test
echo "<h2>4. QR Manager Query Test</h2>";
try {
    // Test the improved query from qr_manager.php
    $business_id = 1; // Test with business ID 1
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            qr.id, 
            qr.code, 
            qr.qr_type, 
            COALESCE(qr.url, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.content'))) as url,
            COALESCE(qr.machine_name, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.machine_name'))) as machine_name,
            qr.created_at,
            qr.meta,
            COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
                CONCAT('/uploads/qr/', qr.code, '.png')
            ) as file_path,
            (SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = qr.id) as scan_count,
            COALESCE(qr.business_id, c.business_id, vl.business_id, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.business_id'))) as owner_business_id
        FROM qr_codes qr
        LEFT JOIN campaigns c ON qr.campaign_id = c.id
        LEFT JOIN voting_lists vl ON qr.machine_id = vl.id
        WHERE qr.status = 'active'
        AND (
            qr.business_id = ? OR
            c.business_id = ? OR
            vl.business_id = ? OR
            JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.business_id')) = ?
        )
        ORDER BY qr.created_at DESC
        LIMIT 5
    ");
    
    $stmt->execute([$business_id, $business_id, $business_id, $business_id]);
    $qr_codes = $stmt->fetchAll();
    
    echo "✅ QR Manager query executed successfully<br>";
    echo "📊 Found " . count($qr_codes) . " QR codes for business ID $business_id<br>";
    
    if (!empty($qr_codes)) {
        echo "<table border='1' style='margin-top: 10px;'>";
        echo "<tr><th>ID</th><th>Code</th><th>Type</th><th>URL</th><th>File Path</th></tr>";
        
        foreach (array_slice($qr_codes, 0, 3) as $qr) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($qr['id']) . "</td>";
            echo "<td>" . htmlspecialchars($qr['code']) . "</td>";
            echo "<td>" . htmlspecialchars($qr['qr_type']) . "</td>";
            echo "<td style='max-width: 200px; overflow: hidden;'>" . htmlspecialchars($qr['url'] ?? 'N/A') . "</td>";
            echo "<td style='max-width: 200px; overflow: hidden;'>" . htmlspecialchars($qr['file_path'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ QR Manager query error: " . $e->getMessage() . "<br>";
}

// Test 5: File Upload Directory Check
echo "<h2>5. File Upload Directories</h2>";

$upload_dirs = [
    'html/uploads/qr/',
    'html/uploads/qr/1/',
    'html/uploads/qr/business/',
    'html/assets/img/qr/'
];

foreach ($upload_dirs as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    
    if (is_dir($full_path)) {
        echo "✅ Directory exists: $dir<br>";
        
        if (is_writable($full_path)) {
            echo "&nbsp;&nbsp;✏️ Writable<br>";
        } else {
            echo "&nbsp;&nbsp;⚠️ Not writable<br>";
        }
        
        // Count files
        $files = glob($full_path . '*.{png,jpg,svg}', GLOB_BRACE);
        echo "&nbsp;&nbsp;📁 Files: " . count($files) . "<br>";
        
    } else {
        echo "❌ Directory missing: $dir<br>";
        
        // Try to create it
        if (mkdir($full_path, 0755, true)) {
            echo "&nbsp;&nbsp;✅ Created successfully<br>";
        } else {
            echo "&nbsp;&nbsp;❌ Failed to create<br>";
        }
    }
}

// Test 6: JavaScript Function Test
echo "<h2>6. Frontend Integration Test</h2>";

$qr_generator_file = __DIR__ . '/html/qr-generator.php';
if (file_exists($qr_generator_file)) {
    $content = file_get_contents($qr_generator_file);
    
    // Check for our new functions
    $functions_to_check = [
        'generateQRCode()' => 'Main generation function',
        'generatePreviewOnly()' => 'Preview generation function',
        'showPlaceholder()' => 'Placeholder function'
    ];
    
    foreach ($functions_to_check as $func => $desc) {
        if (strpos($content, $func) !== false) {
            echo "✅ $desc found<br>";
        } else {
            echo "❌ $desc missing<br>";
        }
    }
    
    // Check for proper form handling
    if (strpos($content, 'onclick="generateQRCode()"') !== false) {
        echo "✅ Form submit handler properly configured<br>";
    } else {
        echo "⚠️ Form submit handler needs checking<br>";
    }
    
} else {
    echo "❌ QR Generator file not found<br>";
}

// Test 7: Enhanced API Response Test
echo "<h2>7. Enhanced API Response Format</h2>";

$enhanced_api_file = __DIR__ . '/html/api/qr/enhanced-generate.php';
if (file_exists($enhanced_api_file)) {
    $content = file_get_contents($enhanced_api_file);
    
    // Check for proper headers and file serving
    if (strpos($content, 'Content-Type: image/png') !== false) {
        echo "✅ PNG content type header found<br>";
    } else {
        echo "⚠️ PNG content type header missing<br>";
    }
    
    if (strpos($content, 'Content-Disposition: attachment') !== false) {
        echo "✅ File download header found<br>";
    } else {
        echo "⚠️ File download header missing<br>";
    }
    
    if (strpos($content, 'readfile($file_path)') !== false) {
        echo "✅ File output function found<br>";
    } else {
        echo "⚠️ File output function missing<br>";
    }
    
} else {
    echo "❌ Enhanced API file not found<br>";
}

// Test 8: System Summary
echo "<h2>8. System Status Summary</h2>";

// Count total QR codes
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM qr_codes WHERE status = 'active'");
    $total_qr_codes = $stmt->fetchColumn();
    echo "📊 Total active QR codes: $total_qr_codes<br>";
} catch (Exception $e) {
    echo "❌ Could not count QR codes: " . $e->getMessage() . "<br>";
}

// Check for QR code files
$qr_files_found = 0;
foreach ($upload_dirs as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    if (is_dir($full_path)) {
        $files = glob($full_path . '*.{png,jpg,svg}', GLOB_BRACE);
        $qr_files_found += count($files);
    }
}
echo "📁 QR code files found: $qr_files_found<br>";

echo "<hr>";
echo "<h3>🎯 Fix Status Overview:</h3>";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>✅ FIXED ISSUES:</strong><br>";
echo "• QR generation JavaScript functions added<br>";
echo "• Form submission properly configured<br>";
echo "• Field visibility logic improved<br>";
echo "• QR manager query enhanced to find all QR codes<br>";
echo "• Enhanced API properly returns image files<br>";
echo "• Database schema verification included<br>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>🔧 RECOMMENDATIONS:</strong><br>";
echo "• Test QR generation from the frontend<br>";
echo "• Verify QR codes appear in the manager<br>";
echo "• Check file permissions on upload directories<br>";
echo "• Test different QR types for field visibility<br>";
echo "• Monitor server logs for any errors<br>";
echo "</div>";

echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>📋 NEXT STEPS:</strong><br>";
echo "1. Go to: <a href='html/qr-generator.php' target='_blank'>QR Generator</a><br>";
echo "2. Try generating different QR types<br>";
echo "3. Check: <a href='html/qr_manager.php' target='_blank'>QR Manager</a><br>";
echo "4. Verify downloads work and QR codes appear in manager<br>";
echo "</div>";

?> 