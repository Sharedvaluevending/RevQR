<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require user role
require_role('user');

// Get filter parameters
$filter = $_GET['filter'] ?? 'all'; // all, qr_store, business_store, active, expired
$sort = $_GET['sort'] ?? 'newest'; // newest, oldest, amount_high, amount_low
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Note: Sorting is now handled in the UNION query with ORDER BY created_at DESC
// Filtering is handled in the UNION query conditions below

// Build filter conditions for UNION query
$qr_store_conditions = ["uqsp.user_id = ?"];
$business_store_conditions = ["bp.user_id = ?"];

if ($filter === 'qr_store') {
    // Only include QR store purchases
    $business_store_conditions[] = "1 = 0"; // Exclude business store
} elseif ($filter === 'business_store') {
    // Only include business store purchases  
    $qr_store_conditions[] = "1 = 0"; // Exclude QR store
} elseif ($filter === 'active') {
    $qr_store_conditions[] = "(uqsp.expires_at IS NULL OR uqsp.expires_at > NOW())";
    $qr_store_conditions[] = "uqsp.status = 'active'";
    $business_store_conditions[] = "bp.status = 'pending'";
    $business_store_conditions[] = "(bp.expires_at IS NULL OR bp.expires_at > NOW())";
} elseif ($filter === 'expired') {
    $qr_store_conditions[] = "(uqsp.expires_at IS NOT NULL AND uqsp.expires_at <= NOW()) OR uqsp.status = 'used'";
    $business_store_conditions[] = "(bp.expires_at IS NOT NULL AND bp.expires_at <= NOW()) OR bp.status IN ('redeemed', 'expired')";
}

$qr_where = implode(' AND ', $qr_store_conditions);
$business_where = implode(' AND ', $business_store_conditions);

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) FROM (
        SELECT uqsp.id
        FROM user_qr_store_purchases uqsp
        LEFT JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
        WHERE $qr_where
        
        UNION ALL
        
        SELECT bp.id
        FROM business_purchases bp
        LEFT JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
        LEFT JOIN businesses b ON bp.business_id = b.id
        WHERE $business_where
    ) combined_purchases
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$total_items = $stmt->fetchColumn();
$total_pages = ceil($total_items / $per_page);

// Get purchases with details - Fixed to use UNION of both purchase types
$sql = "
    SELECT * FROM (
        SELECT 
            uqsp.id,
            uqsp.user_id,
            uqsp.qr_coins_spent,
            uqsp.quantity,
            uqsp.status,
            uqsp.expires_at,
            uqsp.used_at,
            uqsp.created_at,
            uqsp.updated_at,
            qsi.id as qr_store_item_id,
            NULL as store_item_id,
            qsi.item_name as qr_item_name,
            qsi.item_type as qr_item_type,
            qsi.rarity as qr_item_rarity,
            NULL as business_item_name,
            NULL as discount_percentage,
            NULL as business_name,
            NULL as purchase_code,
            NULL as qr_code_data,
            'qr_store' as purchase_type,
            CASE 
                WHEN uqsp.expires_at IS NOT NULL AND uqsp.expires_at <= NOW() THEN 'expired'
                WHEN uqsp.status = 'used' THEN 'used'
                WHEN uqsp.status = 'active' THEN 'active'
                ELSE 'unknown'
            END as current_status
        FROM user_qr_store_purchases uqsp
        LEFT JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
        WHERE $qr_where
        
        UNION ALL
        
        SELECT 
            bp.id,
            bp.user_id,
            bp.qr_coins_spent,
            1 as quantity,
            bp.status,
            bp.expires_at,
            bp.redeemed_at as used_at,
            bp.created_at,
            bp.updated_at,
            NULL as qr_store_item_id,
            bp.business_store_item_id as store_item_id,
            NULL as qr_item_name,
            NULL as qr_item_type,
            NULL as qr_item_rarity,
            bsi.item_name as business_item_name,
            bp.discount_percentage,
            b.name as business_name,
            bp.purchase_code,
            bp.qr_code_data,
            'business_store' as purchase_type,
            CASE 
                WHEN bp.expires_at IS NOT NULL AND bp.expires_at <= NOW() THEN 'expired'
                WHEN bp.status = 'redeemed' THEN 'used'
                WHEN bp.status = 'pending' THEN 'active'
                ELSE 'unknown'
            END as current_status
        FROM business_purchases bp
        LEFT JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
        LEFT JOIN businesses b ON bp.business_id = b.id
        WHERE bp.user_id = ?
    ) combined_purchases
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$purchases = $stmt->fetchAll();

