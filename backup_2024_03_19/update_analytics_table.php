<?php
require_once __DIR__ . '/html/core/config.php';

try {
    // Drop existing trigger if it exists
    $pdo->exec("DROP TRIGGER IF EXISTS trg_vote_analytics");

    // Drop existing analytics table
    $pdo->exec("DROP TABLE IF EXISTS analytics");

    // Create new analytics table
    $pdo->exec("CREATE TABLE analytics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        machine_id INT NOT NULL,
        campaign_id INT NOT NULL,
        item_id INT NOT NULL,
        voter_ip VARCHAR(45) NOT NULL,
        vote_type ENUM('vote_in','vote_out') NOT NULL,
        hour_of_day TINYINT NOT NULL,
        day_of_week TINYINT NOT NULL,
        user_agent TEXT,
        device_type VARCHAR(50),
        browser VARCHAR(50),
        os VARCHAR(50),
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_analytics_machine (machine_id),
        INDEX idx_analytics_campaign (campaign_id),
        INDEX idx_analytics_item (item_id),
        INDEX idx_analytics_ip (voter_ip),
        INDEX idx_analytics_time (hour_of_day, day_of_week),
        INDEX idx_analytics_device (device_type),
        FOREIGN KEY (machine_id)
            REFERENCES machines(id)
            ON DELETE CASCADE
            ON UPDATE CASCADE,
        FOREIGN KEY (campaign_id)
            REFERENCES qr_campaigns(id)
            ON DELETE CASCADE
            ON UPDATE CASCADE,
        FOREIGN KEY (item_id)
            REFERENCES items(id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Create trigger to populate analytics
    $pdo->exec("CREATE TRIGGER trg_vote_analytics
        AFTER INSERT ON votes
        FOR EACH ROW
        BEGIN
            INSERT INTO analytics (
                machine_id,
                campaign_id,
                item_id,
                voter_ip,
                vote_type,
                hour_of_day,
                day_of_week,
                user_agent,
                device_type,
                browser,
                os
            )
            VALUES (
                NEW.machine_id,
                NEW.campaign_id,
                NEW.item_id,
                NEW.voter_ip,
                NEW.vote_type,
                HOUR(NEW.created_at),
                DAYOFWEEK(NEW.created_at),
                NEW.user_agent,
                NEW.device_type,
                NEW.browser,
                NEW.os
            );
        END");

    echo "Analytics table and trigger updated successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 