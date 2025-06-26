<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

$business_id = $_SESSION['business_id'] ?? null;
if (!$business_id) {
    // Get business_id from user table if not in session
    $stmt = $pdo->prepare("SELECT business_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $business_id = $stmt->fetchColumn();
    $_SESSION['business_id'] = $business_id;
}

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'update_casino_participation':
                $casino_enabled = isset($_POST['casino_enabled']) ? 1 : 0;
                $featured_promotion = trim($_POST['featured_promotion'] ?? '');
                $location_bonus = (float) ($_POST['location_bonus_multiplier'] ?? 1.0);
                $show_promotional_ad = isset($_POST['show_promotional_ad']) ? 1 : 0;
                
                // Validate input
                if ($location_bonus < 1.0 || $location_bonus > 1.5) {
                    throw new Exception('Location bonus must be between 1.0 and 1.5 (0% to 50% bonus)');
                }
                if (strlen($featured_promotion) > 255) {
                    throw new Exception('Featured promotion text must be 255 characters or less');
                }
                
                // Insert or update casino participation
                $stmt = $pdo->prepare("
                    INSERT INTO business_casino_participation 
                    (business_id, casino_enabled, featured_promotion, location_bonus_multiplier, show_promotional_ad)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    casino_enabled = VALUES(casino_enabled),
                    featured_promotion = VALUES(featured_promotion),
                    location_bonus_multiplier = VALUES(location_bonus_multiplier),
                    show_promotional_ad = VALUES(show_promotional_ad),
                    updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$business_id, $casino_enabled, $featured_promotion, $location_bonus, $show_promotional_ad]);
                
                // Create or update promotional ad if enabled
                if ($casino_enabled && $show_promotional_ad && !empty($featured_promotion)) {
                    require_once __DIR__ . '/../core/promotional_ads_manager.php';
                    $adsManager = new PromotionalAdsManager($pdo);
                    
                    // Get business name for ad title
                    $stmt = $pdo->prepare("SELECT name FROM businesses WHERE id = ?");
                    $stmt->execute([$business_id]);
                    $business_name = $stmt->fetchColumn();
                    
                    $adsManager->createAd(
                        $business_id,
                        'casino',
                        $business_name . ' Casino Bonus!',
                        $featured_promotion,
                        'Play Now',
                        '/casino/index.php',
                        [
                            'background_color' => '#dc3545',
                            'text_color' => '#ffffff',
                            'show_on_vote_page' => true,
                            'priority' => 2
                        ]
                    );
                } else {
                    // Disable promotional ad if casino disabled or promotional ad disabled
                    $stmt = $pdo->prepare("
                        UPDATE business_promotional_ads 
                        SET is_active = FALSE 
                        WHERE business_id = ? AND feature_type = 'casino'
                    ");
                    $stmt->execute([$business_id]);
                }
                
                $success = 'Casino participation updated successfully!';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch current casino participation
$stmt = $pdo->prepare("SELECT * FROM business_casino_participation WHERE business_id = ?");
$stmt->execute([$business_id]);
$casino_participation = $stmt->fetch() ?: [];

// Get unified casino settings for display
$stmt = $pdo->query("SELECT * FROM casino_unified_settings WHERE id = 1");
$unified_settings = $stmt->fetch() ?: [];

// Fetch business info
$stmt = $pdo->prepare("SELECT name, logo_path FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

include '../core/includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active">Settings</li>
                </ol>
            </nav>
            <h1 class="mb-2">Business Settings</h1>
            <p class="text-muted">Configure your business features and preferences</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Casino Participation Card -->
    <div class="card mb-4" id="casino">
        <div class="card-header">
            <h5><i class="bi bi-dice-5-fill text-danger me-2"></i>🎰 Unified Casino Participation</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_casino_participation">
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="casino_enabled" id="casinoEnabled" 
                           <?php echo ($casino_participation['casino_enabled'] ?? false) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="casinoEnabled">
                        <strong>Enable Casino at Your Location</strong>
                    </label>
                    <div class="form-text">
                        Allow users to play the unified Revenue QR Casino at your location and earn revenue sharing.
                        All prizes and rules are managed centrally - you just enable participation.
                    </div>
                </div>
                
                <div id="casinoOptions" style="<?php echo ($casino_participation['casino_enabled'] ?? false) ? '' : 'display:none;'; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Featured Promotion (Optional)</label>
                        <input type="text" class="form-control" name="featured_promotion" 
                               value="<?php echo htmlspecialchars($casino_participation['featured_promotion'] ?? ''); ?>" 
                               maxlength="255" placeholder="e.g., 'Play here for 10% bonus on all wins!'">
                        <div class="form-text">Optional promotional text shown to casino players (255 characters max)</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="show_promotional_ad" id="showPromotionalAd"
                                   <?php echo ($casino_participation['show_promotional_ad'] ?? false) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="showPromotionalAd">
                                <strong>Show Promotional Ad on User Pages</strong>
                            </label>
                            <div class="form-text">
                                Display your casino promotion as an ad on voting pages and user dashboard to drive more traffic.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Location Bonus Multiplier</label>
                        <select class="form-control" name="location_bonus_multiplier">
                            <option value="1.00" <?php echo ($casino_participation['location_bonus_multiplier'] ?? 1.0) == 1.0 ? 'selected' : ''; ?>>1.0x (No Bonus)</option>
                            <option value="1.05" <?php echo ($casino_participation['location_bonus_multiplier'] ?? 1.0) == 1.05 ? 'selected' : ''; ?>>1.05x (+5% Bonus)</option>
                            <option value="1.10" <?php echo ($casino_participation['location_bonus_multiplier'] ?? 1.0) == 1.10 ? 'selected' : ''; ?>>1.1x (+10% Bonus)</option>
                            <option value="1.15" <?php echo ($casino_participation['location_bonus_multiplier'] ?? 1.0) == 1.15 ? 'selected' : ''; ?>>1.15x (+15% Bonus)</option>
                            <option value="1.20" <?php echo ($casino_participation['location_bonus_multiplier'] ?? 1.0) == 1.20 ? 'selected' : ''; ?>>1.2x (+20% Bonus)</option>
                        </select>
                        <div class="form-text">Optional bonus multiplier for wins at your location (higher bonus = more foot traffic)</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="bi bi-lightbulb me-1"></i> Unified Casino Benefits:</h6>
                        <ul class="mb-0">
                            <li><strong>Automatic Revenue:</strong> Earn <?php echo $casino_participation['revenue_share_percentage'] ?? 10; ?>% of casino activity at your location</li>
                            <li><strong>Increased Foot Traffic:</strong> Players must visit your location to play</li>
                            <li><strong>Zero Management:</strong> No complex settings - just enable participation</li>
                            <li><strong>Brand Visibility:</strong> Your logo and promotion appear in casino</li>
                            <li><strong>Consistent Experience:</strong> Same rules everywhere reduce user confusion</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-success">
                        <h6><i class="bi bi-info-circle me-1"></i> Platform Casino Settings:</h6>
                        <p class="mb-2">All managed centrally by Revenue QR:</p>
                        <ul class="mb-0">
                            <li><strong>Daily Spins:</strong> <?php echo $unified_settings['base_daily_spins'] ?? 10; ?> free spins per user per day</li>
                            <li><strong>Bet Range:</strong> <?php echo $unified_settings['min_bet'] ?? 1; ?>-<?php echo $unified_settings['max_bet'] ?? 50; ?> QR Coins per spin</li>
                            <li><strong>Jackpot Threshold:</strong> <?php echo $unified_settings['jackpot_threshold'] ?? 25; ?>x multiplier for jackpots</li>
                            <li><strong>House Edge:</strong> <?php echo $unified_settings['house_edge_target'] ?? 5; ?>% platform target</li>
                        </ul>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check me-1"></i>Update Casino Participation
                </button>
            </form>
        </div>
    </div>

    <!-- Business Info Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="bi bi-building me-2"></i>Business Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h6><?php echo htmlspecialchars($business['name']); ?></h6>
                    <p class="text-muted">Business ID: <?php echo $business_id; ?></p>
                    <a href="profile.php" class="btn btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i>Edit Business Profile
                    </a>
                </div>
                <?php if ($business['logo_path']): ?>
                <div class="col-md-4 text-end">
                    <img src="<?php echo APP_URL . '/' . htmlspecialchars($business['logo_path']); ?>" 
                         alt="Business Logo" style="max-height: 80px; max-width: 200px;" class="img-thumbnail">
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="card">
        <div class="card-header">
            <h5><i class="bi bi-link-45deg me-2"></i>Quick Links</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <a href="notification-settings.php" class="btn btn-outline-info w-100">
                        <i class="bi bi-bell me-1"></i>Notifications
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="spin-wheel.php" class="btn btn-outline-success w-100">
                        <i class="bi bi-arrow-clockwise me-1"></i>Spin Wheel
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="analytics.php" class="btn btn-outline-warning w-100">
                        <i class="bi bi-graph-up me-1"></i>Analytics
                    </a>
                </div>
                <?php if ($casino_settings['casino_enabled'] ?? false): ?>
                <div class="col-md-3">
                    <a href="casino-analytics.php" class="btn btn-outline-danger w-100">
                        <i class="bi bi-dice-5-fill me-1"></i>Casino Analytics
                    </a>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <a href="user-settings.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-person-gear me-1"></i>User Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('casinoEnabled').addEventListener('change', function() {
    document.getElementById('casinoOptions').style.display = this.checked ? 'block' : 'none';
});
</script>

<?php include '../core/includes/footer.php'; ?> 