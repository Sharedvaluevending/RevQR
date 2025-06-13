#!/usr/bin/env php
<?php
/**
 * Weekly Voting Reset and Winner Calculation
 * Run this script every Monday at 12:01 AM
 * Cron: 1 0 * * 1 /usr/bin/php /var/www/html/cron/weekly-reset.php
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cron-weekly-reset.log');

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message" . PHP_EOL;
    file_put_contents(__DIR__ . '/../logs/cron-weekly-reset.log', $log_message, FILE_APPEND | LOCK_EX);
    echo $log_message;
}

function calculateWinners($pdo) {
    try {
        // Get current week dates
        $week_start = date('Y-m-d', strtotime('monday last week'));
        $week_end = date('Y-m-d', strtotime('sunday last week'));
        
        logMessage("Calculating winners for week: $week_start to $week_end");
        
        // Get all active campaigns
        $stmt = $pdo->prepare("
            SELECT c.*, b.name as business_name
            FROM campaigns c
            JOIN businesses b ON c.business_id = b.id
            WHERE c.status = 'active'
        ");
        $stmt->execute();
        $campaigns = $stmt->fetchAll();
        
        $winners_found = 0;
        
        foreach ($campaigns as $campaign) {
            // Get voting lists for this campaign
            $stmt = $pdo->prepare("
                SELECT vl.*
                FROM voting_lists vl
                JOIN campaign_voting_lists cvl ON vl.id = cvl.voting_list_id
                WHERE cvl.campaign_id = ?
            ");
            $stmt->execute([$campaign['id']]);
            $lists = $stmt->fetchAll();
            
            foreach ($lists as $list) {
                // Get vote counts for items in this list during last week
                $stmt = $pdo->prepare("
                    SELECT 
                        i.id,
                        i.name,
                        COUNT(CASE WHEN v.vote_type = 'in' THEN 1 END) as votes_in,
                        COUNT(CASE WHEN v.vote_type = 'out' THEN 1 END) as votes_out,
                        (COUNT(CASE WHEN v.vote_type = 'in' THEN 1 END) + COUNT(CASE WHEN v.vote_type = 'out' THEN 1 END)) as total_votes
                    FROM voting_list_items i
                    LEFT JOIN votes v ON i.id = v.item_id 
                        AND v.campaign_id = ?
                        AND DATE(v.created_at) BETWEEN ? AND ?
                    WHERE i.list_id = ? AND i.status = 'active'
                    GROUP BY i.id
                    HAVING total_votes > 0
                    ORDER BY votes_in DESC, total_votes DESC
                ");
                $stmt->execute([$campaign['id'], $week_start, $week_end, $list['id']]);
                $items = $stmt->fetchAll();
                
                if (!empty($items)) {
                    // Find winners (highest vote_in count)
                    $max_votes_in = $items[0]['votes_in'];
                    $vote_in_winners = array_filter($items, function($item) use ($max_votes_in) {
                        return $item['votes_in'] == $max_votes_in && $max_votes_in > 0;
                    });
                    
                    // Find losers (highest vote_out count)
                    usort($items, function($a, $b) {
                        return $b['votes_out'] - $a['votes_out'];
                    });
                    $max_votes_out = $items[0]['votes_out'];
                    $vote_out_winners = array_filter($items, function($item) use ($max_votes_out) {
                        return $item['votes_out'] == $max_votes_out && $max_votes_out > 0;
                    });
                    
                    // Record vote_in winners
                    foreach ($vote_in_winners as $winner) {
                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO winners (
                                campaign_id, list_id, item_id, vote_type, 
                                week_start, week_end, votes_count, created_at
                            ) VALUES (?, ?, ?, 'in', ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $campaign['id'], $list['id'], $winner['id'],
                            $week_start, $week_end, $winner['votes_in']
                        ]);
                        $winners_found++;
                        logMessage("Winner (IN): {$winner['name']} with {$winner['votes_in']} votes in campaign {$campaign['name']}");
                    }
                    
                    // Record vote_out winners
                    foreach ($vote_out_winners as $loser) {
                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO winners (
                                campaign_id, list_id, item_id, vote_type, 
                                week_start, week_end, votes_count, created_at
                            ) VALUES (?, ?, ?, 'out', ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $campaign['id'], $list['id'], $loser['id'],
                            $week_start, $week_end, $loser['votes_out']
                        ]);
                        $winners_found++;
                        logMessage("Winner (OUT): {$loser['name']} with {$loser['votes_out']} votes in campaign {$campaign['name']}");
                    }
                }
            }
        }
        
        logMessage("Total winners recorded: $winners_found");
        return $winners_found;
        
    } catch (Exception $e) {
        logMessage("ERROR in calculateWinners: " . $e->getMessage());
        throw $e;
    }
}

function resetWeeklyVotes($pdo) {
    try {
        // Archive old votes to a backup table before deleting
        $stmt = $pdo->prepare("
            CREATE TABLE IF NOT EXISTS votes_archive (
                id int PRIMARY KEY,
                list_id int,
                item_id int,
                vote_type enum('in','out'),
                user_id int,
                ip_address varchar(45),
                campaign_id int,
                created_at timestamp,
                archived_at timestamp DEFAULT CURRENT_TIMESTAMP,
                week_archived varchar(10)
            )
        ");
        $stmt->execute();
        
        // Archive last week's votes
        $week_start = date('Y-m-d', strtotime('monday last week'));
        $week_end = date('Y-m-d', strtotime('sunday last week'));
        $week_label = date('Y-W', strtotime($week_start));
        
        $stmt = $pdo->prepare("
            INSERT INTO votes_archive 
            SELECT *, NOW(), ? FROM votes 
            WHERE DATE(created_at) BETWEEN ? AND ?
        ");
        $result = $stmt->execute([$week_label, $week_start, $week_end]);
        $archived_count = $stmt->rowCount();
        
        // Delete old votes (older than 1 week)
        $stmt = $pdo->prepare("
            DELETE FROM votes 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)
        ");
        $result = $stmt->execute();
        $deleted_count = $stmt->rowCount();
        
        logMessage("Archived $archived_count votes, deleted $deleted_count old votes");
        return $deleted_count;
        
    } catch (Exception $e) {
        logMessage("ERROR in resetWeeklyVotes: " . $e->getMessage());
        throw $e;
    }
}

function postWinners($pdo) {
    try {
        // Get this week's winners
        $week_start = date('Y-m-d', strtotime('monday last week'));
        $week_end = date('Y-m-d', strtotime('sunday last week'));
        
        $stmt = $pdo->prepare("
            SELECT w.*, i.name as item_name, c.name as campaign_name, 
                   b.name as business_name, vl.name as list_name
            FROM winners w
            JOIN voting_list_items i ON w.item_id = i.id
            JOIN campaigns c ON w.campaign_id = c.id
            JOIN businesses b ON c.business_id = b.id
            JOIN voting_lists vl ON w.list_id = vl.id
            WHERE w.week_start = ?
            ORDER BY c.name, w.vote_type, w.votes_count DESC
        ");
        $stmt->execute([$week_start]);
        $winners = $stmt->fetchAll();
        
        if (!empty($winners)) {
            // Create winner summary for posting/emailing
            $summary = "🏆 WEEKLY VOTING RESULTS - " . date('M j', strtotime($week_start)) . " to " . date('M j, Y', strtotime($week_end)) . "\n\n";
            
            $current_campaign = '';
            foreach ($winners as $winner) {
                if ($current_campaign !== $winner['campaign_name']) {
                    $current_campaign = $winner['campaign_name'];
                    $summary .= "📊 " . $winner['business_name'] . " - " . $winner['campaign_name'] . "\n";
                    $summary .= "   List: " . $winner['list_name'] . "\n";
                }
                
                $type_emoji = $winner['vote_type'] === 'in' ? '👍' : '👎';
                $summary .= "   $type_emoji " . $winner['item_name'] . " - " . $winner['votes_count'] . " votes\n";
            }
            
            // Save to file for business owners to access
            $results_file = __DIR__ . '/../public/weekly-results-' . date('Y-W', strtotime($week_start)) . '.txt';
            file_put_contents($results_file, $summary);
            
            logMessage("Winner summary created: $results_file");
            logMessage("Winners posted successfully");
            
            // TODO: Add email notification logic here
            // TODO: Add social media posting logic here
            
            return count($winners);
        } else {
            logMessage("No winners found for the week");
            return 0;
        }
        
    } catch (Exception $e) {
        logMessage("ERROR in postWinners: " . $e->getMessage());
        throw $e;
    }
}

// Main execution
try {
    logMessage("=== WEEKLY RESET STARTING ===");
    
    // Step 1: Calculate and record winners
    $winners_count = calculateWinners($pdo);
    
    // Step 2: Reset/archive old votes  
    $reset_count = resetWeeklyVotes($pdo);
    
    // Step 3: Post winners
    $posted_count = postWinners($pdo);
    
    logMessage("=== WEEKLY RESET COMPLETED ===");
    logMessage("Summary: $winners_count winners found, $reset_count votes reset, $posted_count winners posted");
    
    // Send success notification
    exit(0);
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    
    // Send error notification
    exit(1);
}
?> 