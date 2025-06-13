<?php
/**
 * Weekly Reset Cron Job
 * 
 * This script should run every Sunday at 11:59 PM to:
 * 1. Archive last week's winners
 * 2. Reset weekly vote counts and IP tracking
 * 3. Clean up old engagement data
 * 
 * Add to crontab: 59 23 * * 0 /usr/bin/php /var/www/weekly_reset_cron.php
 */

require_once __DIR__ . '/html/core/config.php';

// Set error reporting for cron job
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/html/logs/weekly_reset.log');

$log_file = __DIR__ . '/html/logs/weekly_reset.log';

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    writeLog("Starting weekly reset process...");
    
    // Create weekly_winners table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS weekly_winners (
            id INT PRIMARY KEY AUTO_INCREMENT,
            voting_list_id INT NOT NULL,
            week_year VARCHAR(7) NOT NULL,
            winner_type ENUM('vote_in', 'vote_out') NOT NULL,
            item_id INT NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            vote_count INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_winner (voting_list_id, week_year, winner_type),
            INDEX idx_week_year (week_year),
            INDEX idx_voting_list (voting_list_id)
        )
    ");
    
    writeLog("Weekly winners table ensured to exist");
    
    // Get all voting lists to process
    $stmt = $pdo->prepare("SELECT DISTINCT id FROM voting_lists");
    $stmt->execute();
    $voting_lists = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    writeLog("Found " . count($voting_lists) . " voting lists to process");
    
    // Get last week's year-week identifier
    $last_week = date('Y-W', strtotime('last week'));
    writeLog("Processing data for week: $last_week");
    
    // Calculate GLOBAL winners across ALL voting lists (only ONE winner per category per week)
    writeLog("Calculating global winners across all voting lists");
    
    // Find the single "vote in" winner with most votes across ALL lists
    $stmt = $pdo->prepare("
        SELECT 
            v.item_id,
            vli.item_name,
            vli.voting_list_id,
            COUNT(*) as vote_count
        FROM votes v
        INNER JOIN voting_list_items vli ON v.item_id = vli.id
        WHERE YEARWEEK(v.created_at, 1) = YEARWEEK(DATE_SUB(NOW(), INTERVAL 1 WEEK), 1)
        AND v.vote_type = 'vote_in'
        GROUP BY v.item_id, vli.item_name, vli.voting_list_id
        ORDER BY vote_count DESC
        LIMIT 1
    ");
    $stmt->execute();
    $global_vote_in_winner = $stmt->fetch();
    
    if ($global_vote_in_winner) {
        // Clear any existing vote_in winners for this week
        $stmt = $pdo->prepare("DELETE FROM weekly_winners WHERE week_year = ? AND winner_type = 'vote_in'");
        $stmt->execute([$last_week]);
        
        // Insert the single global vote_in winner
        $stmt = $pdo->prepare("
            INSERT INTO weekly_winners 
            (voting_list_id, week_year, winner_type, item_id, item_name, vote_count)
            VALUES (?, ?, 'vote_in', ?, ?, ?)
        ");
        $stmt->execute([
            $global_vote_in_winner['voting_list_id'], 
            $last_week, 
            $global_vote_in_winner['item_id'], 
            $global_vote_in_winner['item_name'], 
            $global_vote_in_winner['vote_count']
        ]);
        
        writeLog("GLOBAL vote_in winner: {$global_vote_in_winner['item_name']} with {$global_vote_in_winner['vote_count']} votes (from list {$global_vote_in_winner['voting_list_id']})");
    }
    
    // Find the single "vote out" winner with most votes across ALL lists
    $stmt = $pdo->prepare("
        SELECT 
            v.item_id,
            vli.item_name,
            vli.voting_list_id,
            COUNT(*) as vote_count
        FROM votes v
        INNER JOIN voting_list_items vli ON v.item_id = vli.id
        WHERE YEARWEEK(v.created_at, 1) = YEARWEEK(DATE_SUB(NOW(), INTERVAL 1 WEEK), 1)
        AND v.vote_type = 'vote_out'
        GROUP BY v.item_id, vli.item_name, vli.voting_list_id
        ORDER BY vote_count DESC
        LIMIT 1
    ");
    $stmt->execute();
    $global_vote_out_winner = $stmt->fetch();
    
    if ($global_vote_out_winner) {
        // Clear any existing vote_out winners for this week
        $stmt = $pdo->prepare("DELETE FROM weekly_winners WHERE week_year = ? AND winner_type = 'vote_out'");
        $stmt->execute([$last_week]);
        
        // Insert the single global vote_out winner
        $stmt = $pdo->prepare("
            INSERT INTO weekly_winners 
            (voting_list_id, week_year, winner_type, item_id, item_name, vote_count)
            VALUES (?, ?, 'vote_out', ?, ?, ?)
        ");
        $stmt->execute([
            $global_vote_out_winner['voting_list_id'], 
            $last_week, 
            $global_vote_out_winner['item_id'], 
            $global_vote_out_winner['item_name'], 
            $global_vote_out_winner['vote_count']
        ]);
        
        writeLog("GLOBAL vote_out winner: {$global_vote_out_winner['item_name']} with {$global_vote_out_winner['vote_count']} votes (from list {$global_vote_out_winner['voting_list_id']})");
    }
    
    // Archive old votes (older than 8 weeks) to reduce table size
    // TODO: Fix archiving process - temporarily disabled due to column constraint issues
    /*
    $stmt = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS votes_archive (
            id INT PRIMARY KEY AUTO_INCREMENT,
            machine_id INT,
            qr_code_id INT,
            campaign_id INT,
            item_id INT,
            vote_type ENUM('vote_in', 'vote_out'),
            voter_ip VARCHAR(45),
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            user_agent VARCHAR(255),
            device_type VARCHAR(255),
            browser VARCHAR(255),
            os VARCHAR(255),
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_archived_created (created_at),
            INDEX idx_archived_item (item_id)
        )
    ");
    $stmt->execute();
    
    // Move old votes to archive
    $stmt = $pdo->prepare("
        INSERT INTO votes_archive (machine_id, qr_code_id, campaign_id, item_id, vote_type, voter_ip, created_at, updated_at, user_agent, device_type, browser, os)
        SELECT machine_id, qr_code_id, campaign_id, item_id, vote_type, voter_ip, created_at, updated_at, user_agent, device_type, browser, os
        FROM votes 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 8 WEEK)
    ");
    $archived_count = $stmt->execute();
    $archived_rows = $stmt->rowCount();
    
    if ($archived_rows > 0) {
        // Temporarily disable foreign key checks to delete archived votes
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $stmt = $pdo->prepare("DELETE FROM votes WHERE created_at < DATE_SUB(NOW(), INTERVAL 8 WEEK)");
        $stmt->execute();
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        writeLog("Archived and deleted $archived_rows old votes (older than 8 weeks)");
    }
    */
    
    writeLog("Vote archiving temporarily disabled - weekly winners and cleanup still processed");
    
    // Clean up old machine engagement data (older than 4 weeks)
    $stmt = $pdo->prepare("DELETE FROM machine_engagement WHERE created_at < DATE_SUB(NOW(), INTERVAL 4 WEEK)");
    $stmt->execute();
    $cleaned_engagement = $stmt->rowCount();
    writeLog("Cleaned up $cleaned_engagement old machine engagement records");
    
    // Clean up old spin results (older than 4 weeks)
    $stmt = $pdo->prepare("DELETE FROM spin_results WHERE spin_time < DATE_SUB(NOW(), INTERVAL 4 WEEK)");
    $stmt->execute();
    $cleaned_spins = $stmt->rowCount();
    writeLog("Cleaned up $cleaned_spins old spin results");
    
    // Optimize tables for better performance
    $tables_to_optimize = ['votes', 'weekly_winners', 'voting_list_items', 'machine_engagement'];
    foreach ($tables_to_optimize as $table) {
        try {
            $pdo->exec("OPTIMIZE TABLE $table");
            writeLog("Optimized table: $table");
        } catch (Exception $e) {
            writeLog("Warning: Could not optimize table $table: " . $e->getMessage());
        }
    }
    
    writeLog("Weekly reset process completed successfully!");
    writeLog("========================================");
    
} catch (Exception $e) {
    writeLog("ERROR: Weekly reset failed: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    
    // Send email notification if configured
    if (defined('ADMIN_EMAIL') && ADMIN_EMAIL) {
        $subject = "Weekly Reset Cron Job Failed";
        $message = "The weekly reset cron job failed with error:\n\n" . $e->getMessage() . "\n\nCheck the log file for more details.";
        mail(ADMIN_EMAIL, $subject, $message);
    }
    
    exit(1);
}
?> 