<?php

// CRITICAL FIX: Updated query to include business_id, machine_id, and url field
$stmt = $pdo->prepare("
    INSERT INTO qr_codes (
        business_id, machine_id, campaign_id, qr_type, machine_name, machine_location, 
        url, code, meta, created_at, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
");

// Prepare metadata including all the enhanced options and content
$metadata = [
    'business_id' => $business_id,
    'content' => $content,
    'file_path' => $qr_code_url,
    'location' => $data['location'] ?? '',
    'options' => $options,
    'validated_machine_id' => $validated_machine_id ?? null,
    'validated_machine_name' => $validated_machine_name ?? null,
    'spin_wheel_id' => $validated_spin_wheel_id ?? null,
    'generated_at' => date('Y-m-d H:i:s')
];

error_log("Enhanced QR: Metadata: " . json_encode($metadata));

$result = $stmt->execute([
    $business_id, // business_id (REQUIRED for multi-tenant isolation)
    $validated_machine_id ?? null, // machine_id (validated if machine-related QR type)
    $data['campaign_id'] ?? null, // campaign_id
    $data['qr_type'], // qr_type
    $validated_machine_name ?? ($data['machine_name'] ?? ''), // machine_name
    $data['location'] ?? '', // machine_location
    $content, // url (CRITICAL FIX: Store the actual URL)
    $qr_code, // code
    json_encode($metadata) // meta
]);

if ($result) {
    error_log("Enhanced QR: Database insert successful");
    // Ensure QR code file exists and is accessible
    if (!file_exists($qr_code_url)) {
        error_log("Enhanced QR: QR code file not found at: " . $qr_code_url);
        // Try to regenerate QR code if file is missing
        $generator = new QRGenerator();
        $regenerate_result = $generator->generate([
            'content' => $content,
            'size' => $options['size'] ?? 300,
            'foreground_color' => $options['foreground_color'] ?? '#000000',
            'background_color' => $options['background_color'] ?? '#FFFFFF',
            'error_correction_level' => $options['error_correction_level'] ?? 'H'
        ]);
        
        if ($regenerate_result['success']) {
            error_log("Enhanced QR: Successfully regenerated QR code");
        } else {
            error_log("Enhanced QR: Failed to regenerate QR code: " . ($regenerate_result['error'] ?? 'Unknown error'));
        }
    }
} else {
    error_log("Enhanced QR: Database insert failed");
} 