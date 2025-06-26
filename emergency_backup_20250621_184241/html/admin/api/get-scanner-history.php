<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

// Check admin access
if (!is_logged_in() || !has_role('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit();
}

$limit = min((int)($_GET['limit'] ?? 10), 50); // Max 50 records

try {
    // Initialize table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_scanner_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_user_id INT NOT NULL,
            qr_code_scanned VARCHAR(255),
            payload_data JSON,
            scan_type ENUM('live', 'test', 'invalid') DEFAULT 'test',
            device_info JSON,
            processing_time_ms INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_user_id) REFERENCES users(id)
        )
    ");

    // Get recent scanner history
    $stmt = $pdo->prepare("
        SELECT 
            sl.*,
            u.username as admin_username,
            qr.qr_type,
            qr.machine_name,
            qr.status as qr_status
        FROM admin_scanner_logs sl
        LEFT JOIN users u ON sl.admin_user_id = u.id
        LEFT JOIN qr_codes qr ON qr.code = sl.qr_code_scanned OR qr.code = CONCAT('qr_', sl.qr_code_scanned)
        WHERE sl.admin_user_id = ?
        ORDER BY sl.created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$_SESSION['user_id'], $limit]);
    $scans = $stmt->fetchAll();

    // Get summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_scans,
            COUNT(CASE WHEN scan_type = 'live' THEN 1 END) as live_scans,
            COUNT(CASE WHEN scan_type = 'test' THEN 1 END) as test_scans,
            COUNT(CASE WHEN scan_type = 'invalid' THEN 1 END) as invalid_scans,
            AVG(processing_time_ms) as avg_processing_time,
            MAX(created_at) as last_scan
        FROM admin_scanner_logs 
        WHERE admin_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'scans' => $scans,
        'stats' => [
            'total_scans_24h' => (int)$stats['total_scans'],
            'live_scans' => (int)$stats['live_scans'],
            'test_scans' => (int)$stats['test_scans'],
            'invalid_scans' => (int)$stats['invalid_scans'],
            'avg_processing_time_ms' => round((float)$stats['avg_processing_time'], 1),
            'last_scan' => $stats['last_scan']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve scanner history: ' . $e->getMessage()
    ]);
}
?> 