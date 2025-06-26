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
$business_id = $input['business_id'] ?? 0;

// Debug logging
error_log("Refresh Insights - Business ID: " . $business_id);

try {
    // Check if already refreshed today - TEMPORARILY DISABLED FOR TESTING
    /*
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as refresh_count
        FROM ai_insights_log 
        WHERE business_id = ? 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$business_id]);
    $limit_result = $stmt->fetch();
    $daily_refresh_count = $limit_result['refresh_count'] ?? 0;
    
    if ($daily_refresh_count > 0) {
        echo json_encode([
            'success' => false, 
            'error' => 'Insights have already been refreshed today. You can refresh once per day, or wait for the weekly automatic update.'
        ]);
        exit;
    }
    */
    
    // Initialize AI Assistant
    $aiAssistant = new AIAssistant();
    
    // Get fresh analytics data
    $analytics = $aiAssistant->getBusinessAnalytics($business_id, $pdo);
    error_log("Analytics data retrieved: " . json_encode(array_keys($analytics)));
    
    // Generate new insights
    $insights = $aiAssistant->generateInsights($analytics);
    error_log("Insights generated: " . count($insights['recommendations']) . " recommendations");
    
    // Validate insights structure
    if (!isset($insights['recommendations']) || !is_array($insights['recommendations'])) {
        throw new Exception("Generated insights are malformed");
    }
    
    // Log the insight refresh and update usage stats
    if ($business_id > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ai_insights_log (business_id, insights_data, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([
                $business_id, 
                json_encode($insights)
            ]);
            
            // Update usage stats
            $stmt = $pdo->prepare("
                INSERT INTO ai_usage_stats (business_id, feature_used, usage_count, last_used) 
                VALUES (?, 'insights', 1, NOW())
                ON DUPLICATE KEY UPDATE 
                usage_count = usage_count + 1, 
                last_used = NOW()
            ");
            $stmt->execute([$business_id]);
            
        } catch (Exception $e) {
            // Log error but don't fail the request
            error_log("Failed to log insights refresh: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Insights refreshed successfully',
        'insights_count' => count($insights['recommendations']),
        'insights' => $insights, // Return the actual insights data
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Refresh Insights Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to refresh insights: ' . $e->getMessage(),
        'debug_info' => [
            'business_id' => $business_id,
            'error_message' => $e->getMessage(),
            'error_line' => $e->getLine()
        ]
    ]);
}
?> 