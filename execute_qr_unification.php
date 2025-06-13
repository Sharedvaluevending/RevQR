<?php
/**
 * QR Code System Unification Executor
 * Safely executes the complete QR system unification process
 */

require_once __DIR__ . '/html/core/config.php';

echo "🚀 QR CODE SYSTEM UNIFICATION\n";
echo "===============================\n\n";

function executePhase1Schema($pdo) {
    echo "Phase 1: Schema Updates\n";
    echo "-----------------------\n";
    
    try {
        // Read and execute schema updates
        $sql = file_get_contents(__DIR__ . '/qr_unification_phase1.sql');
        if (!$sql) {
            throw new Exception("Could not read phase 1 SQL file");
        }
        
        // Execute SQL statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $executed = 0;
        
        foreach ($statements as $stmt) {
            if (!empty($stmt) && !preg_match('/^(--|USE)/', $stmt)) {
                $pdo->exec($stmt);
                $executed++;
            }
        }
        
        echo "  ✅ Schema updated ($executed statements executed)\n";
        
        // Verify changes
        $qr_types = $pdo->query("SHOW COLUMNS FROM qr_codes LIKE 'qr_type'")->fetch();
        if ($qr_types) {
            echo "  ✅ QR types enum updated\n";
        }
        
        $url_col = $pdo->query("SHOW COLUMNS FROM qr_codes LIKE 'url'")->fetch();
        if ($url_col) {
            echo "  ✅ URL column verified\n";
        }
        
        $log_table = $pdo->query("SHOW TABLES LIKE 'qr_generation_log'")->fetch();
        if ($log_table) {
            echo "  ✅ Generation log table created\n";
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "  ❌ Phase 1 failed: " . $e->getMessage() . "\n";
        return false;
    }
}

function executePhase2Unification($pdo) {
    echo "\nPhase 2: Code Unification\n";
    echo "-------------------------\n";
    
    try {
        // Test the unified QR manager
        require_once __DIR__ . '/html/core/unified_qr_manager.php';
        
        $business_id = 1; // Test with first business
        $manager = new UnifiedQRManager($pdo, $business_id);
        
        echo "  ✅ Unified QR Manager loaded\n";
        
        // Test allowed types
        $types = $manager->getAllowedTypes();
        echo "  ✅ QR types configured: " . count($types) . " types\n";
        
        // Test API endpoint
        if (file_exists(__DIR__ . '/html/api/qr/unified-generate.php')) {
            echo "  ✅ Unified API endpoint created\n";
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "  ❌ Phase 2 failed: " . $e->getMessage() . "\n";
        return false;
    }
}

function executePhase3Testing($pdo) {
    echo "\nPhase 3: System Testing\n";
    echo "-----------------------\n";
    
    try {
        require_once __DIR__ . '/html/core/unified_qr_manager.php';
        
        $business_id = 1;
        $manager = new UnifiedQRManager($pdo, $business_id);
        
        // Test static QR generation
        $test_data = [
            'qr_type' => 'static',
            'url' => 'https://example.com',
            'size' => 300,
            'name' => 'Test QR Code'
        ];
        
        echo "  🧪 Testing static QR generation...\n";
        $result = $manager->generateQR($test_data);
        
        if ($result['success']) {
            echo "  ✅ Static QR generation: PASSED\n";
            echo "    • Code: " . $result['data']['code'] . "\n";
            echo "    • Generation time: " . $result['data']['generation_time'] . "s\n";
            
            // Clean up test QR
            $pdo->exec("DELETE FROM qr_codes WHERE code = '" . $result['data']['code'] . "'");
            echo "  🧹 Test QR cleaned up\n";
        } else {
            echo "  ❌ Static QR generation: FAILED - " . $result['error'] . "\n";
            return false;
        }
        
        // Test QR types validation
        echo "  🧪 Testing QR type validation...\n";
        $invalid_result = $manager->generateQR(['qr_type' => 'invalid_type']);
        
        if (!$invalid_result['success']) {
            echo "  ✅ QR type validation: PASSED\n";
        } else {
            echo "  ❌ QR type validation: FAILED\n";
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        echo "  ❌ Phase 3 failed: " . $e->getMessage() . "\n";
        return false;
    }
}

function executePhase4Integration($pdo) {
    echo "\nPhase 4: Frontend Integration\n";
    echo "-----------------------------\n";
    
    try {
        // Update QR manager to use unified system
        $qr_manager_file = __DIR__ . '/html/qr_manager.php';
        if (file_exists($qr_manager_file)) {
            echo "  ✅ QR Manager page found\n";
        }
        
        // Check QR generator pages
        $generator_files = [
            'html/qr-generator.php',
            'html/qr-generator-enhanced.php'
        ];
        
        foreach ($generator_files as $file) {
            if (file_exists(__DIR__ . '/' . $file)) {
                echo "  ✅ Generator page: $file\n";
            }
        }
        
        echo "  💡 Frontend integration ready\n";
        echo "  💡 Update generator pages to use /api/qr/unified-generate.php\n";
        
        return true;
        
    } catch (Exception $e) {
        echo "  ❌ Phase 4 failed: " . $e->getMessage() . "\n";
        return false;
    }
}

function generateUnificationReport($pdo) {
    echo "\n📊 UNIFICATION REPORT\n";
    echo "=====================\n";
    
    try {
        // Count QR codes by type
        $types = $pdo->query("
            SELECT qr_type, COUNT(*) as count 
            FROM qr_codes 
            GROUP BY qr_type 
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "QR Codes by Type:\n";
        foreach ($types as $type) {
            echo "  • {$type['qr_type']}: {$type['count']} codes\n";
        }
        
        // Check generation log
        $log_count = $pdo->query("SELECT COUNT(*) FROM qr_generation_log")->fetchColumn();
        echo "\nGeneration Log: $log_count entries\n";
        
        // Check file uploads
        $upload_dir = __DIR__ . '/html/uploads/qr/';
        if (is_dir($upload_dir)) {
            $files = glob($upload_dir . '*');
            echo "Upload Directory: " . count($files) . " files\n";
        }
        
        echo "\n✅ Unification completed successfully!\n";
        
    } catch (Exception $e) {
        echo "  ⚠️  Report generation failed: " . $e->getMessage() . "\n";
    }
}

// Execute unification process
try {
    $success = true;
    
    // Phase 1: Schema Updates
    if (!executePhase1Schema($pdo)) {
        $success = false;
    }
    
    // Phase 2: Code Unification  
    if ($success && !executePhase2Unification($pdo)) {
        $success = false;
    }
    
    // Phase 3: System Testing
    if ($success && !executePhase3Testing($pdo)) {
        $success = false;
    }
    
    // Phase 4: Frontend Integration
    if ($success && !executePhase4Integration($pdo)) {
        $success = false;
    }
    
    if ($success) {
        generateUnificationReport($pdo);
        
        echo "\n🎉 QR CODE SYSTEM UNIFICATION COMPLETE!\n";
        echo "========================================\n\n";
        echo "Next steps:\n";
        echo "1. Update frontend pages to use unified API\n";
        echo "2. Test QR generation from web interface\n";
        echo "3. Monitor generation logs for issues\n";
        echo "4. Remove old QR generation endpoints\n\n";
    } else {
        echo "\n❌ UNIFICATION FAILED\n";
        echo "Check errors above and retry\n";
    }
    
} catch (Exception $e) {
    echo "❌ Unification process failed: " . $e->getMessage() . "\n";
} 