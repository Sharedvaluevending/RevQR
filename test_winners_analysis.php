<?php
require_once __DIR__ . '/html/core/config.php';

echo "=== WEEKLY WINNERS ANALYSIS ===\n\n";

try {
    // Check current winners by week and type
    echo "1. Winners count by week and type:\n";
    $stmt = $pdo->prepare("
        SELECT 
            week_year, 
            winner_type, 
            COUNT(*) as winner_count,
            GROUP_CONCAT(item_name ORDER BY vote_count DESC) as items
        FROM weekly_winners 
        GROUP BY week_year, winner_type 
        ORDER BY week_year DESC, winner_type
    ");
    $stmt->execute();
    $winners_summary = $stmt->fetchAll();
    
    $issues_found = false;
    foreach ($winners_summary as $row) {
        echo sprintf("   Week %s - %s: %d winner(s) [%s]\n", 
            $row['week_year'], 
            strtoupper($row['winner_type']), 
            $row['winner_count'],
            $row['items']
        );
        
        if ($row['winner_count'] > 1) {
            echo "   ❌ ISSUE: Multiple winners found for same category!\n";
            $issues_found = true;
        }
    }
    
    if (!$issues_found) {
        echo "   ✅ All weeks have exactly one winner per category\n";
    }
    
    echo "\n2. Detailed current winners:\n";
    $stmt = $pdo->prepare("
        SELECT 
            ww.*,
            vl.name as machine_name,
            b.name as business_name
        FROM weekly_winners ww
        JOIN voting_lists vl ON ww.voting_list_id = vl.id
        JOIN businesses b ON vl.business_id = b.id
        ORDER BY ww.week_year DESC, ww.winner_type, ww.vote_count DESC
    ");
    $stmt->execute();
    $all_winners = $stmt->fetchAll();
    
    $current_week = null;
    foreach ($all_winners as $winner) {
        if ($current_week !== $winner['week_year']) {
            $current_week = $winner['week_year'];
            echo "\n   Week {$winner['week_year']}:\n";
        }
        
        echo sprintf("      %s: %s (%s votes) - %s @ %s\n",
            strtoupper($winner['winner_type']),
            $winner['item_name'],
            $winner['vote_count'],
            $winner['business_name'],
            $winner['machine_name']
        );
    }
    
    echo "\n3. Table constraint check:\n";
    $stmt = $pdo->prepare("SHOW CREATE TABLE weekly_winners");
    $stmt->execute();
    $table_info = $stmt->fetch();
    
    if (strpos($table_info['Create Table'], 'unique_winner') !== false) {
        echo "   ✅ UNIQUE constraint exists for (voting_list_id, week_year, winner_type)\n";
    } else {
        echo "   ❌ UNIQUE constraint missing!\n";
    }
    
    echo "\n4. Testing logic - would current cron create multiple winners?\n";
    
    // Test current week's votes to see what winners would be selected
    $stmt = $pdo->prepare("
        SELECT 
            vli.voting_list_id,
            v.vote_type,
            v.item_id,
            vli.item_name,
            COUNT(*) as vote_count,
            ROW_NUMBER() OVER (PARTITION BY vli.voting_list_id, v.vote_type ORDER BY COUNT(*) DESC) as rank_num
        FROM votes v
        JOIN voting_list_items vli ON v.item_id = vli.id
        WHERE YEARWEEK(v.created_at, 1) = YEARWEEK(NOW(), 1)
        GROUP BY vli.voting_list_id, v.vote_type, v.item_id, vli.item_name
        ORDER BY vli.voting_list_id, v.vote_type, vote_count DESC
    ");
    $stmt->execute();
    $current_votes = $stmt->fetchAll();
    
    $list_winners = [];
    foreach ($current_votes as $vote) {
        $key = $vote['voting_list_id'] . '_' . $vote['vote_type'];
        if ($vote['rank_num'] == 1) { // Only top winner
            $list_winners[$key] = $vote;
        }
    }
    
    echo "   Current week potential winners:\n";
    foreach ($list_winners as $winner) {
        echo sprintf("      List %d - %s: %s (%d votes)\n",
            $winner['voting_list_id'],
            strtoupper($winner['vote_type']),
            $winner['item_name'],
            $winner['vote_count']
        );
    }
    
    echo "\n5. Summary:\n";
    if ($issues_found) {
        echo "   ⚠️  MULTIPLE WINNERS DETECTED in historical data\n";
        echo "   This suggests either:\n";
        echo "   - Multiple voting lists creating multiple winners\n";
        echo "   - Past bugs in winner calculation\n";
        echo "   - Manual data entry creating duplicates\n";
    } else {
        echo "   ✅ WINNER LOGIC IS CORRECT\n";
        echo "   One winner per category per week as expected\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== ANALYSIS COMPLETE ===\n";
?> 