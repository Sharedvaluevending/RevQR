<?php
/**
 * Casino Balance Debug Page
 * Helps diagnose QR coin balance issues in casino games
 */

session_start();
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /html/auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get current balance
$current_balance = QRCoinManager::getBalance($user_id);

// Get recent QR coin transactions
$stmt = $pdo->prepare("
    SELECT *
    FROM qr_coin_transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

// Get recent casino plays
$stmt = $pdo->prepare("
    SELECT cp.*, b.name as business_name
    FROM casino_plays cp
    LEFT JOIN businesses b ON cp.business_id = b.id
    WHERE cp.user_id = ? 
    ORDER BY cp.played_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$casino_plays = $stmt->fetchAll();

// Check for any orphaned casino plays (plays without matching transactions)
$stmt = $pdo->prepare("
    SELECT cp.*
    FROM casino_plays cp
    LEFT JOIN qr_coin_transactions qct ON cp.id = qct.reference_id AND qct.reference_type = 'casino_play'
    WHERE cp.user_id = ? AND qct.id IS NULL
    ORDER BY cp.played_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$orphaned_plays = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Casino Balance Debug | RevenueQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="mb-0">
                    <i class="bi bi-bug text-warning me-2"></i>Casino Balance Debug
                </h1>
                <p class="text-muted">Diagnostic tool for QR coin balance issues</p>
            </div>
        </div>

        <!-- Current Balance -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h2 class="display-4"><?php echo number_format($current_balance); ?></h2>
                        <p class="mb-0">Current QR Coin Balance</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5>Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <button class="btn btn-light" onclick="refreshBalance()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh Balance
                            </button>
                            <a href="/html/user/qr-transactions.php" class="btn btn-outline-light">
                                <i class="bi bi-list me-1"></i>View All Transactions
                            </a>
                            <a href="/html/casino/" class="btn btn-outline-light">
                                <i class="bi bi-arrow-left me-1"></i>Back to Casino
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent QR Coin Transactions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Recent QR Coin Transactions
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transactions)): ?>
                            <div class="text-center py-3 text-muted">
                                No transactions found
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Category</th>
                                            <th>Amount</th>
                                            <th>Description</th>
                                            <th>Reference</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $tx): ?>
                                            <tr>
                                                <td><?php echo date('M d, H:i', strtotime($tx['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $tx['transaction_type'] === 'earning' ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($tx['transaction_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($tx['category']); ?></td>
                                                <td class="text-<?php echo $tx['amount'] >= 0 ? 'success' : 'danger'; ?>">
                                                    <?php echo $tx['amount'] >= 0 ? '+' : ''; ?><?php echo number_format($tx['amount']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($tx['description']); ?></td>
                                                <td>
                                                    <?php if ($tx['reference_id']): ?>
                                                        <?php echo $tx['reference_type']; ?> #<?php echo $tx['reference_id']; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
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

        <!-- Recent Casino Plays -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-dice-6 me-2"></i>Recent Casino Plays
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($casino_plays)): ?>
                            <div class="text-center py-3 text-muted">
                                No casino plays found
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Business</th>
                                            <th>Bet</th>
                                            <th>Win</th>
                                            <th>Result</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($casino_plays as $play): ?>
                                            <tr>
                                                <td><?php echo date('M d, H:i', strtotime($play['played_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($play['business_name'] ?? 'Unknown'); ?></td>
                                                <td class="text-danger">-<?php echo number_format($play['bet_amount']); ?></td>
                                                <td class="text-success">
                                                    <?php if ($play['win_amount'] > 0): ?>
                                                        +<?php echo number_format($play['win_amount']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($play['prize_won'] ?? 'No prize'); ?></td>
                                                <td>
                                                    <?php if ($play['is_jackpot']): ?>
                                                        <span class="badge bg-warning text-dark">JACKPOT</span>
                                                    <?php elseif ($play['win_amount'] > 0): ?>
                                                        <span class="badge bg-success">WIN</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">LOSS</span>
                                                    <?php endif; ?>
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

        <!-- Orphaned Casino Plays (plays without matching transactions) -->
        <?php if (!empty($orphaned_plays)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>Potential Issues Found
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-warning mb-3">
                                The following casino plays don't have matching QR coin transactions. This might indicate a sync issue:
                            </p>
                            <div class="table-responsive">
                                <table class="table table-dark table-striped">
                                    <thead>
                                        <tr>
                                            <th>Play ID</th>
                                            <th>Date</th>
                                            <th>Bet</th>
                                            <th>Win</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orphaned_plays as $play): ?>
                                            <tr>
                                                <td>#<?php echo $play['id']; ?></td>
                                                <td><?php echo date('M d, H:i', strtotime($play['played_at'])); ?></td>
                                                <td class="text-danger">-<?php echo number_format($play['bet_amount']); ?></td>
                                                <td class="text-success">
                                                    <?php if ($play['win_amount'] > 0): ?>
                                                        +<?php echo number_format($play['win_amount']); ?>
                                                    <?php else: ?>
                                                        0
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="fixTransaction(<?php echo $play['id']; ?>)">
                                                        Fix Transaction
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function refreshBalance() {
            location.reload();
        }

        function fixTransaction(playId) {
            if (confirm('This will attempt to create the missing QR coin transactions for this casino play. Continue?')) {
                fetch('fix-casino-transaction.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        play_id: playId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Transaction fixed successfully!');
                        location.reload();
                    } else {
                        alert('Failed to fix transaction: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }

        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html> 