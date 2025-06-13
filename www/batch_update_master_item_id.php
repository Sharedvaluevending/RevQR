<?php
// Direct DB connection (update if needed)
$pdo = new PDO('mysql:host=localhost;dbname=revenueqr', 'root', '');

// 1. Fetch all voting_list_items without a master_item_id
$stmt = $pdo->query("SELECT id, item_name FROM voting_list_items WHERE master_item_id IS NULL OR master_item_id = 0");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($items as $item) {
    $itemId = $item['id'];
    $itemName = $item['item_name'];

    // 2. Try to find a matching master_item by name (case-insensitive)
    $miStmt = $pdo->prepare("SELECT id FROM master_items WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $miStmt->execute([$itemName]);
    $master = $miStmt->fetch(PDO::FETCH_ASSOC);

    if ($master) {
        $masterId = $master['id'];
        // 3. Update voting_list_items with the found master_item_id
        $updateStmt = $pdo->prepare("UPDATE voting_list_items SET master_item_id = ? WHERE id = ?");
        $updateStmt->execute([$masterId, $itemId]);
        echo "Updated voting_list_item $itemId with master_item_id $masterId\n";
    } else {
        echo "No master_item found for voting_list_item $itemId ($itemName)\n";
    }
}

echo "Batch update complete.\n";
?> 