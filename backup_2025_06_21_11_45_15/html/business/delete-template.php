<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Ensure user is logged in and is a business user
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['template_id'])) {
    $template_id = (int)$_POST['template_id'];
    
    // Get template info
    $stmt = $pdo->prepare("
        SELECT file_path 
        FROM header_templates 
        WHERE id = ? AND business_id = ? AND created_by = ?
    ");
    $stmt->execute([$template_id, $_SESSION['business_id'], $_SESSION['user_id']]);
    $template = $stmt->fetch();
    
    if ($template) {
        // Delete file
        $file_path = __DIR__ . '/../uploads/headers/' . basename($template['file_path']);
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM header_templates WHERE id = ? AND business_id = ?");
        $stmt->execute([$template_id, $_SESSION['business_id']]);
        
        $_SESSION['message'] = 'Template deleted successfully.';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Template not found or you do not have permission to delete it.';
        $_SESSION['message_type'] = 'danger';
    }
}

header('Location: manage-headers.php');
exit; 