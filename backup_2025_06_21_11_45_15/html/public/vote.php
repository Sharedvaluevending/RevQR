<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/auto_login.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

$message = '';
$message_type = '';
$qr_code = null;
$campaign = null;
$items = [];
$machine_info = null;

// Simple weekly vote limit system (2 votes per week) - matching main vote page
$user_id = $_SESSION['user_id'] ?? null;
$voter_ip = $_SERVER['REMOTE_ADDR'];

// Get user's QR balance if logged in
$user_qr_balance = 0;
if ($user_id) {
    try {
        $user_qr_balance = QRCoinManager::getBalance($user_id);
    } catch (Exception $e) {
        error_log("Error getting QR balance: " . $e->getMessage());
        $user_qr_balance = 0;
    }
}

// Get user's weekly vote count
$weekly_votes_used = 0;
$weekly_vote_limit = 2;

if ($user_id) {
    // For logged-in users: Count by user_id
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as weekly_votes
        FROM votes 
        WHERE user_id = ? 
        AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
    ");
    $stmt->execute([$user_id]);
    $weekly_votes_used = (int) $stmt->fetchColumn();
} else {
    // For guest users: Count by IP
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as weekly_votes
        FROM votes 
        WHERE voter_ip = ? 
        AND user_id IS NULL
        AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
    ");
    $stmt->execute([$voter_ip]);
    $weekly_votes_used = (int) $stmt->fetchColumn();
}

$votes_remaining = max(0, $weekly_vote_limit - $weekly_votes_used);

// Simple vote status for display
$vote_status = [
    'votes_used' => $weekly_votes_used,
    'votes_remaining' => $votes_remaining,
    'weekly_limit' => $weekly_vote_limit,
    'qr_balance' => $user_qr_balance
];

