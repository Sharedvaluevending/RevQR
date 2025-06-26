<?php
// Get current casino settings
$stmt = $pdo->prepare("SELECT * FROM business_casino_settings WHERE business_id = ?");
$stmt->execute([$_SESSION['business_id']]);
$casino_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Get CORRECT revenue stats - business earns from house edge, not player winnings
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_plays,
        COALESCE(SUM(bet_amount), 0) as total_bets,
        COALESCE(SUM(win_amount), 0) as total_wins,
        COALESCE(SUM(bet_amount) - SUM(win_amount), 0) as house_profit
    FROM casino_plays 
    WHERE business_id = ? AND played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute([$_SESSION['business_id']]);
$casino_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate actual business revenue share (typically 10% of house profit)
$house_edge = $casino_settings['house_edge'] ?? 0.05; // Default 5%
$business_revenue_share = 0.10; // 10% of house profits go to business
$actual_business_revenue = max(0, $casino_stats['house_profit'] * $business_revenue_share);

$casino_enabled = $casino_settings['casino_enabled'] ?? false;
$has_promotion = false; // Disable promotion section since the column doesn't exist in current schema
?>

<div class="card dashboard-card h-100" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border: none;">
    <div class="card-header bg-transparent border-0">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="text-white mb-0">
                <i class="bi bi-dice-5-fill me-2"></i>🎰 Casino Participation
            </h5>
            <div class="d-flex align-items-center">
                <?php if ($casino_enabled): ?>
                    <span class="badge bg-success me-2">
                        <i class="bi bi-check-circle me-1"></i>Active
                    </span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark me-2">
                        <i class="bi bi-pause-circle me-1"></i>Disabled
                    </span>
                <?php endif; ?>
                <span class="text-warning fw-bold"><?php echo number_format($house_edge * 100, 1); ?>% House Edge</span>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- Status Overview -->
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center p-3 bg-white bg-opacity-15 rounded">
                    <div class="text-white">
                        <h6 class="mb-1">
                            <i class="bi bi-info-circle me-2"></i>How It Works
                        </h6>
                        <p class="mb-0 small">
                            Users play casino games at your location. You earn <strong>10% of house profits</strong> 
                            (when players lose more than they win).
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Business Revenue -->
            <div class="col-md-6">
                <div class="d-flex align-items-center p-3 bg-white bg-opacity-10 rounded">
                    <div class="flex-shrink-0">
                        <i class="bi bi-coin display-6 text-warning"></i>
                    </div>
                    <div class="ms-3 text-white">
                        <h6 class="mb-1">Your Revenue (30d)</h6>
                        <h4 class="mb-0"><?php echo number_format($actual_business_revenue); ?></h4>
                        <small class="opacity-75">QR Coins earned</small>
                    </div>
                </div>
            </div>
            
            <!-- Play Stats -->
            <div class="col-md-6">
                <div class="d-flex align-items-center p-3 bg-white bg-opacity-10 rounded">
                    <div class="flex-shrink-0">
                        <i class="bi bi-people display-6 text-info"></i>
                    </div>
                    <div class="ms-3 text-white">
                        <h6 class="mb-1">Casino Plays</h6>
                        <h4 class="mb-0"><?php echo number_format($casino_stats['total_plays'] ?? 0); ?></h4>
                        <small class="opacity-75">Last 30 days</small>
                    </div>
                </div>
            </div>
            
            <!-- House Performance -->
            <div class="col-12">
                <div class="row g-2">
                    <div class="col-4">
                        <div class="text-center p-2 bg-white bg-opacity-10 rounded">
                            <div class="text-warning fw-bold"><?php echo number_format($casino_stats['total_bets']); ?></div>
                            <small class="text-white-50">Total Bets</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-2 bg-white bg-opacity-10 rounded">
                            <div class="text-info fw-bold"><?php echo number_format($casino_stats['total_wins']); ?></div>
                            <small class="text-white-50">Player Wins</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-2 bg-white bg-opacity-10 rounded">
                            <div class="<?php echo $casino_stats['house_profit'] >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                                <?php echo number_format($casino_stats['house_profit']); ?>
                            </div>
                            <small class="text-white-50">House Profit</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Warning if house is losing -->
            <?php if ($casino_stats['house_profit'] < 0): ?>
            <div class="col-12">
                <div class="alert alert-warning mb-0" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Notice:</strong> Players are currently winning more than they're betting. 
                    Consider adjusting casino settings or house edge.
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-footer bg-transparent border-0">
        <div class="d-flex gap-2 justify-content-between">
            <a href="settings.php#casino" class="btn btn-light btn-sm flex-fill">
                <i class="bi bi-gear me-1"></i>Settings
            </a>
            <?php if ($casino_enabled): ?>
                <a href="../casino/index.php" class="btn btn-warning btn-sm flex-fill" target="_blank">
                    <i class="bi bi-play-circle me-1"></i>Preview Casino
                </a>
            <?php else: ?>
                <button class="btn btn-success btn-sm flex-fill" onclick="enableCasino()">
                    <i class="bi bi-power me-1"></i>Enable Casino
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function enableCasino() {
    if (confirm('Enable casino participation? You\'ll earn 10% of house profits when players lose more than they win.')) {
        window.location.href = 'settings.php#casino';
    }
}
</script> 