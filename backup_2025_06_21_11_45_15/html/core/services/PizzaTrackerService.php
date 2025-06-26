<?php
/**
 * Pizza Tracker Service
 * Handles pizza tracking functionality with list loading and campaign integration
 */

class PizzaTrackerService {
    private static $pdo;
    
    /**
     * Initialize the service with PDO connection
     */
    public static function init($pdo_connection) {
        self::$pdo = $pdo_connection;
    }
    
    /**
     * Create a new pizza tracker with optional campaign integration
     */
    public static function createTracker($data) {
        try {
            // Validate required fields
            if (empty($data['business_id'])) {
                throw new Exception('Business ID is required');
            }
            
            // Start transaction
            self::$pdo->beginTransaction();
            
            // Create tracker record
            $stmt = self::$pdo->prepare("
                INSERT INTO pizza_trackers (
                    business_id, campaign_id, name, description,
                    pizza_list_id, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $stmt->execute([
                $data['business_id'],
                $data['campaign_id'] ?? null,
                $data['name'] ?? 'Pizza Tracker',
                $data['description'] ?? '',
                $data['pizza_list_id'] ?? null
            ]);
            
            $tracker_id = self::$pdo->lastInsertId();
            
            // If pizza list is provided, load pizza takers
            if (!empty($data['pizza_list_id'])) {
                self::loadPizzaTakers($tracker_id, $data['pizza_list_id']);
            }
            
            // If campaign is provided, link pizzas
            if (!empty($data['campaign_id']) && !empty($data['pizza_ids'])) {
                self::linkCampaignPizzas($tracker_id, $data['campaign_id'], $data['pizza_ids']);
            }
            
            self::$pdo->commit();
            
            return [
                'success' => true,
                'tracker_id' => $tracker_id,
                'message' => 'Pizza tracker created successfully'
            ];
            
        } catch (Exception $e) {
            self::$pdo->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Load pizza takers from a list
     */
    private static function loadPizzaTakers($tracker_id, $list_id) {
        // Get pizza takers from list
        $stmt = self::$pdo->prepare("
            SELECT pt.* 
            FROM pizza_takers pt
            JOIN pizza_lists pl ON pt.list_id = pl.id
            WHERE pl.id = ? AND pl.status = 'active'
        ");
        $stmt->execute([$list_id]);
        $takers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Insert takers into tracker
        $stmt = self::$pdo->prepare("
            INSERT INTO pizza_tracker_takers (
                tracker_id, taker_id, name, email, phone,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        foreach ($takers as $taker) {
            $stmt->execute([
                $tracker_id,
                $taker['id'],
                $taker['name'],
                $taker['email'],
                $taker['phone']
            ]);
        }
    }
    
    /**
     * Link pizzas to a campaign tracker
     */
    private static function linkCampaignPizzas($tracker_id, $campaign_id, $pizza_ids) {
        // Validate campaign ownership
        $stmt = self::$pdo->prepare("
            SELECT business_id 
            FROM campaigns 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$campaign_id]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$campaign) {
            throw new Exception('Invalid campaign ID');
        }
        
        // Link pizzas to tracker
        $stmt = self::$pdo->prepare("
            INSERT INTO pizza_tracker_items (
                tracker_id, pizza_id, campaign_id,
                status, created_at
            ) VALUES (?, ?, ?, 'pending', NOW())
        ");
        
        foreach ($pizza_ids as $pizza_id) {
            $stmt->execute([
                $tracker_id,
                $pizza_id,
                $campaign_id
            ]);
        }
    }
    
    /**
     * Get tracker details with pizza takers
     */
    public static function getTracker($tracker_id) {
        try {
            // Get tracker info
            $stmt = self::$pdo->prepare("
                SELECT pt.*, c.name as campaign_name
                FROM pizza_trackers pt
                LEFT JOIN campaigns c ON pt.campaign_id = c.id
                WHERE pt.id = ? AND pt.status = 'active'
            ");
            $stmt->execute([$tracker_id]);
            $tracker = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tracker) {
                throw new Exception('Tracker not found');
            }
            
            // Get pizza takers
            $stmt = self::$pdo->prepare("
                SELECT * FROM pizza_tracker_takers
                WHERE tracker_id = ?
                ORDER BY name ASC
            ");
            $stmt->execute([$tracker_id]);
            $takers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get linked pizzas
            $stmt = self::$pdo->prepare("
                SELECT pti.*, p.name as pizza_name
                FROM pizza_tracker_items pti
                JOIN pizzas p ON pti.pizza_id = p.id
                WHERE pti.tracker_id = ?
                ORDER BY p.name ASC
            ");
            $stmt->execute([$tracker_id]);
            $pizzas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'tracker' => $tracker,
                'takers' => $takers,
                'pizzas' => $pizzas
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update pizza taker status
     */
    public static function updateTakerStatus($tracker_id, $taker_id, $status) {
        try {
            $stmt = self::$pdo->prepare("
                UPDATE pizza_tracker_takers
                SET status = ?, updated_at = NOW()
                WHERE tracker_id = ? AND taker_id = ?
            ");
            
            $success = $stmt->execute([$status, $tracker_id, $taker_id]);
            
            return [
                'success' => $success,
                'message' => $success ? 'Status updated successfully' : 'Failed to update status'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get available pizza lists for a business
     */
    public static function getPizzaLists($business_id) {
        try {
            $stmt = self::$pdo->prepare("
                SELECT * FROM pizza_lists
                WHERE business_id = ? AND status = 'active'
                ORDER BY name ASC
            ");
            $stmt->execute([$business_id]);
            
            return [
                'success' => true,
                'lists' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get available pizzas for a campaign
     */
    public static function getCampaignPizzas($campaign_id) {
        try {
            $stmt = self::$pdo->prepare("
                SELECT p.* 
                FROM pizzas p
                JOIN campaign_pizzas cp ON p.id = cp.pizza_id
                WHERE cp.campaign_id = ? AND p.status = 'active'
                ORDER BY p.name ASC
            ");
            $stmt->execute([$campaign_id]);
            
            return [
                'success' => true,
                'pizzas' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
