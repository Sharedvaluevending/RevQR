<?php
/**
 * Run Database Fix for Discount Purchases
 * This fixes the missing columns and structure issues
 */

require_once __DIR__ . '/core/config.php';

echo "🔧 FIXING DISCOUNT PURCHASE DATABASE ISSUES...\n";
echo "==============================================\n";

try {
    // Get database connection
    $pdo = get_db_connection();
    
    echo "✅ Connected to database\n";
    
    // 1. Fix user_store_purchases table structure
    echo "\n1. FIXING user_store_purchases TABLE:\n";
    
    $columns_to_add = [
        "business_store_item_id INT NULL",
        "discount_code VARCHAR(20) NULL", 
        "discount_percent DECIMAL(5,2) NULL",
        "expires_at DATETIME NULL",
        "max_uses INT DEFAULT 1",
        "uses_count INT DEFAULT 0"
    ];
    
    foreach ($columns_to_add as $column) {
        $col_name = explode(' ', $column)[0];
        try {
            // Check if column exists
            $check = $pdo->prepare("SHOW COLUMNS FROM user_store_purchases LIKE ?");
            $check->execute([$col_name]);
            
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE user_store_purchases ADD COLUMN $column");
                echo "   ✅ Added column: $col_name\n";
            } else {
                echo "   ✓ Column exists: $col_name\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Error adding $col_name: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Ensure business_store_items table exists
    echo "\n2. ENSURING business_store_items TABLE EXISTS:\n";
    
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS business_store_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            business_id INT NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            item_description TEXT,
            regular_price_cents INT NOT NULL DEFAULT 0,
            discount_percentage DECIMAL(5,2) NOT NULL,
            qr_coin_cost INT NOT NULL,
            category VARCHAR(50) DEFAULT 'discount',
            stock_quantity INT DEFAULT -1,
            max_per_user INT DEFAULT 1,
            is_active BOOLEAN DEFAULT TRUE,
            valid_from DATETIME NULL,
            valid_until DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";
    
    $pdo->exec($create_table_sql);
    echo "   ✅ business_store_items table ready\n";
    
    // 3. Ensure businesses table has sample data
    echo "\n3. CHECKING BUSINESSES TABLE:\n";
    
    $business_check = $pdo->query("SELECT COUNT(*) FROM businesses")->fetchColumn();
    
    if ($business_check == 0) {
        $pdo->exec("INSERT INTO businesses (id, name, email, status) VALUES (1, 'Sample Business', 'sample@business.com', 'active')");
        echo "   ✅ Added sample business\n";
    } else {
        echo "   ✓ Businesses exist ($business_check found)\n";
    }
    
    // 4. Add sample discount items if none exist
    echo "\n4. CHECKING DISCOUNT ITEMS:\n";
    
    $discount_check = $pdo->query("SELECT COUNT(*) FROM business_store_items WHERE category = 'discount'")->fetchColumn();
    
    if ($discount_check == 0) {
        $sample_items = [
            [1, '5% Off Any Item', 'Get 5% discount on any purchase', 500, 5.00, 25],
            [1, '10% Off Any Item', 'Get 10% discount on any purchase', 500, 10.00, 45],
            [1, '15% Off Any Item', 'Get 15% discount on any purchase', 500, 15.00, 65],
            [1, '20% Off Any Item', 'Get 20% discount on any purchase', 500, 20.00, 85]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO business_store_items 
            (business_id, item_name, item_description, regular_price_cents, discount_percentage, qr_coin_cost, category, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 'discount', 1)
        ");
        
        foreach ($sample_items as $item) {
            $stmt->execute($item);
        }
        
        echo "   ✅ Added " . count($sample_items) . " sample discount items\n";
    } else {
        echo "   ✓ Discount items exist ($discount_check found)\n";
    }
    
    // 5. Test the fix by checking table structure
    echo "\n5. VERIFYING FIXES:\n";
    
    $columns = $pdo->query("DESCRIBE user_store_purchases")->fetchAll(PDO::FETCH_COLUMN);
    $required = ['business_store_item_id', 'discount_code', 'discount_percent'];
    $missing = array_diff($required, $columns);
    
    if (empty($missing)) {
        echo "   ✅ All required columns present\n";
    } else {
        echo "   ❌ Still missing: " . implode(', ', $missing) . "\n";
    }
    
    // Check discount items are available
    $available_discounts = $pdo->query("
        SELECT COUNT(*) FROM business_store_items 
        WHERE category = 'discount' AND is_active = 1
    ")->fetchColumn();
    
    echo "   📊 Available discount items: $available_discounts\n";
    
    echo "\n🎉 DATABASE FIX COMPLETE!\n";
    echo "\nNow you should be able to:\n";
    echo "• Log in to your account\n";
    echo "• Visit /html/user/qr-store.php or /html/user/business-stores.php\n";
    echo "• Purchase discount items with QR coins\n";
    echo "\nIf you still have issues:\n";
    echo "1. Make sure you're logged in\n";
    echo "2. Check you have enough QR coins\n";
    echo "3. Clear browser cache\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and permissions.\n";
}
?> 