<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

// Get QR code from URL parameter
$qr_code = $_GET['code'] ?? '';

if (empty($qr_code)) {
    http_response_code(404);
    die('QR code not found');
}

try {
    // Look up QR code in database
    $stmt = $pdo->prepare("
        SELECT qr.*, 
               COALESCE(qr.url, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.content'))) as redirect_url,
               b.name as business_name
        FROM qr_codes qr
        LEFT JOIN businesses b ON qr.business_id = b.id
        WHERE qr.code = ? AND qr.status = 'active'
    ");
    $stmt->execute([$qr_code]);
    $qr_data = $stmt->fetch();
    
    if (!$qr_data) {
        http_response_code(404);
        die('QR code not found or inactive');
    }
    
    // Track QR code scan
    try {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $device_type = 'Unknown';
        
        // Device detection
        if (preg_match('/Mobile|Android|iPhone/', $user_agent)) {
            $device_type = 'Mobile';
        } elseif (preg_match('/Tablet|iPad/', $user_agent)) {
            $device_type = 'Tablet';
        } else {
            $device_type = 'Desktop';
        }
        
        // Create qr_code_stats table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS qr_code_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                qr_code_id INT NOT NULL,
                scan_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                device_type VARCHAR(50),
                INDEX idx_qr_stats_code (qr_code_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO qr_code_stats (qr_code_id, ip_address, user_agent, device_type) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $qr_data['id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $user_agent,
            $device_type
        ]);
    } catch (Exception $e) {
        error_log("QR tracking error: " . $e->getMessage());
    }
    
    $redirect_url = $qr_data['redirect_url'];
    
    // Ensure URL is absolute
    if (!empty($redirect_url)) {
        if (!preg_match('/^https?:\/\//', $redirect_url)) {
            // Relative URL, make it absolute
            $redirect_url = APP_URL . '/' . ltrim($redirect_url, '/');
        }
        
        // Redirect to the target URL
        header('Location: ' . $redirect_url, true, 302);
        exit;
    } else {
        // No URL found, show error
        http_response_code(404);
        die('QR code destination not configured');
    }
    
} catch (Exception $e) {
    error_log("QR redirect error: " . $e->getMessage());
    http_response_code(500);
    die('Internal server error');
}
?> 