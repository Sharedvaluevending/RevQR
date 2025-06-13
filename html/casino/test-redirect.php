<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';

echo "<h2>Casino Redirect Test</h2>";

$location_id = $_GET['location_id'] ?? 'NOT_PROVIDED';
echo "<p>Location ID received: " . htmlspecialchars($location_id) . "</p>";

if ($location_id && is_numeric($location_id)) {
    echo "<p>✅ Valid location ID provided</p>";
    
    // Check if business exists
    $stmt = $pdo->prepare("SELECT name FROM businesses WHERE id = ?");
    $stmt->execute([$location_id]);
    $business = $stmt->fetch();
    
    if ($business) {
        echo "<p>✅ Business found: " . htmlspecialchars($business['name']) . "</p>";
        
        // Check if casino is enabled for this business
        $stmt = $pdo->prepare("SELECT casino_enabled FROM business_casino_participation WHERE business_id = ?");
        $stmt->execute([$location_id]);
        $participation = $stmt->fetch();
        
        if ($participation && $participation['casino_enabled']) {
            echo "<p>✅ Casino is enabled for this business</p>";
            echo "<p>🎰 <a href='slot-machine.php?location_id={$location_id}'>Click here to go to actual slot machine</a></p>";
        } else {
            echo "<p>❌ Casino not enabled for this business</p>";
        }
    } else {
        echo "<p>❌ Business not found</p>";
    }
} else {
    echo "<p>❌ Invalid or missing location ID</p>";
}

echo "<p><a href='index.php'>← Back to Casino Lobby</a></p>";
?> 