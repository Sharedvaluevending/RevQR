<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

$message = '';
$message_type = '';
$qr_code = null;
$campaign = null;
$items = [];
$machine_info = null;

// Get QR code details
if (isset($_GET['qr'])) {
    $stmt = $pdo->prepare("
        SELECT qr.*, c.*, b.name as business_name
        FROM qr_codes qr
        JOIN qr_campaigns c ON qr.campaign_id = c.id
        JOIN businesses b ON c.business_id = b.id
        WHERE qr.code = ? AND c.is_active = 1
    ");
    $stmt->execute([$_GET['qr']]);
    $qr_code = $stmt->fetch();
    
    if ($qr_code) {
        // QR code scan tracking (removed due to schema mismatch)
        // Log scan event in machine_engagement
        $machine_id = $qr_code['machine_id'] ?? null;
        if (!$machine_id && !empty($qr_code['machine_name'])) {
            $stmt_mid = $pdo->prepare("SELECT id FROM machines WHERE name = ?");
            $stmt_mid->execute([$qr_code['machine_name']]);
            $machine_id = $stmt_mid->fetchColumn();
        }
        $stmt = $pdo->prepare("INSERT INTO machine_engagement (qr_code_id, machine_id, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $qr_code['id'],
            $machine_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        // Get campaign items with vote counts
        $stmt = $pdo->prepare("
            SELECT i.*, 
                   i.high_margin,
                   (SELECT COUNT(*) FROM votes v WHERE v.item_id = i.id AND v.vote_type = 'vote_in') as vote_in_count,
                   (SELECT COUNT(*) FROM votes v WHERE v.item_id = i.id AND v.vote_type = 'vote_out') as vote_out_count,
                   (SELECT COUNT(*) FROM votes v WHERE v.item_id = i.id AND v.qr_code_id = ?) as machine_votes
            FROM items i
            JOIN campaign_items ci ON i.id = ci.item_id
            WHERE ci.campaign_id = ? AND i.status = 'active'
            ORDER BY i.item_name
        ");
        $stmt->execute([$qr_code['id'], $qr_code['campaign_id']]);
        $items = $stmt->fetchAll();
        
        // Get machine info
        $machine_info = [
            'name' => $qr_code['machine_name'],
            'location' => $qr_code['machine_location'],
            'total_scans' => 0 // Scan tracking disabled due to schema mismatch
        ];
    }
}

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id']) && isset($_POST['vote_type'])) {
    $item_id = $_POST['item_id'];
    $vote_type = $_POST['vote_type'];
    $qr_code_id = $_POST['qr_code_id'] ?? null;
    
    // Check if user has already voted for this item on this machine
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM votes 
        WHERE item_id = ? 
        AND qr_code_id = ?
        AND voter_ip = ?
    ");
    $stmt->execute([$item_id, $qr_code_id, get_client_ip()]);
    $has_voted = $stmt->fetchColumn() > 0;
    
    if ($has_voted) {
        $message = 'You have already voted for this item on this machine.';
        $message_type = 'warning';
    } else {
        // Record the vote with normalized vote type
        $user_id = $_SESSION['user_id'] ?? null; // Get user_id if logged in
        $normalized_vote_type = ($vote_type === 'in') ? 'vote_in' : 'vote_out';
        
        $stmt = $pdo->prepare("
            INSERT INTO votes (
                campaign_id, item_id, qr_code_id, vote_type, 
                user_id, voter_ip, machine_id, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            $qr_code['campaign_id'], 
            $item_id, 
            $qr_code_id, 
            $normalized_vote_type, 
            $user_id, // Include user_id for logged-in users
            get_client_ip()
        ]);
        
        $message = 'Thank you for your vote!';
        $message_type = 'success';
        
        // Refresh items to show updated counts
        $stmt = $pdo->prepare("
            SELECT i.*, 
                   i.high_margin,
                   (SELECT COUNT(*) FROM votes v WHERE v.item_id = i.id AND v.vote_type = 'vote_in') as vote_in_count,
                   (SELECT COUNT(*) FROM votes v WHERE v.item_id = i.id AND v.vote_type = 'vote_out') as vote_out_count,
                   (SELECT COUNT(*) FROM votes v WHERE v.item_id = i.id AND v.qr_code_id = ?) as machine_votes
            FROM items i
            JOIN campaign_items ci ON i.id = ci.item_id
            WHERE ci.campaign_id = ? AND i.status = 'active'
            ORDER BY i.item_name
        ");
        $stmt->execute([$qr_code_id, $qr_code['campaign_id']]);
        $items = $stmt->fetchAll();
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<!-- OVERRIDE CONFLICTING HEADER STYLES -->
<style>
/* CRITICAL: Override all conflicting header styles with higher specificity */
html.voting-page, 
html.voting-page body,
body.voting-page {
    background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%) !important;
    background-attachment: fixed !important;
    color: #ffffff !important;
    min-height: 100vh !important;
    font-family: 'Inter', 'Segoe UI', system-ui, sans-serif !important;
}

/* Override global card styles */
.voting-page .card,
.voting-page .card.h-100,
.voting-page .card:hover {
    background: unset !important;
    backdrop-filter: unset !important;
    border: unset !important;
    border-radius: unset !important;
    box-shadow: unset !important;
    transition: unset !important;
    transform: unset !important;
}

/* Override global text styles */
.voting-page p,
.voting-page div,
.voting-page span,
.voting-page li,
.voting-page .text-muted,
.voting-page .text-light,
.voting-page small,
.voting-page .small {
    color: unset !important;
}

/* Override global alert styles */
.voting-page .alert,
.voting-page .alert-success,
.voting-page .alert-warning,
.voting-page .alert-danger,
.voting-page .alert-info {
    background: unset !important;
    border: unset !important;
    color: unset !important;
    border-radius: unset !important;
}

/* Override global background classes */
.voting-page .bg-white,
.voting-page .bg-light {
    background: unset !important;
    backdrop-filter: unset !important;
    border: unset !important;
}

/* Override global container styles */
.voting-page .container,
.voting-page .container-fluid {
    background: unset !important;
    backdrop-filter: unset !important;
    border: unset !important;
    border-radius: unset !important;
    box-shadow: unset !important;
}

/* Dark Masculine Color Scheme - Enhanced Specificity */
.voting-page {
    --bg-primary: #0f0f0f;
    --bg-secondary: #1a1a1a;
    --bg-card: #252525;
    --bg-accent: #2d2d2d;
    --text-primary: #ffffff;
    --text-secondary: #b8b8b8;
    --text-muted: #8a8a8a;
    --accent-green: #00ff88;
    --accent-red: #ff4757;
    --accent-blue: #3742fa;
    --accent-orange: #ff6348;
    --accent-purple: #7d5fff;
    --border-color: #3a3a3a;
    --shadow-dark: 0 8px 32px rgba(0, 0, 0, 0.6);
    --shadow-light: 0 4px 16px rgba(0, 0, 0, 0.3);
}

.voting-page .main-container {
    background: rgba(26, 26, 26, 0.95);
    border-radius: 16px;
    box-shadow: var(--shadow-dark);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-color);
    padding: 2rem;
    margin: 1rem;
    min-height: calc(100vh - 2rem);
}

/* Alert Messages */
.voting-page .custom-alert {
    border: none;
    border-radius: 12px;
    font-weight: 500;
    box-shadow: var(--shadow-light);
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
}

.voting-page .custom-alert.alert-success {
    background: linear-gradient(135deg, rgba(0, 255, 136, 0.15) 0%, rgba(0, 255, 136, 0.05) 100%);
    color: var(--accent-green);
    border-left: 4px solid var(--accent-green);
}

.voting-page .custom-alert.alert-warning {
    background: linear-gradient(135deg, rgba(255, 99, 72, 0.15) 0%, rgba(255, 99, 72, 0.05) 100%);
    color: var(--accent-orange);
    border-left: 4px solid var(--accent-orange);
}

/* Headers */
.voting-page h1,
.voting-page h2,
.voting-page h3,
.voting-page h4,
.voting-page h5 {
    color: var(--text-primary) !important;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.voting-page .page-title {
    background: linear-gradient(135deg, var(--accent-green) 0%, var(--accent-blue) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.voting-page .custom-text-muted {
    color: var(--text-muted) !important;
}

/* Banner/Header Image */
.voting-page .banner-placeholder {
    background: linear-gradient(135deg, var(--bg-accent) 0%, var(--bg-card) 100%);
    border: 2px dashed var(--border-color);
    border-radius: 16px;
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.voting-page .banner-placeholder::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: conic-gradient(from 0deg, transparent, rgba(0, 255, 136, 0.1), transparent);
    animation: rotate 6s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Machine Info Card */
.voting-page .machine-info-card {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-accent) 100%);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: var(--shadow-light);
    overflow: hidden;
    position: relative;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.voting-page .machine-info-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent-green) 0%, var(--accent-blue) 50%, var(--accent-purple) 100%);
}

.voting-page .machine-info-card .card-title {
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    font-size: 1.25rem;
    font-weight: 600;
}

/* Item Cards */
.voting-page .item-card {
    background: linear-gradient(135deg, var(--bg-card) 0%, rgba(37, 37, 37, 0.8) 100%);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: var(--shadow-light);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    position: relative;
    margin-bottom: 1rem;
    padding: 1.5rem;
}

.voting-page .item-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-dark);
    border-color: var(--accent-green);
}

.voting-page .item-card.promotion-card {
    border-color: var(--accent-green);
    background: linear-gradient(135deg, rgba(0, 255, 136, 0.05) 0%, var(--bg-card) 100%);
}

.voting-page .item-card.promotion-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--accent-green);
}

