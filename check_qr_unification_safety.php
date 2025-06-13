<?php
/**
 * QR Code System Unification Safety Assessment
 * Analyzes the safety of proceeding with Priority 2
 */

require_once __DIR__ . '/html/core/config.php';

echo "🔍 QR CODE SYSTEM UNIFICATION SAFETY ASSESSMENT\n";
echo "=================================================\n\n";

function checkQRTypeMismatches($pdo) {
    echo "📊 Checking QR Type Mismatches:\n";
    
    // Check current QR types in database
    try {
        $qr_types = $pdo->query("SELECT DISTINCT qr_type FROM qr_codes ORDER BY qr_type")->fetchAll(PDO::FETCH_COLUMN);
        echo "  • Database QR types: " . implode(', ', $qr_types) . "\n";
    } catch (Exception $e) {
        echo "  ❌ Error checking DB types: " . $e->getMessage() . "\n";
    }
    
    // Check QRGenerator allowed types
    include_once __DIR__ . '/html/includes/QRGenerator.php';
    $generator = new QRGenerator();
    $reflection = new ReflectionClass($generator);
    $properties = $reflection->getProperties();
    
    foreach ($properties as $prop) {
        if ($prop->getName() === 'allowedTypes') {
            $prop->setAccessible(true);
            $allowed = $prop->getValue($generator);
            echo "  • QRGenerator allowed: " . implode(', ', $allowed) . "\n";
            break;
        }
    }
    
    // Check config file types
    if (file_exists(__DIR__ . '/html/config/qr.php')) {
        $config = include __DIR__ . '/html/config/qr.php';
        if (isset($config['allowed_types'])) {
            echo "  • Config allowed: " . implode(', ', $config['allowed_types']) . "\n";
        }
    }
    
    echo "\n";
}

function checkQRGenerationSystems($pdo) {
    echo "🔧 Checking QR Generation Systems:\n";
    
    $generators = [
        'html/includes/QRGenerator.php' => 'Main QRGenerator',
        'html/api/qr/generate.php' => 'API Generator',
        'html/api/qr/enhanced-generate.php' => 'Enhanced API Generator',
        'vendor/bacon/bacon-qr-code/' => 'Bacon QR Library',
        'vendor/endroid/qr-code/' => 'Endroid QR Library', 
        'vendor/phpqrcode/' => 'PHP QR Code Library'
    ];
    
    foreach ($generators as $path => $name) {
        if (file_exists(__DIR__ . '/' . $path)) {
            echo "  ✅ $name: Found\n";
        } else {
            echo "  ❌ $name: Missing\n";
        }
    }
    
    echo "\n";
}

function checkFilePathManagement($pdo) {
    echo "📁 Checking File Path Management:\n";
    
    $upload_dirs = [
        'uploads/qr/',
        'html/uploads/qr/',
        'assets/img/qr/',
        'html/assets/img/qr/'
    ];
    
    foreach ($upload_dirs as $dir) {
        $full_path = __DIR__ . '/' . $dir;
        if (is_dir($full_path)) {
            $files = glob($full_path . '*');
            $count = count($files);
            $writable = is_writable($full_path) ? 'writable' : 'NOT writable';
            echo "  ✅ $dir: $count files, $writable\n";
        } else {
            echo "  ❌ $dir: Not found\n";
        }
    }
    
    echo "\n";
}

