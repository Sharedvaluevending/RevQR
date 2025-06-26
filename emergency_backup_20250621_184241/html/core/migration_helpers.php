<?php
/**
 * Migration Helper Functions
 * Provides safe database access during schema migration
 */

function safeGetQRCodes($pdo, $business_id, $search = '', $type_filter = '', $sort = 'created_desc') {
    try {
        // Use the safe view if it exists, otherwise fallback to original table
        $table_check = $pdo->query("SHOW TABLES LIKE 'qr_codes_safe'")->fetch();
        $use_safe_view = $table_check !== false;
        
        if ($use_safe_view) {
            $from_clause = "qr_codes_safe qr LEFT JOIN campaigns c ON qr.campaign_id = c.id";
            $business_filter = "qr.safe_business_id = ?";
        } else {
            $from_clause = "qr_codes qr LEFT JOIN campaigns c ON qr.campaign_id = c.id";
            $business_filter = "(qr.business_id = ? OR c.business_id = ?)";
        }
        
        $where_conditions = ["qr.status != 'deleted'", $business_filter];
        $params = $use_safe_view ? [$business_id] : [$business_id, $business_id];
        
        if ($search) {
            $where_conditions[] = "(qr.machine_name LIKE ? OR qr.code LIKE ? OR c.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($type_filter) {
            $where_conditions[] = "qr.qr_type = ?";
            $params[] = $type_filter;
        }
        
        $order_by = "qr.created_at DESC";
        switch ($sort) {
            case 'name_asc':
                $order_by = "COALESCE(c.name, qr.machine_name, qr.code) ASC";
                break;
            case 'name_desc':
                $order_by = "COALESCE(c.name, qr.machine_name, qr.code) DESC";
                break;
            case 'type_asc':
                $order_by = "qr.qr_type ASC";
                break;
            case 'type_desc':
                $order_by = "qr.qr_type DESC";
                break;
            case 'created_asc':
                $order_by = "qr.created_at ASC";
                break;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $stmt = $pdo->prepare("
            SELECT 
                qr.*,
                c.name as campaign_name,
                c.description as campaign_description,
                c.campaign_type as campaign_type,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
                    CONCAT('/uploads/qr/', qr.code, '.png')
                ) as qr_url,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.preview_path')),
                    CONCAT('/uploads/qr/', qr.code, '_preview.png')
                ) as preview_url,
                (SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = qr.id) as scan_count,
                (SELECT MAX(scan_time) FROM qr_code_stats WHERE qr_code_id = qr.id) as last_scan,
                (SELECT COUNT(*) FROM votes WHERE qr_code_id = qr.id) as vote_count
            FROM $from_clause
            WHERE $where_clause
            ORDER BY $order_by
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error in safeGetQRCodes: " . $e->getMessage());
        return [];
    }
}

function safeGetMachines($pdo, $business_id) {
    try {
        // Check if unified view exists
        $view_check = $pdo->query("SHOW TABLES LIKE 'machines_unified'")->fetch();
        if ($view_check) {
            $stmt = $pdo->prepare("
                SELECT id, name, location, source_table, created_at 
                FROM machines_unified 
                WHERE business_id = ? 
                ORDER BY name ASC
            ");
            $stmt->execute([$business_id]);
            return $stmt->fetchAll();
        }
        
        // Fallback to voting_lists
        $stmt = $pdo->prepare("
            SELECT 
                id,
                name,
                description as location,
                'voting_list' as source_table,
                created_at
            FROM voting_lists 
            WHERE business_id = ? 
            ORDER BY name ASC
        ");
        $stmt->execute([$business_id]);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error in safeGetMachines: " . $e->getMessage());
        return [];
    }
}

function safeGetVotingListItems($pdo, $voting_list_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                item_name,
                item_category,
                retail_price,
                cost_price,
                inventory,
                list_type,
                status
            FROM voting_list_items
            WHERE voting_list_id = ?
            ORDER BY 
                CASE WHEN inventory > 0 THEN 0 ELSE 1 END,
                item_name ASC
        ");
        
        $stmt->execute([$voting_list_id]);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error in safeGetVotingListItems: " . $e->getMessage());
        return [];
    }
}

function isMigrationSafe($pdo) {
    try {
        // Check if migration backup tables exist
        $backup_check = $pdo->query("SHOW TABLES LIKE 'qr_codes_backup'")->fetch();
        return $backup_check !== false;
    } catch (PDOException $e) {
        return false;
    }
}

function getMigrationStatus($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT phase, step, status, message, created_at 
            FROM migration_log 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function logMigrationStep($pdo, $phase, $step, $status, $message = '') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO migration_log (phase, step, status, message) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$phase, $step, $status, $message]);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to log migration step: " . $e->getMessage());
        return false;
    }
}
?> 