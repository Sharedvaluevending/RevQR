<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';

// Debug output
echo "<h1>QR Manager Debug</h1>";
echo "<h2>Session Debug</h2>";
echo "Session data: <pre>" . print_r($_SESSION, true) . "</pre>";
echo "Session ID: " . session_id() . "<br>";
echo "is_logged_in(): " . (is_logged_in() ? "YES" : "NO") . "<br>";
echo "has_role('business'): " . (has_role('business') ? "YES" : "NO") . "<br>";

// Check if we can get the business ID
echo "<h2>Business ID Debug</h2>";
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
    echo "business_id: " . $business_id . "<br>";
} catch (Exception $e) {
    echo "Error getting business ID: " . $e->getMessage() . "<br>";
    $business_id = null;
}

// Try the require_role function directly
echo "<h2>Authentication Debug</h2>";
try {
    // This should either work or redirect/exit
    require_role('business');
    echo "require_role('business') passed successfully<br>";
} catch (Exception $e) {
    echo "require_role('business') failed: " . $e->getMessage() . "<br>";
}

echo "<h2>Database Connection</h2>";
echo "PDO connected: " . (isset($pdo) ? "YES" : "NO") . "<br>";

// If we get here, authentication worked
echo "<h2>Result</h2>";
echo "If you see this, authentication is working. The issue might be in the original file.";

echo "<h1>🔍 QR Manager Debug</h1>";

// Check if user is logged in
echo "<h2>🔐 Authentication Status</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";

if (isset($_SESSION['user_id'])) {
    echo "<p>✅ <strong>Logged in as User ID:</strong> {$_SESSION['user_id']}</p>";
    if (isset($_SESSION['username'])) {
        echo "<p>✅ <strong>Username:</strong> {$_SESSION['username']}</p>";
    }
    if (isset($_SESSION['role'])) {
        echo "<p>✅ <strong>Role:</strong> {$_SESSION['role']}</p>";
    }
} else {
    echo "<p>❌ <strong>NOT LOGGED IN</strong></p>";
    echo "<p>This explains why you see the login page!</p>";
    echo "<p><a href='/login.php'>Click here to login</a></p>";
    echo "</div>";
    
    echo "<h2>🎯 Solution</h2>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<p><strong>The issue is that you're not logged in.</strong></p>";
    echo "<ol>";
    echo "<li>Go to the <a href='/login.php'>login page</a></li>";
    echo "<li>Login with your business account credentials</li>";
    echo "<li>Then visit the <a href='/qr_manager.php'>QR Manager</a></li>";
    echo "</ol>";
    echo "</div>";
    exit;
}

echo "</div>";

// Check business access
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';

echo "<h2>🏢 Business Association</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";

try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
    echo "<p>✅ <strong>Business ID:</strong> {$business_id}</p>";
    
    // Get business details
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch();
    
    if ($business) {
        echo "<p>✅ <strong>Business Name:</strong> {$business['name']}</p>";
        echo "<p>✅ <strong>Business Owner:</strong> User ID {$business['user_id']}</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Business Error:</strong> " . $e->getMessage() . "</p>";
}

echo "</div>";

// Check QR codes with the CORRECTED query
echo "<h2>📊 QR Codes Status</h2>";

// First, let's see what the current QR manager query returns
$where_conditions = ["qr.status != 'deleted'"];
$params = [];

// OLD PROBLEMATIC QUERY (from QR manager)
$where_conditions[] = "(c.business_id = ? OR qr.campaign_id IS NULL)";
$params[] = $business_id;

$where_clause = implode(' AND ', $where_conditions);

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h3>❌ Current QR Manager Query (PROBLEMATIC)</h3>";

$stmt = $pdo->prepare("
    SELECT 
        qr.id, qr.business_id, qr.qr_type, qr.machine_name, qr.code, qr.status,
        c.name as campaign_name,
        COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
            CONCAT('/uploads/qr/', qr.code, '.png')
        ) as qr_url
    FROM qr_codes qr
    LEFT JOIN campaigns c ON qr.campaign_id = c.id
    WHERE $where_clause
    ORDER BY qr.created_at DESC
");
$stmt->execute($params);
$qr_codes_old = $stmt->fetchAll();

echo "<p><strong>QR Codes found with current query:</strong> " . count($qr_codes_old) . "</p>";

