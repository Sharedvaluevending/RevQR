<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Require business role
require_role('business');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['machine_name'])) {
    $machine_name = $_POST['machine_name'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get business ID
        $stmt = $pdo->prepare("
            SELECT b.id 
            FROM businesses b 
            JOIN users u ON b.id = u.business_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $business = $stmt->fetch();
        
        if (!$business) {
            throw new Exception('Business not found');
        }
        
        // Get all QR codes for this machine
        $stmt = $pdo->prepare("
            SELECT qr.id 
            FROM qr_codes qr
            JOIN campaigns c ON qr.campaign_id = c.id
            WHERE c.business_id = ? AND qr.machine_name = ?
        ");
        $stmt->execute([$business['id'], $machine_name]);
        $qr_codes = $stmt->fetchAll();
        
        // Delete votes for these QR codes
        if (!empty($qr_codes)) {
            $qr_ids = array_column($qr_codes, 'id');
            $placeholders = str_repeat('?,', count($qr_ids) - 1) . '?';
            
            $stmt = $pdo->prepare("
                DELETE FROM votes 
                WHERE qr_code_id IN ($placeholders)
            ");
            $stmt->execute($qr_ids);
        }
        
        // Delete QR codes
        $stmt = $pdo->prepare("
            DELETE qr FROM qr_codes qr
            JOIN campaigns c ON qr.campaign_id = c.id
            WHERE c.business_id = ? AND qr.machine_name = ?
        ");
        $stmt->execute([$business['id'], $machine_name]);
        
        // Commit transaction
        $pdo->commit();
        
        $message = 'Machine and all associated data have been deleted successfully.';
        $message_type = 'success';
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $message = 'Error deleting machine: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Redirect back to manage machines page
$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;
header('Location: manage-machine.php');
exit; 