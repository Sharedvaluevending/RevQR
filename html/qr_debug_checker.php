<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

// Require business role
if (!is_logged_in() || !has_role('business')) {
    die("Access denied - login required");
}

$business_id = $_SESSION['business_id'] ?? 1;
if (!$business_id) {
    $stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $business = $stmt->fetch();
    $business_id = $business ? $business['id'] : 1;
}

echo "<h1>QR Code Debug Checker</h1>";
echo "<style>body{font-family:Arial;margin:20px;background:#1a1a1a;color:#fff;} .success{color:#28a745;} .error{color:#dc3545;} .warning{color:#ffc107;} .info{color:#17a2b8;} pre{background:#2a2a2a;padding:10px;border-radius:5px;}</style>";

// 1. Check database QR codes
echo "<h2>1. Database QR Codes Check</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT id, code, qr_type, url, created_at, status,
               COALESCE(url, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.content'))) as content,
               meta
        FROM qr_codes 
        WHERE business_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$business_id]);
    $qr_codes = $stmt->fetchAll();
    
    if ($qr_codes) {
        echo "<p class='success'>✓ Found " . count($qr_codes) . " QR codes in database</p>";
        echo "<table border='1' style='border-collapse:collapse;width:100%;'>";
        echo "<tr><th>ID</th><th>Code</th><th>Type</th><th>Status</th><th>Content</th><th>Created</th></tr>";
        foreach ($qr_codes as $qr) {
            echo "<tr>";
            echo "<td>{$qr['id']}</td>";
            echo "<td>{$qr['code']}</td>";
            echo "<td>{$qr['qr_type']}</td>";
            echo "<td>{$qr['status']}</td>";
            echo "<td>" . substr($qr['content'] ?: $qr['url'], 0, 50) . "...</td>";
            echo "<td>{$qr['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ No QR codes found in database for business_id: $business_id</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Database error: " . $e->getMessage() . "</p>";
}

// 2. Check file system directories
echo "<h2>2. File System Check</h2>";
$qr_directories = [
    __DIR__ . '/uploads/qr/',
    __DIR__ . '/uploads/qr/1/',
    __DIR__ . '/uploads/qr/business/',
    __DIR__ . '/assets/img/qr/'
];

foreach ($qr_directories as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*.png');
        echo "<p class='success'>✓ Directory exists: $dir (" . count($files) . " PNG files)</p>";
        if (count($files) > 0) {
            echo "<ul>";
            foreach (array_slice($files, 0, 5) as $file) {
                $filename = basename($file);
                $size = filesize($file);
                echo "<li>$filename (" . round($size/1024, 1) . " KB)</li>";
            }
            if (count($files) > 5) echo "<li>... and " . (count($files) - 5) . " more files</li>";
            echo "</ul>";
        }
    } else {
        echo "<p class='error'>✗ Directory missing: $dir</p>";
        // Try to create directory
        if (mkdir($dir, 0755, true)) {
            echo "<p class='info'>  → Created directory successfully</p>";
        } else {
            echo "<p class='error'>  → Failed to create directory</p>";
        }
    }
}

// 3. Check specific QR code files
echo "<h2>3. QR Code File Verification</h2>";
if (isset($qr_codes) && $qr_codes) {
    foreach (array_slice($qr_codes, 0, 5) as $qr) {
        echo "<h3>QR Code: {$qr['code']}</h3>";
        
        $possible_paths = [
            __DIR__ . '/uploads/qr/' . $qr['code'] . '.png',
            __DIR__ . '/uploads/qr/1/' . $qr['code'] . '.png',
            __DIR__ . '/uploads/qr/business/' . $qr['code'] . '.png'
        ];
        
        $found = false;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $size = filesize($path);
                $web_path = str_replace(__DIR__, '', $path);
                echo "<p class='success'>  ✓ Found: $web_path (" . round($size/1024, 1) . " KB)</p>";
                echo "<p>    <img src='$web_path' alt='QR Code' style='width:100px;height:100px;border:1px solid #ccc;'></p>";
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo "<p class='error'>  ✗ File not found in any expected location</p>";
            echo "<p class='info'>    Searched:</p><ul>";
            foreach ($possible_paths as $path) {
                echo "<li>" . str_replace(__DIR__, '', $path) . "</li>";
            }
            echo "</ul>";
        }
    }
}

// 4. Check QR Generator class
echo "<h2>4. QR Generator System Check</h2>";
$generator_paths = [
    __DIR__ . '/includes/QRGenerator.php',
    __DIR__ . '/core/services/QRService.php'
];

foreach ($generator_paths as $path) {
    if (file_exists($path)) {
        echo "<p class='success'>✓ Found: " . str_replace(__DIR__, '', $path) . "</p>";
    } else {
        echo "<p class='error'>✗ Missing: " . str_replace(__DIR__, '', $path) . "</p>";
    }
}

// 5. Test QR code generation capability
echo "<h2>5. QR Generation Test</h2>";
if (file_exists(__DIR__ . '/includes/QRGenerator.php')) {
    try {
        require_once __DIR__ . '/includes/QRGenerator.php';
        $generator = new QRGenerator();
        $result = $generator->generate([
            'type' => 'static',
            'content' => 'https://example.com/test',
            'size' => 200,
            'preview' => true
        ]);
        
        if ($result['success']) {
            echo "<p class='success'>✓ QR generation test successful</p>";
            if (isset($result['data']['base64'])) {
                echo "<p>Test QR Code:</p>";
                echo "<img src='data:image/png;base64,{$result['data']['base64']}' alt='Test QR' style='width:150px;height:150px;border:1px solid #ccc;'>";
            }
        } else {
            echo "<p class='error'>✗ QR generation test failed: " . ($result['message'] ?? 'Unknown error') . "</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ QR generation test error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='warning'>⚠️ Cannot test - QRGenerator.php not found</p>";
}

// 6. Check permissions
echo "<h2>6. Permissions Check</h2>";
$check_paths = [
    __DIR__ . '/uploads/',
    __DIR__ . '/uploads/qr/'
];

foreach ($check_paths as $path) {
    if (is_dir($path)) {
        $perms = fileperms($path);
        $perms_str = substr(sprintf('%o', $perms), -4);
        $writable = is_writable($path);
        
        echo "<p class='" . ($writable ? 'success' : 'error') . "'>";
        echo ($writable ? '✓' : '✗') . " $path - Permissions: $perms_str " . ($writable ? '(writable)' : '(not writable)');
        echo "</p>";
    }
}

echo "<h2>7. Recommendations</h2>";
echo "<div style='background:#2a2a2a;padding:15px;border-radius:5px;'>";
echo "<h3>Issues Found & Solutions:</h3>";
echo "<ul>";

if (!isset($qr_codes) || empty($qr_codes)) {
    echo "<li class='warning'>No QR codes in database - Generate some QR codes first</li>";
}

$uploads_dir = __DIR__ . '/uploads/qr/';
if (!is_dir($uploads_dir)) {
    echo "<li class='error'>Missing uploads/qr directory - Create it with proper permissions</li>";
}

if (!file_exists(__DIR__ . '/includes/QRGenerator.php')) {
    echo "<li class='error'>Missing QRGenerator.php - This is required for QR generation</li>";
}

echo "<li class='info'>Check server error logs for additional clues</li>";
echo "<li class='info'>Ensure web server has write permissions to uploads directory</li>";
echo "<li class='info'>Verify database connections and business_id associations</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><a href='qr_manager.php'>← Back to QR Manager</a> | <a href='?refresh=1'>Refresh Diagnostics</a></p>";
?> 