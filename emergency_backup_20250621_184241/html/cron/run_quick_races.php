<?php
/**
 * Quick Race Cron Job
 * Runs every minute to check and simulate active races
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../horse-racing/race_simulator.php';

// Log output
$log_file = __DIR__ . '/../logs/quick_races.log';

try {
    $log_entry = date('Y-m-d H:i:s') . " - Starting quick race check\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Create simulator and check races
    $simulator = new QuickRaceSimulator($pdo);
    $simulator->checkAndSimulateActiveRaces();
    
    $log_entry = date('Y-m-d H:i:s') . " - Quick race check completed\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
} catch (Exception $e) {
    $error_entry = date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n";
    file_put_contents($log_file, $error_entry, FILE_APPEND | LOCK_EX);
    echo "Error: " . $e->getMessage() . "\n";
}
?> 