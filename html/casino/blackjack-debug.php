<?php
/**
 * Blackjack Debug Script
 * To identify blank page issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting blackjack debug...<br>";

session_start();
echo "Session started successfully<br>";

require_once __DIR__ . '/../core/config.php';
echo "Config loaded successfully<br>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "User not logged in - redirecting<br>";
    // Debug: log why user is being redirected
    error_log("Blackjack: User not logged in, redirecting to login");
    
    // Preserve the URL with location_id for after login
    $current_url = $_SERVER['REQUEST_URI'];
    $encoded_url = urlencode('https://revenueqr.sharedvaluevending.com' . $current_url);
    
    echo "Redirect URL would be: ../user/login.php?redirect=" . $encoded_url . "<br>";
    exit("User must login first");
}

$user_id = $_SESSION['user_id'];
echo "User ID: " . $user_id . "<br>";

// Get location_id from URL parameter
$location_id = $_GET['location_id'] ?? null;
echo "Location ID: " . ($location_id ?: 'none') . "<br>";

// Debug logging
error_log("Blackjack: User ID: " . $user_id . ", Location ID: " . ($location_id ?: 'none'));

try {
    // Get user's current balance and business info
    $stmt = $pdo->prepare("
        SELECT u.qr_coins as balance, u.business_id, b.business_name, b.app_url
        FROM users u 
        LEFT JOIN businesses b ON u.business_id = b.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    
    if (!$user_data) {
        die('User not found in database');
    }
    
    echo "User data loaded successfully<br>";
    echo "Balance: " . $user_data['balance'] . "<br>";
    echo "Business ID: " . ($user_data['business_id'] ?: 'none') . "<br>";

    $current_balance = $user_data['balance'];

    // If location_id is provided, use that for casino business info
    if ($location_id) {
        echo "Loading casino business data for location ID: " . $location_id . "<br>";
        
        $stmt = $pdo->prepare("
            SELECT b.id, b.business_name, b.app_url, cs.*, bcp.casino_enabled as participation_enabled
            FROM businesses b
            LEFT JOIN casino_settings cs ON b.id = cs.business_id
            LEFT JOIN business_casino_participation bcp ON b.id = bcp.business_id
            WHERE b.id = ? AND (b.is_casino_enabled = 1 OR bcp.casino_enabled = 1)
        ");
        $stmt->execute([$location_id]);
        $casino_business = $stmt->fetch();
        
        if (!$casino_business) {
            echo "Casino business not found for location_id: " . $location_id . "<br>";
            
            // Check if business exists at all
            $stmt = $pdo->prepare("SELECT id, business_name, is_casino_enabled FROM businesses WHERE id = ?");
            $stmt->execute([$location_id]);
            $business_check = $stmt->fetch();
            
            if ($business_check) {
                echo "Business exists but casino not enabled: " . $business_check['business_name'] . "<br>";
                echo "Casino enabled flag: " . ($business_check['is_casino_enabled'] ? 'Yes' : 'No') . "<br>";
                
                // Check participation table
                $stmt = $pdo->prepare("SELECT casino_enabled FROM business_casino_participation WHERE business_id = ?");
                $stmt->execute([$location_id]);
                $participation = $stmt->fetch();
                echo "Participation enabled: " . ($participation ? ($participation['casino_enabled'] ? 'Yes' : 'No') : 'Not found') . "<br>";
                
                die('Casino not enabled for this location');
            } else {
                echo "Business does not exist: " . $location_id . "<br>";
                die('Business location not found');
            }
        }
        
        echo "Successfully loaded casino business: " . $casino_business['business_name'] . "<br>";
        
        $business_id = $casino_business['id'];
        $business_name = $casino_business['business_name'] ?? 'QR Casino';
        $app_url = $casino_business['app_url'] ?? APP_URL;
        $casino_settings = $casino_business;
    } else {
        echo "Using user's own business<br>";
        // Fallback to user's own business
        $business_id = $user_data['business_id'];
        $business_name = $user_data['business_name'] ?? 'QR Casino';
        $app_url = $user_data['app_url'] ?? APP_URL;
        
        // Get casino settings
        $stmt = $pdo->prepare("SELECT * FROM casino_settings WHERE business_id = ?");
        $stmt->execute([$business_id]);
        $casino_settings = $stmt->fetch();
        
        if (!$casino_settings) {
            echo "No casino settings found for business ID: " . $business_id . "<br>";
        }
    }

    $jackpot_multiplier = $casino_settings['jackpot_multiplier'] ?? 50;
    $min_bet = $casino_settings['min_bet'] ?? 1;
    $max_bet = $casino_settings['max_bet'] ?? 100;
    
    echo "Casino settings loaded:<br>";
    echo "- Jackpot multiplier: " . $jackpot_multiplier . "<br>";
    echo "- Min bet: " . $min_bet . "<br>";
    echo "- Max bet: " . $max_bet . "<br>";
    
    echo "<br><strong>Debug complete - blackjack.php should work now</strong><br>";
    echo "<a href='blackjack.php?location_id=" . $location_id . "'>Try blackjack.php again</a>";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    echo "Stack trace:<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?> 