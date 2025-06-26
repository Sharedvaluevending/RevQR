<?php
/**
 * Dynamic Horse Racing API
 * Handles all API requests for the enhanced horse racing system
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../core/config.php';
require_once 'dynamic_horses.php';
require_once 'enhanced_race_engine.php';

class HorseRacingAPI {
    private $pdo;
    private $horseSystem;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->horseSystem = new DynamicHorseSystem($pdo);
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_GET['action'] ?? '';
        
        try {
            switch ($path) {
                case 'horses':
                    return $this->getHorses();
                    
                case 'horse-details':
                    return $this->getHorseDetails($_GET['id'] ?? null);
                    
                case 'current-race':
                    return $this->getCurrentRace();
                    
                case 'place-bet':
                    if ($method !== 'POST') throw new Exception('POST method required');
                    return $this->placeBet();
                    
                case 'race-results':
                    return $this->getRaceResults($_GET['date'] ?? null, $_GET['race_index'] ?? null);
                    
                case 'user-bets':
                    return $this->getUserBets($_GET['user_id'] ?? null);
                    
                case 'simulate-race':
                    if ($method !== 'POST') throw new Exception('POST method required');
                    return $this->simulateRace();
                    
                case 'race-conditions':
                    return $this->getRaceConditions();
                    
                case 'leaderboard':
                    return $this->getLeaderboard();
                    
                case 'horse-stats':
                    return $this->getHorseStats($_GET['horse_id'] ?? null);
                    
                default:
                    throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
    
    private function getHorses() {
        $horses = $this->horseSystem->getAllHorsesWithStats();
        
        // Add additional data for each horse
        foreach ($horses as &$horse) {
            $horse['form'] = $this->getHorseForm($horse['id']);
            $horse['odds'] = $this->calculateOdds($horse['id'], $horse['current_stats']);
        }
        
        return $this->successResponse($horses);
    }
    
    private function getHorseDetails($horseId) {
        if (!$horseId) {
            throw new Exception('Horse ID required');
        }
        
        $horse = $this->horseSystem->getHorseWithStats($horseId);
        if (!$horse) {
            throw new Exception('Horse not found');
        }
        
        $recentRaces = $this->getRecentRaces($horseId, 10);
        $performance = $this->getPerformanceHistory($horseId);
        
        return $this->successResponse([
            'horse' => $horse,
            'recent_races' => $recentRaces,
            'performance_history' => $performance,
            'form' => $this->getHorseForm($horseId),
            'odds' => $this->calculateOdds($horseId, $horse['current_stats'])
        ]);
    }
    
    private function getCurrentRace() {
        $currentTime = new DateTime();
        $raceSchedule = $this->getRaceSchedule();
        
        foreach ($raceSchedule as $index => $race) {
            $raceStart = new DateTime($race['start_time']);
            $raceEnd = new DateTime($race['end_time']);
            
            if ($currentTime >= $raceStart && $currentTime <= $raceEnd) {
                $horses = $this->horseSystem->getAllHorsesWithStats();
                $conditions = $this->generateRaceConditions();
                
                // Calculate dynamic odds based on current stats and conditions
                foreach ($horses as &$horse) {
                    $horse['odds'] = $this->calculateOdds($horse['id'], $horse['current_stats'], $conditions);
                }
                
                return $this->successResponse([
                    'race' => $race,
                    'race_index' => $index,
                    'horses' => $horses,
                    'conditions' => $conditions,
                    'time_remaining' => $raceEnd->getTimestamp() - $currentTime->getTimestamp()
                ]);
            }
        }
        
        // No current race, return next race
        $nextRace = $this->getNextRace();
        return $this->successResponse([
            'current_race' => null,
            'next_race' => $nextRace
        ]);
    }
    
    private function placeBet() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = $input['user_id'] ?? null;
        $horseId = $input['horse_id'] ?? null;
        $betAmount = $input['bet_amount'] ?? null;
        $betType = $input['bet_type'] ?? 'win';
        
        if (!$userId || !$horseId || !$betAmount) {
            throw new Exception('Missing required fields');
        }
        
        // Validate user has sufficient balance
        $userBalance = $this->getUserBalance($userId);
        if ($userBalance < $betAmount) {
            throw new Exception('Insufficient balance');
        }
        
        // Get current race
        $currentRace = $this->getCurrentRace();
        if (!$currentRace['success'] || !$currentRace['data']['race']) {
            throw new Exception('No active race');
        }
        
        $raceData = $currentRace['data'];
        $horse = null;
        foreach ($raceData['horses'] as $h) {
            if ($h['id'] == $horseId) {
                $horse = $h;
                break;
            }
        }
        
        if (!$horse) {
            throw new Exception('Horse not found in current race');
        }
        
        // Calculate potential winnings
        $odds = $horse['odds'];
        $potentialWinnings = $betAmount * $odds;
        
        // Place the bet
        $stmt = $this->pdo->prepare("
            INSERT INTO quick_race_bets 
            (user_id, race_date, race_index, horse_index, horse_id, horse_name, 
             jockey_name, bet_amount, potential_winnings, bet_type, odds_multiplier)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            date('Y-m-d'),
            $raceData['race_index'],
            array_search($horse, $raceData['horses']),
            $horseId,
            $horse['name'],
            $horse['jockey'] ?? 'Unknown',
            $betAmount,
            $potentialWinnings,
            $betType,
            $odds
        ]);
        
        // Deduct from user balance
        $this->updateUserBalance($userId, -$betAmount);
        
        return $this->successResponse([
            'bet_id' => $this->pdo->lastInsertId(),
            'message' => 'Bet placed successfully',
            'potential_winnings' => $potentialWinnings
        ]);
    }
    
    private function simulateRace() {
        $input = json_decode(file_get_contents('php://input'), true);
        $raceDate = $input['race_date'] ?? date('Y-m-d');
        $raceIndex = $input['race_index'] ?? 0;
        
        $horses = $this->horseSystem->getAllHorsesWithStats();
        $conditions = $this->generateRaceConditions();
        
        // Run the race simulation
        $results = $this->horseSystem->simulateRace($horses, $conditions);
        
        // Update horse performance based on results
        $this->horseSystem->updateHorsePerformance($results, $raceDate, $raceIndex, $conditions);
        
        // Save race results
        $this->saveRaceResults($raceDate, $raceIndex, $results, $conditions);
        
        // Process bets
        $this->processBets($raceDate, $raceIndex, $results);
        
        return $this->successResponse([
            'results' => $results,
            'conditions' => $conditions,
            'winner' => $results[0]
        ]);
    }
    
    private function getRaceResults($date = null, $raceIndex = null) {
        $date = $date ?? date('Y-m-d');
        
        $sql = "SELECT * FROM quick_race_results WHERE race_date = ?";
        $params = [$date];
        
        if ($raceIndex !== null) {
            $sql .= " AND race_index = ?";
            $params[] = $raceIndex;
        }
        
        $sql .= " ORDER BY race_index DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($results as &$result) {
            $result['race_results'] = json_decode($result['race_results'], true);
            if (isset($result['race_conditions'])) {
                $result['race_conditions'] = json_decode($result['race_conditions'], true);
            }
        }
        
        return $this->successResponse($results);
    }
    
    private function getUserBets($userId) {
        if (!$userId) {
            throw new Exception('User ID required');
        }
        
        $stmt = $this->pdo->prepare("
            SELECT b.*, r.race_results, r.winning_horse_name
            FROM quick_race_bets b
            LEFT JOIN quick_race_results r ON (b.race_date = r.race_date AND b.race_index = r.race_index)
            WHERE b.user_id = ?
            ORDER BY b.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        $bets = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($bets as &$bet) {
            if ($bet['race_result']) {
                $bet['race_result'] = json_decode($bet['race_result'], true);
            }
            if ($bet['race_results']) {
                $bet['race_results'] = json_decode($bet['race_results'], true);
            }
        }
        
        return $this->successResponse($bets);
    }
    
    private function getRaceConditions() {
        return $this->successResponse($this->generateRaceConditions());
    }
    
    private function getLeaderboard() {
        // Top horses by win rate
        $horses = $this->horseSystem->getAllHorsesWithStats();
        $leaderboard = [];
        
        foreach ($horses as $horse) {
            if ($horse['current_stats']['total_races'] >= 3) {
                $winPercentage = ($horse['current_stats']['total_wins'] * 100.0) / $horse['current_stats']['total_races'];
                $leaderboard[] = [
                    'horse' => $horse,
                    'win_percentage' => round($winPercentage, 1)
                ];
            }
        }
        
        // Sort by win percentage
        usort($leaderboard, function($a, $b) {
            return $b['win_percentage'] <=> $a['win_percentage'];
        });
        
        return $this->successResponse(array_slice($leaderboard, 0, 10));
    }
    
    private function getHorseStats($horseId) {
        if (!$horseId) {
            throw new Exception('Horse ID required');
        }
        
        $horse = $this->horseSystem->getHorseWithStats($horseId);
        $recentPerformance = $this->getRecentRaces($horseId, 5);
        
        return $this->successResponse([
            'horse' => $horse,
            'recent_performance' => $recentPerformance,
            'form' => $this->getHorseForm($horseId)
        ]);
    }
    
    // Helper methods
    private function getHorseForm($horseId, $races = 5) {
        $stmt = $this->pdo->prepare("
            SELECT position FROM horse_performance 
            WHERE horse_id = ? 
            ORDER BY race_date DESC, race_index DESC 
            LIMIT ?
        ");
        $stmt->execute([$horseId, $races]);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $form = '';
        foreach ($results as $position) {
            if ($position == 1) $form .= 'W';
            elseif ($position <= 3) $form .= 'P';
            else $form .= 'L';
        }
        
        return $form;
    }
    
    private function calculateOdds($horseId, $stats, $conditions = null) {
        $baseOdds = 2.0;
        
        // Adjust based on win rate
        if ($stats['total_races'] > 0) {
            $winRate = $stats['total_wins'] / $stats['total_races'];
            $baseOdds = max(1.1, 5.0 - ($winRate * 4.0));
        }
        
        // Adjust for current form
        $form = $this->getHorseForm($horseId, 3);
        $winCount = substr_count($form, 'W');
        if ($winCount >= 2) $baseOdds *= 0.8; // Better odds for good form
        elseif ($winCount == 0) $baseOdds *= 1.3; // Worse odds for poor form
        
        // Adjust for fatigue
        $fatigueMultiplier = 1.0 + ($stats['fatigue_level'] / 100.0);
        $baseOdds *= $fatigueMultiplier;
        
        // Adjust for confidence
        $confidenceMultiplier = 1.0 + ((50 - $stats['confidence_level']) / 100.0);
        $baseOdds *= $confidenceMultiplier;
        
        return round($baseOdds, 2);
    }
    
    private function generateRaceConditions() {
        $weather = ['sunny', 'cloudy', 'rainy', 'windy'][array_rand(['sunny', 'cloudy', 'rainy', 'windy'])];
        $track = ['fast', 'good', 'soft', 'heavy'][array_rand(['fast', 'good', 'soft', 'heavy'])];
        $timeOfDay = date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening');
        
        return [
            'weather' => $weather,
            'track' => $track,
            'time_of_day' => $timeOfDay,
            'temperature' => rand(15, 30)
        ];
    }
    
    private function getRaceSchedule() {
        // Return the same schedule as in enhanced_quick_races.php
        return [
            ['name' => 'Dawn Dash', 'start_time' => date('Y-m-d') . ' 06:00:00', 'end_time' => date('Y-m-d') . ' 06:05:00'],
            ['name' => 'Morning Glory', 'start_time' => date('Y-m-d') . ' 09:00:00', 'end_time' => date('Y-m-d') . ' 09:05:00'],
            ['name' => 'Lunch Rush', 'start_time' => date('Y-m-d') . ' 12:00:00', 'end_time' => date('Y-m-d') . ' 12:05:00'],
            ['name' => 'Afternoon Delight', 'start_time' => date('Y-m-d') . ' 15:00:00', 'end_time' => date('Y-m-d') . ' 15:05:00'],
            ['name' => 'Evening Express', 'start_time' => date('Y-m-d') . ' 18:00:00', 'end_time' => date('Y-m-d') . ' 18:05:00'],
            ['name' => 'Night Rider', 'start_time' => date('Y-m-d') . ' 21:00:00', 'end_time' => date('Y-m-d') . ' 21:05:00']
        ];
    }
    
    private function getNextRace() {
        $currentTime = new DateTime();
        $schedule = $this->getRaceSchedule();
        
        foreach ($schedule as $index => $race) {
            $raceStart = new DateTime($race['start_time']);
            if ($currentTime < $raceStart) {
                return [
                    'race' => $race,
                    'race_index' => $index,
                    'time_until_start' => $raceStart->getTimestamp() - $currentTime->getTimestamp()
                ];
            }
        }
        
        // No more races today, return tomorrow's first race
        $tomorrow = new DateTime('tomorrow');
        $firstRace = $schedule[0];
        $firstRace['start_time'] = $tomorrow->format('Y-m-d') . ' 06:00:00';
        $firstRace['end_time'] = $tomorrow->format('Y-m-d') . ' 06:05:00';
        
        return [
            'race' => $firstRace,
            'race_index' => 0,
            'time_until_start' => strtotime($firstRace['start_time']) - time()
        ];
    }
    
    private function getUserBalance($userId) {
        $stmt = $this->pdo->prepare("SELECT qr_coins FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() ?: 0;
    }
    
    private function updateUserBalance($userId, $amount) {
        $stmt = $this->pdo->prepare("UPDATE users SET qr_coins = qr_coins + ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);
    }
    
    private function getRecentRaces($horseId, $limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM horse_performance 
            WHERE horse_id = ? 
            ORDER BY race_date DESC, race_index DESC 
            LIMIT ?
        ");
        $stmt->execute([$horseId, $limit]);
        return $stmt->fetchAll();
    }
    
    private function getPerformanceHistory($horseId) {
        $stmt = $this->pdo->prepare("
            SELECT race_date, AVG(position) as avg_position, COUNT(*) as races
            FROM horse_performance 
            WHERE horse_id = ? 
            GROUP BY race_date 
            ORDER BY race_date DESC 
            LIMIT 30
        ");
        $stmt->execute([$horseId]);
        return $stmt->fetchAll();
    }
    
    private function saveRaceResults($raceDate, $raceIndex, $results, $conditions) {
        $winner = $results[0];
        
        $stmt = $this->pdo->prepare("
            INSERT INTO quick_race_results 
            (race_date, race_index, race_name, race_start_time, race_end_time,
             winning_horse_id, winning_horse_name, winning_jockey_name,
             race_results, race_conditions)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            race_results = VALUES(race_results),
            race_conditions = VALUES(race_conditions)
        ");
        
        $raceName = $this->getRaceSchedule()[$raceIndex]['name'] ?? 'Race ' . ($raceIndex + 1);
        
        $stmt->execute([
            $raceDate,
            $raceIndex,
            $raceName,
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s', strtotime('+5 minutes')),
            $winner['horse']['id'],
            $winner['horse']['name'],
            $winner['horse']['jockey'] ?? 'Unknown',
            json_encode($results),
            json_encode($conditions)
        ]);
    }
    
    private function processBets($raceDate, $raceIndex, $results) {
        // Get all bets for this race
        $stmt = $this->pdo->prepare("
            SELECT * FROM quick_race_bets 
            WHERE race_date = ? AND race_index = ? AND status = 'pending'
        ");
        $stmt->execute([$raceDate, $raceIndex]);
        $bets = $stmt->fetchAll();
        
        $winnerId = $results[0]['horse']['id'];
        
        foreach ($bets as $bet) {
            $winnings = 0;
            $status = 'lost';
            
            if ($bet['horse_id'] == $winnerId) {
                $winnings = $bet['potential_winnings'];
                $status = 'won';
                
                // Credit user account
                $this->updateUserBalance($bet['user_id'], $winnings);
            }
            
            // Update bet record
            $updateStmt = $this->pdo->prepare("
                UPDATE quick_race_bets 
                SET actual_winnings = ?, status = ?, race_result = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $winnings,
                $status,
                json_encode($results),
                $bet['id']
            ]);
        }
    }
    
    private function successResponse($data) {
        return [
            'success' => true,
            'data' => $data,
            'timestamp' => time()
        ];
    }
    
    private function errorResponse($message) {
        return [
            'success' => false,
            'error' => $message,
            'timestamp' => time()
        ];
    }
}

// Handle the request
try {
    $api = new HorseRacingAPI($pdo);
    $response = $api->handleRequest();
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => time()
    ]);
}
?> 