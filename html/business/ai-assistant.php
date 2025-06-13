<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Get business details
$stmt = $pdo->prepare("
    SELECT b.*, u.username 
    FROM businesses b 
    JOIN users u ON b.id = u.business_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Debug: Log business information
error_log("AI Assistant Debug - User ID: " . ($_SESSION['user_id'] ?? 'NULL'));
error_log("AI Assistant Debug - Business ID: " . $business_id);
error_log("AI Assistant Debug - Business Name: " . ($business['name'] ?? 'NULL'));

// Check daily interaction limits
$can_refresh = true; // Enable refresh for testing
$can_chat = true;
$last_refresh_time = null;
$daily_chat_count = 0;
$max_daily_chats = 10; // Allow 10 chats per day

try {
    // Check last refresh time
    $stmt = $pdo->prepare("
        SELECT MAX(created_at) as last_refresh
        FROM ai_insights_log 
        WHERE business_id = ? 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$business_id]);
    $refresh_result = $stmt->fetch();
    if ($refresh_result && $refresh_result['last_refresh']) {
        $last_refresh_time = $refresh_result['last_refresh'];
        // Enable refresh even if refreshed today for testing
        // $can_refresh = false; // Already refreshed today
    }
    
    // Check daily chat count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as chat_count
        FROM ai_chat_log 
        WHERE business_id = ? 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$business_id]);
    $chat_result = $stmt->fetch();
    $daily_chat_count = $chat_result['chat_count'] ?? 0;
    $can_chat = $daily_chat_count < $max_daily_chats;
    
} catch (Exception $e) {
    // Tables might not exist, continue with default behavior
    error_log("Daily limits check failed: " . $e->getMessage());
}

// Include the AI Assistant class
require_once __DIR__ . '/../core/ai_assistant.php';

// Initialize AI Assistant
$aiAssistant = new AIAssistant();

// Get business analytics data
$analytics = $aiAssistant->getBusinessAnalytics($business_id, $pdo);

// Try to load the latest stored insights first
$stored_insights = null;
$insights_timestamp = null;
try {
    $stmt = $pdo->prepare("
        SELECT insights_data, created_at 
        FROM ai_insights_log 
        WHERE business_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$business_id]);
    $latest_insight = $stmt->fetch();
    
    if ($latest_insight && $latest_insight['insights_data']) {
        $decoded_insights = json_decode($latest_insight['insights_data'], true);
        // Validate that the insights data is properly structured
        if ($decoded_insights && isset($decoded_insights['recommendations']) && is_array($decoded_insights['recommendations'])) {
            $stored_insights = $decoded_insights;
            $insights_timestamp = $latest_insight['created_at'];
        }
    }
} catch (Exception $e) {
    error_log("Failed to load stored insights: " . $e->getMessage());
}

// Use stored insights if available and valid, otherwise generate fresh ones
if ($stored_insights && is_array($stored_insights) && !empty($stored_insights['recommendations'])) {
    $insights = $stored_insights;
    $using_stored = true;
} else {
    // Generate fresh insights if none stored or invalid
    try {
        $insights = $aiAssistant->generateInsights($analytics);
        $using_stored = false;
        $insights_timestamp = date('Y-m-d H:i:s');
        
        // Store the fresh insights immediately
        if ($business_id > 0 && !empty($insights['recommendations'])) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO ai_insights_log (business_id, insights_data, created_at) 
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$business_id, json_encode($insights)]);
            } catch (Exception $e) {
                error_log("Failed to store fresh insights: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log("Failed to generate insights: " . $e->getMessage());
        // Fallback to default insights
        $insights = [
            'recommendations' => [
                [
                    'title' => 'Welcome to AI Insights',
                    'description' => 'Your AI assistant is ready to help optimize your vending business.',
                    'action' => 'Start by checking your inventory and sales data',
                    'impact' => 'Better business decisions ahead',
                    'priority' => 'medium',
                    'icon' => 'bi-lightbulb',
                    'color' => 'info'
                ]
            ],
            'sales_opportunities' => []
        ];
        $using_stored = false;
        $insights_timestamp = date('Y-m-d H:i:s');
    }
}

// Ensure insights structure is valid
if (!isset($insights['recommendations']) || !is_array($insights['recommendations'])) {
    $insights['recommendations'] = [
        [
            'title' => 'Getting Started',
            'description' => 'Your AI assistant needs more business data to provide personalized insights.',
            'action' => 'Ensure your sales and inventory data is up to date',
            'impact' => 'Better recommendations with more data',
            'priority' => 'medium',
            'icon' => 'bi-info-circle',
            'color' => 'info'
        ]
    ];
}

