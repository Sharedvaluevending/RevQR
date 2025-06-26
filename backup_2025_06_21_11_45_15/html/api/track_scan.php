<?php
require_once __DIR__ . '/../core/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['qr_code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'QR code required']);
    exit;
}

try {
    // Get QR code details
    $stmt = $pdo->prepare("SELECT id FROM qr_codes WHERE code = ?");
    $stmt->execute([$data['qr_code']]);
    $qr_id = $stmt->fetchColumn();
    
    if (!$qr_id) {
        echo json_encode(['success' => false, 'message' => 'QR code not found']);
        exit;
    }
    
    // Detect device and browser info
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device_type = 'Unknown';
    $browser = 'Unknown';
    $os = 'Unknown';
    
    // Simple device detection
    if (preg_match('/Mobile|Android|iPhone|iPad/', $user_agent)) {
        $device_type = 'Mobile';
    } elseif (preg_match('/Tablet|iPad/', $user_agent)) {
        $device_type = 'Tablet';
    } else {
        $device_type = 'Desktop';
    }
    
    // Simple browser detection
    if (preg_match('/Chrome/i', $user_agent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox/i', $user_agent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari/i', $user_agent)) {
        $browser = 'Safari';
    } elseif (preg_match('/Edge/i', $user_agent)) {
        $browser = 'Edge';
    }
    
    // Simple OS detection
    if (preg_match('/Windows/i', $user_agent)) {
        $os = 'Windows';
    } elseif (preg_match('/Mac/i', $user_agent)) {
        $os = 'macOS';
    } elseif (preg_match('/Linux/i', $user_agent)) {
        $os = 'Linux';
    } elseif (preg_match('/Android/i', $user_agent)) {
        $os = 'Android';
    } elseif (preg_match('/iOS/i', $user_agent)) {
        $os = 'iOS';
    }
    
    // Get IP and location info
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Try to get location from IP (basic implementation)
    $location = '';
    if ($ip_address && $ip_address !== '127.0.0.1') {
        // You could integrate with a geolocation service here
        $location = 'Unknown';
    }
    
    // Check if this is a unique scan (within last hour)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM qr_code_stats 
        WHERE qr_code_id = ? AND ip_address = ? 
        AND scan_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$qr_id, $ip_address]);
    $recent_scans = $stmt->fetchColumn();
    
    // Log the scan
    $stmt = $pdo->prepare("
        INSERT INTO qr_code_stats (
            qr_code_id, scan_time, ip_address, user_agent, 
            referrer, device_type, browser, os, location
        ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $qr_id,
        $ip_address,
        $user_agent,
        $referrer,
        $device_type,
        $browser,
        $os,
        $location
    ]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'qr_id' => $qr_id,
            'is_unique' => $recent_scans == 0,
            'device_type' => $device_type,
            'browser' => $browser,
            'os' => $os
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Scan tracking error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Tracking failed']);
} 