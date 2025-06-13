<?php

// ... existing code ...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete_qr':
            $qr_id = intval($_POST['qr_id']);
            
            try {
                // First get QR code details to verify ownership and get file path
                $stmt = $pdo->prepare("
                    SELECT qr.*, 
                           COALESCE(c.business_id, vl.business_id, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.business_id'))) as owner_business_id,
                           COALESCE(
                               JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
                               CONCAT('/uploads/qr/', qr.code, '.png')
                           ) as file_path
                    FROM qr_codes qr
                    LEFT JOIN campaigns c ON qr.campaign_id = c.id
                    LEFT JOIN voting_lists vl ON qr.machine_id = vl.id
                    WHERE qr.id = ? AND qr.status = 'active'
                ");
                $stmt->execute([$qr_id]);
                $qr_data = $stmt->fetch();
                
                if ($qr_data) {
                    // Verify ownership
                    if ($qr_data['owner_business_id'] != $business_id) {
                        $message = "You do not have permission to delete this QR code";
                        $message_type = "danger";
                        break;
                    }
                    
                    // Delete QR code file
                    $file_path = $qr_data['file_path'];
                    
                    // Try multiple possible QR code locations
                    $possible_paths = [
                        $file_path,
                        '/uploads/qr/' . $qr_data['code'] . '.png',
                        '/uploads/qr/1/' . $qr_data['code'] . '.png',
                        '/uploads/qr/business/' . $qr_data['code'] . '.png',
                        '/assets/img/qr/' . $qr_data['code'] . '.png',
                        '/qr/' . $qr_data['code'] . '.png'
                    ];
                    
                    foreach ($possible_paths as $path) {
                        $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
                        if (file_exists($full_path)) {
                            unlink($full_path);
                        }
                    }
                    
                    // Delete from database
                    $stmt = $pdo->prepare("
                        UPDATE qr_codes 
                        SET status = 'deleted' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$qr_id]);
                    
                    $message = "QR code deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "QR code not found";
                    $message_type = "danger";
                }
            } catch (Exception $e) {
                $message = "Error deleting QR code: " . $e->getMessage();
                $message_type = "danger";
            }
            break;
            
        case 'toggle_status':
            // ... existing code ...
            break;
    }
}

// Get QR codes for this business
$qr_codes = [];
try {
    $stmt = $pdo->prepare("
        SELECT qr.*, 
               COALESCE(qr.url, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.content'))) as current_url,
               COALESCE(
                   JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
                   CONCAT('/uploads/qr/', qr.code, '.png')
               ) as file_path,
               (SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = qr.id) as scan_count,
               COALESCE(c.business_id, vl.business_id, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.business_id'))) as owner_business_id
        FROM qr_codes qr
        LEFT JOIN campaigns c ON qr.campaign_id = c.id
        LEFT JOIN voting_lists vl ON qr.machine_id = vl.id
        WHERE qr.status = 'active'
        AND (
            qr.business_id = ? OR
            c.business_id = ? OR
            vl.business_id = ? OR
            JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.business_id')) = ?
        )
        ORDER BY qr.created_at DESC
    ");
    $stmt->execute([$business_id, $business_id, $business_id, $business_id]);
    $qr_codes = $stmt->fetchAll();
} catch (Exception $e) {
    $message = "Error loading QR codes: " . $e->getMessage();
    $message_type = "danger";
}
// ... existing code ... 