if (!isset($insights['sales_opportunities']) || !is_array($insights['sales_opportunities'])) {
    $insights['sales_opportunities'] = [];
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.ai-container {
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.05) 0%, rgba(102, 16, 242, 0.05) 100%);
    min-height: calc(100vh - 200px);
    padding: 2rem 0;
}

.insight-card {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(13, 110, 253, 0.2);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.insight-card:hover {
    border-color: rgba(13, 110, 253, 0.4);
    transform: translateY(-2px);
}

.insight-priority-high {
    border-left: 4px solid #dc3545;
}

.insight-priority-medium {
    border-left: 4px solid #ffc107;
}

.insight-priority-low {
    border-left: 4px solid #28a745;
}

.ai-chat-container {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    max-height: 400px;
    overflow-y: auto;
}

.loading-spinner {
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top: 3px solid #007bff;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="ai-container">
    <div class="container">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="bg-primary text-white rounded-circle p-3">
                                    <i class="bi bi-robot fs-3"></i>
                                </div>
                            </div>
                            <div class="col">
                                <h1 class="mb-1">AI Business Assistant</h1>
                                <p class="text-muted mb-0">
                                    Intelligent insights and recommendations for <?php echo htmlspecialchars($business['name']); ?>
                                </p>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-primary" id="refresh-insights" 
                                        <?php echo !$can_refresh ? 'disabled' : ''; ?>>
                                    <i class="bi bi-arrow-clockwise me-2"></i>
                                    <?php if ($can_refresh): ?>
                                        Refresh Insights
                                    <?php else: ?>
                                        Already Refreshed Today
                                    <?php endif; ?>
                                </button>
                                <?php if (!$can_refresh && $last_refresh_time): ?>
                                    <div class="small text-muted mt-1">
                                        Last refreshed: <?php echo date('g:i A', strtotime($last_refresh_time)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Debug info -->
                                <div class="small text-info mt-2">
                                    <?php if (isset($using_stored) && $using_stored): ?>
                                        <i class="bi bi-database me-1"></i>Using stored insights
                                    <?php else: ?>
                                        <i class="bi bi-lightning me-1"></i>Fresh insights generated
                                    <?php endif; ?>
                                    <br>
                                    <small>Updated: <?php echo isset($insights_timestamp) ? date('M j, g:i A', strtotime($insights_timestamp)) : 'Just now'; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Quick Stats Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-lightbulb text-warning fs-2 mb-2"></i>
                        <h4 class="text-primary"><?php echo count($insights['recommendations']); ?></h4>
                        <small class="text-muted">AI Insights</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-graph-up text-success fs-2 mb-2"></i>
                        <h4 class="text-success">$<?php echo number_format($analytics['revenue_trend'], 0); ?></h4>
                        <small class="text-muted">Weekly Revenue</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <?php if (!empty($analytics['casino_participation']['casino_enabled'])): ?>
                            <i class="bi bi-piggy-bank text-success fs-2 mb-2"></i>
                            <h4 class="text-success">$<?php echo number_format($analytics['casino_revenue']['total_casino_revenue'] ?? 0, 0); ?></h4>
                            <small class="text-muted">Casino Revenue</small>
                        <?php else: ?>
                            <i class="bi bi-piggy-bank-fill text-muted fs-2 mb-2"></i>
                            <h4 class="text-muted">-</h4>
                            <small class="text-muted">Casino Disabled</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <?php 
                        $total_ads = !empty($analytics['promotional_ads']) ? array_sum(array_column($analytics['promotional_ads'], 'total_ads')) : 0;
                        $total_views = !empty($analytics['promotional_ads']) ? array_sum(array_column($analytics['promotional_ads'], 'total_views')) : 0;
                        ?>
                        <i class="bi bi-megaphone text-info fs-2 mb-2"></i>
                        <h4 class="text-info"><?php echo $total_views; ?></h4>
                        <small class="text-muted">Ad Views (<?php echo $total_ads; ?> ads)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-box text-warning fs-2 mb-2"></i>
                        <h4 class="text-warning"><?php echo $analytics['low_stock_count']; ?></h4>
                        <small class="text-muted">Low Stock Items</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-star text-primary fs-2 mb-2"></i>
                        <h4 class="text-primary"><?php echo $analytics['optimization_score']; ?>%</h4>
                        <small class="text-muted">Optimization Score</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- NEW: Feature Utilization Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">🎡 Spin Wheels</span>
                            <span class="badge bg-primary"><?php echo count($analytics['spin_wheels'] ?? []); ?></span>
                        </div>
                        <?php if (!empty($analytics['spin_wheels'])): ?>
                            <?php 
                            $total_spins = array_sum(array_column($analytics['spin_wheels'], 'total_spins'));
                            ?>
                            <h6 class="text-success mb-0"><?php echo $total_spins; ?> Total Spins</h6>
                        <?php else: ?>
                            <h6 class="text-muted mb-0">No spin wheels yet</h6>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">🍕 Pizza Trackers</span>
                            <span class="badge bg-warning"><?php echo count($analytics['pizza_trackers'] ?? []); ?></span>
                        </div>
                        <?php if (!empty($analytics['pizza_trackers'])): ?>
                            <?php 
                            $completed_trackers = array_filter($analytics['pizza_trackers'], function($t) { return $t['is_complete']; });
                            ?>
                            <h6 class="text-info mb-0"><?php echo count($completed_trackers); ?> Completed Goals</h6>
                        <?php else: ?>
                            <h6 class="text-muted mb-0">No trackers yet</h6>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">📱 QR Codes</span>
                            <?php 
                            $total_qr_codes = !empty($analytics['qr_performance']) ? array_sum(array_column($analytics['qr_performance'], 'total_qr_codes')) : 0;
                            $total_scans = !empty($analytics['qr_performance']) ? array_sum(array_column($analytics['qr_performance'], 'total_scans')) : 0;
                            ?>
                            <span class="badge bg-info"><?php echo $total_qr_codes; ?></span>
                        </div>
                        <h6 class="text-success mb-0"><?php echo $total_scans; ?> Total Scans</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">🎯 Campaigns</span>
                            <?php 
                            $total_campaigns = count($analytics['campaign_performance'] ?? []);
                            $active_campaigns = !empty($analytics['campaign_performance']) ? 
                                count(array_filter($analytics['campaign_performance'], function($c) { return $c['status'] === 'active'; })) : 0;
                            ?>
                            <span class="badge bg-success"><?php echo $active_campaigns; ?>/<?php echo $total_campaigns; ?></span>
                        </div>
                        <h6 class="text-primary mb-0">
                            <?php 
                            $total_votes = !empty($analytics['campaign_performance']) ? 
                                array_sum(array_column($analytics['campaign_performance'], 'total_votes')) : 0;
                            echo $total_votes; 
                            ?> Votes
                        </h6>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Balance Info -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-info-circle me-3 mt-1"></i>
                        <div>
                            <h6 class="mb-2"><strong>AI Assistant Usage Balance</strong></h6>
                            <p class="mb-2">We've designed a balanced approach to AI interactions:</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="mb-0 small">
                                        <li><strong>Weekly Auto-Updates:</strong> Insights refresh automatically based on your latest data</li>
                                        <li><strong>Daily Manual Refresh:</strong> Force refresh insights once per day if needed</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="mb-0 small">
                                        <li><strong>Daily Chat Limit:</strong> Up to <?php echo $max_daily_chats; ?> AI conversations per day</li>
                                        <li><strong>Quality Focus:</strong> Ensures thoughtful questions and meaningful responses</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row g-4">
            <!-- AI Insights Column -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-lightbulb me-2"></i>AI-Generated Insights & Recommendations
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="insights-container">
                            <?php foreach ($insights['recommendations'] as $insight): ?>
                                <div class="insight-card insight-priority-<?php echo $insight['priority']; ?> p-3 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">
                                            <i class="bi <?php echo $insight['icon']; ?> text-<?php echo $insight['color']; ?> fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-2"><?php echo htmlspecialchars($insight['title']); ?></h6>
                                            <p class="mb-2 text-muted"><?php echo htmlspecialchars($insight['description']); ?></p>
                                            <?php if (!empty($insight['action'])): ?>
                                                <div class="mt-2">
                                                    <strong>Recommended Action:</strong>
                                                    <span class="text-info"><?php echo htmlspecialchars($insight['action']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($insight['impact'])): ?>
                                                <div class="mt-1">
                                                    <small class="text-success">
                                                        <i class="bi bi-arrow-up me-1"></i>
                                                        Potential Impact: <?php echo htmlspecialchars($insight['impact']); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ms-3">
                                            <span class="badge bg-<?php echo $insight['priority'] === 'high' ? 'danger' : ($insight['priority'] === 'medium' ? 'warning' : 'success'); ?>">
                                                <?php echo ucfirst($insight['priority']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Sales Optimization Section -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up me-2"></i>Sales Optimization Opportunities
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($insights['sales_opportunities'] as $opportunity): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="p-3 border rounded">
                                        <h6 class="text-primary"><?php echo htmlspecialchars($opportunity['title']); ?></h6>
                                        <p class="small text-muted mb-2"><?php echo htmlspecialchars($opportunity['description']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-success">+<?php echo $opportunity['revenue_increase']; ?>% Revenue</span>
                                            <small class="text-muted"><?php echo $opportunity['difficulty']; ?> to implement</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Chat Assistant -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 d-flex justify-content-between align-items-center">
                            <span>
                                <i class="bi bi-chat-dots me-2"></i>Ask AI Assistant
                            </span>
                            <small class="badge bg-info">
                                <?php echo ($max_daily_chats - $daily_chat_count); ?> chats left today
                            </small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="ai-chat-container p-3 mb-3" id="chat-container">
                            <div class="chat-message mb-2">
                                <div class="d-flex align-items-start">
                                    <div class="bg-primary text-white rounded-circle p-2 me-2">
                                        <i class="bi bi-robot"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted">AI Assistant</small>
                                        <p class="mb-0">Hello! I'm here to help you optimize your vending business. Ask me about sales trends, inventory optimization, or pricing strategies.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <form id="ai-chat-form">
                            <div class="input-group">
                                <input type="text" class="form-control" id="chat-input" 
                                       placeholder="<?php echo $can_chat ? 'Ask me anything about your business...' : 'Daily chat limit reached (10 chats)'; ?>"
                                       <?php echo !$can_chat ? 'disabled' : ''; ?>>
                                <button class="btn btn-primary" type="submit" id="send-chat"
                                        <?php echo !$can_chat ? 'disabled' : ''; ?>>
                                    <i class="bi bi-send"></i>
                                </button>
                            </div>
                        </form>
                        <div class="mt-2">
                            <?php if ($can_chat): ?>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Try asking: "What items should I stock more?", "How can I increase profits?", "What combos work best?"
                                </small>
                            <?php else: ?>
                                <small class="text-warning">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    You've reached your daily chat limit of <?php echo $max_daily_chats; ?> interactions. 
                                    This resets at midnight to maintain balanced AI usage.
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-lightning me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="generatePricingReport()">
                                <i class="bi bi-currency-dollar me-2"></i>Pricing Analysis
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="generateStockingReport()">
                                <i class="bi bi-box me-2"></i>Stocking Recommendations
                            </button>
                            <button class="btn btn-outline-warning btn-sm" onclick="generateComboReport()">
                                <i class="bi bi-layers me-2"></i>Combo Opportunities
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="generateTrendReport()">
                                <i class="bi bi-graph-up me-2"></i>Trend Analysis
                            </button>
                            <?php if (empty($analytics['casino_participation']['casino_enabled'])): ?>
                            <button class="btn btn-outline-success btn-sm" onclick="generateCasinoAdvice()">
                                <i class="bi bi-piggy-bank me-2"></i>Casino Revenue Opportunity
                            </button>
                            <?php else: ?>
                            <button class="btn btn-outline-primary btn-sm" onclick="generateCasinoOptimization()">
                                <i class="bi bi-gear me-2"></i>Casino Optimization
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-outline-danger btn-sm" onclick="generatePromotionalStrategy()">
                                <i class="bi bi-megaphone me-2"></i>Promotional Strategy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// AI Assistant JavaScript - Declare all global variables first
var canChat = <?php echo $can_chat ? 'true' : 'false'; ?>;
var canRefresh = <?php echo $can_refresh ? 'true' : 'false'; ?>;
var dailyChatCount = <?php echo $daily_chat_count; ?>;
var maxDailyChats = <?php echo $max_daily_chats; ?>;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const chatForm = document.getElementById('ai-chat-form');
    const chatInput = document.getElementById('chat-input');
    const chatContainer = document.getElementById('chat-container');
    const sendButton = document.getElementById('send-chat');
    const refreshButton = document.getElementById('refresh-insights');

    // Chat form submission
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!canChat || dailyChatCount >= maxDailyChats) {
            addChatMessage('system', 'Daily chat limit reached. Please try again tomorrow.');
            return;
        }
        const message = chatInput.value.trim();
        if (message) {
            sendChatMessage(message);
            chatInput.value = '';
        }
    });

    // Refresh insights
    refreshButton.addEventListener('click', function() {
        if (!canRefresh) {
            alert('Insights have already been refreshed today. Automatic refresh happens weekly, or you can refresh once daily.');
            return;
        }
        refreshInsights();
    });
});

