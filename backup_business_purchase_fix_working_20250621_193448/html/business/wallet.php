<?php
/**
 * Business Wallet - QR Coin Balance and Transaction Management
 * Shows business QR coin balance, transaction history, and wallet operations
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

$business_id = $_SESSION['business_id'] ?? null;
$user_id = $_SESSION['user_id'];

// Fetch business details
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

// Get wallet information
$stmt = $pdo->prepare("
    SELECT * FROM business_wallets 
    WHERE business_id = ?
");
$stmt->execute([$business_id]);
$wallet = $stmt->fetch();

// Get recent transactions
$stmt = $pdo->prepare("
    SELECT * FROM business_qr_transactions 
    WHERE business_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute([$business_id]);
$transactions = $stmt->fetchAll();

// Get revenue sources summary
$stmt = $pdo->prepare("
    SELECT 
        source_type,
        SUM(qr_coins_earned) as total_earned,
        COUNT(*) as periods,
        MAX(date_period) as last_earning_date
    FROM business_revenue_sources 
    WHERE business_id = ? 
    GROUP BY source_type
    ORDER BY total_earned DESC
");
$stmt->execute([$business_id]);
$revenue_sources = $stmt->fetchAll();

// Get monthly earnings chart data (last 12 months)
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(date_period, '%Y-%m') as month,
        SUM(qr_coins_earned) as earnings
    FROM business_revenue_sources 
    WHERE business_id = ? 
    AND date_period >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date_period, '%Y-%m')
    ORDER BY month DESC
");
$stmt->execute([$business_id]);
$monthly_earnings = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Business Wallet Styles */
.wallet-overview {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.wallet-balance {
    font-size: 3rem;
    font-weight: 700;
    color: #ffd700 !important;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.wallet-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    transition: all 0.3s ease !important;
}

.wallet-card:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
}

.transaction-item {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    border-left: 4px solid;
    transition: all 0.2s ease;
}

.transaction-item.earning {
    border-left-color: #28a745;
    background: rgba(40, 167, 69, 0.1);
}

.transaction-item.spending {
    border-left-color: #dc3545;
    background: rgba(220, 53, 69, 0.1);
}

.transaction-item.adjustment {
    border-left-color: #ffc107;
    background: rgba(255, 193, 7, 0.1);
}

.transaction-item:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(5px);
}

