<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

require_role('business');
require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container-fluid mt-5 pt-4">
    <h1 class="h3 mb-3">Template Designer</h1>
    <p class="text-muted">Create custom print layouts for your QR code labels</p>
    
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        Custom template designer coming soon! For now, use the pre-built templates in the Print Manager.
    </div>
    
    <a href="print-manager.php" class="btn btn-primary">
        <i class="bi bi-arrow-left me-2"></i>Back to Print Manager
    </a>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?>