// Chat message sending function
function sendChatMessage(message) {
    // Check if we've hit the limit
    if (dailyChatCount >= maxDailyChats) {
        addChatMessage('system', 'Daily chat limit reached. Please try again tomorrow.');
        return;
    }
    
    const chatContainer = document.getElementById('chat-container');
    
    // Add user message
    addChatMessage('user', message);
    
    // Show typing indicator
    showTypingIndicator();
    
    // Debug: Log the request data
    const requestData = {
        message: message,
        business_id: <?php echo $business_id; ?>
    };
    console.log('Sending AI request:', requestData);
    console.log('API URL:', '<?php echo APP_URL; ?>/api/ai-chat.php');
    
    // Send to AI API
    fetch('<?php echo APP_URL; ?>/api/ai-chat.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        hideTypingIndicator();
        if (data.success) {
            addChatMessage('ai', data.response);
            dailyChatCount++; // Increment local counter
            updateChatCounter();
        } else {
            console.error('AI API returned error:', data.error || data.response);
            addChatMessage('ai', data.response || 'I apologize, but I encountered an error. Please try again.');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        hideTypingIndicator();
        addChatMessage('ai', 'I apologize, but I encountered an error. Please try again.');
    });
}

// Update chat counter
function updateChatCounter() {
    const counter = document.querySelector('.badge.bg-info');
    if (counter) {
        const remaining = maxDailyChats - dailyChatCount;
        counter.textContent = remaining + ' chats left today';
        
        if (remaining <= 0) {
            // Disable chat when limit reached
            document.getElementById('chat-input').disabled = true;
            document.getElementById('send-chat').disabled = true;
            document.getElementById('chat-input').placeholder = 'Daily chat limit reached (10 chats)';
            
            // Update the info text
            const infoDiv = document.querySelector('.mt-2');
            if (infoDiv) {
                infoDiv.innerHTML = `
                    <small class="text-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        You've reached your daily chat limit of ${maxDailyChats} interactions. 
                        This resets at midnight to maintain balanced AI usage.
                    </small>
                `;
            }
        }
    }
}

