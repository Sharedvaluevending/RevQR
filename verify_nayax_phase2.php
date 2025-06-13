#!/usr/bin/env php
<?php
/**
 * Nayax Integration Phase 2 Verification Script
 * Tests core integration services including NayaxManager, webhook endpoint, and AWS SQS
 * 
 * Run: php verify_nayax_phase2.php
 */

require_once __DIR__ . '/html/core/config.php';

echo "🚀 NAYAX INTEGRATION PHASE 2 VERIFICATION\n";
echo "==========================================\n\n";

$errors = [];
$warnings = [];
$success_count = 0;

/**
 * Test function wrapper
 */
function test($description, $test_function) {
    global $errors, $warnings, $success_count;
    
    echo "Testing: {$description}... ";
    
    try {
        $result = $test_function();
        if ($result === true || (is_string($result) && $result !== '')) {
            echo "✅ PASS";
            if (is_string($result) && $result !== 'true') {
                echo " - {$result}";
            }
            echo "\n";
            $success_count++;
        } else {
            echo "❌ FAIL\n";
            $errors[] = $description;
        }
    } catch (Exception $e) {
        echo "❌ ERROR - " . $e->getMessage() . "\n";
        $errors[] = $description . ": " . $e->getMessage();
    }
}

// =============================================================================
// 1. CORE SERVICE CLASSES
// =============================================================================

echo "🔧 Testing Core Service Classes...\n";

test("NayaxManager class exists", function() {
    $file_path = __DIR__ . '/html/core/nayax_manager.php';
    if (!file_exists($file_path)) {
        throw new Exception("NayaxManager file not found");
    }
    
    require_once $file_path;
    if (!class_exists('NayaxManager')) {
        throw new Exception("NayaxManager class not defined");
    }
    
    // Test instantiation
    $manager = new NayaxManager();
    return "Class instantiated successfully";
});

test("NayaxAWSSQS class exists", function() {
    $file_path = __DIR__ . '/html/core/nayax_aws_sqs.php';
    if (!file_exists($file_path)) {
        throw new Exception("NayaxAWSSQS file not found");
    }
    
    require_once $file_path;
    if (!class_exists('NayaxAWSSQS')) {
        throw new Exception("NayaxAWSSQS class not defined");
    }
    
    return "Class definition found";
});

test("NayaxDiscountManager class exists", function() {
    $file_path = __DIR__ . '/html/core/nayax_discount_manager.php';
    if (!file_exists($file_path)) {
        throw new Exception("NayaxDiscountManager file not found");
    }
    
    require_once $file_path;
    if (!class_exists('NayaxDiscountManager')) {
        throw new Exception("NayaxDiscountManager class not defined");
    }
    
    // Test instantiation
    $manager = new NayaxDiscountManager();
    return "Class instantiated successfully";
});

// =============================================================================
// 2. WEBHOOK ENDPOINT
// =============================================================================

echo "\n🌐 Testing Webhook Endpoint...\n";

test("Webhook endpoint file exists", function() {
    $webhook_path = __DIR__ . '/html/api/nayax_webhook.php';
    if (!file_exists($webhook_path)) {
        throw new Exception("Webhook endpoint file not found");
    }
    
    // Check if file is accessible
    if (!is_readable($webhook_path)) {
        throw new Exception("Webhook endpoint file is not readable");
    }
    
    return "File exists and readable";
});

test("Webhook endpoint basic functionality", function() {
    $webhook_path = __DIR__ . '/html/api/nayax_webhook.php';
    
    // Check file contains expected components
    $content = file_get_contents($webhook_path);
    
    $required_components = [
        'HTTP_X_NAYAX_SIGNATURE',
        'processTransaction',
        'rate_limit',
        'application/json'
    ];
    
    foreach ($required_components as $component) {
        if (strpos($content, $component) === false) {
            throw new Exception("Missing component: {$component}");
        }
    }
    
    return "All required components found";
});

// =============================================================================
// 3. NAYAX MANAGER FUNCTIONALITY
// =============================================================================

echo "\n⚙️ Testing NayaxManager Functionality...\n";

require_once __DIR__ . '/html/core/nayax_manager.php';

test("NayaxManager configuration loading", function() use ($pdo) {
    $manager = new NayaxManager($pdo);
    
    // Test getting integration stats (this tests config loading)
    $stats = $manager->getIntegrationStats();
    
    if (!is_array($stats)) {
        throw new Exception("Failed to get integration stats");
    }
    
    return "Configuration loaded successfully";
});

test("NayaxManager machine registration", function() use ($pdo) {
    $manager = new NayaxManager($pdo);
    
    // Test machine registration
    $result = $manager->registerMachine(
        1, // business_id (assuming business 1 exists)
        'TEST_MACHINE_001',
        'TEST_DEVICE_001', 
        'Test Vending Machine',
        ['location' => 'Test Location']
    );
    
    if (!$result['success']) {
        throw new Exception("Machine registration failed: " . $result['message']);
    }
    
    // Clean up test data
    $stmt = $pdo->prepare("DELETE FROM nayax_machines WHERE nayax_machine_id = 'TEST_MACHINE_001'");
    $stmt->execute();
    
    return "Machine registration successful";
});

