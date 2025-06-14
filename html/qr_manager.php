<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';

// Manual authentication check instead of require_role
if (!is_logged_in()) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

if (!has_role('business')) {
    echo "Debug: User role is: " . ($_SESSION['role'] ?? 'not set') . "<br>";
    echo "Debug: Expected role: business<br>";
    echo "Debug: Session data: <pre>" . print_r($_SESSION, true) . "</pre>";
    header('Location: ' . APP_URL . '/unauthorized.php');
    exit();
}

// Get business_id with proper error handling
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    $business_id = null;
    error_log("Error getting business ID in QR manager: " . $e->getMessage());
}

// Initialize variables
$qr_codes = [];
$analytics_data = [];
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$sort = $_GET['sort'] ?? 'created_desc';

if ($business_id) {
    try {
        // Build query with filters
        $where_conditions = ["qr.status != 'deleted'"];
        $params = [];
        
        // Add business filter - FIXED to use qr.business_id directly
        $where_conditions[] = "qr.business_id = ?";
        $params[] = $business_id;
        
        // Add search filter
        if ($search) {
            $where_conditions[] = "(qr.machine_name LIKE ? OR qr.code LIKE ? OR c.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Add type filter
        if ($type_filter) {
            $where_conditions[] = "qr.qr_type = ?";
            $params[] = $type_filter;
        }
        
        // Build ORDER BY clause
        $order_by = "qr.created_at DESC"; // default
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
            default:
                $order_by = "qr.created_at DESC";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Fetch QR codes with campaign and analytics data
        $stmt = $pdo->prepare("
            SELECT 
                qr.*,
                c.name as campaign_name,
                c.description as campaign_description,
                c.type as campaign_type,
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
            FROM qr_codes qr
            LEFT JOIN campaigns c ON qr.campaign_id = c.id
            WHERE $where_clause
            ORDER BY $order_by
        ");
        $stmt->execute($params);
        $qr_codes = $stmt->fetchAll();
        
        // Get analytics summary
        $stmt = $pdo->prepare("
            SELECT 
                qr.qr_type,
                COUNT(*) as count,
                SUM(COALESCE((SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = qr.id), 0)) as total_scans
            FROM qr_codes qr
            LEFT JOIN campaigns c ON qr.campaign_id = c.id AND c.business_id = ?
            WHERE qr.business_id = ? AND qr.status != 'deleted'
            GROUP BY qr.qr_type
        ");
        $stmt->execute([$business_id, $business_id]);
        $analytics_data = $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error fetching QR codes: " . $e->getMessage());
    }
}

// Simple output instead of full page for testing
echo "<h1>QR Manager Fixed Test</h1>";
echo "<p>Business ID: " . $business_id . "</p>";
echo "<p>QR Codes found: " . count($qr_codes) . "</p>";

if (count($qr_codes) > 0) {
    echo "<h2>Your QR Codes:</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Type</th><th>Machine</th><th>Status</th><th>Created</th></tr>";
    foreach ($qr_codes as $qr) {
        echo "<tr>";
        echo "<td>" . $qr['id'] . "</td>";
        echo "<td>" . $qr['qr_type'] . "</td>";
        echo "<td>" . ($qr['machine_name'] ?: '-') . "</td>";
        echo "<td>" . $qr['status'] . "</td>";
        echo "<td>" . date('M j, Y H:i', strtotime($qr['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No QR codes found.</p>";
}

echo "<p><a href='qr_manager.php'>Try Original QR Manager</a></p>";
echo "<p><a href='business/dashboard.php'>Back to Dashboard</a></p>";
?> 