<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Business user details
$business_username = 'sharedvaluevending';
$business_password = '1qaz2wsx';
$business_name = 'Shared Value Vending';
$business_slug = 'sharedvaluevending-' . time(); // Make slug unique
$business_email = 'sharedvaluevending@gmail.com'; // Set email to sharedvaluevending@gmail.com

try {
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$business_username]);
    if (!$stmt->fetch()) {
        // Create business record
        $stmt = $pdo->prepare("
            INSERT INTO businesses (name, slug, created_at) 
            VALUES (?, ?, NOW())
        ");
        if ($stmt->execute([$business_name, $business_slug])) {
            $business_id = $pdo->lastInsertId();
            
            // Create user with email
            if (create_user($business_username, $business_password, ROLE_BUSINESS, $business_id, $business_email)) {
                echo "Business user created successfully\n";
                echo "Username: " . $business_username . "\n";
                echo "Email: " . $business_email . "\n";
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