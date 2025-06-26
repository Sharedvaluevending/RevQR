<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Get business ID
$stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    http_response_code(404);
    echo json_encode(['error' => 'Business not found']);
    exit;
}

$business_id = $business['id'];

// Get campaign ID from request
$campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$campaign_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Campaign ID is required']);
    exit;
}

try {
    // Get campaign details
    $stmt = $pdo->prepare("
        SELECT c.*
        FROM campaigns c
        WHERE c.id = ? AND c.business_id = ?
    ");
    $stmt->execute([$campaign_id, $business_id]);
    $campaign = $stmt->fetch();

    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found']);
        exit;
    }

    // Get associated lists
    $stmt = $pdo->prepare("
        SELECT vl.*, COUNT(vli.id) as item_count
        FROM voting_lists vl
        JOIN campaign_voting_lists cvl ON vl.id = cvl.voting_list_id
        LEFT JOIN voting_list_items vli ON vl.id = vli.voting_list_id
        WHERE cvl.campaign_id = ?
        GROUP BY vl.id
    ");
    $stmt->execute([$campaign_id]);
    $lists = $stmt->fetchAll();

    // Get associated QR codes
    $stmt = $pdo->prepare("
        SELECT qr.*, m.name as machine_name
        FROM qr_codes qr
        LEFT JOIN machines m ON qr.machine_id = m.id
        WHERE qr.campaign_id = ?
    ");
    $stmt->execute([$campaign_id]);
    $qr_codes = $stmt->fetchAll();

    // Return all data
    echo json_encode([
        'campaign' => $campaign,
        'lists' => $lists,
        'qr_codes' => $qr_codes
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
} 