function checkDatabaseConsistency($pdo) {
    echo "🗄️  Checking Database Consistency:\n";
    
    try {
        // Check for orphaned QR codes
        $orphaned = $pdo->query("
            SELECT COUNT(*) FROM qr_codes qr 
            LEFT JOIN machines_unified m ON qr.machine_id = m.id 
            WHERE qr.machine_id IS NOT NULL AND m.id IS NULL
        ")->fetchColumn();
        
        if ($orphaned > 0) {
            echo "  ⚠️  Orphaned QR codes (no machine): $orphaned\n";
        } else {
            echo "  ✅ No orphaned QR codes\n";
        }
        
        // Check for QR codes without business_id
        $no_business = $pdo->query("SELECT COUNT(*) FROM qr_codes WHERE business_id IS NULL")->fetchColumn();
        if ($no_business > 0) {
            echo "  ⚠️  QR codes without business_id: $no_business\n";
        } else {
            echo "  ✅ All QR codes have business_id\n";
        }
        
        // Check for invalid URLs
        $invalid_urls = $pdo->query("
            SELECT COUNT(*) FROM qr_codes 
            WHERE url IS NOT NULL 
            AND url != '' 
            AND url NOT LIKE 'http%'
        ")->fetchColumn();
        
        if ($invalid_urls > 0) {
            echo "  ⚠️  QR codes with invalid URLs: $invalid_urls\n";
        } else {
            echo "  ✅ All URLs are valid\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ Database check error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

function assessUnificationRisks($pdo) {
    echo "⚠️  UNIFICATION RISKS ASSESSMENT:\n";
    
    $risks = [];
    
    // Check for multiple QR generation libraries
    $libraries = 0;
    if (is_dir(__DIR__ . '/vendor/bacon/bacon-qr-code/')) $libraries++;
    if (is_dir(__DIR__ . '/vendor/endroid/qr-code/')) $libraries++;
    if (is_dir(__DIR__ . '/vendor/phpqrcode/')) $libraries++;
    
    if ($libraries > 1) {
        $risks[] = "Multiple QR libraries detected ($libraries) - could cause conflicts";
    }
    
    // Check for inconsistent QR types
    try {
        $db_types = $pdo->query("SELECT DISTINCT qr_type FROM qr_codes")->fetchAll(PDO::FETCH_COLUMN);
        $code_types = ['static', 'dynamic', 'dynamic_voting', 'dynamic_vending', 'machine_sales', 'promotion', 'spin_wheel', 'pizza_tracker'];
        
        $db_only = array_diff($db_types, $code_types);
        $code_only = array_diff($code_types, $db_types);
        
        if (!empty($db_only)) {
            $risks[] = "QR types in DB but not in code: " . implode(', ', $db_only);
        }
        if (!empty($code_only)) {
            $risks[] = "QR types in code but not in DB: " . implode(', ', $code_only);
        }
    } catch (Exception $e) {
        $risks[] = "Could not verify QR type consistency: " . $e->getMessage();
    }
    
    // Check for file path inconsistencies
    $qr_dirs = glob(__DIR__ . '/*/qr', GLOB_ONLYDIR);
    if (count($qr_dirs) > 2) {
        $risks[] = "Multiple QR directories found - path confusion risk";
    }
    
    if (empty($risks)) {
        echo "  ✅ LOW RISK - Safe to proceed with unification\n";
        return 'LOW';
    } else {
        echo "  ⚠️  MEDIUM RISK - Proceed with caution:\n";
        foreach ($risks as $risk) {
            echo "    • $risk\n";
        }
        return 'MEDIUM';
    }
}

function generateUnificationPlan($risk_level) {
    echo "\n📋 UNIFICATION PLAN:\n";
    
    if ($risk_level === 'LOW') {
        echo "  1. ✅ Consolidate QR type definitions\n";
        echo "  2. ✅ Standardize QR generation APIs\n";
        echo "  3. ✅ Unify file path management\n";
        echo "  4. ✅ Implement validation improvements\n";
        echo "  5. ✅ Clean up redundant libraries\n";
        echo "\n  💡 RECOMMENDATION: SAFE TO PROCEED IMMEDIATELY\n";
    } else {
        echo "  1. 🔧 Create compatibility layer\n";
        echo "  2. 🔧 Backup existing QR generation system\n";
        echo "  3. 🔧 Implement gradual migration\n";
        echo "  4. 🔧 Test extensively before full deployment\n";
        echo "  5. 🔧 Monitor for issues during transition\n";
        echo "\n  💡 RECOMMENDATION: PROCEED WITH STAGED APPROACH\n";
    }
}

try {
    checkQRTypeMismatches($pdo);
    checkQRGenerationSystems($pdo);
    checkFilePathManagement($pdo);
    checkDatabaseConsistency($pdo);
    $risk_level = assessUnificationRisks($pdo);
    generateUnificationPlan($risk_level);
    
    echo "\n🎯 CONCLUSION: QR system unification can proceed ";
    echo $risk_level === 'LOW' ? "immediately with minimal risk" : "with staged approach and monitoring";
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ Assessment failed: " . $e->getMessage() . "\n";
} 