// Get QR code details - updated to match main vote page structure
if (isset($_GET['qr'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT qr.*, c.*, b.name as business_name
            FROM qr_codes qr
            JOIN campaigns c ON qr.campaign_id = c.id
            JOIN businesses b ON c.business_id = b.id
            WHERE qr.code = ? AND c.status = 'active'
        ");
        $stmt->execute([$_GET['qr']]);
        $qr_code = $stmt->fetch();
    } catch (Exception $e) {
        error_log("QR Code lookup error: " . $e->getMessage());
        $qr_code = null;
    }
    
    if ($qr_code) {
        // QR code scan tracking - enhanced like main vote page
        try {
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $device_type = 'Unknown';
            $browser = 'Unknown';
            $os = 'Unknown';
            
            // Device detection
            if (preg_match('/Mobile|Android|iPhone/', $user_agent)) {
                $device_type = 'Mobile';
            } elseif (preg_match('/Tablet|iPad/', $user_agent)) {
                $device_type = 'Tablet';
            } else {
                $device_type = 'Desktop';
            }
            
            // Browser detection
            if (preg_match('/Chrome/i', $user_agent)) {
                $browser = 'Chrome';
            } elseif (preg_match('/Firefox/i', $user_agent)) {
                $browser = 'Firefox';
            } elseif (preg_match('/Safari/i', $user_agent)) {
                $browser = 'Safari';
            } elseif (preg_match('/Edge/i', $user_agent)) {
                $browser = 'Edge';
            }
            
            // OS detection
            if (preg_match('/Windows/i', $user_agent)) {
                $os = 'Windows';
            } elseif (preg_match('/Mac/i', $user_agent)) {
                $os = 'macOS';
            } elseif (preg_match('/Android/i', $user_agent)) {
                $os = 'Android';
            } elseif (preg_match('/iOS/i', $user_agent)) {
                $os = 'iOS';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO qr_code_stats (
                    qr_code_id, scan_time, ip_address, user_agent, 
                    referrer, device_type, browser, os, location
                ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $qr_code['id'],
                $_SERVER['REMOTE_ADDR'] ?? '',
                $user_agent,
                $_SERVER['HTTP_REFERER'] ?? '',
                $device_type,
                $browser,
                $os,
                'Unknown'
            ]);
        } catch (Exception $e) {
            error_log("QR tracking error: " . $e->getMessage());
        }
        
        // Get campaign data
        $campaign = [
            'id' => $qr_code['campaign_id'],
            'business_id' => $qr_code['business_id'],
            'name' => $qr_code['campaign_name'],
            'description' => $qr_code['campaign_description'],
            'status' => $qr_code['status']
        ];
        
        // Get items with vote counts - updated to match main vote page
        try {
            $stmt = $pdo->prepare("
                SELECT i.*, 
                       COUNT(CASE WHEN v.vote_type = 'vote_in' AND v.campaign_id = ? THEN 1 END) as votes_in,
                       COUNT(CASE WHEN v.vote_type = 'vote_out' AND v.campaign_id = ? THEN 1 END) as votes_out
                FROM items i
                LEFT JOIN votes v ON i.id = v.item_id
                JOIN campaign_items ci ON i.id = ci.item_id
                WHERE ci.campaign_id = ? AND i.status = 'active'
                GROUP BY i.id
                ORDER BY i.item_name ASC
            ");
            $stmt->execute([$campaign['id'], $campaign['id'], $campaign['id']]);
            $items = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting items: " . $e->getMessage());
        }
        
        // Get machine info
        $machine_info = [
            'name' => $qr_code['machine_name'] ?? 'Unknown Machine',
            'location' => $qr_code['machine_location'] ?? 'Unknown Location',
            'total_scans' => 0
        ];
    }
}

// Handle vote submission with enhanced voting structure - matching main vote page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote'])) {
    $item_id = (int)$_POST['item_id'];
    $vote_type = $_POST['vote_type'];
    $campaign_id = isset($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : null;
    $vote_method = $_POST['vote_method'] ?? 'auto';
    
    // Validate campaign
    $valid = false;
    if ($campaign_id) {
        $stmt = $pdo->prepare("
            SELECT c.id, c.business_id
            FROM campaigns c
            WHERE c.id = ? AND c.status = 'active'
        ");
        $stmt->execute([$campaign_id]);
        $valid = $stmt->fetch();
    }
    
    if ($valid) {
        // Simple weekly vote limit system (2 votes per week)
        if ($vote_status['votes_remaining'] <= 0) {
            $message = "You have used all your votes for this week. You get 2 votes per week total.";
            $message_type = "warning";
        } else {
            // Check if already voted for this item this week
            $already_voted = false;
            if ($user_id) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM votes 
                    WHERE item_id = ? AND user_id = ?
                    AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
                ");
                $stmt->execute([$item_id, $user_id]);
                $already_voted = $stmt->fetchColumn() > 0;
            } else {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM votes 
                    WHERE item_id = ? AND voter_ip = ? AND user_id IS NULL
                    AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
                ");
                $stmt->execute([$item_id, $voter_ip]);
                $already_voted = $stmt->fetchColumn() > 0;
            }
            
            if ($already_voted) {
                $message = "You have already voted for this item this week.";
                $message_type = "warning";
            } else {
                // Record the vote
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO votes (item_id, vote_type, voter_ip, user_id, campaign_id, machine_id, user_agent, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $item_id,
                        $vote_type,
                        $voter_ip,
                        $user_id,
                        $campaign_id,
                        0, // machine_id not available in public page
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    // Award QR coins for voting (30 coins per vote) - matching main vote page
                    if ($user_id) {
                        QRCoinManager::addTransaction($user_id, 30, 'Vote cast for item', 'vote');
                        // Update user's QR balance after transaction
                        try {
                            $user_qr_balance = QRCoinManager::getBalance($user_id);
                        } catch (Exception $e) {
                            error_log("Error updating QR balance after vote: " . $e->getMessage());
                        }
                    }
                    
                    $message = "Vote successfully recorded! You earned 30 QR coins.";
                    $message_type = "success";
                    
                    // Update vote status
                    $weekly_votes_used++;
                    $votes_remaining--;
                    $vote_status = [
                        'votes_used' => $weekly_votes_used,
                        'votes_remaining' => $votes_remaining,
                        'weekly_limit' => $weekly_vote_limit,
                        'qr_balance' => $user_qr_balance
                    ];
            
                    // Refresh items list with updated counts
                    if ($campaign) {
                        try {
                            $stmt = $pdo->prepare("
                                SELECT i.*, 
                                       COUNT(CASE WHEN v.vote_type = 'vote_in' AND v.campaign_id = ? THEN 1 END) as votes_in,
                                       COUNT(CASE WHEN v.vote_type = 'vote_out' AND v.campaign_id = ? THEN 1 END) as votes_out
                                FROM items i
                                LEFT JOIN votes v ON i.id = v.item_id
                                JOIN campaign_items ci ON i.id = ci.item_id
                                WHERE ci.campaign_id = ? AND i.status = 'active'
                                GROUP BY i.id
                                ORDER BY i.item_name ASC
                            ");
                            $stmt->execute([$campaign['id'], $campaign['id'], $campaign['id']]);
                            $items = $stmt->fetchAll();
                        } catch (Exception $e) {
                            error_log("Error refreshing items: " . $e->getMessage());
                        }
                    }
                    
                } catch (Exception $e) {
                    error_log("Vote recording error: " . $e->getMessage());
                    $message = "Error recording vote. Please try again.";
                    $message_type = "danger";
                }
            }
        }
    } else {
        $message = "Invalid campaign.";
        $message_type = "danger";
    }
}

// Add error messages for debugging
if (!$qr_code && isset($_GET['qr'])) {
    $message = "QR code not found or inactive. Please check your QR code.";
    $message_type = "warning";
} elseif (empty($items) && $campaign) {
    $message = "No items available for voting in this campaign.";
    $message_type = "info";
}

require_once __DIR__ . '/../core/includes/header.php';

// Get promotional ads for rotating ad spots - matching main vote page
require_once __DIR__ . '/../core/promotional_ads_manager.php';
$adsManager = new PromotionalAdsManager($pdo);
$promotional_ads = $adsManager->getAdsForPage('vote', 2);

// Track ad views for analytics
foreach ($promotional_ads as $ad) {
    $adsManager->trackView($ad['id'], $user_id, 'vote');
}
?>

<style>
    /* VOTE PAGE SPECIFIC STYLES - matching main vote page */
    
    /* Override navbar hiding for vote page */
    .vote-page .navbar {
        display: block !important;
    }
    
    /* Dark theme for voting page */
    body {
        background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
        color: #ffffff;
        font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    }
    
    .voting-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
    }
    
    .voting-card {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }
    
    .item-card {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    
    .item-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        border-color: rgba(255, 255, 255, 0.2);
    }
    
    .btn-vote-in {
        background: linear-gradient(135deg, #00ff88 0%, #00d084 100%);
        border: none;
        color: #000;
        font-weight: 600;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .btn-vote-in:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 255, 136, 0.4);
        color: #000;
    }
    
    .btn-vote-out {
        background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .btn-vote-out:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 71, 87, 0.4);
        color: #fff;
    }
    
    .promotional-ad {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    
    .promotional-ad:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }
    
    .vote-status {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .alert {
        border-radius: 8px;
        border: none;
    }
    
    .alert-success {
        background: rgba(0, 255, 136, 0.2);
        color: #00ff88;
        border-left: 4px solid #00ff88;
    }
    
    .alert-warning {
        background: rgba(255, 193, 7, 0.2);
        color: #ffc107;
        border-left: 4px solid #ffc107;
    }
    
    .alert-danger {
        background: rgba(255, 71, 87, 0.2);
        color: #ff4757;
        border-left: 4px solid #ff4757;
    }
</style>

<div class="voting-container">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Vote Status Display -->
    <div class="voting-card">
        <div class="vote-status">
            <div class="row text-center">
                <div class="col-md-3">
                    <h4 class="text-success"><?php echo $vote_status['votes_remaining']; ?></h4>
                    <small class="text-muted">Votes Remaining</small>
                </div>
                <div class="col-md-3">
                    <h4 class="text-info"><?php echo $vote_status['votes_used']; ?></h4>
                    <small class="text-muted">Votes Used</small>
                </div>
                <div class="col-md-3">
                    <h4 class="text-warning"><?php echo $vote_status['weekly_limit']; ?></h4>
                    <small class="text-muted">Weekly Limit</small>
                </div>
                <?php if ($user_id): ?>
                <div class="col-md-3">
                    <h4 class="text-primary"><?php echo number_format($vote_status['qr_balance']); ?></h4>
                    <small class="text-muted">QR Coins</small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Promotional Ads -->
    <?php if (!empty($promotional_ads)): ?>
    <div class="voting-card">
        <h4 class="text-primary mb-3">
            <i class="bi bi-megaphone me-2"></i>Featured Promotions
        </h4>
        <div class="row">
            <?php foreach ($promotional_ads as $ad): ?>
            <div class="col-md-6 mb-3">
                <div class="promotional-ad" style="background: <?php echo $ad['background_color']; ?>; color: <?php echo $ad['text_color']; ?>;">
                    <h5><?php echo htmlspecialchars($ad['ad_title']); ?></h5>
                    <p><?php echo htmlspecialchars($ad['ad_description']); ?></p>
                    <?php if ($ad['ad_cta_url']): ?>
                    <a href="<?php echo htmlspecialchars($ad['ad_cta_url']); ?>" 
                       class="btn btn-light btn-sm"
                       onclick="trackAdClick(<?php echo $ad['id']; ?>)">
                        <?php echo htmlspecialchars($ad['ad_cta_text']); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Voting Interface -->
    <?php if ($qr_code && $campaign): ?>
        <div class="voting-card">
            <h4 class="text-light mb-3">
                <i class="bi bi-ballot me-2"></i>
                Vote for Items - <?php echo htmlspecialchars($campaign['name']); ?>
            </h4>
            
            <?php if ($machine_info): ?>
            <div class="mb-3">
                <small class="text-muted">
                    <i class="bi bi-geo-alt me-1"></i>
                    <?php echo htmlspecialchars($machine_info['name']); ?> - 
                    <?php echo htmlspecialchars($machine_info['location']); ?>
                </small>
            </div>
            <?php endif; ?>

            <div class="voting-card">
                <?php if (!empty($items)): ?>
                    <div class="row">
                        <?php foreach ($items as $item): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="item-card">
                                    <h5 class="text-light mb-3"><?php echo htmlspecialchars($item['item_name']); ?></h5>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="text-center">
                                            <div class="text-success h4 mb-0">
                                                <i class="bi bi-arrow-up-circle-fill me-1"></i>
                                                <?php echo $item['votes_in']; ?>
                                            </div>
                                            <small class="text-muted">Vote In</small>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-danger h4 mb-0">
                                                <i class="bi bi-arrow-down-circle-fill me-1"></i>
                                                <?php echo $item['votes_out']; ?>
                                            </div>
                                            <small class="text-muted">Vote Out</small>
                                        </div>
                                    </div>

                                    <!-- Enhanced Voting Options -->
                                    <div class="voting-options">
                                        <!-- Smart Vote (Auto-Select Best Option) -->
                                        <?php if ($vote_status['votes_remaining'] > 0): ?>
                                            <div class="row mb-2">
                                                <div class="col-6">
                                                    <form method="post" class="d-inline w-100">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <input type="hidden" name="vote_type" value="vote_in">
                                                        <input type="hidden" name="vote_method" value="auto">
                                                        <?php if ($campaign): ?>
                                                            <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                        <?php endif; ?>
                                                        <button type="submit" name="vote" class="btn btn-vote-in w-100">
                                                            <i class="bi bi-hand-thumbs-up me-2"></i>Vote In
                                                            <span class="small">(+30 coins)</span>
                                                        </button>
                                                    </form>
                                                </div>
                                                <div class="col-6">
                                                    <form method="post" class="d-inline w-100">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <input type="hidden" name="vote_type" value="vote_out">
                                                        <input type="hidden" name="vote_method" value="auto">
                                                        <?php if ($campaign): ?>
                                                            <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                        <?php endif; ?>
                                                        <button type="submit" name="vote" class="btn btn-vote-out w-100">
                                                            <i class="bi bi-hand-thumbs-down me-2"></i>Vote Out
                                                            <span class="small">(+30 coins)</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- No Votes Available -->
                                        <?php if ($vote_status['votes_remaining'] == 0): ?>
                                            <div class="text-center">
                                                <div class="alert alert-warning py-2 mb-2">
                                                    <i class="bi bi-clock me-2"></i>
                                                    <strong>No free votes remaining</strong>
                                                    <br>
                                                    <small>Come back next week for your free votes!</small>
                                                </div>
                                                <?php if ($user_id): ?>
                                                    <small class="text-muted">
                                                        Earn more QR coins by spinning or purchase premium votes
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">
                                                        <a href="<?php echo APP_URL; ?>/user/register.php" class="text-warning">Sign up</a> to unlock QR coins and premium voting!
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
                        <h4 class="text-muted mt-3">No Items Available</h4>
                        <p class="text-muted">This campaign doesn't have any items yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- No QR Code or Campaign -->
        <div class="voting-card text-center">
            <i class="bi bi-qr-code-scan text-muted" style="font-size: 4rem;"></i>
            <h3 class="text-muted mt-3">Scan QR Code to Vote</h3>
            <p class="text-muted">
                Find a RevenueQR-enabled vending machine and scan the QR code to start voting on items.
            </p>
            <?php if (!$user_id): ?>
                <div class="mt-4">
                    <a href="<?php echo APP_URL; ?>/user/register.php" class="btn btn-warning btn-lg me-2">
                        <i class="bi bi-person-plus me-1"></i>Register
                    </a>
                    <a href="<?php echo APP_URL; ?>/login.php" class="btn btn-outline-light btn-lg">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Track promotional ad clicks
    function trackAdClick(adId) {
        fetch('<?php echo APP_URL; ?>/api/track-ad-click.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                ad_id: adId,
                user_id: <?php echo $user_id ?? 'null'; ?>
            })
        }).catch(error => {
            console.log('Ad tracking error:', error);
        });
    }

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // AJAX Voting System - No Page Reload Required - matching main vote page
    document.addEventListener('DOMContentLoaded', function() {
        // Convert all voting forms to AJAX
        const voteForms = document.querySelectorAll('form[method="post"]');
        voteForms.forEach(form => {
            if (form.querySelector('button[name="vote"]')) {
                form.addEventListener('submit', handleVoteSubmission);
            }
        });
        
        // Start real-time vote count updates
        setInterval(updateAllVoteCounts, 5000); // Update every 5 seconds
        
        // Add animation to cards
        const cards = document.querySelectorAll('.item-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('animate__fadeInUp');
        });
    });

    async function handleVoteSubmission(event) {
        event.preventDefault();
        
        const form = event.target;
        const submitButton = form.querySelector('button[name="vote"]');
        const originalButtonText = submitButton.innerHTML;
        
        // Disable button and show loading state
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Voting...';
        
        try {
            const formData = new FormData(form);
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const text = await response.text();
            
            // Parse the response to extract the result
            const parser = new DOMParser();
            const doc = parser.parseFromString(text, 'text/html');
            const alert = doc.querySelector('.alert');
            
            if (alert) {
                const alertType = alert.classList.contains('alert-success') ? 'success' : 
                                 alert.classList.contains('alert-warning') ? 'warning' : 
                                 alert.classList.contains('alert-info') ? 'info' : 'danger';
                const alertText = alert.textContent.trim();
                
                // Show toast notification instead of page reload
                showVoteToast(alertText, alertType);
                
                if (alertType === 'success') {
                    // Reload page to ensure proper vote count and status updates
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500); // Give user time to see the success message
                    
                    // Add success animation to the voted item
                    const itemCard = form.closest('.item-card');
                    if (itemCard) {
                        itemCard.style.transform = 'scale(1.02)';
                        itemCard.style.boxShadow = '0 8px 25px rgba(0,255,0,0.2)';
                        setTimeout(() => {
                            itemCard.style.transform = '';
                            itemCard.style.boxShadow = '';
                        }, 500);
                    }
                }
            }
            
        } catch (error) {
            console.error('Vote submission error:', error);
            showVoteToast('Network error. Please try again.', 'danger');
        } finally {
            // Re-enable button
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    }

    function showVoteToast(message, type) {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
        
        const icon = type === 'success' ? 'check-circle' : 
                    type === 'warning' ? 'exclamation-triangle' : 
                    type === 'info' ? 'info-circle' : 'x-circle';
        
        toast.innerHTML = `
            <i class="bi bi-${icon} me-2"></i>
            ${message}
            <button type="button" class="btn-close btn-close-white" onclick="this.parentElement.remove()"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto-remove after 4 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 4000);
    }

    async function updateAllVoteCounts() {
        const items = document.querySelectorAll('.item-card');
        const updates = [];
        
        items.forEach(async (card) => {
            const form = card.querySelector('form');
            if (form) {
                const itemId = form.querySelector('input[name="item_id"]')?.value;
                const campaignId = form.querySelector('input[name="campaign_id"]')?.value;
                
                if (itemId) {
                    updates.push(updateItemVoteCount(card, itemId, campaignId));
                }
            }
        });
        
        await Promise.all(updates);
    }

    async function updateItemVoteCount(card, itemId, campaignId) {
        try {
            let url = `<?php echo APP_URL; ?>/core/get-vote-counts.php?item_id=${itemId}`;
            if (campaignId) url += `&campaign_id=${campaignId}`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                const upCount = card.querySelector('.text-success.h4');
                const downCount = card.querySelector('.text-danger.h4');
                
                if (upCount && data.vote_in_count !== parseInt(upCount.textContent)) {
                    animateCountUpdate(upCount, data.vote_in_count);
                }
                
                if (downCount && data.vote_out_count !== parseInt(downCount.textContent)) {
                    animateCountUpdate(downCount, data.vote_out_count);
                }
            }
        } catch (error) {
            console.error('Error updating vote count for item', itemId, error);
        }
    }

    function animateCountUpdate(element, newValue) {
        element.style.transform = 'scale(1.3)';
        element.style.color = '#ffd700';
        element.textContent = newValue;
        
        setTimeout(() => {
            element.style.transform = 'scale(1)';
            element.style.color = '';
        }, 300);
    }

    // Add smooth transitions
    const cards = document.querySelectorAll('.item-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('animate__fadeInUp');
    });
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 