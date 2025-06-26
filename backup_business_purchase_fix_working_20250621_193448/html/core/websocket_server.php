<?php
/**
 * Pizza Tracker WebSocket Server
 * Provides real-time updates for pizza tracker progress
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pizza_tracker_utils.php';

class PizzaTrackerWebSocketServer {
    private $host = '0.0.0.0';
    private $port = 8080;
    private $clients = [];
    private $trackerSubscriptions = [];
    private $pdo;
    private $pizzaTracker;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->pizzaTracker = new PizzaTracker($pdo);
    }
    
    public function start() {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($socket, $this->host, $this->port);
        socket_listen($socket, 5);
        
        echo "Pizza Tracker WebSocket server started on {$this->host}:{$this->port}\n";
        
        while (true) {
            $read = array_merge([$socket], $this->clients);
            $write = null;
            $except = null;
            
            if (socket_select($read, $write, $except, 0) > 0) {
                // New connection
                if (in_array($socket, $read)) {
                    $client = socket_accept($socket);
                    $this->clients[] = $client;
                    $this->performHandshake($client);
                    echo "New client connected\n";
                }
                
                // Handle client messages
                foreach ($this->clients as $key => $client) {
                    if (in_array($client, $read)) {
                        $data = socket_read($client, 1024);
                        if ($data === false || empty($data)) {
                            $this->disconnectClient($key);
                        } else {
                            $this->handleMessage($client, $data);
                        }
                    }
                }
            }
            
            // Check for tracker updates
            $this->checkTrackerUpdates();
            usleep(100000); // 100ms delay
        }
    }
    
    private function performHandshake($client) {
        $request = socket_read($client, 5000);
        preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
        
        if (!empty($matches[1])) {
            $webSocketKey = trim($matches[1]);
            $webSocketAccept = base64_encode(pack('H*', sha1($webSocketKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
            
            $response = "HTTP/1.1 101 Switching Protocols\r\n";
            $response .= "Upgrade: websocket\r\n";
            $response .= "Connection: Upgrade\r\n";
            $response .= "Sec-WebSocket-Accept: $webSocketAccept\r\n\r\n";
            
            socket_write($client, $response, strlen($response));
        }
    }
    
    private function handleMessage($client, $data) {
        $message = $this->unmask($data);
        $messageData = json_decode($message, true);
        
        if ($messageData && isset($messageData['action'])) {
            switch ($messageData['action']) {
                case 'subscribe_tracker':
                    $this->subscribeToTracker($client, $messageData['tracker_id']);
                    break;
                case 'unsubscribe_tracker':
                    $this->unsubscribeFromTracker($client, $messageData['tracker_id']);
                    break;
                case 'get_tracker_status':
                    $this->sendTrackerStatus($client, $messageData['tracker_id']);
                    break;
                case 'ping':
                    $this->sendMessage($client, ['action' => 'pong', 'timestamp' => time()]);
                    break;
            }
        }
    }
    
    private function subscribeToTracker($client, $trackerId) {
        if (!isset($this->trackerSubscriptions[$trackerId])) {
            $this->trackerSubscriptions[$trackerId] = [];
        }
        
        $clientKey = array_search($client, $this->clients);
        if ($clientKey !== false && !in_array($clientKey, $this->trackerSubscriptions[$trackerId])) {
            $this->trackerSubscriptions[$trackerId][] = $clientKey;
            
            // Send current tracker status
            $this->sendTrackerStatus($client, $trackerId);
            
            echo "Client subscribed to tracker $trackerId\n";
        }
    }
    
    private function unsubscribeFromTracker($client, $trackerId) {
        if (isset($this->trackerSubscriptions[$trackerId])) {
            $clientKey = array_search($client, $this->clients);
            $subscriptionKey = array_search($clientKey, $this->trackerSubscriptions[$trackerId]);
            if ($subscriptionKey !== false) {
                unset($this->trackerSubscriptions[$trackerId][$subscriptionKey]);
            }
        }
    }
    
    private function sendTrackerStatus($client, $trackerId) {
        try {
            $tracker = $this->pizzaTracker->getTrackerDetails($trackerId);
            if ($tracker) {
                $this->sendMessage($client, [
                    'action' => 'tracker_update',
                    'tracker_id' => $trackerId,
                    'data' => $tracker,
                    'timestamp' => time()
                ]);
            }
        } catch (Exception $e) {
            error_log("Error sending tracker status: " . $e->getMessage());
        }
    }
    
    private function checkTrackerUpdates() {
        // Check for recent tracker updates
        static $lastCheck = 0;
        $now = time();
        
        if ($now - $lastCheck < 5) { // Check every 5 seconds
            return;
        }
        
        $lastCheck = $now;
        
        try {
            // Get recently updated trackers
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT pt.id 
                FROM pizza_trackers pt
                LEFT JOIN pizza_tracker_analytics pta ON pt.id = pta.tracker_id
                WHERE pt.is_active = 1 
                AND (pt.updated_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)
                     OR pta.created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND))
            ");
            $stmt->execute();
            $updatedTrackers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($updatedTrackers as $trackerId) {
                $this->broadcastTrackerUpdate($trackerId);
            }
        } catch (Exception $e) {
            error_log("Error checking tracker updates: " . $e->getMessage());
        }
    }
    
    private function broadcastTrackerUpdate($trackerId) {
        if (!isset($this->trackerSubscriptions[$trackerId])) {
            return;
        }
        
        try {
            $tracker = $this->pizzaTracker->getTrackerDetails($trackerId);
            if (!$tracker) {
                return;
            }
            
            $message = [
                'action' => 'tracker_update',
                'tracker_id' => $trackerId,
                'data' => $tracker,
                'timestamp' => time()
            ];
            
            foreach ($this->trackerSubscriptions[$trackerId] as $clientKey) {
                if (isset($this->clients[$clientKey])) {
                    $this->sendMessage($this->clients[$clientKey], $message);
                }
            }
            
            echo "Broadcasted update for tracker $trackerId to " . count($this->trackerSubscriptions[$trackerId]) . " clients\n";
        } catch (Exception $e) {
            error_log("Error broadcasting tracker update: " . $e->getMessage());
        }
    }
    
    private function sendMessage($client, $data) {
        $message = json_encode($data);
        $this->mask($client, $message);
    }
    
    private function mask($client, $text) {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);
        
        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } elseif ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } elseif ($length >= 65536) {
            $header = pack('CCNN', $b1, 127, $length);
        }
        
        socket_write($client, $header . $text, strlen($header . $text));
    }
    
    private function unmask($text) {
        $length = ord($text[1]) & 127;
        
        if ($length == 126) {
            $masks = substr($text, 4, 4);
            $data = substr($text, 8);
        } elseif ($length == 127) {
            $masks = substr($text, 10, 4);
            $data = substr($text, 14);
        } else {
            $masks = substr($text, 2, 4);
            $data = substr($text, 6);
        }
        
        $text = "";
        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }
        
        return $text;
    }
    
    private function disconnectClient($key) {
        // Remove from all subscriptions
        foreach ($this->trackerSubscriptions as $trackerId => $subscribers) {
            $subscriptionKey = array_search($key, $subscribers);
            if ($subscriptionKey !== false) {
                unset($this->trackerSubscriptions[$trackerId][$subscriptionKey]);
            }
        }
        
        socket_close($this->clients[$key]);
        unset($this->clients[$key]);
        echo "Client disconnected\n";
    }
}

// Start the server if run directly
if (php_sapi_name() === 'cli') {
    $server = new PizzaTrackerWebSocketServer($pdo);
    $server->start();
}
?> 