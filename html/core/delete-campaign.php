<?php
// Ensure we return JSON
header('Content-Type: application/json');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';

// Require business role
require_role('business');

// Get business ID
$stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    echo json_encode(['success' => false, 'error' => 'Business not found']);
    exit;
}

$business_id = $business['id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$response = ['success' => false, 'error' => ''];

if (!isset($input['campaign_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing campaign ID']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Verify campaign belongs to business
    $stmt = $pdo->prepare("SELECT id FROM qr_campaigns WHERE id = ? AND business_id = ?");
    $stmt->execute([$input['campaign_id'], $business_id]);
    
    if (!$stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Campaign not found or unauthorized']);
        exit;
    }
    
    // Get all QR codes for this campaign to delete their files
    $stmt = $pdo->prepare("SELECT code FROM qr_codes WHERE campaign_id = ?");
    $stmt->execute([$input['campaign_id']]);
    $qr_codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Delete QR code files
    foreach ($qr_codes as $code) {
        $qr_file = __DIR__ . '/../assets/img/qr/' . $code . '.png';
        if (file_exists($qr_file)) {
            unlink($qr_file);
        }
    }
    
    // Delete QR codes first (this will cascade delete votes due to foreign key constraints)
    $stmt = $pdo->prepare("DELETE FROM qr_codes WHERE campaign_id = ?");
    $result = $stmt->execute([$input['campaign_id']]);
    
    if (!$result) {
        throw new Exception("Failed to delete QR codes");
    }
    
    // Delete campaign items
    $stmt = $pdo->prepare("DELETE FROM campaign_items WHERE campaign_id = ?");
    $result = $stmt->execute([$input['campaign_id']]);
    
    if (!$result) {
        throw new Exception("Failed to delete campaign items");
    }
    
    // Now delete the campaign
    $stmt = $pdo->prepare("DELETE FROM qr_campaigns WHERE id = ?");
    $result = $stmt->execute([$input['campaign_id']]);
    
    if (!$result) {
        throw new Exception("Failed to delete campaign");
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Campaign deleted successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error deleting campaign: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred while deleting the campaign']);
}
exit; 