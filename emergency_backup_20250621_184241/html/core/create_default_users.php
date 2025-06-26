<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Default admin user
$admin_username = 'admin';
$admin_password = 'Admin@123'; // You should change this after first login

// Default business user
$business_username = 'business';
$business_password = 'Business@123'; // You should change this after first login

try {
    // Create admin user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$admin_username]);
    if (!$stmt->fetch()) {
        if (create_user($admin_username, $admin_password, ROLE_ADMIN)) {
            echo "Admin user created successfully\n";
            echo "Username: " . $admin_username . "\n";
            echo "Password: " . $admin_password . "\n";
        } else {
            echo "Failed to create admin user\n";
        }
    } else {
        echo "Admin user already exists\n";
    }

    // Create business user
    $stmt->execute([$business_username]);
    if (!$stmt->fetch()) {
        // First create a business record
        $stmt = $pdo->prepare("
            INSERT INTO businesses (name, slug, created_at) 
            VALUES (?, ?, NOW())
        ");
        if ($stmt->execute(['Default Business', 'default-business'])) {
            $business_id = $pdo->lastInsertId();
            
            if (create_user($business_username, $business_password, ROLE_BUSINESS, $business_id)) {
                echo "Business user created successfully\n";
                echo "Username: " . $business_username . "\n";
                echo "Password: " . $business_password . "\n";
            } else {
                echo "Failed to create business user\n";
            }
        } else {
            echo "Failed to create business record\n";
        }
    } else {
        echo "Business user already exists\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 