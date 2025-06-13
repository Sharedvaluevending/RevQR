<?php
require_once 'html/core/config.php';
require_once 'html/core/database.php';

// Simulate business session for testing
$_SESSION['user_id'] = 1;
$_SESSION['business_id'] = 1;
$_SESSION['role'] = 'business';

echo "=== TESTING EXACT DASHBOARD FUNCTION ===\n";

$business_id = 1;

// Test the exact function from dashboard_enhanced.php
function get_enhanced_analytics($business_id) {
    $analytics = [];
    
    try {
        // Include database functions
        require_once __DIR__ . '/html/core/database.php';
        
        // Campaign Overview (fixed - no status column exists)
        $campaigns = db_fetch("
            SELECT 
                COUNT(*) as total_campaigns,
                COUNT(*) as active_campaigns,
                0 as completed_campaigns
            FROM voting_lists 
            WHERE business_id = ?
        ", [$business_id]) ?: ['total_campaigns' => 0, 'active_campaigns' => 0, 'completed_campaigns' => 0];
        
        // Vote Analytics (fixed - votes join on machine_id not voting_list_id)
        $votes = db_fetch("
            SELECT 
                COUNT(*) as total_votes,
                COUNT(CASE WHEN DATE(v.created_at) = CURDATE() THEN 1 END) as votes_today,
                COUNT(CASE WHEN DATE(v.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as votes_week
            FROM votes v
            JOIN machines m ON v.machine_id = m.id
            WHERE m.business_id = ?
        ", [$business_id]) ?: ['total_votes' => 0, 'votes_today' => 0, 'votes_week' => 0];
        
        // QR Code Management (this one actually works correctly)
        $qr_codes = db_fetch("
            SELECT 
                COUNT(*) as total_qr_codes,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as qr_today,
                COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as qr_week
            FROM qr_codes 
            WHERE business_id = ?
        ", [$business_id]) ?: ['total_qr_codes' => 0, 'qr_today' => 0, 'qr_week' => 0];
        
        return [
            'campaigns' => $campaigns,
            'votes' => $votes,
            'qr_codes' => $qr_codes
        ];
        
    } catch (Exception $e) {
        error_log("Error getting enhanced analytics: " . $e->getMessage());
        return [
            'campaigns' => ['total_campaigns' => 0, 'active_campaigns' => 0, 'completed_campaigns' => 0],
            'votes' => ['total_votes' => 0, 'votes_today' => 0, 'votes_week' => 0],
            'qr_codes' => ['total_qr_codes' => 0, 'qr_today' => 0, 'qr_week' => 0]
        ];
    }
}

$enhanced_analytics = get_enhanced_analytics($business_id);

echo "Dashboard analytics result:\n";
print_r($enhanced_analytics);

echo "\nDashboard should display:\n";
echo "Active Campaigns: " . $enhanced_analytics['campaigns']['active_campaigns'] . "\n";
echo "Votes Today: " . $enhanced_analytics['votes']['votes_today'] . "\n";
echo "QR Codes Today: " . $enhanced_analytics['qr_codes']['qr_today'] . "\n";
echo "QR Codes Total: " . $enhanced_analytics['qr_codes']['total_qr_codes'] . "\n";
?> 