<?php
require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/ai_assistant.php';

// Test the AI assistant analytics directly
echo "=== AI Assistant Analytics Debug ===\n\n";

// Use the business user from our previous test
$test_user_id = 1; // sharedvaluevending
$test_business_id = 1; // Shared Value Vending

echo "Testing with User ID: $test_user_id, Business ID: $test_business_id\n\n";

// Initialize AI Assistant
$aiAssistant = new AIAssistant();

// Get analytics data
echo "1. Getting analytics data...\n";
$analytics = $aiAssistant->getBusinessAnalytics($test_business_id, $pdo);

echo "2. Analytics results:\n";
echo "   Revenue trend: $" . number_format($analytics['revenue_trend'] ?? 0, 2) . "\n";
echo "   Total sales: " . ($analytics['total_sales'] ?? 0) . "\n";
echo "   Date range: " . ($analytics['date_range'] ?? 'N/A') . "\n";
echo "   Low stock count: " . ($analytics['low_stock_count'] ?? 0) . "\n";
echo "   Optimization score: " . ($analytics['optimization_score'] ?? 0) . "%\n";

// Check specific data arrays
echo "\n3. Data array sizes:\n";
echo "   Top items: " . count($analytics['top_items'] ?? []) . "\n";
echo "   Low stock items: " . count($analytics['low_stock_items'] ?? []) . "\n";
echo "   Daily trends: " . count($analytics['daily_trends'] ?? []) . "\n";
echo "   Item performance: " . count($analytics['item_performance'] ?? []) . "\n";
echo "   Voting data: " . count($analytics['voting_data'] ?? []) . "\n";
echo "   Machine performance: " . count($analytics['machine_performance'] ?? []) . "\n";
echo "   Spin wheels: " . count($analytics['spin_wheels'] ?? []) . "\n";
echo "   Pizza trackers: " . count($analytics['pizza_trackers'] ?? []) . "\n";
echo "   QR performance: " . count($analytics['qr_performance'] ?? []) . "\n";
echo "   Campaign performance: " . count($analytics['campaign_performance'] ?? []) . "\n";
echo "   Casino participation: " . (empty($analytics['casino_participation']) ? 'Empty' : 'Has data') . "\n";
echo "   Promotional ads: " . count($analytics['promotional_ads'] ?? []) . "\n";

// Test insights generation
echo "\n4. Testing insights generation...\n";
try {
    $insights = $aiAssistant->generateInsights($analytics);
    echo "   Recommendations count: " . count($insights['recommendations'] ?? []) . "\n";
    echo "   Sales opportunities count: " . count($insights['sales_opportunities'] ?? []) . "\n";
    
    if (!empty($insights['recommendations'])) {
        echo "   First recommendation: " . ($insights['recommendations'][0]['title'] ?? 'No title') . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error generating insights: " . $e->getMessage() . "\n";
}

// Check for common issues
echo "\n5. Checking for common issues:\n";

// Check if tables exist
$tables_to_check = [
    'sales', 'votes', 'voting_lists', 'voting_list_items', 
    'spin_wheels', 'pizza_trackers', 'qr_codes', 
    'business_casino_participation', 'business_promotional_ads',
    'campaigns'
];

foreach ($tables_to_check as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table WHERE business_id = $test_business_id");
        $count = $stmt->fetchColumn();
        echo "   $table: $count records\n";
    } catch (Exception $e) {
        echo "   $table: ❌ Error - " . $e->getMessage() . "\n";
    }
}

// Test specific queries from the AI assistant
echo "\n6. Testing specific AI assistant queries:\n";

// Test the revenue query
try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(quantity * sale_price) as total_revenue,
            COUNT(*) as total_sales,
            AVG(quantity * sale_price) as avg_sale_value
        FROM sales 
        WHERE business_id = ? 
        AND sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$test_business_id]);
    $revenue_data = $stmt->fetch();
    echo "   Recent revenue query: $" . number_format($revenue_data['total_revenue'] ?? 0, 2) . " from " . ($revenue_data['total_sales'] ?? 0) . " sales\n";
} catch (Exception $e) {
    echo "   Revenue query error: " . $e->getMessage() . "\n";
}

// Test casino participation
try {
    $stmt = $pdo->prepare("SELECT * FROM business_casino_participation WHERE business_id = ?");
    $stmt->execute([$test_business_id]);
    $casino_data = $stmt->fetch();
    echo "   Casino enabled: " . ($casino_data['casino_enabled'] ?? 'false') . "\n";
} catch (Exception $e) {
    echo "   Casino query error: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
?> 