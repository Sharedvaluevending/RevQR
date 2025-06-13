<?php
/**
 * Admin Horse Racing Control Center
 * Monitor and manage all horse racing activities
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

// Require admin role
require_role('admin');

// Handle race approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $race_id = intval($_POST['race_id'] ?? 0);
    
    if ($race_id && in_array($action, ['approve', 'reject', 'cancel'])) {
        try {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("
                    UPDATE business_races 
                    SET status = 'approved', admin_approved_by = ? 
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$_SESSION['user_id'], $race_id]);
                $message = "Race approved successfully!";
                $message_type = 'success';
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare("
                    UPDATE business_races 
                    SET status = 'cancelled', admin_approved_by = ? 
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$_SESSION['user_id'], $race_id]);
                $message = "Race rejected.";
                $message_type = 'warning';
            } elseif ($action === 'cancel') {
                $stmt = $pdo->prepare("
                    UPDATE business_races 
                    SET status = 'cancelled', admin_approved_by = ? 
                    WHERE id = ? AND status IN ('approved', 'active')
                ");
                $stmt->execute([$_SESSION['user_id'], $race_id]);
                $message = "Race cancelled.";
                $message_type = 'warning';
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get system overview statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_races,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_races,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_races,
        COUNT(*) as total_races,
        SUM(prize_pool_qr_coins) as total_prize_pool,
        SUM(total_qr_coins_bet) as total_coins_bet
    FROM business_races
");
$overview = $stmt->fetch();

// Get pending races for approval
$stmt = $pdo->prepare("
    SELECT br.*, b.name as business_name, COUNT(rh.id) as horse_count
    FROM business_races br
    JOIN businesses b ON br.business_id = b.id
    LEFT JOIN race_horses rh ON br.id = rh.race_id
    WHERE br.status = 'pending'
    GROUP BY br.id
    ORDER BY br.created_at ASC
");
$stmt->execute();
$pending_races = $stmt->fetchAll();

// Get active races
$stmt = $pdo->prepare("
    SELECT br.*, b.name as business_name, COUNT(rh.id) as horse_count,
           COUNT(rb.id) as total_bets
    FROM business_races br
    JOIN businesses b ON br.business_id = b.id
    LEFT JOIN race_horses rh ON br.id = rh.race_id
    LEFT JOIN race_bets rb ON br.id = rb.race_id
    WHERE br.status = 'active'
    GROUP BY br.id
    ORDER BY br.start_time ASC
");
$stmt->execute();
$active_races = $stmt->fetchAll();

// Get recent activity
$stmt = $pdo->prepare("
    SELECT 'bet' as activity_type, rb.bet_placed_at as activity_time, 
           u.username, br.race_name, rb.bet_amount_qr_coins as amount
    FROM race_bets rb
    JOIN users u ON rb.user_id = u.id
    JOIN business_races br ON rb.race_id = br.id
    WHERE rb.bet_placed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    
    UNION ALL
    
    SELECT 'race_created' as activity_type, br.created_at as activity_time,
           b.name as username, br.race_name, br.prize_pool_qr_coins as amount
    FROM business_races br
    JOIN businesses b ON br.business_id = b.id
    WHERE br.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    
    ORDER BY activity_time DESC
    LIMIT 20
");
$stmt->execute();
$recent_activity = $stmt->fetchAll();

require_once __DIR__ . '/../../core/includes/header.php';
?>

<style>
.admin-header {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    border-left: 4px solid #007bff;
    transition: all 0.3s ease;
    color: #ffffff;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.25);
}

.stat-card.pending { border-left-color: #ffc107; }
.stat-card.active { border-left-color: #dc3545; }
.stat-card.completed { border-left-color: #28a745; }
.stat-card.total { border-left-color: #6f42c1; }

.approval-card {
    background: rgba(255, 193, 7, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 193, 7, 0.3) !important;
    border-radius: 16px;
    color: #ffffff !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.active-race-card {
    background: rgba(220, 53, 69, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(220, 53, 69, 0.3) !important;
    border-radius: 16px;
    color: #ffffff !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.activity-item {
    border-left: 3px solid #007bff;
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    color: #ffffff !important;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-radius: 0 10px 10px 0;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.activity-bet { border-left-color: #28a745; }
.activity-race { border-left-color: #007bff; }

.danger-zone {
    background: #fff5f5;
    border: 2px solid #feb2b2;
    border-radius: 10px;
    padding: 1rem;
}
</style>

<div class="admin-header text-center">
    <div class="container">
        <img src="/horse-racing/assets/img/racetrophy.png" alt="Race Trophy" style="width: 80px; height: 80px; margin-bottom: 1rem;">
        <h1 class="display-4 mb-3">🏇 Horse Racing Command Center</h1>
        <p class="lead">Monitor and manage all racing activities across the platform</p>
    </div>
</div>

<div class="container">
    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- System Overview -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card pending">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="bi bi-clock-history text-warning" style="font-size: 2rem;"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-warning"><?php echo $overview['pending_races']; ?></h3>
                        <small class="text-muted">Pending Approval</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card active">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="bi bi-broadcast text-danger" style="font-size: 2rem;"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-danger"><?php echo $overview['active_races']; ?></h3>
                        <small class="text-muted">Live Races</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card completed">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-success"><?php echo $overview['completed_races']; ?></h3>
                        <small class="text-muted">Completed</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card total">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="bi bi-coin text-warning" style="font-size: 2rem;"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 text-warning"><?php echo number_format($overview['total_coins_bet']); ?></h3>
                        <small class="text-muted">QR Coins Bet</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Pending Approvals -->
        <div class="col-lg-6">
            <h4 class="mb-4" style="color: #fff;">⏳ Pending Race Approvals</h4>
            
            <?php if (empty($pending_races)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-check-circle-fill text-success display-4"></i>
                    <p class="text-muted mt-3">No races pending approval</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending_races as $race): ?>
                    <div class="approval-card p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($race['race_name']); ?></h6>
                                <small class="text-muted">
                                    by <?php echo htmlspecialchars($race['business_name']); ?>
                                </small>
                            </div>
                            <span class="badge bg-warning">Pending</span>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <small class="text-muted">Duration</small>
                                <div><?php echo ucfirst($race['race_type']); ?></div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Horses</small>
                                <div><?php echo $race['horse_count']; ?> items</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Prize Pool</small>
                                <div class="text-warning fw-bold">
                                    <?php echo number_format($race['prize_pool_qr_coins']); ?> coins
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="race_id" value="<?php echo $race['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="bi bi-check-lg"></i> Approve
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="race_id" value="<?php echo $race['id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-danger btn-sm" 
                                        onclick="return confirm('Are you sure you want to reject this race?')">
                                    <i class="bi bi-x-lg"></i> Reject
                                </button>
                            </form>
                            <a href="race-details.php?id=<?php echo $race['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-eye"></i> Review
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Active Races -->
        <div class="col-lg-6">
            <h4 class="mb-4" style="color: #fff;">🔴 Live Races</h4>
            
            <?php if (empty($active_races)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-pause-circle text-muted display-4"></i>
                    <p class="text-muted mt-3">No races currently running</p>
                </div>
            <?php else: ?>
                <?php foreach ($active_races as $race): ?>
                    <div class="active-race-card p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($race['race_name']); ?></h6>
                                <small class="text-muted">
                                    by <?php echo htmlspecialchars($race['business_name']); ?>
                                </small>
                            </div>
                            <span class="badge bg-danger">LIVE</span>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <small class="text-muted">Participants</small>
                                <div><?php echo $race['total_bets']; ?> bets</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Total Bet</small>
                                <div><?php echo number_format($race['total_qr_coins_bet']); ?> coins</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Ends</small>
                                <div><?php echo date('M j, g:i A', strtotime($race['end_time'])); ?></div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="../horse-racing/race-live.php?id=<?php echo $race['id']; ?>" 
                               class="btn btn-danger btn-sm">
                                <i class="bi bi-eye"></i> Monitor Live
                            </a>
                            <div class="danger-zone d-inline">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="race_id" value="<?php echo $race['id']; ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" 
                                            onclick="return confirm('EMERGENCY: Cancel this live race? This will refund all bets.')">
                                        <i class="bi bi-exclamation-triangle"></i> Emergency Stop
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row mt-4">
        <div class="col-12">
            <h4 class="mb-4" style="color: #fff;">📊 Recent Activity (24 Hours)</h4>
            
            <div class="row">
                <?php if (empty($recent_activity)): ?>
                    <div class="col-12 text-center py-4">
                        <p class="text-muted">No recent activity</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($recent_activity, 0, 10) as $activity): ?>
                        <div class="col-md-6 mb-2">
                            <div class="activity-item activity-<?php echo $activity['activity_type'] === 'bet' ? 'bet' : 'race'; ?>">
                                <?php if ($activity['activity_type'] === 'bet'): ?>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?php echo htmlspecialchars($activity['username']); ?></strong> 
                                            placed a bet on <em><?php echo htmlspecialchars($activity['race_name']); ?></em>
                                        </div>
                                        <div class="text-success fw-bold">
                                            <?php echo number_format($activity['amount']); ?> coins
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?php echo htmlspecialchars($activity['username']); ?></strong> 
                                            created race <em><?php echo htmlspecialchars($activity['race_name']); ?></em>
                                        </div>
                                        <div class="text-primary fw-bold">
                                            <?php echo number_format($activity['amount']); ?> prize pool
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted">
                                    <?php echo date('M j, g:i A', strtotime($activity['activity_time'])); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card" style="background: rgba(255, 255, 255, 0.12) !important; backdrop-filter: blur(20px) !important; border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #fff !important;">
                <div class="card-header" style="background: rgba(255, 255, 255, 0.1) !important; border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;">
                    <h5 class="mb-0">⚡ Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="system-settings.php" class="btn btn-outline-primary w-100 mb-2">
                                <i class="bi bi-gear"></i> Racing Settings
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="reports.php" class="btn btn-outline-info w-100 mb-2">
                                <i class="bi bi-file-earmark-text"></i> Generate Reports
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="jockey-management.php" class="btn btn-outline-success w-100 mb-2">
                                <i class="bi bi-person-badge"></i> Manage Jockeys
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="emergency-controls.php" class="btn btn-outline-danger w-100 mb-2">
                                <i class="bi bi-exclamation-triangle"></i> Emergency Controls
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh active races every 30 seconds
setInterval(function() {
    const activeRaces = document.querySelectorAll('.active-race-card');
    if (activeRaces.length > 0) {
        // Refresh only the active races section
        location.reload();
    }
}, 30000);
</script>

<?php require_once __DIR__ . '/../../core/includes/footer.php'; ?> 