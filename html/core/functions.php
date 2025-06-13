<?php
require_once __DIR__ . '/config.php';

/**
 * Get client IP address
 */
function get_client_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

// Validation Functions
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_password($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return strlen($password) >= 8 
        && preg_match('/[A-Z]/', $password) 
        && preg_match('/[a-z]/', $password) 
        && preg_match('/[0-9]/', $password);
}

// File Upload Functions
function handle_file_upload($file, $allowed_types = ['image/jpeg', 'image/png'], $max_size = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file parameters'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }

    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }

    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large'];
    }

    $upload_dir = UPLOAD_PATH;
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = uniqid() . '_' . basename($file['name']);
    $destination = $upload_dir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $destination
        ];
    }

    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

// Date/Time Functions
function format_datetime($datetime) {
    return date('Y-m-d H:i:s', strtotime($datetime));
}

// Security Functions
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Response Functions
function json_response($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Generate a unique code for QR codes
 */
function generate_unique_code() {
    return bin2hex(random_bytes(16));
}

/**
 * Handle logo upload for QR codes
 */
function handle_logo_upload($file) {
    $upload_dir = __DIR__ . '/../assets/img/logos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return null;
    }
    
    $filename = uniqid('logo_') . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'assets/img/logos/' . $filename;
    }
    
    return null;
}

/**
 * Convert hex color to RGB array
 */
function hex2rgb($hex) {
    $hex = ltrim($hex, '#');
    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}

/**
 * Send email using system templates
 */
