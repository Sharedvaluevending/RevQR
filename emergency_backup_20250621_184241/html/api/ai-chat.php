<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/ai_assistant.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in and has business role
if (!is_logged_in() || !has_role('business')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['message']) || empty(trim($input['message']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

$message = trim($input['message']);
$business_id = $input['business_id'] ?? 0;

try {
    // Check daily limit first
    $daily_limit = 10;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as chat_count
        FROM ai_chat_log 
        WHERE business_id = ? 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$business_id]);
    $limit_result = $stmt->fetch();
    $daily_count = $limit_result['chat_count'] ?? 0;
    
    if ($daily_count >= $daily_limit) {
        echo json_encode([
            'success' => false, 
            'response' => 'Daily chat limit reached. You can send up to ' . $daily_limit . ' messages per day. This resets at midnight.'
        ]);
        exit;
    }
    
    // Initialize AI Assistant
    $aiAssistant = new AIAssistant();
    
    // Get business context for better AI responses
    $business_context = [];
    if ($business_id > 0) {
        $analytics = $aiAssistant->getBusinessAnalytics($business_id, $pdo);
        $business_context = [
            'revenue' => $analytics['revenue_trend'] * 4, // Convert weekly back to total for the period
            'total_sales' => $analytics['total_sales'],
            'avg_sale_value' => $analytics['avg_sale_value'],
            'date_range' => $analytics['date_range'],
            'low_stock_count' => $analytics['low_stock_count'],
            'low_stock_items' => array_slice($analytics['low_stock_items'], 0, 5),
            'top_items' => array_slice($analytics['top_items'], 0, 3),
            'highest_margin_items' => array_slice($analytics['item_performance'], 0, 5),
            'voting_data' => array_slice($analytics['voting_data'], 0, 10),
            'machine_performance' => $analytics['machine_performance'],
            'price_analysis' => array_slice($analytics['price_analysis'], 0, 5)
        ];
    }
    
    // Send message to AI
    $result = $aiAssistant->sendChatMessage($message, $business_context);
    
    // Log the chat interaction and update usage stats
    if ($business_id > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ai_chat_log (business_id, user_message, ai_response, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $business_id, 
                $message, 
                $result['response']
            ]);
            
            // Update usage stats
            $stmt = $pdo->prepare("
                INSERT INTO ai_usage_stats (business_id, feature_used, usage_count, last_used) 
                VALUES (?, 'chat', 1, NOW())
                ON DUPLICATE KEY UPDATE 
                usage_count = usage_count + 1, 
                last_used = NOW()
            ");
            $stmt->execute([$business_id]);
            
        } catch (Exception $e) {
            // Log error but don't fail the request
            error_log("Failed to log AI chat: " . $e->getMessage());
        }
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("AI Chat Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'response' => 'I apologize, but I encountered an error processing your request. Please try again.'
    ]);
}
?> 