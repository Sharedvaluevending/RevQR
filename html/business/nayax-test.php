<?php
// Minimal Nayax settings page - bypassing all session issues for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try different database configurations
$configs = [
    ['host' => 'localhost', 'dbname' => 'qr', 'username' => 'qr_user', 'password' => ''],
    ['host' => 'localhost', 'dbname' => 'qr', 'username' => 'root', 'password' => ''],
    ['host' => 'localhost', 'dbname' => 'qr', 'username' => 'qr_user', 'password' => 'password'],
    ['host' => 'localhost', 'dbname' => 'qr', 'username' => 'root', 'password' => 'password']
];

$pdo = null;
$db_error = '';
foreach ($configs as $config) {
    try {
        $pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']}", $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        break; // Success
    } catch(PDOException $e) {
        $db_error .= "Failed config {$config['username']}: " . $e->getMessage() . "\n";
    }
}

if (!$pdo) {
    die("Database connection failed. Tried configs:\n" . $db_error);
}

// For testing, we'll use business_id = 1
$business_id = 1;

// Handle form submission
if ($_POST['action'] === 'save_token') {
    $access_token = $_POST['access_token'];
    
    if (empty($access_token)) {
        $error = "Access token is required";
    } else {
        try {
            // Simple encryption (you should use proper encryption in production)
            $encrypted_token = base64_encode($access_token);
            
            $stmt = $pdo->prepare("
                INSERT INTO business_nayax_credentials (business_id, access_token, created_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                access_token = VALUES(access_token), 
                updated_at = NOW()
            ");
            $stmt->execute([$business_id, $encrypted_token]);
            
            $success = "Nayax token saved successfully!";
        } catch (Exception $e) {
            $error = "Error saving token: " . $e->getMessage();
        }
    }
}

// Get current token
$current_token = '';
try {
    $stmt = $pdo->prepare("SELECT access_token FROM business_nayax_credentials WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $result = $stmt->fetch();
    if ($result) {
        $current_token = base64_decode($result['access_token']);
    }
} catch (Exception $e) {
    // Ignore
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nayax Integration Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005a87; }
        .success { color: green; padding: 10px; background: #f0fff0; border: 1px solid green; border-radius: 4px; }
        .error { color: red; padding: 10px; background: #fff0f0; border: 1px solid red; border-radius: 4px; }
        .info { background: #f0f8ff; padding: 15px; border-radius: 4px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Nayax Integration Test</h1>
        
        <div class="info">
            <strong>Instructions:</strong><br>
            1. Enter your Nayax access token below<br>
            2. Click "Save Token" to store it<br>
            3. Once saved, you can test the connection<br>
            <br>
            <strong>Business ID:</strong> <?php echo $business_id; ?><br>
            <strong>Current Token:</strong> <?php echo $current_token ? substr($current_token, 0, 10) . '...' : 'Not set'; ?>
        </div>

        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="access_token">Nayax Access Token:</label>
                <input type="text" id="access_token" name="access_token" 
                       value="<?php echo htmlspecialchars($current_token); ?>" 
                       placeholder="Enter your Nayax access token">
            </div>
            
            <button type="submit" name="action" value="save_token">Save Token</button>
        </form>

        <?php if ($current_token): ?>
        <hr>
        <h3>Test Connection</h3>
        <button onclick="testConnection()">Test Nayax API Connection</button>
        <div id="test-result"></div>

        <script>
        function testConnection() {
            document.getElementById('test-result').innerHTML = 'Testing connection...';
            
            fetch('nayax-test.php?test=1', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=test_connection'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('test-result').innerHTML = 
                    '<div class="' + (data.success ? 'success' : 'error') + '">' + 
                    data.message + '</div>';
            })
            .catch(error => {
                document.getElementById('test-result').innerHTML = 
                    '<div class="error">Error: ' + error + '</div>';
            });
        }
        </script>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
// Handle AJAX test connection
if ($_GET['test'] === '1' && $_POST['action'] === 'test_connection') {
    header('Content-Type: application/json');
    
    try {
        $stmt = $pdo->prepare("SELECT access_token FROM business_nayax_credentials WHERE business_id = ?");
        $stmt->execute([$business_id]);
        $result = $stmt->fetch();
        
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'No token found']);
            exit;
        }
        
        $token = base64_decode($result['access_token']);
        
        // Test API call to Nayax
        $url = 'https://api.nayax.com/api/v1/machines';
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            echo json_encode([
                'success' => true, 
                'message' => 'Connection successful! Found ' . count($data['machines'] ?? []) . ' machines.'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'API Error: HTTP ' . $httpCode . ' - ' . $response
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}
?> 