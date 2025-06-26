<?php
/**
 * Enable Casino for Business ID 1
 * One-time setup script
 */

require_once __DIR__ . '/../core/config.php';

echo "<h2>Enabling Casino for Business ID 1</h2>";

try {
    // First check if business exists
    $stmt = $pdo->prepare("SELECT id, business_name FROM businesses WHERE id = 1");
    $stmt->execute();
    $business = $stmt->fetch();
    
    if (!$business) {
        echo "❌ Business ID 1 doesn't exist. Creating sample business...<br>";
        
        // Create a sample business
        $stmt = $pdo->prepare("
            INSERT INTO businesses (id, business_name, app_url, is_casino_enabled) 
            VALUES (1, 'QR Casino Demo', 'https://revenueqr.sharedvaluevending.com', 1)
            ON DUPLICATE KEY UPDATE is_casino_enabled = 1
        ");
        $stmt->execute();
        echo "✅ Created/updated business ID 1<br>";
    } else {
        echo "✅ Business found: " . $business['business_name'] . "<br>";
        
        // Enable casino for this business
        $stmt = $pdo->prepare("UPDATE businesses SET is_casino_enabled = 1 WHERE id = 1");
        $stmt->execute();
        echo "✅ Enabled casino flag for business<br>";
    }
    
    // Create casino_settings table if it doesn't exist
    $create_casino_settings = "
        CREATE TABLE IF NOT EXISTS casino_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            business_id INT NOT NULL,
            jackpot_multiplier DECIMAL(8,2) DEFAULT 50.00,
            min_bet INT DEFAULT 1,
            max_bet INT DEFAULT 100,
            house_edge DECIMAL(5,2) DEFAULT 5.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_business (business_id),
            INDEX idx_business_id (business_id)
        )
    ";
    $pdo->exec($create_casino_settings);
    echo "✅ Casino settings table ready<br>";
    
    // Insert default casino settings
    $stmt = $pdo->prepare("
        INSERT INTO casino_settings (business_id, jackpot_multiplier, min_bet, max_bet, house_edge)
        VALUES (1, 50.00, 1, 100, 5.00)
        ON DUPLICATE KEY UPDATE 
        jackpot_multiplier = VALUES(jackpot_multiplier),
        min_bet = VALUES(min_bet),
        max_bet = VALUES(max_bet),
        house_edge = VALUES(house_edge)
    ");
    $stmt->execute();
    echo "✅ Casino settings configured<br>";
    
    // Create business_casino_participation table if needed
    $create_participation = "
        CREATE TABLE IF NOT EXISTS business_casino_participation (
            id INT AUTO_INCREMENT PRIMARY KEY,
            business_id INT NOT NULL,
            casino_enabled TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_business_participation (business_id),
            INDEX idx_business_participation (business_id)
        )
    ";
    $pdo->exec($create_participation);
    echo "✅ Casino participation table ready<br>";
    
    // Enable participation
    $stmt = $pdo->prepare("
        INSERT INTO business_casino_participation (business_id, casino_enabled)
        VALUES (1, 1)
        ON DUPLICATE KEY UPDATE casino_enabled = 1
    ");
    $stmt->execute();
    echo "✅ Casino participation enabled<br>";
    
    echo "<br><h3>✅ Casino setup complete!</h3>";
    echo "<a href='check-tables.php'>Check Tables</a> | ";
    echo "<a href='blackjack-debug.php?location_id=1'>Debug Blackjack</a> | ";
    echo "<a href='blackjack.php?location_id=1'>Try Blackjack</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}
?> 