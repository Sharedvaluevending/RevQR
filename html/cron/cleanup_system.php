<?php
// MASSIVE AUTOMATED CLEANUP SYSTEM - RESTORED FROM LOST WORK
// This system maintains database health and optimizes performance

require_once dirname(__DIR__) . '/core/config/database.php';

// Logging function
function writeCleanupLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] CLEANUP: $message\n";
    $log_file = dirname(__DIR__) . '/logs/cleanup_system.log';
    
    // Ensure log directory exists
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    echo $log_entry;
}

try {
    writeCleanupLog("========================================");
    writeCleanupLog("MASSIVE CLEANUP SYSTEM STARTED");
    writeCleanupLog("========================================");

    // 1. VOTES BACK 2 2 WEEK SYSTEM - Archive old votes
    writeCleanupLog("Starting vote archiving process (2-week system)...");
    
    // Ensure archive table exists with enhanced structure
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
            original_updated_at TIMESTAMP,
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            archive_reason VARCHAR(100) DEFAULT 'automatic_2week_cleanup',
            INDEX idx_archived_date (original_created_at),
            INDEX idx_archived_item (item_id),
            INDEX idx_archived_user (user_id),
            INDEX idx_archive_reason (archive_reason),
            INDEX idx_archived_machine (machine_id),
            INDEX idx_archived_qr (qr_code_id)
        )
    ");
    
    // Archive votes older than 2 weeks
    $stmt = $pdo->prepare("
        INSERT INTO votes_archive (
            original_vote_id, user_id, machine_id, qr_code_id, campaign_id, 
            item_id, vote_type, voter_ip, user_agent, device_type, browser, os, 
            original_created_at, original_updated_at, archive_reason
        )
        SELECT 
            id, user_id, machine_id, qr_code_id, campaign_id, 
            item_id, vote_type, voter_ip, user_agent, device_type, browser, os,
            created_at, updated_at, 'cleanup_2week_automatic'
        FROM votes 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 WEEK)
        AND id NOT IN (SELECT original_vote_id FROM votes_archive WHERE original_vote_id IS NOT NULL)
    ");
    $stmt->execute();
    $archived_votes = $stmt->rowCount();
    writeCleanupLog("Archived $archived_votes votes older than 2 weeks");

    // Delete archived votes from main table
    if ($archived_votes > 0) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $stmt = $pdo->prepare("DELETE FROM votes WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 WEEK)");
        $stmt->execute();
        $deleted_votes = $stmt->rowCount();
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        writeCleanupLog("Deleted $deleted_votes old votes from main table");
    }

    // 2. QR CODE STATS CLEANUP - Keep 30 days of detailed stats
    writeCleanupLog("Cleaning up old QR code statistics...");
    
    // Archive old QR stats
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qr_code_stats_archive (
            id INT PRIMARY KEY AUTO_INCREMENT,
            original_stat_id INT,
            qr_code_id INT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            device_type VARCHAR(100),
            browser VARCHAR(100),
            os VARCHAR(100),
            original_created_at TIMESTAMP,
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_archived_qr (qr_code_id),
            INDEX idx_archived_date (original_created_at)
        )
    ");
    
    $stmt = $pdo->prepare("
        INSERT INTO qr_code_stats_archive (
            original_stat_id, qr_code_id, ip_address, user_agent, 
            device_type, browser, os, original_created_at
        )
        SELECT 
            id, qr_code_id, ip_address, user_agent, 
            device_type, browser, os, created_at
        FROM qr_code_stats 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND id NOT IN (SELECT original_stat_id FROM qr_code_stats_archive WHERE original_stat_id IS NOT NULL)
    ");
    $stmt->execute();
    $archived_stats = $stmt->rowCount();
    writeCleanupLog("Archived $archived_stats QR code stats older than 30 days");

    // Delete old stats
    if ($archived_stats > 0) {
        $stmt = $pdo->prepare("DELETE FROM qr_code_stats WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $deleted_stats = $stmt->rowCount();
        writeCleanupLog("Deleted $deleted_stats old QR code stats");
    }

    // 3. MACHINE ENGAGEMENT CLEANUP - Keep 4 weeks
    writeCleanupLog("Cleaning up old machine engagement data...");
    $stmt = $pdo->prepare("DELETE FROM machine_engagement WHERE created_at < DATE_SUB(NOW(), INTERVAL 4 WEEK)");
    $stmt->execute();
    $cleaned_engagement = $stmt->rowCount();
    writeCleanupLog("Cleaned up $cleaned_engagement old machine engagement records");

    // 4. SPIN RESULTS CLEANUP - Keep 4 weeks
    writeCleanupLog("Cleaning up old spin results...");
    $stmt = $pdo->prepare("DELETE FROM spin_results WHERE spin_time < DATE_SUB(NOW(), INTERVAL 4 WEEK)");
    $stmt->execute();
    $cleaned_spins = $stmt->rowCount();
    writeCleanupLog("Cleaned up $cleaned_spins old spin results");

    // 5. SESSION CLEANUP - Remove expired sessions
    writeCleanupLog("Cleaning up expired sessions...");
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
    $stmt->execute();
    $cleaned_sessions = $stmt->rowCount();
    writeCleanupLog("Cleaned up $cleaned_sessions expired sessions");

    // 6. TRANSACTION LOG CLEANUP - Archive old transactions
    writeCleanupLog("Archiving old transaction logs...");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transaction_logs_archive (
            id INT PRIMARY KEY AUTO_INCREMENT,
            original_transaction_id INT,
            user_id INT,
            transaction_type VARCHAR(50),
            amount DECIMAL(10,2),
            description TEXT,
            original_created_at TIMESTAMP,
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_archived_user (user_id),
            INDEX idx_archived_date (original_created_at)
        )
    ");
    
    $stmt = $pdo->prepare("
        INSERT INTO transaction_logs_archive (
            original_transaction_id, user_id, transaction_type, amount, description, original_created_at
        )
        SELECT 
            id, user_id, transaction_type, amount, description, created_at
        FROM transaction_logs 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
        AND id NOT IN (SELECT original_transaction_id FROM transaction_logs_archive WHERE original_transaction_id IS NOT NULL)
    ");
    $stmt->execute();
    $archived_transactions = $stmt->rowCount();
    writeCleanupLog("Archived $archived_transactions old transaction logs");

    // 7. ERROR LOG CLEANUP - Keep 2 weeks of error logs
    writeCleanupLog("Cleaning up old error logs...");
    $stmt = $pdo->prepare("DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 WEEK)");
    $stmt->execute();
    $cleaned_errors = $stmt->rowCount();
    writeCleanupLog("Cleaned up $cleaned_errors old error logs");

    // 8. MASSIVE TABLE OPTIMIZATION - Optimize all major tables
    writeCleanupLog("Starting table optimization process...");
    $tables_to_optimize = [
        'votes', 'votes_archive', 'qr_codes', 'qr_code_stats', 'qr_code_stats_archive',
        'voting_list_items', 'weekly_winners', 'machine_engagement', 'spin_results',
        'user_sessions', 'transaction_logs', 'transaction_logs_archive', 'campaigns',
        'businesses', 'users', 'qr_coin_transactions'
    ];

    foreach ($tables_to_optimize as $table) {
        try {
            writeCleanupLog("Optimizing table: $table");
            $pdo->exec("OPTIMIZE TABLE $table");
            
            // Also analyze table for better query performance
            $pdo->exec("ANALYZE TABLE $table");
            writeCleanupLog("Analyzed and optimized table: $table");
        } catch (Exception $e) {
            writeCleanupLog("Warning: Could not optimize table $table: " . $e->getMessage());
        }
    }

    // 9. DISK SPACE ANALYSIS
    writeCleanupLog("Analyzing disk space usage...");
    $stmt = $pdo->query("
        SELECT 
            table_name,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'DB Size in MB'
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        ORDER BY (data_length + index_length) DESC
    ");
    $table_sizes = $stmt->fetchAll();
    writeCleanupLog("Database size analysis completed:");
    foreach ($table_sizes as $table) {
        writeCleanupLog("  {$table['table_name']}: {$table['DB Size in MB']} MB");
    }

    // 10. GENERATE CLEANUP REPORT
    $total_records_processed = $archived_votes + $archived_stats + $cleaned_engagement + 
                               $cleaned_spins + $cleaned_sessions + $archived_transactions + $cleaned_errors;
    
    writeCleanupLog("========================================");
    writeCleanupLog("CLEANUP SUMMARY REPORT:");
    writeCleanupLog("  - Votes archived: $archived_votes");
    writeCleanupLog("  - QR stats archived: $archived_stats");
    writeCleanupLog("  - Engagement records cleaned: $cleaned_engagement");
    writeCleanupLog("  - Spin results cleaned: $cleaned_spins");
    writeCleanupLog("  - Sessions cleaned: $cleaned_sessions");
    writeCleanupLog("  - Transactions archived: $archived_transactions");
    writeCleanupLog("  - Error logs cleaned: $cleaned_errors");
    writeCleanupLog("  - Total records processed: $total_records_processed");
    writeCleanupLog("  - Tables optimized: " . count($tables_to_optimize));
    writeCleanupLog("========================================");
    writeCleanupLog("MASSIVE CLEANUP SYSTEM COMPLETED SUCCESSFULLY!");
    writeCleanupLog("========================================");

    // 11. HEALTH CHECK - Verify system integrity
    writeCleanupLog("Performing post-cleanup health check...");
    
    // Check for orphaned records
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM votes WHERE item_id NOT IN (SELECT id FROM voting_list_items)) as orphaned_votes,
            (SELECT COUNT(*) FROM qr_codes WHERE machine_id IS NOT NULL AND machine_id NOT IN (SELECT id FROM voting_lists)) as orphaned_qr_codes,
            (SELECT COUNT(*) FROM weekly_winners WHERE item_id NOT IN (SELECT id FROM voting_list_items)) as orphaned_winners
    ");
    $health_check = $stmt->fetch();
    
    writeCleanupLog("Health check results:");
    writeCleanupLog("  - Orphaned votes: {$health_check['orphaned_votes']}");
    writeCleanupLog("  - Orphaned QR codes: {$health_check['orphaned_qr_codes']}");
    writeCleanupLog("  - Orphaned winners: {$health_check['orphaned_winners']}");
    
    if ($health_check['orphaned_votes'] > 0 || $health_check['orphaned_qr_codes'] > 0 || $health_check['orphaned_winners'] > 0) {
        writeCleanupLog("WARNING: Orphaned records detected - manual review recommended");
    } else {
        writeCleanupLog("✓ System integrity verified - no orphaned records found");
    }

} catch (Exception $e) {
    writeCleanupLog("ERROR: Cleanup system failed: " . $e->getMessage());
    writeCleanupLog("Stack trace: " . $e->getTraceAsString());
    
    // Send email notification if configured
    if (defined('ADMIN_EMAIL') && ADMIN_EMAIL) {
        $subject = "Database Cleanup System Failed";
        $message = "The database cleanup system failed with error:\n\n" . $e->getMessage() . "\n\nCheck the cleanup log for more details.";
        mail(ADMIN_EMAIL, $subject, $message);
    }
    
    exit(1);
}
?> 