if (count($qr_codes_old) > 0) {
    echo "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #dee2e6;'>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>ID</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Business ID</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Type</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Machine</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Campaign</th>";
    echo "</tr>";
    
    foreach (array_slice($qr_codes_old, 0, 5) as $qr) {
        echo "<tr>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$qr['id']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$qr['business_id']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$qr['qr_type']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . ($qr['machine_name'] ?: '-') . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . ($qr['campaign_name'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ No QR codes returned by current query</p>";
}

echo "</div>";

// NEW CORRECTED QUERY
echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h3>✅ CORRECTED Query (Should work)</h3>";

$stmt = $pdo->prepare("
    SELECT 
        qr.id, qr.business_id, qr.qr_type, qr.machine_name, qr.code, qr.status,
        c.name as campaign_name,
        COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
            CONCAT('/uploads/qr/', qr.code, '.png')
        ) as qr_url
    FROM qr_codes qr
    LEFT JOIN campaigns c ON qr.campaign_id = c.id AND c.business_id = ?
    WHERE qr.business_id = ? AND qr.status = 'active'
    ORDER BY qr.created_at DESC
");
$stmt->execute([$business_id, $business_id]);
$qr_codes_fixed = $stmt->fetchAll();

echo "<p><strong>QR Codes found with CORRECTED query:</strong> " . count($qr_codes_fixed) . "</p>";

if (count($qr_codes_fixed) > 0) {
    echo "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #dee2e6;'>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>ID</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Business ID</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Type</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Machine</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Campaign</th>";
    echo "</tr>";
    
    foreach (array_slice($qr_codes_fixed, 0, 10) as $qr) {
        echo "<tr>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$qr['id']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$qr['business_id']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$qr['qr_type']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . ($qr['machine_name'] ?: '-') . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . ($qr['campaign_name'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ No QR codes found even with corrected query</p>";
}

echo "</div>";

// Show all QR codes for this business (including deleted)
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h3>📋 ALL QR Codes for Business ID {$business_id}</h3>";

$stmt = $pdo->prepare("
    SELECT id, business_id, qr_type, machine_name, code, status, created_at
    FROM qr_codes 
    WHERE business_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$business_id]);
$all_qr_codes = $stmt->fetchAll();

echo "<p><strong>Total QR codes (including deleted):</strong> " . count($all_qr_codes) . "</p>";

if (count($all_qr_codes) > 0) {
    $active_count = count(array_filter($all_qr_codes, fn($qr) => $qr['status'] === 'active'));
    $deleted_count = count(array_filter($all_qr_codes, fn($qr) => $qr['status'] === 'deleted'));
    
    echo "<p>✅ <strong>Active:</strong> {$active_count} | ❌ <strong>Deleted:</strong> {$deleted_count}</p>";
    
    echo "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #dee2e6;'>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>ID</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Type</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Machine</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Status</th>";
    echo "<th style='padding: 8px; border: 1px solid #aaa;'>Created</th>";
    echo "</tr>";
    
    foreach ($all_qr_codes as $qr) {
        $status_color = $qr['status'] === 'active' ? '#d4edda' : '#f8d7da';
        echo "<tr style='background: {$status_color};'>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$qr['id']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$qr['qr_type']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . ($qr['machine_name'] ?: '-') . "</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$qr['status']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . date('M j, Y H:i', strtotime($qr['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ No QR codes found at all for this business</p>";
}

echo "</div>";

echo "<h2>🛠️ Next Steps</h2>";
echo "<div style='background: #cce5ff; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<ol>";
echo "<li><strong>If you see this debug page but the QR Manager shows login:</strong> Clear your browser cache and try again</li>";
echo "<li><strong>If the corrected query shows QR codes:</strong> I need to fix the QR Manager query</li>";
echo "<li><strong>If you have active QR codes:</strong> They should appear in the QR Manager once the query is fixed</li>";
echo "</ol>";

echo "<p><strong>Quick Links to Test:</strong></p>";
echo "<ul>";
echo "<li><a href='/qr_manager.php' target='_blank'>QR Manager (Original)</a></li>";
echo "<li><a href='/qr-display.php' target='_blank'>QR Display (Should work)</a></li>";
echo "<li><a href='/test_qr_system_fix.php' target='_blank'>QR System Test</a></li>";
echo "</ul>";
echo "</div>";

?> 