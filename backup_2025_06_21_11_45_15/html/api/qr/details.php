<?php
require_once __DIR__ . '/../../core/config.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid QR code ID']);
    exit;
}

$qr_id = (int)$_GET['id'];

try {
    // Get QR code details
    $stmt = $pdo->prepare("
        SELECT 
            id, code, qr_type, machine_name, machine_location, 
            url, qr_url, status, created_at, meta
        FROM qr_codes 
        WHERE id = ? AND status != 'deleted'
    ");
    $stmt->execute([$qr_id]);
    $qr = $stmt->fetch();

    if (!$qr) {
        http_response_code(404);
        echo json_encode(['error' => 'QR code not found']);
        exit;
    }

    // Get scan statistics (try qr_code_stats first, fallback to qr_scans)
    $scan_stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_scans,
            COUNT(DISTINCT DATE(COALESCE(qcs.scan_time, qs.created_at))) as unique_days,
            MAX(COALESCE(qcs.scan_time, qs.created_at)) as last_scan
        FROM qr_codes qc
        LEFT JOIN qr_code_stats qcs ON qcs.qr_code_id = qc.id
        LEFT JOIN qr_scans qs ON qs.qr_code_id = qc.id
        WHERE qc.id = ? AND (qcs.id IS NOT NULL OR qs.id IS NOT NULL)
    ");
    $scan_stats_stmt->execute([$qr_id]);
    $scan_stats = $scan_stats_stmt->fetch();

    // Get recent scans
    $recent_scans_stmt = $pdo->prepare("
        SELECT 
            COALESCE(qcs.scan_time, qs.created_at) as scanned_at, 
            COALESCE(qcs.device_type, qs.device_type) as device_type, 
            COALESCE(qcs.ip_address, qs.ip_address) as ip_address 
        FROM qr_codes qc
        LEFT JOIN qr_code_stats qcs ON qcs.qr_code_id = qc.id
        LEFT JOIN qr_scans qs ON qs.qr_code_id = qc.id
        WHERE qc.id = ? AND (qcs.id IS NOT NULL OR qs.id IS NOT NULL)
        ORDER BY COALESCE(qcs.scan_time, qs.created_at) DESC 
        LIMIT 5
    ");
    $recent_scans_stmt->execute([$qr_id]);
    $recent_scans = $recent_scans_stmt->fetchAll();

    // Combine data
    $response = array_merge($qr, [
        'scan_stats' => $scan_stats,
        'recent_scans' => $recent_scans
    ]);

    echo json_encode($response);

} catch (Exception $e) {
    error_log("QR Details API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
} 