<?php
/**
 * Nayax AWS SQS Integration
 * Handles SQS queue polling and event processing for Nayax machine events
 * 
 * @author RevenueQR Team
 * @version 1.0
 * @date 2025-01-17
 */

require_once __DIR__ . '/nayax_manager.php';

class NayaxAWSSQS {
    
    private $pdo;
    private $sqs_client;
    private $nayax_manager;
    private $config;
    
    public function __construct($pdo = null) {
        global $pdo;
        $global_pdo = $pdo;
        $this->pdo = $pdo ?: $global_pdo;
        $this->nayax_manager = new NayaxManager($this->pdo);
        $this->config = $this->loadAWSConfig();
        
        // Initialize AWS SQS client if credentials are available
        if ($this->hasValidAWSCredentials()) {
            $this->initializeSQSClient();
        }
    }
    
    /**
     * Load AWS configuration from database
     */
    private function loadAWSConfig() {
        try {
            $stmt = $this->pdo->prepare("SELECT config_key, config_value FROM nayax_aws_config");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $config = [];
            foreach ($results as $row) {
                $config[$row['config_key']] = $row['config_value'];
            }
            
            return $config;
            
        } catch (Exception $e) {
            error_log("NayaxAWSSQS::loadAWSConfig() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if valid AWS credentials are configured
     */
    private function hasValidAWSCredentials() {
        return !empty($this->config['access_key']) && 
               !empty($this->config['secret_key']) && 
               !empty($this->config['region']) &&
               $this->config['access_key'] !== 'YOUR_ACCESS_KEY' &&
               $this->config['secret_key'] !== 'YOUR_SECRET_KEY';
    }
    
    /**
     * Initialize AWS SQS client
     */
    private function initializeSQSClient() {
        try {
            // Check if AWS SDK is available
            if (!class_exists('Aws\Sqs\SqsClient')) {
                if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
                    require_once __DIR__ . '/../vendor/autoload.php';
                } else {
                    throw new Exception('AWS SDK not found. Please run: composer require aws/aws-sdk-php');
                }
            }
            
            $this->sqs_client = new \Aws\Sqs\SqsClient([
                'version' => '2012-11-05',
                'region' => $this->config['region'],
                'credentials' => [
                    'key' => $this->config['access_key'],
                    'secret' => $this->config['secret_key'],
                ]
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("NayaxAWSSQS::initializeSQSClient() error: " . $e->getMessage());
            $this->sqs_client = null;
            return false;
        }
    }
    
    /**
     * Poll SQS queue for new messages
     */
    public function pollQueue($max_messages = 10, $wait_time_seconds = 20) {
        if (!$this->sqs_client) {
            throw new Exception('AWS SQS client not initialized');
        }
        
        if (empty($this->config['queue_url'])) {
            throw new Exception('SQS queue URL not configured');
        }
        
        try {
            $result = $this->sqs_client->receiveMessage([
                'QueueUrl' => $this->config['queue_url'],
                'MaxNumberOfMessages' => $max_messages,
                'WaitTimeSeconds' => $wait_time_seconds,
                'VisibilityTimeout' => 300, // 5 minutes to process
                'MessageAttributeNames' => ['All']
            ]);
            
            $messages = $result->get('Messages') ?: [];
            $processed_count = 0;
            
            foreach ($messages as $message) {
                if ($this->processMessage($message)) {
                    // Delete message from queue after successful processing
                    $this->deleteMessage($message);
                    $processed_count++;
                } else {
                    // Message will become visible again after visibility timeout
                    error_log("Failed to process SQS message: " . $message['MessageId']);
                }
            }
            
            return [
                'success' => true,
                'messages_received' => count($messages),
                'messages_processed' => $processed_count
            ];
            
        } catch (Exception $e) {
            error_log("NayaxAWSSQS::pollQueue() error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process a single SQS message
     */
    private function processMessage($message) {
        try {
            $message_id = $message['MessageId'];
            $receipt_handle = $message['ReceiptHandle'];
            $body = json_decode($message['Body'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON in message body");
            }
            
            // Log message for debugging
            $this->logEvent('SQS_MESSAGE_RECEIVED', [
                'message_id' => $message_id,
                'body' => $body
            ]);
            
            // Determine event type and process accordingly
            $event_type = $this->determineEventType($body);
            $result = $this->processEventByType($event_type, $body);
            
            // Store event in database
            $this->storeEvent($message_id, $event_type, $body, $result);
            
            return $result['success'] ?? true;
            
        } catch (Exception $e) {
            error_log("NayaxAWSSQS::processMessage() error: " . $e->getMessage());
            
            // Store failed event
            $this->storeEvent(
                $message['MessageId'] ?? 'unknown',
                'PROCESSING_ERROR',
                $message,
                ['success' => false, 'error' => $e->getMessage()]
            );
            
            return false;
        }
    }
    
    /**
     * Determine event type from message body
     */
    private function determineEventType($body) {
        // Check for common Nayax event types
        if (isset($body['Type']) && $body['Type'] === 'Notification') {
            if (isset($body['Subject'])) {
                if (strpos($body['Subject'], 'Machine Status') !== false) {
                    return 'MACHINE_STATUS';
                } elseif (strpos($body['Subject'], 'Transaction') !== false) {
                    return 'TRANSACTION_NOTIFICATION';
                } elseif (strpos($body['Subject'], 'Alert') !== false) {
                    return 'MACHINE_ALERT';
                } elseif (strpos($body['Subject'], 'Error') !== false) {
                    return 'MACHINE_ERROR';
                }
            }
        }
        
        // Check for direct event data
        if (isset($body['EventType'])) {
            return strtoupper($body['EventType']);
        }
        
        // Check for machine data
        if (isset($body['MachineId']) || isset($body['DeviceId'])) {
            return 'MACHINE_EVENT';
        }
        
        return 'UNKNOWN_EVENT';
    }
    
    /**
     * Process event based on its type
     */
    private function processEventByType($event_type, $data) {
        switch ($event_type) {
            case 'MACHINE_STATUS':
                return $this->processMachineStatusEvent($data);
                
            case 'MACHINE_ALERT':
                return $this->processMachineAlertEvent($data);
                
            case 'MACHINE_ERROR':
                return $this->processMachineErrorEvent($data);
                
            case 'TRANSACTION_NOTIFICATION':
                return $this->processTransactionNotificationEvent($data);
                
            case 'MACHINE_EVENT':
                return $this->processMachineEvent($data);
                
            default:
                return $this->processUnknownEvent($data);
        }
    }
    
    /**
     * Process machine status events
     */
    private function processMachineStatusEvent($data) {
        try {
            // Extract machine ID and status from the message
            $machine_id = $this->extractMachineId($data);
            $status = $this->extractMachineStatus($data);
            
            if ($machine_id && $status) {
                // Update machine status
                $this->nayax_manager->updateMachineStatus($machine_id, $status);
                
                // If machine went offline, log alert
                if ($status === 'offline' || $status === 'error') {
                    $this->createMachineAlert($machine_id, 'MACHINE_OFFLINE', 'Machine went offline', $data);
                }
                
                return ['success' => true, 'action' => 'status_updated'];
            }
            
            return ['success' => false, 'error' => 'Could not extract machine ID or status'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process machine alert events
     */
    private function processMachineAlertEvent($data) {
        try {
            $machine_id = $this->extractMachineId($data);
            $alert_type = $data['AlertType'] ?? 'GENERAL_ALERT';
            $message = $data['Message'] ?? $data['Subject'] ?? 'Machine alert received';
            
            if ($machine_id) {
                $this->createMachineAlert($machine_id, $alert_type, $message, $data);
                return ['success' => true, 'action' => 'alert_created'];
            }
            
            return ['success' => false, 'error' => 'Could not extract machine ID'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process machine error events
     */
    private function processMachineErrorEvent($data) {
        try {
            $machine_id = $this->extractMachineId($data);
            $error_code = $data['ErrorCode'] ?? 'UNKNOWN_ERROR';
            $error_message = $data['ErrorMessage'] ?? $data['Message'] ?? 'Machine error occurred';
            
            if ($machine_id) {
                // Update machine status to error
                $this->nayax_manager->updateMachineStatus($machine_id, 'error');
                
                // Create alert for error
                $this->createMachineAlert($machine_id, "ERROR_{$error_code}", $error_message, $data);
                
                return ['success' => true, 'action' => 'error_processed'];
            }
            
            return ['success' => false, 'error' => 'Could not extract machine ID'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process transaction notification events
     */
    private function processTransactionNotificationEvent($data) {
        try {
            // If this is a transaction notification, extract the actual transaction data
            $transaction_data = $data['TransactionData'] ?? $data;
            
            if (isset($transaction_data['TransactionId'])) {
                // Process as a regular transaction
                $result = $this->nayax_manager->processTransaction($transaction_data);
                return $result;
            }
            
            return ['success' => false, 'error' => 'No transaction data found'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process general machine events
     */
    private function processMachineEvent($data) {
        try {
            $machine_id = $this->extractMachineId($data);
            
            if ($machine_id) {
                // Update last seen timestamp
                $this->nayax_manager->updateMachineStatus($machine_id, null, date('Y-m-d H:i:s'));
                return ['success' => true, 'action' => 'heartbeat_updated'];
            }
            
            return ['success' => false, 'error' => 'Could not extract machine ID'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process unknown events
     */
    private function processUnknownEvent($data) {
        return ['success' => true, 'action' => 'logged_only'];
    }
    
    /**
     * Extract machine ID from various data formats
     */
    private function extractMachineId($data) {
        return $data['MachineId'] ?? 
               $data['DeviceId'] ?? 
               $data['machineId'] ?? 
               $data['deviceId'] ?? 
               null;
    }
    
    /**
     * Extract machine status from data
     */
    private function extractMachineStatus($data) {
        $status = $data['Status'] ?? $data['MachineStatus'] ?? null;
        
        if ($status) {
            // Normalize status values
            $status = strtolower($status);
            $status_map = [
                'online' => 'active',
                'operational' => 'active',
                'working' => 'active',
                'offline' => 'offline',
                'down' => 'offline',
                'error' => 'error',
                'fault' => 'error',
                'maintenance' => 'maintenance'
            ];
            
            return $status_map[$status] ?? $status;
        }
        
        return null;
    }
    
    /**
     * Create machine alert
     */
    private function createMachineAlert($machine_id, $alert_type, $message, $data) {
        try {
            $machine = $this->nayax_manager->getMachine($machine_id);
            if (!$machine) {
                return false;
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO nayax_events 
                (business_id, nayax_machine_id, event_type, event_data, alert_level, message, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $alert_level = $this->determineAlertLevel($alert_type);
            
            return $stmt->execute([
                $machine['business_id'],
                $machine_id,
                $alert_type,
                json_encode($data),
                $alert_level,
                $message
            ]);
            
        } catch (Exception $e) {
            error_log("NayaxAWSSQS::createMachineAlert() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Determine alert level based on type
     */
    private function determineAlertLevel($alert_type) {
        if (strpos($alert_type, 'ERROR') !== false || $alert_type === 'MACHINE_OFFLINE') {
            return 'high';
        } elseif (strpos($alert_type, 'WARNING') !== false || strpos($alert_type, 'ALERT') !== false) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Store event in database
     */
    private function storeEvent($message_id, $event_type, $event_data, $processing_result) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO nayax_events 
                (message_id, event_type, event_data, processing_result, status)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                processing_result = VALUES(processing_result),
                status = VALUES(status),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $status = ($processing_result['success'] ?? false) ? 'processed' : 'failed';
            
            return $stmt->execute([
                $message_id,
                $event_type,
                json_encode($event_data),
                json_encode($processing_result),
                $status
            ]);
            
        } catch (Exception $e) {
            error_log("NayaxAWSSQS::storeEvent() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete message from SQS queue
     */
    private function deleteMessage($message) {
        try {
            $this->sqs_client->deleteMessage([
                'QueueUrl' => $this->config['queue_url'],
                'ReceiptHandle' => $message['ReceiptHandle']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("NayaxAWSSQS::deleteMessage() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log event for debugging
     */
    private function logEvent($event_type, $data) {
        $log_file = __DIR__ . '/../../logs/nayax_sqs_events.log';
        $log_entry = "[" . date('Y-m-d H:i:s') . "] {$event_type}: " . json_encode($data) . "\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Test SQS connection
     */
    public function testConnection() {
        if (!$this->sqs_client) {
            return ['success' => false, 'error' => 'SQS client not initialized'];
        }
        
        try {
            $result = $this->sqs_client->getQueueAttributes([
                'QueueUrl' => $this->config['queue_url'],
                'AttributeNames' => ['QueueArn', 'ApproximateNumberOfMessages']
            ]);
            
            return [
                'success' => true,
                'queue_arn' => $result->get('Attributes')['QueueArn'] ?? '',
                'message_count' => $result->get('Attributes')['ApproximateNumberOfMessages'] ?? 0
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?> 