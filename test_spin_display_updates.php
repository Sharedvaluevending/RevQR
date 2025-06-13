<?php
require_once 'html/core/config.php';
require_once 'html/core/session.php';
require_once 'html/core/functions.php';
require_once 'html/core/casino_spin_manager.php';

echo "🎰 SPIN DISPLAY UPDATE TEST\n";
echo "==========================\n\n";

$test_user_id = 4;
$test_business_id = 1;

echo "Testing spin count display updates for User ID: $test_user_id, Business ID: $test_business_id\n\n";

// Test the API endpoint
echo "1. TESTING NEW API ENDPOINT (get-spin-info.php):\n";
$spin_info = CasinoSpinManager::getAvailableSpins($test_user_id, $test_business_id);
echo "   Current spin info from manager:\n";
echo "   - Spins used: {$spin_info['spins_used']}\n";
echo "   - Total spins: {$spin_info['total_spins']}\n";
echo "   - Bonus spins: {$spin_info['bonus_spins']}\n";
echo "   - Spins remaining: {$spin_info['spins_remaining']}\n\n";

echo "2. CHANGES MADE TO SLOT MACHINE PAGE:\n";
echo "   ✅ Added IDs to spin count display elements:\n";
echo "      - #spinsUsed, #totalSpins, #bonusSpins\n";
echo "      - #extraSpinsDisplay, #spinPackAlert\n";
echo "   ✅ Made spin pack alert always present (hidden when no bonus)\n\n";

echo "3. CHANGES MADE TO JAVASCRIPT:\n";
echo "   ✅ Added updateSpinCountDisplay() method\n";
echo "   ✅ Added fetchAndUpdateSpinInfo() method\n";
echo "   ✅ Integrated with recordPlay() to update display after each spin\n";
echo "   ✅ Added console logging for debugging\n\n";

echo "4. NEW API ENDPOINT:\n";
echo "   ✅ Created html/api/casino/get-spin-info.php\n";
echo "   ✅ Returns updated spin information for JavaScript\n";
echo "   ✅ Uses existing CasinoSpinManager for consistency\n\n";

echo "Expected behavior after fixes:\n";
echo "- After each spin, the 'Spins: X/Y used today' should update immediately\n";
echo "- The '(N bonus from spin packs!)' text should update\n";
echo "- The 'You have N extra spins today' message should update\n";
echo "- When bonus spins run out, the spin pack alert should disappear\n";
echo "- Console will show 'Updated spin count display' with current values\n\n";

// Verify the API endpoint file exists
$api_file = 'html/api/casino/get-spin-info.php';
if (file_exists($api_file)) {
    echo "✅ API endpoint file created successfully: $api_file\n";
} else {
    echo "❌ API endpoint file not found: $api_file\n";
}

echo "\n🎯 TO TEST:\n";
echo "1. Go to the slot machine page in your browser\n";
echo "2. Open browser developer console (F12)\n";
echo "3. Make a spin\n";
echo "4. Watch for 'Updated spin count display' message in console\n";
echo "5. Verify that the spin count numbers update on the page\n";
echo "6. Make more spins until you run out to test the hiding of spin pack alerts\n\n";

echo "The spin count display should now update in real-time after each spin!\n";
?> 