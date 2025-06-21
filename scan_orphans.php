<?php
require_once __DIR__ . '/html/core/config.php';

echo "Scanning for orphaned rows in all foreign key relationships...\n\n";

$db = $pdo->query("
    SELECT
        TABLE_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND REFERENCED_TABLE_NAME IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($db as $fk) {
    $child = $fk['TABLE_NAME'];
    $child_col = $fk['COLUMN_NAME'];
    $parent = $fk['REFERENCED_TABLE_NAME'];
    $parent_col = $fk['REFERENCED_COLUMN_NAME'];

    $sql = "
        SELECT COUNT(*) as orphan_count
        FROM {$child} c
        LEFT JOIN {$parent} p ON c.{$child_col} = p.{$parent_col}
        WHERE p.{$parent_col} IS NULL
    ";
    $count = $pdo->query($sql)->fetchColumn();

    if ($count > 0) {
        echo "❌ {$count} orphaned rows in {$child}.{$child_col} (should reference {$parent}.{$parent_col})\n";
    } else {
        echo "✅ No orphans in {$child}.{$child_col} → {$parent}.{$parent_col}\n";
    }
}

echo "\nScan complete.\n"; 