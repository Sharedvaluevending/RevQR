<?php
require_once __DIR__ . '/html/core/config.php';

try {
    // Add campaign_id column to votes table
    $pdo->exec("ALTER TABLE votes ADD COLUMN campaign_id INT NOT NULL AFTER machine_id");

    echo "Campaign ID column added to votes table successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 