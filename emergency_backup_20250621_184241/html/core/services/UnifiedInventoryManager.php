<?php
/**
 * Unified Inventory Manager
 * Handles synchronization between Manual and Nayax inventory systems  
 */

class UnifiedInventoryManager {
    
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new item mapping between systems
     */
    public function createItemMapping($business_id, $data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO unified_item_mapping (
                    business_id, master_item_id, voting_list_item_id,
                    nayax_machine_id, nayax_product_code, nayax_slot_position,
                    unified_name, unified_category, unified_price, unified_cost,
                    mapping_confidence, mapped_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $business_id,
                $data['master_item_id'] ?? null,
                $data['voting_list_item_id'] ?? null,
                $data['nayax_machine_id'] ?? null,
                $data['nayax_product_code'] ?? null,
                $data['nayax_slot_position'] ?? null,
                $data['unified_name'],
                $data['unified_category'] ?? null,
                $data['unified_price'] ?? null,
                $data['unified_cost'] ?? null,
                $data['mapping_confidence'] ?? 'high',
                $data['mapped_by'] ?? null
            ]);
            
            $mapping_id = $this->pdo->lastInsertId();
            
            // Create corresponding inventory status record
            $this->createInventoryStatus($mapping_id, $business_id);
            
            return $mapping_id;
            
        } catch (Exception $e) {
            error_log("Error creating item mapping: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create inventory status record for a mapping
     */
    private function createInventoryStatus($mapping_id, $business_id) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO unified_inventory_status (
                    unified_mapping_id, business_id, created_at
                ) VALUES (?, ?, NOW())
            ");
            
            return $stmt->execute([$mapping_id, $business_id]);
            
        } catch (Exception $e) {
            error_log("Error creating inventory status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unified inventory for a business
     */
    public function getUnifiedInventory($business_id, $filters = []) {
        try {
            $where_conditions = ["uim.business_id = ? AND uim.is_active = 1"];
            $params = [$business_id];
            
            // Add filters
            if (!empty($filters['category'])) {
                $where_conditions[] = "uim.unified_category = ?";
                $params[] = $filters['category'];
            }
            
            if (!empty($filters['low_stock_only'])) {
                $where_conditions[] = "uis.total_available_qty <= uis.low_stock_threshold";
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    uim.id as mapping_id,
                    uim.unified_name,
                    uim.unified_category,
                    uim.unified_price,
                    uim.unified_cost,
                    uim.mapping_confidence,
                    
                    -- System identification
                    CASE 
                        WHEN uim.voting_list_item_id IS NOT NULL AND uim.nayax_product_code IS NOT NULL THEN 'unified'
                        WHEN uim.nayax_product_code IS NOT NULL THEN 'nayax_only'
                        WHEN uim.voting_list_item_id IS NOT NULL THEN 'manual_only'
                        ELSE 'unmapped'
                    END as system_type,
                    
                    -- Inventory status
                    uis.manual_stock_qty,
                    uis.nayax_estimated_qty,
                    uis.total_available_qty,
                    uis.low_stock_threshold,
                    
                    -- Sales performance
                    uis.total_sales_today,
                    uis.total_sales_week,
                    uis.sync_status,
                    uis.updated_at
                    
                FROM unified_item_mapping uim
                LEFT JOIN unified_inventory_status uis ON uim.id = uis.unified_mapping_id
                
                WHERE $where_clause
                ORDER BY uim.unified_category, uim.unified_name
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting unified inventory: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get business inventory summary
     */
    public function getInventorySummary($business_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN uis.total_available_qty <= uis.low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count,
                    SUM(CASE WHEN uis.total_available_qty = 0 THEN 1 ELSE 0 END) as out_of_stock_count,
                    SUM(CASE WHEN uis.sync_status = 'error' THEN 1 ELSE 0 END) as sync_errors,
                    SUM(uis.total_sales_today) as total_sales_today,
                    SUM(uis.total_sales_week) as total_sales_week,
                    MAX(uis.updated_at) as last_updated
                FROM unified_item_mapping uim
                LEFT JOIN unified_inventory_status uis ON uim.id = uis.unified_mapping_id
                WHERE uim.business_id = ? AND uim.is_active = 1
            ");
            
            $stmt->execute([$business_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting inventory summary: " . $e->getMessage());
            return [];
        }
    }
}
?>