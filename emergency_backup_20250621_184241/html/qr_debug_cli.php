<?php
// CLI version of QR Debug Checker
require_once __DIR__ . '/core/config.php';

echo "=== QR CODE DEBUG CHECKER (CLI) ===\n";
echo "Running diagnostics...\n\n";

// 1. Check database connection
echo "1. DATABASE CONNECTION CHECK\n";
echo "================================\n";
try {
    $test_query = $pdo->query("SELECT COUNT(*) FROM qr_codes");
    $total_qr = $test_query->fetchColumn();
    echo "✓ Database connected successfully\n";
    echo "✓ Total QR codes in system: $total_qr\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}

// 2. Check QR codes by business
echo "\n2. QR CODES BY BUSINESS\n";
echo "========================\n";
try {
    $stmt = $pdo->query("
        SELECT business_id, COUNT(*) as count, 
               GROUP_CONCAT(DISTINCT qr_type) as types,
               GROUP_CONCAT(DISTINCT status) as statuses
        FROM qr_codes 
        GROUP BY business_id 
        ORDER BY business_id
    ");
    $businesses = $stmt->fetchAll();
    
    if ($businesses) {
        foreach ($businesses as $biz) {
            echo "Business ID {$biz['business_id']}: {$biz['count']} QR codes\n";
            echo "  Types: {$biz['types']}\n";
            echo "  Statuses: {$biz['statuses']}\n";
        }
    } else {
        echo "⚠️ No QR codes found in database\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking QR codes: " . $e->getMessage() . "\n";
}

// 3. Check recent QR codes
echo "\n3. RECENT QR CODES (Last 10)\n";
echo "==============================\n";
try {
    $stmt = $pdo->query("
        SELECT id, code, qr_type, business_id, status, created_at,
               COALESCE(url, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.content'))) as content
        FROM qr_codes 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recent_qrs = $stmt->fetchAll();
    
    if ($recent_qrs) {
        foreach ($recent_qrs as $qr) {
            echo "ID: {$qr['id']} | Code: {$qr['code']} | Type: {$qr['qr_type']} | Business: {$qr['business_id']} | Status: {$qr['status']}\n";
            echo "  Created: {$qr['created_at']}\n";
            echo "  Content: " . substr($qr['content'] ?: 'N/A', 0, 60) . "...\n";
            echo "  ---\n";
        }
    } else {
        echo "⚠️ No QR codes found\n";
    }
} catch (Exception $e) {
    echo "✗ Error fetching recent QR codes: " . $e->getMessage() . "\n";
}

// 4. Check file system
echo "\n4. FILE SYSTEM CHECK\n";
echo "=====================\n";

$qr_directories = [
    __DIR__ . '/uploads/qr/',
    __DIR__ . '/uploads/qr/1/',
    __DIR__ . '/uploads/qr/business/',
    __DIR__ . '/assets/img/qr/'
];

foreach ($qr_directories as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*.png');
        echo "✓ Directory exists: $dir (" . count($files) . " PNG files)\n";
        
        if (count($files) > 0) {
            echo "  Sample files:\n";
            foreach (array_slice($files, 0, 3) as $file) {
                $filename = basename($file);
                $size = filesize($file);
                echo "    - $filename (" . round($size/1024, 1) . " KB)\n";
            }
            if (count($files) > 3) {
                echo "    - ... and " . (count($files) - 3) . " more files\n";
            }
        }
    } else {
        echo "✗ Directory missing: $dir\n";
        // Try to create directory
        if (mkdir($dir, 0755, true)) {
            echo "  → Created directory successfully\n";
        } else {
            echo "  → Failed to create directory\n";
        }
    }
}

// 5. Check specific QR code files
echo "\n5. QR CODE FILE VERIFICATION\n";
echo "=============================\n";

if (isset($recent_qrs) && $recent_qrs) {
    foreach (array_slice($recent_qrs, 0, 5) as $qr) {
        echo "Checking QR Code: {$qr['code']}\n";
        
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
                echo "  ✓ Found: $web_path (" . round($size/1024, 1) . " KB)\n";
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo "  ✗ File not found in any expected location\n";
            echo "    Searched paths:\n";
            foreach ($possible_paths as $path) {
                echo "      - " . str_replace(__DIR__, '', $path) . "\n";
            }
        }
        echo "  ---\n";
    }
}

// 6. Check QR Generator
echo "\n6. QR GENERATOR CHECK\n";
echo "=====================\n";

$generator_paths = [
    __DIR__ . '/includes/QRGenerator.php',
    __DIR__ . '/core/services/QRService.php'
];

foreach ($generator_paths as $path) {
    if (file_exists($path)) {
        echo "✓ Found: " . str_replace(__DIR__, '', $path) . "\n";
    } else {
        echo "✗ Missing: " . str_replace(__DIR__, '', $path) . "\n";
    }
}

// 7. Check permissions
echo "\n7. PERMISSIONS CHECK\n";
echo "====================\n";

$check_paths = [
    __DIR__ . '/uploads/',
    __DIR__ . '/uploads/qr/'
];

foreach ($check_paths as $path) {
    if (is_dir($path)) {
        $perms = fileperms($path);
        $perms_str = substr(sprintf('%o', $perms), -4);
        $writable = is_writable($path);
        
        echo ($writable ? '✓' : '✗') . " $path - Permissions: $perms_str " . ($writable ? '(writable)' : '(not writable)') . "\n";
    } else {
        echo "✗ Directory not found: $path\n";
    }
}

// 8. Summary and recommendations
echo "\n8. SUMMARY & RECOMMENDATIONS\n";
echo "=============================\n";

$issues = [];

if (!isset($recent_qrs) || empty($recent_qrs)) {
    $issues[] = "No QR codes found in database";
}

$uploads_dir = __DIR__ . '/uploads/qr/';
if (!is_dir($uploads_dir)) {
    $issues[] = "Missing uploads/qr directory";
}

if (!file_exists(__DIR__ . '/includes/QRGenerator.php')) {
    $issues[] = "Missing QRGenerator.php";
}

if (!empty($issues)) {
    echo "ISSUES FOUND:\n";
    foreach ($issues as $issue) {
        echo "  - $issue\n";
    }
} else {
    echo "✓ No major issues detected\n";
}

echo "\nRECOMMENDATIONS:\n";
echo "1. Ensure QR codes are being generated and saved to database\n";
echo "2. Check file permissions on uploads directory (should be 755 or 777)\n";
echo "3. Verify QRGenerator.php exists and is working\n";
echo "4. Check server error logs for detailed error messages\n";
echo "5. Test QR generation process manually\n";

echo "\n=== DEBUG COMPLETE ===\n";
?> 