<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/qr_coin_manager.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /html/user/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$balance = QRCoinManager::getBalance($user_id);

// Handle AJAX requests for testing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'test_frontend_purchase':
            // Test the frontend purchase flow
            $item_id = (int)$_POST['item_id'];
            
            // Test purchase via frontend API
            $purchase_data = json_encode(['item_id' => $item_id]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, APP_URL . '/user/purchase-business-item.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $purchase_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Cookie: ' . $_SERVER['HTTP_COOKIE']
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo json_encode([
                'success' => true,
                'frontend_test' => [
                    'http_code' => $http_code,
                    'response' => json_decode($response, true),
                    'raw_response' => $response
                ]
            ]);
            exit;
    }
}

// Get available business discount items for testing
$stmt = $pdo->prepare("
    SELECT bsi.*, b.name as business_name, b.id as business_id
    FROM business_store_items bsi
    JOIN businesses b ON bsi.business_id = b.id
    WHERE bsi.category = 'discount' AND bsi.is_active = 1
    ORDER BY bsi.created_at DESC
    LIMIT 10
");
$stmt->execute();
$discount_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Discount Purchase Testing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h1><i class="bi bi-bug me-2 text-primary"></i>Business Discount Purchase Testing</h1>
    <p>Current Balance: <?= number_format($balance) ?> QR Coins</p>
    
    <div class="row">
        <?php foreach ($discount_items as $item): ?>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6><?= htmlspecialchars($item['item_name']) ?></h6>
                        <p class="text-muted"><?= htmlspecialchars($item['business_name']) ?></p>
                        <p><?= $item['discount_percentage'] ?>% OFF - <?= number_format($item['qr_coin_cost']) ?> QR Coins</p>
                        <button class="btn btn-primary btn-sm" onclick="testFrontendPurchase(<?= $item['id'] ?>)">
                            Test Purchase
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div id="test-results" class="mt-4"></div>
</div>

<script>
function testFrontendPurchase(itemId) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=test_frontend_purchase&item_id=${itemId}`
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('test-results').innerHTML = `
            <div class="alert alert-info">
                <h5>Test Results:</h5>
                <pre>${JSON.stringify(data, null, 2)}</pre>
            </div>
        `;
    })
    .catch(error => {
        document.getElementById('test-results').innerHTML = `
            <div class="alert alert-danger">
                <h5>Error:</h5>
                <p>${error.message}</p>
            </div>
        `;
    });
}
</script>
</body>
</html>