function send_system_email($template_name, $recipient_email, $variables = []) {
    global $pdo;
    
    // Get template
    $stmt = $pdo->prepare("SELECT template_content FROM email_templates WHERE template_name = ?");
    $stmt->execute([$template_name]);
    $template = $stmt->fetchColumn();
    
    if (!$template) {
        error_log("Email template not found: $template_name");
        return false;
    }
    
    // Replace variables
    $content = $template;
    foreach ($variables as $key => $value) {
        $content = str_replace("{{$key}}", $value, $content);
    }
    
    // Get system settings
    $stmt = $pdo->query("SELECT setting_key, value FROM system_settings WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Configure PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_username'];
        $mail->Password = $settings['smtp_password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $settings['smtp_port'];
        
        $mail->setFrom($settings['smtp_from_email'], $settings['smtp_from_name']);
        $mail->addAddress($recipient_email);
        
        $mail->isHTML(true);
        $mail->Subject = get_email_subject($template_name);
        $mail->Body = nl2br($content);
        $mail->AltBody = $content;
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get email subject based on template
 */
function get_email_subject($template_name) {
    $subjects = [
        'welcome_email' => 'Welcome to RevenueQR Platform',
        'password_reset' => 'Password Reset Request',
        'prize_notification' => 'Congratulations! You Won a Prize',
        'business_approval' => 'Your Business Account is Approved'
    ];
    
    return $subjects[$template_name] ?? 'Notification from RevenueQR Platform';
}

/**
 * Send welcome email to new user
 */
function send_welcome_email($user_email, $user_name) {
    $variables = [
        'name' => $user_name,
        'email' => $user_email,
        'login_url' => APP_URL . '/login.php'
    ];
    
    return send_system_email('welcome_email', $user_email, $variables);
}

/**
 * Send password reset email
 */
function send_password_reset_email($user_email, $user_name, $reset_token) {
    $variables = [
        'name' => $user_name,
        'reset_url' => APP_URL . '/reset-password.php?token=' . $reset_token,
        'expiry_hours' => 24
    ];
    
    return send_system_email('password_reset', $user_email, $variables);
}

/**
 * Send prize notification email
 */
function send_prize_notification($user_email, $user_name, $prize_name, $prize_value) {
    $variables = [
        'name' => $user_name,
        'prize_name' => $prize_name,
        'prize_value' => $prize_value,
        'claim_url' => APP_URL . '/user/claim-prize.php'
    ];
    
    return send_system_email('prize_notification', $user_email, $variables);
}

/**
 * Send business approval email
 */
function send_business_approval_email($user_email, $business_name, $owner_name) {
    $variables = [
        'business_name' => $business_name,
        'owner_name' => $owner_name,
        'login_url' => APP_URL . '/business/login.php'
    ];
    
    return send_system_email('business_approval', $user_email, $variables);
}

/**
 * Calculate user level based on lifetime earnings - LEVELS NEVER GO DOWN!
 * Users keep their highest achieved level forever to encourage spending
 * 
 * @param int $user_id User ID to get/update persistent level data
 * @param int $current_balance Current QR coin balance (for progress calculation only)
 * @param int $user_votes Total votes (for activity bonuses)
 * @param int $voting_days Days with voting activity
 * @param int $spin_days Days with spinning activity
 * @return array ['level' => int, 'progress' => float, 'points_to_next' => int, etc.]
 */
function calculateUserLevel($user_votes, $current_balance, $voting_days, $spin_days, $user_id = null) {
    global $pdo;
    
    // Calculate lifetime earnings from QR coin transactions
    $lifetime_earnings = 0;
    if ($user_id) {
        try {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as total_earned 
                FROM qr_coin_transactions 
                WHERE user_id = ? AND amount > 0
            ");
            $stmt->execute([$user_id]);
            $lifetime_earnings = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error calculating lifetime earnings: " . $e->getMessage());
        }
    }
    
    // Add activity-based points to lifetime earnings for level calculation
    $activity_bonus = ($user_votes * 10) + ($voting_days * 50) + ($spin_days * 100);
    $total_level_points = $lifetime_earnings + $activity_bonus;
    
    // Generate level thresholds - scaling system
    $level_thresholds = [];
    $level_thresholds[1] = 0; // Starting level
    
    for ($level = 2; $level <= 100; $level++) {
        if ($level == 2) {
            $level_thresholds[$level] = 1000;
        } else {
            // Progressive scaling: each level requires more points than the last
            $base_increase = 200 + (($level - 2) * 50); // Increases by 50 each level
            $level_thresholds[$level] = $level_thresholds[$level - 1] + $base_increase;
        }
    }
    
    // Find level based on total lifetime points
    $calculated_level = 1;
    $next_level = 2;
    
    for ($level = 100; $level >= 1; $level--) {
        if ($total_level_points >= $level_thresholds[$level]) {
            $calculated_level = $level;
            $next_level = $level + 1;
            break;
        }
    }
    
    // Get stored highest level and update if necessary
    $highest_level = $calculated_level;
    if ($user_id) {
        try {
            $stmt = $pdo->prepare("SELECT highest_level_achieved FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $stored_highest = (int) $stmt->fetchColumn();
            
            if ($calculated_level > $stored_highest) {
                // New level achieved! Update database
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET highest_level_achieved = ?, lifetime_qr_earnings = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$calculated_level, $lifetime_earnings, $user_id]);
                $highest_level = $calculated_level;
            } else {
                // Use stored highest level (never goes down)
                $highest_level = max($stored_highest, 1);
            }
        } catch (PDOException $e) {
            error_log("Error updating user level: " . $e->getMessage());
        }
    }
    
    // Cap at max level
    if ($highest_level >= 100) {
        return [
            'level' => 100,
            'progress' => 100.0,
            'points_to_next' => 0,
            'current_balance' => $current_balance,
            'lifetime_earnings' => $lifetime_earnings,
            'total_level_points' => $total_level_points,
            'current_threshold' => $level_thresholds[100],
            'next_threshold' => $level_thresholds[100],
            'level_locked' => true,
            'activity_bonus' => $activity_bonus
        ];
    }
    
    // Calculate progress for next level based on lifetime points
    $current_threshold = $level_thresholds[$highest_level];
    $next_threshold = $level_thresholds[$next_level];
    
    $progress_points = $total_level_points - $current_threshold;
    $points_needed = $next_threshold - $current_threshold;
    $progress_percentage = ($points_needed > 0) ? ($progress_points / $points_needed) * 100 : 0;
    $progress_percentage = max(0, min(100, $progress_percentage));
    $points_to_next = max(0, $next_threshold - $total_level_points);
    
    return [
        'level' => $highest_level,
        'progress' => round($progress_percentage, 1),
        'points_to_next' => $points_to_next,
        'current_balance' => $current_balance,
        'lifetime_earnings' => $lifetime_earnings,
        'total_level_points' => $total_level_points,
        'current_threshold' => $current_threshold,
        'next_threshold' => $next_threshold,
        'level_locked' => false, // Level can still go up
        'activity_bonus' => $activity_bonus,
        'calculated_level' => $calculated_level, // What level they'd be without protection
        'is_permanent' => true // Levels never go down
    ];
} 

/**
 * Get user's equipped avatar ID from database or session
 * 
 * @return int Avatar ID (defaults to 1 if none found)
 */
function getUserEquippedAvatar() {
    global $pdo;
    
    // First try to get from database if user is logged in
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT equipped_avatar FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $equipped_avatar = $stmt->fetchColumn();
            
            if ($equipped_avatar) {
                // Update session to match database
                $_SESSION['equipped_avatar'] = $equipped_avatar;
                return $equipped_avatar;
            }
        } catch (PDOException $e) {
            error_log("Error getting equipped avatar: " . $e->getMessage());
        }
    }
    
    // Fallback to session or default
    return $_SESSION['equipped_avatar'] ?? 1;
}

