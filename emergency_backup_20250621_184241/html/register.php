<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/csrf.php';

// Redirect if already logged in
if (is_logged_in()) {
    $role = $_SESSION['role'];
    header("Location: " . APP_URL . "/$role/dashboard.php");
    exit();
}

$error = '';
$success = '';

// Add slug generation function
function generate_slug($name) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    return $slug;
}

// Function to ensure unique slug
function ensure_unique_slug($pdo, $slug) {
    $original_slug = $slug;
    $counter = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM businesses WHERE slug = ?");
        $stmt->execute([$slug]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $original_slug . '-' . $counter++;
    }
}

// Add this at the top after session start
$casino_incentive = isset($_SESSION['casino_signup_incentive']) || isset($_GET['ref']) && $_GET['ref'] === 'casino';

if ($casino_incentive) {
    $signup_benefits = [
        '🎰 <strong>Instant Casino Access</strong> - Play slot machines with QR avatars',
        '🪙 <strong>50 Bonus QR Coins</strong> - Start playing immediately', 
        '🎁 <strong>Daily Free Spins</strong> - Earn coins through voting and activities',
        '🏆 <strong>Win Real Prizes</strong> - Local business discounts and rewards'
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    
    $username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $business_id = null;

    // Validation
    if (empty($username) || empty($password) || empty($confirm_password) || empty($role) || empty($name)) {
        $error = 'Please fill in all fields';
    } elseif (!validate_password($password)) {
        $error = 'Password must be at least 8 characters long and contain uppercase, lowercase, and numbers';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username already taken';
        } else {
            // For business users, create business record first
            if ($role === 'business') {
                $slug = generate_slug($name);
                $slug = ensure_unique_slug($pdo, $slug);
                $stmt = $pdo->prepare("INSERT INTO businesses (name, slug, user_id, created_at) VALUES (?, ?, ?, NOW())");
                if ($stmt->execute([$name, $slug, null])) {  // user_id will be updated after user creation
                    $business_id = $pdo->lastInsertId();
                } else {
                    $error = 'Failed to create business record';
                }
            }

            if (!$error) {
                // Create user
                if (create_user($username, $password, $role, $business_id)) {
                    // Update business with user_id
                    if ($role === 'business') {
                        $user_id = $pdo->lastInsertId();
                        $stmt = $pdo->prepare("UPDATE businesses SET user_id = ? WHERE id = ?");
                        $stmt->execute([$user_id, $business_id]);
                    }
                    $success = 'Registration successful! You can now login.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Register</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <div class="mt-2">
                            <a href="<?php echo APP_URL; ?>/login.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if ($casino_incentive): ?>
                    <div class="alert alert-danger border-0 mb-4" style="background: linear-gradient(135deg, #dc3545, #fd7e14);">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <i class="bi bi-dice-5-fill" style="font-size: 4rem; color: white;"></i>
                            </div>
                            <div class="col-md-9">
                                <h4 class="text-white mb-2">🎰 Join the QR Casino Revolution!</h4>
                                <p class="text-white mb-3">Create your account now and get instant access to:</p>
                                <ul class="list-unstyled text-white">
                                    <?php foreach ($signup_benefits as $benefit): ?>
                                        <li class="mb-1">✨ <?php echo $benefit; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <small class="text-white-50">
                                    <i class="bi bi-shield-check me-1"></i>
                                    Free forever • No credit card required • Play responsibly
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="form-text">
                                Choose a unique username for login
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">
                                Must be at least 8 characters with uppercase, lowercase, and numbers
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>

                        <div class="mb-4">
                            <label for="role" class="form-label">Register as</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select role...</option>
                                <option value="business">Business</option>
                                <option value="user">User</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-person-plus me-2"></i>Register
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <p class="mb-0">Already have an account?</p>
                        <a href="<?php echo APP_URL; ?>/login.php" class="text-decoration-none">Login here</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 