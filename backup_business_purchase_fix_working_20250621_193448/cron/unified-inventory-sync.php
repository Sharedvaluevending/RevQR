#!/usr/bin/env php
<?php
/**
 * Unified Inventory Daily Batch Sync
 * Cron job for daily reconciliation and synchronization
 * 
 * Schedule: Run daily at 2:00 AM
 * Crontab entry: 0 2 * * * /usr/bin/php /var/www/cron/unified-inventory-sync.php
 */

// Change to project directory
chdir(dirname(__DIR__));

require_once 'html/core/config.php';
require_once 'html/core/services/UnifiedSyncEngine.php';

echo "Starting Unified Inventory Daily Batch Sync - " . date('Y-m-d H:i:s') . "\n";

try {
    // Initialize sync engine
    $syncEngine = new UnifiedSyncEngine($pdo);
    
    // Run batch sync for all businesses
    echo "Running daily batch synchronization...\n";
    $batchResult = $syncEngine->runDailyBatchSync();
    
    if ($batchResult['success']) {
        echo "✅ Batch sync completed successfully\n";
        
        // Display results for each business
        foreach ($batchResult['results'] as $businessId => $result) {
            echo "\n📊 Business {$businessId} Results:\n";
            echo "  - Reconciled items: {$result['reconciled_items']}\n";
            echo "  - Nayax updates: {$result['nayax_updates']}\n";
            echo "  - Total mappings: {$result['summary']['total_mappings']}\n";
            echo "  - Synced mappings: {$result['summary']['synced_mappings']}\n";
            echo "  - Total inventory: {$result['summary']['total_inventory']}\n";
            echo "  - Sales today: {$result['summary']['sales_today']}\n";
            echo "  - Sales this week: {$result['summary']['sales_week']}\n";
        }
        
        // Send summary email if configured
        sendDailySyncSummary($batchResult['results']);
        
    } else {
        echo "❌ Batch sync failed: " . $batchResult['error'] . "\n";
        
        // Send error notification
        sendSyncErrorNotification($batchResult['error']);
    }
    
    // Additional maintenance tasks
    echo "\n🧹 Running maintenance tasks...\n";
    
    // Clean up old webhook logs
    cleanupOldWebhookLogs();
    
    // Generate sync health report
    generateSyncHealthReport();
    
    echo "\n✅ Daily sync job completed - " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "💥 Critical error in daily sync job: " . $e->getMessage() . "\n";
    error_log("Unified inventory daily sync critical error: " . $e->getMessage());
    
    // Send critical error notification
    sendCriticalErrorNotification($e->getMessage());
}

/**
 * Send daily sync summary email
 */
function sendDailySyncSummary($results) {
    global $pdo;
    
    try {
        // Get admin email addresses
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.email 
            FROM users u 
            JOIN businesses b ON u.business_id = b.id 
            WHERE u.role = 'business' AND u.email IS NOT NULL
            AND b.id IN (" . implode(',', array_keys($results)) . ")
        ");
        $stmt->execute();
        $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($emails)) {
            return;
        }
        
        // Build summary email
        $subject = "Daily Unified Inventory Sync Report - " . date('Y-m-d');
        $body = buildSyncSummaryEmail($results);
        
        // Send email (implement your email sending logic here)
        foreach ($emails as $email) {
            // mail($email, $subject, $body);
            echo "📧 Would send summary email to: {$email}\n";
        }
        
    } catch (Exception $e) {
        echo "⚠️ Failed to send summary emails: " . $e->getMessage() . "\n";
    }
}

/**
 * Build sync summary email content
 */
