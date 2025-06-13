<?php
require_once __DIR__ . '/config.php';

// Get all active campaigns
$stmt = $pdo->query("SELECT id FROM qr_campaigns WHERE is_active = 1");
$campaigns = $stmt->fetchAll();

foreach ($campaigns as $campaign) {
    // Get all active items for the business
    $stmt = $pdo->prepare("
        SELECT i.id 
        FROM items i
        JOIN businesses b ON i.business_id = b.id
        JOIN qr_campaigns c ON c.business_id = b.id
        WHERE c.id = ? AND i.status = 'active'
    ");
    $stmt->execute([$campaign['id']]);
    $items = $stmt->fetchAll();
    
    // Add items to campaign
    foreach ($items as $item) {
        try {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO campaign_items (campaign_id, item_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$campaign['id'], $item['id']]);
        } catch (PDOException $e) {
            echo "Error adding item {$item['id']} to campaign {$campaign['id']}: " . $e->getMessage() . "\n";
        }
    }
}

echo "Campaign items updated successfully!\n"; 