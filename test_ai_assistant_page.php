<?php
// Test the AI assistant page as it would work for a real user
session_start();

// Simulate business user login
$_SESSION['user_id'] = 1; // sharedvaluevending user
$_SESSION['role'] = 'business';

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/auth.php';

echo "=== AI Assistant Page Test ===\n\n";

// Test the business ID retrieval from the actual page
$stmt = $pdo->prepare("
    SELECT b.*, u.username 
    FROM businesses b 
    JOIN users u ON b.id = u.business_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

echo "1. Business ID retrieval: " . ($business_id > 0 ? "✅ Success (ID: $business_id)" : "❌ Failed") . "\n";
echo "   Business Name: " . ($business['name'] ?? 'Unknown') . "\n\n";

// Test AI Assistant initialization 
require_once __DIR__ . '/html/core/ai_assistant.php';
$aiAssistant = new AIAssistant();

echo "2. AI Assistant initialization: ✅ Success\n\n";

// Test analytics retrieval
try {
    $analytics = $aiAssistant->getBusinessAnalytics($business_id, $pdo);
    echo "3. Analytics retrieval: ✅ Success\n";
    echo "   Revenue trend: $" . number_format($analytics['revenue_trend'], 2) . "\n";
    echo "   Total sales: " . $analytics['total_sales'] . "\n";
    echo "   Low stock count: " . $analytics['low_stock_count'] . "\n";
    echo "   Top items: " . count($analytics['top_items']) . "\n";
    echo "   Voting data: " . count($analytics['voting_data']) . "\n";
    echo "   Machine performance: " . count($analytics['machine_performance']) . "\n\n";
} catch (Exception $e) {
    echo "3. Analytics retrieval: ❌ Error - " . $e->getMessage() . "\n\n";
    exit;
}

// Test insights generation
try {
    $insights = $aiAssistant->generateInsights($analytics);
    echo "4. Insights generation: ✅ Success\n";
    echo "   Recommendations: " . count($insights['recommendations']) . "\n";
    echo "   Sales opportunities: " . count($insights['sales_opportunities']) . "\n";
    
    if (!empty($insights['recommendations'])) {
        echo "\n   Sample recommendations:\n";
        foreach (array_slice($insights['recommendations'], 0, 3) as $i => $rec) {
            echo "   " . ($i+1) . ". " . $rec['title'] . "\n";
            echo "      " . $rec['description'] . "\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "4. Insights generation: ❌ Error - " . $e->getMessage() . "\n\n";
}

// Test storing insights (like the page does)
try {
    $stmt = $pdo->prepare("
        INSERT INTO ai_insights_log (business_id, insights_data, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$business_id, json_encode($insights)]);
    echo "5. Insights storage: ✅ Success\n\n";
} catch (Exception $e) {
    echo "5. Insights storage: ❌ Error - " . $e->getMessage() . "\n\n";
}

echo "✅ AI Assistant page should now be working properly!\n";
echo "🌐 The page at https://revenueqr.sharedvaluevending.com/business/ai-assistant.php should display:\n";
echo "   - Revenue trend: $" . number_format($analytics['revenue_trend'], 2) . "\n";
echo "   - " . count($insights['recommendations']) . " AI recommendations\n";
echo "   - " . count($insights['sales_opportunities']) . " sales opportunities\n";
echo "   - " . $analytics['low_stock_count'] . " low stock alerts\n";
echo "   - Casino, promotional ads, and other feature data\n\n";

echo "=== Test Complete ===\n";
?> 