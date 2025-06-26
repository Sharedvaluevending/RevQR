<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Test - RevenueQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .test-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            color: white;
        }
        .status-good { color: #28a745; }
        .status-bad { color: #dc3545; }
        .status-warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="test-card p-4 mb-4">
                    <h1 class="text-center mb-4">🔒 Session Diagnostic Test</h1>
                    <p class="text-center">This page tests if your session management is working correctly</p>
                </div>

                <div class="test-card p-4 mb-4">
                    <h3>📊 Session Status</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Session Active:</strong> 
                                <span class="<?php echo (session_status() === PHP_SESSION_ACTIVE) ? 'status-good' : 'status-bad'; ?>">
                                    <?php echo (session_status() === PHP_SESSION_ACTIVE) ? '✅ Yes' : '❌ No'; ?>
                                </span>
                            </p>
                            <p><strong>Session ID:</strong> 
                                <code><?php echo session_id() ?: 'None'; ?></code>
                            </p>
                            <p><strong>HTTPS Status:</strong> 
                                <span class="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'status-good' : 'status-warning'; ?>">
                                    <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? '🔒 HTTPS' : '🔓 HTTP'; ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>User Logged In:</strong> 
                                <span class="<?php echo is_logged_in() ? 'status-good' : 'status-warning'; ?>">
                                    <?php echo is_logged_in() ? '✅ Yes' : '⚠️ No'; ?>
                                </span>
                            </p>
                            <p><strong>User ID:</strong> 
                                <code><?php echo $_SESSION['user_id'] ?? 'Not set'; ?></code>
                            </p>
                            <p><strong>User Role:</strong> 
                                <code><?php echo $_SESSION['role'] ?? 'Not set'; ?></code>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="test-card p-4 mb-4">
                    <h3>🍪 Cookie Settings</h3>
                    <?php
                    $cookieParams = session_get_cookie_params();
                    ?>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Lifetime:</strong> <?php echo $cookieParams['lifetime']; ?> seconds</p>
                            <p><strong>Path:</strong> <?php echo $cookieParams['path']; ?></p>
                            <p><strong>Domain:</strong> <?php echo $cookieParams['domain'] ?: '(current)'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Secure:</strong> 
                                <span class="<?php echo $cookieParams['secure'] ? 'status-good' : 'status-warning'; ?>">
                                    <?php echo $cookieParams['secure'] ? '✅ Yes' : '⚠️ No'; ?>
                                </span>
                            </p>
                            <p><strong>HttpOnly:</strong> 
                                <span class="<?php echo $cookieParams['httponly'] ? 'status-good' : 'status-bad'; ?>">
                                    <?php echo $cookieParams['httponly'] ? '✅ Yes' : '❌ No'; ?>
                                </span>
                            </p>
                            <p><strong>SameSite:</strong> <?php echo $cookieParams['samesite']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="test-card p-4 mb-4">
                    <h3>🧪 Session Test</h3>
                    <?php
                    // Test session by setting/getting a value
                    if (!isset($_SESSION['test_value'])) {
                        $_SESSION['test_value'] = 'Session is working! ' . date('Y-m-d H:i:s');
                    }
                    ?>
                    <p><strong>Test Value:</strong> <code><?php echo $_SESSION['test_value']; ?></code></p>
                    <p><strong>Page Loads:</strong> 
                        <code>
                            <?php 
                            $_SESSION['page_loads'] = ($_SESSION['page_loads'] ?? 0) + 1;
                            echo $_SESSION['page_loads'];
                            ?>
                        </code>
                    </p>
                </div>

                <div class="test-card p-4 mb-4">
                    <h3>🔍 Diagnostic</h3>
                    <?php if (is_logged_in()): ?>
                        <div class="alert alert-success">
                            ✅ <strong>Session Working!</strong> You are properly logged in.
                        </div>
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="user/dashboard.php" class="btn btn-success">Go to User Dashboard</a>
                            <a href="business/dashboard.php" class="btn btn-primary">Go to Business Dashboard</a>
                            <a href="logout.php" class="btn btn-outline-light">Logout</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            ⚠️ <strong>Not Logged In</strong> - Session is working, but you need to log in.
                        </div>
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="login.php" class="btn btn-primary">Login</a>
                            <a href="register.php" class="btn btn-outline-light">Register</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="test-card p-4">
                    <h3>🛠️ Debug Info</h3>
                    <details>
                        <summary class="btn btn-outline-light btn-sm">Show Full Session Data</summary>
                        <pre class="mt-3 p-3 bg-dark text-white rounded">
<?php print_r($_SESSION); ?>
                        </pre>
                    </details>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 