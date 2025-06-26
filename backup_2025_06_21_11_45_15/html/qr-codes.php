<?php
// This page has been replaced by the new QR Manager
header('Location: qr_manager.php');
exit;

// Get business ID
$business_id = get_business_id();

// Get ALL user's QR codes - much more inclusive query
$stmt = $pdo->prepare("
    SELECT qc.*, 
           COALESCE(c.name, vl.name, sw.name, 'QR Code') as name,
           CASE 
               WHEN c.id IS NOT NULL THEN 'campaign'
               WHEN vl.id IS NOT NULL THEN 'voting_list'
               WHEN sw.id IS NOT NULL THEN 'spin_wheel'
               WHEN qc.qr_type = 'static' THEN 'static'
               WHEN qc.qr_type = 'dynamic' THEN 'dynamic'
               WHEN qc.qr_type = 'machine_sales' THEN 'machine_sales'
               WHEN qc.qr_type = 'promotion' THEN 'promotion'
               ELSE 'other'
           END as source_type,
           COALESCE(
               JSON_UNQUOTE(JSON_EXTRACT(qc.meta, '$.file_path')),
               CONCAT('/uploads/qr/', qc.code, '.png')
           ) as qr_url,
           sw.name as spin_wheel_name,
           c.name as campaign_name,
           vl.name as machine_name,
           qc.machine_name as qr_machine_name
    FROM qr_codes qc
    LEFT JOIN campaigns c ON qc.campaign_id = c.id
    LEFT JOIN voting_lists vl ON qc.machine_id = vl.id
    LEFT JOIN spin_wheels sw ON JSON_UNQUOTE(JSON_EXTRACT(qc.meta, '$.spin_wheel_id')) = sw.id
    WHERE (
        -- Direct business ownership
        qc.business_id = ? OR
        
        -- Business ownership through metadata
        JSON_UNQUOTE(JSON_EXTRACT(qc.meta, '$.business_id')) = ? OR
        
        -- Business ownership through campaigns
        c.business_id = ? OR
        
        -- Business ownership through voting lists
        vl.business_id = ? OR
        
        -- Business ownership through spin wheels
        sw.business_id = ?
    ) 
    AND qc.status = 'active'
    ORDER BY qc.created_at DESC
");
$stmt->execute([$business_id, $business_id, $business_id, $business_id, $business_id]);
$qr_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="container mt-5 pt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>My QR Codes <span class="badge bg-primary"><?php echo count($qr_codes); ?></span></h1>
                <div>
                    <a href="<?php echo APP_URL; ?>/qr-generator.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-plus-circle me-2"></i>Basic Generator
                    </a>
                    <a href="<?php echo APP_URL; ?>/qr-generator-enhanced.php" class="btn btn-primary">
                        <i class="bi bi-stars me-2"></i>Enhanced Generator
                </a>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($qr_codes)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>You haven't generated any QR codes yet.
                    <a href="<?php echo APP_URL; ?>/qr-generator.php" class="alert-link">Generate your first QR code</a>.
                </div>
            <?php else: ?>
                <!-- QR Type Filter -->
                <div class="mb-4">
                    <div class="btn-group" role="group" aria-label="QR Type Filter">
                        <button type="button" class="btn btn-outline-secondary active" data-filter="all">
                            All Types <span class="badge bg-secondary"><?php echo count($qr_codes); ?></span>
                        </button>
                        <?php
                        $types = array_count_values(array_column($qr_codes, 'qr_type'));
                        foreach ($types as $type => $count): ?>
                            <button type="button" class="btn btn-outline-secondary" data-filter="<?php echo $type; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $type)); ?> <span class="badge bg-secondary"><?php echo $count; ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="qrGrid">
                    <?php foreach ($qr_codes as $qr): ?>
                        <div class="col qr-card" data-type="<?php echo $qr['qr_type']; ?>">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <img src="<?php echo htmlspecialchars($qr['qr_url']); ?>" 
                                             alt="QR Code" 
                                             class="img-fluid" 
                                             style="max-width: 200px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                    </div>
                                    <h5 class="card-title"><?php echo htmlspecialchars($qr['name']); ?></h5>
                                    <div class="mb-2">
                                        <?php
                                        $typeColors = [
                                            'static' => 'primary',
                                            'dynamic' => 'info', 
                                            'dynamic_voting' => 'success',
                                            'dynamic_vending' => 'warning',
                                            'machine_sales' => 'danger',
                                            'promotion' => 'secondary',
                                            'spin_wheel' => 'warning',
                                            'pizza_tracker' => 'success'
                                        ];
                                        $typeColor = $typeColors[$qr['qr_type']] ?? 'dark';
                                        ?>
                                        <span class="badge bg-<?php echo $typeColor; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $qr['qr_type'])); ?>
                                        </span>
                                    </div>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <?php if ($qr['qr_type'] === 'spin_wheel' && $qr['spin_wheel_name']): ?>
                                                <strong>Spin Wheel:</strong> <?php echo htmlspecialchars($qr['spin_wheel_name']); ?><br>
                                            <?php elseif ($qr['qr_type'] === 'dynamic_voting' && $qr['campaign_name']): ?>
                                                <strong>Campaign:</strong> <?php echo htmlspecialchars($qr['campaign_name']); ?><br>
                                            <?php elseif (in_array($qr['qr_type'], ['dynamic_vending', 'machine_sales', 'promotion'])): ?>
                                                <strong>Machine:</strong> <?php echo htmlspecialchars($qr['machine_name'] ?: $qr['qr_machine_name']); ?><br>
                                            <?php endif; ?>
                                            <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($qr['created_at'])); ?>
                                        </small>
                                    </p>
                                    <div class="d-grid gap-2">
                                        <a href="<?php echo htmlspecialchars($qr['qr_url']); ?>" 
                                           class="btn btn-outline-primary" 
                                           download>
                                            <i class="bi bi-download me-2"></i>Download
                                        </a>
                                        <button type="button" 
                                                class="btn btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal<?php echo $qr['id']; ?>">
                                            <i class="bi bi-trash me-2"></i>Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Modal -->
                        <div class="modal fade" id="deleteModal<?php echo $qr['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Delete QR Code</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to delete this QR code? This action cannot be undone.</p>
                                        <div class="text-center">
                                            <img src="<?php echo htmlspecialchars($qr['qr_url']); ?>" 
                                                 alt="QR Code" 
                                                 class="img-fluid" 
                                                 style="max-width: 100px;">
                                            <p class="mt-2"><strong><?php echo htmlspecialchars($qr['name']); ?></strong></p>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form action="<?php echo APP_URL; ?>/api/qr/delete.php" method="POST" class="d-inline">
                                            <input type="hidden" name="qr_id" value="<?php echo $qr['id']; ?>">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// QR Type Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('[data-filter]');
    const qrCards = document.querySelectorAll('.qr-card');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filterType = this.getAttribute('data-filter');
            
            // Update active button
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Filter cards
            qrCards.forEach(card => {
                if (filterType === 'all' || card.getAttribute('data-type') === filterType) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 