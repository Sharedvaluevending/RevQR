<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/auto_login.php';
require_once __DIR__ . '/core/services/VotingService.php';

// Initialize voting service
VotingService::init($pdo);

$message = '';
$message_type = '';
$qr_data = null;
$list = null;
$items = [];
$campaign = null;
$spin_wheel = null;

// Get current user's vote status
$user_id = $_SESSION['user_id'] ?? null;
$voter_ip = $_SERVER['REMOTE_ADDR'];
$vote_status = VotingService::getUserVoteStatus($user_id, $voter_ip);

// Get QR code data by code parameter (legacy)
if (isset($_GET['code'])) {
    $stmt = $pdo->prepare("
        SELECT qr.*, vl.name as list_name, vl.description as list_description,
               b.name as business_name, c.name as campaign_name
        FROM qr_codes qr
        LEFT JOIN voting_lists vl ON qr.machine_id = vl.id
        LEFT JOIN campaigns c ON qr.campaign_id = c.id
        LEFT JOIN businesses b ON COALESCE(vl.business_id, c.business_id) = b.id
        WHERE qr.code = ?
    ");
    $stmt->execute([$_GET['code']]);
    $qr_data = $stmt->fetch();
    
    if ($qr_data) {
        // QR code found and validated
        // --- ENHANCED QR SCAN TRACKING ---
        try {
            // Track in the new qr_code_stats table
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
                $qr_data['id'],
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
        
        // If QR code is linked to a campaign, get voting lists via campaign_voting_lists
        if ($qr_data['campaign_id']) {
            $stmt = $pdo->prepare("
                SELECT c.id as campaign_id, c.business_id as campaign_business_id, c.name as campaign_name, 
                       c.description as campaign_description, c.start_date, c.end_date, c.status, c.campaign_type,
                       vl.id as list_id, vl.business_id as list_business_id, vl.name as list_name, 
                       vl.location, vl.description as list_description, vl.created_at as list_created_at
                FROM campaigns c
                LEFT JOIN campaign_voting_lists cvl ON c.id = cvl.campaign_id
                LEFT JOIN voting_lists vl ON cvl.voting_list_id = vl.id
                WHERE c.id = ?
            ");
            $stmt->execute([$qr_data['campaign_id']]);
            $campaign_data = $stmt->fetch();
            if ($campaign_data) {
                // Create separate campaign and list arrays with proper IDs
                $campaign = [
                    'id' => $campaign_data['campaign_id'],
                    'business_id' => $campaign_data['campaign_business_id'],
                    'name' => $campaign_data['campaign_name'],
                    'description' => $campaign_data['campaign_description'],
                    'start_date' => $campaign_data['start_date'],
                    'end_date' => $campaign_data['end_date'],
                    'status' => $campaign_data['status'],
                    'campaign_type' => $campaign_data['campaign_type']
                ];
                $list = [
                    'id' => $campaign_data['list_id'],
                    'business_id' => $campaign_data['list_business_id'],
                    'name' => $campaign_data['list_name'],
                    'location' => $campaign_data['location'],
                    'description' => $campaign_data['list_description'],
                    'created_at' => $campaign_data['list_created_at']
                ];
                
                // Get spin wheel for this campaign
                $stmt = $pdo->prepare("SELECT * FROM spin_wheels WHERE campaign_id = ? AND is_active = 1");
                $stmt->execute([$campaign['id']]);
                $spin_wheel = $stmt->fetch();
                
                // Get pizza tracker for this campaign
                require_once __DIR__ . '/core/pizza_tracker_utils.php';
                $pizzaTracker = new PizzaTracker($pdo);
                $stmt = $pdo->prepare("SELECT id FROM pizza_trackers WHERE campaign_id = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$campaign['id']]);
                $tracker_row = $stmt->fetch();
                $pizza_tracker = null;
                if ($tracker_row) {
                    $pizza_tracker = $pizzaTracker->getTrackerDetails($tracker_row['id']);
                }
            }
        } else if ($qr_data['machine_id']) {
            // Fallback for legacy QR codes with machine_id
            $stmt = $pdo->prepare("
                SELECT * FROM voting_lists 
                WHERE id = ?
            ");
            $stmt->execute([$qr_data['machine_id']]);
            $list = $stmt->fetch();
        }
        
        if ($list) {
            // Get items for voting with CAMPAIGN-SPECIFIC vote counts (SECURITY FIX)
            if ($qr_data && isset($qr_data['campaign_id']) && $qr_data['campaign_id']) {
                // Campaign-specific vote counting
                $stmt = $pdo->prepare("
                    SELECT i.*, 
                           COUNT(CASE WHEN v.vote_type = 'vote_in' AND v.campaign_id = ? THEN 1 END) as votes_in,
                           COUNT(CASE WHEN v.vote_type = 'vote_out' AND v.campaign_id = ? THEN 1 END) as votes_out,
                           COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as total_votes_in,
                           COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as total_votes_out
                    FROM voting_list_items i
                    LEFT JOIN votes v ON i.id = v.item_id
                    WHERE i.voting_list_id = ?
                    GROUP BY i.id
                    ORDER BY i.item_name ASC
                ");
                $stmt->execute([$qr_data['campaign_id'], $qr_data['campaign_id'], $list['id']]);
            } else {
                // Legacy machine-specific vote counting  
                $stmt = $pdo->prepare("
                    SELECT i.*, 
                           COUNT(CASE WHEN v.vote_type = 'vote_in' AND v.machine_id = ? THEN 1 END) as votes_in,
                           COUNT(CASE WHEN v.vote_type = 'vote_out' AND v.machine_id = ? THEN 1 END) as votes_out,
                           COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as total_votes_in,
                           COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as total_votes_out
                    FROM voting_list_items i
                    LEFT JOIN votes v ON i.id = v.item_id
                    WHERE i.voting_list_id = ?
                    GROUP BY i.id
                    ORDER BY i.item_name ASC
                ");
                $stmt->execute([$list['id'], $list['id'], $list['id']]);
            }
            $items = $stmt->fetchAll();
        }
    }
}

// Get campaign data by campaign parameter (new system)
if (isset($_GET['campaign']) && !$qr_data) {
    $campaign_id = (int)$_GET['campaign'];
    
    $stmt = $pdo->prepare("
        SELECT c.*, b.name as business_name
        FROM campaigns c
        JOIN businesses b ON c.business_id = b.id
        WHERE c.id = ? AND c.status = 'active'
    ");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch();
    
    if ($campaign) {
        // Get spin wheel for this campaign
        $stmt = $pdo->prepare("SELECT * FROM spin_wheels WHERE campaign_id = ? AND is_active = 1");
        $stmt->execute([$campaign['id']]);
        $spin_wheel = $stmt->fetch();
        
        // Get pizza tracker for this campaign
        require_once __DIR__ . '/core/pizza_tracker_utils.php';
        $pizzaTracker = new PizzaTracker($pdo);
        $stmt = $pdo->prepare("SELECT id FROM pizza_trackers WHERE campaign_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$campaign['id']]);
        $tracker_row = $stmt->fetch();
        $pizza_tracker = null;
        if ($tracker_row) {
            $pizza_tracker = $pizzaTracker->getTrackerDetails($tracker_row['id']);
        }
        
        // Get voting lists for this campaign
        $stmt = $pdo->prepare("
            SELECT vl.*
            FROM voting_lists vl
            JOIN campaign_voting_lists cvl ON vl.id = cvl.voting_list_id
            WHERE cvl.campaign_id = ?
        ");
        $stmt->execute([$campaign_id]);
        $lists = $stmt->fetchAll();
        
        // For simplicity, use the first list
        if (!empty($lists)) {
            $list = $lists[0];
            
            // Get items for voting
            $stmt = $pdo->prepare("
                SELECT i.*, 
                       COUNT(CASE WHEN v.vote_type = 'vote_in' AND v.campaign_id = ? THEN 1 END) as votes_in,
                       COUNT(CASE WHEN v.vote_type = 'vote_out' AND v.campaign_id = ? THEN 1 END) as votes_out,
                       COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as total_votes_in,
                       COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as total_votes_out
                FROM voting_list_items i
                LEFT JOIN votes v ON i.id = v.item_id
                WHERE i.voting_list_id = ?
                GROUP BY i.id
                ORDER BY i.item_name ASC
            ");
            $stmt->execute([$campaign['id'], $campaign['id'], $list['id']]);
            $items = $stmt->fetchAll();
        }
    }
}

// Get spin wheel rewards if we have a spin wheel
$spin_rewards = [];
if ($spin_wheel) {
    $stmt = $pdo->prepare("SELECT * FROM rewards WHERE spin_wheel_id = ? AND active = 1 ORDER BY rarity_level DESC");
    $stmt->execute([$spin_wheel['id']]);
    $spin_rewards = $stmt->fetchAll();
}

// Get current week's stats and last week's winners
$current_stats = [];
$last_week_winners = ['in' => null, 'out' => null];

if ($list) {
    // Get current week stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as total_votes_in,
            COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as total_votes_out,
            COUNT(DISTINCT v.voter_ip) as unique_voters,
            COUNT(*) as total_votes
        FROM votes v
        INNER JOIN voting_list_items vli ON v.item_id = vli.id
        WHERE vli.voting_list_id = ? 
        AND YEARWEEK(v.created_at, 1) = YEARWEEK(NOW(), 1)
    ");
    $stmt->execute([$list['id']]);
    $current_stats = $stmt->fetch();
    
    // Get last week's winners
    $stmt = $pdo->prepare("
        SELECT 
            vli.item_name,
            COUNT(*) as vote_count,
            v.vote_type
        FROM votes v
        INNER JOIN voting_list_items vli ON v.item_id = vli.id
        WHERE vli.voting_list_id = ? 
        AND YEARWEEK(v.created_at, 1) = YEARWEEK(NOW(), 1) - 1
        AND v.vote_type = 'vote_in'
        GROUP BY v.item_id, v.vote_type
        ORDER BY vote_count DESC
        LIMIT 1
    ");
    $stmt->execute([$list['id']]);
    $last_week_winners['in'] = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        SELECT 
            vli.item_name,
            COUNT(*) as vote_count,
            v.vote_type
        FROM votes v
        INNER JOIN voting_list_items vli ON v.item_id = vli.id
        WHERE vli.voting_list_id = ? 
        AND YEARWEEK(v.created_at, 1) = YEARWEEK(NOW(), 1) - 1
        AND v.vote_type = 'vote_out'
        GROUP BY v.item_id, v.vote_type
        ORDER BY vote_count DESC
        LIMIT 1
    ");
    $stmt->execute([$list['id']]);
    $last_week_winners['out'] = $stmt->fetch();
}

// Handle vote submission with enhanced voting structure
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote'])) {
    $item_id = (int)$_POST['item_id'];
    $vote_type = $_POST['vote_type'];
    $list_id = (int)$_POST['list_id'];
    $campaign_id = isset($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : null;
    $vote_method = $_POST['vote_method'] ?? 'auto'; // 'daily', 'weekly', 'premium', 'auto'
    
    // Validate QR code and list (for legacy codes) OR campaign (for new system)
    $valid = false;
    if (isset($_POST['code'])) {
        $stmt = $pdo->prepare("
            SELECT qr.id, COALESCE(vl.business_id, c.business_id) as business_id
            FROM qr_codes qr
            LEFT JOIN voting_lists vl ON qr.machine_id = vl.id
            LEFT JOIN campaigns c ON qr.campaign_id = c.id
            LEFT JOIN campaign_voting_lists cvl ON c.id = cvl.campaign_id
            WHERE qr.code = ? AND (
                (qr.machine_id IS NOT NULL AND vl.id = ?) OR 
                (qr.campaign_id IS NOT NULL AND c.id = ? AND cvl.voting_list_id = ?)
            )
        ");
        $stmt->execute([$_POST['code'], $list_id, $campaign_id, $list_id]);
        $valid = $stmt->fetch();
    } else if ($campaign_id) {
        $stmt = $pdo->prepare("
            SELECT c.id, c.business_id
            FROM campaigns c
            WHERE c.id = ? AND c.status = 'active'
        ");
        $stmt->execute([$campaign_id]);
        $valid = $stmt->fetch();
    }
    
    if ($valid) {
        // Use enhanced voting service to record vote
        $vote_data = [
            'item_id' => $item_id,
            'vote_type' => $vote_type,
            'voter_ip' => $_SERVER['REMOTE_ADDR'],
            'user_id' => $_SESSION['user_id'] ?? null,
            'campaign_id' => $campaign_id,
            'machine_id' => $list['id'] ?? 0,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'vote_method' => $vote_method
        ];
        
        $vote_result = VotingService::recordVote($vote_data);
        
        if ($vote_result['success']) {
            $message = $vote_result['message'];
            $message_type = "success";
            
            // Update vote status after successful vote
            $vote_status = VotingService::getUserVoteStatus($user_id, $voter_ip);
            
            // If spin wheel is available, show spin opportunity
            if ($spin_wheel && !empty($spin_rewards)) {
                $message .= " You've earned a spin on the wheel!";
            }
            
            // Refresh items list with updated counts using enhanced service
            if ($campaign_id) {
                $items_result = VotingService::getItemsWithVotes($list_id, $campaign_id);
                if ($items_result['success']) {
                    $items = $items_result['items'];
                }
            } else {
                // Legacy refresh for machine-based voting
                if ($list) {
                    $stmt = $pdo->prepare("
                        SELECT i.*, 
                               COUNT(CASE WHEN v.vote_type = 'vote_in' AND v.machine_id = ? THEN 1 END) as votes_in,
                               COUNT(CASE WHEN v.vote_type = 'vote_out' AND v.machine_id = ? THEN 1 END) as votes_out
                        FROM voting_list_items i
                        LEFT JOIN votes v ON i.id = v.item_id
                        WHERE i.voting_list_id = ?
                        GROUP BY i.id
                        ORDER BY i.item_name ASC
                    ");
                    $stmt->execute([$list['id'], $list['id'], $list['id']]);
                    $items = $stmt->fetchAll();
                }
            }
        } else {
            $message = $vote_result['message'];
            if ($vote_result['error_code'] === 'NO_FREE_VOTES') {
                $message_type = "info";
            } elseif ($vote_result['error_code'] === 'INSUFFICIENT_COINS') {
                $message_type = "warning";
            } else {
                $message_type = "danger";
            }
        }
    } else {
        $message = "Invalid QR code, campaign, or list.";
        $message_type = "danger";
    }
}

// Handle spin wheel action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spin_wheel']) && $spin_wheel) {
    // Select a random reward based on rarity
    if (!empty($spin_rewards)) {
        $weighted_rewards = [];
        foreach ($spin_rewards as $reward) {
            $weight = 11 - $reward['rarity_level']; // Higher rarity = lower weight
            for ($i = 0; $i < $weight; $i++) {
                $weighted_rewards[] = $reward;
            }
        }
        
        if (!empty($weighted_rewards)) {
            $selected_reward = $weighted_rewards[array_rand($weighted_rewards)];
            
            // Log the spin result
            try {
                $stmt = $pdo->prepare("INSERT INTO spin_results (spin_wheel_id, reward_id, user_ip, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$spin_wheel['id'], $selected_reward['id'], $_SERVER['REMOTE_ADDR']]);
            } catch (Exception $e) {
                // Log error but don't break the experience
            }
            
            $message = "🎉 Congratulations! You won: " . $selected_reward['name'];
            if ($selected_reward['description']) {
                $message .= " - " . $selected_reward['description'];
            }
            $message_type = "success";
        }
    }
}

require_once __DIR__ . '/core/includes/header.php';

// Get promotional ads for rotating ad spots
require_once __DIR__ . '/core/promotional_ads_manager.php';
$adsManager = new PromotionalAdsManager($pdo);
$promotional_ads = $adsManager->getAdsForPage('vote', 2);

// Track ad views for analytics
foreach ($promotional_ads as $ad) {
    $adsManager->trackView($ad['id'], $user_id, 'vote');
}
?>

<style>
    /* VOTE PAGE SPECIFIC STYLES */
    
    /* Override navbar hiding for vote page */
    .vote-page .navbar {
        display: block !important;
    }
    
    /* Vote banner styling - FULL SIZE FOR BETTER VISIBILITY */
    .vote-banner {
        width: 100%;
        height: auto;
        max-height: 250px; /* Increased to full size for better banner visibility */
        object-fit: cover;
        display: block;
        border-radius: 0 0 16px 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        cursor: pointer; /* Allow clicking to view full size */
        transition: transform 0.3s ease;
    }
    
    /* Full size banner modal */
    .vote-banner:hover {
        transform: scale(1.02);
    }
    
    .banner-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        cursor: pointer;
    }
    
    .banner-modal img {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        max-width: 95%;
        max-height: 95%;
        object-fit: contain;
    }

    /* Main content area with padding */
    .main-content {
        padding: 1rem 1rem 2rem 1rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    /* Rotating ads styling */
    .rotating-ads {
        margin-bottom: 2rem;
    }

    .ad-card {
        border: none !important;
        border-radius: 12px !important;
        overflow: hidden;
        height: 120px;
        position: relative;
    }

    .ad-card .card-body {
        padding: 1rem;
        height: 100%;
        display: flex;
        align-items: center;
    }

    /* Vote status panel icons styling */
    .vote-status-icon {
        width: 48px;
        height: 48px;
        margin-right: 12px;
    }

    /* QR coin balance styling */
    .qr-balance {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 20px;
        background: rgba(255, 193, 7, 0.1);
        border: 1px solid rgba(255, 193, 7, 0.3);
        border-radius: 12px;
        margin-bottom: 1rem;
    }

    .qr-coin-image {
        width: 32px;
        height: 32px;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .main-content {
            padding: 0.5rem 0.5rem 1rem 0.5rem;
        }

        .ad-card {
            height: 100px;
        }

        .vote-status-icon {
            width: 40px;
            height: 40px;
        }

        .qr-coin-image {
            width: 24px;
            height: 24px;
        }
        
        .vote-banner {
            max-height: 200px; /* Responsive full size for mobile */
        }
    }

    /* Voting card styling */
    .voting-card {
        background: rgba(255, 255, 255, 0.12) !important;
        backdrop-filter: blur(20px) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        border-radius: 20px !important;
        padding: 2rem;
        margin-bottom: 2rem;
    }

    @media (max-width: 768px) {
        .voting-card {
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
    }

    /* Vote button styling */
    .btn-vote-in {
        background: linear-gradient(135deg, #4caf50, #66bb6a) !important;
        border: none !important;
        color: white !important;
        transition: all 0.3s ease !important;
    }

    .btn-vote-in:hover {
        background: linear-gradient(135deg, #388e3c, #4caf50) !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4) !important;
    }

    .btn-vote-out {
        background: linear-gradient(135deg, #f44336, #ef5350) !important;
        border: none !important;
        color: white !important;
        transition: all 0.3s ease !important;
    }

    .btn-vote-out:hover {
        background: linear-gradient(135deg, #d32f2f, #f44336) !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(244, 67, 54, 0.4) !important;
    }

    /* Item card styling */
    .item-card {
        background: rgba(255, 255, 255, 0.08) !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        border-radius: 16px !important;
        padding: 1.5rem;
        transition: all 0.3s ease !important;
    }

    .item-card:hover {
        background: rgba(255, 255, 255, 0.12) !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        transform: translateY(-2px) !important;
    }

    /* Vote status card animations */
    .vote-status-card {
        transition: all 0.3s ease !important;
        animation: pulse-glow 2s infinite;
    }

    .vote-status-card:hover {
        transform: translateY(-3px) !important;
        box-shadow: 0 8px 25px rgba(0,0,0,0.3) !important;
    }

    @keyframes pulse-glow {
        0%, 100% { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        50% { box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
    }

    /* Mobile responsive adjustments */
    @media (max-width: 768px) {
        .vote-status-card {
            min-height: 120px !important;
            margin-bottom: 15px;
        }
        
        .vote-status-card .h2 {
            font-size: 1.5rem !important;
        }
        
        .vote-status-card small {
            font-size: 0.75rem !important;
        }
    }

    /* Banner styling - smaller size, no animations */
    .vote-banner {
        width: 33.33% !important;
        height: auto !important;
        max-width: 400px !important;
        border-radius: 8px !important;
        margin: 0 auto 20px auto;
        display: block;
    }

    /* Vote status card styling */
    .vote-status-card {
        transition: all 0.3s ease !important;
        position: relative !important;
        overflow: hidden !important;
    }

    .vote-status-card:hover {
        transform: translateY(-3px) !important;
        box-shadow: 0 8px 25px rgba(0,0,0,0.2) !important;
    }

    .vote-status-card .badge {
        animation: pulseGlow 2s infinite ease-in-out;
    }

    @keyframes pulseGlow {
        0%, 100% { 
            transform: translateX(-50%) scale(1);
            opacity: 1;
        }
        50% { 
            transform: translateX(-50%) scale(1.05);
            opacity: 0.8;
        }
    }

    /* Vote count update animation */
    .vote-count-update {
        animation: countUpdate 0.5s ease-in-out;
    }

    @keyframes countUpdate {
        0% { transform: scale(1); }
        50% { transform: scale(1.2); color: #ffd700; }
        100% { transform: scale(1); }
    }

    /* Success vote animation */
    .vote-success-glow {
        animation: successGlow 0.5s ease-in-out;
    }

    @keyframes successGlow {
        0% { 
            transform: scale(1); 
            box-shadow: 0 0 0 rgba(76, 175, 80, 0);
        }
        50% { 
            transform: scale(1.02); 
            box-shadow: 0 0 20px rgba(76, 175, 80, 0.5);
        }
        100% { 
            transform: scale(1); 
            box-shadow: 0 0 0 rgba(76, 175, 80, 0);
        }
    }
</style>

<!-- Vote Banner - Small Size -->
<div class="text-center mb-3">
    <img src="<?php echo APP_URL; ?>/public/votebanner.png" alt="Vote Banner" class="vote-banner">
</div>

<div class="main-content">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

    <!-- Business Promotional Ads -->
    <?php if (!empty($promotional_ads)): ?>
    <div class="rotating-ads">
        <div class="row g-3">
            <?php foreach ($promotional_ads as $ad): ?>
            <div class="col-md-6">
                <div class="ad-card card" 
                     style="background: linear-gradient(135deg, <?php echo $ad['background_color']; ?> 0%, <?php echo $ad['background_color']; ?>dd 100%); color: <?php echo $ad['text_color']; ?>;">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <?php if ($ad['business_logo']): ?>
                                <img src="<?php echo APP_URL . '/' . htmlspecialchars($ad['business_logo']); ?>" 
                                     alt="<?php echo htmlspecialchars($ad['business_name']); ?>" 
                                     class="rounded me-3" 
                                     style="width: 50px; height: 50px; object-fit: contain; background: white; padding: 5px;">
                            <?php else: ?>
                                <div class="rounded me-3 d-flex align-items-center justify-content-center text-white" 
                                     style="width: 50px; height: 50px; background: rgba(255,255,255,0.2);">
                                    <i class="bi bi-building fs-4"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex-grow-1">
                                <h6 class="mb-1" style="color: <?php echo $ad['text_color']; ?>;">
                                    <?php echo htmlspecialchars($ad['ad_title']); ?>
                                </h6>
                                <p class="small mb-2 opacity-75" style="color: <?php echo $ad['text_color']; ?>;">
                                    <?php echo htmlspecialchars($ad['ad_description']); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="opacity-50" style="color: <?php echo $ad['text_color']; ?>;">
                                        <?php echo htmlspecialchars($ad['business_name']); ?>
                                    </small>
                                    <?php if ($ad['ad_cta_text'] && $ad['ad_cta_url']): ?>
                                        <a href="<?php echo htmlspecialchars($ad['ad_cta_url']); ?>" 
                                           class="btn btn-sm btn-light" 
                                           target="_blank"
                                           onclick="trackAdClick(<?php echo $ad['id']; ?>)">
                                            <?php echo htmlspecialchars($ad['ad_cta_text']); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Spin Wheel -->
    <?php if ($spin_wheel && !empty($spin_rewards)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-gradient-warning text-dark">
                <div class="card-header border-0">
                    <h5 class="mb-0 text-center">
                        <i class="bi bi-arrow-clockwise me-2"></i>Spin the Wheel
                    </h5>
                </div>
                <div class="card-body text-center">
                    <p class="mb-3">Vote to earn spins and win amazing rewards!</p>
                    <form method="post" class="d-inline">
                        <button type="submit" name="spin_wheel" class="btn btn-dark btn-lg">
                            <i class="bi bi-arrow-clockwise me-2"></i>Spin Now!
                        </button>
                    </form>
                    <div class="mt-3">
                        <small class="text-muted">Available rewards: 
                            <?php echo implode(', ', array_column($spin_rewards, 'name')); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pizza Tracker -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-gradient-success text-white">
                <div class="card-body text-center">
                    <h6 class="text-white mb-2">🍕 Pizza Tracker</h6>
                    <p class="small mb-3">Track your pizza order in real-time</p>
                    <a href="<?php echo APP_URL; ?>/public/pizza-tracker.php" class="btn btn-info btn-sm">
                        <i class="bi bi-geo-alt me-1"></i>Track Order
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($qr_data || $campaign): ?>
        <!-- Quick Vote Status Alert -->
        <?php 
        $total_free_votes = $vote_status['daily_free_remaining'] + $vote_status['weekly_bonus_remaining'];
        $status_class = $total_free_votes > 0 ? 'success' : ($vote_status['premium_votes_available'] > 0 ? 'warning' : 'danger');
        $status_icon = $total_free_votes > 0 ? 'check-circle' : ($vote_status['premium_votes_available'] > 0 ? 'exclamation-triangle' : 'x-circle');
        ?>
        <div class="alert alert-<?php echo $status_class; ?> alert-dismissible fade show text-center mb-3" role="alert">
            <i class="bi bi-<?php echo $status_icon; ?> me-2"></i>
            <strong>
                <?php if ($total_free_votes > 0): ?>
                    🎉 You have <?php echo $total_free_votes; ?> FREE vote<?php echo $total_free_votes != 1 ? 's' : ''; ?> remaining!
                <?php elseif ($vote_status['premium_votes_available'] > 0): ?>
                    ⚡ <?php echo $vote_status['premium_votes_available']; ?> premium vote<?php echo $vote_status['premium_votes_available'] != 1 ? 's' : ''; ?> available (45 coins each)
                <?php else: ?>
                    😴 No votes remaining - Come back tomorrow for daily free votes!
                <?php endif; ?>
            </strong>
            <?php if ($vote_status['qr_balance'] > 0 && $total_free_votes == 0): ?>
                <br><small>💰 You have <?php echo number_format($vote_status['qr_balance']); ?> QR coins - enough for <?php echo floor($vote_status['qr_balance'] / 45); ?> premium votes!</small>
            <?php endif; ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <!-- Vote Status Panel -->
        <div class="voting-card">
            <div class="text-center mb-4">
                <h2 class="text-primary">
                    <i class="bi bi-ballot-fill me-2"></i>
                    <?php echo htmlspecialchars($list['name'] ?? $campaign['name'] ?? 'Voting List'); ?>
                </h2>
                <?php if ($list && isset($list['description'])): ?>
                    <p class="text-muted"><?php echo htmlspecialchars($list['description']); ?></p>
                <?php endif; ?>
                <?php if ($qr_data && isset($qr_data['business_name'])): ?>
                    <p class="text-info">
                        <i class="bi bi-building me-1"></i>
                        <?php echo htmlspecialchars($qr_data['business_name']); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Vote Status Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card vote-status-card bg-gradient-success text-white h-100">
                        <div class="card-body text-center">
                            <div class="position-relative">
                                <h2 class="h2 mb-1"><?php echo $vote_status['daily_free_remaining']; ?></h2>
                                <small class="opacity-75">Daily Free Votes</small>
                                <div class="position-absolute top-0 start-50 translate-middle">
                                    <span class="badge bg-light text-success rounded-pill px-2 py-1">
                                        <i class="bi bi-gift"></i> FREE
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card vote-status-card bg-gradient-info text-white h-100">
                        <div class="card-body text-center">
                            <div class="position-relative">
                                <h2 class="h2 mb-1"><?php echo $vote_status['weekly_bonus_remaining']; ?></h2>
                                <small class="opacity-75">Weekly Bonus Votes</small>
                                <div class="position-absolute top-0 start-50 translate-middle">
                                    <span class="badge bg-light text-info rounded-pill px-2 py-1">
                                        <i class="bi bi-star"></i> BONUS
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card vote-status-card bg-gradient-warning text-white h-100">
                        <div class="card-body text-center">
                            <div class="position-relative">
                                <h2 class="h2 mb-1"><?php echo $vote_status['premium_votes_available']; ?></h2>
                                <small class="opacity-75">Premium Votes Available</small>
                                <div class="position-absolute top-0 start-50 translate-middle">
                                    <span class="badge bg-light text-warning rounded-pill px-2 py-1">
                                        <i class="bi bi-coin"></i> COINS
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weekly Winners Display -->
            <?php if ($last_week_winners['in'] || $last_week_winners['out']): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-gradient-primary text-white">
                        <div class="card-header border-0">
                            <h5 class="mb-0 text-center">
                                <i class="bi bi-trophy-fill me-2"></i>Last Week's Winners
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php if ($last_week_winners['in']): ?>
                                <div class="col-md-6">
                                    <div class="card bg-success bg-opacity-25 border-success">
                                        <div class="card-body text-center">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="bi bi-hand-thumbs-up-fill fs-3 text-success me-2"></i>
                                                <div>
                                                    <h6 class="mb-0 text-white">Most Voted IN</h6>
                                                    <small class="text-white-50">Winner</small>
                                                </div>
                                            </div>
                                            <h5 class="text-white mb-1"><?php echo htmlspecialchars($last_week_winners['in']['item_name']); ?></h5>
                                            <span class="badge bg-success">
                                                <?php echo $last_week_winners['in']['vote_count']; ?> votes
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($last_week_winners['out']): ?>
                                <div class="col-md-6">
                                    <div class="card bg-danger bg-opacity-25 border-danger">
                                        <div class="card-body text-center">
                                            <div class="d-flex align-items-center justify-content-center mb-2">
                                                <i class="bi bi-hand-thumbs-down-fill fs-3 text-danger me-2"></i>
                                                <div>
                                                    <h6 class="mb-0 text-white">Most Voted OUT</h6>
                                                    <small class="text-white-50">Winner</small>
                                                </div>
                                            </div>
                                            <h5 class="text-white mb-1"><?php echo htmlspecialchars($last_week_winners['out']['item_name']); ?></h5>
                                            <span class="badge bg-danger">
                                                <?php echo $last_week_winners['out']['vote_count']; ?> votes
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Current Week Stats -->
                            <?php if ($current_stats && ($current_stats['total_votes'] > 0)): ?>
                            <div class="mt-3 pt-3 border-top border-white border-opacity-25">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="text-white">
                                            <div class="h4 mb-0"><?php echo $current_stats['total_votes_in']; ?></div>
                                            <small class="text-white-50">This Week IN</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-white">
                                            <div class="h4 mb-0"><?php echo $current_stats['total_votes_out']; ?></div>
                                            <small class="text-white-50">This Week OUT</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-white">
                                            <div class="h4 mb-0"><?php echo $current_stats['unique_voters']; ?></div>
                                            <small class="text-white-50">Unique Voters</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- QR Balance with QR Coin Image -->
            <?php if ($user_id): ?>
                <div class="qr-balance">
                    <img src="<?php echo APP_URL; ?>/img/qrCoin.png" alt="QR Coin" class="qr-coin-image">
                    <span class="text-light">Your QR Balance:</span>
                    <span class="text-warning fw-bold"><?php echo number_format($vote_status['qr_balance']); ?></span>
                    <span class="text-light">coins</span>
                </div>
            <?php else: ?>
                <div class="text-center mb-4">
                    <div class="alert alert-info py-2">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Sign up to unlock QR coins and premium voting!</strong> Guest users get limited voting.
                        <div class="mt-2">
                            <a href="<?php echo APP_URL; ?>/user/register.php" class="btn btn-warning btn-sm me-2">
                                <i class="bi bi-person-plus me-1"></i>Register
                            </a>
                            <a href="<?php echo APP_URL; ?>/login.php" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Login
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Voting Items -->
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
                                    <?php if ($vote_status['daily_free_remaining'] > 0 || $vote_status['weekly_bonus_remaining'] > 0): ?>
                                        <div class="row mb-2">
                                            <div class="col-6">
                                                <form method="post" class="d-inline w-100">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="vote_type" value="in">
                                                    <input type="hidden" name="vote_method" value="auto">
                                                    <input type="hidden" name="list_id" value="<?php echo $list['id'] ?? 0; ?>">
                                                    <?php if ($campaign): ?>
                                                        <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                    <?php endif; ?>
                                                    <?php if (isset($_GET['code'])): ?>
                                                        <input type="hidden" name="code" value="<?php echo htmlspecialchars($_GET['code']); ?>">
                                                    <?php endif; ?>
                                                    <button type="submit" name="vote" class="btn btn-vote-in w-100">
                                                        <i class="bi bi-hand-thumbs-up me-2"></i>Vote In
                                                        <?php if ($vote_status['daily_free_remaining'] > 0): ?>
                                                            <span class="small">(+30 coins)</span>
                                                        <?php else: ?>
                                                            <span class="small">(+5 coins)</span>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="col-6">
                                                <form method="post" class="d-inline w-100">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="vote_type" value="out">
                                                    <input type="hidden" name="vote_method" value="auto">
                                                    <input type="hidden" name="list_id" value="<?php echo $list['id'] ?? 0; ?>">
                                                    <?php if ($campaign): ?>
                                                        <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                    <?php endif; ?>
                                                    <?php if (isset($_GET['code'])): ?>
                                                        <input type="hidden" name="code" value="<?php echo htmlspecialchars($_GET['code']); ?>">
                                                    <?php endif; ?>
                                                    <button type="submit" name="vote" class="btn btn-vote-out w-100">
                                                        <i class="bi bi-hand-thumbs-down me-2"></i>Vote Out
                                                        <?php if ($vote_status['daily_free_remaining'] > 0): ?>
                                                            <span class="small">(+30 coins)</span>
                                                        <?php else: ?>
                                                            <span class="small">(+5 coins)</span>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Premium Vote Option -->
                                    <?php if ($user_id && $vote_status['premium_votes_available'] > 0 && ($vote_status['daily_free_remaining'] == 0 && $vote_status['weekly_bonus_remaining'] == 0)): ?>
                                        <div class="row mb-2">
                                            <div class="col-12">
                                                <div class="text-center mb-2">
                                                    <small class="text-warning">
                                                        <i class="bi bi-lightning-fill me-1"></i>
                                                        Premium votes available!
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <form method="post" class="d-inline w-100">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="vote_type" value="in">
                                                    <input type="hidden" name="vote_method" value="premium">
                                                    <input type="hidden" name="list_id" value="<?php echo $list['id'] ?? 0; ?>">
                                                    <?php if ($campaign): ?>
                                                        <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                    <?php endif; ?>
                                                    <?php if (isset($_GET['code'])): ?>
                                                        <input type="hidden" name="code" value="<?php echo htmlspecialchars($_GET['code']); ?>">
                                                    <?php endif; ?>
                                                    <button type="submit" name="vote" class="btn btn-warning w-100">
                                                        <i class="bi bi-hand-thumbs-up me-2"></i>Vote In
                                                        <span class="small">(-45 coins)</span>
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="col-6">
                                                <form method="post" class="d-inline w-100">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="vote_type" value="out">
                                                    <input type="hidden" name="vote_method" value="premium">
                                                    <input type="hidden" name="list_id" value="<?php echo $list['id'] ?? 0; ?>">
                                                    <?php if ($campaign): ?>
                                                        <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                    <?php endif; ?>
                                                    <?php if (isset($_GET['code'])): ?>
                                                        <input type="hidden" name="code" value="<?php echo htmlspecialchars($_GET['code']); ?>">
                                                    <?php endif; ?>
                                                    <button type="submit" name="vote" class="btn btn-warning w-100">
                                                        <i class="bi bi-hand-thumbs-down me-2"></i>Vote Out
                                                        <span class="small">(-45 coins)</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- No Votes Available -->
                                    <?php if ($vote_status['daily_free_remaining'] == 0 && $vote_status['weekly_bonus_remaining'] == 0 && $vote_status['premium_votes_available'] == 0): ?>
                                        <div class="text-center">
                                            <div class="alert alert-warning py-2 mb-2">
                                                <i class="bi bi-clock me-2"></i>
                                                <strong>No free votes remaining</strong>
                                                <br>
                                                <small>Come back tomorrow for your daily free vote!</small>
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
                    <p class="text-muted">This voting list doesn't have any items yet.</p>
                </div>
            <?php endif; ?>
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

    <!-- Current Week Statistics -->
    <?php if (!empty($current_stats)): ?>
    <div class="voting-card">
        <h4 class="text-primary mb-3">
            <i class="bi bi-graph-up me-2"></i>This Week's Impact
        </h4>
        <div class="row text-center">
            <div class="col-md-3">
                <div class="p-3">
                    <h3 class="text-success"><?php echo number_format($current_stats['total_votes_in']); ?></h3>
                    <small class="text-muted">Votes In</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <h3 class="text-danger"><?php echo number_format($current_stats['total_votes_out']); ?></h3>
                    <small class="text-muted">Votes Out</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <h3 class="text-info"><?php echo number_format($current_stats['unique_voters']); ?></h3>
                    <small class="text-muted">Unique Voters</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <h3 class="text-warning"><?php echo number_format($current_stats['total_votes']); ?></h3>
                    <small class="text-muted">Total Votes</small>
                </div>
            </div>
        </div>
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

    // AJAX Voting System - No Page Reload Required
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
                    // Update vote counts and status immediately
                    await updateAllVoteCounts();
                    await updateVoteStatus();
                    
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
                const listId = form.querySelector('input[name="list_id"]')?.value;
                
                if (itemId) {
                    updates.push(updateItemVoteCount(card, itemId, campaignId, listId));
                }
            }
        });
        
        await Promise.all(updates);
    }

    async function updateItemVoteCount(card, itemId, campaignId, listId) {
        try {
            let url = `<?php echo APP_URL; ?>/core/get-vote-counts.php?item_id=${itemId}`;
            if (campaignId) url += `&campaign_id=${campaignId}`;
            if (listId) url += `&machine_id=${listId}`;
            
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

    async function updateVoteStatus() {
        try {
            const response = await fetch(`<?php echo APP_URL; ?>/api/get-vote-status.php`);
            const data = await response.json();
            
            if (data.success) {
                // Update vote status display
                const dailyElement = document.querySelector('.h4.text-success');
                const weeklyElement = document.querySelector('.h4.text-info');
                const premiumElement = document.querySelector('.h4.text-warning');
                
                if (dailyElement) {
                    animateCountUpdate(dailyElement, data.daily_free_remaining);
                }
                if (weeklyElement) {
                    animateCountUpdate(weeklyElement, data.weekly_bonus_remaining);
                }
                if (premiumElement) {
                    animateCountUpdate(premiumElement, data.premium_votes_available);
                }
                
                // Hide/show voting buttons based on availability
                updateVotingButtons(data);
            }
        } catch (error) {
            console.error('Error updating vote status:', error);
        }
    }

    function updateVotingButtons(voteStatus) {
        const votingOptions = document.querySelectorAll('.voting-options');
        
        votingOptions.forEach(options => {
            const freeVoteSection = options.querySelector('.row.mb-2:first-child');
            const premiumVoteSection = options.querySelector('.row.mb-2:nth-child(2)');
            const noVotesSection = options.querySelector('.text-center .alert-warning');
            
            // Show/hide sections based on vote availability
            if (voteStatus.daily_free_remaining > 0 || voteStatus.weekly_bonus_remaining > 0) {
                if (freeVoteSection) freeVoteSection.style.display = 'block';
                if (noVotesSection) noVotesSection.parentElement.style.display = 'none';
            } else {
                if (freeVoteSection) freeVoteSection.style.display = 'none';
                
                if (voteStatus.premium_votes_available > 0) {
                    if (premiumVoteSection) premiumVoteSection.style.display = 'block';
                    if (noVotesSection) noVotesSection.parentElement.style.display = 'none';
                } else {
                    if (premiumVoteSection) premiumVoteSection.style.display = 'none';
                    if (noVotesSection) noVotesSection.parentElement.style.display = 'block';
                }
            }
        });
    }

    // Add smooth transitions
    const cards = document.querySelectorAll('.item-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('animate__fadeInUp');
    });

</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 