<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/config_manager.php';

echo "🔧 Fixing Economy Mode...\n\n";

// Set economy mode to 'new' to use QRCoinManager
$success = ConfigManager::set('economy_mode', 'new', 'string', 'QR Coin economy mode - uses QRCoinManager for all balance calculations');

if ($success) {
    echo "✅ SUCCESS: Economy mode set to 'new'\n";
    echo "✅ Your balance will now use QRCoinManager instead of legacy calculation\n";
    echo "✅ Blackjack winnings will be properly added to your balance\n\n";
    
    echo "🎯 NEXT STEPS:\n";
    echo "1. Go play blackjack again\n";
    echo "2. Win some games\n";
    echo "3. Your balance should now increase properly!\n\n";
    
    echo "📊 You can verify the fix at: /html/casino/check-economy-mode.php\n";
} else {
    echo "❌ FAILED: Could not update economy mode\n";
    echo "Manual fix required - check database permissions\n";
}
?> 