<?php
/**
 * Horse Racing Results Processor
 * Advanced payout calculation for all betting types
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require admin role
require_role('admin');

class RaceResultsProcessor {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Process race results and calculate payouts for all bet types
     */
    public function processRaceResults($race_id, $finishing_order) {
        try {
            $this->pdo->beginTransaction();
            
            // Get race details
            $race = $this->getRaceDetails($race_id);
            if (!$race) {
                throw new Exception("Race not found");
            }
            
            // Validate finishing order
            $horses = $this->getRaceHorses($race_id);
            if (count($finishing_order) < count($horses)) {
                throw new Exception("Incomplete finishing order");
            }
            
            // Insert race results
            $this->insertRaceResults($race_id, $finishing_order);
            
            // Process all bet types
            $this->processWinBets($race_id, $finishing_order);
            $this->processPlaceBets($race_id, $finishing_order);
            $this->processShowBets($race_id, $finishing_order);
            $this->processExactaBets($race_id, $finishing_order);
            $this->processQuinellaBets($race_id, $finishing_order);
            $this->processTrifectaBets($race_id, $finishing_order);
            $this->processSuperfectaBets($race_id, $finishing_order);
            $this->processDailyDoubleBets($race_id, $finishing_order);
            
            // Update race status to completed
            $this->updateRaceStatus($race_id, 'completed');
            
            // Update user racing stats
            $this->updateUserRacingStats($race_id);
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Race results processed successfully'];
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function getRaceDetails($race_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM business_races WHERE id = ?");
        $stmt->execute([$race_id]);
        return $stmt->fetch();
    }
    
    private function getRaceHorses($race_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM race_horses WHERE race_id = ? ORDER BY id");
        $stmt->execute([$race_id]);
        return $stmt->fetchAll();
    }
    
    private function insertRaceResults($race_id, $finishing_order) {
        $stmt = $this->pdo->prepare("
            INSERT INTO race_results (race_id, horse_id, finish_position, result_time)
            VALUES (?, ?, ?, NOW())
        ");
        
        foreach ($finishing_order as $position => $horse_id) {
            $stmt->execute([$race_id, $horse_id, $position + 1]);
        }
    }
    
    /**
     * WIN BETS: Horse must finish 1st
     */
    private function processWinBets($race_id, $finishing_order) {
        $winner_id = $finishing_order[0];
        
        $stmt = $this->pdo->prepare("
            SELECT rb.*, u.username 
            FROM race_bets rb 
            JOIN users u ON rb.user_id = u.id
            WHERE rb.race_id = ? AND rb.bet_type = 'win' AND rb.horse_id = ?
        ");
        $stmt->execute([$race_id, $winner_id]);
        $winning_bets = $stmt->fetchAll();
        
        foreach ($winning_bets as $bet) {
            $this->payoutBet($bet, 'won');
        }
        
        // Mark losing win bets
        $stmt = $this->pdo->prepare("
            UPDATE race_bets 
            SET status = 'lost', result_processed_at = NOW()
            WHERE race_id = ? AND bet_type = 'win' AND horse_id != ? AND status = 'pending'
        ");
        $stmt->execute([$race_id, $winner_id]);
    }
    
    /**
     * PLACE BETS: Horse must finish 1st or 2nd
     */
    private function processPlaceBets($race_id, $finishing_order) {
        $place_horses = array_slice($finishing_order, 0, 2);
        
        foreach ($place_horses as $horse_id) {
            $stmt = $this->pdo->prepare("
                SELECT rb.*, u.username 
                FROM race_bets rb 
                JOIN users u ON rb.user_id = u.id
                WHERE rb.race_id = ? AND rb.bet_type = 'place' AND rb.horse_id = ?
            ");
            $stmt->execute([$race_id, $horse_id]);
            $winning_bets = $stmt->fetchAll();
            
            foreach ($winning_bets as $bet) {
                $this->payoutBet($bet, 'won');
            }
        }
        
        // Mark losing place bets
        $place_horses_str = implode(',', $place_horses);
        $stmt = $this->pdo->prepare("
            UPDATE race_bets 
            SET status = 'lost', result_processed_at = NOW()
            WHERE race_id = ? AND bet_type = 'place' 
            AND horse_id NOT IN ($place_horses_str) AND status = 'pending'
        ");
        $stmt->execute([$race_id]);
    }
    
    /**
     * SHOW BETS: Horse must finish 1st, 2nd, or 3rd
     */
    private function processShowBets($race_id, $finishing_order) {
        $show_horses = array_slice($finishing_order, 0, 3);
        
        foreach ($show_horses as $horse_id) {
            $stmt = $this->pdo->prepare("
                SELECT rb.*, u.username 
                FROM race_bets rb 
                JOIN users u ON rb.user_id = u.id
                WHERE rb.race_id = ? AND rb.bet_type = 'show' AND rb.horse_id = ?
            ");
            $stmt->execute([$race_id, $horse_id]);
            $winning_bets = $stmt->fetchAll();
            
            foreach ($winning_bets as $bet) {
                $this->payoutBet($bet, 'won');
            }
        }
        
        // Mark losing show bets
        $show_horses_str = implode(',', $show_horses);
        $stmt = $this->pdo->prepare("
            UPDATE race_bets 
            SET status = 'lost', result_processed_at = NOW()
            WHERE race_id = ? AND bet_type = 'show' 
            AND horse_id NOT IN ($show_horses_str) AND status = 'pending'
        ");
        $stmt->execute([$race_id]);
    }
    
    /**
     * EXACTA BETS: Pick 1st and 2nd in exact order
     */
    private function processExactaBets($race_id, $finishing_order) {
        $first = $finishing_order[0];
        $second = $finishing_order[1];
        $winning_combination = [$first, $second];
        
        $stmt = $this->pdo->prepare("
            SELECT rb.*, u.username 
            FROM race_bets rb 
            JOIN users u ON rb.user_id = u.id
            WHERE rb.race_id = ? AND rb.bet_type = 'exacta' AND rb.status = 'pending'
        ");
        $stmt->execute([$race_id]);
        $exacta_bets = $stmt->fetchAll();
        
        foreach ($exacta_bets as $bet) {
            $selections = json_decode($bet['horse_selections'], true);
            if ($selections && count($selections) >= 2) {
                if ($selections[0] == $first && $selections[1] == $second) {
                    $this->payoutBet($bet, 'won');
                } else {
                    $this->updateBetStatus($bet['id'], 'lost');
                }
            }
        }
    }
    
    /**
     * QUINELLA BETS: Pick 1st and 2nd in any order
     */
    private function processQuinellaBets($race_id, $finishing_order) {
        $first = $finishing_order[0];
        $second = $finishing_order[1];
        
        $stmt = $this->pdo->prepare("
            SELECT rb.*, u.username 
            FROM race_bets rb 
            JOIN users u ON rb.user_id = u.id
            WHERE rb.race_id = ? AND rb.bet_type = 'quinella' AND rb.status = 'pending'
        ");
        $stmt->execute([$race_id]);
        $quinella_bets = $stmt->fetchAll();
        
        foreach ($quinella_bets as $bet) {
            $selections = json_decode($bet['horse_selections'], true);
            if ($selections && count($selections) >= 2) {
                $bet_horses = array_slice($selections, 0, 2);
                $top_two = [$first, $second];
                
                // Check if bet horses match top two in any order
                if (empty(array_diff($bet_horses, $top_two))) {
                    $this->payoutBet($bet, 'won');
                } else {
                    $this->updateBetStatus($bet['id'], 'lost');
                }
            }
        }
    }
    
    /**
     * TRIFECTA BETS: Pick 1st, 2nd, and 3rd in exact order
     */
    private function processTrifectaBets($race_id, $finishing_order) {
        $first = $finishing_order[0];
        $second = $finishing_order[1];
        $third = $finishing_order[2];
        
        $stmt = $this->pdo->prepare("
            SELECT rb.*, u.username 
            FROM race_bets rb 
            JOIN users u ON rb.user_id = u.id
            WHERE rb.race_id = ? AND rb.bet_type = 'trifecta' AND rb.status = 'pending'
        ");
        $stmt->execute([$race_id]);
        $trifecta_bets = $stmt->fetchAll();
        
        foreach ($trifecta_bets as $bet) {
            $selections = json_decode($bet['horse_selections'], true);
            if ($selections && count($selections) >= 3) {
                if ($selections[0] == $first && $selections[1] == $second && $selections[2] == $third) {
                    $this->payoutBet($bet, 'won');
                } else {
                    $this->updateBetStatus($bet['id'], 'lost');
                }
            }
        }
    }
    
    /**
     * SUPERFECTA BETS: Pick 1st, 2nd, 3rd, and 4th in exact order
     */
    private function processSuperfectaBets($race_id, $finishing_order) {
        if (count($finishing_order) < 4) return; // Need at least 4 horses
        
        $first = $finishing_order[0];
        $second = $finishing_order[1];
        $third = $finishing_order[2];
        $fourth = $finishing_order[3];
        
        $stmt = $this->pdo->prepare("
            SELECT rb.*, u.username 
            FROM race_bets rb 
            JOIN users u ON rb.user_id = u.id
            WHERE rb.race_id = ? AND rb.bet_type = 'superfecta' AND rb.status = 'pending'
        ");
        $stmt->execute([$race_id]);
        $superfecta_bets = $stmt->fetchAll();
        
        foreach ($superfecta_bets as $bet) {
            $selections = json_decode($bet['horse_selections'], true);
            if ($selections && count($selections) >= 4) {
                if ($selections[0] == $first && $selections[1] == $second && 
                    $selections[2] == $third && $selections[3] == $fourth) {
                    $this->payoutBet($bet, 'won');
                } else {
                    $this->updateBetStatus($bet['id'], 'lost');
                }
            }
        }
    }
    
    /**
     * DAILY DOUBLE BETS: Pick winners of two consecutive races
     */
    private function processDailyDoubleBets($race_id, $finishing_order) {
        $winner_id = $finishing_order[0];
        
        // Find next race for daily double completion
        $stmt = $this->pdo->prepare("
            SELECT rb.*, u.username 
            FROM race_bets rb 
            JOIN users u ON rb.user_id = u.id
            WHERE rb.race_id = ? AND rb.bet_type = 'daily_double' AND rb.status = 'pending'
        ");
        $stmt->execute([$race_id]);
        $daily_double_bets = $stmt->fetchAll();
        
        foreach ($daily_double_bets as $bet) {
            if ($bet['horse_id'] == $winner_id) {
                // For now, mark as won - in full implementation would check next race
                $this->payoutBet($bet, 'won');
            } else {
                $this->updateBetStatus($bet['id'], 'lost');
            }
        }
    }
    
    private function payoutBet($bet, $status) {
        // Calculate actual winnings (may be different from potential)
        $actual_winnings = $bet['potential_winnings'];
        
        // Pay the user
        QRCoinManager::addBalance(
            $bet['user_id'], 
            $actual_winnings, 
            'horse_racing_win', 
            "Won {$bet['bet_type']} bet on race {$bet['race_id']}"
        );
        
        // Update bet record
        $stmt = $this->pdo->prepare("
            UPDATE race_bets 
            SET status = ?, actual_winnings = ?, result_processed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $actual_winnings, $bet['id']]);
    }
    
    private function updateBetStatus($bet_id, $status) {
        $stmt = $this->pdo->prepare("
            UPDATE race_bets 
            SET status = ?, result_processed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $bet_id]);
    }
    
    private function updateRaceStatus($race_id, $status) {
        $stmt = $this->pdo->prepare("
            UPDATE business_races 
            SET status = ?, completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $race_id]);
    }
    
    private function updateUserRacingStats($race_id) {
        // Get all users who participated in this race
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT user_id FROM race_bets WHERE race_id = ?
        ");
        $stmt->execute([$race_id]);
        $participants = $stmt->fetchAll();
        
        foreach ($participants as $participant) {
            $user_id = $participant['user_id'];
            
            // Calculate user stats for this race
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_bets,
                    COUNT(CASE WHEN status = 'won' THEN 1 END) as winning_bets,
                    SUM(CASE WHEN status = 'won' THEN actual_winnings ELSE 0 END) as total_winnings
                FROM race_bets 
                WHERE race_id = ? AND user_id = ?
            ");
            $stmt->execute([$race_id, $user_id]);
            $race_stats = $stmt->fetch();
            
            // Update or insert user racing stats
            $stmt = $this->pdo->prepare("
                INSERT INTO user_racing_stats (user_id, total_races_participated, total_qr_coins_won, total_bets_placed, total_wins)
                VALUES (?, 1, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_races_participated = total_races_participated + 1,
                    total_qr_coins_won = total_qr_coins_won + VALUES(total_qr_coins_won),
                    total_bets_placed = total_bets_placed + VALUES(total_bets_placed),
                    total_wins = total_wins + VALUES(total_wins),
                    win_rate = (total_wins / total_bets_placed) * 100
            ");
            $stmt->execute([
                $user_id, 
                $race_stats['total_winnings'],
                $race_stats['total_bets'],
                $race_stats['winning_bets']
            ]);
        }
    }
}

// Handle processing request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $race_id = intval($_POST['race_id'] ?? 0);
    $finishing_order = $_POST['finishing_order'] ?? [];
    
    if ($race_id && !empty($finishing_order)) {
        $processor = new RaceResultsProcessor($pdo);
        $result = $processor->processRaceResults($race_id, $finishing_order);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}

// Show active races
$stmt = $pdo->prepare("
    SELECT br.*, b.name as business_name, COUNT(rh.id) as horse_count
    FROM business_races br
    JOIN businesses b ON br.business_id = b.id
    LEFT JOIN race_horses rh ON br.id = rh.race_id
    WHERE br.status = 'active'
    GROUP BY br.id
    ORDER BY br.end_time ASC
");
$stmt->execute();
$active_races = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container">
    <h2 style="color: #fff;">🏆 Process Race Results</h2>
    
    <?php if (empty($active_races)): ?>
        <div class="text-center py-5">
            <h4 class="text-muted">No active races to process</h4>
        </div>
    <?php else: ?>
        <?php foreach ($active_races as $race): ?>
            <div class="card mb-3" style="background: rgba(255, 255, 255, 0.12); color: #fff;">
                <div class="card-body">
                    <h4><?php echo htmlspecialchars($race['race_name']); ?></h4>
                    <p><?php echo $race['horse_count']; ?> horses competing</p>
                    <a href="enter-results.php?race_id=<?php echo $race['id']; ?>" class="btn btn-success">
                        Enter Results
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 