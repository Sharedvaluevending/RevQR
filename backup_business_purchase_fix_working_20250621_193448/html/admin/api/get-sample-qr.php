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

$qr_type = $_GET['type'] ?? '';

if (!$qr_type) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'QR type required']);
    exit();
}

try {
    // Get a sample QR code of the specified type
    $stmt = $pdo->prepare("
        SELECT code, qr_type, machine_name, business_id
        FROM qr_codes 
        WHERE qr_type = ? AND status = 'active'
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $stmt->execute([$qr_type]);
    $qr_code = $stmt->fetch();

    if ($qr_code) {
        echo json_encode([
            'success' => true,
            'qr_code' => $qr_code['code'],
            'qr_type' => $qr_code['qr_type'],
            'machine_name' => $qr_code['machine_name'],
            'business_id' => $qr_code['business_id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => "No QR codes found for type: $qr_type",
            'available_types' => getAvailableTypes($pdo)
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

function getAvailableTypes($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT qr_type, COUNT(*) as count
            FROM qr_codes 
            WHERE status = 'active'
            GROUP BY qr_type
            ORDER BY count DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        return [];
    }
}
?> 