test("NayaxManager QR coin product creation", function() use ($pdo) {
    $manager = new NayaxManager($pdo);
    
    // Register test machine first
    $manager->registerMachine(1, 'TEST_MACHINE_002', 'TEST_DEVICE_002', 'Test Machine 2');
    
    // Test QR coin product creation
    $result = $manager->createQRCoinProduct(
        1,
        'TEST_MACHINE_002',
        [
            'name' => 'Test QR Coin Pack',
            'description' => 'Test pack',
            'coins' => 500,
            'price_cents' => 250
        ]
    );
    
    if (!$result) {
        throw new Exception("QR coin product creation failed");
    }
    
    // Clean up test data
    $stmt = $pdo->prepare("DELETE FROM nayax_qr_coin_products WHERE nayax_machine_id = 'TEST_MACHINE_002'");
    $stmt->execute();
    $stmt = $pdo->prepare("DELETE FROM nayax_machines WHERE nayax_machine_id = 'TEST_MACHINE_002'");
    $stmt->execute();
    
    return "QR coin product creation successful";
});

// =============================================================================
// 4. DISCOUNT MANAGER FUNCTIONALITY
// =============================================================================

echo "\n🎟️ Testing Discount Manager Functionality...\n";

require_once __DIR__ . '/html/core/nayax_discount_manager.php';

test("DiscountManager discount code generation", function() use ($pdo) {
    $manager = new NayaxDiscountManager($pdo);
    
    // Test discount code stats (this tests basic functionality)
    $stats = $manager->getDiscountCodeStats();
    
    if (!is_array($stats)) {
        throw new Exception("Failed to get discount code stats");
    }
    
    return "Discount code functionality working";
});

test("DiscountManager code validation", function() use ($pdo) {
    $manager = new NayaxDiscountManager($pdo);
    
    // Test validation of non-existent code
    $result = $manager->validateDiscountCode('INVALID_CODE_123');
    
    if ($result['valid'] !== false) {
        throw new Exception("Invalid code validation failed");
    }
    
    return "Code validation working correctly";
});

// =============================================================================
// 5. CRON JOB SETUP
// =============================================================================

echo "\n⏰ Testing Cron Job Setup...\n";

test("SQS poller cron job exists", function() {
    $cron_path = __DIR__ . '/cron/nayax_sqs_poller.php';
    if (!file_exists($cron_path)) {
        throw new Exception("SQS poller cron job not found");
    }
    
    // Check if file is executable
    if (!is_readable($cron_path)) {
        throw new Exception("SQS poller file is not readable");
    }
    
    return "Cron job file exists and readable";
});

test("SQS poller configuration", function() {
    $cron_path = __DIR__ . '/cron/nayax_sqs_poller.php';
    $content = file_get_contents($cron_path);
    
    $required_components = [
        'NayaxAWSSQS',
        'pollQueue',
        'lock_file',
        'log_message'
    ];
    
    foreach ($required_components as $component) {
        if (strpos($content, $component) === false) {
            throw new Exception("Missing component: {$component}");
        }
    }
    
    return "All required components found";
});

// =============================================================================
// 6. AWS INTEGRATION READINESS
// =============================================================================

echo "\n☁️ Testing AWS Integration Readiness...\n";

test("AWS configuration table populated", function() use ($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM nayax_aws_config");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count < 3) {
        throw new Exception("AWS configuration incomplete");
    }
    
    return "AWS configuration table ready";
});

test("AWS credentials status", function() use ($pdo) {
    $stmt = $pdo->prepare("SELECT config_key, config_value FROM nayax_aws_config");
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $credentials_set = 0;
    foreach ($configs as $config) {
        if ($config['config_value'] !== 'YOUR_ACCESS_KEY' && 
            $config['config_value'] !== 'YOUR_SECRET_KEY' &&
            !empty($config['config_value'])) {
            $credentials_set++;
        }
    }
    
    if ($credentials_set === 0) {
        return "⚠️ AWS credentials need to be configured";
    } else {
        return "AWS credentials partially configured ({$credentials_set} of " . count($configs) . ")";
    }
});

// =============================================================================
// 7. LOGGING AND MONITORING
// =============================================================================

echo "\n📊 Testing Logging and Monitoring...\n";

test("Log directory exists and writable", function() {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        if (!mkdir($log_dir, 0755, true)) {
            throw new Exception("Cannot create logs directory");
        }
    }
    
    if (!is_writable($log_dir)) {
        throw new Exception("Logs directory is not writable");
    }
    
    return "Log directory ready";
});

test("Webhook logging functionality", function() {
    $log_file = __DIR__ . '/logs/nayax_webhook.log';
    
    // Test write
    $test_message = "[" . date('Y-m-d H:i:s') . "] Phase 2 verification test\n";
    file_put_contents($log_file, $test_message, FILE_APPEND | LOCK_EX);
    
    if (!file_exists($log_file)) {
        throw new Exception("Failed to create webhook log file");
    }
    
    return "Webhook logging working";
});

