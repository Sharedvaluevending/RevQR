<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Require business role
require_role('business');

$qr_code = null;
$list = null;
$items = [];
$winners = [];

// Get business ID
$stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    // No business found for this user
    header('Location: dashboard.php');
    exit();
}

$business_id = $business['id'];

if (isset($_GET['code'])) {
    // Get QR code details - fix the relationship
    $stmt = $pdo->prepare("
        SELECT qr.*, 
               COALESCE(qr.business_id, (SELECT business_id FROM campaigns WHERE id = qr.campaign_id)) as qr_business_id
        FROM qr_codes qr
        WHERE qr.code = ? AND qr.status = 'active'
    ");
    $stmt->execute([$_GET['code']]);
    $qr_code = $stmt->fetch();
    
    if ($qr_code && $qr_code['qr_business_id'] == $business_id) {
        // Get associated voting list based on campaign_id if it exists
        if ($qr_code['campaign_id']) {
            $stmt = $pdo->prepare("
                SELECT vl.* 
                FROM campaigns c
                JOIN voting_lists vl ON c.list_id = vl.id
                WHERE c.id = ? AND c.business_id = ?
            ");
            $stmt->execute([$qr_code['campaign_id'], $business_id]);
            $list = $stmt->fetch();
        } else {
            // If no campaign, try to find voting list by business_id
            $stmt = $pdo->prepare("
                SELECT * FROM voting_lists 
                WHERE business_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$business_id]);
            $list = $stmt->fetch();
        }
        
        if ($list) {
            // Get list items with vote counts
            $stmt = $pdo->prepare("
                SELECT i.*, 
                       (SELECT COUNT(*) FROM votes v WHERE v.item_id = i.id AND v.vote_type = 'in') as vote_in_count,
                       (SELECT COUNT(*) FROM votes v WHERE v.item_id = i.id AND v.vote_type = 'out') as vote_out_count
                FROM voting_list_items i
                WHERE i.list_id = ? AND i.status = 'active'
                ORDER BY vote_in_count DESC, vote_out_count ASC
            ");
            $stmt->execute([$list['id']]);
            $items = $stmt->fetchAll();
            
            // Get winners
            $stmt = $pdo->prepare("
                SELECT w.*, i.item_name
                FROM winners w
                JOIN voting_list_items i ON w.item_id = i.id
                WHERE w.list_id = ?
                ORDER BY w.week_start DESC, w.vote_type
            ");
            $stmt->execute([$list['id']]);
            $winners = $stmt->fetchAll();
        }
    } else {
        // QR code not found or doesn't belong to this business
        $qr_code = null;
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-0">QR Code Results</h1>
        <p class="text-muted">View voting results and winners for this QR code</p>
    </div>
</div>

<?php if (!$qr_code): ?>
    <div class="alert alert-danger">
        <h5><i class="bi bi-exclamation-triangle me-2"></i>QR Code Not Found</h5>
        <p class="mb-2">The QR code was not found or you don't have permission to view it.</p>
        <p class="mb-0"><strong>Possible reasons:</strong></p>
        <ul class="mb-2">
            <li>The QR code doesn't exist</li>
            <li>The QR code is inactive or deleted</li>
            <li>The QR code belongs to a different business</li>
            <li>No <code>code</code> parameter was provided in the URL</li>
        </ul>
        <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    </div>
<?php else: ?>
    <div class="row">
        <!-- QR Code Info -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">QR Code Details</h5>
                </div>
                <div class="card-body">
                    <?php 
                    $qr_image_path = "/uploads/qr/" . htmlspecialchars($qr_code['code']) . ".png";
                    $qr_fallback_path = "/assets/img/qr/" . htmlspecialchars($qr_code['code']) . ".png";
                    ?>
                    <img src="<?php echo $qr_image_path; ?>" 
                         alt="QR Code" 
                         class="img-fluid mb-3"
                         onerror="this.src='<?php echo $qr_fallback_path; ?>'">
                    
                    <p><strong>Code:</strong> <?php echo htmlspecialchars($qr_code['code']); ?></p>
                    <p><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $qr_code['qr_type'])); ?></p>
                    <?php if ($qr_code['machine_name']): ?>
                        <p><strong>Machine:</strong> <?php echo htmlspecialchars($qr_code['machine_name']); ?></p>
                    <?php endif; ?>
                    <?php if ($qr_code['machine_location']): ?>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($qr_code['machine_location']); ?></p>
                    <?php endif; ?>
                    <p><strong>Status:</strong> <span class="badge bg-success"><?php echo ucfirst($qr_code['status']); ?></span></p>
                    <p><strong>Created:</strong> <?php echo date('M d, Y', strtotime($qr_code['created_at'])); ?></p>
                    
                    <?php if ($list): ?>
                        <p><strong>Associated List:</strong> <?php echo htmlspecialchars($list['name']); ?></p>
                    <?php else: ?>
                        <p><strong>Associated List:</strong> <span class="text-muted">None found</span></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Voting Results -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Voting Results</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($items)): ?>
                        <p class="text-muted">No voting items found for this QR code.</p>
                        <?php if (!$list): ?>
                            <div class="alert alert-info">
                                <strong>No Voting List Associated:</strong> This QR code doesn't have an associated voting list or campaign.
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Votes In</th>
                                        <th>Votes Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['item_category'] ?? 'Uncategorized'); ?></td>
                                            <td>$<?php echo number_format($item['retail_price'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?php echo $item['vote_in_count']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    <?php echo $item['vote_out_count']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $total_votes = $item['vote_in_count'] + $item['vote_out_count'];
                                                if ($total_votes > 0) {
                                                    $in_percentage = ($item['vote_in_count'] / $total_votes) * 100;
                                                    if ($in_percentage >= 70) {
                                                        echo '<span class="badge bg-success">Winner</span>';
                                                    } elseif ($in_percentage <= 30) {
                                                        echo '<span class="badge bg-danger">Loser</span>';
                                                    } else {
                                                        echo '<span class="badge bg-warning">Pending</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-secondary">No Votes</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Winners History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Winners History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($winners)): ?>
                        <p class="text-muted">No winners recorded yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Week</th>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Votes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($winners as $winner): ?>
                                        <tr>
                                            <td><?php echo date('M d', strtotime($winner['week_start'])) . ' - ' . date('M d, Y', strtotime($winner['week_end'])); ?></td>
                                            <td><?php echo htmlspecialchars($winner['item_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $winner['vote_type'] === 'in' ? 'success' : 'danger'; ?>">
                                                    <?php echo strtoupper($winner['vote_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $winner['votes_count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 