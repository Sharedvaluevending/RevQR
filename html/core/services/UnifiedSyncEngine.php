<?php
/**
 * Unified Sync Engine
 * Real-time synchronization between Manual and Nayax inventory systems
 * 
 * Features:
 * - Real-time sync triggers
 * - Webhook processing
 * - Batch synchronization
 * - Conflict resolution
 * - Smart mapping assistance
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UnifiedInventoryManager.php';

class UnifiedSyncEngine {
    private $pdo;
    private $inventoryManager;
    private $maxRetries = 3;
    private $batchSize = 100;
    
    public function __construct($pdo = null) {
        global $pdo;
        $this->pdo = $pdo ?? $GLOBALS['pdo'];
        $this->inventoryManager = new UnifiedInventoryManager($this->pdo);
    }
    
    /**
     * Trigger sync when manual sale occurs
     */
    public function syncOnManualSale($business_id, $item_id, $quantity_sold, $sale_price) {
        try {
            $this->logSyncEvent($business_id, 'manual_sale_trigger', [
                'item_id' => $item_id,
                'quantity' => $quantity_sold,
                'price' => $sale_price
            ]);
            
            // Update unified inventory status
            $this->updateUnifiedInventoryFromManualSale($business_id, $item_id, $quantity_sold);
            
            // Check if item needs restocking alert
            $this->checkRestockingAlert($business_id, $item_id);
            
            // Trigger smart mapping if item not yet mapped
            $this->checkSmartMapping($business_id, $item_id);
            
            return ['success' => true, 'message' => 'Manual sale sync completed'];
            
        } catch (Exception $e) {
            $this->logSyncError($business_id, 'manual_sale_sync_error', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process Nayax webhook transaction
     */
    public function processNayaxWebhook($webhookData) {
        try {
            // Parse webhook data
            $transaction = $this->parseNayaxWebhook($webhookData);
            
            if (!$transaction) {
                throw new Exception('Invalid webhook data format');
            }
            
            $business_id = $transaction['business_id'];
            $machine_id = $transaction['machine_id'];
            $item_selection = $transaction['item_selection'];
            $quantity = $transaction['quantity'];
            $amount = $transaction['amount'];
            
            $this->logSyncEvent($business_id, 'nayax_webhook_received', [
                'machine_id' => $machine_id,
                'item_selection' => $item_selection,
                'quantity' => $quantity,
                'amount' => $amount
            ]);
            
            // Update unified inventory from Nayax transaction
            $this->updateUnifiedInventoryFromNayaxSale($business_id, $machine_id, $item_selection, $quantity);
            
            // Check restocking
            $this->checkRestockingAlertFromNayax($business_id, $machine_id, $item_selection);
            
            return ['success' => true, 'message' => 'Nayax webhook processed'];
            
        } catch (Exception $e) {
            $this->logSyncError(0, 'nayax_webhook_error', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Daily batch synchronization
     */
    public function runDailyBatchSync($business_id = null) {
        try {
            $businesses = $business_id ? [$business_id] : $this->getActiveBusinesses();
            $results = [];
            
            foreach ($businesses as $biz_id) {
                $this->logSyncEvent($biz_id, 'daily_batch_sync_started', []);
                
                // Reconcile inventory discrepancies
                $reconcileResult = $this->reconcileInventoryDiscrepancies($biz_id);
                
                // Update estimated Nayax quantities
                $nayaxUpdateResult = $this->updateNayaxEstimatedQuantities($biz_id);
                
                // Clean up old sync logs
                $this->cleanupOldSyncLogs($biz_id);
                
                // Generate sync summary
                $summary = $this->generateSyncSummary($biz_id);
                
                $results[$biz_id] = [
                    'reconciled_items' => $reconcileResult['count'],
                    'nayax_updates' => $nayaxUpdateResult['count'],
                    'summary' => $summary
                ];
                
                $this->logSyncEvent($biz_id, 'daily_batch_sync_completed', $results[$biz_id]);
            }
            
            return ['success' => true, 'results' => $results];
            
        } catch (Exception $e) {
            $this->logSyncError($business_id ?? 0, 'batch_sync_error', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Smart mapping suggestion
     */
    public function suggestItemMappings($business_id) {
        try {
            // Get unmapped manual items
            $unmappedManual = $this->getUnmappedManualItems($business_id);
            
            // Get unmapped Nayax items
            $unmappedNayax = $this->getUnmappedNayaxItems($business_id);
            
            $suggestions = [];
            
            foreach ($unmappedManual as $manualItem) {
                $bestMatches = $this->findBestNayaxMatches($manualItem, $unmappedNayax);
                
                if (!empty($bestMatches)) {
                    $suggestions[] = [
                        'manual_item' => $manualItem,
                        'suggested_matches' => $bestMatches,
                        'confidence_scores' => $this->calculateMatchConfidence($manualItem, $bestMatches)
                    ];
                }
            }
            
            // Also suggest new items that appear in Nayax but not manual
            $newNayaxItems = $this->findNewNayaxItems($business_id);
            foreach ($newNayaxItems as $nayaxItem) {
                $suggestions[] = [
                    'type' => 'new_nayax_item',
                    'nayax_item' => $nayaxItem,
                    'suggestion' => 'Consider adding to manual system'
                ];
            }
            
            return ['success' => true, 'suggestions' => $suggestions];
            
        } catch (Exception $e) {
            $this->logSyncError($business_id, 'smart_mapping_error', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create item mapping
     */
    public function createItemMapping($business_id, $manualItemId, $nayaxMachineId, $nayaxSelection, $mappingNotes = '') {
        try {
            // Validate inputs
            if (!$this->validateMappingInputs($business_id, $manualItemId, $nayaxMachineId, $nayaxSelection)) {
                throw new Exception('Invalid mapping parameters');
            }
            
            // Check for existing mapping
            $existing = $this->checkExistingMapping($business_id, $manualItemId, $nayaxMachineId, $nayaxSelection);
            if ($existing) {
                throw new Exception('Mapping already exists');
            }
            
            // Create the mapping
            $mappingId = $this->inventoryManager->createItemMapping(
                $business_id,
                $manualItemId,
                $nayaxMachineId,
                $nayaxSelection,
                100, // Default confidence
                $mappingNotes
            );
            
            // Update unified inventory status
            $this->refreshUnifiedInventoryStatus($business_id, $mappingId);
            
            $this->logSyncEvent($business_id, 'item_mapping_created', [
                'mapping_id' => $mappingId,
                'manual_item_id' => $manualItemId,
                'nayax_machine_id' => $nayaxMachineId,
                'nayax_selection' => $nayaxSelection
            ]);
            
            return [
                'success' => true, 
                'mapping_id' => $mappingId,
                'message' => 'Item mapping created successfully'
            ];
            
        } catch (Exception $e) {
            $this->logSyncError($business_id, 'create_mapping_error', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get sync status for business
     */
    public function getSyncStatus($business_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(CASE WHEN sync_status = 'synced' THEN 1 END) as synced_count,
                    COUNT(CASE WHEN sync_status = 'partial' THEN 1 END) as partial_count,
                    COUNT(CASE WHEN sync_status = 'unsynced' THEN 1 END) as unsynced_count,
                    COUNT(*) as total_items,
                    MAX(last_synced_at) as last_sync_time
                FROM unified_inventory_status 
                WHERE business_id = ?
            ");
            $stmt->execute([$business_id]);
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get recent sync events
            $eventsStmt = $this->pdo->prepare("
                SELECT event_type, event_data, created_at 
                FROM unified_inventory_sync_log 
                WHERE business_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $eventsStmt->execute([$business_id]);
            $recentEvents = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'status' => $status,
                'recent_events' => $recentEvents,
                'sync_health' => $this->calculateSyncHealth($status)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Private helper methods
    
    private function updateUnifiedInventoryFromManualSale($business_id, $item_id, $quantity_sold) {
        // Update manual stock quantity
        $stmt = $this->pdo->prepare("
            UPDATE unified_inventory_status 
            SET manual_stock_qty = GREATEST(0, manual_stock_qty - ?),
                total_available_qty = manual_stock_qty + nayax_estimated_qty,
                total_sales_today = total_sales_today + ?,
                total_sales_week = total_sales_week + ?,
                last_manual_sale_at = NOW(),
                last_synced_at = NOW()
            WHERE business_id = ? AND manual_item_id = ?
        ");
        $stmt->execute([$quantity_sold, $quantity_sold, $quantity_sold, $business_id, $item_id]);
    }
    
    private function updateUnifiedInventoryFromNayaxSale($business_id, $machine_id, $item_selection, $quantity) {
        // Update Nayax estimated quantity
        $stmt = $this->pdo->prepare("
            UPDATE unified_inventory_status uis
            JOIN unified_item_mapping uim ON uis.mapping_id = uim.id
            SET uis.nayax_estimated_qty = GREATEST(0, uis.nayax_estimated_qty - ?),
                uis.total_available_qty = uis.manual_stock_qty + uis.nayax_estimated_qty,
                uis.total_sales_today = uis.total_sales_today + ?,
                uis.total_sales_week = uis.total_sales_week + ?,
                uis.last_nayax_sale_at = NOW(),
                uis.last_synced_at = NOW()
            WHERE uis.business_id = ? 
            AND uim.nayax_machine_id = ? 
            AND uim.nayax_item_selection = ?
        ");
        $stmt->execute([$quantity, $quantity, $quantity, $business_id, $machine_id, $item_selection]);
    }
    
    private function parseNayaxWebhook($webhookData) {
        // Parse webhook JSON data
        if (is_string($webhookData)) {
            $data = json_decode($webhookData, true);
        } else {
            $data = $webhookData;
        }
        
        if (!$data || !isset($data['transaction'])) {
            return null;
        }
        
        $transaction = $data['transaction'];
        
        // Extract business_id from machine_id lookup
        $stmt = $this->pdo->prepare("
            SELECT business_id FROM nayax_machines 
            WHERE nayax_machine_id = ?
        ");
        $stmt->execute([$transaction['machine_id']]);
        $business = $stmt->fetch();
        
        if (!$business) {
            return null;
        }
        
        return [
            'business_id' => $business['business_id'],
            'machine_id' => $transaction['machine_id'],
            'item_selection' => $transaction['item_selection'],
            'quantity' => $transaction['quantity'] ?? 1,
            'amount' => $transaction['amount'],
            'timestamp' => $transaction['timestamp']
        ];
    }
    
    private function checkRestockingAlert($business_id, $item_id) {
        $stmt = $this->pdo->prepare("
            SELECT uis.*, uim.unified_name, uis.low_stock_threshold
            FROM unified_inventory_status uis
            JOIN unified_item_mapping uim ON uis.mapping_id = uim.id
            WHERE uis.business_id = ? AND uis.manual_item_id = ?
            AND uis.total_available_qty <= uis.low_stock_threshold
        ");
        $stmt->execute([$business_id, $item_id]);
        $lowStockItem = $stmt->fetch();
        
        if ($lowStockItem) {
            // Trigger low stock alert (could send email, notification, etc.)
            $this->logSyncEvent($business_id, 'low_stock_alert', [
                'item_id' => $item_id,
                'item_name' => $lowStockItem['unified_name'],
                'current_stock' => $lowStockItem['total_available_qty'],
                'threshold' => $lowStockItem['low_stock_threshold']
            ]);
        }
    }
    
    private function checkRestockingAlertFromNayax($business_id, $machine_id, $item_selection) {
        $stmt = $this->pdo->prepare("
            SELECT uis.*, uim.unified_name, uis.low_stock_threshold
            FROM unified_inventory_status uis
            JOIN unified_item_mapping uim ON uis.mapping_id = uim.id
            WHERE uis.business_id = ? 
            AND uim.nayax_machine_id = ? 
            AND uim.nayax_item_selection = ?
            AND uis.total_available_qty <= uis.low_stock_threshold
        ");
        $stmt->execute([$business_id, $machine_id, $item_selection]);
        $lowStockItem = $stmt->fetch();
        
        if ($lowStockItem) {
            $this->logSyncEvent($business_id, 'low_stock_alert', [
                'machine_id' => $machine_id,
                'item_selection' => $item_selection,
                'item_name' => $lowStockItem['unified_name'],
                'current_stock' => $lowStockItem['total_available_qty'],
                'threshold' => $lowStockItem['low_stock_threshold']
            ]);
        }
    }
    
    private function checkSmartMapping($business_id, $item_id) {
        // Check if item has mapping - if not, suggest creating one
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM unified_item_mapping 
            WHERE business_id = ? AND manual_item_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$business_id, $item_id]);
        $hasMappging = $stmt->fetchColumn() > 0;
        
        if (!$hasMappging) {
            $this->logSyncEvent($business_id, 'mapping_suggestion', [
                'manual_item_id' => $item_id,
                'suggestion' => 'Consider creating Nayax mapping for this item'
            ]);
        }
    }
    
    private function logSyncEvent($business_id, $event_type, $event_data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO unified_inventory_sync_log 
            (business_id, event_type, event_data, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$business_id, $event_type, json_encode($event_data)]);
    }
    
    private function logSyncError($business_id, $error_type, $error_message) {
        $this->logSyncEvent($business_id, $error_type, [
            'error' => $error_message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        error_log("UnifiedSyncEngine Error [{$error_type}]: {$error_message}");
    }
    
    private function getActiveBusinesses() {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT business_id 
            FROM unified_item_mapping 
            WHERE deleted_at IS NULL
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    private function reconcileInventoryDiscrepancies($business_id) {
        // Compare manual inventory counts with unified status
        $stmt = $this->pdo->prepare("
            SELECT uis.*, vli.inventory as actual_manual_stock
            FROM unified_inventory_status uis
            JOIN unified_item_mapping uim ON uis.mapping_id = uim.id
            JOIN voting_list_items vli ON uim.manual_item_id = vli.id
            WHERE uis.business_id = ?
            AND uis.manual_stock_qty != vli.inventory
        ");
        $stmt->execute([$business_id]);
        $discrepancies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $reconciled = 0;
        foreach ($discrepancies as $item) {
            // Update unified status with actual manual stock
            $updateStmt = $this->pdo->prepare("
                UPDATE unified_inventory_status 
                SET manual_stock_qty = ?,
                    total_available_qty = ? + nayax_estimated_qty,
                    last_synced_at = NOW(),
                    sync_status = 'synced'
                WHERE id = ?
            ");
            $updateStmt->execute([
                $item['actual_manual_stock'],
                $item['actual_manual_stock'],
                $item['id']
            ]);
            $reconciled++;
        }
        
        return ['count' => $reconciled];
    }
    
    private function calculateSyncHealth($status) {
        $total = $status['total_items'];
        if ($total == 0) return 'no_data';
        
        $syncedPercent = ($status['synced_count'] / $total) * 100;
        
        if ($syncedPercent >= 90) return 'excellent';
        if ($syncedPercent >= 70) return 'good';
        if ($syncedPercent >= 50) return 'fair';
        return 'poor';
    }
    
    private function getUnmappedManualItems($business_id) {
        $stmt = $this->pdo->prepare("
            SELECT vli.*, mi.name, mi.brand, mi.category
            FROM voting_list_items vli
            JOIN voting_lists vl ON vli.voting_list_id = vl.id
            JOIN master_items mi ON vli.master_item_id = mi.id
            WHERE vl.business_id = ?
            AND vli.id NOT IN (
                SELECT manual_item_id FROM unified_item_mapping 
                WHERE business_id = ? AND deleted_at IS NULL
            )
        ");
        $stmt->execute([$business_id, $business_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getUnmappedNayaxItems($business_id) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT nt.item_selection, nt.item_name, nm.nayax_machine_id, nm.machine_name
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ?
            AND CONCAT(nm.nayax_machine_id, '-', nt.item_selection) NOT IN (
                SELECT CONCAT(nayax_machine_id, '-', nayax_item_selection) 
                FROM unified_item_mapping 
                WHERE business_id = ? AND deleted_at IS NULL
            )
        ");
        $stmt->execute([$business_id, $business_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function findBestNayaxMatches($manualItem, $nayaxItems) {
        $matches = [];
        $manualName = strtolower($manualItem['name']);
        
        foreach ($nayaxItems as $nayaxItem) {
            $nayaxName = strtolower($nayaxItem['item_name']);
            
            // Simple string similarity
            $similarity = 0;
            similar_text($manualName, $nayaxName, $similarity);
            
            if ($similarity > 50) { // 50% similarity threshold
                $matches[] = array_merge($nayaxItem, ['similarity' => $similarity]);
            }
        }
        
        // Sort by similarity descending
        usort($matches, function($a, $b) {
            return $b['similarity'] - $a['similarity'];
        });
        
        return array_slice($matches, 0, 3); // Top 3 matches
    }
    
    private function calculateMatchConfidence($manualItem, $matches) {
        $confidences = [];
        foreach ($matches as $match) {
            $confidence = $match['similarity'];
            
            // Boost confidence if brands match
            if (isset($manualItem['brand']) && isset($match['brand'])) {
                if (strtolower($manualItem['brand']) === strtolower($match['brand'])) {
                    $confidence += 20;
                }
            }
            
            $confidences[] = min(100, $confidence);
        }
        return $confidences;
    }
    
    private function validateMappingInputs($business_id, $manualItemId, $nayaxMachineId, $nayaxSelection) {
        // Validate manual item exists
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM voting_list_items vli
            JOIN voting_lists vl ON vli.voting_list_id = vl.id
            WHERE vl.business_id = ? AND vli.id = ?
        ");
        $stmt->execute([$business_id, $manualItemId]);
        if (!$stmt->fetchColumn()) return false;
        
        // Validate Nayax machine exists
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM nayax_machines 
            WHERE business_id = ? AND nayax_machine_id = ?
        ");
        $stmt->execute([$business_id, $nayaxMachineId]);
        if (!$stmt->fetchColumn()) return false;
        
        return true;
    }
    
    private function checkExistingMapping($business_id, $manualItemId, $nayaxMachineId, $nayaxSelection) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM unified_item_mapping 
            WHERE business_id = ? 
            AND manual_item_id = ? 
            AND nayax_machine_id = ? 
            AND nayax_item_selection = ?
            AND deleted_at IS NULL
        ");
        $stmt->execute([$business_id, $manualItemId, $nayaxMachineId, $nayaxSelection]);
        return $stmt->fetch();
    }
    
    private function refreshUnifiedInventoryStatus($business_id, $mappingId) {
        // Recalculate inventory status for the new mapping
        $mapping = $this->inventoryManager->getItemMapping($mappingId);
        if ($mapping) {
            $this->inventoryManager->refreshInventoryStatus($business_id, $mappingId);
        }
    }
    
    private function updateNayaxEstimatedQuantities($business_id) {
        // Update estimated quantities based on recent transaction patterns
        // This is a simplified version - in production, you'd use more sophisticated algorithms
        
        $stmt = $this->pdo->prepare("
            UPDATE unified_inventory_status uis
            JOIN unified_item_mapping uim ON uis.mapping_id = uim.id
            SET uis.nayax_estimated_qty = GREATEST(0, 
                uis.nayax_estimated_qty - (
                    SELECT COALESCE(SUM(quantity), 0) 
                    FROM nayax_transactions nt
                    WHERE nt.machine_id = uim.nayax_machine_id
                    AND nt.item_selection = uim.nayax_item_selection
                    AND DATE(nt.created_at) = CURDATE()
                )
            ),
            uis.total_available_qty = uis.manual_stock_qty + uis.nayax_estimated_qty,
            uis.last_synced_at = NOW()
            WHERE uis.business_id = ?
        ");
        $stmt->execute([$business_id]);
        
        return ['count' => $stmt->rowCount()];
    }
    
    private function cleanupOldSyncLogs($business_id, $daysToKeep = 30) {
        $stmt = $this->pdo->prepare("
            DELETE FROM unified_inventory_sync_log 
            WHERE business_id = ? 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$business_id, $daysToKeep]);
        
        return $stmt->rowCount();
    }
    
    private function generateSyncSummary($business_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_mappings,
                SUM(CASE WHEN sync_status = 'synced' THEN 1 ELSE 0 END) as synced_mappings,
                SUM(total_available_qty) as total_inventory,
                SUM(total_sales_today) as sales_today,
                SUM(total_sales_week) as sales_week
            FROM unified_inventory_status
            WHERE business_id = ?
        ");
        $stmt->execute([$business_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function findNewNayaxItems($business_id) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT nt.item_selection, nt.item_name, 
                   COUNT(*) as transaction_count,
                   SUM(nt.amount) as total_revenue
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ?
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND CONCAT(nm.nayax_machine_id, '-', nt.item_selection) NOT IN (
                SELECT CONCAT(nayax_machine_id, '-', nayax_item_selection) 
                FROM unified_item_mapping 
                WHERE business_id = ? AND deleted_at IS NULL
            )
            GROUP BY nt.item_selection, nt.item_name
            HAVING transaction_count >= 3
            ORDER BY total_revenue DESC
        ");
        $stmt->execute([$business_id, $business_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?> 