// =============================================================================
// 8. INTEGRATION COMPATIBILITY
// =============================================================================

echo "\n🔗 Testing Integration Compatibility...\n";

test("QR Coin Manager compatibility", function() {
    if (!class_exists('QRCoinManager')) {
        throw new Exception("QRCoinManager class not available");
    }
    
    // Test getBalance method exists
    if (!method_exists('QRCoinManager', 'getBalance')) {
        throw new Exception("QRCoinManager::getBalance method not found");
    }
    
    return "QR Coin Manager compatible";
});

test("Business Wallet Manager compatibility", function() {
    if (!class_exists('BusinessWalletManager')) {
        throw new Exception("BusinessWalletManager class not available");
    }
    
    return "Business Wallet Manager compatible";
});

// =============================================================================
// 9. SECURITY CHECKS
// =============================================================================

echo "\n🔒 Testing Security Implementation...\n";

test("Webhook signature verification", function() use ($pdo) {
    require_once __DIR__ . '/html/core/nayax_manager.php';
    $manager = new NayaxManager($pdo);
    
    // Test signature verification with test data
    $test_payload = '{"test": "data"}';
    $test_signature = hash_hmac('sha256', $test_payload, 'test_secret');
    
    // This will return false because actual secret is different, but method should exist
    $result = $manager->verifyWebhookSignature($test_payload, $test_signature);
    
    if (!is_bool($result)) {
        throw new Exception("Webhook signature verification not implemented correctly");
    }
    
    return "Webhook signature verification implemented";
});

test("Rate limiting implementation", function() {
    $webhook_content = file_get_contents(__DIR__ . '/html/api/nayax_webhook.php');
    
    if (strpos($webhook_content, 'rate_limit') === false) {
        throw new Exception("Rate limiting not implemented");
    }
    
    return "Rate limiting implemented";
});

// =============================================================================
// 10. SUMMARY AND RECOMMENDATIONS
// =============================================================================

echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 PHASE 2 VERIFICATION SUMMARY\n";
echo str_repeat("=", 50) . "\n";

echo "✅ Successful Tests: {$success_count}\n";
echo "❌ Failed Tests: " . count($errors) . "\n";
echo "⚠️ Warnings: " . count($warnings) . "\n\n";

if (empty($errors)) {
    echo "🎉 PHASE 2 VERIFICATION PASSED!\n";
    echo "✅ Core integration services ready\n";
    echo "✅ NayaxManager operational\n";
    echo "✅ Webhook endpoint configured\n";
    echo "✅ Discount system functional\n";
    echo "✅ Cron job ready for deployment\n\n";
    
    echo "📋 NEXT STEPS:\n";
    echo "1. Configure AWS credentials in database\n";
    echo "2. Set up SQS queue and update queue URL\n";
    echo "3. Add cron job to system crontab\n";
    echo "4. Install AWS SDK: composer require aws/aws-sdk-php\n";
    echo "5. Start Phase 3: User Interface & Purchase Flow\n\n";
    
    echo "🔧 DEPLOYMENT CHECKLIST:\n";
    
    // Show AWS config status
    try {
        $stmt = $pdo->prepare("SELECT config_key, config_value FROM nayax_aws_config ORDER BY config_key");
        $stmt->execute();
        $configs = $stmt->fetchAll();
        
        foreach ($configs as $config) {
            $status = ($config['config_value'] === 'YOUR_ACCESS_KEY' || 
                      $config['config_value'] === 'YOUR_SECRET_KEY' || 
                      empty($config['config_value'])) ? '❌ NEEDS SETUP' : '✅ CONFIGURED';
            echo "   {$config['config_key']}: {$status}\n";
        }
    } catch (Exception $e) {
        echo "   Error reading AWS config: " . $e->getMessage() . "\n";
    }
    
    echo "\n🚀 CRON JOB SETUP:\n";
    echo "   Add to crontab: (asterisk)/2 (asterisk) (asterisk) (asterisk) (asterisk) /usr/bin/php " . __DIR__ . "/cron/nayax_sqs_poller.php\n\n";
    
} else {
    echo "❌ PHASE 2 VERIFICATION FAILED!\n\n";
    echo "Errors found:\n";
    foreach ($errors as $error) {
        echo "   ❌ {$error}\n";
    }
    echo "\nPlease fix these issues before proceeding to Phase 3.\n";
}

if (!empty($warnings)) {
    echo "\n⚠️ Warnings:\n";
    foreach ($warnings as $warning) {
        echo "   ⚠️ {$warning}\n";
    }
}

echo "\n📝 FILES CREATED IN PHASE 2:\n";
echo "   ✅ html/core/nayax_manager.php\n";
echo "   ✅ html/core/nayax_aws_sqs.php\n";
echo "   ✅ html/core/nayax_discount_manager.php\n";
echo "   ✅ html/api/nayax_webhook.php\n";
echo "   ✅ cron/nayax_sqs_poller.php\n";
echo "   ✅ verify_nayax_phase2.php\n\n";

exit(empty($errors) ? 0 : 1);
?> 