.revenue-source-item {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.badge-earning {
    background: linear-gradient(45deg, #28a745, #20c997);
    color: white;
}

.badge-spending {
    background: linear-gradient(45deg, #dc3545, #e74c3c);
    color: white;
}

.text-white-custom {
    color: rgba(255, 255, 255, 0.9) !important;
}

.text-muted-custom {
    color: rgba(255, 255, 255, 0.7) !important;
}

.btn-wallet {
    background: linear-gradient(45deg, #ffd700, #ffed4e);
    color: #000;
    border: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-wallet:hover {
    background: linear-gradient(45deg, #ffed4e, #ffd700);
    color: #000;
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
}

.chart-container {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 1rem;
    height: 300px;
}
</style>

<div class="container py-4">
    <!-- Wallet Overview -->
    <div class="wallet-overview">
        <div class="row align-items-center">
            <div class="col-auto">
                <i class="bi bi-wallet2 display-1 text-warning"></i>
            </div>
            <div class="col">
                <h1 class="text-white-custom mb-2">Business Wallet</h1>
                <p class="text-muted-custom mb-0">Track your QR coin earnings, balance, and transaction history</p>
            </div>
            <div class="col-auto text-end">
                <div class="wallet-balance"><?php echo number_format($wallet['qr_coin_balance'] ?? 0); ?></div>
                <div class="text-muted-custom">QR Coins</div>
                <small class="text-warning">≈ $<?php echo number_format(($wallet['qr_coin_balance'] ?? 0) * 0.001, 2); ?> USD</small>
            </div>
        </div>
    </div>

    <!-- Wallet Stats Grid -->
    <div class="row mb-4">
        <!-- Current Balance -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card wallet-card h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <i class="bi bi-coin display-4 text-warning me-3"></i>
                        <div>
                            <h3 class="text-white-custom mb-0"><?php echo number_format($wallet['qr_coin_balance'] ?? 0); ?></h3>
                            <small class="text-muted-custom">Current Balance</small>
                        </div>
                    </div>
                    <div class="progress mb-2" style="height: 8px;">
                        <?php 
                        $balance_percentage = min(100, (($wallet['qr_coin_balance'] ?? 0) / 10000) * 100); 
                        ?>
                        <div class="progress-bar bg-warning" style="width: <?php echo $balance_percentage; ?>%"></div>
                    </div>
                    <small class="text-muted-custom">Target: 10,000 QR Coins</small>
                </div>
            </div>
        </div>

        <!-- Total Earned -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card wallet-card h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <i class="bi bi-graph-up display-4 text-success me-3"></i>
                        <div>
                            <h3 class="text-white-custom mb-0"><?php echo number_format($wallet['total_earned_all_time'] ?? 0); ?></h3>
                            <small class="text-muted-custom">Total Earned</small>
                        </div>
                    </div>
                    <div class="text-success">
                        <i class="bi bi-arrow-up me-1"></i>
                        All-time earnings
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Spent -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card wallet-card h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <i class="bi bi-arrow-down-circle display-4 text-info me-3"></i>
                        <div>
                            <h3 class="text-white-custom mb-0"><?php echo number_format($wallet['total_spent_all_time'] ?? 0); ?></h3>
                            <small class="text-muted-custom">Total Spent</small>
                        </div>
                    </div>
                    <div class="text-info">
                        <i class="bi bi-arrow-down me-1"></i>
                        All-time spending
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Profit -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card wallet-card h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <i class="bi bi-trophy display-4 text-warning me-3"></i>
                        <div>
                            <?php $net_profit = ($wallet['total_earned_all_time'] ?? 0) - ($wallet['total_spent_all_time'] ?? 0); ?>
                            <h3 class="text-white-custom mb-0"><?php echo number_format($net_profit); ?></h3>
                            <small class="text-muted-custom">Net Profit</small>
                        </div>
                    </div>
                    <div class="<?php echo $net_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <i class="bi bi-<?php echo $net_profit >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                        <?php echo $net_profit >= 0 ? 'Profitable' : 'Investment Phase'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Revenue Sources -->
        <div class="col-lg-4 mb-4">
            <div class="card wallet-card h-100">
                <div class="card-header bg-transparent border-0">
                    <h5 class="text-white-custom mb-0">
                        <i class="bi bi-pie-chart me-2"></i>Revenue Sources
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($revenue_sources)): ?>
                        <?php foreach ($revenue_sources as $source): ?>
                        <div class="revenue-source-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-custom mb-1">
                                        <?php 
                                        $source_icons = [
                                            'casino_revenue_share' => 'dice-5',
                                            'store_sales' => 'shop',
                                            'promotional_bonus' => 'gift',
                                            'referral_bonus' => 'people',
                                            'manual_adjustment' => 'gear'
                                        ];
                                        $icon = $source_icons[$source['source_type']] ?? 'coin';
                                        ?>
                                        <i class="bi bi-<?php echo $icon; ?> me-2"></i>
                                        <?php echo ucwords(str_replace('_', ' ', $source['source_type'])); ?>
                                    </h6>
                                    <small class="text-muted-custom">
                                        <?php echo $source['periods']; ?> earning periods
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="text-warning fw-bold"><?php echo number_format($source['total_earned']); ?></div>
                                    <small class="text-muted-custom">QR Coins</small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted-custom py-4">
                            <i class="bi bi-info-circle display-4 mb-3"></i>
                            <p>No revenue sources yet. Start earning by:</p>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check me-2"></i>Enabling casino revenue sharing</li>
                                <li><i class="bi bi-check me-2"></i>Setting up your QR store</li>
                                <li><i class="bi bi-check me-2"></i>Running promotional campaigns</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Monthly Earnings Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card wallet-card h-100">
                <div class="card-header bg-transparent border-0">
                    <h5 class="text-white-custom mb-0">
                        <i class="bi bi-bar-chart me-2"></i>Monthly Earnings Trend
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="earningsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="row">
        <div class="col-12">
            <div class="card wallet-card">
                <div class="card-header bg-transparent border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="text-white-custom mb-0">
                            <i class="bi bi-clock-history me-2"></i>Recent Transactions
                        </h5>
                        <a href="#" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-download me-1"></i>Export CSV
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($transactions)): ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($transactions as $transaction): ?>
                            <div class="transaction-item <?php echo $transaction['transaction_type']; ?>">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <i class="bi bi-<?php 
                                        echo $transaction['transaction_type'] === 'earning' ? 'arrow-up-circle text-success' : 
                                             ($transaction['transaction_type'] === 'spending' ? 'arrow-down-circle text-danger' : 
                                             'gear text-warning'); 
                                        ?> fs-4"></i>
                                    </div>
                                    <div class="col">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="text-white-custom mb-1"><?php echo htmlspecialchars($transaction['description']); ?></h6>
                                                <div class="d-flex gap-3">
                                                    <span class="badge badge-<?php echo $transaction['transaction_type']; ?>">
                                                        <?php echo ucfirst($transaction['transaction_type']); ?>
                                                    </span>
                                                    <small class="text-muted-custom"><?php echo ucwords(str_replace('_', ' ', $transaction['category'])); ?></small>
                                                    <?php if ($transaction['reference_type']): ?>
                                                        <small class="text-muted-custom">
                                                            <i class="bi bi-link me-1"></i><?php echo ucfirst($transaction['reference_type']); ?> #<?php echo $transaction['reference_id']; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold <?php echo $transaction['amount'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $transaction['amount'] > 0 ? '+' : ''; ?><?php echo number_format($transaction['amount']); ?>
                                                </div>
                                                <small class="text-muted-custom">
                                                    Balance: <?php echo number_format($transaction['balance_after']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <small class="text-muted-custom">
                                            <?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted-custom py-5">
                            <i class="bi bi-receipt display-3 mb-3"></i>
                            <h5>No transactions yet</h5>
                            <p>Your transaction history will appear here once you start earning QR coins.</p>
                            <a href="settings.php#casino" class="btn btn-wallet">
                                <i class="bi bi-rocket me-2"></i>Start Earning Now
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly earnings chart
const ctx = document.getElementById('earningsChart').getContext('2d');
const monthlyData = <?php echo json_encode(array_reverse($monthly_earnings)); ?>;

new Chart(ctx, {
    type: 'line',
    data: {
        labels: monthlyData.map(item => item.month),
        datasets: [{
            label: 'QR Coins Earned',
            data: monthlyData.map(item => item.earnings),
            borderColor: '#ffd700',
            backgroundColor: 'rgba(255, 215, 0, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: 'rgba(255, 255, 255, 0.8)'
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    color: 'rgba(255, 255, 255, 0.8)'
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            },
            x: {
                ticks: {
                    color: 'rgba(255, 255, 255, 0.8)'
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 