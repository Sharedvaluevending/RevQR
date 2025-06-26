<?php
// Simplest possible test
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Test</title>
</head>
<body>
    <h1>🧪 Simple PHP Test</h1>
    
    <p>✅ PHP is working!</p>
    <p>✅ Time: <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <?php if (isset($_SESSION['user_id'])): ?>
        <p>✅ User logged in: <?php echo $_SESSION['user_id']; ?></p>
    <?php else: ?>
        <p>⚠️ Not logged in - <a href="../user/login.php">Login</a></p>
    <?php endif; ?>
    
    <hr>
    <p><a href="php-test.php">Full PHP Test</a> | <a href="index.php">Casino</a></p>
</body>
</html> 