<?php
require_once 'html/core/config.php';
require_once 'html/core/ai_assistant.php';

try {
    $aiAssistant = new AIAssistant();
    
    echo "Testing AI Analytics for Business ID 1...\n";
    
    // Debug: Check if recent data exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as recent_count FROM sales WHERE business_id = 1 AND sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $recent_data = $stmt->fetch();
    echo "Recent 30-day count: " . $recent_data['recent_count'] . "\n";
    
    // Test the date filter logic manually
    $date_filter = "";
    $date_range_description = "last 30 days";
    
    if ($recent_data['recent_count'] == 0) {
        echo "No recent data, checking 90 days...\n";
        $stmt = $pdo->prepare("SELECT COUNT(*) as data_count FROM sales WHERE business_id = 1 AND sale_time >= DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $stmt->execute();
        $medium_data = $stmt->fetch();
        echo "90-day count: " . $medium_data['data_count'] . "\n";
        
        if ($medium_data['data_count'] > 0) {
            $date_filter = "AND sale_time >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            $date_range_description = "last 90 days";
        } else {
            $date_filter = ""; // Use all-time data
            $date_range_description = "all time";
        }
    } else {
        $date_filter = "AND sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    
    echo "Using date filter: '{$date_filter}'\n";
    echo "Date range: {$date_range_description}\n\n";
    
    // Test revenue query manually
    $sql = "
        SELECT 
            SUM(quantity * sale_price) as total_revenue,
            COUNT(*) as total_sales,
            AVG(quantity * sale_price) as avg_sale_value
        FROM sales 
        WHERE business_id = 1 
        {$date_filter}
    ";
    echo "Revenue SQL: " . $sql . "\n";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $revenue_data = $stmt->fetch();
    echo "Manual revenue query result:\n";
    echo "- Total revenue: $" . number_format($revenue_data['total_revenue'] ?? 0, 2) . "\n";
    echo "- Total sales: " . ($revenue_data['total_sales'] ?? 0) . "\n";
    echo "- Avg sale value: $" . number_format($revenue_data['avg_sale_value'] ?? 0, 2) . "\n\n";
    
    // Test the full analytics function
    $analytics = $aiAssistant->getBusinessAnalytics(1, $pdo);
    
    echo "Analytics function result:\n";
    echo "Revenue trend: $" . number_format($analytics['revenue_trend'], 2) . "\n";
    echo "Total sales: " . $analytics['total_sales'] . "\n";
    echo "Date range: " . ($analytics['date_range'] ?? 'not set') . "\n";
    echo "Top items count: " . count($analytics['top_items']) . "\n";
    echo "Item performance count: " . count($analytics['item_performance']) . "\n";
    
    if (!empty($analytics['top_items'])) {
        echo "\nTop selling items:\n";
        foreach (array_slice($analytics['top_items'], 0, 3) as $item) {
            echo "- " . $item['item_name'] . ": $" . number_format($item['total_revenue'], 2) . "\n";
        }
    }
    
    if (!empty($analytics['item_performance'])) {
        echo "\nHighest margin items:\n";
        foreach (array_slice($analytics['item_performance'], 0, 3) as $item) {
            echo "- " . $item['item_name'] . ": " . $item['margin_percentage'] . "% margin\n";
        }
    }
    
    // Test AI chat with the business context
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Testing AI Chat with Business Context...\n\n";
    
    $business_context = [
        'revenue' => $analytics['revenue_trend'] * 4, // Convert weekly back to total for the period
        'total_sales' => $analytics['total_sales'],
        'avg_sale_value' => $analytics['avg_sale_value'],
        'date_range' => $analytics['date_range'],
        'low_stock_count' => $analytics['low_stock_count'],
        'top_items' => $analytics['top_items'],
        'highest_margin_items' => $analytics['item_performance']
    ];
    
    echo "Business context being passed to AI:\n";
    echo "- Revenue: $" . number_format($business_context['revenue'], 2) . "\n";
    echo "- Total sales: " . $business_context['total_sales'] . "\n";
    echo "- Avg sale value: $" . number_format($business_context['avg_sale_value'], 2) . "\n";
    echo "- Date range: " . $business_context['date_range'] . "\n";
    echo "- Top items count: " . count($business_context['top_items']) . "\n";
    echo "- Margin items count: " . count($business_context['highest_margin_items']) . "\n\n";
    
    $ai_response = $aiAssistant->sendChatMessage("How are my sales performing?", $business_context);
    
    if ($ai_response['success']) {
        echo "AI Response:\n";
        echo $ai_response['response'] . "\n";
    } else {
        echo "AI chat failed\n";
    }
    
    echo "\nTest completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Check the logs for more details.\n";
}
?> 