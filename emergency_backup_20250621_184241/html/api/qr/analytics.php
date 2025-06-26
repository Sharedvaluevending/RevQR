<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

// Require business role
require_role('business');

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'QR code ID required']);
    exit;
}

$qr_id = (int)$_GET['id'];

try {
    // Verify QR code belongs to this business
    $stmt = $pdo->prepare("
        SELECT qr.*, c.business_id 
        FROM qr_codes qr
        LEFT JOIN campaigns c ON qr.campaign_id = c.id
        WHERE qr.id = ?
    ");
    $stmt->execute([$qr_id]);
    $qr_code = $stmt->fetch();
    
    if (!$qr_code) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'QR code not found']);
        exit;
    }
    
    // Check business ownership
    if ($qr_code['business_id'] && $qr_code['business_id'] != $_SESSION['business_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    // Get total scans
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = ?");
    $stmt->execute([$qr_id]);
    $total_scans = $stmt->fetchColumn();
    
    // Get total votes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE qr_code_id = ?");
    $stmt->execute([$qr_id]);
    $total_votes = $stmt->fetchColumn();
    
    // Get recent scans (last 10)
    $stmt = $pdo->prepare("
        SELECT scan_time, device_type, browser, os, ip_address, location
        FROM qr_code_stats 
        WHERE qr_code_id = ?
        ORDER BY scan_time DESC
        LIMIT 10
    ");
    $stmt->execute([$qr_id]);
    $recent_scans = $stmt->fetchAll();
    
    // Get scan statistics by day (last 30 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(scan_time) as scan_date,
            COUNT(*) as scan_count
        FROM qr_code_stats 
        WHERE qr_code_id = ? AND scan_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(scan_time)
        ORDER BY scan_date DESC
    ");
    $stmt->execute([$qr_id]);
    $daily_scans = $stmt->fetchAll();
    
    // Get device type breakdown
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(device_type, 'Unknown') as device_type,
            COUNT(*) as count
        FROM qr_code_stats 
        WHERE qr_code_id = ?
        GROUP BY device_type
        ORDER BY count DESC
    ");
    $stmt->execute([$qr_id]);
    $device_breakdown = $stmt->fetchAll();
    
    // Get browser breakdown
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(browser, 'Unknown') as browser,
            COUNT(*) as count
        FROM qr_code_stats 
        WHERE qr_code_id = ?
        GROUP BY browser
        ORDER BY count DESC
        LIMIT 10
    ");
    $stmt->execute([$qr_id]);
    $browser_breakdown = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_scans' => $total_scans,
            'total_votes' => $total_votes,
            'recent_scans' => $recent_scans,
            'daily_scans' => $daily_scans,
            'device_breakdown' => $device_breakdown,
            'browser_breakdown' => $browser_breakdown
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Analytics API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
} 