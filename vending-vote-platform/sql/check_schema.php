<?php
require_once __DIR__ . '/../includes/config.php';

function checkColumns($pdo, $table, $expectedColumns) {
    echo "\nChecking columns for table: $table\n";
    $stmt = $pdo->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_diff($expectedColumns, $columns);
    $extra = array_diff($columns, $expectedColumns);
    if ($missing) {
        echo "  MISSING columns: ", implode(', ', $missing), "\n";
    } else {
        echo "  All required columns present.\n";
    }
    if ($extra) {
        echo "  EXTRA columns: ", implode(', ', $extra), "\n";
    }
}

function checkIndex($pdo, $table, $index) {
    $stmt = $pdo->query("SHOW INDEX FROM $table WHERE Key_name = '$index'");
    $result = $stmt->fetch();
    if ($result) {
        echo "  Index $index: PRESENT\n";
    } else {
        echo "  Index $index: MISSING\n";
    }
}

function checkView($pdo, $view) {
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_revenueqr = '$view'");
    $result = $stmt->fetch();
    if ($result) {
        echo "  View $view: PRESENT\n";
    } else {
        echo "  View $view: MISSING\n";
    }
}

echo "\n==== SCHEMA CHECK ====";

// Winners
checkColumns($pdo, 'winners', [
    'id','machine_id','item_id','vote_type','week_start','week_end','votes_count','created_at'
]);
checkIndex($pdo, 'winners', 'idx_machine_week');

// Machines
checkColumns($pdo, 'machines', [
    'id','business_id','name','slug','description','type','is_active','tooltip','created_at','updated_at'
]);

// QR Codes
checkColumns($pdo, 'qr_codes', [
    'id','machine_id','qr_type','campaign_type','code','meta','static_url','created_at','status','updated_at'
]);

// Items
checkColumns($pdo, 'items', [
    'id','machine_id','name','type','price','list_type','status','created_at','updated_at'
]);
checkIndex($pdo, 'items', 'idx_machine_status');

// Votes
checkColumns($pdo, 'votes', [
    'id','machine_id','item_id','vote_type','voter_ip','created_at','updated_at'
]);
checkIndex($pdo, 'votes', 'idx_machine_vote_type');

// Views
checkView($pdo, 'campaign_view');
checkView($pdo, 'campaign_items_view');

echo "\n==== END SCHEMA CHECK ===="; 