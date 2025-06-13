<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

// Update expired promotions
$stmt = $pdo->prepare("
    UPDATE promotions 
    SET status = 'expired' 
    WHERE status = 'active' 
    AND end_date < CURDATE()
");
$stmt->execute();

$expired_count = $stmt->rowCount();

// Log the results
$log_message = date('Y-m-d H:i:s') . " - Expired {$expired_count} promotions\n";
file_put_contents(__DIR__ . '/../logs/promotion_expirations.log', $log_message, FILE_APPEND);

echo "Expired {$expired_count} promotions.\n"; 