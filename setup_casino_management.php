<?php
/**
 * Setup Casino Management Tables
 * Creates necessary tables for casino administration
 */

require_once __DIR__ . '/html/core/config.php';

echo "🎰 SETTING UP CASINO MANAGEMENT SYSTEM\n";
echo "======================================\n\n";

try {
    // Global Casino Settings Table
    echo "Creating casino_global_settings table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS casino_global_settings (
            id INT PRIMARY KEY DEFAULT 1,
            global_jackpot_min INT DEFAULT 1000 COMMENT 'Minimum jackpot amount across all businesses',
            global_house_edge DECIMAL(5,2) DEFAULT 5.00 COMMENT 'Target platform profit margin percentage',
            max_bet_limit INT DEFAULT 100 COMMENT 'Maximum bet amount allowed',
            min_bet_limit INT DEFAULT 1 COMMENT 'Minimum bet amount allowed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ casino_global_settings table created\n";

    // Prize Templates Table
    echo "Creating casino_prize_templates table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS casino_prize_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            prize_name VARCHAR(255) NOT NULL COMMENT 'Display name for the prize',
            prize_type ENUM('qr_coins', 'multiplier', 'jackpot') NOT NULL COMMENT 'Type of prize',
            prize_value INT NOT NULL COMMENT 'Base value of the prize',
            win_probability DECIMAL(5,2) NOT NULL COMMENT 'Percentage chance of winning this prize',
            multiplier_min INT DEFAULT 1 COMMENT 'Minimum multiplier for dynamic prizes',
            multiplier_max INT DEFAULT 10 COMMENT 'Maximum multiplier for dynamic prizes',
            is_jackpot TINYINT(1) DEFAULT 0 COMMENT 'Whether this is a jackpot prize',
            is_active TINYINT(1) DEFAULT 1 COMMENT 'Whether this template is active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_prize_type (prize_type),
            INDEX idx_active (is_active),
            INDEX idx_jackpot (is_jackpot)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ casino_prize_templates table created\n";

    // Analytics Summary Table
    echo "Creating casino_analytics_summary table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS casino_analytics_summary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            business_id INT NULL COMMENT 'NULL for global stats',
            date_period DATE NOT NULL COMMENT 'Date for this summary',
            period_type ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'daily',
            total_plays INT DEFAULT 0,
            total_bets INT DEFAULT 0 COMMENT 'Total QR coins bet',
            total_winnings INT DEFAULT 0 COMMENT 'Total QR coins won',
            unique_players INT DEFAULT 0,
            jackpot_wins INT DEFAULT 0,
            house_edge DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Calculated house edge percentage',
            avg_bet DECIMAL(10,2) DEFAULT 0.00,
            avg_session_length INT DEFAULT 0 COMMENT 'Average session length in minutes',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_date_period (date_period),
            INDEX idx_period_type (period_type),
            INDEX idx_business_date (business_id, date_period)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ casino_analytics_summary table created\n";

    // Insert default global settings
    echo "Inserting default global settings...\n";
    $pdo->exec("
        INSERT INTO casino_global_settings (id, global_jackpot_min, global_house_edge, max_bet_limit, min_bet_limit) 
        VALUES (1, 1000, 5.00, 100, 1)
        ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
    ");
    echo "✅ Default global settings inserted\n";

    // Insert default prize templates
    echo "Inserting default prize templates...\n";
    $stmt = $pdo->prepare("
        INSERT INTO casino_prize_templates (prize_name, prize_type, prize_value, win_probability, multiplier_min, multiplier_max, is_jackpot) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
    ");
    
    $default_prizes = [
        ['Small Win', 'multiplier', 2, 25.00, 2, 3, 0],
        ['Medium Win', 'multiplier', 5, 15.00, 4, 6, 0],
        ['Big Win', 'multiplier', 10, 8.00, 7, 12, 0],
        ['Rare Combo', 'multiplier', 15, 4.00, 10, 20, 0],
        ['Epic Win', 'multiplier', 25, 2.00, 15, 30, 0],
        ['Legendary Jackpot', 'jackpot', 100, 0.50, 50, 100, 1],
        ['Mythical Jackpot', 'jackpot', 200, 0.10, 100, 200, 1]
    ];
    
    foreach ($default_prizes as $prize) {
        $stmt->execute($prize);
    }
    echo "✅ Default prize templates inserted\n";

    echo "\n🎉 CASINO MANAGEMENT SYSTEM SETUP COMPLETE!\n";
    echo "Admin can now access:\n";
    echo "- Casino Management Dashboard at /admin/casino-management.php\n";
    echo "- Global settings configuration\n";
    echo "- Prize template management\n";
    echo "- Business casino analytics\n";
    echo "- Win rate monitoring\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 