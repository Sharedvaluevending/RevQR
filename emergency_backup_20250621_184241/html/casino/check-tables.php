<?php
/**
 * Check Casino Tables
 * Quick check to see if required tables exist
 */

require_once __DIR__ . '/../core/config.php';

echo "<h2>Casino Table Check</h2>";

try {
    // Check if core tables exist
    $tables_to_check = [
        'users',
        'businesses', 
        'casino_settings',
        'business_casino_participation'
    ];
    
    foreach ($tables_to_check as $table) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
            echo "✅ Table '$table' exists and is accessible<br>";
        } catch (Exception $e) {
            echo "❌ Table '$table' - ERROR: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br><h3>Sample Data Check</h3>";
    
    // Check if location_id=1 exists
    $stmt = $pdo->prepare("SELECT id, business_name, is_casino_enabled FROM businesses WHERE id = 1");
    $stmt->execute();
    $business = $stmt->fetch();
    
    if ($business) {
        echo "✅ Business ID 1 exists: " . $business['business_name'] . "<br>";
        echo "Casino enabled: " . ($business['is_casino_enabled'] ? 'Yes' : 'No') . "<br>";
        
        // Check casino settings
        $stmt = $pdo->prepare("SELECT * FROM casino_settings WHERE business_id = 1");
        $stmt->execute();
        $settings = $stmt->fetch();
        
        if ($settings) {
            echo "✅ Casino settings exist for business 1<br>";
        } else {
            echo "⚠️ No casino settings found for business 1<br>";
        }
        
        // Check participation
        $stmt = $pdo->prepare("SELECT casino_enabled FROM business_casino_participation WHERE business_id = 1");
        $stmt->execute();
        $participation = $stmt->fetch();
        
        if ($participation) {
            echo "Casino participation: " . ($participation['casino_enabled'] ? 'Enabled' : 'Disabled') . "<br>";
        } else {
            echo "⚠️ No casino participation record for business 1<br>";
        }
        
    } else {
        echo "❌ Business ID 1 does not exist<br>";
    }
    
    echo "<br><a href='blackjack-debug.php?location_id=1'>Test Blackjack Debug</a><br>";
    echo "<a href='blackjack.php?location_id=1'>Try Blackjack</a>";

} catch (Exception $e) {
    echo "❌ Database connection error: " . $e->getMessage();
}
?> 