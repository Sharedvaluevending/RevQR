<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

if (!is_logged_in()) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$current_balance = QRCoinManager::getBalance($user_id);

// Get recent blackjack transactions
$stmt = $pdo->prepare("
    SELECT amount, transaction_type, description, created_at 
    FROM qr_transactions 
    WHERE user_id = ? AND description LIKE '%blackjack%' 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$blackjack_transactions = $stmt->fetchAll();

// Get recent balance sync transactions
$stmt = $pdo->prepare("
    SELECT amount, transaction_type, description, created_at 
    FROM qr_transactions 
    WHERE user_id = ? AND description LIKE '%sync%' 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$sync_transactions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blackjack Balance Persistence Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card bg-secondary">
                    <div class="card-header">
                        <h4>🃏 Blackjack Balance Persistence Test</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h5>Current QR Balance: <?php echo number_format($current_balance); ?> coins</h5>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <a href="blackjack.php" class="btn btn-primary btn-lg w-100">
                                    🎯 Play Blackjack
                                </a>
                                <small class="text-muted d-block mt-2">
                                    Go play blackjack, make some bets, win or lose, then come back here
                                </small>
                            </div>
                            <div class="col-md-6">
                                <a href="index.php" class="btn btn-secondary btn-lg w-100">
                                    🏠 Casino Home
                                </a>
                                <small class="text-muted d-block mt-2">
                                    Check your balance on the casino home page
                                </small>
                            </div>
                        </div>
                        
                        <button onclick="location.reload()" class="btn btn-outline-light mb-3">
                            🔄 Refresh This Page
                        </button>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Recent Blackjack Transactions:</h6>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php if (empty($blackjack_transactions)): ?>
                                        <p class="text-muted">No blackjack transactions found</p>
                                    <?php else: ?>
                                        <?php foreach ($blackjack_transactions as $tx): ?>
                                            <div class="card bg-dark mb-2">
                                                <div class="card-body py-2">
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-<?php echo $tx['transaction_type'] === 'earning' ? 'success' : 'danger'; ?>">
                                                            <?php echo $tx['transaction_type'] === 'earning' ? '+' : '-'; ?><?php echo number_format($tx['amount']); ?>
                                                        </span>
                                                        <small class="text-muted"><?php echo date('H:i:s', strtotime($tx['created_at'])); ?></small>
                                                    </div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($tx['description']); ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Recent Balance Sync Transactions:</h6>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php if (empty($sync_transactions)): ?>
                                        <p class="text-muted">No sync transactions found</p>
                                    <?php else: ?>
                                        <?php foreach ($sync_transactions as $tx): ?>
                                            <div class="card bg-dark mb-2">
                                                <div class="card-body py-2">
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-<?php echo $tx['transaction_type'] === 'earning' ? 'success' : 'danger'; ?>">
                                                            <?php echo $tx['transaction_type'] === 'earning' ? '+' : '-'; ?><?php echo number_format($tx['amount']); ?>
                                                        </span>
                                                        <small class="text-muted"><?php echo date('H:i:s', strtotime($tx['created_at'])); ?></small>
                                                    </div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($tx['description']); ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6>Test Instructions:</h6>
                            <ol>
                                <li>Note your current balance above</li>
                                <li>Click "Play Blackjack" to go to the blackjack game</li>
                                <li>Place some bets and play a few rounds</li>
                                <li>Navigate back here using browser back button or by typing this URL</li>
                                <li>Your balance should reflect the changes from blackjack</li>
                                <li>Check the transaction history to see if sync transactions appear</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 10 seconds to see real-time updates
        setTimeout(() => {
            const url = new URL(window.location);
            url.searchParams.set('auto_refresh', Date.now());
            window.location.href = url.toString();
        }, 10000);
    </script>
</body>
</html> 