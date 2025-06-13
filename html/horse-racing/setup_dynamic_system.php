<?php
/**
 * Dynamic Horse Racing System Setup
 * This script initializes the complete dynamic horse racing system
 */

require_once '../core/config.php';
require_once 'dynamic_horses.php';
require_once 'enhanced_race_engine.php';

class DynamicHorseSetup {
    private $pdo;
    private $horseSystem;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->horseSystem = new DynamicHorseSystem($pdo);
    }
    
    public function runCompleteSetup() {
        echo "<h2>🐎 Dynamic Horse Racing System Setup</h2>\n";
        
        try {
            // Step 1: Create all necessary tables
            echo "<h3>Step 1: Database Setup</h3>\n";
            $this->setupDatabase();
            
            // Step 2: Initialize horse system
            echo "<h3>Step 2: Initialize Horse System</h3>\n";
            $this->initializeHorseSystem();
            
            // Step 3: Create race engine tables
            echo "<h3>Step 3: Setup Race Engine</h3>\n";
            $this->setupRaceEngine();
            
            // Step 4: Setup cron job
            echo "<h3>Step 4: Setup Automation</h3>\n";
            $this->setupCronJob();
            
            // Step 5: Create assets directory
            echo "<h3>Step 5: Setup Assets</h3>\n";
            $this->setupAssets();
            
            // Step 6: Test system
            echo "<h3>Step 6: System Test</h3>\n";
            $this->testSystem();
            
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>✅ Setup Complete!</h3>";
            echo "<p>The Dynamic Horse Racing System has been successfully installed and configured.</p>";
            echo "<p><strong>Next Steps:</strong></p>";
            echo "<ul>";
            echo "<li>Visit <a href='enhanced_quick_races.php'>Enhanced Quick Races</a> to see the new interface</li>";
            echo "<li>Check the cron job is running: <code>crontab -l</code></li>";
            echo "<li>Monitor logs in <code>../logs/horse_racing.log</code></li>";
            echo "</ul>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>❌ Setup Failed</h3>";
            echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "</div>";
        }
    }
    
    private function setupDatabase() {
        // The main schema was already created, now add any additional tables
        
        // Create logs table for horse racing
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS horse_racing_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                log_level ENUM('INFO', 'WARNING', 'ERROR', 'DEBUG') DEFAULT 'INFO',
                message TEXT NOT NULL,
                context JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_log_level (log_level),
                INDEX idx_created_at (created_at)
            )
        ");
        
        echo "✅ Database tables verified/created<br>\n";
    }
    
    private function initializeHorseSystem() {
        // Initialize the horse system
        $horses = $this->horseSystem->getAllHorsesWithStats();
        echo "✅ Horse system initialized with " . count($horses) . " horses<br>\n";
        
        // Display horse information
        echo "<div style='background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>Horses loaded:</strong><br>";
        foreach ($horses as $horse) {
            echo "• {$horse['name']} ({$horse['nickname']}) - {$horse['personality']}<br>";
        }
        echo "</div>";
    }
    
    private function setupRaceEngine() {
        // Create enhanced race tables
        if (create_enhanced_race_tables($this->pdo)) {
            echo "✅ Enhanced race engine tables created<br>\n";
        } else {
            throw new Exception("Failed to create race engine tables");
        }
    }
    
    private function setupCronJob() {
        $cronCommand = "*/5 * * * * /usr/bin/php " . __DIR__ . "/cron_update_horses.php >> " . __DIR__ . "/../logs/horse_cron.log 2>&1";
        
        // Create the cron update script
        $cronScript = '<?php
/**
 * Cron job to update horse performance and fatigue
 * Runs every 5 minutes
 */

require_once __DIR__ . "/../core/config.php";
require_once __DIR__ . "/dynamic_horses.php";

try {
    $horseSystem = new DynamicHorseSystem($pdo);
    
    // Update fatigue recovery (horses recover 1 fatigue point every 5 minutes)
    $horseSystem->updateFatigueRecovery();
    
    // Log the update
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents(__DIR__ . "/../logs/horse_cron.log", "[$timestamp] Horse fatigue updated successfully\n", FILE_APPEND);
    
} catch (Exception $e) {
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents(__DIR__ . "/../logs/horse_cron.log", "[$timestamp] ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>';
        
        file_put_contents(__DIR__ . '/cron_update_horses.php', $cronScript);
        chmod(__DIR__ . '/cron_update_horses.php', 0755);
        
        echo "✅ Cron job script created<br>\n";
        echo "<div style='background: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>⚠️ Manual Step Required:</strong><br>";
        echo "Add this line to your crontab (run <code>crontab -e</code>):<br>";
        echo "<code>$cronCommand</code>";
        echo "</div>";
    }
    
    private function setupAssets() {
        $assetsDir = __DIR__ . '/assets';
        $imgDir = $assetsDir . '/img';
        $horseDir = $imgDir . '/horses';
        $jockeyDir = $imgDir . '/jockeys';
        
        // Create directories
        if (!file_exists($assetsDir)) mkdir($assetsDir, 0755, true);
        if (!file_exists($imgDir)) mkdir($imgDir, 0755, true);
        if (!file_exists($horseDir)) mkdir($horseDir, 0755, true);
        if (!file_exists($jockeyDir)) mkdir($jockeyDir, 0755, true);
        
        // Create placeholder images info file
        $placeholderInfo = "# Horse Racing Assets

## Required Images:

### Horse Images (300x200px recommended):
- thunder.png - Thunderbolt McGillicuddy
- gallops.png - Sir Gallops-a-Lot  
- buttercup.png - Buttercup Bonanza
- disco.png - Disco Inferno Dan
- princess.png - Princess Prancealot
- muddy.png - Mudslinger Murphy
- rocket.png - Rocket Fuel Rodriguez
- zen.png - Zen Master Zippy
- charlie.png - Caffeine Crash Charlie
- lucky.png - Lucky Charm Louie

### Jockey Images (150x150px recommended):
- jockey-1.png through jockey-10.png

## Fallback:
If images are not found, the system will use placeholder colors and text.
";
        
        file_put_contents($assetsDir . '/README.md', $placeholderInfo);
        
        echo "✅ Assets directory structure created<br>\n";
    }
    
    private function testSystem() {
        // Test horse system
        $horses = $this->horseSystem->getAllHorsesWithStats();
        if (count($horses) !== 10) {
            throw new Exception("Expected 10 horses, got " . count($horses));
        }
        
        // Test race simulation
        $raceConditions = [
            'weather' => 'sunny',
            'track' => 'fast',
            'time_of_day' => 'afternoon'
        ];
        
        $raceResults = $this->horseSystem->simulateRace($horses, $raceConditions);
        if (count($raceResults) !== 10) {
            throw new Exception("Race simulation failed");
        }
        
        echo "✅ System test passed - all components working<br>\n";
    }
    
    private function logMessage($level, $message, $context = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO horse_racing_logs (log_level, message, context) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$level, $message, $context ? json_encode($context) : null]);
    }
}

// Run setup if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'setup_dynamic_system.php') {
    echo "<!DOCTYPE html>
<html>
<head>
    <title>Dynamic Horse Racing Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h3 { color: #666; margin-top: 30px; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>";
    
    $setup = new DynamicHorseSetup($pdo);
    $setup->runCompleteSetup();
    
    echo "</body></html>";
}
?> 