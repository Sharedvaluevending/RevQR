<?php
require_once __DIR__ . '/html/core/config.php';

try {
    // Add email column to users table
    $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NOT NULL UNIQUE AFTER username");
    echo "Email column added successfully!\n";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Email column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
} 