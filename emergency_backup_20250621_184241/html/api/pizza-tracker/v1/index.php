<?php
/**
 * Pizza Tracker REST API v1
 * Comprehensive mobile and third-party integration endpoints
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/pizza_tracker_utils.php';
require_once __DIR__ . '/../../../core/notification_system.php';

class PizzaTrackerAPI {
    private $pdo;
    private $pizzaTracker;
    private $notificationSystem;
    private $apiKey;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->pizzaTracker = new PizzaTracker($pdo);
        $this->notificationSystem = new PizzaTrackerNotificationSystem($pdo);
    }
    
    public function handleRequest() {
        try {
            // Parse request
            $method = $_SERVER['REQUEST_METHOD'];
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $pathParts = explode('/', trim($path, '/'));
            
            // Remove common path prefix
            $apiIndex = array_search('pizza-tracker', $pathParts);
            if ($apiIndex !== false) {
                $pathParts = array_slice($pathParts, $apiIndex + 2); // Skip 'pizza-tracker/v1'
            }
            
            $endpoint = $pathParts[0] ?? '';
            $id = $pathParts[1] ?? null;
            $action = $pathParts[2] ?? null;
            
            // Authenticate request
            if (!$this->authenticate()) {
                return $this->errorResponse('Unauthorized', 401);
            }
            
            // Route request
            switch ($endpoint) {
                case 'trackers':
                    return $this->handleTrackers($method, $id, $action);
                case 'analytics':
                    return $this->handleAnalytics($method, $id, $action);
                case 'notifications':
                    return $this->handleNotifications($method, $id, $action);
                case 'webhooks':
                    return $this->handleWebhooks($method, $id, $action);
                case 'sync':
                    return $this->handleSync($method, $id, $action);
                default:
                    return $this->errorResponse('Endpoint not found', 404);
            }
        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage());
            return $this->errorResponse('Internal server error', 500);
        }
    }
    
    private function authenticate() {
        // Check API key
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
        
        if (!$apiKey) {
            return false;
        }
        
        // Validate API key
        $stmt = $this->pdo->prepare("
            SELECT business_id FROM api_keys 
            WHERE api_key = ? AND is_active = 1 AND expires_at > NOW()
        ");
        $stmt->execute([$apiKey]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return false;
        }
        
        $this->businessId = $result['business_id'];
        return true;
    }
    
    private function handleTrackers($method, $id, $action) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    if ($action === 'stages') {
                        return $this->getTrackerStages($id);
                    } elseif ($action === 'analytics') {
                        return $this->getTrackerAnalytics($id);
                    } else {
                        return $this->getTracker($id);
                    }
                } else {
                    return $this->getTrackers();
                }
                
            case 'POST':
                return $this->createTracker();
                
            case 'PUT':
                if ($id) {
                    if ($action === 'progress') {
                        return $this->updateTrackerProgress($id);
                    } elseif ($action === 'stage') {
                        return $this->updateTrackerStage($id);
                    } else {
                        return $this->updateTracker($id);
                    }
                }
                return $this->errorResponse('ID required for PUT', 400);
                
            case 'DELETE':
                if ($id) {
                    return $this->deleteTracker($id);
                }
                return $this->errorResponse('ID required for DELETE', 400);
                
            default:
                return $this->errorResponse('Method not allowed', 405);
        }
    }
    
    private function getTrackers() {
        $filters = [
            'status' => $_GET['status'] ?? null,
            'campaign_id' => $_GET['campaign_id'] ?? null,
            'limit' => min(100, $_GET['limit'] ?? 50),
            'offset' => $_GET['offset'] ?? 0
        ];
        
        $trackers = $this->pizzaTracker->getBusinessTrackers($this->businessId, $filters);
        
        return $this->successResponse([
            'trackers' => $trackers,
            'pagination' => [
                'limit' => $filters['limit'],
                'offset' => $filters['offset'],
                'total' => count($trackers)
            ]
        ]);
    }
    
    private function getTracker($id) {
        $tracker = $this->pizzaTracker->getTrackerDetails($id);
        
        if (!$tracker || $tracker['business_id'] != $this->businessId) {
            return $this->errorResponse('Tracker not found', 404);
        }
        
        return $this->successResponse(['tracker' => $tracker]);
    }
    
    private function getTrackerStages($id) {
        $stages = $this->pizzaTracker->getTrackerStages($id);
        return $this->successResponse(['stages' => $stages]);
    }
    
    private function getTrackerAnalytics($id) {
        $analytics = $this->pizzaTracker->getTrackerAnalytics($id);
        return $this->successResponse(['analytics' => $analytics]);
    }
    
    private function createTracker() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['name', 'revenue_goal', 'stages'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                return $this->errorResponse("Field '$field' is required", 400);
            }
        }
        
        $trackerId = $this->pizzaTracker->createTracker([
            'business_id' => $this->businessId,
            'name' => $input['name'],
            'description' => $input['description'] ?? '',
            'revenue_goal' => $input['revenue_goal'],
            'stages' => $input['stages'],
            'campaign_id' => $input['campaign_id'] ?? null,
            'auto_reset' => $input['auto_reset'] ?? false,
            'reset_frequency' => $input['reset_frequency'] ?? 'monthly'
        ]);
        
        if ($trackerId) {
            $tracker = $this->pizzaTracker->getTrackerDetails($trackerId);
            return $this->successResponse(['tracker' => $tracker], 201);
        } else {
            return $this->errorResponse('Failed to create tracker', 500);
        }
    }
    
    private function updateTracker($id) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $success = $this->pizzaTracker->updateTracker($id, $input, $this->businessId);
        
        if ($success) {
            $tracker = $this->pizzaTracker->getTrackerDetails($id);
            return $this->successResponse(['tracker' => $tracker]);
        } else {
            return $this->errorResponse('Failed to update tracker', 500);
        }
    }
    
    private function updateTrackerProgress($id) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['revenue_amount'])) {
            return $this->errorResponse('Revenue amount is required', 400);
        }
        
        $success = $this->pizzaTracker->addRevenue($id, $input['revenue_amount'], [
            'source' => $input['source'] ?? 'api',
            'description' => $input['description'] ?? '',
            'external_id' => $input['external_id'] ?? null
        ]);
        
        if ($success) {
            $tracker = $this->pizzaTracker->getTrackerDetails($id);
            
            // Trigger webhooks
            $this->triggerWebhook('progress_updated', [
                'tracker_id' => $id,
                'tracker' => $tracker,
                'revenue_added' => $input['revenue_amount']
            ]);
            
            return $this->successResponse(['tracker' => $tracker]);
        } else {
            return $this->errorResponse('Failed to update progress', 500);
        }
    }
    
    private function updateTrackerStage($id) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['stage_name']) || !isset($input['status'])) {
            return $this->errorResponse('Stage name and status are required', 400);
        }
        
        $success = $this->pizzaTracker->updateStageStatus(
            $id, 
            $input['stage_name'], 
            $input['status'],
            $input['notes'] ?? ''
        );
        
        if ($success) {
            $tracker = $this->pizzaTracker->getTrackerDetails($id);
            
            // Trigger webhooks
            $this->triggerWebhook('stage_updated', [
                'tracker_id' => $id,
                'tracker' => $tracker,
                'stage' => $input['stage_name'],
                'status' => $input['status']
            ]);
            
            return $this->successResponse(['tracker' => $tracker]);
        } else {
            return $this->errorResponse('Failed to update stage', 500);
        }
    }
    
    private function deleteTracker($id) {
        $success = $this->pizzaTracker->deleteTracker($id, $this->businessId);
        
        if ($success) {
            return $this->successResponse(['message' => 'Tracker deleted successfully']);
        } else {
            return $this->errorResponse('Failed to delete tracker', 500);
        }
    }
    
    private function handleAnalytics($method, $id, $action) {
        if ($method !== 'GET') {
            return $this->errorResponse('Only GET method allowed for analytics', 405);
        }
        
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        $tracker_id = $id ?? 'all';
        
        switch ($action) {
            case 'summary':
                $analytics = $this->pizzaTracker->getAnalyticsSummary($this->businessId, $start_date, $end_date, $tracker_id);
                break;
            case 'revenue':
                $analytics = $this->pizzaTracker->getRevenueAnalytics($this->businessId, $start_date, $end_date, $tracker_id);
                break;
            case 'engagement':
                $analytics = $this->pizzaTracker->getEngagementAnalytics($this->businessId, $start_date, $end_date, $tracker_id);
                break;
            case 'predictions':
                $analytics = $this->pizzaTracker->getPredictiveAnalytics($this->businessId, $tracker_id);
                break;
            default:
                $analytics = $this->pizzaTracker->getAdvancedAnalytics($this->businessId, $start_date, $end_date, $tracker_id);
        }
        
        return $this->successResponse(['analytics' => $analytics]);
    }
    
    private function handleNotifications($method, $id, $action) {
        switch ($method) {
            case 'GET':
                if ($action === 'preferences') {
                    return $this->getNotificationPreferences();
                } else {
                    return $this->getNotificationHistory();
                }
                
            case 'POST':
                if ($action === 'test') {
                    return $this->sendTestNotification();
                } else {
                    return $this->setNotificationPreferences();
                }
                
            default:
                return $this->errorResponse('Method not allowed', 405);
        }
    }
    
    private function getNotificationPreferences() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pizza_tracker_notification_preferences 
            WHERE business_id = ? AND is_active = 1
        ");
        $stmt->execute([$this->businessId]);
        $preferences = $stmt->fetch();
        
        return $this->successResponse(['preferences' => $preferences]);
    }
    
    private function setNotificationPreferences() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $success = $this->notificationSystem->setNotificationPreferences($this->businessId, $input);
        
        if ($success) {
            return $this->successResponse(['message' => 'Preferences updated successfully']);
        } else {
            return $this->errorResponse('Failed to update preferences', 500);
        }
    }
    
    private function sendTestNotification() {
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? 'email';
        
        $success = $this->notificationSystem->sendTestNotification($this->businessId, $type);
        
        if ($success) {
            return $this->successResponse(['message' => 'Test notification sent']);
        } else {
            return $this->errorResponse('Failed to send test notification', 500);
        }
    }
    
    private function getNotificationHistory() {
        $limit = min(100, $_GET['limit'] ?? 50);
        $offset = $_GET['offset'] ?? 0;
        
        $stmt = $this->pdo->prepare("
            SELECT ptn.*, pt.name as tracker_name
            FROM pizza_tracker_notifications ptn
            JOIN pizza_trackers pt ON ptn.tracker_id = pt.id
            WHERE pt.business_id = ?
            ORDER BY ptn.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$this->businessId, $limit, $offset]);
        $notifications = $stmt->fetchAll();
        
        return $this->successResponse([
            'notifications' => $notifications,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => count($notifications)
            ]
        ]);
    }
    
    private function handleWebhooks($method, $id, $action) {
        switch ($method) {
            case 'GET':
                return $this->getWebhooks();
            case 'POST':
                return $this->createWebhook();
            case 'PUT':
                if ($id) {
                    return $this->updateWebhook($id);
                }
                return $this->errorResponse('ID required for PUT', 400);
            case 'DELETE':
                if ($id) {
                    return $this->deleteWebhook($id);
                }
                return $this->errorResponse('ID required for DELETE', 400);
            default:
                return $this->errorResponse('Method not allowed', 405);
        }
    }
    
    private function getWebhooks() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pizza_tracker_webhooks 
            WHERE business_id = ? AND is_active = 1
            ORDER BY created_at DESC
        ");
        $stmt->execute([$this->businessId]);
        $webhooks = $stmt->fetchAll();
        
        return $this->successResponse(['webhooks' => $webhooks]);
    }
    
    private function createWebhook() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['url', 'events'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                return $this->errorResponse("Field '$field' is required", 400);
            }
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO pizza_tracker_webhooks 
            (business_id, url, events, secret, is_active, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        
        $secret = bin2hex(random_bytes(32));
        $success = $stmt->execute([
            $this->businessId,
            $input['url'],
            json_encode($input['events']),
            $secret
        ]);
        
        if ($success) {
            $webhookId = $this->pdo->lastInsertId();
            return $this->successResponse([
                'webhook_id' => $webhookId,
                'secret' => $secret,
                'message' => 'Webhook created successfully'
            ], 201);
        } else {
            return $this->errorResponse('Failed to create webhook', 500);
        }
    }
    
    private function triggerWebhook($event, $data) {
        // Get webhooks for this business that listen to this event
        $stmt = $this->pdo->prepare("
            SELECT * FROM pizza_tracker_webhooks 
            WHERE business_id = ? AND is_active = 1 
            AND JSON_CONTAINS(events, ?)
        ");
        $stmt->execute([$this->businessId, json_encode($event)]);
        $webhooks = $stmt->fetchAll();
        
        foreach ($webhooks as $webhook) {
            $this->sendWebhookPayload($webhook, $event, $data);
        }
    }
    
    private function sendWebhookPayload($webhook, $event, $data) {
        $payload = [
            'event' => $event,
            'data' => $data,
            'timestamp' => time(),
            'business_id' => $this->businessId
        ];
        
        $signature = hash_hmac('sha256', json_encode($payload), $webhook['secret']);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'X-Pizza-Tracker-Signature: ' . $signature,
                    'X-Pizza-Tracker-Event: ' . $event,
                    'User-Agent: PizzaTracker-Webhook/1.0'
                ],
                'content' => json_encode($payload),
                'timeout' => 10
            ]
        ]);
        
        // Send asynchronously
        file_get_contents($webhook['url'], false, $context);
    }
    
    private function handleSync($method, $id, $action) {
        if ($method !== 'POST') {
            return $this->errorResponse('Only POST method allowed for sync', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        switch ($action) {
            case 'orders':
                return $this->syncOrders($input);
            case 'inventory':
                return $this->syncInventory($input);
            case 'pos':
                return $this->syncPOS($input);
            default:
                return $this->errorResponse('Invalid sync action', 400);
        }
    }
    
    private function syncOrders($orders) {
        $processed = 0;
        $errors = [];
        
        foreach ($orders as $order) {
            try {
                // Find tracker by external ID or campaign
                $trackerId = $this->findTrackerForOrder($order);
                
                if ($trackerId) {
                    $this->pizzaTracker->addRevenue($trackerId, $order['total'], [
                        'source' => 'order_sync',
                        'external_id' => $order['order_id'],
                        'description' => 'Order #' . $order['order_id']
                    ]);
                    $processed++;
                }
            } catch (Exception $e) {
                $errors[] = [
                    'order_id' => $order['order_id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $this->successResponse([
            'processed' => $processed,
            'total' => count($orders),
            'errors' => $errors
        ]);
    }
    
    private function findTrackerForOrder($order) {
        // Implementation depends on business logic
        // Could match by campaign ID, location, etc.
        return null;
    }
    
    private function successResponse($data, $status = 200) {
        http_response_code($status);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => time()
        ]);
        exit;
    }
    
    private function errorResponse($message, $status = 400) {
        http_response_code($status);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => time()
        ]);
        exit;
    }
}

// Initialize and handle request
$api = new PizzaTrackerAPI($pdo);
$api->handleRequest();
?> 