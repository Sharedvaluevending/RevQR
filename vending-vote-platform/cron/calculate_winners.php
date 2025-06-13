<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../models/Machine.php';
require_once __DIR__ . '/../models/Vote.php';
require_once __DIR__ . '/../models/Winner.php';

// Get all active machines
$machine = new Machine($pdo);
$machines = $machine->getActiveCampaigns();

// Get current week's start and end dates
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

$vote = new Vote($pdo);
$winner = new Winner($pdo);

foreach ($machines as $machine_data) {
    // Get vote counts for this machine
    $vote_counts = $vote->getVoteCounts($machine_data['id']);
    
    // Process vote-in winners
    if (isset($vote_counts['vote_in'])) {
        $max_votes = 0;
        $winning_item = null;
        
        foreach ($vote_counts['vote_in'] as $item_id => $count) {
            if ($count > $max_votes) {
                $max_votes = $count;
                $winning_item = $item_id;
            }
        }
        
        if ($winning_item && $max_votes > 0) {
            // Record the winner
            $winner->create([
                'machine_id' => $machine_data['id'],
                'item_id' => $winning_item,
                'vote_type' => 'vote_in',
                'week_start' => $week_start,
                'week_end' => $week_end,
                'votes_count' => $max_votes
            ]);
        }
    }
    
    // Process vote-out winners
    if (isset($vote_counts['vote_out'])) {
        $max_votes = 0;
        $winning_item = null;
        
        foreach ($vote_counts['vote_out'] as $item_id => $count) {
            if ($count > $max_votes) {
                $max_votes = $count;
                $winning_item = $item_id;
            }
        }
        
        if ($winning_item && $max_votes > 0) {
            // Record the winner
            $winner->create([
                'machine_id' => $machine_data['id'],
                'item_id' => $winning_item,
                'vote_type' => 'vote_out',
                'week_start' => $week_start,
                'week_end' => $week_end,
                'votes_count' => $max_votes
            ]);
        }
    }
    
    // Clear votes for this machine
    $vote->clearVotes($machine_data['id']);
}

echo "Weekly winners calculated successfully.\n"; 