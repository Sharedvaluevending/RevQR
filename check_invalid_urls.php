<?php
/**
 * Investigate Invalid URLs in QR Codes
 * Identifies the 6 QR codes with invalid URLs found in assessment
 */

require_once __DIR__ . '/html/core/config.php';

echo "🔍 INVESTIGATING INVALID URL QR CODES\n";
echo "====================================\n\n";

try {
    // Find QR codes with invalid URLs (static/dynamic types only)
    $stmt = $pdo->query("
        SELECT 
            id, 
            qr_type, 
            code, 
            machine_name, 
            url, 
            business_id,
            created_at,
            CASE 
                WHEN url IS NULL THEN 'NULL URL'
                WHEN url = '' THEN 'EMPTY URL'
                WHEN url NOT LIKE 'http%' THEN 'INVALID FORMAT'
                ELSE 'VALID'
            END as issue_type
        FROM qr_codes 
        WHERE qr_type IN ('static', 'dynamic') 
        AND (url IS NULL OR url = '' OR url NOT LIKE 'http%')
        ORDER BY id
    ");
    
    $invalid_qrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($invalid_qrs)) {
        echo "✅ No QR codes with invalid URLs found!\n";
        echo "The assessment may have been outdated or the issue was already fixed.\n\n";
        
        // Double-check by showing all static/dynamic QR codes
        echo "📊 All Static/Dynamic QR Codes:\n";
        $all_static_dynamic = $pdo->query("
            SELECT id, qr_type, code, machine_name, url 
            FROM qr_codes 
            WHERE qr_type IN ('static', 'dynamic') 
            ORDER BY id
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($all_static_dynamic)) {
            echo "  • No static or dynamic QR codes found\n";
        } else {
            foreach ($all_static_dynamic as $qr) {
                $url_status = empty($qr['url']) ? 'NO URL' : (filter_var($qr['url'], FILTER_VALIDATE_URL) ? 'VALID' : 'INVALID');
                echo "  • ID {$qr['id']}: {$qr['qr_type']} - {$qr['code']} - URL: " . ($qr['url'] ?: 'NONE') . " ($url_status)\n";
            }
        }
        
    } else {
        echo "Found " . count($invalid_qrs) . " QR codes with invalid URLs:\n\n";
        
        foreach ($invalid_qrs as $qr) {
            echo "🔴 QR Code ID: {$qr['id']}\n";
            echo "   Type: {$qr['qr_type']}\n";
            echo "   Code: {$qr['code']}\n";
            echo "   Machine: {$qr['machine_name']}\n";
            echo "   URL: " . ($qr['url'] ?: 'NULL') . "\n";
            echo "   Issue: {$qr['issue_type']}\n";
            echo "   Created: {$qr['created_at']}\n";
            echo "   Business ID: {$qr['business_id']}\n";
            echo "\n";
        }
        
        // Analyze the issues
        echo "📊 Issue Breakdown:\n";
        $issue_counts = [];
        foreach ($invalid_qrs as $qr) {
            $issue_counts[$qr['issue_type']] = ($issue_counts[$qr['issue_type']] ?? 0) + 1;
        }
        
        foreach ($issue_counts as $issue => $count) {
            echo "  • $issue: $count QR codes\n";
        }
        
        echo "\n💡 Impact Analysis:\n";
        echo "  • These QR codes may not work when scanned\n";
        echo "  • Only affects static/dynamic QR types\n";
        echo "  • Other QR types (voting, vending, etc.) are unaffected\n";
        echo "  • This is a data quality issue, not a system failure\n\n";
        
        // Suggest fixes
        echo "🔧 Suggested Fixes:\n";
        echo "  1. For NULL URLs: Add proper destination URLs\n";
        echo "  2. For empty URLs: Set default landing page or remove QR code\n";
        echo "  3. For invalid format: Fix URL format (add http/https)\n\n";
        
        // Show example fix queries
        echo "🛠️  Example Fix Queries:\n";
        echo "-- Fix invalid format URLs (add https://)\n";
        echo "UPDATE qr_codes SET url = CONCAT('https://', url) WHERE qr_type IN ('static', 'dynamic') AND url NOT LIKE 'http%' AND url IS NOT NULL AND url != '';\n\n";
        
        echo "-- Set default URL for empty/null URLs\n";
        echo "UPDATE qr_codes SET url = 'https://your-default-landing-page.com' WHERE qr_type IN ('static', 'dynamic') AND (url IS NULL OR url = '');\n\n";
    }
    
    // Check if this is really blocking
    echo "🎯 RISK ASSESSMENT:\n";
    echo "==================\n";
    
    $total_qr = $pdo->query("SELECT COUNT(*) FROM qr_codes")->fetchColumn();
    $working_qr = $total_qr - count($invalid_qrs);
    $working_percentage = round(($working_qr / $total_qr) * 100, 1);
    
    echo "  • Total QR codes: $total_qr\n";
    echo "  • Working QR codes: $working_qr\n";
    echo "  • Invalid QR codes: " . count($invalid_qrs) . "\n";
    echo "  • System working rate: $working_percentage%\n\n";
    
    if ($working_percentage >= 90) {
        echo "✅ CONCLUSION: LOW IMPACT - System is mostly functional\n";
        echo "   This issue can be fixed after Priority 3 or as a maintenance task\n";
    } else if ($working_percentage >= 75) {
        echo "⚠️  CONCLUSION: MODERATE IMPACT - Should fix before proceeding\n";
        echo "   Recommend fixing these URLs before Priority 3\n";
    } else {
        echo "🚨 CONCLUSION: HIGH IMPACT - Must fix immediately\n";
        echo "   Too many broken QR codes to proceed safely\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error investigating URLs: " . $e->getMessage() . "\n";
} 