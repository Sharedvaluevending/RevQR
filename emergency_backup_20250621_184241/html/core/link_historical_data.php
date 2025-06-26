<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// This script links historical IP-based activity to user accounts
// Run this when a user logs in to associate their previous anonymous activity

function linkHistoricalDataToUser($user_id, $current_ip) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Link votes from current IP to user account
        $stmt = $pdo->prepare("
            UPDATE votes 
            SET user_id = ? 
            WHERE voter_ip = ? AND user_id IS NULL
        ");
        $result_votes = $stmt->execute([$user_id, $current_ip]);
        $votes_linked = $stmt->rowCount();
        
        // Link spin results from current IP to user account  
        $stmt = $pdo->prepare("
            UPDATE spin_results 
            SET user_id = ? 
            WHERE user_ip = ? AND user_id IS NULL
        ");
        $result_spins = $stmt->execute([$user_id, $current_ip]);
        $spins_linked = $stmt->rowCount();
        
        $pdo->commit();
        
        return [
            'success' => true,
            'votes_linked' => $votes_linked,
            'spins_linked' => $spins_linked,
            'message' => "Successfully linked {$votes_linked} votes and {$spins_linked} spins to your account."
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'message' => 'Failed to link historical data.'
        ];
    }
}

// If called directly with user session
if (isset($_SESSION['user_id']) && isset($_GET['link_data'])) {
    $result = linkHistoricalDataToUser($_SESSION['user_id'], get_client_ip());
    
    if ($result['success']) {
        $_SESSION['flash_message'] = $result['message'];
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = $result['message'];
        $_SESSION['flash_type'] = 'error';
    }
    
    // Redirect back to dashboard
    header('Location: ' . APP_URL . '/user/dashboard.php');
    exit;
}

// Auto-link when user logs in (called from login process)
if (isset($_SESSION['user_id']) && !isset($_SESSION['data_linked'])) {
    $result = linkHistoricalDataToUser($_SESSION['user_id'], get_client_ip());
    $_SESSION['data_linked'] = true; // Prevent multiple runs
    
    if ($result['success'] && ($result['votes_linked'] > 0 || $result['spins_linked'] > 0)) {
        $_SESSION['flash_message'] = "Welcome back! We found and linked {$result['votes_linked']} votes and {$result['spins_linked']} spins to your account.";
        $_SESSION['flash_type'] = 'success';
    }
}
?> 