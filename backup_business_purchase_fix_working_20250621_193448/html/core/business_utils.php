<?php
/**
 * Business Utility Functions
 * 
 * This file contains shared utility functions for business operations
 * to ensure consistency across the application.
 */

require_once __DIR__ . '/config.php';

/**
 * Get or create business_id for a user
 * 
 * This function handles the database schema inconsistency by checking both
 * users.business_id and businesses.user_id to maintain backward compatibility.
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return int Business ID
 * @throws Exception If unable to determine business association
 */
function getOrCreateBusinessId($pdo, $user_id) {
    try {
        // First try to get business_id from users table
        $stmt = $pdo->prepare("SELECT business_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && $user['business_id']) {
            return $user['business_id'];
        }
        
        // If no business_id in users table, check businesses table
        $stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $business = $stmt->fetch();
        
        if ($business) {
            // Update users table with business_id for consistency
            $stmt = $pdo->prepare("UPDATE users SET business_id = ? WHERE id = ?");
            $stmt->execute([$business['id'], $user_id]);
            return $business['id'];
        }
        
        // Create new business if none exists
        $stmt = $pdo->prepare("INSERT INTO businesses (user_id, name, slug) VALUES (?, ?, ?)");
        $slug = 'my-business-' . time() . '-' . $user_id;
        $stmt->execute([$user_id, 'My Business', $slug]);
        $business_id = $pdo->lastInsertId();
        
        // Update users table with new business_id
        $stmt = $pdo->prepare("UPDATE users SET business_id = ? WHERE id = ?");
        $stmt->execute([$business_id, $user_id]);
        
        return $business_id;
    } catch (Exception $e) {
        error_log("Error in getOrCreateBusinessId: " . $e->getMessage());
        throw new Exception("Unable to determine business association");
    }
}

/**
 * Validate that a user has access to a specific business
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $business_id Business ID to check access for
 * @return bool Whether the user has access to the business
 */
function validateBusinessAccess($pdo, $user_id, $business_id) {
    try {
        // Check if user is admin (admins can access all businesses)
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && $user['role'] === 'admin') {
            return true;
        }
        
        // Check if user's business_id matches
        $user_business_id = getOrCreateBusinessId($pdo, $user_id);
        return $user_business_id === $business_id;
        
    } catch (Exception $e) {
        error_log("Error in validateBusinessAccess: " . $e->getMessage());
        return false;
    }
}

/**
 * Get business information by ID
 * 
 * @param PDO $pdo Database connection
 * @param int $business_id Business ID
 * @return array|false Business data or false if not found
 */
function getBusinessById($pdo, $business_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$business_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error in getBusinessById: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a table exists in the database
 * 
 * @param PDO $pdo Database connection
 * @param string $table_name Table name to check
 * @return bool Whether the table exists
 */
function tableExists($pdo, $table_name) {
    try {
        // Validate table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_name)) {
            return false;
        }
        
        // Use INFORMATION_SCHEMA instead of SHOW TABLES for better prepared statement support
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ?
        ");
        $stmt->execute([$table_name]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Error checking table existence: " . $e->getMessage());
        return false;
    }
}

/**
 * Determine which table structure to use for lists/machines
 * 
 * @param PDO $pdo Database connection
 * @return string Either 'voting_lists' or 'machines'
 */
function getListTableStructure($pdo) {
    if (tableExists($pdo, 'voting_lists')) {
        return 'voting_lists';
    }
    return 'machines';
} 