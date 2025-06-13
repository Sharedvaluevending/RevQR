<?php
/**
 * Manual Sales Sync Trigger
 * Integrates with existing manual sales to trigger unified inventory updates
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/UnifiedSyncEngine.php';

/**
 * Trigger sync when a manual sale is recorded
 * Call this function from your existing sales recording code
 */
function triggerManualSaleSync($business_id, $item_id, $quantity_sold, $sale_price) {
    try {
        // Initialize sync engine
        $syncEngine = new UnifiedSyncEngine();
        
        // Trigger the sync
        $result = $syncEngine->syncOnManualSale($business_id, $item_id, $quantity_sold, $sale_price);
        
        // Log result
        if ($result['success']) {
            error_log("Manual sale sync triggered successfully for business {$business_id}, item {$item_id}");
        } else {
            error_log("Manual sale sync failed for business {$business_id}, item {$item_id}: " . $result['error']);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Manual sale sync trigger error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Batch sync for multiple sales
 */
function triggerBatchManualSalesSync($business_id, $sales_data) {
    $results = [];
    $successCount = 0;
    
    foreach ($sales_data as $sale) {
        $result = triggerManualSaleSync(
            $business_id,
            $sale['item_id'],
            $sale['quantity'],
            $sale['price']
        );
        
        $results[] = $result;
        if ($result['success']) {
            $successCount++;
        }
    }
    
    return [
        'success' => $successCount > 0,
        'total_sales' => count($sales_data),
        'successful_syncs' => $successCount,
        'failed_syncs' => count($sales_data) - $successCount,
        'details' => $results
    ];
}

/**
 * Integration point for existing sales recording
 * Add this call to your existing sales recording functions
 */
function integrateUnifiedSyncWithSales($business_id, $item_id, $quantity, $price) {
    // Only sync if unified inventory is enabled for this business
    if (isUnifiedInventoryEnabled($business_id)) {
        return triggerManualSaleSync($business_id, $item_id, $quantity, $price);
    }
    
    return ['success' => true, 'message' => 'Unified inventory not enabled'];
}

/**
 * Check if unified inventory is enabled for business
 */
function isUnifiedInventoryEnabled($business_id) {
    global $pdo;
    
    try {
        // Check if business has any unified item mappings
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM unified_item_mapping 
            WHERE business_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$business_id]);
        
        return $stmt->fetchColumn() > 0;
        
    } catch (Exception $e) {
        error_log("Error checking unified inventory status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get sync status for debugging
 */
function getManualSalesSyncStatus($business_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                event_type,
                COUNT(*) as count,
                MAX(created_at) as last_event
            FROM unified_inventory_sync_log 
            WHERE business_id = ? 
            AND event_type IN ('manual_sale_trigger', 'manual_sale_sync_error')
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY event_type
        ");
        $stmt->execute([$business_id]);
        
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $status = [
            'manual_sales_synced_24h' => 0,
            'sync_errors_24h' => 0,
            'last_sync' => null,
            'last_error' => null
        ];
        
        foreach ($events as $event) {
            if ($event['event_type'] === 'manual_sale_trigger') {
                $status['manual_sales_synced_24h'] = $event['count'];
                $status['last_sync'] = $event['last_event'];
            } elseif ($event['event_type'] === 'manual_sale_sync_error') {
                $status['sync_errors_24h'] = $event['count'];
                $status['last_error'] = $event['last_event'];
            }
        }
        
        return $status;
        
    } catch (Exception $e) {
        error_log("Error getting manual sales sync status: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Manual inventory reconciliation
 * Call this when manual inventory counts are updated
 */
function triggerInventoryReconciliation($business_id, $item_id, $new_quantity) {
    try {
        global $pdo;
        
        // Update the manual inventory directly
        $stmt = $pdo->prepare("
            UPDATE unified_inventory_status uis
            JOIN unified_item_mapping uim ON uis.mapping_id = uim.id
            SET uis.manual_stock_qty = ?,
                uis.total_available_qty = ? + uis.nayax_estimated_qty,
                uis.last_synced_at = NOW(),
                uis.sync_status = 'synced'
            WHERE uis.business_id = ? AND uim.manual_item_id = ?
        ");
        $stmt->execute([$new_quantity, $new_quantity, $business_id, $item_id]);
        
        // Log the reconciliation
        $syncEngine = new UnifiedSyncEngine();
        $syncEngine->logSyncEvent($business_id, 'inventory_reconciliation', [
            'item_id' => $item_id,
            'new_quantity' => $new_quantity,
            'source' => 'manual_update'
        ]);
        
        return ['success' => true, 'message' => 'Inventory reconciled'];
        
    } catch (Exception $e) {
        error_log("Inventory reconciliation error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?> 