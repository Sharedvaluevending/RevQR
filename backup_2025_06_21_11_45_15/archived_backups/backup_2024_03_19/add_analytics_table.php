<?php
require_once __DIR__ . '/html/core/config.php';

try {
    // Create analytics table
    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        machine_id INT NOT NULL,
        voter_ip VARCHAR(45) NOT NULL,
        hour_of_day TINYINT NOT NULL,
        day_of_week TINYINT NOT NULL,
        user_agent VARCHAR(255) NULL,
        device_type VARCHAR(50) NULL,
        browser VARCHAR(50) NULL,
        os VARCHAR(50) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_analytics_machine (machine_id),
        INDEX idx_analytics_time (hour_of_day, day_of_week),
        FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Add analytics columns to votes table
    $columns = ['user_agent', 'device_type', 'browser', 'os'];
    foreach ($columns as $column) {
        try {
            $pdo->exec("ALTER TABLE votes ADD COLUMN $column VARCHAR(255) NULL");
        } catch (PDOException $e) {
            // Column might already exist, continue
            continue;
        }
    }

    // Create trigger to populate analytics
    $pdo->exec("DROP TRIGGER IF EXISTS trg_vote_analytics");
    $pdo->exec("CREATE TRIGGER trg_vote_analytics
        AFTER INSERT ON votes
        FOR EACH ROW
        BEGIN
            INSERT INTO analytics (
                machine_id,
                voter_ip,
                hour_of_day,
                day_of_week,
                user_agent,
                device_type,
                browser,
                os
            )
            VALUES (
                NEW.machine_id,
                NEW.voter_ip,
                HOUR(NEW.created_at),
                DAYOFWEEK(NEW.created_at),
                NEW.user_agent,
                NEW.device_type,
                NEW.browser,
                NEW.os
            );
        END");

    echo "Analytics table and trigger created successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 