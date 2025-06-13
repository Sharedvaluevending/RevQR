<?php
/**
 * Admin Functions
 * RevenueQR Platform - Administrative Functions
 */

// Prevent direct access
if (!defined('APP_URL')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/database.php';

/**
 * Get total user count
 * @return int
 */
function admin_get_user_count() {
    try {
        $result = db_fetch("SELECT COUNT(*) as count FROM users");
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting user count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get total business count
 * @return int
 */
function admin_get_business_count() {
    try {
        $result = db_fetch("SELECT COUNT(*) as count FROM users WHERE role = 'business'");
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting business count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get active voting campaigns count
 * @return int
 */
function admin_get_active_campaigns() {
    try {
        $result = db_fetch("
            SELECT COUNT(*) as count 
            FROM voting_lists 
            WHERE status = 'active' 
            AND (end_date IS NULL OR end_date > NOW())
        ");
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting active campaigns: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get total votes cast today
 * @return int
 */
function admin_get_votes_today() {
    try {
        $result = db_fetch("
            SELECT COUNT(*) as count 
            FROM votes 
            WHERE DATE(created_at) = CURDATE()
        ");
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting today's votes: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get QR codes generated today
 * @return int
 */
function admin_get_qr_codes_today() {
    try {
        $result = db_fetch("
            SELECT COUNT(*) as count 
            FROM qr_codes 
            WHERE DATE(created_at) = CURDATE()
        ");
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting QR codes today: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get system health overview
 * @return array
 */
function admin_get_system_health() {
    $health = [
        'database' => 'unknown',
        'files' => 'unknown',
        'cache' => 'unknown',
        'overall' => 'unknown'
    ];
    
    try {
        // Database health
        $db_health = db_health_check();
        $health['database'] = $db_health['status'] === 'healthy' ? 'good' : 'warning';
        
        // File system health
        $upload_dir = __DIR__ . '/../uploads/';
        $health['files'] = is_writable($upload_dir) ? 'good' : 'warning';
        
        // Cache health
        $cache_dir = __DIR__ . '/../storage/cache/';
        $health['cache'] = is_writable($cache_dir) ? 'good' : 'warning';
        
        // Overall health
        $issues = array_filter($health, function($status) {
            return $status === 'warning' || $status === 'error';
        });
        
        if (empty($issues)) {
            $health['overall'] = 'good';
        } elseif (count($issues) < 2) {
            $health['overall'] = 'warning';
        } else {
            $health['overall'] = 'error';
        }
        
    } catch (Exception $e) {
        error_log("Error checking system health: " . $e->getMessage());
        $health['overall'] = 'error';
    }
    
    return $health;
}

/**
 * Get recent activity log
 * @param int $limit Number of records to return
 * @return array
 */
function admin_get_recent_activity($limit = 10) {
    try {
        $activities = [];
        
        // Recent user registrations
        $recent_users = db_fetch_all("
            SELECT username, created_at, 'user_registration' as type
            FROM users 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC 
            LIMIT ?
        ", [$limit]);
        
        foreach ($recent_users as $user) {
            $activities[] = [
                'type' => 'user_registration',
                'description' => "New user registered: {$user['username']}",
                'timestamp' => $user['created_at']
            ];
        }
        
        // Recent votes
        $recent_votes = db_fetch_all("
            SELECT COUNT(*) as vote_count, DATE(created_at) as vote_date
            FROM votes 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY vote_date DESC 
            LIMIT ?
        ", [$limit]);
        
        foreach ($recent_votes as $vote_day) {
            $activities[] = [
                'type' => 'voting_activity',
                'description' => "{$vote_day['vote_count']} votes cast",
                'timestamp' => $vote_day['vote_date'] . ' 00:00:00'
            ];
        }
        
        // Sort by timestamp
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($activities, 0, $limit);
        
    } catch (Exception $e) {
        error_log("Error getting recent activity: " . $e->getMessage());
        return [];
    }
}

/**
 * Get business performance metrics
 * @return array
 */
function admin_get_business_metrics() {
    try {
        $metrics = [];
        
        // Top businesses by campaigns
        $top_campaigns = db_fetch_all("
            SELECT 
                u.username as business_name,
                COUNT(vl.id) as campaign_count,
                SUM(CASE WHEN vl.status = 'active' THEN 1 ELSE 0 END) as active_campaigns
            FROM users u
            LEFT JOIN voting_lists vl ON u.id = vl.business_id
            WHERE u.role = 'business'
            GROUP BY u.id, u.username
            ORDER BY campaign_count DESC
            LIMIT 5
        ");
        
        $metrics['top_businesses'] = $top_campaigns;
        
        // Campaign status distribution
        $campaign_stats = db_fetch("
            SELECT 
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM voting_lists
        ");
        
        $metrics['campaign_distribution'] = $campaign_stats;
        
        return $metrics;
        
    } catch (Exception $e) {
        error_log("Error getting business metrics: " . $e->getMessage());
        return [];
    }
}

/**
 * Get platform revenue statistics
 * @return array
 */
function admin_get_revenue_stats() {
    try {
        $stats = [];
        
        // Total sales (if sales table exists)
        try {
            $total_sales = db_fetch("SELECT SUM(amount) as total FROM sales WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stats['monthly_sales'] = $total_sales['total'] ?? 0;
        } catch (Exception $e) {
            $stats['monthly_sales'] = 0;
        }
        
        // QR Code usage
        $qr_usage = db_fetch("
            SELECT COUNT(*) as total_qr_codes 
            FROM qr_codes 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['monthly_qr_codes'] = $qr_usage['total_qr_codes'] ?? 0;
        
        // Active business subscriptions (estimated)
        $active_businesses = db_fetch("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE role = 'business' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['new_businesses_month'] = $active_businesses['count'] ?? 0;
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Error getting revenue stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Perform database maintenance
 * @return array Results of maintenance operations
 */
function admin_database_maintenance() {
    $results = [];
    
    try {
        // Optimize tables
        $tables = ['users', 'voting_lists', 'voting_list_items', 'votes', 'qr_codes'];
        
        foreach ($tables as $table) {
            try {
                db_execute("OPTIMIZE TABLE `$table`");
                $results[] = "Optimized table: $table";
            } catch (Exception $e) {
                $results[] = "Failed to optimize $table: " . $e->getMessage();
            }
        }
        
        // Clean old QR codes (older than 90 days)
        $cleaned = db_execute("
            DELETE FROM qr_codes 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND type = 'temporary'
        ");
        $results[] = "Cleaned old temporary QR codes: " . $cleaned->rowCount();
        
        // Clean old votes (keep only last 6 months)
        $old_votes = db_execute("
            DELETE FROM votes 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ");
        $results[] = "Archived old votes: " . $old_votes->rowCount();
        
    } catch (Exception $e) {
        $results[] = "Maintenance error: " . $e->getMessage();
    }
    
    return $results;
}

/**
 * Generate admin dashboard data
 * @return array Complete dashboard data
 */
function admin_get_dashboard_data() {
    return [
        'stats' => [
            'total_users' => admin_get_user_count(),
            'total_businesses' => admin_get_business_count(),
            'active_campaigns' => admin_get_active_campaigns(),
            'votes_today' => admin_get_votes_today(),
            'qr_codes_today' => admin_get_qr_codes_today()
        ],
        'system_health' => admin_get_system_health(),
        'recent_activity' => admin_get_recent_activity(5),
        'business_metrics' => admin_get_business_metrics(),
        'revenue_stats' => admin_get_revenue_stats(),
        'database_stats' => db_get_stats()
    ];
}

/**
 * Log admin action
 * @param string $action Action performed
 * @param string $description Description of the action
 * @param int $admin_id Admin user ID
 */
function admin_log_action($action, $description, $admin_id = null) {
    try {
        if (!$admin_id && isset($_SESSION['user_id'])) {
            $admin_id = $_SESSION['user_id'];
        }
        
        // Create admin log table if it doesn't exist
        db_execute("
            CREATE TABLE IF NOT EXISTS admin_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT,
                action VARCHAR(100),
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_admin_id (admin_id),
                INDEX idx_created_at (created_at)
            )
        ");
        
        db_execute("
            INSERT INTO admin_logs (admin_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ", [
            $admin_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
    } catch (Exception $e) {
        error_log("Error logging admin action: " . $e->getMessage());
    }
}
?> 