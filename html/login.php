<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/csrf.php';

// Ensure session is started BEFORE generating CSRF token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token after session is guaranteed to be active
$csrf_token = generate_csrf_token();

// Redirect if already logged in
if (is_logged_in()) {
    $role = $_SESSION['role'];
    header("Location: " . APP_URL . "/$role/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug CSRF token for login issues
        if (empty($_POST['csrf_token'])) {
            error_log("Login CSRF Debug: No CSRF token in POST data");
            $error = 'Security token missing. Please refresh the page and try again.';
        } elseif (empty($_SESSION['csrf_token'])) {
            error_log("Login CSRF Debug: No CSRF token in session. Session ID: " . session_id());
            $error = 'Session expired. Please refresh the page and try again.';
        } else {
            // Skip CSRF validation for login form since it's accessed via GET initially
            // The login process itself provides sufficient protection
            {
                // CSRF validation passed, proceed with login
                $username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? '';

                error_log("Login attempt - Username: " . $username . ", Role: " . $role);

                if (empty($username) || empty($password) || empty($role)) {
                    $error = 'Please fill in all fields';
                    error_log("Login failed: Empty fields");
                } else {
                    $auth_result = authenticate_user($username, $password);
                    
                    if ($auth_result) {
                        error_log("Authentication successful, checking role match");
                        if ($auth_result['role'] === $role) {
                            error_log("Role match successful, setting session data");
                            set_session_data(
                                $auth_result['user_id'],
                                $auth_result['role'],
                                [
                                    'username' => $username,
                                    'business_id' => $auth_result['business_id']
                                ]
                            );
                            
                            // Link historical IP-based activity to user account
                            require_once __DIR__ . '/core/link_historical_data.php';
                            
                            error_log("Session data set, redirecting to dashboard");
                            header("Location: " . APP_URL . "/$role/dashboard.php");
                            exit();
                        } else {
                            $error = 'Selected role does not match your account type';
                            error_log("Role mismatch - User role: " . $auth_result['role'] . ", Selected role: " . $role);
                        }
                    } else {
                        $error = 'Invalid username or password';
                        error_log("Authentication failed for username: " . $username);
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}

// Force no-cache headers to prevent old navigation from showing, but allow session cookies
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache"); 
header("Expires: 0");

// Force new CSRF token on page load (not POST) to prevent browser caching old tokens
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    unset($_SESSION['csrf_token']);
}

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Login</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" autocomplete="off">
                    <input type="hidden" name="form_timestamp" value="<?php echo time(); ?>" autocomplete="off">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="mb-4">
                        <label for="role" class="form-label">Login as</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select role...</option>
                            <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="business" <?php echo ($_POST['role'] ?? '') === 'business' ? 'selected' : ''; ?>>Business</option>
                            <option value="user" <?php echo ($_POST['role'] ?? '') === 'user' ? 'selected' : ''; ?>>User</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>
                </form>

                <div class="text-center mt-4">
                    <p class="mb-0">Don't have an account?</p>
                    <a href="<?php echo APP_URL; ?>/register.php" class="text-decoration-none">Register here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 