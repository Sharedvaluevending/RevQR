<?php
/**
 * Quick Races AJAX Endpoint
 * Provides real-time race updates
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/race_simulator.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$response = ['success' => false];

try {
    $simulator = new QuickRaceSimulator($pdo);
    
    switch ($action) {
        case 'race_status':
            $race_date = $_GET['race_date'] ?? date('Y-m-d');
            $race_index = (int)($_GET['race_index'] ?? 0);
            
            $progress = $simulator->getLiveRaceProgress($race_date, $race_index);
            
            // Get race results if finished
            $results = null;
            if ($progress['status'] == 'finished') {
                $stmt = $pdo->prepare("
                    SELECT * FROM quick_race_results 
                    WHERE race_date = ? AND race_index = ?
                ");
                $stmt->execute([$race_date, $race_index]);
                $results = $stmt->fetch();
            }
            
            $response = [
                'success' => true,
                'progress' => $progress,
                'results' => $results
            ];
            break;
            
        case 'check_races':
            // Trigger race simulation check
            $simulator->checkAndSimulateActiveRaces();
            $response = ['success' => true, 'message' => 'Races checked'];
            break;
            
        case 'get_current_race':
            $current_time = new DateTime();
            $current_date = $current_time->format('Y-m-d');
            
            $race_schedule = [
                ['time' => '09:35:00', 'name' => 'Morning Sprint'],
                ['time' => '12:00:00', 'name' => 'Lunch Rush'],
                ['time' => '18:10:00', 'name' => 'Evening Thunder'],
                ['time' => '21:05:00', 'name' => 'Night Lightning'],
                ['time' => '02:10:00', 'name' => 'Midnight Express'],
                ['time' => '05:10:00', 'name' => 'Dawn Dash']
            ];
            
            $current_race = null;
            $next_race = null;
            
            foreach ($race_schedule as $index => $race) {
                $race_datetime = new DateTime($current_date . ' ' . $race['time']);
                $race_end = clone $race_datetime;
                $race_end->add(new DateInterval('PT1M'));
                
                if ($current_time >= $race_datetime && $current_time <= $race_end) {
                    $current_race = array_merge($race, ['index' => $index]);
                    break;
                } elseif ($current_time < $race_datetime) {
                    $next_race = array_merge($race, ['index' => $index]);
                    break;
                }
            }
            
            $response = [
                'success' => true,
                'current_race' => $current_race,
                'next_race' => $next_race
            ];
            break;
            
        case 'get_balance':
            session_start();
            if (!isset($_SESSION['user_id'])) {
                $response = ['success' => false, 'error' => 'Not authenticated'];
                break;
            }
            
            $user_id = $_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT qr_coins FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $balance = $stmt->fetchColumn();
            
            if ($balance !== false) {
                $response = [
                    'success' => true,
                    'balance' => (int)$balance
                ];
            } else {
                $response = ['success' => false, 'error' => 'User not found'];
            }
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Invalid action'];
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);
?> 