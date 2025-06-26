<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

// Require business role
require_role('business');

if (!isset($_GET['ids'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'QR code IDs required']);
    exit;
}

$qr_ids = explode(',', $_GET['ids']);
$qr_ids = array_filter(array_map('intval', $qr_ids));

if (empty($qr_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid QR code IDs required']);
    exit;
}

try {
    // Get QR codes with business verification
    $placeholders = str_repeat('?,', count($qr_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT 
            qr.*,
            c.name as campaign_name,
            c.business_id,
            COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
                CONCAT('/uploads/qr/', qr.code, '.png')
            ) as qr_url
        FROM qr_codes qr
        LEFT JOIN campaigns c ON qr.campaign_id = c.id
        WHERE qr.id IN ($placeholders)
    ");
    $stmt->execute($qr_ids);
    $qr_codes = $stmt->fetchAll();
    
    // Filter by business ownership
    $valid_qr_codes = array_filter($qr_codes, function($qr) {
        return !$qr['business_id'] || $qr['business_id'] == $_SESSION['business_id'];
    });
    
    if (empty($valid_qr_codes)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No valid QR codes found']);
        exit;
    }
    
    // Create ZIP file
    $zip = new ZipArchive();
    $zip_filename = 'qr_codes_export_' . date('Y-m-d_H-i-s') . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
    
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not create ZIP file']);
        exit;
    }
    
    // Add QR code files to ZIP
    foreach ($valid_qr_codes as $qr) {
        $file_path = __DIR__ . '/../../' . ltrim($qr['qr_url'], '/');
        
        if (file_exists($file_path)) {
            $filename = ($qr['campaign_name'] ?: 'QR-' . $qr['code']) . '.png';
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename); // Sanitize filename
            $zip->addFile($file_path, $filename);
        }
    }
    
    // Add CSV with QR code data
    $csv_content = "Name,Type,Code,URL,Created,Machine,Location\n";
    foreach ($valid_qr_codes as $qr) {
        $csv_content .= sprintf(
            '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
            $qr['campaign_name'] ?: 'QR-' . $qr['code'],
            $qr['qr_type'],
            $qr['code'],
            APP_URL . '/vote.php?code=' . $qr['code'],
            $qr['created_at'],
            $qr['machine_name'] ?: '',
            $qr['machine_location'] ?: ''
        );
    }
    $zip->addFromString('qr_codes_data.csv', $csv_content);
    
    $zip->close();
    
    // Send ZIP file
    if (file_exists($zip_path)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
        header('Content-Length: ' . filesize($zip_path));
        
        readfile($zip_path);
        unlink($zip_path); // Delete temp file
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not create export file']);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Export API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()]);
} 