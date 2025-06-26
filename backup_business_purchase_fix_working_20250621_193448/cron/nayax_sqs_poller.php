#!/usr/bin/env php
<?php
/**
 * Nayax SQS Queue Poller - Cron Job
 * Polls AWS SQS queue for Nayax machine events and processes them
 * 
 * Schedule: Every 1-2 minutes
 * Usage: Add to crontab: (asterisk)/2 (asterisk) (asterisk) (asterisk) (asterisk) /usr/bin/php /var/www/cron/nayax_sqs_poller.php
 * 
 * @author RevenueQR Team
 * @version 1.0
 * @date 2025-01-17
 */

// Prevent web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line.');
}

// Lock file to prevent concurrent execution
$lock_file = __DIR__ . '/../logs/nayax_sqs_poller.lock';
$lock_timeout = 300; // 5 minutes

// Check if another instance is running
if (file_exists($lock_file)) {
    $lock_time = filemtime($lock_file);
    $current_time = time();
    
    if (($current_time - $lock_time) < $lock_timeout) {
        echo "Another instance is running. Exiting.\n";
        exit(1);
    } else {
        // Remove stale lock file
        unlink($lock_file);
    }
}

// Create lock file
file_put_contents($lock_file, getmypid());

// Log file
$log_file = __DIR__ . '/../logs/nayax_sqs_poller.log';
$start_time = microtime(true);

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    echo $log_entry;
}

function cleanup() {
    global $lock_file, $log_file, $start_time;
    
    // Remove lock file
    if (file_exists($lock_file)) {
        unlink($lock_file);
    }
    
    // Log execution time
    $execution_time = round((microtime(true) - $start_time) * 1000, 2);
    log_message("SQS poller completed in {$execution_time}ms");
}

// Register cleanup function
register_shutdown_function('cleanup');

try {
    log_message("Starting Nayax SQS poller...");
    
    // Include required files
    require_once __DIR__ . '/../html/core/config.php';
    require_once __DIR__ . '/../html/core/nayax_aws_sqs.php';
    
    // Check if integration is enabled
    $integration_enabled = ConfigManager::get('nayax_integration_enabled', false);
    $event_processing_enabled = ConfigManager::get('nayax_event_processing_enabled', true);
    
    if (!$integration_enabled) {
        log_message("Nayax integration is disabled. Exiting.");
        exit(0);
    }
    
    if (!$event_processing_enabled) {
        log_message("Nayax event processing is disabled. Exiting.");
        exit(0);
    }
    
    // Initialize SQS processor
    $sqs_processor = new NayaxAWSSQS($pdo);
    
    // Test connection first
    $connection_test = $sqs_processor->testConnection();
    if (!$connection_test['success']) {
        log_message("SQS connection test failed: " . $connection_test['error']);
        exit(1);
    }
    
    log_message("SQS connection successful. Queue messages: " . ($connection_test['message_count'] ?? 0));
    
    // Poll for messages
    $max_messages = (int) ConfigManager::get('nayax_sqs_max_messages', 10);
    $wait_time = (int) ConfigManager::get('nayax_sqs_wait_time', 5); // Shorter wait time for cron
    
    $result = $sqs_processor->pollQueue($max_messages, $wait_time);
    
    if ($result['success']) {
        $received = $result['messages_received'];
        $processed = $result['messages_processed'];
        
        if ($received > 0) {
            log_message("Processed {$processed}/{$received} SQS messages");
            
            // Update statistics
            $stats_file = __DIR__ . '/../logs/nayax_sqs_stats.json';
            $stats = [];
            
            if (file_exists($stats_file)) {
                $stats = json_decode(file_get_contents($stats_file), true) ?: [];
            }
            
            $today = date('Y-m-d');
            if (!isset($stats[$today])) {
                $stats[$today] = ['received' => 0, 'processed' => 0, 'runs' => 0];
            }
            
            $stats[$today]['received'] += $received;
            $stats[$today]['processed'] += $processed;
            $stats[$today]['runs']++;
            $stats[$today]['last_run'] = date('Y-m-d H:i:s');
            
            // Keep only last 30 days of stats
            $cutoff_date = date('Y-m-d', strtotime('-30 days'));
            foreach ($stats as $date => $data) {
                if ($date < $cutoff_date) {
                    unset($stats[$date]);
                }
            }
            
            file_put_contents($stats_file, json_encode($stats, JSON_PRETTY_PRINT));
            
        } else {
            log_message("No messages to process");
        }
    } else {
        log_message("SQS polling failed: " . $result['error']);
        exit(1);
    }
    
    // Health check - alert if too many failed messages
    $failed_threshold = (int) ConfigManager::get('nayax_sqs_failed_threshold', 10);
    if (($result['messages_received'] - $result['messages_processed']) >= $failed_threshold) {
        log_message("WARNING: {$failed_threshold}+ messages failed to process. Check system health.");
        
        // Could send notification here (email, Slack, etc.)
        $alert_data = [
            'type' => 'SQS_PROCESSING_ERRORS',
            'message' => "Multiple SQS messages failed to process",
            'received' => $result['messages_received'],
            'processed' => $result['messages_processed'],
            'failed' => $result['messages_received'] - $result['messages_processed']
        ];
        
        // Log alert to database if possible
        try {
            $stmt = $pdo->prepare("
                INSERT INTO nayax_events 
                (event_type, event_data, alert_level, message, status)
                VALUES ('SQS_PROCESSING_ALERT', ?, 'high', ?, 'pending')
            ");
            $stmt->execute([
                json_encode($alert_data),
                "SQS message processing errors detected"
            ]);
        } catch (Exception $e) {
            log_message("Failed to log alert to database: " . $e->getMessage());
        }
    }
    
    log_message("SQS poller completed successfully");
    exit(0);
    
} catch (Exception $e) {
    log_message("FATAL ERROR: " . $e->getMessage());
    log_message("Stack trace: " . $e->getTraceAsString());
    exit(1);
}
?> 