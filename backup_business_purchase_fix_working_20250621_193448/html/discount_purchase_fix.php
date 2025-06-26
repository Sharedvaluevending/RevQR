<?php
/**
 * Discount Purchase Diagnostic & Fix Tool
 * Identifies and fixes common issues with business discount purchases
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/qr_coin_manager.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "🛠️ DISCOUNT PURCHASE DIAGNOSTIC & FIX TOOL\n";
echo "===============================================\n\n";

// 1. Check user authentication
echo "1. CHECKING USER AUTHENTICATION:\n";
if (!isset($_SESSION['user_id'])) {
    echo "   ❌ User not logged in\n";
    echo "   💡 Solution: Please log in at /html/user/login.php\n\n";
    echo "   Quick login test:\n";
    echo "   - Visit: http://yoursite.com/html/user/login.php\n";
    echo "   - Or register: http://yoursite.com/html/register.php\n\n";
    exit;
} else {
    $user_id = $_SESSION['user_id'];
    echo "   ✅ User authenticated (ID: $user_id)\n\n";
}

// 2. Check user balance
echo "2. CHECKING QR COIN BALANCE:\n";
try {
    $user_balance = QRCoinManager::getBalance($user_id);
    echo "   📊 Current balance: {$user_balance} QR coins\n";
    
    if ($user_balance <= 0) {
        echo "   ❌ ZERO BALANCE - You need coins to buy discounts!\n";
        echo "   💡 Solutions to earn coins:\n";
        echo "   • Vote at /html/user/vote.php (5-30 coins per vote)\n";
        echo "   • Spin wheel at /html/user/spin.php (15-65 coins)\n";
        echo "   • Play casino games\n\n";
    } else {
        echo "   ✅ You have coins available\n\n";
    }
} catch (Exception $e) {
    echo "   ❌ Balance check failed: " . $e->getMessage() . "\n\n";
}

// 3. Check database tables
echo "3. CHECKING DATABASE TABLES:\n";
$required_tables = [
    'business_store_items' => 'Stores discount items for purchase',
    'user_store_purchases' => 'Records discount purchases',
    'qr_transactions' => 'Tracks QR coin spending'
];

foreach ($required_tables as $table => $description) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() > 0) {
            echo "   ✅ $table exists ($description)\n";
        } else {
            echo "   ❌ $table missing - $description\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error checking $table: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// 4. Check for available discount items
echo "4. CHECKING AVAILABLE DISCOUNT ITEMS:\n";
try {
    $stmt = $pdo->prepare("
        SELECT bsi.id, bsi.item_name, bsi.qr_coin_cost, bsi.discount_percentage,
               bsi.is_active, b.name as business_name
        FROM business_store_items bsi
        LEFT JOIN businesses b ON bsi.business_id = b.id
        WHERE bsi.category = 'discount'
        ORDER BY bsi.is_active DESC, bsi.qr_coin_cost ASC
        LIMIT 10
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        echo "   ❌ NO DISCOUNT ITEMS FOUND\n";
        echo "   💡 Possible causes:\n";
        echo "   • No businesses have created discount items\n";
        echo "   • All discount items are inactive\n";
        echo "   • Database table structure issue\n\n";
        
        // Check if we have any business_store_items at all
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM business_store_items");
        $stmt->execute();
        $total_items = $stmt->fetchColumn();
        echo "   📊 Total business store items in database: $total_items\n\n";
        
    } else {
        echo "   ✅ Found " . count($items) . " discount items:\n";
        
        $affordable_count = 0;
        foreach ($items as $item) {
            $affordable = $user_balance >= $item['qr_coin_cost'];
            if ($affordable) $affordable_count++;
            
            $status_icon = $item['is_active'] ? '✅' : '❌';
            $afford_icon = $affordable ? '💰' : '💸';
            
            echo "      $status_icon $afford_icon {$item['item_name']} - {$item['qr_coin_cost']} coins ({$item['discount_percentage']}% off)\n";
            echo "           From: {$item['business_name']}\n";
            echo "           Active: " . ($item['is_active'] ? 'Yes' : 'No') . "\n";
        }
        
        if ($affordable_count == 0) {
            echo "   ⚠️ No discounts are affordable with your current balance\n";
        } else {
            echo "   ✅ You can afford $affordable_count discount items\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Error checking discount items: " . $e->getMessage() . "\n\n";
}

// 5. Check API endpoints
echo "5. CHECKING DISCOUNT PURCHASE API:\n";
$api_files = [
    '/html/api/purchase-discount.php' => 'Main discount purchase API',
    '/html/user/purchase-business-item.php' => 'Business item purchase handler'
];

foreach ($api_files as $file => $description) {
    $full_path = __DIR__ . $file;
    if (file_exists($full_path)) {
        echo "   ✅ $file exists ($description)\n";
    } else {
        echo "   ❌ $file missing - $description\n";
    }
}
echo "\n";

// 6. Test database structure
echo "6. TESTING DATABASE COLUMN STRUCTURE:\n";
try {
    // Check user_store_purchases table structure
    $stmt = $pdo->prepare("DESCRIBE user_store_purchases");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['user_id', 'business_store_item_id', 'qr_coins_spent', 'discount_code', 'status'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (empty($missing_columns)) {
        echo "   ✅ user_store_purchases table has all required columns\n";
    } else {
        echo "   ❌ user_store_purchases missing columns: " . implode(', ', $missing_columns) . "\n";
        echo "   💡 This may cause SQL errors during purchase\n";
    }
    
    // Check business_store_items table structure
    $stmt = $pdo->prepare("DESCRIBE business_store_items");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['id', 'business_id', 'item_name', 'qr_coin_cost', 'discount_percentage', 'is_active'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (empty($missing_columns)) {
        echo "   ✅ business_store_items table has all required columns\n";
    } else {
        echo "   ❌ business_store_items missing columns: " . implode(', ', $missing_columns) . "\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Database structure check failed: " . $e->getMessage() . "\n";
}
echo "\n";

// 7. Check recent error logs
echo "7. CHECKING RECENT ERRORS:\n";
$error_log_file = __DIR__ . '/logs/php-error.log';
if (file_exists($error_log_file)) {
    $recent_errors = shell_exec("tail -10 '$error_log_file' | grep -i 'discount\\|purchase'");
    if ($recent_errors) {
        echo "   ⚠️ Recent discount/purchase related errors found:\n";
        $lines = explode("\n", trim($recent_errors));
        foreach ($lines as $line) {
            if (!empty($line)) {
                echo "      • $line\n";
            }
        }
    } else {
        echo "   ✅ No recent discount/purchase errors found\n";
    }
} else {
    echo "   ⚠️ Error log file not found at $error_log_file\n";
}
echo "\n";

// 8. Provide solutions and next steps
echo "🎯 SOLUTIONS & NEXT STEPS:\n";
echo "===========================\n\n";

if ($user_balance <= 0) {
    echo "🔴 PRIORITY 1: Get QR Coins\n";
    echo "• Visit /html/user/vote.php to vote (5-30 coins)\n";
    echo "• Visit /html/user/spin.php for daily wheel (15-65 coins)\n";
    echo "• Play casino games at /html/casino/\n\n";
}

if (empty($items)) {
    echo "🔴 PRIORITY 2: No Discount Items Available\n";
    echo "• Ask businesses to create discount items\n";
    echo "• Check /html/business/store.php for business store management\n";
    echo "• Admin can add items at /html/admin/manage-business-store.php\n\n";
}

echo "🔵 GENERAL TROUBLESHOOTING:\n";
echo "• Clear browser cache and cookies\n";
echo "• Check browser console (F12) for JavaScript errors\n";
echo "• Try different browser or incognito mode\n";
echo "• Check if JavaScript is enabled\n\n";

echo "📍 DISCOUNT STORE LOCATIONS:\n";
echo "• Main QR Store: /html/user/qr-store.php\n";
echo "• Business Stores: /html/user/business-stores.php\n";
echo "• Nayax Store: /html/nayax/discount-store.php\n";
echo "• Purchase History: /html/user/my-purchases.php\n\n";

// 9. Quick fix attempt
echo "🔧 ATTEMPTING QUICK FIXES:\n";

try {
    // Ensure proper table structure exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_store_purchases (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            qr_store_item_id INT NULL,
            business_store_item_id INT NULL,
            qr_coins_spent INT NOT NULL,
            discount_code VARCHAR(20) NULL,
            discount_percent DECIMAL(5,2) NULL,
            status ENUM('active', 'used', 'expired') DEFAULT 'active',
            expires_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            max_uses INT DEFAULT 1,
            uses_count INT DEFAULT 0
        )
    ");
    echo "   ✅ Ensured user_store_purchases table exists\n";
    
    // Check if we need to add missing columns
    $stmt = $pdo->prepare("SHOW COLUMNS FROM user_store_purchases LIKE 'business_store_item_id'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE user_store_purchases ADD COLUMN business_store_item_id INT NULL AFTER qr_store_item_id");
        echo "   ✅ Added missing business_store_item_id column\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Quick fix failed: " . $e->getMessage() . "\n";
}

echo "\nDiagnostic complete! 🎉\n";
echo "\nIf you're still having issues:\n";
echo "1. Check the specific error messages above\n";
echo "2. Try the suggested solutions\n";
echo "3. Contact support with this diagnostic output\n";
?> 