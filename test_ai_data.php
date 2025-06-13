<?php
require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/ai_assistant.php';

echo "=== AI Assistant Data Test ===\n\n";

$aiAssistant = new AIAssistant();

// Test with business ID 1
echo "Testing with Business ID: 1\n";
echo "================================\n\n";

try {
    $analytics = $aiAssistant->getBusinessAnalytics(1, $pdo);
    
    echo "1. REVENUE DATA:\n";
    echo "   - Weekly Revenue: $" . number_format($analytics['revenue_trend'], 2) . "\n";
    echo "   - Total Sales: " . $analytics['total_sales'] . "\n";
    echo "   - Avg Sale Value: $" . number_format($analytics['avg_sale_value'], 2) . "\n";
    echo "   - Low Stock Count: " . $analytics['low_stock_count'] . "\n\n";
    
    echo "2. TOP SELLING ITEMS:\n";
    if (!empty($analytics['top_items'])) {
        foreach (array_slice($analytics['top_items'], 0, 3) as $item) {
            echo "   - " . $item['item_name'] . ": $" . number_format($item['total_revenue'], 2) . " revenue\n";
        }
    } else {
        echo "   - No top selling items found\n";
    }
    echo "\n";
    
    echo "3. HIGHEST MARGIN ITEMS:\n";
    if (!empty($analytics['item_performance'])) {
        foreach (array_slice($analytics['item_performance'], 0, 5) as $item) {
            echo "   - " . $item['item_name'] . ": " . $item['margin_percentage'] . "% margin (Cost: $" . 
                 $item['cost_price'] . ", Retail: $" . $item['current_price'] . ")\n";
        }
    } else {
        echo "   - No items found in item_performance\n";
    }
    echo "\n";
    
    echo "4. TESTING AI CHAT:\n";
    $business_context = [
        'revenue' => $analytics['revenue_trend'],
        'low_stock_count' => $analytics['low_stock_count'],
        'top_items' => array_slice($analytics['top_items'], 0, 3),
        'highest_margin_items' => array_slice($analytics['item_performance'], 0, 5)
    ];
    
    echo "   Business Context Sent to AI:\n";
    print_r($business_context);
    
    $response = $aiAssistant->sendChatMessage("What is my highest margin item?", $business_context);
    echo "\n   AI Response:\n";
    echo "   " . $response['response'] . "\n\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "=== Test Complete ===\n";
?> 