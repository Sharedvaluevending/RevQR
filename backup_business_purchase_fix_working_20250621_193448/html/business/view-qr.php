<?php
// This page has been replaced by the new QR Manager
header('Location: ../qr_manager.php' . (isset($_GET['machine_name']) ? '?search=' . urlencode($_GET['machine_name']) : ''));
exit;

$message = '';
$message_type = '';
$qr_codes = [];
$machine_name = '';

// Get business details
$stmt = $pdo->prepare("
    SELECT b.*, u.username 
    FROM businesses b 
    JOIN users u ON b.id = u.business_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (isset($_GET['machine_name'])) {
    $machine_name = $_GET['machine_name'];
    
    // Get QR codes for this machine
    $stmt = $pdo->prepare("
        SELECT qr.*, 
               c.name as campaign_name, 
               c.description as campaign_description,
               COALESCE(
                   JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
                   CONCAT('/uploads/qr/', qr.code, '.png')
               ) as qr_url
        FROM qr_codes qr
        JOIN campaigns c ON qr.campaign_id = c.id
        WHERE c.business_id = ? AND qr.machine_name = ? AND qr.status = 'active'
        ORDER BY qr.created_at DESC
    ");
    $stmt->execute([$business['id'], $machine_name]);
    $qr_codes = $stmt->fetchAll();
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0">QR Codes for <?php echo htmlspecialchars($machine_name); ?></h1>
                <p class="text-muted">View and manage QR codes for this machine</p>
            </div>
            <a href="manage-machine.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Machines
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (empty($qr_codes)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>No QR codes found for this machine.
        <a href="../qr-generator.php" class="alert-link">Generate a QR code</a> for this machine.
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($qr_codes as $qr): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <img src="<?php echo htmlspecialchars($qr['qr_url']); ?>" 
                                 alt="QR Code" 
                                 class="img-fluid" 
                                 style="max-width: 200px;">
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($qr['campaign_name']); ?></h5>
                        <p class="card-text">
                            <small class="text-muted">
                                Type: <?php echo ucfirst(str_replace('_', ' ', $qr['qr_type'])); ?><br>
                                Created: <?php echo date('M j, Y', strtotime($qr['created_at'])); ?>
                            </small>
                        </p>
                        <?php if ($qr['campaign_description']): ?>
                            <p class="card-text text-muted small">
                                <?php echo htmlspecialchars($qr['campaign_description']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <a href="<?php echo htmlspecialchars($qr['qr_url']); ?>" 
                               class="btn btn-primary" 
                               download="qr-<?php echo htmlspecialchars($machine_name); ?>-<?php echo htmlspecialchars($qr['code']); ?>.png">
                                <i class="bi bi-download me-2"></i>Download QR Code
                            </a>
                            <button type="button" 
                                    class="btn btn-outline-primary" 
                                    onclick="copyToClipboard('<?php echo APP_URL; ?>/vote.php?code=<?php echo htmlspecialchars($qr['code']); ?>')">
                                <i class="bi bi-clipboard me-2"></i>Copy Link
                            </button>
                            <a href="<?php echo APP_URL; ?>/vote.php?code=<?php echo htmlspecialchars($qr['code']); ?>" 
                               class="btn btn-outline-success" 
                               target="_blank">
                                <i class="bi bi-box-arrow-up-right me-2"></i>Test QR Code
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
        alert.style.top = '20px';
        alert.style.right = '20px';
        alert.style.zIndex = '9999';
        alert.innerHTML = `
            <i class="bi bi-check-circle me-2"></i>Link copied to clipboard!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alert);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 3000);
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        alert('Failed to copy link to clipboard');
    });
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 