.voting-page .item-card .card-title {
    color: var(--text-primary);
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.voting-page .item-card .card-text {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* Badges */
.voting-page .custom-badge {
    border-radius: 8px;
    font-weight: 600;
    letter-spacing: 0.02em;
    padding: 0.5em 0.75em;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
}

.voting-page .custom-badge.badge-primary {
    background: linear-gradient(135deg, var(--accent-blue) 0%, var(--accent-purple) 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(55, 66, 250, 0.3);
}

.voting-page .custom-badge.badge-success {
    background: linear-gradient(135deg, var(--accent-green) 0%, #00d084 100%);
    color: #000;
    box-shadow: 0 2px 8px rgba(0, 255, 136, 0.3);
}

.voting-page .custom-badge.promotion-badge {
    background: linear-gradient(135deg, var(--accent-green) 0%, #00d084 100%);
    color: #000;
    font-weight: 700;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* Vote Buttons */
.voting-page .vote-buttons {
    display: flex;
    gap: 12px;
    width: 100%;
}

.voting-page .vote-btn {
    flex: 1;
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 1rem;
    font-size: 1.2rem;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    background: var(--bg-accent);
    color: var(--text-secondary);
    cursor: pointer;
}

.voting-page .vote-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.5s;
}

.voting-page .vote-btn:hover::before {
    left: 100%;
}

.voting-page .vote-btn-up {
    background: linear-gradient(135deg, rgba(0, 255, 136, 0.1) 0%, var(--bg-accent) 100%);
    border-color: var(--accent-green);
    color: var(--accent-green);
}

.voting-page .vote-btn-up:hover {
    background: linear-gradient(135deg, var(--accent-green) 0%, #00d084 100%);
    color: #000;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 255, 136, 0.4);
}

.voting-page .vote-btn-down {
    background: linear-gradient(135deg, rgba(255, 71, 87, 0.1) 0%, var(--bg-accent) 100%);
    border-color: var(--accent-red);
    color: var(--accent-red);
}

.voting-page .vote-btn-down:hover {
    background: linear-gradient(135deg, var(--accent-red) 0%, #ff3838 100%);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 71, 87, 0.4);
}

/* Vote Counts */
.voting-page .vote-counts {
    margin-top: 0.75rem;
    padding: 0.5rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 8px;
    text-align: center;
    font-size: 0.85rem;
    color: var(--text-muted);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.voting-page .vote-count-up {
    color: var(--accent-green);
    font-weight: 600;
}

.voting-page .vote-count-down {
    color: var(--accent-red);
    font-weight: 600;
}

/* Error State */
.voting-page .error-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-secondary);
}

.voting-page .error-state .bi {
    font-size: 4rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    opacity: 0.6;
}

.voting-page .error-state h2 {
    color: var(--text-primary);
    margin-bottom: 1rem;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .voting-page .main-container {
        margin: 0.5rem;
        border-radius: 12px;
        padding: 1rem;
    }
    
    .voting-page .page-title {
        font-size: 1.5rem;
    }
    
    .voting-page .item-card {
        padding: 1rem;
    }
    
    .voting-page .vote-btn {
        padding: 0.75rem;
        font-size: 1rem;
    }
    
    .voting-page .custom-badge {
        font-size: 0.7rem;
        padding: 0.4em 0.6em;
    }
}

/* Dark scrollbar */
.voting-page ::-webkit-scrollbar {
    width: 8px;
}

.voting-page ::-webkit-scrollbar-track {
    background: var(--bg-secondary);
}

.voting-page ::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 4px;
}

.voting-page ::-webkit-scrollbar-thumb:hover {
    background: var(--accent-green);
}

/* Banner hover effect */
.voting-page .banner-image:hover {
    transform: scale(1.02);
}

/* Override navbar for voting page */
.voting-page .navbar,
.voting-page .navbar-nav,
.voting-page .nav-link {
    display: none !important;
}

/* Override footer for voting page */
.voting-page .footer {
    display: none !important;
}

/* Override main layout */
.voting-page main {
    padding-top: 0 !important;
}

.voting-page .container-fluid {
    padding: 0 !important;
}
</style>

<script>
// Add voting-page class to html element to enable our specific styles
document.documentElement.classList.add('voting-page');
document.body.classList.add('voting-page');
</script>

<div class="main-container">
    <?php if ($message): ?>
        <div class="custom-alert alert-<?php echo $message_type; ?>" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close" style="filter: invert(1);"></button>
        </div>
    <?php endif; ?>

    <?php if ($qr_code): ?>
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="page-title mb-2"><?php echo htmlspecialchars($qr_code['campaign_name']); ?></h1>
                <p class="custom-text-muted mb-0"><?php echo htmlspecialchars($qr_code['business_name']); ?></p>
            </div>
        </div>

        <?php if (!empty($qr_code['header_image'])): ?>
            <div class="mb-4 text-center">
                <img src="<?php echo htmlspecialchars($qr_code['header_image']); ?>" alt="Header Image" class="img-fluid banner-image" style="max-height:300px; width:100%; object-fit:cover; border-radius: 16px; box-shadow: var(--shadow-light); cursor: pointer; transition: transform 0.3s ease;" onclick="openBannerModal(this.src)">
            </div>
            
            <!-- Banner Modal for Full Size View -->
            <div id="bannerModal" class="banner-modal" onclick="closeBannerModal()" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.9); cursor: pointer;">
                <img id="bannerModalImg" src="" alt="Full Size Banner" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 95%; max-height: 95%; object-fit: contain;">
            </div>
        <?php else: ?>
            <div class="mb-4 banner-placeholder">
                <span class="custom-text-muted" style="z-index: 1; position: relative; font-weight: 500;">Your Banner Here</span>
            </div>
        <?php endif; ?>

        <?php if ($machine_info): ?>
            <div class="machine-info-card">
                <h5 class="card-title mb-3">
                    <i class="bi bi-geo-alt-fill me-2" style="color: var(--accent-green);"></i>
                    Machine Information
                </h5>
                            <div class="row">
                                <div class="col-6 mb-2">
                        <small class="custom-text-muted d-block">Machine Name</small>
                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($machine_info['name']); ?></strong>
                                </div>
                                <div class="col-6 mb-2">
                        <small class="custom-text-muted d-block">Location</small>
                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($machine_info['location']); ?></strong>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <?php foreach ($items as $item): ?>
                <div class="col-12">
                    <div class="item-card <?php if (!empty($item['promotion'])) echo 'promotion-card'; ?>">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <h5 class="card-title mb-1">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                        <?php if (!empty($item['promotion'])): ?>
                                        <span class="custom-badge promotion-badge ms-2">
                                            <i class="bi bi-lightning-fill me-1"></i>Promotion
                                        </span>
                                        <?php endif; ?>
                                    </h5>
                                <p class="card-text mb-2">
                                    <i class="bi bi-tag-fill me-1" style="color: var(--accent-blue);"></i>
                                        <?php echo htmlspecialchars($item['item_category']); ?>
                                    </p>
                                    <div class="d-flex align-items-center">
                                    <span class="custom-badge badge-primary me-2">
                                        <i class="bi bi-currency-dollar me-1"></i><?php echo number_format($item['retail_price'], 2); ?>
                                    </span>
                                        <?php if ($item['high_margin']): ?>
                                        <span class="custom-badge badge-success">
                                            <i class="bi bi-star-fill me-1"></i>High Margin
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="campaign_id" value="<?php echo $qr_code['campaign_id']; ?>">
                                    <input type="hidden" name="qr_code_id" value="<?php echo $qr_code['id']; ?>">
                                    <div class="vote-buttons">
                                        <button type="submit" name="vote_type" value="in" class="vote-btn vote-btn-up">
                                            <i class="bi bi-hand-thumbs-up-fill"></i>
                                            </button>
                                        <button type="submit" name="vote_type" value="out" class="vote-btn vote-btn-down">
                                            <i class="bi bi-hand-thumbs-down-fill"></i>
                                            </button>
                                        </div>
                                    </form>
                                <div class="vote-counts">
                                    <span class="vote-count-up"><?php echo $item['vote_in_count']; ?></span>
                                    <span class="custom-text-muted mx-2">•</span>
                                    <span class="vote-count-down"><?php echo $item['vote_out_count']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="error-state">
            <i class="bi bi-qr-code"></i>
            <h2 class="h4 mb-3">Invalid or Expired QR Code</h2>
            <p class="custom-text-muted">Please scan a valid QR code to vote for items.</p>
        </div>
    <?php endif; ?>
</div>

<script>
// Enhanced vote functionality with animations
document.addEventListener('DOMContentLoaded', function() {
    // Add click animations to vote buttons
    document.querySelectorAll('.vote-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Create ripple effect
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Add CSS for ripple effect
    const style = document.createElement('style');
    style.textContent = `
        .voting-page .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

    // Function to update vote counts with smooth animations
function updateVoteCounts() {
        const items = document.querySelectorAll('.item-card');
        items.forEach(card => {
            const itemIdInput = card.querySelector('input[name="item_id"]');
            const campaignIdInput = card.querySelector('input[name="campaign_id"]');
            
            if (itemIdInput && campaignIdInput) {
                const itemId = itemIdInput.value;
                const campaignId = campaignIdInput.value;
        
        fetch(`<?php echo APP_URL; ?>/core/get-vote-counts.php?item_id=${itemId}&campaign_id=${campaignId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                            const upCount = card.querySelector('.vote-count-up');
                            const downCount = card.querySelector('.vote-count-down');
                            
                            if (upCount && data.vote_in_count !== upCount.textContent) {
                                upCount.style.transform = 'scale(1.2)';
                                upCount.textContent = data.vote_in_count;
                                setTimeout(() => {
                                    upCount.style.transform = 'scale(1)';
                                }, 200);
                            }
                            
                            if (downCount && data.vote_out_count !== downCount.textContent) {
                                downCount.style.transform = 'scale(1.2)';
                                downCount.textContent = data.vote_out_count;
                                setTimeout(() => {
                                    downCount.style.transform = 'scale(1)';
                                }, 200);
                            }
                }
            })
            .catch(error => console.error('Error updating vote counts:', error));
            }
    });
}

// Update vote counts every 30 seconds
setInterval(updateVoteCounts, 30000);

    // Smooth transition for vote counts
    document.querySelectorAll('.vote-count-up, .vote-count-down').forEach(el => {
        el.style.transition = 'transform 0.2s ease';
    });
    
    // Banner modal functionality for full size image viewing
    window.openBannerModal = function(imageSrc) {
        const modal = document.getElementById('bannerModal');
        const modalImg = document.getElementById('bannerModalImg');
        
        modalImg.src = imageSrc;
        modal.style.display = 'block';
        
        // Prevent body scroll when modal is open
        document.body.style.overflow = 'hidden';
    }

    window.closeBannerModal = function() {
        const modal = document.getElementById('bannerModal');
        modal.style.display = 'none';
        
        // Restore body scroll
        document.body.style.overflow = 'auto';
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeBannerModal();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 