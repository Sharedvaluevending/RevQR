<?php
/**
 * Setup Phase 4 Sample Data
 * Create sample Nayax machines, transactions, and related data for testing
 */

require_once __DIR__ . '/html/core/config.php';

echo "🚀 Setting up Phase 4 Sample Data\n";
echo "==================================\n\n";

try {
    // Get first business for testing
    $stmt = $pdo->query("SELECT id, name FROM businesses LIMIT 1");
    $business = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$business) {
        echo "❌ No businesses found. Please create a business first.\n";
        exit(1);
    }
    
    $business_id = $business['id'];
    echo "📊 Using business: {$business['name']} (ID: {$business_id})\n\n";
    
    // 1. Create Nayax Machines
    echo "🏗️ Creating Nayax machines...\n";
    
    $machines = [
        ['machine_id' => 'NAY001', 'device_id' => 'DEV001', 'name' => 'Cafeteria Main', 'location' => 'Main Building Cafeteria'],
        ['machine_id' => 'NAY002', 'device_id' => 'DEV002', 'name' => 'Office Breakroom', 'location' => 'Office Building Floor 2'],
        ['machine_id' => 'NAY003', 'device_id' => 'DEV003', 'name' => 'Lobby Snacks', 'location' => 'Main Lobby'],
        ['machine_id' => 'NAY004', 'device_id' => 'DEV004', 'name' => 'Gym Area', 'location' => 'Fitness Center'],
        ['machine_id' => 'NAY005', 'device_id' => 'DEV005', 'name' => 'Conference Center', 'location' => 'Conference Room Building']
    ];
    
    foreach ($machines as $machine) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO nayax_machines (
                business_id, nayax_machine_id, nayax_device_id, machine_name, 
                location_description, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        $stmt->execute([
            $business_id, 
            $machine['machine_id'], 
            $machine['device_id'],
            $machine['name'], 
            $machine['location']
        ]);
        echo "   ✅ Created machine: {$machine['name']}\n";
    }
    
    // Get machine IDs for transactions
    $stmt = $pdo->prepare("SELECT nayax_machine_id, machine_name FROM nayax_machines WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $machine_ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n💰 Creating sample transactions...\n";
    
    // 2. Create sample transactions over the last 30 days
    $transaction_types = ['sale', 'qr_coin_purchase', 'discount_redemption'];
    $payment_methods = ['credit_card', 'debit_card', 'mobile_payment', 'cash'];
    
    $total_transactions = 0;
    $total_revenue = 0;
    
    for ($day = 30; $day >= 0; $day--) {
        $date = date('Y-m-d H:i:s', strtotime("-{$day} days"));
        
        // Random number of transactions per day (0-15)
        $daily_transactions = rand(0, 15);
        
        for ($i = 0; $i < $daily_transactions; $i++) {
            $machine = $machine_ids[array_rand($machine_ids)];
            $transaction_type = $transaction_types[array_rand($transaction_types)];
            $payment_method = $payment_methods[array_rand($payment_methods)];
            
            // Generate realistic amounts based on transaction type
            if ($transaction_type === 'qr_coin_purchase') {
                $amount_cents = rand(500, 2000); // $5-$20 for coin purchases
                $qr_coins_awarded = floor($amount_cents / 10); // 10 cents per coin
            } elseif ($transaction_type === 'discount_redemption') {
                $amount_cents = rand(50, 300); // $0.50-$3.00 discount values
                $qr_coins_awarded = 0;
            } else { // sale
                $amount_cents = rand(150, 800); // $1.50-$8.00 for regular sales
                $qr_coins_awarded = 0;
            }
            
            $platform_commission_cents = floor($amount_cents * 0.1); // 10% platform commission
            
            // Add some time variance within the day
            $transaction_time = date('Y-m-d H:i:s', strtotime($date) + rand(0, 86400));
            
            $stmt = $pdo->prepare("
                INSERT INTO nayax_transactions (
                    business_id, nayax_machine_id, nayax_transaction_id, 
                    transaction_type, amount_cents, platform_commission_cents, 
                    qr_coins_awarded, payment_method, status, 
                    machine_time, transaction_data, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, NOW())
            ");
            
            $external_id = 'NAY_' . strtoupper(uniqid());
            
            // Create transaction data JSON
            $transaction_data = json_encode([
                'machine_name' => $machine['machine_name'],
                'payment_reference' => 'REF_' . uniqid(),
                'items_purchased' => rand(1, 3),
                'processing_time' => rand(2, 8) . 's'
            ]);
            
            $stmt->execute([
                $business_id,
                $machine['nayax_machine_id'],
                $external_id,
                $transaction_type,
                $amount_cents,
                $platform_commission_cents,
                $qr_coins_awarded,
                $payment_method,
                $transaction_time,
                $transaction_data
            ]);
            
            $total_transactions++;
            $total_revenue += $amount_cents;
        }
    }
    
    echo "   ✅ Created {$total_transactions} transactions\n";
    echo "   💰 Total revenue: $" . number_format($total_revenue / 100, 2) . "\n";
    
    // 3. Create QR Store Items
    echo "\n🛒 Creating QR store items...\n";
    
    // First create business store items if they don't exist
    $store_items = [
        ['name' => 'Coca Cola', 'price_cents' => 250, 'category' => 'Beverages', 'discount' => 15],
        ['name' => 'Pepsi', 'price_cents' => 250, 'category' => 'Beverages', 'discount' => 15],
        ['name' => 'Sprite', 'price_cents' => 250, 'category' => 'Beverages', 'discount' => 12],
        ['name' => 'Water Bottle', 'price_cents' => 200, 'category' => 'Beverages', 'discount' => 10],
        ['name' => 'Lay\'s Chips', 'price_cents' => 350, 'category' => 'Snacks', 'discount' => 20],
        ['name' => 'Doritos', 'price_cents' => 375, 'category' => 'Snacks', 'discount' => 18],
        ['name' => 'Snickers Bar', 'price_cents' => 225, 'category' => 'Candy', 'discount' => 15],
        ['name' => 'Kit Kat', 'price_cents' => 225, 'category' => 'Candy', 'discount' => 15],
        ['name' => 'Energy Drink', 'price_cents' => 450, 'category' => 'Beverages', 'discount' => 25],
        ['name' => 'Granola Bar', 'price_cents' => 300, 'category' => 'Healthy', 'discount' => 12]
    ];
    
    foreach ($store_items as $item) {
        // Map category to valid enum
        $category_map = [
            'Beverages' => 'beverage',
            'Snacks' => 'snack', 
            'Candy' => 'snack',
            'Healthy' => 'food'
        ];
        $mapped_category = $category_map[$item['category']] ?? 'other';
        
        // Create business store item
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO business_store_items (
                business_id, item_name, regular_price_cents, category, 
                discount_percentage, qr_coin_cost, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $qr_coin_cost = rand(50, 150); // 50-150 coins
        
        $stmt->execute([
            $business_id,
            $item['name'],
            $item['price_cents'],
            $mapped_category,
            $item['discount'],
            $qr_coin_cost
        ]);
        
        echo "   ✅ Created store item: {$item['name']} ({$qr_coin_cost} coins, {$item['discount']}% discount)\n";
    }
    
    // 4. Skip discount codes (table doesn't exist in current schema)
    echo "\n🎫 Skipping discount codes (table not available)...\n";
    
    // 5. Summary
    echo "\n📊 Sample Data Summary:\n";
    echo "=======================\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM nayax_machines WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $machine_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM nayax_transactions nt 
        JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id 
        WHERE nm.business_id = ?
    ");
    $stmt->execute([$business_id]);
    $transaction_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM business_store_items WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $store_item_count = $stmt->fetchColumn();
    
    echo "✅ Nayax Machines: {$machine_count}\n";
    echo "✅ Transactions: {$transaction_count}\n";
    echo "✅ Store Items: {$store_item_count}\n";
    echo "\n🎉 Phase 4 sample data setup complete!\n";
    echo "\n🚀 Ready to run Phase 4 verification tests.\n";
    
} catch (Exception $e) {
    echo "❌ Error setting up sample data: " . $e->getMessage() . "\n";
    exit(1);
}
?> 