/**
 * Get avatar filename from avatar ID
 * 
 * @param int $avatar_id Avatar ID
 * @return string Avatar filename
 */
function getAvatarFilename($avatar_id) {
    return match($avatar_id) {
        1 => 'qrted.png',
        2 => 'qrjames.png', 
        3 => 'qrmike.png',
        4 => 'qrkevin.png',
        5 => 'qrtim.png',
        6 => 'qrbush.png',
        7 => 'qrterry.png',
        8 => 'qred.png', // QR ED avatar (200 votes)
        9 => 'qrLordPixel.png', // Lord Pixel avatar (spin wheel)
        10 => 'qrned.png', // QR NED avatar (500 votes)
        11 => 'qrClayton.png', // QR Clayton avatar (150,000 points)
        12 => 'qrsteve.png', // QR Steve avatar (common)
        13 => 'qrbob.png', // QR Bob avatar (common)
        14 => 'qrRyan.png', // QR Ryan avatar (special - for sale)
        15 => 'qrEasybake.png', // QR Easybake avatar (420/420/420 unlocks)
        16 => 'posty.png', // Posty avatar (50,000 spending milestone)
        default => 'qrted.png'
    };
}

/**
 * Get comprehensive user stats with improved consistency for user_id vs voter_ip tracking
 * This function prioritizes user_id when available and handles IP changes better
 */
function getUserStats($user_id = null, $fallback_ip = null) {
    global $pdo;
    
    if (!$user_id && !$fallback_ip) {
        return [
            'voting_stats' => ['total_votes' => 0, 'votes_in' => 0, 'votes_out' => 0, 'voting_days' => 0, 'created_at' => null],
            'spin_stats' => ['total_spins' => 0, 'spin_days' => 0],
            'user_points' => 0
        ];
    }
    
    // Get voting stats - prioritize user_id but include IP-only votes for completeness
    if ($user_id) {
        // For logged-in users, get all votes associated with this user_id, regardless of IP
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_votes,
                COUNT(CASE WHEN vote_type IN ('in', 'vote_in') THEN 1 END) as votes_in,
                COUNT(CASE WHEN vote_type IN ('out', 'vote_out') THEN 1 END) as votes_out,
                COUNT(DISTINCT DATE(created_at)) as voting_days,
                MIN(created_at) as created_at
            FROM votes 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
    } else {
        // For non-logged-in users, use IP only
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_votes,
                COUNT(CASE WHEN vote_type IN ('in', 'vote_in') THEN 1 END) as votes_in,
                COUNT(CASE WHEN vote_type IN ('out', 'vote_out') THEN 1 END) as votes_out,
                COUNT(DISTINCT DATE(created_at)) as voting_days,
                MIN(created_at) as created_at
            FROM votes 
            WHERE voter_ip = ? AND user_id IS NULL
        ");
        $stmt->execute([$fallback_ip]);
    }
    $voting_stats = $stmt->fetch();

    // Get spin stats - same logic, but also include prize points
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_spins,
                COUNT(DISTINCT DATE(spin_time)) as spin_days,
                COALESCE(SUM(prize_points), 0) as total_prize_points
            FROM spin_results 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_spins,
                COUNT(DISTINCT DATE(spin_time)) as spin_days,
                COALESCE(SUM(prize_points), 0) as total_prize_points
            FROM spin_results 
            WHERE user_ip = ? AND user_id IS NULL
        ");
        $stmt->execute([$fallback_ip]);
    }
    $spin_stats = $stmt->fetch();

    // Calculate points using the improved formula (includes actual prize points)
    $base_points = ($voting_stats['total_votes'] * 10) + ($spin_stats['total_spins'] * 25);
    $bonus_points = ($voting_stats['voting_days'] * 50) + ($spin_stats['spin_days'] * 100);
    $prize_points = $spin_stats['total_prize_points']; // NEW: Actual prize points from spin wheel
    $user_points = $base_points + $bonus_points + $prize_points;

    return [
        'voting_stats' => $voting_stats,
        'spin_stats' => $spin_stats,
        'user_points' => $user_points
    ];
}