function addChatMessage(sender, message) {
    const chatContainer = document.getElementById('chat-container');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'chat-message mb-2';
    
    const isUser = sender === 'user';
    const isSystem = sender === 'system';
    const iconClass = isUser ? 'bi-person-circle' : (isSystem ? 'bi-info-circle' : 'bi-robot');
    const bgClass = isUser ? 'bg-secondary' : (isSystem ? 'bg-warning' : 'bg-primary');
    const senderName = isUser ? 'You' : (isSystem ? 'System' : 'AI Assistant');
    
    messageDiv.innerHTML = `
        <div class="d-flex align-items-start">
            <div class="${bgClass} text-white rounded-circle p-2 me-2">
                <i class="bi ${iconClass}"></i>
            </div>
            <div class="flex-grow-1">
                <small class="text-muted">${senderName}</small>
                <p class="mb-0">${message}</p>
            </div>
        </div>
    `;
    
    chatContainer.appendChild(messageDiv);
    chatContainer.scrollTop = chatContainer.scrollHeight;
}

function showTypingIndicator() {
    const indicator = document.createElement('div');
    indicator.id = 'typing-indicator';
    indicator.className = 'chat-message mb-2';
    indicator.innerHTML = `
        <div class="d-flex align-items-start">
            <div class="bg-primary text-white rounded-circle p-2 me-2">
                <i class="bi bi-robot"></i>
            </div>
            <div class="flex-grow-1">
                <small class="text-muted">AI Assistant</small>
                <p class="mb-0">
                    <span class="loading-spinner d-inline-block me-2"></span>
                    Thinking...
                </p>
            </div>
        </div>
    `;
    document.getElementById('chat-container').appendChild(indicator);
}

