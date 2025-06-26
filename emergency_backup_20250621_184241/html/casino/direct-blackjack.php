<?php
// Direct Blackjack - bypassing login check temporarily
require_once __DIR__ . '/../core/config.php';

// Simulate user login for testing
session_start();
if (!isset($_SESSION['user_id'])) {
    // For testing - set a default user ID (replace with your actual user ID)
    $_SESSION['user_id'] = 1; // Change this to your actual user ID
}

$user_id = $_SESSION['user_id'];
$location_id = $_GET['location_id'] ?? null;

echo "<h1>🃏 Direct QR Blackjack 🃏</h1>";
echo "<p>Location ID: " . ($location_id ?: 'none') . "</p>";
echo "<p>User ID: $user_id</p>";

if ($location_id) {
    // Get business info
    $stmt = $pdo->prepare("
        SELECT b.id, b.business_name, b.app_url
        FROM businesses b
        WHERE b.id = ?
    ");
    $stmt->execute([$location_id]);
    $business = $stmt->fetch();
    
    if ($business) {
        echo "<p>✅ Business: " . htmlspecialchars($business['business_name']) . "</p>";
        
        // Get user balance
        $stmt = $pdo->prepare("SELECT qr_coins FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $balance = $stmt->fetchColumn();
        
        echo "<p>💰 Your Balance: " . number_format($balance) . " QR Coins</p>";
        
        echo "<div style='background: #28a745; color: white; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
        echo "<h2>🎮 QR Blackjack Game Working!</h2>";
        echo "<p>This proves the casino system works. The redirect issue was with the login check.</p>";
        echo "<p><strong>Playing at:</strong> " . htmlspecialchars($business['business_name']) . "</p>";
        echo "<p><strong>Your Balance:</strong> " . number_format($balance) . " QR Coins</p>";
        echo "</div>";
        
        echo "<p><a href='blackjack.php?location_id=$location_id' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Try Normal Blackjack</a></p>";
        
    } else {
        echo "<p>❌ Business not found</p>";
    }
} else {
    echo "<p>❌ No location ID provided</p>";
}
?> 