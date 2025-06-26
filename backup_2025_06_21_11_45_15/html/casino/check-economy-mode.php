<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/config_manager.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/functions.php';

echo "<!DOCTYPE html><html><head><title>Economy Mode Diagnostic</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;} .test{background:white;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #007bff;} .pass{border-left-color:#28a745;} .fail{border-left-color:#dc3545;} .warning{border-left-color:#ffc107;}</style>";
echo "</head><body>";

echo "<h1>🔧 Economy Mode Diagnostic</h1>";

// Check current economy mode
$current_mode = ConfigManager::get('economy_mode', 'legacy');
echo "<div class='test " . ($current_mode === 'new' ? 'pass' : 'fail') . "'>";
echo "<strong>Current Economy Mode:</strong> " . htmlspecialchars($current_mode);
echo "</div>";

// Test with a user (if logged in)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Get QRCoinManager balance
    $new_balance = QRCoinManager::getBalance($user_id);
    echo "<div class='test'><strong>QRCoinManager Balance:</strong> " . number_format($new_balance) . " QR Coins</div>";
    
    // Get legacy balance
    $legacy_stats = getUserStats($user_id);
    $legacy_balance = $legacy_stats['user_points'];
    echo "<div class='test'><strong>Legacy getUserStats Balance:</strong> " . number_format($legacy_balance) . " points</div>";
    
    // Get getBalance result
    $combined_balance = QRCoinManager::getBalance($user_id);
    echo "<div class='test " . ($combined_balance == $new_balance ? 'pass' : 'fail') . "'>";
    echo "<strong>getBalance Result:</strong> " . number_format($combined_balance) . " coins";
    echo "</div>";
    
    // Show the issue
    if ($current_mode === 'legacy' && $combined_balance == $legacy_balance) {
        echo "<div class='test fail'>";
        echo "<strong>❌ PROBLEM FOUND:</strong> System is in 'legacy' mode, so it's ignoring QRCoinManager and using the old getUserStats calculation!";
        echo "<br><strong>This is why your balance is stuck at " . number_format($legacy_balance) . " instead of " . number_format($new_balance) . "</strong>";
        echo "</div>";
    }
    
} else {
    echo "<div class='test warning'><strong>Not logged in:</strong> Cannot test user-specific balances</div>";
}

// Show fix options
echo "<div class='test'>";
echo "<h3>🔧 Fix Options:</h3>";
echo "<form method='POST' style='margin-bottom:10px;'>";
echo "<button type='submit' name='fix_mode' value='new' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;'>✅ Fix: Set Economy Mode to 'new'</button>";
echo "</form>";

echo "<form method='POST' style='margin-bottom:10px;'>";
echo "<button type='submit' name='fix_mode' value='transition' style='background:#ffc107;color:black;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;'>⚖️ Set to 'transition' (uses higher balance)</button>";
echo "</form>";

echo "<p><strong>Recommended:</strong> Use 'new' mode to fully switch to the QRCoinManager system.</p>";
echo "</div>";

// Handle fix requests
if ($_POST['fix_mode'] ?? false) {
    $new_mode = $_POST['fix_mode'];
    
    if (in_array($new_mode, ['new', 'transition', 'legacy'])) {
        $success = ConfigManager::set('economy_mode', $new_mode, 'string', 'QR Coin economy mode setting');
        
        if ($success) {
            echo "<div class='test pass'>";
            echo "<strong>✅ SUCCESS:</strong> Economy mode updated to '$new_mode'";
            echo "<br><a href='" . $_SERVER['PHP_SELF'] . "'>🔄 Refresh to see changes</a>";
            echo "</div>";
        } else {
            echo "<div class='test fail'>";
            echo "<strong>❌ FAILED:</strong> Could not update economy mode";
            echo "</div>";
        }
    }
}

// Show current config table
echo "<div class='test'>";
echo "<h3>📋 Current Config Settings:</h3>";
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value, setting_type FROM config_settings WHERE setting_key LIKE '%economy%' OR setting_key LIKE '%coin%' ORDER BY setting_key");
    $stmt->execute();
    $settings = $stmt->fetchAll();
    
    if ($settings) {
        echo "<table border='1' style='border-collapse:collapse;width:100%;'>";
        echo "<tr><th>Key</th><th>Value</th><th>Type</th></tr>";
        foreach ($settings as $setting) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
            echo "<td>" . htmlspecialchars($setting['setting_value']) . "</td>";
            echo "<td>" . htmlspecialchars($setting['setting_type']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No economy-related config settings found.</p>";
    }
} catch (Exception $e) {
    echo "<p>Error loading config: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

echo "</body></html>";
?> 