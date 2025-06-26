<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';

echo "<!DOCTYPE html><html><head><title>Casino Business ID Debug</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;} .debug{background:white;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #007bff;} .error{border-left-color:#dc3545;} .success{border-left-color:#28a745;}</style>";
echo "</head><body>";

echo "<h1>🔧 Casino Business ID Debug</h1>";

// Check URL parameters
echo "<div class='debug'>";
echo "<h3>📋 URL Parameters</h3>";
echo "<p><strong>business_id:</strong> " . ($_GET['business_id'] ?? 'NOT SET') . "</p>";
echo "<p><strong>location_id:</strong> " . ($_GET['location_id'] ?? 'NOT SET') . "</p>";
echo "<p><strong>Current URL:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
echo "</div>";

// Get business ID from either parameter
$business_id = $_GET['business_id'] ?? $_GET['location_id'] ?? null;

echo "<div class='debug " . ($business_id ? 'success' : 'error') . "'>";
echo "<h3>🎯 Resolved Business ID</h3>";
echo "<p><strong>Final business_id:</strong> " . ($business_id ?: 'NULL/EMPTY') . "</p>";
echo "</div>";

if ($business_id) {
    // Check if business exists and has casino enabled
    $stmt = $pdo->prepare("
        SELECT b.id, b.name, bcp.casino_enabled 
        FROM businesses b
        LEFT JOIN business_casino_participation bcp ON b.id = bcp.business_id
        WHERE b.id = ?
    ");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch();
    
    echo "<div class='debug " . ($business ? 'success' : 'error') . "'>";
    echo "<h3>🏢 Business Database Check</h3>";
    if ($business) {
        echo "<p>✅ <strong>Business found:</strong> " . htmlspecialchars($business['name']) . "</p>";
        echo "<p><strong>Casino enabled:</strong> " . ($business['casino_enabled'] ? '✅ YES' : '❌ NO') . "</p>";
    } else {
        echo "<p>❌ <strong>Business not found in database</strong></p>";
    }
    echo "</div>";
    
    // Check available businesses with casino enabled
    $stmt = $pdo->query("
        SELECT b.id, b.name, bcp.casino_enabled 
        FROM businesses b
        LEFT JOIN business_casino_participation bcp ON b.id = bcp.business_id
        WHERE bcp.casino_enabled = 1
        ORDER BY b.name
    ");
    $casino_businesses = $stmt->fetchAll();
    
    echo "<div class='debug'>";
    echo "<h3>🎰 Available Casino Businesses</h3>";
    if ($casino_businesses) {
        echo "<ul>";
        foreach ($casino_businesses as $cb) {
            $current = ($cb['id'] == $business_id) ? ' <strong>(CURRENT)</strong>' : '';
            echo "<li>ID: {$cb['id']} - {$cb['name']}{$current}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ No businesses have casino enabled</p>";
    }
    echo "</div>";
}

// Show correct URLs to access slot machine
echo "<div class='debug'>";
echo "<h3>🔗 Correct Slot Machine URLs</h3>";
echo "<p>The slot machine should be accessed with a business_id or location_id parameter:</p>";
echo "<ul>";
echo "<li><code>/casino/slot-machine.php?business_id=1</code></li>";
echo "<li><code>/casino/slot-machine.php?location_id=1</code></li>";
echo "</ul>";
echo "</div>";

// Show session info
if (is_logged_in()) {
    echo "<div class='debug success'>";
    echo "<h3>👤 User Session</h3>";
    echo "<p>✅ <strong>Logged in as user ID:</strong> " . $_SESSION['user_id'] . "</p>";
    echo "</div>";
} else {
    echo "<div class='debug error'>";
    echo "<h3>👤 User Session</h3>";
    echo "<p>❌ <strong>Not logged in</strong></p>";
    echo "</div>";
}

echo "</body></html>";
?> 