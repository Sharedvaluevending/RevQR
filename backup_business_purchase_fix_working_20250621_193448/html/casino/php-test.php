<?php
// Basic PHP Test - No output before session_start()
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to prevent header issues
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Casino Test</title>
    <style>
        body { font-family: monospace; margin: 20px; }
        .step { margin: 10px 0; padding: 10px; border: 1px solid #ccc; }
        .success { background: #d4edda; }
        .error { background: #f8d7da; }
        .warning { background: #fff3cd; }
    </style>
</head>
<body>

<h1>🔧 PHP Casino Test</h1>

<div class='step'>
    <h3>Step 1: Basic PHP</h3>
    <div class='success'>✅ PHP is working</div>
    <div class='success'>✅ Current time: <?php echo date('Y-m-d H:i:s'); ?></div>
</div>

<div class='step'>
    <h3>Step 2: Session Check</h3>
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class='success'>✅ User logged in: <?php echo $_SESSION['user_id']; ?></div>
    <?php else: ?>
        <div class='warning'>⚠️ No active session - need to login</div>
        <a href="../user/login.php">Login Here</a>
    <?php endif; ?>
</div>

<div class='step'>
    <h3>Step 3: Config File</h3>
    <?php
    $config_path = __DIR__ . '/../core/config.php';
    if (file_exists($config_path)):
    ?>
        <div class='success'>✅ Config file exists</div>
        <?php
        try {
            require_once $config_path;
            echo "<div class='success'>✅ Config loaded successfully</div>";
            echo "<div class='success'>✅ APP_URL: " . (defined('APP_URL') ? APP_URL : 'NOT DEFINED') . "</div>";
        } catch (Exception $e) {
            echo "<div class='error'>❌ Config error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    <?php else: ?>
        <div class='error'>❌ Config file missing</div>
    <?php endif; ?>
</div>

<div class='step'>
    <h3>Step 4: Database</h3>
    <?php if (isset($pdo)): ?>
        <div class='success'>✅ Database connection exists</div>
        <?php
        try {
            $stmt = $pdo->query("SELECT 1 as test");
            $result = $stmt->fetch();
            echo "<div class='success'>✅ Database query works</div>";
        } catch (Exception $e) {
            echo "<div class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    <?php else: ?>
        <div class='error'>❌ No database connection</div>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['user_id']) && isset($pdo)): ?>
<div class='step'>
    <h3>Step 5: User Data</h3>
    <?php
    try {
        $stmt = $pdo->prepare("SELECT id, qr_coins, business_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "<div class='success'>✅ User found</div>";
            echo "<div class='success'>Balance: " . number_format($user['qr_coins']) . " QR Coins</div>";
            echo "<div class='success'>Business ID: " . ($user['business_id'] ?: 'None') . "</div>";
        } else {
            echo "<div class='error'>❌ User not found</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ User query error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
</div>

<div class='step'>
    <h3>Step 6: Casino Business Check</h3>
    <?php
    $location_id = $_GET['location_id'] ?? 1;
    echo "<div class='success'>Testing location ID: $location_id</div>";
    
    try {
        $stmt = $pdo->prepare("
            SELECT b.id, b.business_name, b.is_casino_enabled,
                   bcp.casino_enabled as participation_enabled
            FROM businesses b
            LEFT JOIN business_casino_participation bcp ON b.id = bcp.business_id
            WHERE b.id = ?
        ");
        $stmt->execute([$location_id]);
        $business = $stmt->fetch();
        
        if ($business) {
            echo "<div class='success'>✅ Business found: " . htmlspecialchars($business['business_name']) . "</div>";
            echo "<div class='success'>Casino enabled (old): " . ($business['is_casino_enabled'] ? 'Yes' : 'No') . "</div>";
            echo "<div class='success'>Casino participation: " . ($business['participation_enabled'] ? 'Yes' : 'No') . "</div>";
            
            if ($business['is_casino_enabled'] || $business['participation_enabled']) {
                echo "<div class='success'>✅ Casino is enabled for this business</div>";
            } else {
                echo "<div class='warning'>⚠️ Casino not enabled for this business</div>";
            }
        } else {
            echo "<div class='error'>❌ Business not found for ID: $location_id</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Business query error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
</div>
<?php endif; ?>

<div class='step'>
    <h3>Step 7: File Checks</h3>
    <?php
    $files_to_check = [
        'blackjack.php' => __DIR__ . '/blackjack.php',
        'blackjack.js' => __DIR__ . '/js/blackjack.js',
        'header.php' => __DIR__ . '/../core/includes/header.php',
        'footer.php' => __DIR__ . '/../core/includes/footer.php'
    ];
    
    foreach ($files_to_check as $name => $path) {
        if (file_exists($path)) {
            $size = filesize($path);
            echo "<div class='success'>✅ $name exists (" . number_format($size) . " bytes)</div>";
        } else {
            echo "<div class='error'>❌ $name missing: $path</div>";
        }
    }
    ?>
</div>

<div class='step'>
    <h3>Step 8: Test Links</h3>
    <div class='success'>Try these test links:</div>
    <p>
        <a href="simple-test.php" target="_blank" style="display:inline-block;padding:8px 12px;background:#28a745;color:white;text-decoration:none;border-radius:4px;margin:5px;">🧪 Simple Test</a>
        <a href="blackjack-simple.php?location_id=1" target="_blank" style="display:inline-block;padding:8px 12px;background:#007bff;color:white;text-decoration:none;border-radius:4px;margin:5px;">🃏 Simple Blackjack</a>
        <a href="blackjack.php?location_id=1" target="_blank" style="display:inline-block;padding:8px 12px;background:#dc3545;color:white;text-decoration:none;border-radius:4px;margin:5px;">🎰 Full Blackjack</a>
    </p>
</div>

<div class='step'>
    <h3>🎯 Summary</h3>
    <?php if (isset($_SESSION['user_id']) && isset($pdo) && isset($user) && $user): ?>
        <div class='success'>✅ All systems appear to be working</div>
        <div class='success'>✅ You should be able to play blackjack</div>
        <div class='warning'>If blackjack still shows blank, check browser console for JavaScript errors</div>
    <?php else: ?>
        <div class='warning'>⚠️ Some issues found - see details above</div>
    <?php endif; ?>
</div>

<hr>
<p><a href="index.php">← Back to Casino</a></p>

</body>
</html>

<?php
// Flush output buffer
ob_end_flush();
?> 