<?php
require_once __DIR__ . '/html/core/config.php';

echo "=== FIXING HISTORICAL WEEKLY WINNERS ===\n\n";

try {
    // Get all weeks that have multiple winners
    $stmt = $pdo->prepare("
        SELECT 
            week_year, 
            winner_type, 
            COUNT(*) as winner_count
        FROM weekly_winners 
        GROUP BY week_year, winner_type 
        HAVING winner_count > 1
        ORDER BY week_year DESC, winner_type
    ");
    $stmt->execute();
    $problematic_weeks = $stmt->fetchAll();
    
    echo "Found " . count($problematic_weeks) . " week/category combinations with multiple winners:\n\n";
    
    $total_fixed = 0;
    
    foreach ($problematic_weeks as $week_issue) {
        echo "Fixing Week {$week_issue['week_year']} - {$week_issue['winner_type']} ({$week_issue['winner_count']} winners):\n";
        
        // Get all winners for this week/type, ordered by vote count
        $stmt = $pdo->prepare("
            SELECT 
                ww.*,
                vl.name as machine_name,
                b.name as business_name
            FROM weekly_winners ww
            JOIN voting_lists vl ON ww.voting_list_id = vl.id
            JOIN businesses b ON vl.business_id = b.id
            WHERE ww.week_year = ? AND ww.winner_type = ?
            ORDER BY ww.vote_count DESC, ww.id ASC
        ");
        $stmt->execute([$week_issue['week_year'], $week_issue['winner_type']]);
        $winners = $stmt->fetchAll();
        
        // Keep the winner with highest votes (first one), remove the rest
        $keep_winner = $winners[0];
        $remove_winners = array_slice($winners, 1);
        
        echo "   KEEPING: {$keep_winner['item_name']} ({$keep_winner['vote_count']} votes) - {$keep_winner['business_name']} @ {$keep_winner['machine_name']}\n";
        
        foreach ($remove_winners as $remove) {
            echo "   REMOVING: {$remove['item_name']} ({$remove['vote_count']} votes) - {$remove['business_name']} @ {$remove['machine_name']}\n";
            
            // Delete the duplicate winner
            $stmt = $pdo->prepare("DELETE FROM weekly_winners WHERE id = ?");
            $stmt->execute([$remove['id']]);
            $total_fixed++;
        }
        
        echo "\n";
    }
    
    echo "=== SUMMARY ===\n";
    echo "Total duplicate winners removed: $total_fixed\n";
    
    // Verify the fix
    $stmt = $pdo->prepare("
        SELECT 
            week_year, 
            winner_type, 
            COUNT(*) as winner_count
        FROM weekly_winners 
        GROUP BY week_year, winner_type 
        HAVING winner_count > 1
    ");
    $stmt->execute();
    $remaining_issues = $stmt->fetchAll();
    
    if (empty($remaining_issues)) {
        echo "✅ SUCCESS: All weeks now have exactly one winner per category!\n";
    } else {
        echo "❌ WARNING: " . count($remaining_issues) . " issues still remain\n";
    }
    
    // Show current winners
    echo "\n=== CURRENT WINNERS (after cleanup) ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            ww.week_year,
            ww.winner_type,
            ww.item_name,
            ww.vote_count,
            vl.name as machine_name,
            b.name as business_name
        FROM weekly_winners ww
        JOIN voting_lists vl ON ww.voting_list_id = vl.id
        JOIN businesses b ON vl.business_id = b.id
        ORDER BY ww.week_year DESC, ww.winner_type
    ");
    $stmt->execute();
    $current_winners = $stmt->fetchAll();
    
    $current_week = null;
    foreach ($current_winners as $winner) {
        if ($current_week !== $winner['week_year']) {
            $current_week = $winner['week_year'];
            echo "\nWeek {$winner['week_year']}:\n";
        }
        
        echo "   " . strtoupper($winner['winner_type']) . ": {$winner['item_name']} ({$winner['vote_count']} votes) - {$winner['business_name']} @ {$winner['machine_name']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== CLEANUP COMPLETE ===\n";
?> 