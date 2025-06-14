<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Get business ID from session
$business_id = $_SESSION['business_id'] ?? null;

if (!$business_id) {
    header('Location: ' . APP_URL . '/business/dashboard.php');
    exit;
}

// Get machines for this business
$stmt = $pdo->prepare("
    SELECT 
        m.id,
        m.name as machine_name,
        COUNT(v.id) as total_votes
    FROM machines m
    LEFT JOIN votes v ON m.id = v.machine_id
    WHERE m.business_id = ?
    GROUP BY m.id, m.name
    ORDER BY total_votes DESC
");
$stmt->execute([$business_id]);
$machines = $stmt->fetchAll();

// Get recent winners (top voted items from last 7 days)
$stmt = $pdo->prepare("
    SELECT 
        i.name as item_name,
        i.type as item_category,
        COUNT(*) as vote_count,
        v.vote_type,
        m.name as machine_name,
        DATE(MAX(v.created_at)) as vote_date
    FROM votes v
    JOIN items i ON v.item_id = i.id
    JOIN machines m ON v.machine_id = m.id
    WHERE m.business_id = ? 
    AND v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY i.name, i.type, v.vote_type, m.name
    ORDER BY vote_count DESC, MAX(v.created_at) DESC
    LIMIT 20
");
$stmt->execute([$business_id]);
$recent_winners = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0">Vote Winners</h1>
                <p class="text-muted">Top performing items across all your machines</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Machine Overview -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Machine Performance Overview</h5>
            </div>
            <div class="card-body">
                <?php if (empty($machines)): ?>
                    <p class="text-muted">No machines found. <a href="manage-machine.php">Add your first machine</a></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th>Total Votes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($machines as $machine): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                                        <td>
                                            <strong><?php echo $machine['total_votes']; ?></strong>
                                        </td>
                                        <td>
                                            <a href="view-votes.php?machine_id=<?php echo $machine['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye me-1"></i>View Details
                                            </a>
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

<!-- Recent Winners -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Winners (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_winners)): ?>
                    <p class="text-muted">No votes recorded in the last 7 days.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($recent_winners as $winner): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($winner['item_name']); ?></h6>
                                                <p class="text-muted small mb-1">
                                                    <?php echo $winner['item_category'] ? htmlspecialchars($winner['item_category']) : 'No category'; ?>
                                                </p>
                                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($winner['machine_name']); ?></p>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?php echo $winner['vote_type'] === 'vote_in' ? 'success' : 'danger'; ?>">
                                                    <?php echo $winner['vote_count']; ?> 
                                                    <?php echo $winner['vote_type'] === 'vote_in' ? 'IN' : 'OUT'; ?>
                                                </span>
                                                <p class="text-muted small mb-0 mt-1">
                                                    <?php echo date('M d', strtotime($winner['vote_date'])); ?>
                                                </p>
                                            </div>
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
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 