<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno] $errstr on line $errline in file $errfile");
    return true;
});

// Custom exception handler
set_exception_handler(function($e) {
    error_log("Uncaught Exception: " . $e->getMessage());
    echo "An error occurred. Please check the logs for details.";
});

try {
    // Get current week's start and end dates
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    
    // Get all active voting lists
    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM voting_lists 
        WHERE status = 'active'
    ");
    $stmt->execute();
    $lists = $stmt->fetchAll();
    
    foreach ($lists as $list) {
        // Get items with their vote counts for this list
        $stmt = $pdo->prepare("
            SELECT 
                i.id,
                i.name,
                i.type,
                COUNT(CASE WHEN v.vote_type = 'in' THEN 1 END) as vote_in_count,
                COUNT(CASE WHEN v.vote_type = 'out' THEN 1 END) as vote_out_count
            FROM voting_list_items i
            LEFT JOIN votes v ON i.id = v.item_id 
                AND v.created_at BETWEEN ? AND ?
            WHERE i.voting_list_id = ?
            GROUP BY i.id
            HAVING (vote_in_count + vote_out_count) > 0
        ");
        $stmt->execute([$week_start, $week_end, $list['id']]);
        $items = $stmt->fetchAll();
        
        // Calculate winners based on vote percentages
        foreach ($items as $item) {
            $total_votes = $item['vote_in_count'] + $item['vote_out_count'];
            $in_percentage = ($item['vote_in_count'] / $total_votes) * 100;
            
            // If item has 70% or more "in" votes, it's a winner
            if ($in_percentage >= 70) {
                // Check if winner already exists for this week
                $stmt = $pdo->prepare("
                    SELECT id 
                    FROM winners 
                    WHERE list_id = ? 
                    AND week_start = ? 
                    AND vote_type = 'in'
                ");
                $stmt->execute([$list['id'], $week_start]);
                if (!$stmt->fetch()) {
                    // Insert new winner
                    $stmt = $pdo->prepare("
                        INSERT INTO winners (
                            list_id, 
                            item_id, 
                            vote_type, 
                            week_start, 
                            week_end, 
                            votes_count
                        ) VALUES (?, ?, 'in', ?, ?, ?)
                    ");
                    $stmt->execute([
                        $list['id'],
                        $item['id'],
                        $week_start,
                        $week_end,
                        $item['vote_in_count']
                    ]);
                }
            }
            
            // If item has 70% or more "out" votes, it's a loser
            if ((100 - $in_percentage) >= 70) {
                // Check if loser already exists for this week
                $stmt = $pdo->prepare("
                    SELECT id 
                    FROM winners 
                    WHERE list_id = ? 
                    AND week_start = ? 
                    AND vote_type = 'out'
                ");
                $stmt->execute([$list['id'], $week_start]);
                if (!$stmt->fetch()) {
                    // Insert new loser
                    $stmt = $pdo->prepare("
                        INSERT INTO winners (
                            list_id, 
                            item_id, 
                            vote_type, 
                            week_start, 
                            week_end, 
                            votes_count
                        ) VALUES (?, ?, 'out', ?, ?, ?)
                    ");
                    $stmt->execute([
                        $list['id'],
                        $item['id'],
                        $week_start,
                        $week_end,
                        $item['vote_out_count']
                    ]);
                }
            }
        }
    }
    
    echo "Winner calculation completed successfully.\n";
    
} catch (Exception $e) {
    error_log("Error in calculate-winners.php: " . $e->getMessage());
    echo "An error occurred while calculating winners. Please check the logs for details.\n";
} 