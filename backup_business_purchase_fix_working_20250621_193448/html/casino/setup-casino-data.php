<?php
require_once __DIR__ . '/../core/config.php';

echo "<!DOCTYPE html><html><head><title>Casino Setup</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;} .debug{background:white;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #007bff;} .error{border-left-color:#dc3545;} .success{border-left-color:#28a745;} .warning{border-left-color:#ffc107;}</style>";
echo "</head><body>";

echo "<h1>🎰 Casino Setup & Data Fix</h1>";

// Step 1: Check if businesses exist
echo "<div class='debug'>";
echo "<h3>📋 Step 1: Check Businesses</h3>";

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM businesses");
    $business_count = $stmt->fetchColumn();
    echo "<p>✅ <strong>Businesses found:</strong> $business_count</p>";
    
    if ($business_count == 0) {
        echo "<p>⚠️ No businesses found. Creating default business...</p>";
        
        $stmt = $pdo->prepare("INSERT INTO businesses (name, email, slug, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute(['Shared Value Vending', 'admin@sharedvaluevending.com', 'shared-value-vending']);
        $business_id = $pdo->lastInsertId();
        
        echo "<p>✅ Created business with ID: $business_id</p>";
    } else {
        // Show existing businesses
        $stmt = $pdo->query("SELECT id, name FROM businesses ORDER BY id");
        $businesses = $stmt->fetchAll();
        echo "<ul>";
        foreach ($businesses as $business) {
            echo "<li>ID {$business['id']}: " . htmlspecialchars($business['name']) . "</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Step 2: Check and create casino participation
echo "<div class='debug'>";
echo "<h3>🎰 Step 2: Setup Casino Participation</h3>";

try {
    // Get all businesses
    $stmt = $pdo->query("SELECT id, name FROM businesses ORDER BY id");
    $businesses = $stmt->fetchAll();
    
    foreach ($businesses as $business) {
        // Check if casino participation exists
        $stmt = $pdo->prepare("SELECT * FROM business_casino_participation WHERE business_id = ?");
        $stmt->execute([$business['id']]);
        $participation = $stmt->fetch();
        
        if (!$participation) {
            echo "<p>⚠️ Creating casino participation for: " . htmlspecialchars($business['name']) . "</p>";
            
            $stmt = $pdo->prepare("
                INSERT INTO business_casino_participation 
                (business_id, casino_enabled, revenue_share_percentage, created_at) 
                VALUES (?, 1, 10.0, NOW())
            ");
            $stmt->execute([$business['id']]);
            
            echo "<p>✅ Casino participation created for business ID {$business['id']}</p>";
        } else {
            $status = $participation['casino_enabled'] ? '✅ ENABLED' : '❌ DISABLED';
            echo "<p>$status Casino participation exists for: " . htmlspecialchars($business['name']) . " (Revenue: {$participation['revenue_share_percentage']}%)</p>";
            
            // Enable casino if disabled
            if (!$participation['casino_enabled']) {
                $stmt = $pdo->prepare("UPDATE business_casino_participation SET casino_enabled = 1 WHERE business_id = ?");
                $stmt->execute([$business['id']]);
                echo "<p>✅ Enabled casino for business ID {$business['id']}</p>";
            }
        }
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Step 3: Check users and sessions
echo "<div class='debug'>";
echo "<h3>👤 Step 3: Check User Setup</h3>";

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt->fetchColumn();
    echo "<p>✅ <strong>Users found:</strong> $user_count</p>";
    
    if ($user_count == 0) {
        echo "<p>⚠️ No users found. Creating test user...</p>";
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, qr_coins, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute(['testuser', 'test@example.com', password_hash('test123', PASSWORD_DEFAULT), 'user', 1000]);
        $user_id = $pdo->lastInsertId();
        
        echo "<p>✅ Created test user with ID: $user_id (username: testuser, password: test123, 1000 QR coins)</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Step 4: Test slot machine access
echo "<div class='debug success'>";
echo "<h3>🎰 Step 4: Test Casino Access</h3>";

try {
    $stmt = $pdo->query("
        SELECT b.id, b.name, bcp.casino_enabled 
        FROM businesses b
        JOIN business_casino_participation bcp ON b.id = bcp.business_id
        WHERE bcp.casino_enabled = 1
        ORDER BY b.id
        LIMIT 1
    ");
    $test_business = $stmt->fetch();
    
    if ($test_business) {
        echo "<p>✅ <strong>Casino ready for testing!</strong></p>";
        echo "<p><strong>Test business:</strong> " . htmlspecialchars($test_business['name']) . " (ID: {$test_business['id']})</p>";
        echo "<p><a href='test-business-debug.php?location_id={$test_business['id']}' target='_blank' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>🔍 Run Debug Test</a></p>";
        echo "<p><a href='slot-machine.php?location_id={$test_business['id']}' target='_blank' style='background:#dc3545;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;margin-left:10px;'>🎰 Test Slot Machine</a></p>";
    } else {
        echo "<p>❌ No businesses with casino enabled found</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// Step 5: Summary and next steps
echo "<div class='debug warning'>";
echo "<h3>📝 Summary & Next Steps</h3>";
echo "<p><strong>What this script did:</strong></p>";
echo "<ul>";
echo "<li>✅ Verified businesses exist in database</li>";
echo "<li>✅ Created/verified casino participation records</li>";
echo "<li>✅ Enabled casino for all businesses</li>";
echo "<li>✅ Verified user accounts exist</li>";
echo "</ul>";

echo "<p><strong>If you're still getting 'BusinessId is missing' error:</strong></p>";
echo "<ol>";
echo "<li>Make sure you're accessing the slot machine with <code>?location_id=1</code> parameter</li>";
echo "<li>Clear your browser cache and cookies</li>";
echo "<li>Check that you're logged into the system</li>";
echo "<li>Run the debug test above to identify specific issues</li>";
echo "</ol>";

echo "<p><strong>Direct links to test:</strong></p>";
echo "<ul>";
echo "<li><a href='../login.php'>🔐 Login Page</a></li>";
echo "<li><a href='../casino/'>🎰 Casino Main Page</a></li>";
echo "<li><a href='test-business-debug.php?location_id=1'>🔍 Debug Test (ID=1)</a></li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?> 