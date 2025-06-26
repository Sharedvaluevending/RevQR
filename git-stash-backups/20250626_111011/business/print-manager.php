<?php

// Process QR codes to add proper image URLs
foreach ($qr_codes as &$qr) {
    $meta = $qr['meta'] ? json_decode($qr['meta'], true) : [];
    $file_path = $meta['file_path'] ?? '';
    
    // Try multiple possible QR code locations
    $possible_paths = [
        // From meta field
        $file_path,
        // Standard upload paths
        '/uploads/qr/' . $qr['code'] . '.png',
        '/uploads/qr/1/' . $qr['code'] . '.png',
        '/uploads/qr/business/' . $qr['code'] . '.png',
        // Legacy paths
        '/assets/img/qr/' . $qr['code'] . '.png',
        '/qr/' . $qr['code'] . '.png'
    ];
    
    $qr_image_url = null;
    foreach ($possible_paths as $path) {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
            $qr_image_url = $path;
            break;
        }
    }
    
    // If QR code file not found, try to regenerate it
    if (!$qr_image_url) {
        error_log("QR code file not found for code: " . $qr['code']);
        try {
            $generator = new QRGenerator();
            $regenerate_result = $generator->generate([
                'content' => $qr['url'],
                'size' => 300,
                'foreground_color' => '#000000',
                'background_color' => '#FFFFFF',
                'error_correction_level' => 'H'
            ]);
            
            if ($regenerate_result['success']) {
                $qr_image_url = $regenerate_result['data']['qr_code_url'];
                // Update meta with new file path
                $meta['file_path'] = $qr_image_url;
                $stmt = $pdo->prepare("UPDATE qr_codes SET meta = ? WHERE id = ?");
                $stmt->execute([json_encode($meta), $qr['id']]);
            }
        } catch (Exception $e) {
            error_log("Failed to regenerate QR code: " . $e->getMessage());
        }
    }
    
    $qr['image_url'] = $qr_image_url;
} 