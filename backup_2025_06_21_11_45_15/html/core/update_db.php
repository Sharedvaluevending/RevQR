<?php
require_once __DIR__ . '/config.php';

try {
    // Check if column exists
    $result = $pdo->query("SHOW COLUMNS FROM businesses LIKE 'logo_path'");
    if ($result->rowCount() == 0) {
        // Add logo_path column to businesses table
        $pdo->exec("ALTER TABLE businesses ADD COLUMN logo_path VARCHAR(255) DEFAULT NULL");
        echo "Successfully added logo_path column to businesses table.\n";
    } else {
        echo "logo_path column already exists.\n";
    }

    // Check if type column exists
    $result = $pdo->query("SHOW COLUMNS FROM businesses LIKE 'type'");
    if ($result->rowCount() == 0) {
        // Add type column to businesses table
        $pdo->exec("ALTER TABLE businesses ADD COLUMN type ENUM('vending', 'restaurant', 'cannabis', 'retail', 'other') NOT NULL DEFAULT 'vending'");
        echo "Successfully added type column to businesses table.\n";
    } else {
        echo "type column already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 