function buildSyncSummaryEmail($results) {
    $body = "Daily Unified Inventory Sync Report\n";
    $body .= "=====================================\n";
    $body .= "Date: " . date('Y-m-d H:i:s') . "\n\n";
    
    $totalBusinesses = count($results);
    $totalReconciled = array_sum(array_column($results, 'reconciled_items'));
    $totalNayaxUpdates = array_sum(array_column($results, 'nayax_updates'));
    
    $body .= "Summary:\n";
    $body .= "- Businesses processed: {$totalBusinesses}\n";
    $body .= "- Total items reconciled: {$totalReconciled}\n";
    $body .= "- Total Nayax updates: {$totalNayaxUpdates}\n\n";
    
    foreach ($results as $businessId => $result) {
        $body .= "Business {$businessId}:\n";
        $body .= "  Total mappings: {$result['summary']['total_mappings']}\n";
        $body .= "  Synced mappings: {$result['summary']['synced_mappings']}\n";
        $body .= "  Total inventory: {$result['summary']['total_inventory']}\n";
        $body .= "  Sales today: {$result['summary']['sales_today']}\n";
        $body .= "  Sales this week: {$result['summary']['sales_week']}\n\n";
    }
    
    return $body;
}

/**
 * Send error notification
 */
function sendSyncErrorNotification($error) {
    // Implement error notification logic (email, Slack, etc.)
    echo "🚨 Would send error notification: {$error}\n";
}

/**
 * Send critical error notification
 */
function sendCriticalErrorNotification($error) {
    // Implement critical error notification logic
    echo "🆘 Would send critical error notification: {$error}\n";
}

/**
 * Clean up old webhook logs
 */
function cleanupOldWebhookLogs() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM nayax_webhook_log 
            WHERE received_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $deletedCount = $stmt->rowCount();
        
        echo "🗑️ Cleaned up {$deletedCount} old webhook logs\n";
        
    } catch (Exception $e) {
        echo "⚠️ Failed to cleanup webhook logs: " . $e->getMessage() . "\n";
    }
}

/**
 * Generate sync health report
 */
function generateSyncHealthReport() {
    global $pdo;
    
    try {
        echo "\n📊 Sync Health Report:\n";
        
        // Overall system health
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_mappings,
                SUM(CASE WHEN sync_status = 'synced' THEN 1 ELSE 0 END) as synced_count,
                SUM(CASE WHEN sync_status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                SUM(CASE WHEN sync_status = 'unsynced' THEN 1 ELSE 0 END) as unsynced_count
            FROM unified_inventory_status
        ");
        $stmt->execute();
        $health = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $syncedPercent = $health['total_mappings'] > 0 ? 
            round(($health['synced_count'] / $health['total_mappings']) * 100, 1) : 0;
        
        echo "  Total mappings: {$health['total_mappings']}\n";
        echo "  Synced: {$health['synced_count']} ({$syncedPercent}%)\n";
        echo "  Partial: {$health['partial_count']}\n";
        echo "  Unsynced: {$health['unsynced_count']}\n";
        
        // Recent sync activity
        $stmt = $pdo->prepare("
            SELECT 
                event_type,
                COUNT(*) as count
            FROM unified_inventory_sync_log 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY event_type
            ORDER BY count DESC
        ");
        $stmt->execute();
        $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n  Recent 24h Activity:\n";
        foreach ($activity as $event) {
            echo "    {$event['event_type']}: {$event['count']}\n";
        }
        
    } catch (Exception $e) {
        echo "⚠️ Failed to generate health report: " . $e->getMessage() . "\n";
    }
}

/**
 * Performance monitoring
 */
function logPerformanceMetrics() {
    global $pdo;
    
    try {
        // Log daily performance metrics
        $stmt = $pdo->prepare("
            INSERT INTO unified_sync_performance_log 
            (date, total_mappings, sync_health_percent, total_sync_events, created_at)
            SELECT 
                CURDATE(),
                COUNT(*),
                ROUND((SUM(CASE WHEN sync_status = 'synced' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1),
                (SELECT COUNT(*) FROM unified_inventory_sync_log WHERE DATE(created_at) = CURDATE()),
                NOW()
            FROM unified_inventory_status
            ON DUPLICATE KEY UPDATE
                total_mappings = VALUES(total_mappings),
                sync_health_percent = VALUES(sync_health_percent),
                total_sync_events = VALUES(total_sync_events),
                updated_at = NOW()
        ");
        $stmt->execute();
        
        echo "📈 Performance metrics logged\n";
        
    } catch (Exception $e) {
        echo "⚠️ Failed to log performance metrics: " . $e->getMessage() . "\n";
    }
}
?> 