<?php
/**
 * 🚨 RESTORE: Clear Vote Tables Cron Job - "2 Vote a Week" System
 * 
 * This cron runs every 2 weeks to clear old vote data and reset the voting system
 * Add to crontab: 0 0 */14 * * /usr/bin/php /var/www/clear_vote_tables_cron.php
 */

require_once __DIR__ . '/html/core/config.php';

// Set error reporting for cron job
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/html/logs/vote_clearing.log');

$log_file = __DIR__ . '/html/logs/vote_clearing.log';

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    writeLog("🚨 VOTE TABLE CLEARING - 2 WEEK RESET STARTED");
    
    // Archive votes older than 2 weeks to preserve data
    writeLog("Creating votes_archive table if not exists...");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS votes_archive (
            id INT PRIMARY KEY AUTO_INCREMENT,
            original_vote_id INT,
            user_id INT,
            machine_id INT,
            qr_code_id INT,
            campaign_id INT,
            item_id INT,
            vote_type ENUM('vote_in', 'vote_out'),
            voter_ip VARCHAR(45),
            user_agent TEXT,
            device_type VARCHAR(100),
            browser VARCHAR(100),
            os VARCHAR(100),
            original_created_at TIMESTAMP,
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_archived_date (original_created_at),
            INDEX idx_archived_item (item_id),
            INDEX idx_archived_user (user_id)
        )
    ");
    
    // Archive old votes (older than 2 weeks)
    $stmt = $pdo->prepare("
        INSERT INTO votes_archive (
            original_vote_id, user_id, machine_id, qr_code_id, campaign_id, 
            item_id, vote_type, voter_ip, user_agent, device_type, browser, os, 
            original_created_at
        )
        SELECT 
            id, user_id, machine_id, qr_code_id, campaign_id, 
            item_id, vote_type, voter_ip, user_agent, device_type, browser, os,
            created_at
        FROM votes 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 WEEK)
    ");
    $stmt->execute();
    $archived_count = $stmt->rowCount();
    writeLog("Archived $archived_count votes older than 2 weeks");
    
    // Clear old votes to reset the system
    $stmt = $pdo->prepare("DELETE FROM votes WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 WEEK)");
    $stmt->execute();
    $cleared_count = $stmt->rowCount();
    writeLog("Cleared $cleared_count old votes from main table");
    
    // Reset weekly winners (keep only last 4 weeks)
    $stmt = $pdo->prepare("DELETE FROM weekly_winners WHERE created_at < DATE_SUB(NOW(), INTERVAL 4 WEEK)");
    $stmt->execute();
    $winners_cleared = $stmt->rowCount();
    writeLog("Cleared $winners_cleared old winner records");
    
    // Clear old machine engagement data
    $stmt = $pdo->prepare("DELETE FROM machine_engagement WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 WEEK)");
    $stmt->execute();
    $engagement_cleared = $stmt->rowCount();
    writeLog("Cleared $engagement_cleared old engagement records");
    
    // Clear old spin results  
    $stmt = $pdo->prepare("DELETE FROM spin_results WHERE spin_time < DATE_SUB(NOW(), INTERVAL 2 WEEK)");
    $stmt->execute();
    $spins_cleared = $stmt->rowCount();
    writeLog("Cleared $spins_cleared old spin results");
    
    // Clear old QR error logs
    $stmt = $pdo->prepare("DELETE FROM qr_error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)");
    $stmt->execute();
    $errors_cleared = $stmt->rowCount();
    writeLog("Cleared $errors_cleared old error logs");
    
    // Optimize tables after clearing
    writeLog("Optimizing tables after cleanup...");
    $pdo->exec("OPTIMIZE TABLE votes");
    $pdo->exec("OPTIMIZE TABLE weekly_winners");
    $pdo->exec("OPTIMIZE TABLE machine_engagement");
    $pdo->exec("OPTIMIZE TABLE spin_results");
    writeLog("Table optimization completed");
    
    // Update system setting to track last cleanup
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at) 
        VALUES ('last_vote_cleanup', NOW(), NOW())
        ON DUPLICATE KEY UPDATE setting_value = NOW(), updated_at = NOW()
    ");
    $stmt->execute();
    
    writeLog("✅ VOTE TABLE CLEARING COMPLETED SUCCESSFULLY");
    writeLog("Summary: Archived $archived_count, Cleared $cleared_count votes, $winners_cleared winners, $engagement_cleared engagements");
    
} catch (Exception $e) {
    writeLog("❌ ERROR: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    
    // Send error notification if configured
    if (defined('ADMIN_EMAIL')) {
        mail(ADMIN_EMAIL, 'Vote Clearing Cron Error', "Error in vote clearing cron: " . $e->getMessage());
    }
}

writeLog("Vote clearing cron completed at " . date('Y-m-d H:i:s'));
?> 