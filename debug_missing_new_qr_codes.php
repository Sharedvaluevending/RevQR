<?php
/**
 * Debug Missing New QR Codes
 * For users who are logged in but don't see their newest QR codes
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/session.php';
require_once __DIR__ . '/html/core/auth.php';

echo "<h1>🔍 Debug: Missing New QR Codes</h1>";
echo "<p>For users who are logged in but don't see their newest QR codes in the manager.</p>";

// Check if user is logged in
if (!is_logged_in()) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>❌ Not logged in - please log in first</div>";
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;
$business_id = $_SESSION['business_id'] ?? null;

echo "<h2>1. Your Session Info</h2>";
echo "User ID: $user_id<br>";
echo "User Role: $user_role<br>";
echo "Business ID: $business_id<br>";

// Get or determine business ID
if (!$business_id) {
    $stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $business = $stmt->fetch();
    $business_id = $business ? $business['id'] : 1;
    echo "<br>📝 Determined Business ID: $business_id (from businesses table)<br>";
}

echo "<h2>2. All Your QR Codes (Last 48 Hours)</h2>";
try {
    // Check all recent QR codes with different business_id scenarios
    $stmt = $pdo->prepare("
        SELECT 
            qr.*,
            COALESCE(qr.business_id, c.business_id, vl.business_id, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.business_id'))) as effective_business_id,
            JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.business_id')) as meta_business_id
        FROM qr_codes qr
        LEFT JOIN campaigns c ON qr.campaign_id = c.id
        LEFT JOIN voting_lists vl ON qr.machine_id = vl.id
        WHERE qr.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY qr.created_at DESC
    ");
    $stmt->execute();
    $all_recent_qr = $stmt->fetchAll();
    
    if (!empty($all_recent_qr)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f1f1f1;'>";
        echo "<th>ID</th><th>DB Business ID</th><th>Meta Business ID</th><th>Effective ID</th><th>Your Business?</th><th>Type</th><th>Status</th><th>Created</th>";
        echo "</tr>";
        
        $your_qr_count = 0;
        $other_qr_count = 0;
        
        foreach ($all_recent_qr as $qr) {
            $is_yours = ($qr['effective_business_id'] == $business_id);
            if ($is_yours) $your_qr_count++;
            else $other_qr_count++;
            
            $row_color = $is_yours ? 'background: #d4edda;' : 'background: #f8d7da;';
            
            echo "<tr style='$row_color'>";
            echo "<td>" . $qr['id'] . "</td>";
            echo "<td>" . ($qr['business_id'] ?: 'NULL') . "</td>";
            echo "<td>" . ($qr['meta_business_id'] ?: 'NULL') . "</td>";
            echo "<td>" . ($qr['effective_business_id'] ?: 'NULL') . "</td>";
            echo "<td>" . ($is_yours ? '✅ YES' : '❌ NO') . "</td>";
            echo "<td>" . $qr['qr_type'] . "</td>";
            echo "<td>" . $qr['status'] . "</td>";
            echo "<td>" . date('M j H:i:s', strtotime($qr['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<br><strong>Summary:</strong><br>";
        echo "✅ Your QR codes: $your_qr_count<br>";
        echo "❌ Other business QR codes: $other_qr_count<br>";
        
    } else {
        echo "<p>❌ No QR codes found in the last 48 hours</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<h2>3. QR Manager Query Test</h2>";
echo "<p>Testing the exact query used by the QR manager...</p>";

try {
    // This is the exact query from qr_manager.php
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
    ");
    $stmt->execute([$business_id, $business_id, $business_id, $business_id]);
    $manager_qr_codes = $stmt->fetchAll();
    
    echo "<p>🔍 QR Manager query found: <strong>" . count($manager_qr_codes) . "</strong> QR codes for your business</p>";
    
    if (!empty($manager_qr_codes)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f1f1f1;'>";
        echo "<th>ID</th><th>Type</th><th>Created</th><th>Code (partial)</th>";
        echo "</tr>";
        
        foreach (array_slice($manager_qr_codes, 0, 10) as $qr) {
            echo "<tr>";
            echo "<td>" . $qr['id'] . "</td>";
            echo "<td>" . $qr['qr_type'] . "</td>";
            echo "<td>" . date('M j H:i:s', strtotime($qr['created_at'])) . "</td>";
            echo "<td style='font-family: monospace;'>" . substr($qr['code'], 0, 20) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ QR Manager query error: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Recent QR Generation Activity</h2>";
try {
    // Check for very recent QR generation
    $stmt = $pdo->prepare("
        SELECT 
            id, qr_type, business_id, status, created_at,
            JSON_UNQUOTE(JSON_EXTRACT(meta, '$.business_id')) as meta_business_id
        FROM qr_codes 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $very_recent = $stmt->fetchAll();
    
    if (!empty($very_recent)) {
        echo "<p>📊 QR codes created in the last 2 hours:</p>";
        foreach ($very_recent as $qr) {
            $time_ago = floor((time() - strtotime($qr['created_at'])) / 60);
            echo "• ID {$qr['id']} - {$qr['qr_type']} - Business: {$qr['business_id']} (meta: {$qr['meta_business_id']}) - Status: {$qr['status']} - $time_ago minutes ago<br>";
        }
    } else {
        echo "<p>❌ No QR codes created in the last 2 hours</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h2>5. Diagnosis</h2>";

// Calculate what should be showing
$should_show = 0;
$wrong_business = 0;
$wrong_status = 0;

foreach ($all_recent_qr ?? [] as $qr) {
    if ($qr['effective_business_id'] == $business_id) {
        if ($qr['status'] === 'active') {
            $should_show++;
        } else {
            $wrong_status++;
        }
    } else {
        $wrong_business++;
    }
}

echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>";
echo "<h3>📊 Analysis:</h3>";
echo "• QR codes that SHOULD show in manager: <strong>$should_show</strong><br>";
echo "• QR codes with wrong business ID: <strong>$wrong_business</strong><br>";
echo "• QR codes with wrong status: <strong>$wrong_status</strong><br>";
echo "• QR codes actually showing in manager: <strong>" . count($manager_qr_codes ?? []) . "</strong><br>";

if ($should_show > count($manager_qr_codes ?? [])) {
    echo "<br>⚠️ <strong>PROBLEM FOUND:</strong> You have $should_show QR codes that should show, but only " . count($manager_qr_codes ?? []) . " are appearing.<br>";
    echo "<br>💡 <strong>Possible causes:</strong><br>";
    echo "• Cache issue - try refreshing the page<br>";
    echo "• Database query issue<br>";
    echo "• Business ID mismatch<br>";
} elseif ($should_show == 0) {
    echo "<br>⚠️ <strong>ISSUE:</strong> No QR codes are associated with your business ID ($business_id)<br>";
    echo "💡 This means QR codes are being saved with a different business ID than expected<br>";
} else {
    echo "<br>✅ <strong>LOOKS GOOD:</strong> All your QR codes should be showing correctly<br>";
}

echo "</div>";

echo "<h2>6. Quick Fixes to Try</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<ol>";
echo "<li><strong>Hard refresh the page:</strong> Ctrl+F5 (or Cmd+Shift+R on Mac)</li>";
echo "<li><strong>Clear browser cache</strong> for the RevenueQR site</li>";
echo "<li><strong>Try generating a new test QR code</strong> and see if it appears</li>";
echo "<li><strong>Check if you're looking at the right time period</strong> - manager might be sorted differently</li>";
echo "</ol>";
echo "</div>";

?> 