// Get user's current QR coin balance
$user_balance = QRCoinManager::getBalance($_SESSION['user_id']);

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0">
                        <i class="bi bi-bag-check text-primary me-2"></i>My Purchases
                    </h1>
                    <p class="text-muted mb-0">Your QR store and business discount purchase history</p>
                </div>
                <div class="text-end">
                    <div class="d-flex align-items-center">
                        <img src="../img/qrCoin.png" alt="QR Coin" style="width: 2rem; height: 2rem;" class="me-2">
                        <h4 class="text-warning mb-0"><?php echo number_format($user_balance); ?></h4>
                    </div>
                    <small class="text-muted">Current Balance</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Controls -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="btn-group me-3" role="group" aria-label="Filter purchases">
                <a href="?filter=all&sort=<?php echo $sort; ?>" 
                   class="btn btn-outline-secondary <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All Purchases
                </a>
                <a href="?filter=qr_store&sort=<?php echo $sort; ?>" 
                   class="btn btn-outline-secondary <?php echo $filter === 'qr_store' ? 'active' : ''; ?>">
                    QR Store
                </a>
                <a href="?filter=business_store&sort=<?php echo $sort; ?>" 
                   class="btn btn-outline-secondary <?php echo $filter === 'business_store' ? 'active' : ''; ?>">
                    Business Discounts
                </a>
            </div>
            <div class="btn-group" role="group" aria-label="Filter by status">
                <a href="?filter=active&sort=<?php echo $sort; ?>" 
                   class="btn btn-outline-success <?php echo $filter === 'active' ? 'active' : ''; ?>">
                    Active
                </a>
                <a href="?filter=expired&sort=<?php echo $sort; ?>" 
                   class="btn btn-outline-danger <?php echo $filter === 'expired' ? 'active' : ''; ?>">
                    Used/Expired
                </a>
            </div>
        </div>
        <div class="col-md-6 text-end">
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-sort-down me-1"></i>Sort: <?php 
                        echo match($sort) {
                            'oldest' => 'Oldest First',
                            'amount_high' => 'Highest Cost',
                            'amount_low' => 'Lowest Cost',
                            default => 'Newest First'
                        };
                    ?>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&sort=newest">Newest First</a></li>
                    <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&sort=oldest">Oldest First</a></li>
                    <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&sort=amount_high">Highest Cost</a></li>
                    <li><a class="dropdown-item" href="?filter=<?php echo $filter; ?>&sort=amount_low">Lowest Cost</a></li>
                </ul>
            </div>
        </div>
    </div>

    <?php if (empty($purchases)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-bag display-1 text-muted mb-3"></i>
                        <h3 class="text-muted mb-3">No Purchases Found</h3>
                        <p class="text-muted mb-4">
                            <?php if ($filter === 'all'): ?>
                                You haven't made any purchases yet. Start earning QR coins and explore our stores!
                            <?php else: ?>
                                No purchases found for the selected filter. Try adjusting your filters.
                            <?php endif; ?>
                        </p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="qr-store.php" class="btn btn-primary">
                                <i class="bi bi-gem me-1"></i>QR Store
                            </a>
                            <a href="business-stores.php" class="btn btn-warning">
                                <i class="bi bi-building me-1"></i>Business Discounts
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
        <div class="row g-3">
            <?php foreach ($purchases as $purchase): ?>
                <div class="col-12">
                    <div class="card <?php 
                        echo $purchase['current_status'] === 'expired' ? 'border-danger' : 
                             ($purchase['current_status'] === 'used' ? 'border-warning' : 'border-success'); 
                    ?>">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">
                                            <?php if ($purchase['qr_store_item_id']): ?>
                                                <i class="bi bi-gem fs-2 text-<?php 
                                                    echo $purchase['qr_item_rarity'] === 'legendary' ? 'warning' : 
                                                         ($purchase['qr_item_rarity'] === 'epic' ? 'info' : 
                                                          ($purchase['qr_item_rarity'] === 'rare' ? 'success' : 'secondary')); 
                                                ?>"></i>
                                            <?php else: ?>
                                                <i class="bi bi-building fs-2 text-primary"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h5 class="mb-1">
                                                <?php echo htmlspecialchars($purchase['qr_item_name'] ?? $purchase['business_item_name']); ?>
                                            </h5>
                                            <div class="mb-2">
                                                <?php if ($purchase['qr_store_item_id']): ?>
                                                    <span class="badge bg-info">QR Store</span>
                                                    <span class="badge bg-<?php 
                                                        echo $purchase['qr_item_rarity'] === 'legendary' ? 'warning' : 
                                                             ($purchase['qr_item_rarity'] === 'epic' ? 'info' : 
                                                              ($purchase['qr_item_rarity'] === 'rare' ? 'success' : 'secondary')); 
                                                    ?>">
                                                        <?php echo ucfirst($purchase['qr_item_rarity']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Business Discount</span>
                                                    <span class="badge bg-success"><?php echo $purchase['discount_percentage']; ?>% OFF</span>
                                                    <?php if ($purchase['business_name']): ?>
                                                        <small class="text-muted">from <?php echo htmlspecialchars($purchase['business_name']); ?></small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                Purchased on <?php echo date('M j, Y g:i A', strtotime($purchase['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <div class="d-flex align-items-center justify-content-center mb-1">
                                            <img src="../img/qrCoin.png" alt="QR Coin" style="width: 1.5rem; height: 1.5rem;" class="me-1">
                                            <strong class="text-warning"><?php echo number_format($purchase['qr_coins_spent']); ?></strong>
                                        </div>
                                        <small class="text-muted">Coins Spent</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <?php if ($purchase['purchase_code']): ?>
                                            <div class="mb-2">
                                                <span class="badge bg-<?php 
                                                    echo $purchase['current_status'] === 'active' ? 'success' : 
                                                         ($purchase['current_status'] === 'used' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($purchase['current_status']); ?>
                                                </span>
                                            </div>
                                            <div class="font-monospace fw-bold text-primary">
                                                <?php echo htmlspecialchars($purchase['purchase_code']); ?>
                                            </div>
                                            <small class="text-muted">Redemption Code</small>
                                            <?php if ($purchase['expires_at']): ?>
                                                <div class="mt-1">
                                                    <small class="text-<?php echo $purchase['current_status'] === 'expired' ? 'danger' : 'muted'; ?>">
                                                        Expires: <?php echo date('M j, Y', strtotime($purchase['expires_at'])); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No Code Required</span>
                                            <div><small class="text-muted">Applied automatically</small></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($purchase['purchase_code'] && $purchase['current_status'] === 'active'): ?>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <?php if ($purchase['store_item_id'] && !empty($purchase['qr_code_data'])): ?>
                                            <div class="alert alert-success d-flex align-items-center">
                                                <i class="bi bi-qr-code me-2"></i>
                                                <div class="flex-grow-1">
                                                    <strong>QR Code Available!</strong> 
                                                    Scan at Nayax vending machines for instant discount application.
                                                </div>
                                                <a href="my-discount-qr-codes.php" class="btn btn-sm btn-success ms-2">
                                                    <i class="bi bi-qr-code"></i> View QR
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info d-flex align-items-center">
                                                <i class="bi bi-info-circle me-2"></i>
                                                <div>
                                                    <strong>How to use:</strong> 
                                                    <?php if ($purchase['store_item_id']): ?>
                                                        Show this code at the vending machine to get your discount.
                                                    <?php else: ?>
                                                        This item has been applied to your account automatically.
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav aria-label="Purchase history pagination">
                    <ul class="pagination">
                        <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&sort=<?php echo $sort; ?>&page=1">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php
                        $window = 2;
                        $start = max(1, $page - $window);
                        $end = min($total_pages, $page + $window);
                        
                        if ($start > 1) {
                            echo '<li class="page-item"><span class="page-link">...</span></li>';
                        }
                        
                        for ($i = $start; $i <= $end; $i++) {
                            $active = $page === $i ? 'active' : '';
                            echo '<li class="page-item ' . $active . '">';
                            echo '<a class="page-link" href="?filter=' . $filter . '&sort=' . $sort . '&page=' . $i . '">' . $i . '</a>';
                            echo '</li>';
                        }
                        
                        if ($end < $total_pages) {
                            echo '<li class="page-item"><span class="page-link">...</span></li>';
                        }
                        ?>
                        <li class="page-item <?php echo $page === $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <li class="page-item <?php echo $page === $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&sort=<?php echo $sort; ?>&page=<?php echo $total_pages; ?>">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <div class="text-center text-muted mt-2">
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_items); ?> 
                of <?php echo number_format($total_items); ?> purchases
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Purchase Summary Stats -->
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="bi bi-graph-up me-2"></i>Your Purchase Summary
                    </h6>
                    <div class="row g-3">
                        <?php
                        // Get summary stats - Get both QR store and business purchases
                        $qr_store_sql = "
                            SELECT 
                                COUNT(*) as qr_store_purchases,
                                COALESCE(SUM(qr_coins_spent), 0) as qr_store_spent,
                                COUNT(CASE WHEN status = 'active' AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 END) as qr_store_active
                            FROM user_qr_store_purchases 
                            WHERE user_id = ?
                        ";
                        $stmt = $pdo->prepare($qr_store_sql);
                        $stmt->execute([$_SESSION['user_id']]);
                        $qr_summary = $stmt->fetch();
                        
                        $business_sql = "
                            SELECT 
                                COUNT(*) as business_purchases,
                                COALESCE(SUM(qr_coins_spent), 0) as business_spent,
                                COUNT(CASE WHEN status = 'pending' AND expires_at > NOW() THEN 1 END) as business_active
                            FROM business_purchases 
                            WHERE user_id = ?
                        ";
                        $stmt = $pdo->prepare($business_sql);
                        $stmt->execute([$_SESSION['user_id']]);
                        $business_summary = $stmt->fetch();
                        
                        // Combine the results
                        $summary = [
                            'total_purchases' => ($qr_summary['qr_store_purchases'] ?? 0) + ($business_summary['business_purchases'] ?? 0),
                            'total_spent' => ($qr_summary['qr_store_spent'] ?? 0) + ($business_summary['business_spent'] ?? 0),
                            'qr_store_purchases' => $qr_summary['qr_store_purchases'] ?? 0,
                            'business_purchases' => $business_summary['business_purchases'] ?? 0,
                            'active_purchases' => ($qr_summary['qr_store_active'] ?? 0) + ($business_summary['business_active'] ?? 0)
                        ];
                        ?>
                        <div class="col-md-2-4">
                            <div class="text-center">
                                <h4 class="text-primary"><?php echo number_format($summary['total_purchases'] ?? 0); ?></h4>
                                <small class="text-muted">Total Purchases</small>
                            </div>
                        </div>
                        <div class="col-md-2-4">
                            <div class="text-center">
                                <div class="d-flex align-items-center justify-content-center">
                                    <img src="../img/qrCoin.png" alt="QR Coin" style="width: 1.2rem; height: 1.2rem;" class="me-1">
                                    <h4 class="text-warning mb-0"><?php echo number_format($summary['total_spent'] ?? 0); ?></h4>
                                </div>
                                <small class="text-muted">Total Spent</small>
                            </div>
                        </div>
                        <div class="col-md-2-4">
                            <div class="text-center">
                                <h4 class="text-info"><?php echo number_format($summary['qr_store_purchases'] ?? 0); ?></h4>
                                <small class="text-muted">QR Store Items</small>
                            </div>
                        </div>
                        <div class="col-md-2-4">
                            <div class="text-center">
                                <h4 class="text-warning"><?php echo number_format($summary['business_purchases'] ?? 0); ?></h4>
                                <small class="text-muted">Business Discounts</small>
                            </div>
                        </div>
                        <div class="col-md-2-4">
                            <div class="text-center">
                                <h4 class="text-success"><?php echo number_format($summary['active_purchases'] ?? 0); ?></h4>
                                <small class="text-muted">Active Items</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?>