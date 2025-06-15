<?php
/**
 * Cron job to clear vote tables
 * This script should be run periodically to clean up old votes
 */

// Include database configuration
require_once 'html/core/config/database.php';

try {
    // Clear old votes from the database
    $stmt = $pdo->prepare("DELETE FROM votes WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    
    echo "Vote tables cleared successfully at " . date('Y-m-d H:i:s') . "\n";
    
} catch (PDOException $e) {
    echo "Error clearing vote tables: " . $e->getMessage() . "\n";
}

// Log the cron job execution
$log_message = "Clear vote tables cron executed at " . date('Y-m-d H:i:s') . "\n";
file_put_contents('html/logs/cron.log', $log_message, FILE_APPEND | LOCK_EX);
?> 