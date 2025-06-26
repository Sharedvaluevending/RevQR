<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

// Check if we should auto-login as business user for testing
if (isset($_GET['test_login']) && $_GET['test_login'] === 'business') {
    // Find the business user
    $stmt = $pdo->prepare("SELECT id, username, role, business_id FROM users WHERE role = 'business' LIMIT 1");
    $stmt->execute();
    $business_user = $stmt->fetch();
    
    if ($business_user) {
        $_SESSION['user_id'] = $business_user['id'];
        $_SESSION['username'] = $business_user['username'];
        $_SESSION['role'] = $business_user['role'];
        $_SESSION['business_id'] = $business_user['business_id'];
        echo "<script>alert('Logged in as business user: " . $business_user['username'] . "'); window.location.href = 'qr_manager.php';</script>";
        exit();
    }
}

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3><i class="bi bi-qr-code me-2"></i>QR Manager Access Test</h3>
                </div>
                <div class="card-body">
                    <h5>Current Session Status:</h5>
                    <div class="bg-light p-3 rounded mb-4">
                        <p><strong>Logged in:</strong> <?php echo is_logged_in() ? 'Yes' : 'No'; ?></p>
                        <?php if (is_logged_in()): ?>
                            <p><strong>User ID:</strong> <?php echo $_SESSION['user_id'] ?? 'None'; ?></p>
                            <p><strong>Username:</strong> <?php echo $_SESSION['username'] ?? 'None'; ?></p>
                            <p><strong>Role:</strong> <?php echo $_SESSION['role'] ?? 'None'; ?></p>
                            <p><strong>Business ID:</strong> <?php echo $_SESSION['business_id'] ?? 'None'; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <h5>Available Users in Database:</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Business ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $pdo->prepare("SELECT id, username, role, business_id FROM users ORDER BY role, username");
                                $stmt->execute();
                                $users = $stmt->fetchAll();
                                foreach ($users as $user):
                                ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['role'] === 'business' ? 'primary' : ($user['role'] === 'admin' ? 'danger' : 'secondary'); ?>">
                                            <?php echo $user['role']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['business_id'] ?? 'NULL'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <h5>QR Codes in Database:</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Business ID</th>
                                    <th>Machine Name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $pdo->prepare("SELECT id, code, qr_type, business_id, machine_name, status FROM qr_codes ORDER BY created_at DESC LIMIT 10");
                                $stmt->execute();
                                $qr_codes = $stmt->fetchAll();
                                foreach ($qr_codes as $qr):
                                ?>
                                <tr>
                                    <td><?php echo $qr['id']; ?></td>
                                    <td><code><?php echo htmlspecialchars($qr['code']); ?></code></td>
                                    <td><?php echo htmlspecialchars($qr['qr_type']); ?></td>
                                    <td><?php echo $qr['business_id'] ?? 'NULL'; ?></td>
                                    <td><?php echo htmlspecialchars($qr['machine_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($qr['status']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h5>Test Actions:</h5>
                            <div class="d-grid gap-2">
                                <a href="?test_login=business" class="btn btn-success">
                                    <i class="bi bi-person-check me-2"></i>Login as Business User & Go to QR Manager
                                </a>
                                <a href="qr_manager.php" class="btn btn-primary">
                                    <i class="bi bi-qr-code me-2"></i>Go to QR Manager (Current Session)
                                </a>
                                <a href="logout.php" class="btn btn-outline-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5>Expected QR Manager Features:</h5>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    QR Code List
                                    <span class="badge bg-primary">✓</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Scan Analytics
                                    <span class="badge bg-primary">✓</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    QR Code Images
                                    <span class="badge bg-primary">✓</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Test Links
                                    <span class="badge bg-primary">✓</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Download/Print
                                    <span class="badge bg-primary">✓</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 