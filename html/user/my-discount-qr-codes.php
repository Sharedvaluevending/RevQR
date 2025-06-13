<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/qr_code_manager.php';

// Require user role
require_role('user');

// Get filter parameters
$filter = $_GET['filter'] ?? 'active'; // active, all, redeemed, expired
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build filter conditions
$where_conditions = ["bp.user_id = ?"];
$params = [$_SESSION['user_id']];

if ($filter === 'active') {
    $where_conditions[] = "bp.status = 'pending' AND bp.expires_at > NOW()";
} elseif ($filter === 'redeemed') {
    $where_conditions[] = "bp.status = 'redeemed'";
} elseif ($filter === 'expired') {
    $where_conditions[] = "(bp.expires_at <= NOW() AND bp.status != 'redeemed') OR bp.status = 'expired'";
}

// Only show purchases with QR codes
$where_conditions[] = "bp.qr_code_data IS NOT NULL";

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*)
    FROM business_purchases bp
    JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
    JOIN businesses b ON bp.business_id = b.id
    WHERE $where_clause
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_items = $stmt->fetchColumn();
$total_pages = ceil($total_items / $per_page);

// Get QR code purchases
$sql = "
    SELECT 
        bp.*,
        bsi.item_name,
        bsi.item_description,
        b.name as business_name,
        b.type as business_type,
        nm.machine_name,
        nm.location_description,
        CASE 
            WHEN bp.expires_at <= NOW() AND bp.status != 'redeemed' THEN 'expired'
            WHEN bp.status = 'redeemed' THEN 'redeemed'
            WHEN bp.status = 'pending' AND bp.expires_at > NOW() THEN 'active'
            ELSE 'unknown'
        END as current_status
    FROM business_purchases bp
    JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
    JOIN businesses b ON bp.business_id = b.id
    LEFT JOIN nayax_machines nm ON bp.nayax_machine_id = nm.nayax_machine_id
    WHERE $where_clause
    ORDER BY bp.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$qr_purchases = $stmt->fetchAll();

// Get user's QR code statistics
$qr_stats = QRCodeManager::getUserQRStats($_SESSION['user_id']);

// Get user's current QR coin balance
$user_balance = QRCoinManager::getBalance($_SESSION['user_id']);

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.qr-code-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 15px;
    overflow: hidden;
}

.qr-code-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.qr-code-display {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    padding: 15px;
    text-align: center;
}

