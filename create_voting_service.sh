#!/bin/bash

cat > html/core/services/VotingService.php << 'EOF'
<?php
/**
 * Unified Voting Service
 * Consolidates all voting logic to eliminate inconsistencies
 */

class VotingService {
    private $pdo;
    
    const VOTE_IN = 'vote_in';
    const VOTE_OUT = 'vote_out';
    const VOTES_PER_WEEK_PER_IP = 2;
    const VOTES_PER_WEEK_PER_USER = 10;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Record a vote with full validation
     */
    public function recordVote($voteData) {
        try {
            // Validate input data
            $validation = $this->validateVoteData($voteData);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            
            // Standardize vote type
            $voteType = $this->standardizeVoteType($voteData['vote_type']);
            
            // Check vote limits
            $limitCheck = $this->checkVoteLimits($voteData);
            if (!$limitCheck['allowed']) {
                return ['success' => false, 'message' => $limitCheck['message']];
            }
            
            // Record the vote
            $voteId = $this->insertVote([
                'user_id' => $voteData['user_id'] ?? null,
                'item_id' => $voteData['item_id'],
                'vote_type' => $voteType,
                'voter_ip' => $this->getClientIP(),
                'campaign_id' => $voteData['campaign_id'] ?? 0,
                'machine_id' => $voteData['machine_id'] ?? 0,
                'qr_code_id' => $voteData['qr_code_id'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            if ($voteId) {
                return [
                    'success' => true, 
                    'message' => 'Thank you for your vote!',
                    'vote_id' => $voteId,
                    'updated_counts' => $this->getVoteCounts($voteData['item_id'], $voteData['campaign_id'] ?? null)
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to record vote. Please try again.'];
            }
            
        } catch (Exception $e) {
            error_log("VotingService Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while recording your vote.'];
        }
    }
    
    /**
     * Get vote counts for an item
     */
    public function getVoteCounts($itemId, $campaignId = null) {
        try {
            $query = "
                SELECT 
                    COUNT(CASE WHEN vote_type = ? THEN 1 END) as vote_in_count,
                    COUNT(CASE WHEN vote_type = ? THEN 1 END) as vote_out_count,
                    COUNT(*) as total_votes
                FROM votes 
                WHERE item_id = ?
            ";
            $params = [self::VOTE_IN, self::VOTE_OUT, $itemId];
            
            if ($campaignId) {
                $query .= " AND campaign_id = ?";
                $params[] = $campaignId;
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'vote_in_count' => (int)$result['vote_in_count'],
                'vote_out_count' => (int)$result['vote_out_count'],
                'total_votes' => (int)$result['total_votes']
            ];
            
        } catch (Exception $e) {
            error_log("VotingService::getVoteCounts Error: " . $e->getMessage());
            return ['vote_in_count' => 0, 'vote_out_count' => 0, 'total_votes' => 0];
        }
    }
    
    /**
     * Standardize vote type to consistent enum values
     */
    private function standardizeVoteType($voteType) {
        $voteType = strtolower(trim($voteType));
        
        $inTypes = ['in', 'vote_in', 'yes', 'up', 'thumbs_up', '1', 'true'];
        $outTypes = ['out', 'vote_out', 'no', 'down', 'thumbs_down', '0', 'false'];
        
        if (in_array($voteType, $inTypes)) {
            return self::VOTE_IN;
        } elseif (in_array($voteType, $outTypes)) {
            return self::VOTE_OUT;
        }
        
        return self::VOTE_IN; // Default fallback
    }
    
    /**
     * Validate vote data
     */
    private function validateVoteData($voteData) {
        if (empty($voteData['item_id']) || !is_numeric($voteData['item_id'])) {
            return ['valid' => false, 'message' => 'Valid item ID is required'];
        }
        
        if (empty($voteData['vote_type'])) {
            return ['valid' => false, 'message' => 'Vote type is required'];
        }
        
        // Validate item exists
        $stmt = $this->pdo->prepare("SELECT id FROM voting_list_items WHERE id = ?");
        $stmt->execute([$voteData['item_id']]);
        if (!$stmt->fetch()) {
            return ['valid' => false, 'message' => 'Item not found'];
        }
        
        return ['valid' => true, 'message' => 'Valid'];
    }
    
    /**
     * Check voting limits for IP/user
     */
    private function checkVoteLimits($voteData) {
        $itemId = $voteData['item_id'];
        $userId = $voteData['user_id'] ?? null;
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Check if already voted for this specific item this week
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM votes 
            WHERE item_id = ? 
            AND (
                (user_id IS NOT NULL AND user_id = ?) OR
                (voter_ip = ? AND user_agent = ?)
            )
            AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
        ");
        $stmt->execute([$itemId, $userId, $ip, $userAgent]);
        $itemVotes = $stmt->fetchColumn();
        
        if ($itemVotes > 0) {
            return ['allowed' => false, 'message' => 'You have already voted for this item this week.'];
        }
        
        return ['allowed' => true, 'message' => 'Within limits'];
    }
    
    /**
     * Insert vote record
     */
    private function insertVote($voteData) {
        try {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            
            $stmt = $this->pdo->prepare("
                INSERT INTO votes (
                    user_id, item_id, vote_type, voter_ip, 
                    campaign_id, machine_id, qr_code_id, user_agent, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $voteData['user_id'],
                $voteData['item_id'], 
                $voteData['vote_type'],
                $voteData['voter_ip'],
                $voteData['campaign_id'],
                $voteData['machine_id'],
                $voteData['qr_code_id'],
                $voteData['user_agent']
            ]);
            
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            
            return $result ? $this->pdo->lastInsertId() : false;
            
        } catch (Exception $e) {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            error_log("VotingService::insertVote Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
EOF

echo "Voting service created successfully!" 