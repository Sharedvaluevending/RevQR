<?php
require_once __DIR__ . '/config.php';

try {
    // Check if machine_name column exists
    $result = $pdo->query("SHOW COLUMNS FROM qr_codes LIKE 'machine_name'");
    if ($result->rowCount() == 0) {
        // Add machine_name column to qr_codes table
        $pdo->exec("ALTER TABLE qr_codes ADD COLUMN machine_name VARCHAR(100) AFTER code");
        echo "Successfully added machine_name column to qr_codes table.\n";
    } else {
        echo "machine_name column already exists.\n";
    }

    // Check if machine_location column exists
    $result = $pdo->query("SHOW COLUMNS FROM qr_codes LIKE 'machine_location'");
    if ($result->rowCount() == 0) {
        // Add machine_location column to qr_codes table
        $pdo->exec("ALTER TABLE qr_codes ADD COLUMN machine_location TEXT AFTER machine_name");
        echo "Successfully added machine_location column to qr_codes table.\n";
    } else {
        echo "machine_location column already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 