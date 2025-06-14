<?php
/**
 * Enhanced Voting Service
 * Implements Daily + Weekly + Premium Vote Structure
 * 
 * Vote Structure:
 * - 1 FREE daily vote (30 QR coins: 5 base + 25 daily bonus)
 * - 2 FREE weekly bonus votes (5 QR coins each, no daily bonus)
 * - UNLIMITED premium votes (cost: 50 QR coins each, earn 5 QR coins back)
 */

class VotingService {
    private static $pdo;
    
    // Standard vote type mappings
    const VOTE_TYPE_IN = 'vote_in';
    const VOTE_TYPE_OUT = 'vote_out';
    
    // Vote limit constants
    const DAILY_FREE_VOTES = 1;
    const WEEKLY_BONUS_VOTES = 2;
    const PREMIUM_VOTE_COST = 50; // QR coins // QR coins
    const VOTE_BASE_REWARD = 5;   // QR coins
    const DAILY_BONUS_REWARD = 25; // QR coins (only for daily free vote)
    
    /**
     * Initialize the service with PDO connection
     */
    public static function init($pdo_connection) {
        self::$pdo = $pdo_connection;
    }
    
    /**
     * Normalize vote type to database enum values
     * 
     * @param string $vote_type Raw vote type from frontend
     * @return string Normalized vote type for database
     */
    public static function normalizeVoteType($vote_type) {
        $vote_type = strtolower(trim($vote_type));
        
        switch ($vote_type) {
            case 'in':
            case 'vote_in':
            case 'yes':
            case 'up':
            case 'like':
                return self::VOTE_TYPE_IN;
                
            case 'out':
            case 'vote_out':
            case 'no':
            case 'down':
            case 'dislike':
                return self::VOTE_TYPE_OUT;
                
            default:
                throw new InvalidArgumentException("Invalid vote type: {$vote_type}");
        }
    }
    
