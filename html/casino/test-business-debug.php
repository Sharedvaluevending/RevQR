<?php
require_once __DIR__ . '/../core/config.php';

echo "<!DOCTYPE html><html><head><title>Business Debug Test</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;} .debug{background:white;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #007bff;} .error{border-left-color:#dc3545;} .success{border-left-color:#28a745;}</style>";
echo "</head><body>";

echo "<h1>🔧 Business Debug Test</h1>";

// Test URL parameters
echo "<div class='debug'>";
echo "<h3>📋 URL Parameters Debug</h3>";
echo "<p><strong>Current URL:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p><strong>business_id from GET:</strong> " . ($_GET['business_id'] ?? 'NOT SET') . "</p>";
echo "<p><strong>location_id from GET:</strong> " . ($_GET['location_id'] ?? 'NOT SET') . "</p>";

// Simulate what slot-machine.php does
$business_id = $_GET['business_id'] ?? $_GET['location_id'] ?? null;
if ($business_id !== null) {
    $business_id = (int) $business_id;
    if ($business_id <= 0) {
        $business_id = null;
    }
}
echo "<p><strong>Final processed business_id:</strong> " . ($business_id ?: 'NULL') . "</p>";
echo "</div>";

if ($business_id) {
    // Check if business exists
    echo "<div class='debug " . ($business_id ? 'success' : 'error') . "'>";
    echo "<h3>🏢 Business Database Check</h3>";
    
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM businesses WHERE id = ?");
        $stmt->execute([$business_id]);
        $business = $stmt->fetch();
        
        if ($business) {
            echo "<p>✅ <strong>Business found:</strong> " . htmlspecialchars($business['name']) . "</p>";
        } else {
            echo "<p>❌ <strong>Business not found for ID:</strong> $business_id</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ <strong>Database error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // Check casino participation
    echo "<div class='debug'>";
    echo "<h3>🎰 Casino Participation Check</h3>";
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM business_casino_participation WHERE business_id = ?");
        $stmt->execute([$business_id]);
        $participation = $stmt->fetch();
        
        if ($participation) {
            echo "<p>✅ <strong>Casino participation found</strong></p>";
            echo "<p><strong>Casino enabled:</strong> " . ($participation['casino_enabled'] ? '✅ YES' : '❌ NO') . "</p>";
            echo "<p><strong>Revenue share:</strong> " . ($participation['revenue_share_percentage'] ?? 'N/A') . "%</p>";
        } else {
            echo "<p>❌ <strong>No casino participation record found</strong></p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ <strong>Casino participation check error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // Test the actual query from slot-machine.php
    echo "<div class='debug'>";
    echo "<h3>🔍 Slot Machine Query Test</h3>";
    
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, bcp.*, 
                   COUNT(cp.id) as daily_plays,
                   GROUP_CONCAT(DISTINCT pr.prize_name) as available_prizes
            FROM businesses b
            JOIN business_casino_participation bcp ON b.id = bcp.business_id
            LEFT JOIN casino_plays cp ON b.id = cp.business_id AND cp.user_id = ? AND DATE(cp.played_at) = CURDATE()
            LEFT JOIN casino_prizes pr ON b.id = pr.business_id AND pr.is_active = 1
            WHERE b.id = ? AND bcp.casino_enabled = 1
            GROUP BY b.id
        ");
        
        // Use a dummy user_id for testing
        $test_user_id = 1;
        $stmt->execute([$test_user_id, $business_id]);
        $business_data = $stmt->fetch();
        
        if ($business_data) {
            echo "<p>✅ <strong>Slot machine query successful</strong></p>";
            echo "<p><strong>Business name:</strong> " . htmlspecialchars($business_data['name']) . "</p>";
            echo "<p><strong>Casino enabled:</strong> " . ($business_data['casino_enabled'] ? '✅ YES' : '❌ NO') . "</p>";
            echo "<p><strong>Daily plays:</strong> " . $business_data['daily_plays'] . "</p>";
        } else {
            echo "<p>❌ <strong>Slot machine query returned no results</strong></p>";
            echo "<p>This means either:</p>";
            echo "<ul>";
            echo "<li>Business doesn't exist</li>";
            echo "<li>No casino participation record</li>";
            echo "<li>Casino is not enabled for this business</li>";
            echo "</ul>";
        }
    } catch (Exception $e) {
        echo "<p>❌ <strong>Slot machine query error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    if ($business_data) {
        echo "<div class='debug success'>";
        echo "<h3>🎰 Ready to Test Slot Machine</h3>";
        echo "<p>✅ All checks passed! You can access the slot machine.</p>";
        echo "<p><a href='slot-machine.php?location_id=$business_id' target='_blank' style='background:#dc3545;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>🎰 Test Slot Machine</a></p>";
        echo "</div>";
    }
} else {
    echo "<div class='debug error'>";
    echo "<h3>❌ No Business ID Provided</h3>";
    echo "<p>Add <code>?location_id=1</code> or <code>?business_id=1</code> to the URL to test</p>";
    echo "<p><a href='?location_id=1'>🔗 Test with location_id=1</a></p>";
    echo "</div>";
}

// Show all available businesses with casino enabled
echo "<div class='debug'>";
echo "<h3>🎰 Available Casino Businesses</h3>";

try {
    $stmt = $pdo->query("
        SELECT b.id, b.name, bcp.casino_enabled 
        FROM businesses b
        LEFT JOIN business_casino_participation bcp ON b.id = bcp.business_id
        WHERE bcp.casino_enabled = 1
        ORDER BY b.name
    ");
    $casino_businesses = $stmt->fetchAll();
    
    if ($casino_businesses) {
        echo "<ul>";
        foreach ($casino_businesses as $cb) {
            echo "<li><strong>ID {$cb['id']}:</strong> " . htmlspecialchars($cb['name']) . " - <a href='?location_id={$cb['id']}'>Test</a></li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ No businesses have casino enabled</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error loading casino businesses: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

echo "</body></html>";
?> 