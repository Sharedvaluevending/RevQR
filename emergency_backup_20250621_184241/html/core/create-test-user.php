<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Create test user
$email = 'test@example.com';
$password = 'test123';
$name = 'Test User';
$role = 'business';
$status = 'active';

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (name, email, password, role, status, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");
$stmt->execute([$name, $email, $hashed_password, $role, $status]);

$user_id = $pdo->lastInsertId();

// Create test business and link to user
$stmt = $pdo->prepare("
    INSERT INTO businesses (user_id, name, created_at)
    VALUES (?, ?, NOW())
");
$stmt->execute([$user_id, 'Test Business']);

$business_id = $pdo->lastInsertId();

echo "Test user and business created successfully!\n";
echo "Email: $email\n";
echo "Password: $password\n";
echo "User ID: $user_id\n";
echo "Business ID: $business_id\n"; 