<?php
require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/business_utils.php';

// Test script to debug AI assistant business ID issues
echo "=== AI Assistant Business ID Debug ===\n\n";

// Test user authentication - simulate business login
session_start();

// First, let's check what businesses exist
echo "1. Checking existing businesses:\n";
$stmt = $pdo->query("SELECT id, name, user_id FROM businesses ORDER BY id");
$businesses = $stmt->fetchAll();
foreach ($businesses as $business) {
    echo "   Business ID: {$business['id']}, Name: {$business['name']}, User ID: {$business['user_id']}\n";
}

echo "\n2. Checking users with business associations:\n";
$stmt = $pdo->query("SELECT id, username, business_id, role FROM users ORDER BY id");
$users = $stmt->fetchAll();
foreach ($users as $user) {
    echo "   User ID: {$user['id']}, Username: {$user['username']}, Business ID: {$user['business_id']}, Role: {$user['role']}\n";
}

// Test with the first business user we find
$business_user = null;
foreach ($users as $user) {
    if ($user['role'] === 'business') {
        $business_user = $user;
        break;
    }
}

if (!$business_user) {
    echo "\n❌ No business users found!\n";
    exit;
}

echo "\n3. Testing AI Assistant business ID retrieval for User ID: {$business_user['id']}\n";

// Test method from AI assistant
echo "\n   A) Current AI Assistant method:\n";
$stmt = $pdo->prepare("
    SELECT b.*, u.username 
    FROM businesses b 
    JOIN users u ON b.id = u.business_id 
    WHERE u.id = ?
");
$stmt->execute([$business_user['id']]);
$business_current = $stmt->fetch();

if ($business_current) {
    echo "   ✅ Found business: ID {$business_current['id']}, Name: {$business_current['name']}\n";
    $business_id_current = $business_current['id'];
} else {
    echo "   ❌ No business found with current method\n";
    $business_id_current = 0;
}

// Test method from business_utils.php
echo "\n   B) Business Utils method:\n";
try {
    $business_id_utils = getOrCreateBusinessId($pdo, $business_user['id']);
    echo "   ✅ Found business ID: {$business_id_utils}\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    $business_id_utils = 0;
}

// Test data availability for both methods
$test_business_id = $business_id_utils > 0 ? $business_id_utils : $business_id_current;

if ($test_business_id > 0) {
    echo "\n4. Testing data availability for Business ID: {$test_business_id}\n";
    
    // Test sales data
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(quantity * sale_price) as revenue FROM sales WHERE business_id = ?");
    $stmt->execute([$test_business_id]);
    $sales_data = $stmt->fetch();
    echo "   Sales records: {$sales_data['count']}, Total revenue: $" . number_format($sales_data['revenue'] ?? 0, 2) . "\n";
    
    // Test voting data
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM votes v 
        JOIN voting_lists vl ON v.machine_id = vl.id 
        WHERE vl.business_id = ?
    ");
    $stmt->execute([$test_business_id]);
    $votes_data = $stmt->fetch();
    echo "   Vote records: {$votes_data['count']}\n";
    
    // Test voting list items
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM voting_list_items vli 
        JOIN voting_lists vl ON vli.voting_list_id = vl.id 
        WHERE vl.business_id = ?
    ");
    $stmt->execute([$test_business_id]);
    $items_data = $stmt->fetch();
    echo "   Voting list items: {$items_data['count']}\n";
    
    // Test spin wheels
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spin_wheels WHERE business_id = ?");
    $stmt->execute([$test_business_id]);
    $spin_data = $stmt->fetch();
    echo "   Spin wheels: {$spin_data['count']}\n";
    
    // Test pizza trackers
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pizza_trackers WHERE business_id = ?");
    $stmt->execute([$test_business_id]);
    $pizza_data = $stmt->fetch();
    echo "   Pizza trackers: {$pizza_data['count']}\n";
    
    // Test QR codes
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM qr_codes WHERE business_id = ?");
    $stmt->execute([$test_business_id]);
    $qr_data = $stmt->fetch();
    echo "   QR codes: {$qr_data['count']}\n";
    
    // Test casino participation
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM business_casino_participation WHERE business_id = ?");
    $stmt->execute([$test_business_id]);
    $casino_data = $stmt->fetch();
    echo "   Casino participation records: {$casino_data['count']}\n";
    
    // Test promotional ads
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM business_promotional_ads WHERE business_id = ?");
    $stmt->execute([$test_business_id]);
    $promo_data = $stmt->fetch();
    echo "   Promotional ads: {$promo_data['count']}\n";
    
    // Test campaigns
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM campaigns WHERE business_id = ?");
    $stmt->execute([$test_business_id]);
    $campaign_data = $stmt->fetch();
    echo "   Campaigns: {$campaign_data['count']}\n";
}

echo "\n5. Suggested fixes:\n";
if ($business_id_current === 0 && $business_id_utils > 0) {
    echo "   ✅ Replace AI assistant business ID retrieval with business_utils.php method\n";
}

if ($test_business_id > 0) {
    $total_data = ($sales_data['count'] ?? 0) + ($votes_data['count'] ?? 0) + ($items_data['count'] ?? 0);
    if ($total_data === 0) {
        echo "   ⚠️  No data found - this explains why metrics don't show\n";
        echo "   💡 Create sample data or check if business has any associated machines/items\n";
    } else {
        echo "   ✅ Data exists - business ID retrieval issue likely the cause\n";
    }
}

echo "\n=== Debug Complete ===\n";
?> 