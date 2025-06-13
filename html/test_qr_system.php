<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if running from CLI
$is_cli = (php_sapi_name() === 'cli');

if ($is_cli) {
    // CLI mode - simulate session
    $_SESSION = [];
    $_SESSION['user_id'] = 1; // Use a test user ID
    $_SESSION['user_data'] = ['username' => 'Test User'];
    $_SESSION['role'] = 'business';
}

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';
require_once __DIR__ . '/includes/QRGenerator.php';

// Require business role
if (!$is_cli) {
    require_role('business');
}

$business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);

echo "<h1>QR System Test</h1>";

// Test 1: Generate a test QR code
echo "<h2>1. Generating Test QR Code</h2>";
try {
    $qr_code = 'test_' . uniqid();
    $content = 'https://test-storage.com';
    
    // Generate QR code
    $generator = new QRGenerator();
    $options = [
        'content' => $content,
        'size' => 300,
        'foreground_color' => '#000000',
        'background_color' => '#FFFFFF',
        'error_correction_level' => 'H'
    ];
    
    $result = $generator->generate($options);
    
    if ($result['success']) {
        echo "<div style='color: green;'>✅ QR code generated successfully</div>";
        
        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO qr_codes (
                business_id, code, qr_type, url, meta, status
            ) VALUES (
                ?, ?, 'static', ?, ?, 'active'
            )
        ");
        
        $meta = json_encode([
            'content' => $content,
            'file_path' => $result['data']['qr_code_url'],
            'size' => 300,
            'foreground_color' => '#000000',
            'background_color' => '#FFFFFF'
        ]);
        
        $stmt->execute([$business_id, $qr_code, $content, $meta]);
        $qr_id = $pdo->lastInsertId();
        
        echo "<div style='color: green;'>✅ QR code saved to database (ID: {$qr_id})</div>";
        
        // Verify file exists
        $file_path = __DIR__ . $result['data']['qr_code_url'];
        if (file_exists($file_path)) {
            echo "<div style='color: green;'>✅ QR code file exists at: {$result['data']['qr_code_url']}</div>";
        } else {
            echo "<div style='color: red;'>❌ QR code file not found at: {$result['data']['qr_code_url']}</div>";
            
            // Try to create directory if it doesn't exist
            $dir = dirname($file_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Save the QR code file
            file_put_contents($file_path, file_get_contents($result['data']['qr_code_url']));
            
            if (file_exists($file_path)) {
                echo "<div style='color: green;'>✅ QR code file created successfully</div>";
            } else {
                echo "<div style='color: red;'>❌ Failed to create QR code file</div>";
            }
        }
    } else {
        echo "<div style='color: red;'>❌ QR code generation failed: {$result['error']}</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: {$e->getMessage()}</div>";
    echo "<pre>{$e->getTraceAsString()}</pre>";
}

// Test 2: Verify QR code in database
echo "<h2>2. Verifying QR Code in Database</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT * FROM qr_codes 
        WHERE business_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$business_id]);
    $qr = $stmt->fetch();
    
    if ($qr) {
        echo "<div style='color: green;'>✅ Found QR code in database:</div>";
        echo "<pre>";
        print_r($qr);
        echo "</pre>";
    } else {
        echo "<div style='color: red;'>❌ No QR codes found in database</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: {$e->getMessage()}</div>";
    echo "<pre>{$e->getTraceAsString()}</pre>";
}

// Test 3: Clean up OLD test QR codes (but keep the new one)
echo "<h2>3. Cleaning Up Old Test QR Codes</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT id, code, meta 
        FROM qr_codes 
        WHERE business_id = ? 
        AND code LIKE 'test_%'
        AND id != ?
    ");
    $stmt->execute([$business_id, $qr_id]);
    $test_qrs = $stmt->fetchAll();
    
    foreach ($test_qrs as $qr) {
        // Get file path from meta
        $meta = json_decode($qr['meta'] ?? '{}', true);
        $file_path = $meta['file_path'] ?? null;
        
        // Check multiple possible file locations
        $possible_paths = [
            $file_path,
            '/uploads/qr/' . $qr['code'] . '.png',
            '/uploads/qr/1/' . $qr['code'] . '.png',
            '/uploads/qr/business/' . $qr['code'] . '.png',
            '/assets/img/qr/' . $qr['code'] . '.png',
            '/qr/' . $qr['code'] . '.png'
        ];
        
        // Try to delete the file from any of the possible locations
        foreach ($possible_paths as $path) {
            if ($path && file_exists(__DIR__ . $path)) {
                unlink(__DIR__ . $path);
                echo "<div style='color: green;'>✅ Deleted file: {$path}</div>";
            }
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM qr_codes WHERE id = ?");
        $stmt->execute([$qr['id']]);
        echo "<div style='color: green;'>✅ Deleted QR code from database (ID: {$qr['id']})</div>";
    }
    
    echo "<div style='color: green;'>✅ Cleanup completed</div>";
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error during cleanup: {$e->getMessage()}</div>";
    echo "<pre>{$e->getTraceAsString()}</pre>";
}

// Test 4: Verify QR Manager Display
echo "<h2>4. Verifying QR Manager Display</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT qr.*, 
               COALESCE(qr.url, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.content'))) as current_url,
               JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')) as file_path
        FROM qr_codes qr
        WHERE qr.business_id = ? AND qr.status = 'active'
        ORDER BY qr.created_at DESC
    ");
    $stmt->execute([$business_id]);
    $qr_codes = $stmt->fetchAll();
    
    if (!empty($qr_codes)) {
        echo "<div style='color: green;'>✅ Found QR codes in QR Manager:</div>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Code</th><th>Type</th><th>URL</th><th>File Path</th><th>Status</th></tr>";
        
        foreach ($qr_codes as $qr) {
            echo "<tr>";
            echo "<td>{$qr['id']}</td>";
            echo "<td>{$qr['code']}</td>";
            echo "<td>{$qr['qr_type']}</td>";
            echo "<td>{$qr['current_url']}</td>";
            echo "<td>{$qr['file_path']}</td>";
            echo "<td>{$qr['status']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<div style='color: yellow;'>⚠️ No QR codes found in QR Manager</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: {$e->getMessage()}</div>";
    echo "<pre>{$e->getTraceAsString()}</pre>";
}

// Add a link to refresh the QR Manager
echo "<div style='margin-top: 20px;'>";
echo "<a href='/qr_manager.php' class='btn btn-primary'>Go to QR Manager</a>";
echo "</div>";
?> 