<?php
/**
 * Simple Nayax Integration Settings (No Complex Includes)
 * Direct database connection version to avoid redirect issues
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'business') {
    echo '<h1>Authentication Required</h1>';
    echo '<p>Please log in as a business user to access this page.</p>';
    echo '<a href="/login.php">Login Here</a>';
    exit;
}

// Simple database connection
$host = 'localhost';
$dbname = 'revenueqr';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$business_id = $_SESSION['business_id'] ?? 1; // Fallback to 1 for testing
$success_message = '';
$error_message = '';

// Test Nayax API connection function
function testNayaxAPI($token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://lynx.nayax.com/operational/api/v1/devices');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => $http_code === 200,
        'http_code' => $http_code,
        'error' => $curl_error,
        'response' => $response
    ];
}

// Handle form submission
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'test_token') {
        $test_token = trim($_POST['test_token'] ?? '');
        if ($test_token) {
            $test_result = testNayaxAPI($test_token);
            if ($test_result['success']) {
                $devices = json_decode($test_result['response'], true);
                $success_message = "Connection successful! Found " . count($devices) . " devices.";
            } else {
                $error_message = "Connection failed: HTTP " . $test_result['http_code'] . " - " . $test_result['error'];
            }
        }
    } elseif ($action === 'save_token') {
        $access_token = trim($_POST['access_token'] ?? '');
        if ($access_token) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO business_nayax_credentials (business_id, access_token, api_url, is_active)
                    VALUES (?, AES_ENCRYPT(?, 'nayax_secure_key_2025'), 'https://lynx.nayax.com/operational/api/v1', 1)
                    ON DUPLICATE KEY UPDATE 
                    access_token = AES_ENCRYPT(VALUES(access_token), 'nayax_secure_key_2025'),
                    is_active = 1,
                    updated_at = NOW()
                ");
                $stmt->execute([$business_id, $access_token]);
                $success_message = "Nayax credentials saved successfully!";
            } catch (Exception $e) {
                $error_message = "Error saving credentials: " . $e->getMessage();
            }
        }
    }
}

// Get current settings
$current_settings = null;
try {
    $stmt = $pdo->prepare("
        SELECT api_url, is_active, last_sync_at, total_machines,
               CASE WHEN access_token IS NOT NULL THEN 'Yes' ELSE 'No' END as has_token
        FROM business_nayax_credentials 
        WHERE business_id = ?
    ");
    $stmt->execute([$business_id]);
    $current_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error loading settings: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nayax Integration Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="fas fa-cog"></i> Nayax Integration Settings</h1>
            <p class="text-muted">Connect your Nayax account to enable machine integration</p>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <!-- Connection Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Connection Status</h4>
                </div>
                <div class="card-body">
                    <?php if ($current_settings): ?>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Status:</strong> 
                                <span class="badge bg-<?= $current_settings['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $current_settings['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                            <div class="col-md-3">
                                <strong>Token Saved:</strong> 
                                <span class="badge bg-<?= $current_settings['has_token'] === 'Yes' ? 'success' : 'warning' ?>">
                                    <?= $current_settings['has_token'] ?>
                                </span>
                            </div>
                            <div class="col-md-3">
                                <strong>Machines:</strong> <?= $current_settings['total_machines'] ?: 0 ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Last Sync:</strong> 
                                <?= $current_settings['last_sync_at'] ? date('M j, H:i', strtotime($current_settings['last_sync_at'])) : 'Never' ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No Nayax integration configured yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Test Connection -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Test Nayax Connection</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="test_token">
                        <div class="row">
                            <div class="col-md-8">
                                <label class="form-label">Enter your Nayax Access Token to test:</label>
                                <input type="password" name="test_token" class="form-control" 
                                       placeholder="Your Nayax access token" required>
                                <small class="text-muted">
                                    Use: 6RDWH0sRaodLBFnRz0DfKNnHH_gLHKaJLkz-pihO9AG3QuO0G60us3vU3SNT-rU40
                                </small>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-test-tube"></i> Test Connection
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Save Credentials -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Save Nayax Credentials</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_token">
                        <div class="row">
                            <div class="col-md-8">
                                <label class="form-label">Nayax Access Token:</label>
                                <input type="password" name="access_token" class="form-control" 
                                       placeholder="Your Nayax access token" required>
                                <small class="text-muted">This will be encrypted and stored securely</small>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Save Credentials
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="card">
                <div class="card-header">
                    <h4>Quick Links</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <a href="discount-store.php" class="btn btn-outline-primary w-100 mb-2">
                                <i class="fas fa-tag"></i> Discount Store
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="machine-dashboard.php" class="btn btn-outline-info w-100 mb-2">
                                <i class="fas fa-desktop"></i> Machine Dashboard
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="nayax-settings.php" class="btn btn-outline-success w-100 mb-2">
                                <i class="fas fa-cog"></i> Full Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Debug Info -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5>Debug Information</h5>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <strong>Session Info:</strong><br>
                        User ID: <?= $_SESSION['user_id'] ?? 'Not set' ?><br>
                        User Type: <?= $_SESSION['user_type'] ?? 'Not set' ?><br>
                        Business ID: <?= $business_id ?><br>
                        Current File: <?= __FILE__ ?><br>
                        <br>
                        <strong>If the main nayax-settings.php redirects to dashboard:</strong><br>
                        • Check your web server document root<br>
                        • Look for .htaccess redirects<br>
                        • Verify session variables are set correctly<br>
                        • Try this simplified version instead
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 