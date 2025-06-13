<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

// Require business role
require_role('business');

// Get business ID
$business_id = get_business_id();

// Get ALL user's QR codes - simplified query with proper business_id filtering
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
    LEFT JOIN campaigns c ON qc.campaign_id = c.id AND c.business_id = ?
    LEFT JOIN voting_lists vl ON qc.machine_id = vl.id AND vl.business_id = ?
    LEFT JOIN spin_wheels sw ON JSON_UNQUOTE(JSON_EXTRACT(qc.meta, '$.spin_wheel_id')) = sw.id AND sw.business_id = ?
    WHERE qc.business_id = ? 
    AND qc.status = 'active'
    ORDER BY qc.created_at DESC
");
$stmt->execute([$business_id, $business_id, $business_id, $business_id]);
$qr_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Force no-cache headers to prevent old navigation from showing  
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="container-fluid mt-5 pt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>QR Display <span class="badge bg-info"><?php echo count($qr_codes); ?> codes</span></h1>
                <div>
                    <button type="button" class="btn btn-outline-primary me-2" id="toggleFullscreen">
                        <i class="bi bi-arrows-fullscreen me-2"></i>Toggle Fullscreen
                    </button>
                    <a href="<?php echo APP_URL; ?>/qr-generator.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-plus-circle me-2"></i>Basic Generator
                    </a>
                    <a href="<?php echo APP_URL; ?>/qr-generator-enhanced.php" class="btn btn-primary">
                        <i class="bi bi-stars me-2"></i>Enhanced Generator
                    </a>
                </div>
            </div>

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

                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4" id="qrDisplay">
                    <?php foreach ($qr_codes as $qr): ?>
                        <div class="col qr-card" data-type="<?php echo $qr['qr_type']; ?>">
                            <div class="card h-100 display-card">
                                <div class="card-body text-center">
                                    <img src="<?php echo htmlspecialchars($qr['qr_url']); ?>" 
                                         alt="QR Code" 
                                         class="img-fluid mb-3" 
                                         style="max-width: 100%; border-radius: 8px;">
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
                                                <?php echo htmlspecialchars($qr['spin_wheel_name']); ?>
                                            <?php elseif ($qr['qr_type'] === 'dynamic_voting' && $qr['campaign_name']): ?>
                                                <?php echo htmlspecialchars($qr['campaign_name']); ?>
                                            <?php elseif (in_array($qr['qr_type'], ['dynamic_vending', 'machine_sales', 'promotion'])): ?>
                                                <?php echo htmlspecialchars($qr['machine_name'] ?: $qr['qr_machine_name']); ?>
                                            <?php else: ?>
                                                <?php echo date('M j, Y', strtotime($qr['created_at'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.display-card {
    transition: transform 0.3s ease;
}

.display-card:hover {
    transform: scale(1.05);
}

#qrDisplay.fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: white;
    z-index: 9999;
    padding: 2rem;
    overflow-y: auto;
}

#qrDisplay.fullscreen .col {
    display: flex;
    justify-content: center;
    align-items: center;
}

#qrDisplay.fullscreen .card {
    max-width: 400px;
    margin: 0 auto;
}

.btn-group .btn {
    border-radius: 0.375rem !important;
    margin-right: 0.25rem;
}

.qr-card {
    transition: opacity 0.3s ease;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fullscreen toggle
document.getElementById('toggleFullscreen').addEventListener('click', function() {
    const display = document.getElementById('qrDisplay');
    display.classList.toggle('fullscreen');
    
    if (display.classList.contains('fullscreen')) {
        document.body.style.overflow = 'hidden';
            this.innerHTML = '<i class="bi bi-fullscreen-exit me-2"></i>Exit Fullscreen';
    } else {
        document.body.style.overflow = '';
            this.innerHTML = '<i class="bi bi-arrows-fullscreen me-2"></i>Toggle Fullscreen';
        }
    });

    // QR Type Filter functionality
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
                    card.style.opacity = '1';
                } else {
                    card.style.display = 'none';
                    card.style.opacity = '0';
                }
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 