function hideTypingIndicator() {
    const indicator = document.getElementById('typing-indicator');
    if (indicator) {
        indicator.remove();
    }
}

function refreshInsights() {
    const button = document.getElementById('refresh-insights');
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="loading-spinner me-2"></span>Refreshing...';
    button.disabled = true;
    
    console.log('Starting insights refresh for business ID:', <?php echo $business_id; ?>);
    
    fetch('<?php echo APP_URL; ?>/api/refresh-insights.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            business_id: <?php echo $business_id; ?>
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            // Update insights display without page reload
            if (data.insights && data.insights.recommendations) {
                updateInsightsDisplay(data.insights);
                
                // Update the timestamp info
                const debugInfo = document.querySelector('.small.text-info');
                if (debugInfo) {
                    debugInfo.innerHTML = `
                        <i class="bi bi-lightning me-1"></i>Fresh insights generated
                        <br>
                        <small>Updated: ${new Date().toLocaleString()}</small>
                    `;
                }
                
                // Show success message
                showMessage('success', `Insights refreshed successfully! Generated ${data.insights_count} recommendations.`);
            } else {
                console.warn('No insights data in response');
                location.reload(); // Fallback to page reload
            }
        } else {
            console.error('Refresh failed:', data.error);
            if (data.debug_info) {
                console.error('Debug info:', data.debug_info);
            }
            showMessage('error', data.error || 'Failed to refresh insights. Please try again.');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showMessage('error', 'Network error. Please check your connection and try again.');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function updateInsightsDisplay(insights) {
    const insightsContainer = document.getElementById('insights-container');
    if (!insightsContainer) return;
    
    // Clear existing insights
    insightsContainer.innerHTML = '';
    
    // Add new insights
    insights.recommendations.forEach(insight => {
        const insightElement = document.createElement('div');
        insightElement.className = `insight-card insight-priority-${insight.priority} p-3 mb-3`;
        
        insightElement.innerHTML = `
            <div class="d-flex align-items-start">
                <div class="me-3">
                    <i class="bi ${insight.icon} text-${insight.color} fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-2">${escapeHtml(insight.title)}</h6>
                    <p class="mb-2 text-muted">${escapeHtml(insight.description)}</p>
                    ${insight.action ? `
                        <div class="mt-2">
                            <strong>Recommended Action:</strong>
                            <span class="text-info">${escapeHtml(insight.action)}</span>
                        </div>
                    ` : ''}
                    ${insight.impact ? `
                        <div class="mt-1">
                            <small class="text-success">
                                <i class="bi bi-arrow-up me-1"></i>
                                Potential Impact: ${escapeHtml(insight.impact)}
                            </small>
                        </div>
                    ` : ''}
                </div>
                <div class="ms-3">
                    <span class="badge bg-${insight.priority === 'high' ? 'danger' : (insight.priority === 'medium' ? 'warning' : 'success')}">
                        ${insight.priority.charAt(0).toUpperCase() + insight.priority.slice(1)}
                    </span>
                </div>
            </div>
        `;
        
        insightsContainer.appendChild(insightElement);
    });
    
    // Update the insights count in the stats section
    const insightsCountElement = document.querySelector('.text-primary h4');
    if (insightsCountElement) {
        insightsCountElement.textContent = insights.recommendations.length;
    }
}

function showMessage(type, message) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show mt-3`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert after the header card
    const headerCard = document.querySelector('.card');
    if (headerCard && headerCard.parentNode) {
        headerCard.parentNode.insertBefore(alertDiv, headerCard.nextSibling);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Quick action functions
function generatePricingReport() {
    addChatMessage('user', 'Generate a pricing analysis report');
    sendChatMessage('Generate a pricing analysis report for my business');
}

function generateStockingReport() {
    addChatMessage('user', 'What stocking recommendations do you have?');
    sendChatMessage('What stocking recommendations do you have for my business?');
}

function generateComboReport() {
    addChatMessage('user', 'What combo opportunities should I consider?');
    sendChatMessage('What combo opportunities should I consider for my vending machines?');
}

function generateTrendReport() {
    addChatMessage('user', 'Show me trend analysis for my business');
    sendChatMessage('Show me trend analysis for my business');
}

function generateCasinoAdvice() {
    addChatMessage('user', 'How can I start earning revenue from casino features?');
    sendChatMessage('How can I start earning revenue from casino features? What are the benefits and setup process?');
}

function generateCasinoOptimization() {
    addChatMessage('user', 'How can I optimize my casino revenue?');
    sendChatMessage('How can I optimize my casino revenue? Should I create promotional ads or adjust my location bonus?');
}

function generatePromotionalStrategy() {
    addChatMessage('user', 'What promotional advertising strategy should I use?');
    sendChatMessage('What promotional advertising strategy should I use? How can I improve my promotional ads performance?');
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 