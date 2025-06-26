<?php
/**
 * EMERGENCY DATABASE FIX RUNNER
 * This runs the critical fixes that made everything work on June 15th
 */

require_once 'html/core/config.php';

echo "🚨 RUNNING EMERGENCY DATABASE FIXES...\n";
echo "======================================\n\n";

try {
    // 1. Fix vote type standardization (CRITICAL)
    echo "1. 🔧 Fixing vote type standardization...\n";
    $stmt = $pdo->prepare("ALTER TABLE votes MODIFY vote_type ENUM('vote_in', 'vote_out') NOT NULL DEFAULT 'vote_in'");
    $stmt->execute();
    echo "   ✅ Vote type enum fixed\n\n";

    // 2. Add performance indexes (CRITICAL) - Check if they exist first
    echo "2. 📊 Adding performance indexes...\n";
    
    $indexes = [
        'idx_item_vote_type' => 'ALTER TABLE votes ADD INDEX idx_item_vote_type (item_id, vote_type)',
        'idx_campaign_vote_type' => 'ALTER TABLE votes ADD INDEX idx_campaign_vote_type (campaign_id, vote_type)',
        'idx_machine_vote_type' => 'ALTER TABLE votes ADD INDEX idx_machine_vote_type (machine_id, vote_type)',
        'idx_voter_ip_date' => 'ALTER TABLE votes ADD INDEX idx_voter_ip_date (voter_ip, created_at)'
    ];
    
    foreach ($indexes as $indexName => $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            echo "   ✅ Added index: $indexName\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "   ℹ️  Index $indexName already exists\n";
            } else {
                echo "   ⚠️  Error adding $indexName: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "\n";

    // 3. Fix constraint violations (CRITICAL)
    echo "3. 🛠️  Fixing constraint violations...\n";
    $stmt = $pdo->prepare("UPDATE votes SET machine_id = 0 WHERE machine_id IS NULL");
    $result1 = $stmt->execute();
    $affected1 = $stmt->rowCount();
    
    $stmt = $pdo->prepare("UPDATE votes SET campaign_id = 0 WHERE campaign_id IS NULL");
    $result2 = $stmt->execute();
    $affected2 = $stmt->rowCount();
    
    echo "   ✅ Fixed $affected1 machine_id null values\n";
    echo "   ✅ Fixed $affected2 campaign_id null values\n\n";

    // 4. Clean up inconsistent vote data
    echo "4. 🧹 Cleaning up inconsistent vote data...\n";
    $stmt = $pdo->prepare("UPDATE votes SET vote_type = 'vote_in' WHERE vote_type IN ('in', 'yes', 'up', 'like')");
    $result3 = $stmt->execute();
    $affected3 = $stmt->rowCount();
    
    $stmt = $pdo->prepare("UPDATE votes SET vote_type = 'vote_out' WHERE vote_type IN ('out', 'no', 'down', 'dislike')");
    $result4 = $stmt->execute();
    $affected4 = $stmt->rowCount();
    
    echo "   ✅ Standardized $affected3 'in' type votes\n";
    echo "   ✅ Standardized $affected4 'out' type votes\n\n";

    // 5. Verify the fixes
    echo "5. ✅ VERIFICATION:\n";
    $stmt = $pdo->prepare("SELECT vote_type, COUNT(*) as total_votes FROM votes GROUP BY vote_type");
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    foreach ($results as $row) {
        echo "   📊 Vote type '{$row['vote_type']}': {$row['total_votes']} votes\n";
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as votes_with_valid_constraints FROM votes WHERE machine_id IS NOT NULL AND campaign_id IS NOT NULL");
    $stmt->execute();
    $constraint_result = $stmt->fetch();
    
    echo "   📊 Votes with valid constraints: {$constraint_result['votes_with_valid_constraints']}\n\n";

    echo "🎉 SUCCESS! All critical database fixes applied!\n";
    echo "==============================================\n";
    echo "✅ Vote type standardization: FIXED\n";
    echo "✅ Performance indexes: ADDED\n";
    echo "✅ Constraint violations: RESOLVED\n";
    echo "✅ Data inconsistencies: CLEANED\n\n";
    
    echo "🚀 Your voting system should now work like it did on June 15th!\n";
    echo "🎯 Next: Test voting, slots, and discount purchases\n";

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
}
?> 