    /**
     * Get user's current vote status for today and this week
     * SECURITY ENHANCED: Prevents IP switching exploitation
     */
    public static function getUserVoteStatus($user_id, $voter_ip) {
        try {
            $today = date('Y-m-d');
            $week_start = date('Y-m-d', strtotime('monday this week'));
            
            // SECURITY FIX: Primary check by user_id (if logged in)
            $daily_votes = 0;
            $weekly_votes = 0;
            
            if ($user_id) {
                // For logged-in users: Count by user_id ONLY (prevents IP switching exploit)
                $stmt = self::$pdo->prepare("
                    SELECT COUNT(*) as daily_votes
                    FROM votes 
                    WHERE user_id = ? AND DATE(created_at) = ?
                ");
                $stmt->execute([$user_id, $today]);
                $daily_votes = (int) $stmt->fetchColumn();
                
                // Get this week's votes (excluding today's)
                $stmt = self::$pdo->prepare("
                    SELECT COUNT(*) as weekly_votes
                    FROM votes 
                    WHERE user_id = ? 
                    AND DATE(created_at) >= ?
                    AND DATE(created_at) != ?
                ");
                $stmt->execute([$user_id, $week_start, $today]);
                $weekly_votes = (int) $stmt->fetchColumn();
            } else {
                // For guest users: IP-based limits only (more restrictive)
                $stmt = self::$pdo->prepare("
                    SELECT COUNT(*) as daily_votes
                    FROM votes 
                    WHERE voter_ip = ? AND user_id IS NULL AND DATE(created_at) = ?
                ");
                $stmt->execute([$voter_ip, $today]);
                $daily_votes = (int) $stmt->fetchColumn();
                
                $stmt = self::$pdo->prepare("
                    SELECT COUNT(*) as weekly_votes
                    FROM votes 
                    WHERE voter_ip = ? AND user_id IS NULL
                    AND DATE(created_at) >= ?
                    AND DATE(created_at) != ?
                ");
                $stmt->execute([$voter_ip, $week_start, $today]);
                $weekly_votes = (int) $stmt->fetchColumn();
            }
            
            // SECURITY CHECK: Cross-validate with IP limits for logged-in users
            if ($user_id) {
                $stmt = self::$pdo->prepare("
                    SELECT COUNT(*) as ip_votes_today
                    FROM votes 
                    WHERE voter_ip = ? AND DATE(created_at) = ?
                ");
                $stmt->execute([$voter_ip, $today]);
                $ip_votes_today = (int) $stmt->fetchColumn();
                
                // If IP has excessive votes, apply additional restrictions
                if ($ip_votes_today > 10) {
                    error_log("SECURITY ALERT: IP {$voter_ip} has {$ip_votes_today} votes today for user {$user_id}");
                    // Apply stricter limits for suspicious IPs
                    $daily_votes = max($daily_votes, $ip_votes_today);
                }
            }
            
            // Get user's QR coin balance for premium votes
            $balance = 0;
            $vote_pack_votes = 0;
            if ($user_id) {
                require_once __DIR__ . '/../qr_coin_manager.php';
                $balance = QRCoinManager::getBalance($user_id);
                
                // Get vote pack votes (including expired ones for now)
                $stmt = self::$pdo->prepare("
                    SELECT COALESCE(SUM(votes_remaining), 0) as vote_pack_total
                    FROM user_vote_packs 
                    WHERE user_id = ? 
                    AND votes_remaining > 0
                    AND (expires_at IS NULL OR expires_at > NOW())
                ");
                $stmt->execute([$user_id]);
                $vote_pack_votes = (int) $stmt->fetchColumn();
            }
            
            // Calculate total premium votes available (coin purchases + vote packs)
            $premium_from_coins = floor($balance / self::PREMIUM_VOTE_COST);
            $total_premium_votes = $premium_from_coins + $vote_pack_votes;
            
            return [
                'daily_free_used' => min($daily_votes, self::DAILY_FREE_VOTES),
                'daily_free_remaining' => max(0, self::DAILY_FREE_VOTES - $daily_votes),
                'weekly_bonus_used' => min($weekly_votes, self::WEEKLY_BONUS_VOTES),
                'weekly_bonus_remaining' => max(0, self::WEEKLY_BONUS_VOTES - $weekly_votes),
                'premium_votes_available' => $total_premium_votes,
                'vote_pack_votes' => $vote_pack_votes,
                'premium_from_coins' => $premium_from_coins,
                'qr_balance' => $balance,
                'total_votes_today' => $daily_votes,
                'total_votes_this_week' => $daily_votes + $weekly_votes
            ];
            
        } catch (Exception $e) {
            error_log("VotingService::getUserVoteStatus() error: " . $e->getMessage());
            return [
                'daily_free_used' => 1,
                'daily_free_remaining' => 0,
                'weekly_bonus_used' => 2,
                'weekly_bonus_remaining' => 0,
                'premium_votes_available' => 0,
                'qr_balance' => 0,
                'total_votes_today' => 0,
                'total_votes_this_week' => 0
            ];
        }
    }
    
    /**
     * Record a vote with proper validation and limits
     */
    public static function recordVote($vote_data) {
        try {
            // Normalize vote type
            $normalized_vote_type = self::normalizeVoteType($vote_data['vote_type']);
            
            // Get user info
            $user_id = $vote_data['user_id'] ?? null;
            $voter_ip = $vote_data['voter_ip'];
            $user_agent = $vote_data['user_agent'] ?? '';
            $machine_id = $vote_data['machine_id'] ?? 0;
            $qr_code_id = $vote_data['qr_code_id'] ?? null;
            $campaign_id = $vote_data['campaign_id'] ?? null;
            $vote_method = $vote_data['vote_method'] ?? 'auto';
            
            // Get current vote status
            $vote_status = self::getUserVoteStatus($user_id, $voter_ip);
            
            // Check if user has already voted for this item today (SECURITY FIX)
            if ($user_id) {
                // For logged-in users: Check by user_id + item_id (prevents multiple votes per item)
                $stmt = self::$pdo->prepare("
                    SELECT COUNT(*) 
                    FROM votes 
                    WHERE item_id = ? 
                    AND user_id = ?
                    AND DATE(created_at) = CURDATE()
                ");
                $stmt->execute([$vote_data['item_id'], $user_id]);
                $has_voted_today = $stmt->fetchColumn() > 0;
            } else {
                // For guest users: Check by IP + item_id
                $stmt = self::$pdo->prepare("
                    SELECT COUNT(*) 
                    FROM votes 
                    WHERE item_id = ? 
                    AND voter_ip = ?
                    AND user_id IS NULL
                    AND DATE(created_at) = CURDATE()
                ");
                $stmt->execute([$vote_data['item_id'], $voter_ip]);
                $has_voted_today = $stmt->fetchColumn() > 0;
            }
            
            if ($has_voted_today) {
                return [
                    'success' => false,
                    'message' => 'You have already voted for this item today.',
                    'error_code' => 'ALREADY_VOTED'
                ];
            }
            
            // Initialize vote reward and cost
            $vote_reward = self::VOTE_BASE_REWARD;
            $vote_cost = 0;
            $is_daily_bonus = false;
            $vote_category = '';
            
            // ENHANCED VOTE LIMIT ENFORCEMENT
            // Check if user has exceeded total daily limit (1 daily + 2 weekly = max 3 per day)
            if ($vote_status['total_votes_today'] >= (self::DAILY_FREE_VOTES + self::WEEKLY_BONUS_VOTES) && $vote_method !== 'premium') {
                return [
                    'success' => false,
                    'message' => 'Daily vote limit reached (3 votes max). Use premium votes to continue.',
                    'error_code' => 'DAILY_LIMIT_EXCEEDED',
                    'suggest_premium' => true
                ];
            }
            
            // Determine vote type and validate limits
            if ($vote_method === 'premium') {
                // Premium vote logic
                if ($vote_status['premium_votes_available'] < 1) {
                    return [
                        'success' => false,
                        'message' => 'No premium votes available. Purchase vote packs or need ' . self::PREMIUM_VOTE_COST . ' coins.',
                        'error_code' => 'INSUFFICIENT_VOTES'
                    ];
                }
                
                // Use vote pack first, then QR coins
                if ($vote_status['vote_pack_votes'] > 0) {
                    $vote_cost = 0;
                    $vote_category = 'vote_pack';
                } else {
                    $vote_cost = self::PREMIUM_VOTE_COST;
                    $vote_category = 'premium';
                }
            } elseif ($vote_status['daily_free_remaining'] > 0 && $vote_method !== 'weekly') {
                // Daily free vote with bonus
                $vote_reward += self::DAILY_BONUS_REWARD;
                $is_daily_bonus = true;
                $vote_category = 'daily_free';
            } elseif ($vote_status['weekly_bonus_remaining'] > 0) {
                // Weekly bonus vote
                $vote_category = 'weekly_bonus';
            } else {
                // No free votes remaining
                return [
                    'success' => false,
                    'message' => 'No free votes remaining today. You get 1 daily + 2 weekly votes. Purchase premium votes for ' . self::PREMIUM_VOTE_COST . ' QR coins?',
                    'error_code' => 'NO_FREE_VOTES',
                    'suggest_premium' => true
                ];
            }
            
            // Process vote cost and rewards
            if ($user_id) {
                if ($vote_category === 'vote_pack') {
                    $deduction = self::useVotePackVote($user_id);
                    if (!$deduction['success']) {
                        return [
                            'success' => false,
                            'message' => $deduction['error'],
                            'error_code' => 'VOTE_PACK_ERROR'
                        ];
                    }
                } elseif ($vote_cost > 0) {
                    require_once __DIR__ . '/../qr_coin_manager.php';
                    $deduction = QRCoinManager::smartSpend(
                        $user_id, 
                        $vote_cost, 
                        'voting', 
                        'Premium vote purchase',
                        ['vote_method' => 'premium']
                    );
                    if (!$deduction['success']) {
                        return [
                            'success' => false,
                            'message' => $deduction['error'],
                            'error_code' => 'PAYMENT_FAILED'
                        ];
                    }
                }
            }
            
            // Record the vote
            $stmt = self::$pdo->prepare("
                INSERT INTO votes (
                    user_id, machine_id, qr_code_id, campaign_id, item_id, 
                    vote_type, voter_ip, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $success = $stmt->execute([
                $user_id,
                $machine_id,
                $qr_code_id,
                $campaign_id,
                $vote_data['item_id'],
                $normalized_vote_type,
                $voter_ip,
                $user_agent
            ]);
            
            if (!$success) {
                return [
                    'success' => false,
                    'message' => 'Failed to record vote. Please try again.',
                    'error_code' => 'INSERT_FAILED'
                ];
            }
            
            $vote_id = self::$pdo->lastInsertId();
            
            // Award QR coins for the vote
            if ($user_id && $vote_reward > 0) {
                require_once __DIR__ . '/../qr_coin_manager.php';
                QRCoinManager::awardVoteCoins($user_id, $vote_id, $is_daily_bonus);
            }
            
            // Prepare success message
            $message = "Vote recorded successfully! ";
            if ($vote_category === 'daily_free') {
                $message .= "Earned {$vote_reward} QR coins (daily bonus included).";
            } elseif ($vote_category === 'weekly_bonus') {
                $message .= "Earned {$vote_reward} QR coins.";
            } elseif ($vote_category === 'vote_pack') {
                $message .= "Vote pack used. Earned {$vote_reward} QR coins.";
            } else {
                $net_cost = $vote_cost - $vote_reward;
                $message .= "Premium vote used. Net cost: {$net_cost} QR coins.";
            }
            
            return [
                'success' => true,
                'message' => $message,
                'vote_id' => $vote_id,
                'vote_category' => $vote_category,
                'coins_earned' => $vote_reward,
                'coins_spent' => $vote_cost,
                'is_daily_bonus' => $is_daily_bonus
            ];
            
        } catch (Exception $e) {
            error_log("VotingService::recordVote() error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while recording your vote.',
                'error_code' => 'SYSTEM_ERROR'
            ];
        }
    }
    
    /**
     * Get vote counts for an item
     * 
     * @param int $item_id Item ID
     * @param int|null $campaign_id Optional campaign filter
     * @param int|null $machine_id Optional machine filter
     * @return array Vote counts
     */
    public static function getVoteCounts($item_id, $campaign_id = null, $machine_id = null) {
        try {
            $where_conditions = ["item_id = ?"];
            $params = [$item_id];
            
            if ($campaign_id) {
                $where_conditions[] = "campaign_id = ?";
                $params[] = $campaign_id;
            }
            
            if ($machine_id) {
                $where_conditions[] = "machine_id = ?";
                $params[] = $machine_id;
            }
            
            $where_clause = implode(" AND ", $where_conditions);
            
            $stmt = self::$pdo->prepare("
                SELECT 
                    COUNT(CASE WHEN vote_type = ? THEN 1 END) as vote_in_count,
                    COUNT(CASE WHEN vote_type = ? THEN 1 END) as vote_out_count,
                    COUNT(*) as total_votes
                FROM votes
                WHERE {$where_clause}
            ");
            
            $params = array_merge([self::VOTE_TYPE_IN, self::VOTE_TYPE_OUT], $params);
            $stmt->execute($params);
            $counts = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'vote_in_count' => (int)($counts['vote_in_count'] ?? 0),
                'vote_out_count' => (int)($counts['vote_out_count'] ?? 0),
                'total_votes' => (int)($counts['total_votes'] ?? 0)
            ];
            
        } catch (Exception $e) {
            error_log("VotingService::getVoteCounts() error: " . $e->getMessage());
            return [
                'success' => false,
                'vote_in_count' => 0,
                'vote_out_count' => 0,
                'total_votes' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get items with vote counts for a campaign or machine
     * 
     * @param int $list_id Voting list ID
     * @param int|null $campaign_id Optional campaign ID
     * @return array Items with vote counts
     */
    public static function getItemsWithVotes($list_id, $campaign_id = null) {
        try {
            $vote_filter = $campaign_id ? "AND v.campaign_id = ?" : "";
            $params = [$list_id];
            if ($campaign_id) {
                $params[] = $campaign_id;
                $params[] = $campaign_id;
            }
            
            $stmt = self::$pdo->prepare("
                SELECT 
                    i.*,
                    COUNT(CASE WHEN v.vote_type = ? THEN 1 END) as votes_in,
                    COUNT(CASE WHEN v.vote_type = ? THEN 1 END) as votes_out,
                    COUNT(v.id) as total_votes
                FROM voting_list_items i
                LEFT JOIN votes v ON i.id = v.item_id {$vote_filter}
                WHERE i.voting_list_id = ?
                GROUP BY i.id
                ORDER BY i.item_name ASC
            ");
            
            $execute_params = array_merge([self::VOTE_TYPE_IN, self::VOTE_TYPE_OUT], $params);
            $stmt->execute($execute_params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'items' => $items
            ];
            
        } catch (Exception $e) {
            error_log("VotingService::getItemsWithVotes() error: " . $e->getMessage());
            return [
                'success' => false,
                'items' => [],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get voting statistics for admin dashboard
     * 
     * @param array $filters Optional filters
     * @return array Voting statistics
     */
    public static function getVotingStats($filters = []) {
        try {
            // Basic voting stats
            $stmt = self::$pdo->prepare("
                SELECT 
                    COUNT(*) as total_votes,
                    COUNT(CASE WHEN vote_type = ? THEN 1 END) as total_votes_in,
                    COUNT(CASE WHEN vote_type = ? THEN 1 END) as total_votes_out,
                    COUNT(DISTINCT voter_ip) as unique_voters,
                    COUNT(DISTINCT item_id) as items_voted_on,
                    DATE(MIN(created_at)) as first_vote_date,
                    DATE(MAX(created_at)) as last_vote_date
                FROM votes
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute([self::VOTE_TYPE_IN, self::VOTE_TYPE_OUT]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'stats' => $stats
            ];
            
        } catch (Exception $e) {
            error_log("VotingService::getVotingStats() error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get system setting value with fallback default
     */
    private static function getSystemSetting($key, $default = null) {
        try {
            $stmt = self::$pdo->prepare("SELECT value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value !== false ? (int)$value : $default;
        } catch (Exception $e) {
            error_log("VotingService::getSystemSetting() error: " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Get count of premium votes used today by user
     */
    private static function getUserPremiumVotesToday($user_id, $voter_ip) {
        try {
            $today = date('Y-m-d');
            
            // Count votes where user paid for them (either via coins or vote packs)
            $stmt = self::$pdo->prepare("
                SELECT COUNT(*) as premium_votes_today
                FROM votes v
                LEFT JOIN qr_coin_transactions qct ON qct.reference_id = v.id AND qct.reference_type = 'vote'
                LEFT JOIN user_vote_packs uvp ON uvp.user_id = v.user_id
                WHERE (v.user_id = ? OR v.voter_ip = ?)
                AND DATE(v.created_at) = ?
                AND (
                    qct.amount < 0 OR  -- Spent coins on vote
                    uvp.id IS NOT NULL -- Has vote packs (indicating premium usage)
                )
            ");
            $stmt->execute([$user_id, $voter_ip, $today]);
            return (int) $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("VotingService::getUserPremiumVotesToday() error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Use one vote from user's vote packs (oldest first)
     */
    private static function useVotePackVote($user_id) {
        try {
            // Find oldest vote pack with remaining votes
            $stmt = self::$pdo->prepare("
                SELECT id, votes_remaining 
                FROM user_vote_packs 
                WHERE user_id = ? 
                AND votes_remaining > 0 
                AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY created_at ASC 
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $vote_pack = $stmt->fetch();
            
            if (!$vote_pack) {
                return [
                    'success' => false,
                    'error' => 'No vote pack votes available'
                ];
            }
            
            // Deduct one vote from the pack
            $stmt = self::$pdo->prepare("
                UPDATE user_vote_packs 
                SET votes_used = votes_used + 1, 
                    votes_remaining = votes_remaining - 1,
                    updated_at = NOW()
                WHERE id = ? AND votes_remaining > 0
            ");
            $stmt->execute([$vote_pack['id']]);
            
            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'error' => 'Failed to deduct vote pack vote (concurrent usage?)'
                ];
            }
            
            return [
                'success' => true,
                'vote_pack_id' => $vote_pack['id'],
                'votes_remaining' => $vote_pack['votes_remaining'] - 1
            ];
            
        } catch (Exception $e) {
            error_log("VotingService::useVotePackVote() error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Database error while using vote pack'
            ];
        }
    }
    
    /**
     * Get user's vote pack summary
     */
    public static function getUserVotePackSummary($user_id) {
        try {
            $stmt = self::$pdo->prepare("
                SELECT 
                    COUNT(*) as total_packs,
                    SUM(votes_total) as total_votes_purchased,
                    SUM(votes_used) as total_votes_used,
                    SUM(votes_remaining) as total_votes_remaining,
                    MIN(expires_at) as earliest_expiry
                FROM user_vote_packs 
                WHERE user_id = ?
                AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("VotingService::getUserVotePackSummary() error: " . $e->getMessage());
            return [
                'total_packs' => 0,
                'total_votes_purchased' => 0,
                'total_votes_used' => 0,
                'total_votes_remaining' => 0,
                'earliest_expiry' => null
            ];
        }
    }
}
