<?php
/**
 * Spinwheel Database Setup Script
 * Run this script to set up the required database tables for the spinwheel system
 */

require_once __DIR__ . '/html/core/config.php';

echo "Setting up Spinwheel Database Tables...\n";

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Create spin_results table
    echo "Creating spin_results table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `spin_results` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_ip` VARCHAR(45) NOT NULL,
            `prize_won` VARCHAR(255) NOT NULL,
            `is_big_win` BOOLEAN DEFAULT FALSE,
            `business_id` INT NULL,
            `machine_id` INT NULL,
            `spin_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_spin_results_ip` (`user_ip`),
            INDEX `idx_spin_results_business` (`business_id`),
            INDEX `idx_spin_results_machine` (`machine_id`),
            INDEX `idx_spin_results_time` (`spin_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 2. Check if rewards table exists and update schema
    echo "Updating rewards table schema...\n";
    
    // Check existing columns
    $columns = $pdo->query("SHOW COLUMNS FROM rewards")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('name', $columns)) {
        $pdo->exec("ALTER TABLE `rewards` ADD COLUMN `name` VARCHAR(255) NOT NULL DEFAULT 'Unnamed Prize' AFTER `id`");
    }
    
    if (!in_array('rarity_level', $columns)) {
        $pdo->exec("ALTER TABLE `rewards` ADD COLUMN `rarity_level` INT NOT NULL DEFAULT 1 AFTER `description`");
    }
    
    if (!in_array('active', $columns)) {
        $pdo->exec("ALTER TABLE `rewards` ADD COLUMN `active` BOOLEAN DEFAULT TRUE AFTER `rarity_level`");
    }
    
    if (!in_array('image_url', $columns)) {
        $pdo->exec("ALTER TABLE `rewards` ADD COLUMN `image_url` VARCHAR(255) NULL AFTER `active`");
    }
    
    if (!in_array('code', $columns)) {
        $pdo->exec("ALTER TABLE `rewards` ADD COLUMN `code` VARCHAR(100) NULL AFTER `image_url`");
    }
    
    if (!in_array('link', $columns)) {
        $pdo->exec("ALTER TABLE `rewards` ADD COLUMN `link` VARCHAR(255) NULL AFTER `code`");
    }
    
    if (!in_array('list_id', $columns)) {
        $pdo->exec("ALTER TABLE `rewards` ADD COLUMN `list_id` INT NULL AFTER `link`");
    }
    
    if (!in_array('prize_type', $columns)) {
        $pdo->exec("ALTER TABLE `rewards` ADD COLUMN `prize_type` VARCHAR(50) NULL AFTER `list_id`");
    }
    
    // Add indexes if they don't exist
    try {
        $pdo->exec("ALTER TABLE `rewards` ADD INDEX `idx_rewards_active` (`active`)");
    } catch (PDOException $e) {
        // Index might already exist
    }
    
    try {
        $pdo->exec("ALTER TABLE `rewards` ADD INDEX `idx_rewards_rarity` (`rarity_level`)");
    } catch (PDOException $e) {
        // Index might already exist
    }
    
    // 3. Update voting_lists table for spin settings
    echo "Updating voting_lists table for spin settings...\n";
    
    try {
        $listColumns = $pdo->query("SHOW COLUMNS FROM voting_lists")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('spin_enabled', $listColumns)) {
            $pdo->exec("ALTER TABLE `voting_lists` ADD COLUMN `spin_enabled` BOOLEAN DEFAULT FALSE AFTER `description`");
        }
        
        if (!in_array('spin_trigger_count', $listColumns)) {
            $pdo->exec("ALTER TABLE `voting_lists` ADD COLUMN `spin_trigger_count` INT DEFAULT 3 AFTER `spin_enabled`");
        }
    } catch (PDOException $e) {
        echo "Note: voting_lists table not found, skipping spin settings update\n";
    }
    
    // 4. Insert sample rewards if table is empty
    echo "Checking for sample rewards...\n";
    $rewardCount = $pdo->query("SELECT COUNT(*) FROM rewards")->fetchColumn();
    
    if ($rewardCount == 0) {
        echo "Inserting sample rewards...\n";
        
        // Get first business_id and machine_id for sample data
        $business = $pdo->query("SELECT id FROM businesses LIMIT 1")->fetch();
        $machine = $pdo->query("SELECT id FROM machines LIMIT 1")->fetch();
        
        if ($business && $machine) {
            $business_id = $business['id'];
            $machine_id = $machine['id'];
            
            $sampleRewards = [
                ['Free Snack', 'Get any snack for free!', 'discount', 2.50, 3, 'FREESNACK', 'promo'],
                ['BOGO Deal', 'Buy one get one free on drinks', 'promo', 5.00, 5, 'BOGO50', 'promo'],
                ['Company Hoodie', 'Exclusive branded hoodie', 'other', 25.00, 8, 'HOODIE2024', 'physical'],
                ['$5 Credit', 'Five dollar vending credit', 'discount', 5.00, 4, 'CREDIT5', 'discount'],
                ['Try Again', 'Better luck next time!', 'other', 0.00, 1, '', 'nothing']
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO rewards (machine_id, name, reward_type, description, value, rarity_level, active, code, prize_type) 
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
            ");
            
            foreach ($sampleRewards as $reward) {
                $stmt->execute([
                    $machine_id,
                    $reward[0], // name
                    $reward[2], // reward_type
                    $reward[1], // description
                    $reward[3], // value
                    $reward[4], // rarity_level
                    $reward[5], // code
                    $reward[6]  // prize_type
                ]);
            }
            
            echo "Sample rewards inserted successfully!\n";
        } else {
            echo "No businesses or machines found, skipping sample rewards\n";
        }
    }
    
    // 5. Ensure spin settings exist in system_settings
    echo "Checking spin settings...\n";
    
    $spinSettings = [
        'spin_hoodie_probability' => '5',
        'spin_bogo_probability' => '15', 
        'spin_nothing_probability' => '80',
        'spin_cooldown_hours' => '24'
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, value, description) VALUES (?, ?, ?)");
    
    foreach ($spinSettings as $key => $value) {
        $description = match($key) {
            'spin_hoodie_probability' => 'Probability of winning a hoodie in spin wheel (%)',
            'spin_bogo_probability' => 'Probability of winning BOGO in spin wheel (%)',
            'spin_nothing_probability' => 'Probability of winning nothing in spin wheel (%)',
            'spin_cooldown_hours' => 'Hours between spins for same IP'
        };
        
        $stmt->execute([$key, $value, $description]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✅ Spinwheel database setup completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Visit /html/business/spin-wheel.php to manage rewards\n";
    echo "2. Visit /html/user/spin.php to test the user experience\n";
    echo "3. Configure spin probabilities in the business dashboard\n";
    
} catch (PDOException $e) {
    $pdo->rollback();
    echo "\n❌ Error setting up database: " . $e->getMessage() . "\n";
    exit(1);
}
?> 