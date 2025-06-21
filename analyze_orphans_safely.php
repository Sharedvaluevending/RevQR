<?php
require_once __DIR__ . '/html/core/config.php';

echo "=== SAFE ORPHAN ANALYSIS ===\n\n";

// Get all foreign key relationships
$fks = $pdo->query("
    SELECT
        TABLE_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND REFERENCED_TABLE_NAME IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($fks as $fk) {
    $child = $fk['TABLE_NAME'];
    $child_col = $fk['COLUMN_NAME'];
    $parent = $fk['REFERENCED_TABLE_NAME'];
    $parent_col = $fk['REFERENCED_COLUMN_NAME'];

    // Check for orphans
    $sql = "
        SELECT COUNT(*) as orphan_count
        FROM {$child} c
        LEFT JOIN {$parent} p ON c.{$child_col} = p.{$parent_col}
        WHERE p.{$parent_col} IS NULL
    ";
    $count = $pdo->query($sql)->fetchColumn();

    if ($count > 0) {
        echo "🔍 ANALYZING: {$count} orphaned rows in {$child}.{$child_col} → {$parent}.{$parent_col}\n";
        
        // Get sample orphaned records to understand what they are
        $sample_sql = "
            SELECT c.*
            FROM {$child} c
            LEFT JOIN {$parent} p ON c.{$child_col} = p.{$parent_col}
            WHERE p.{$parent_col} IS NULL
            LIMIT 3
        ";
        
        try {
            $samples = $pdo->query($sample_sql)->fetchAll(PDO::FETCH_ASSOC);
            
            echo "   Sample orphaned records:\n";
            foreach ($samples as $i => $sample) {
                echo "   " . ($i + 1) . ". ";
                // Show key identifying fields
                $identifiers = [];
                foreach ($sample as $field => $value) {
                    if (in_array($field, ['id', 'name', 'title', 'user_id', 'business_id', 'created_at', 'updated_at']) || 
                        strpos($field, '_id') !== false) {
                        $identifiers[] = "{$field}: " . (is_null($value) ? 'NULL' : $value);
                    }
                }
                echo implode(', ', array_slice($identifiers, 0, 5)) . "\n";
            }
            
            // Provide cleanup recommendation
            echo "   💡 RECOMMENDATION: ";
            if ($count < 10) {
                echo "Safe to delete - small number of orphans\n";
                echo "   SQL: DELETE c FROM {$child} c LEFT JOIN {$parent} p ON c.{$child_col} = p.{$parent_col} WHERE p.{$parent_col} IS NULL;\n";
            } else {
                echo "Review manually - {$count} orphans found\n";
                echo "   SQL: SELECT c.* FROM {$child} c LEFT JOIN {$parent} p ON c.{$child_col} = p.{$parent_col} WHERE p.{$parent_col} IS NULL;\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ Error analyzing: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
}

echo "=== ANALYSIS COMPLETE ===\n\n";
echo "SAFETY GUIDELINES:\n";
echo "1. Always backup before cleanup: mysqldump your_database > backup_$(date +%Y%m%d_%H%M%S).sql\n";
echo "2. Test cleanup queries on a copy of your database first\n";
echo "3. For large numbers of orphans, review them manually before deleting\n";
echo "4. Consider if the orphaned data might be needed for audit/history purposes\n";
echo "5. Some orphans might be from incomplete transactions - check if they should be completed instead of deleted\n"; 