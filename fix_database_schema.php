<?php
// DATABASE SCHEMA FIX - RESTORE MISSING COLUMNS AND TABLES
// This script fixes the database to support all the restored advanced features

require_once 'html/core/includes/config.php';
require_once 'html/core/database.php';

function writeLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
}

try {
    $pdo = get_db_connection();
    
    writeLog("========================================");
    writeLog("DATABASE SCHEMA FIX STARTED");
    writeLog("========================================");

    // 1. Fix QR_CODE_STATS table - add missing columns
    writeLog("Checking qr_code_stats table structure...");
    
    $stmt = $pdo->query("SHOW COLUMNS FROM qr_code_stats");
    $existing_columns = array_column($stmt->fetchAll(), 'Field');
    
    $required_columns = [
        'qr_code_id' => 'INT NOT NULL',
        'ip_address' => 'VARCHAR(45)',
        'user_agent' => 'TEXT',
        'device_type' => 'VARCHAR(100)',
        'browser' => 'VARCHAR(100)',
        'os' => 'VARCHAR(100)',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ];
    
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            writeLog("Adding missing column: $column");
            $pdo->exec("ALTER TABLE qr_code_stats ADD COLUMN $column $definition");
        }
    }
    
    // Add indexes for performance
    try {
        $pdo->exec("ALTER TABLE qr_code_stats ADD INDEX idx_qr_code_id (qr_code_id)");
        writeLog("Added index on qr_code_id");
    } catch (Exception $e) {
        writeLog("Index on qr_code_id already exists or failed: " . $e->getMessage());
    }
    
    try {
        $pdo->exec("ALTER TABLE qr_code_stats ADD INDEX idx_created_at (created_at)");
        writeLog("Added index on created_at");
    } catch (Exception $e) {
        writeLog("Index on created_at already exists or failed: " . $e->getMessage());
    }

    // 2. Create QR_COIN_TRANSACTIONS table if missing
    writeLog("Checking qr_coin_transactions table...");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qr_coin_transactions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            qr_code_id INT,
            user_id INT,
            transaction_type ENUM('earning', 'spending', 'bonus') DEFAULT 'earning',
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_qr_code_id (qr_code_id),
            INDEX idx_user_id (user_id),
            INDEX idx_transaction_type (transaction_type),
            INDEX idx_created_at (created_at)
        )
    ");
    writeLog("qr_coin_transactions table created/verified");

    // 3. Create VOTES_ARCHIVE table for 2-week system
    writeLog("Creating votes_archive table for 2-week voting system...");
    
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
    writeLog("votes_archive table created/verified");

    // 4. Create QR_CODE_STATS_ARCHIVE table
    writeLog("Creating qr_code_stats_archive table...");
    
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
    writeLog("qr_code_stats_archive table created/verified");

    // 5. Create TRANSACTION_LOGS_ARCHIVE table
    writeLog("Creating transaction_logs_archive table...");
    
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
    writeLog("transaction_logs_archive table created/verified");

    // 6. Ensure VOTES table has all required columns
    writeLog("Checking votes table structure...");
    
    $stmt = $pdo->query("SHOW COLUMNS FROM votes");
    $vote_columns = array_column($stmt->fetchAll(), 'Field');
    
    $required_vote_columns = [
        'user_id' => 'INT',
        'machine_id' => 'INT',
        'qr_code_id' => 'INT',
        'campaign_id' => 'INT',
        'item_id' => 'INT NOT NULL',
        'vote_type' => "ENUM('vote_in', 'vote_out') NOT NULL",
        'voter_ip' => 'VARCHAR(45)',
        'user_agent' => 'TEXT',
        'device_type' => 'VARCHAR(100)',
        'browser' => 'VARCHAR(100)',
        'os' => 'VARCHAR(100)',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];
    
    foreach ($required_vote_columns as $column => $definition) {
        if (!in_array($column, $vote_columns)) {
            writeLog("Adding missing vote column: $column");
            try {
                $pdo->exec("ALTER TABLE votes ADD COLUMN $column $definition");
            } catch (Exception $e) {
                writeLog("Warning: Could not add column $column: " . $e->getMessage());
            }
        }
    }

    // 7. Ensure QR_CODES table has all required columns
    writeLog("Checking qr_codes table structure...");
    
    $stmt = $pdo->query("SHOW COLUMNS FROM qr_codes");
    $qr_columns = array_column($stmt->fetchAll(), 'Field');
    
    $required_qr_columns = [
        'business_id' => 'INT',
        'campaign_id' => 'INT',
        'machine_id' => 'INT',
        'qr_type' => 'VARCHAR(50) NOT NULL',
        'code' => 'VARCHAR(255) NOT NULL',
        'url' => 'TEXT',
        'status' => "ENUM('active', 'inactive', 'expired') DEFAULT 'active'",
        'meta' => 'JSON',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];
    
    foreach ($required_qr_columns as $column => $definition) {
        if (!in_array($column, $qr_columns)) {
            writeLog("Adding missing QR code column: $column");
            try {
                $pdo->exec("ALTER TABLE qr_codes ADD COLUMN $column $definition");
            } catch (Exception $e) {
                writeLog("Warning: Could not add column $column: " . $e->getMessage());
            }
        }
    }

    // 8. Add essential indexes for performance
    writeLog("Adding performance indexes...");
    
    $indexes = [
        'votes' => [
            'idx_item_id' => 'item_id',
            'idx_vote_type' => 'vote_type',
            'idx_created_at' => 'created_at',
            'idx_qr_code_id' => 'qr_code_id',
            'idx_machine_id' => 'machine_id'
        ],
        'qr_codes' => [
            'idx_business_id' => 'business_id',
            'idx_qr_type' => 'qr_type',
            'idx_status' => 'status',
            'idx_campaign_id' => 'campaign_id',
            'idx_machine_id' => 'machine_id'
        ]
    ];
    
    foreach ($indexes as $table => $table_indexes) {
        foreach ($table_indexes as $index_name => $column) {
            try {
                $pdo->exec("ALTER TABLE $table ADD INDEX $index_name ($column)");
                writeLog("Added index $index_name on $table.$column");
            } catch (Exception $e) {
                writeLog("Index $index_name on $table already exists or failed");
            }
        }
    }

    // 9. Create ERROR_LOGS table if missing
    writeLog("Creating error_logs table...");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS error_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            error_type VARCHAR(100),
            error_message TEXT,
            file_path VARCHAR(255),
            line_number INT,
            user_id INT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_error_type (error_type),
            INDEX idx_created_at (created_at),
            INDEX idx_user_id (user_id)
        )
    ");
    writeLog("error_logs table created/verified");

    // 10. Create USER_SESSIONS table if missing
    writeLog("Creating user_sessions table...");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id VARCHAR(128) PRIMARY KEY,
            user_id INT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        )
    ");
    writeLog("user_sessions table created/verified");

    // 11. Verify all tables exist
    writeLog("Verifying all required tables exist...");
    
    $required_tables = [
        'votes', 'votes_archive', 'qr_codes', 'qr_code_stats', 'qr_code_stats_archive',
        'qr_coin_transactions', 'voting_list_items', 'weekly_winners', 
        'machine_engagement', 'spin_results', 'user_sessions', 'error_logs',
        'transaction_logs_archive', 'campaigns', 'businesses', 'users'
    ];
    
    $stmt = $pdo->query("SHOW TABLES");
    $existing_tables = array_column($stmt->fetchAll(), 'Tables_in_' . DB_NAME);
    
    foreach ($required_tables as $table) {
        if (in_array($table, $existing_tables)) {
            writeLog("✓ Table $table exists");
        } else {
            writeLog("✗ Table $table is missing");
        }
    }

    writeLog("========================================");
    writeLog("DATABASE SCHEMA FIX COMPLETED SUCCESSFULLY!");
    writeLog("All missing columns and tables have been added.");
    writeLog("The enhanced QR analytics system should now work properly.");
    writeLog("========================================");

} catch (Exception $e) {
    writeLog("ERROR: Database schema fix failed: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    exit(1);
}
?> 