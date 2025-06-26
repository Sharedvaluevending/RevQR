<?php
require_once 'core/config.php';

echo "Testing QR code: qr_684becc9621107.66630854\n";
$stmt = $pdo->prepare("
    SELECT qr.*, vl.name as list_name, vl.description as list_description,
           b.name as business_name, c.name as campaign_name
    FROM qr_codes qr
    LEFT JOIN voting_lists vl ON qr.machine_id = vl.id
    LEFT JOIN campaigns c ON qr.campaign_id = c.id
    LEFT JOIN businesses b ON COALESCE(vl.business_id, c.business_id) = b.id
    WHERE qr.code = ?
");
$stmt->execute(['qr_684becc9621107.66630854']);
$qr_data = $stmt->fetch();

if ($qr_data) {
    echo "✅ QR Code found: {$qr_data['campaign_name']}\n";
    if ($qr_data['campaign_id']) {
        $stmt = $pdo->prepare("
            SELECT vl.id, vl.name, COUNT(vli.id) as item_count
            FROM voting_lists vl
            JOIN campaign_voting_lists cvl ON vl.id = cvl.voting_list_id
            LEFT JOIN voting_list_items vli ON vl.id = vli.voting_list_id
            WHERE cvl.campaign_id = ?
            GROUP BY vl.id
        ");
        $stmt->execute([$qr_data['campaign_id']]);
        $list = $stmt->fetch();
        if ($list) {
            echo "✅ Voting list: {$list['name']} with {$list['item_count']} items\n";
        }
    }
} else {
    echo "❌ QR Code not found\n";
}
?> 