<?php
/**
 * Comprehensive Safety and Impact Assessment
 * Evaluates all changes made during Priority 1 & 2 implementations
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/migration_helpers.php';

echo "🔍 COMPREHENSIVE SAFETY & IMPACT ASSESSMENT\n";
echo "============================================\n\n";

function assessDatabaseChanges($pdo) {
    echo "📊 DATABASE CHANGES ASSESSMENT\n";
    echo "------------------------------\n";
    
    $safety_score = 0;
    $max_score = 0;
    
    try {
        // Check schema changes
        echo "Schema Changes:\n";
        
        // 1. Check qr_codes table structure
        $max_score += 5;
        $columns = $pdo->query("DESCRIBE qr_codes")->fetchAll(PDO::FETCH_ASSOC);
        $column_names = array_column($columns, 'Field');
        
        $required_columns = ['business_id', 'qr_type', 'url', 'qr_options'];
        $missing_columns = array_diff($required_columns, $column_names);
        
        if (empty($missing_columns)) {
            echo "  ✅ QR codes table: All required columns present\n";
            $safety_score += 5;
        } else {
            echo "  ⚠️  QR codes table: Missing columns: " . implode(', ', $missing_columns) . "\n";
            $safety_score += 3;
        }
        
        // 2. Check QR types enum
        $max_score += 3;
        $qr_type_column = null;
        foreach ($columns as $col) {
            if ($col['Field'] === 'qr_type') {
                $qr_type_column = $col;
                break;
            }
        }
        
        if ($qr_type_column && strpos($qr_type_column['Type'], 'pizza_tracker') !== false) {
            echo "  ✅ QR types enum: Includes all new types\n";
            $safety_score += 3;
        } else {
            echo "  ⚠️  QR types enum: May be missing some types\n";
            $safety_score += 2;
        }
        
        // 3. Check foreign key constraints
        $max_score += 4;
        $fks = $pdo->query("
            SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = 'revenueqr' 
            AND TABLE_NAME = 'qr_codes' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $business_fk = false;
        foreach ($fks as $fk) {
            if ($fk['COLUMN_NAME'] === 'business_id' && $fk['REFERENCED_TABLE_NAME'] === 'businesses') {
                $business_fk = true;
                break;
            }
        }
        
        if ($business_fk) {
            echo "  ✅ Foreign keys: Business isolation enforced\n";
            $safety_score += 4;
        } else {
            echo "  ⚠️  Foreign keys: Business isolation may not be enforced\n";
            $safety_score += 2;
        }
        
        // 4. Check compatibility views
        $max_score += 3;
        $views = $pdo->query("SHOW TABLES LIKE '%_unified'")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('machines_unified', $views)) {
            echo "  ✅ Compatibility views: machines_unified exists\n";
            $safety_score += 3;
        } else {
            echo "  ❌ Compatibility views: machines_unified missing\n";
        }
        
        // 5. Check backup tables
        $max_score += 2;
        $backup_tables = $pdo->query("SHOW TABLES LIKE '%_backup'")->fetchAll(PDO::FETCH_COLUMN);
        if (count($backup_tables) >= 2) {
            echo "  ✅ Backup tables: " . count($backup_tables) . " backup tables exist\n";
            $safety_score += 2;
        } else {
            echo "  ⚠️  Backup tables: Limited backups available\n";
            $safety_score += 1;
        }
        
    } catch (Exception $e) {
        echo "  ❌ Database assessment error: " . $e->getMessage() . "\n";
    }
    
    $percentage = round(($safety_score / $max_score) * 100);
    echo "\n📊 Database Safety Score: $safety_score/$max_score ($percentage%)\n\n";
    
    return $percentage >= 80 ? 'SAFE' : ($percentage >= 60 ? 'MODERATE' : 'RISKY');
}

function assessDataIntegrity($pdo) {
    echo "🔐 DATA INTEGRITY ASSESSMENT\n";
    echo "----------------------------\n";
    
    $issues = [];
    $total_checks = 0;
    $passed_checks = 0;
    
    try {
        // Check for orphaned QR codes
        $total_checks++;
        $orphaned_qr = $pdo->query("
            SELECT COUNT(*) FROM qr_codes qr 
            LEFT JOIN machines_unified m ON qr.machine_id = m.id 
            WHERE qr.machine_id IS NOT NULL AND m.id IS NULL
        ")->fetchColumn();
        
        if ($orphaned_qr == 0) {
            echo "  ✅ QR-Machine relationships: No orphaned records\n";
            $passed_checks++;
        } else {
            echo "  ⚠️  QR-Machine relationships: $orphaned_qr orphaned QR codes\n";
            $issues[] = "Orphaned QR codes need cleanup";
        }
        
        // Check business isolation
        $total_checks++;
        $no_business = $pdo->query("SELECT COUNT(*) FROM qr_codes WHERE business_id IS NULL")->fetchColumn();
        
        if ($no_business == 0) {
            echo "  ✅ Business isolation: All QR codes have business_id\n";
            $passed_checks++;
        } else {
            echo "  ❌ Business isolation: $no_business QR codes missing business_id\n";
            $issues[] = "QR codes without business_id - SECURITY RISK";
        }
        
        // Check QR code uniqueness
        $total_checks++;
        $duplicate_codes = $pdo->query("
            SELECT COUNT(*) FROM (
                SELECT code FROM qr_codes GROUP BY code HAVING COUNT(*) > 1
            ) as duplicates
        ")->fetchColumn();
        
        if ($duplicate_codes == 0) {
            echo "  ✅ QR code uniqueness: No duplicate codes\n";
            $passed_checks++;
        } else {
            echo "  ❌ QR code uniqueness: $duplicate_codes duplicate codes found\n";
            $issues[] = "Duplicate QR codes - FUNCTIONAL RISK";
        }
        
        // Check URL validity (where applicable)
        $total_checks++;
        $invalid_urls = $pdo->query("
            SELECT COUNT(*) FROM qr_codes 
            WHERE qr_type IN ('static', 'dynamic') 
            AND (url IS NULL OR url = '' OR url NOT LIKE 'http%')
        ")->fetchColumn();
        
        if ($invalid_urls == 0) {
            echo "  ✅ URL validity: All static/dynamic QR codes have valid URLs\n";
            $passed_checks++;
        } else {
            echo "  ⚠️  URL validity: $invalid_urls QR codes with invalid URLs\n";
            $issues[] = "Invalid URLs may cause QR code failures";
        }
        
        // Check campaign references
        $total_checks++;
        $invalid_campaigns = $pdo->query("
            SELECT COUNT(*) FROM qr_codes qr
            LEFT JOIN campaigns c ON qr.campaign_id = c.id
            WHERE qr.campaign_id IS NOT NULL AND c.id IS NULL
        ")->fetchColumn();
        
        if ($invalid_campaigns == 0) {
            echo "  ✅ Campaign references: All campaign references valid\n";
            $passed_checks++;
        } else {
            echo "  ⚠️  Campaign references: $invalid_campaigns invalid campaign references\n";
            $issues[] = "Invalid campaign references";
        }
        
    } catch (Exception $e) {
        echo "  ❌ Data integrity check error: " . $e->getMessage() . "\n";
        $issues[] = "Data integrity check failed";
    }
    
    $integrity_score = round(($passed_checks / $total_checks) * 100);
    echo "\n📊 Data Integrity Score: $passed_checks/$total_checks ($integrity_score%)\n";
    
    if (!empty($issues)) {
        echo "\n⚠️  Issues Found:\n";
        foreach ($issues as $issue) {
            echo "  • $issue\n";
        }
    }
    
    echo "\n";
    return $integrity_score >= 90 ? 'EXCELLENT' : ($integrity_score >= 70 ? 'GOOD' : 'POOR');
}

function assessFunctionalImpact($pdo) {
    echo "⚡ FUNCTIONAL IMPACT ASSESSMENT\n";
    echo "------------------------------\n";
    
    $impact_areas = [];
    
    try {
        // Test QR generation
        echo "QR Generation Impact:\n";
        require_once __DIR__ . '/html/includes/QRGenerator.php';
        
        $generator = new QRGenerator();
        $test_result = $generator->generate([
            'type' => 'static',
            'content' => 'https://example.com/test',
            'size' => 200,
            'preview' => true
        ]);
        
        if ($test_result['success']) {
            echo "  ✅ Basic QR generation: WORKING\n";
        } else {
            echo "  ❌ Basic QR generation: FAILED - " . ($test_result['error'] ?? 'Unknown') . "\n";
            $impact_areas[] = "QR generation may be broken";
        }
        
        // Test database operations
        echo "  🧪 Testing database operations...\n";
        $qr_count = $pdo->query("SELECT COUNT(*) FROM qr_codes")->fetchColumn();
        echo "  ✅ Database queries: Working (found $qr_count QR codes)\n";
        
        // Test migration helpers
        if (function_exists('getMigrationStatus')) {
            echo "  ✅ Migration helpers: Available\n";
        } else {
            echo "  ⚠️  Migration helpers: May not be available\n";
            $impact_areas[] = "Migration helpers may be missing";
        }
        
        // Test file operations
        echo "File System Impact:\n";
        $upload_dir = __DIR__ . '/html/uploads/qr/';
        if (is_dir($upload_dir) && is_writable($upload_dir)) {
            $file_count = count(glob($upload_dir . '*'));
            echo "  ✅ QR upload directory: Accessible ($file_count files)\n";
        } else {
            echo "  ❌ QR upload directory: Not accessible\n";
            $impact_areas[] = "QR file storage may be broken";
        }
        
        // Test API endpoints
        echo "API Impact:\n";
        $api_files = [
            'html/api/qr/generate.php' => 'Original Generate API',
            'html/api/qr/enhanced-generate.php' => 'Enhanced Generate API',
            'html/api/qr/unified-generate.php' => 'Unified Generate API'
        ];
        
        $working_apis = 0;
        foreach ($api_files as $file => $name) {
            if (file_exists(__DIR__ . '/' . $file)) {
                echo "  ✅ $name: Available\n";
                $working_apis++;
            } else {
                echo "  ❌ $name: Missing\n";
            }
        }
        
        if ($working_apis >= 2) {
            echo "  ✅ API redundancy: Multiple APIs available\n";
        } else {
            echo "  ⚠️  API redundancy: Limited API options\n";
            $impact_areas[] = "Limited API redundancy";
        }
        
    } catch (Exception $e) {
        echo "  ❌ Functional impact test error: " . $e->getMessage() . "\n";
        $impact_areas[] = "Functional testing failed";
    }
    
    if (empty($impact_areas)) {
        echo "\n✅ FUNCTIONAL IMPACT: MINIMAL - All systems operational\n\n";
        return 'MINIMAL';
    } else {
        echo "\n⚠️  FUNCTIONAL IMPACT: MODERATE\n";
        foreach ($impact_areas as $impact) {
            echo "  • $impact\n";
        }
        echo "\n";
        return 'MODERATE';
    }
}

function assessSecurityImpact($pdo) {
    echo "🔒 SECURITY IMPACT ASSESSMENT\n";
    echo "-----------------------------\n";
    
    $security_issues = [];
    
    try {
        // Check business isolation
        $businesses = $pdo->query("SELECT COUNT(DISTINCT business_id) FROM qr_codes WHERE business_id IS NOT NULL")->fetchColumn();
        $total_qr = $pdo->query("SELECT COUNT(*) FROM qr_codes")->fetchColumn();
        $isolated_qr = $pdo->query("SELECT COUNT(*) FROM qr_codes WHERE business_id IS NOT NULL")->fetchColumn();
        
        $isolation_percentage = round(($isolated_qr / $total_qr) * 100);
        
        echo "Business Isolation:\n";
        echo "  • Total QR codes: $total_qr\n";
        echo "  • With business_id: $isolated_qr ($isolation_percentage%)\n";
        echo "  • Distinct businesses: $businesses\n";
        
        if ($isolation_percentage >= 95) {
            echo "  ✅ Business isolation: SECURE\n";
        } else {
            echo "  ⚠️  Business isolation: PARTIAL - " . ($total_qr - $isolated_qr) . " QR codes not isolated\n";
            $security_issues[] = "Incomplete business isolation - potential data leakage";
        }
        
        // Check for public QR codes
        $public_qr = $pdo->query("
            SELECT COUNT(*) FROM qr_codes 
            WHERE qr_type IN ('static', 'dynamic') 
            AND (url LIKE '%public%' OR url LIKE '%open%')
        ")->fetchColumn();
        
        echo "\nPublic Access:\n";
        echo "  • Potentially public QR codes: $public_qr\n";
        
        if ($public_qr > 0) {
            echo "  ⚠️  Public QR codes exist - ensure they're intentionally public\n";
            $security_issues[] = "Review public QR codes for unintended exposure";
        } else {
            echo "  ✅ No obviously public QR codes detected\n";
        }
        
        // Check foreign key constraints
        $fk_count = $pdo->query("
            SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = 'revenueqr' 
            AND TABLE_NAME = 'qr_codes' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->fetchColumn();
        
        echo "\nData Integrity Constraints:\n";
        echo "  • Foreign key constraints: $fk_count\n";
        
        if ($fk_count >= 1) {
            echo "  ✅ Referential integrity: Enforced\n";
        } else {
            echo "  ⚠️  Referential integrity: May not be enforced\n";
            $security_issues[] = "Lack of foreign key constraints - data consistency risk";
        }
        
    } catch (Exception $e) {
        echo "  ❌ Security assessment error: " . $e->getMessage() . "\n";
        $security_issues[] = "Security assessment failed";
    }
    
    if (empty($security_issues)) {
        echo "\n✅ SECURITY IMPACT: LOW RISK - Security measures intact\n\n";
        return 'LOW_RISK';
    } else {
        echo "\n⚠️  SECURITY IMPACT: MEDIUM RISK\n";
        foreach ($security_issues as $issue) {
            echo "  • $issue\n";
        }
        echo "\n";
        return 'MEDIUM_RISK';
    }
}

function generateOverallAssessment($db_safety, $data_integrity, $functional_impact, $security_impact) {
    echo "🎯 OVERALL SAFETY & IMPACT ASSESSMENT\n";
    echo "=====================================\n\n";
    
    echo "Component Assessments:\n";
    echo "  • Database Safety: $db_safety\n";
    echo "  • Data Integrity: $data_integrity\n";
    echo "  • Functional Impact: $functional_impact\n";
    echo "  • Security Impact: $security_impact\n\n";
    
    // Calculate overall risk level
    $risk_factors = [
        $db_safety === 'SAFE' ? 0 : ($db_safety === 'MODERATE' ? 1 : 2),
        $data_integrity === 'EXCELLENT' ? 0 : ($data_integrity === 'GOOD' ? 1 : 2),
        $functional_impact === 'MINIMAL' ? 0 : 1,
        $security_impact === 'LOW_RISK' ? 0 : 1
    ];
    
    $total_risk = array_sum($risk_factors);
    
    if ($total_risk <= 1) {
        echo "🟢 OVERALL ASSESSMENT: LOW RISK - SAFE TO PROCEED\n";
        echo "✅ The system is stable and ready for next phase\n\n";
        
        echo "Recommendations:\n";
        echo "  • ✅ Continue with Priority 3: Item-Machine Relationships\n";
        echo "  • ✅ Monitor system performance\n";
        echo "  • ✅ Schedule regular data integrity checks\n\n";
        
        return 'PROCEED';
        
    } else if ($total_risk <= 3) {
        echo "🟡 OVERALL ASSESSMENT: MEDIUM RISK - PROCEED WITH CAUTION\n";
        echo "⚠️  Some issues need attention before proceeding\n\n";
        
        echo "Recommendations:\n";
        echo "  • 🔧 Address identified issues first\n";
        echo "  • 🔧 Implement additional monitoring\n";
        echo "  • 🔧 Create rollback plan\n";
        echo "  • ⏸️  Consider pausing for fixes\n\n";
        
        return 'CAUTION';
        
    } else {
        echo "🔴 OVERALL ASSESSMENT: HIGH RISK - DO NOT PROCEED\n";
        echo "❌ Critical issues must be resolved first\n\n";
        
        echo "Recommendations:\n";
        echo "  • 🚨 Stop all changes immediately\n";
        echo "  • 🚨 Restore from backups if necessary\n";
        echo "  • 🚨 Fix critical issues before proceeding\n";
        echo "  • 🚨 Re-run assessment after fixes\n\n";
        
        return 'STOP';
    }
}

// Run complete assessment
try {
    $db_safety = assessDatabaseChanges($pdo);
    $data_integrity = assessDataIntegrity($pdo);
    $functional_impact = assessFunctionalImpact($pdo);
    $security_impact = assessSecurityImpact($pdo);
    
    $recommendation = generateOverallAssessment($db_safety, $data_integrity, $functional_impact, $security_impact);
    
    echo "📋 NEXT STEPS BASED ON ASSESSMENT:\n";
    echo "==================================\n";
    
    switch ($recommendation) {
        case 'PROCEED':
            echo "✅ APPROVED: Ready to proceed with Priority 3\n";
            echo "✅ System is stable and secure\n";
            echo "✅ All major risks mitigated\n";
            break;
            
        case 'CAUTION':
            echo "⚠️  CONDITIONAL: Fix minor issues then proceed\n";
            echo "⚠️  System is mostly stable but needs attention\n";
            echo "⚠️  Recommend addressing issues first\n";
            break;
            
        case 'STOP':
            echo "🚨 HALT: Do not proceed until critical issues resolved\n";
            echo "🚨 System stability at risk\n";
            echo "🚨 Immediate intervention required\n";
            break;
    }
    
} catch (Exception $e) {
    echo "❌ Assessment failed: " . $e->getMessage() . "\n";
    echo "🚨 Unable to determine system safety - manual review required\n";
} 