.status-badge-active {
    background: linear-gradient(135deg, #28a745, #20c997);
}

.status-badge-redeemed {
    background: linear-gradient(135deg, #6c757d, #495057);
}

.status-badge-expired {
    background: linear-gradient(135deg, #dc3545, #c82333);
}

.discount-badge {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
}

.mobile-qr-modal .modal-dialog {
    margin: 10px;
    max-width: calc(100% - 20px);
}

.mobile-qr-modal .qr-code-large {
    max-width: 280px;
    width: 100%;
    height: auto;
}

@media (max-width: 768px) {
    .qr-code-card {
        margin-bottom: 1rem;
    }
    
    .stats-card {
        text-align: center;
        margin-bottom: 1rem;
    }
}
</style>

<div class="container py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="mb-3 mb-md-0">
                    <h1 class="mb-2">
                        <i class="bi bi-qr-code text-primary me-2"></i>My Discount QR Codes
                    </h1>
                    <p class="text-muted mb-0">Scan these QR codes at Nayax vending machines to redeem your discounts</p>
                </div>
                <div class="text-end">
                    <div class="d-flex align-items-center mb-2">
                        <img src="../img/qrCoin.png" alt="QR Coin" style="width: 2rem; height: 2rem;" class="me-2">
                        <h4 class="text-warning mb-0"><?php echo number_format($user_balance); ?></h4>
                    </div>
                    <small class="text-muted">Current Balance</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-6 col-md-3">
            <div class="card stats-card bg-primary text-white">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1"><?php echo $qr_stats['active_codes']; ?></h3>
                    <small>Active Codes</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stats-card bg-success text-white">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1"><?php echo $qr_stats['redeemed_codes']; ?></h3>
                    <small>Redeemed</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stats-card bg-warning text-white">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1"><?php echo $qr_stats['total_scans']; ?></h3>
                    <small>Total Scans</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stats-card bg-info text-white">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1"><?php echo $qr_stats['total_qr_codes']; ?></h3>
                    <small>Total Codes</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="btn-group w-100 d-md-inline-block" role="group">
                <a href="?filter=active" class="btn btn-outline-primary <?php echo $filter === 'active' ? 'active' : ''; ?>">
                    <i class="bi bi-check-circle me-1"></i>Active
                </a>
                <a href="?filter=all" class="btn btn-outline-secondary <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="bi bi-list me-1"></i>All Codes
                </a>
                <a href="?filter=redeemed" class="btn btn-outline-success <?php echo $filter === 'redeemed' ? 'active' : ''; ?>">
                    <i class="bi bi-check-square me-1"></i>Redeemed
                </a>
                <a href="?filter=expired" class="btn btn-outline-danger <?php echo $filter === 'expired' ? 'active' : ''; ?>">
                    <i class="bi bi-clock me-1"></i>Expired
                </a>
            </div>
        </div>
    </div>

    <?php if (empty($qr_purchases)): ?>
        <!-- Empty State -->
        <div class="row">
            <div class="col-12">
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-qr-code display-1 text-muted mb-3"></i>
                        <h3 class="text-muted mb-3">No QR Codes Found</h3>
                        <p class="text-muted mb-4">
                            <?php if ($filter === 'active'): ?>
                                You don't have any active discount QR codes. Purchase some business discounts to get started!
                            <?php else: ?>
                                No QR codes found for the selected filter.
                            <?php endif; ?>
                        </p>
                        <div class="d-flex justify-content-center gap-3 flex-wrap">
                            <a href="business-stores.php" class="btn btn-primary">
                                <i class="bi bi-shop me-1"></i>Browse Business Discounts
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- QR Code Grid -->
        <div class="row g-4">
            <?php foreach ($qr_purchases as $purchase): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card qr-code-card h-100 <?php 
                        echo $purchase['current_status'] === 'expired' ? 'border-danger' : 
                             ($purchase['current_status'] === 'redeemed' ? 'border-success' : 'border-primary'); 
                    ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-truncate me-2"><?php echo htmlspecialchars($purchase['item_name']); ?></h6>
                            <span class="badge <?php 
                                echo $purchase['current_status'] === 'active' ? 'status-badge-active' : 
                                     ($purchase['current_status'] === 'redeemed' ? 'status-badge-redeemed' : 'status-badge-expired'); 
                            ?> text-white">
                                <?php echo ucfirst($purchase['current_status']); ?>
                            </span>
                        </div>
                        
                        <div class="card-body">
                            <!-- Business Info -->
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-building text-primary me-2"></i>
                                <div>
                                    <strong><?php echo htmlspecialchars($purchase['business_name']); ?></strong>
                                    <?php if ($purchase['location_description']): ?>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars($purchase['location_description']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Discount Badge -->
                            <div class="text-center mb-3">
                                <span class="badge discount-badge text-white fs-6 px-3 py-2">
                                    <?php echo $purchase['discount_percentage']; ?>% OFF
                                </span>
                            </div>

                            <!-- QR Code Display -->
                            <div class="qr-code-display mb-3">
                                <?php if ($purchase['current_status'] === 'active'): ?>
                                    <img src="data:image/png;base64,<?php echo $purchase['qr_code_data']; ?>" 
                                         alt="Discount QR Code" 
                                         class="img-fluid qr-code-image"
                                         style="max-width: 120px; height: auto; cursor: pointer;"
                                         onclick="showQRModal(<?php echo $purchase['id']; ?>)">
                                    <div class="mt-2">
                                        <small class="text-muted">Tap to enlarge</small>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted py-4">
                                        <i class="bi bi-qr-code fs-1 opacity-50"></i>
                                        <div class="mt-2">
                                            <small><?php echo $purchase['current_status'] === 'redeemed' ? 'Already Redeemed' : 'Expired'; ?></small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Purchase Details -->
                            <div class="small text-muted">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Purchase Code:</span>
                                    <code class="text-primary"><?php echo $purchase['purchase_code']; ?></code>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Cost:</span>
                                    <span>
                                        <img src="../img/qrCoin.png" alt="QR Coin" style="width: 1rem; height: 1rem;" class="me-1">
                                        <?php echo number_format($purchase['qr_coins_spent']); ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Expires:</span>
                                    <span class="<?php echo $purchase['current_status'] === 'expired' ? 'text-danger' : ''; ?>">
                                        <?php echo date('M j, Y', strtotime($purchase['expires_at'])); ?>
                                    </span>
                                </div>
                                <?php if ($purchase['scan_count'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Scans:</span>
                                        <span><?php echo $purchase['scan_count']; ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($purchase['redeemed_at']): ?>
                                    <div class="d-flex justify-content-between">
                                        <span>Redeemed:</span>
                                        <span><?php echo date('M j, Y', strtotime($purchase['redeemed_at'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($purchase['current_status'] === 'active'): ?>
                            <div class="card-footer bg-light">
                                <small class="text-success">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Scan this QR code at the vending machine to apply your discount
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-5">
                <nav aria-label="QR Codes pagination">
                    <ul class="pagination">
                        <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=1">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php
                        $window = 2;
                        $start = max(1, $page - $window);
                        $end = min($total_pages, $page + $window);
                        
                        for ($i = $start; $i <= $end; $i++) {
                            $active = $page === $i ? 'active' : '';
                            echo '<li class="page-item ' . $active . '">';
                            echo '<a class="page-link" href="?filter=' . $filter . '&page=' . $i . '">' . $i . '</a>';
                            echo '</li>';
                        }
                        ?>
                        <li class="page-item <?php echo $page === $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?php echo $page === $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $total_pages; ?>">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- QR Code Modal -->
<div class="modal fade mobile-qr-modal" id="qrModal" tabindex="-1" aria-labelledby="qrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrModalLabel">
                    <i class="bi bi-qr-code me-2"></i>Discount QR Code
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrCodeContainer">
                    <!-- QR code will be loaded here -->
                </div>
                <div id="qrDetailsContainer" class="mt-3">
                    <!-- Purchase details will be loaded here -->
                </div>
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <small>Show this QR code to the vending machine scanner to automatically apply your discount</small>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
const purchaseData = <?php echo json_encode($qr_purchases); ?>;

function showQRModal(purchaseId) {
    const purchase = purchaseData.find(p => p.id == purchaseId);
    if (!purchase) return;
    
    // Update modal content
    document.getElementById('qrCodeContainer').innerHTML = `
        <img src="data:image/png;base64,${purchase.qr_code_data}" 
             alt="Discount QR Code" 
             class="qr-code-large img-fluid"
             style="max-width: 300px;">
    `;
    
    document.getElementById('qrDetailsContainer').innerHTML = `
        <div class="text-start">
            <h6>${purchase.item_name}</h6>
            <p class="text-muted mb-1">${purchase.business_name}</p>
            <div class="d-flex justify-content-between">
                <span><strong>${purchase.discount_percentage}% OFF</strong></span>
                <span class="text-muted">Code: ${purchase.purchase_code}</span>
            </div>
            <div class="text-muted">
                <small>Expires: ${new Date(purchase.expires_at).toLocaleDateString()}</small>
            </div>
        </div>
    `;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('qrModal'));
    modal.show();
}

// Update page title based on filter
document.addEventListener('DOMContentLoaded', function() {
    const filter = '<?php echo $filter; ?>';
    const titleMap = {
        'active': 'Active QR Codes',
        'all': 'All QR Codes', 
        'redeemed': 'Redeemed QR Codes',
        'expired': 'Expired QR Codes'
    };
    
    if (titleMap[filter]) {
        document.title = `${titleMap[filter]} - RevenueQR`;
    }
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 