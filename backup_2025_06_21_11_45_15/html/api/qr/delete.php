<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

// Check authentication
if (!is_logged_in()) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Require business role
require_role('business');

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/qr-codes.php');
    exit;
}

// Get business ID
$business_id = get_business_id();

// Get QR code ID from POST data
$input = json_decode(file_get_contents('php://input'), true);
$qr_id = $input['qr_id'] ?? $_POST['qr_id'] ?? null;

if (!$qr_id) {
    if (isset($input)) {
        // JSON response for API calls
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'QR code ID is required'
        ]);
    } else {
        // Redirect for form submissions
        $_SESSION['error'] = 'QR code ID is required';
        header('Location: ' . APP_URL . '/qr-codes.php');
    }
    exit;
}

try {
    // First, get the QR code details to verify ownership and get file path
    $stmt = $pdo->prepare("
        SELECT qc.*, 
               COALESCE(c.business_id, vl.business_id, JSON_UNQUOTE(JSON_EXTRACT(qc.meta, '$.business_id'))) as owner_business_id,
               COALESCE(
                   JSON_UNQUOTE(JSON_EXTRACT(qc.meta, '$.file_path')),
                   CONCAT('/uploads/qr/', qc.code, '.png')
               ) as file_path
        FROM qr_codes qc
        LEFT JOIN campaigns c ON qc.campaign_id = c.id
        LEFT JOIN voting_lists vl ON qc.machine_id = vl.id
        WHERE qc.id = ? AND qc.status = 'active'
    ");
    $stmt->execute([$qr_id]);
    $qr_code = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$qr_code) {
        if (isset($input)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'QR code not found'
            ]);
        } else {
            $_SESSION['error'] = 'QR code not found';
            header('Location: ' . APP_URL . '/qr-codes.php');
        }
        exit;
    }
    
    // Verify ownership
    if ($qr_code['owner_business_id'] != $business_id) {
        if (isset($input)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'You do not have permission to delete this QR code'
            ]);
        } else {
            $_SESSION['error'] = 'You do not have permission to delete this QR code';
            header('Location: ' . APP_URL . '/qr-codes.php');
        }
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Mark QR code as deleted (soft delete)
    $stmt = $pdo->prepare("UPDATE qr_codes SET status = 'deleted' WHERE id = ?");
    $stmt->execute([$qr_id]);
    
    // Try to delete the physical file
    $file_path = __DIR__ . '/../../' . ltrim($qr_code['file_path'], '/');
    if (file_exists($file_path)) {
        if (!unlink($file_path)) {
            error_log("Failed to delete QR code file: " . $file_path);
            // Don't fail the transaction if file deletion fails
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    if (isset($input)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'QR code deleted successfully'
        ]);
    } else {
        $_SESSION['success'] = 'QR code deleted successfully';
        header('Location: ' . APP_URL . '/qr-codes.php');
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Error deleting QR code: " . $e->getMessage());
    
    if (isset($input)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete QR code. Please try again.'
        ]);
    } else {
        $_SESSION['error'] = 'Failed to delete QR code. Please try again.';
        header('Location: ' . APP_URL . '/qr-codes.php');
    }
}
exit;
?> 