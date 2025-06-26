<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/promotional_ads_manager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['ad_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ad_id']);
    exit;
}

$ad_id = (int)$input['ad_id'];
$user_id = $input['user_id'] ?? null;

try {
    $adsManager = new PromotionalAdsManager($pdo);
    $adsManager->trackClick($ad_id, $user_id);
    
    echo json_encode(['success' => true, 'message' => 'Click tracked']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to track click', 'details' => $e->getMessage()]);
}
?> 