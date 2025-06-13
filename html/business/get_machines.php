<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Set JSON header
header('Content-Type: application/json');

try {
    // Get business details
    $stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $business = $stmt->fetch();
    $business_id = $business ? $business['id'] : 0;
    
    if (!$business) {
        echo json_encode(['success' => false, 'message' => 'Business not found']);
        exit;
    }
    
    // Get machines (voting lists) for this business
    $stmt = $pdo->prepare("
        SELECT 
            id,
            name,
            description as location,
            status,
            created_at
        FROM voting_lists 
        WHERE business_id = ? 
        ORDER BY name ASC
    ");
    $stmt->execute([$business_id]);
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'machines' => $machines
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching machines: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading machines'
    ]);
}
?> 