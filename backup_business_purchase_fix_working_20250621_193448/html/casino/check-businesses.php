<?php
require_once __DIR__ . '/../core/config.php';

try {
    echo "<h2>🏢 Available Casino Businesses</h2>";
    
    $stmt = $pdo->prepare("
        SELECT b.id, b.name, 
               COALESCE(bcp.casino_enabled, 0) as casino_enabled,
               bcp.revenue_share_percentage
        FROM businesses b 
        LEFT JOIN business_casino_participation bcp ON b.id = bcp.business_id 
        ORDER BY b.name
    ");
    $stmt->execute();
    $businesses = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Business Name</th><th>Casino Enabled</th><th>Revenue Share</th><th>Slot Link</th></tr>";
    
    foreach ($businesses as $business) {
        $casino_status = $business['casino_enabled'] ? '✅ YES' : '❌ NO';
        $revenue_share = $business['revenue_share_percentage'] ? $business['revenue_share_percentage'] . '%' : 'N/A';
        
        echo "<tr>";
        echo "<td>{$business['id']}</td>";
        echo "<td>" . htmlspecialchars($business['name']) . "</td>";
        echo "<td>$casino_status</td>";
        echo "<td>$revenue_share</td>";
        
        if ($business['casino_enabled']) {
            echo "<td><a href='" . APP_URL . "/casino/slot-machine.php?business_id={$business['id']}' target='_blank'>🎰 Play Slots</a></td>";
        } else {
            echo "<td>Not Available</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<br><h3>🎯 For Users:</h3>";
    echo "<p>To play slots properly:</p>";
    echo "<ol>";
    echo "<li>Go to <a href='" . APP_URL . "/casino/'>Casino Main Page</a></li>";
    echo "<li>Find your preferred business from the list above</li>";
    echo "<li>Click the 'Slots' button next to it</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 