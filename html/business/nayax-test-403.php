<?php
/**
 * Test the Nayax endpoint that's giving 403 errors
 * Sometimes 403 endpoints work for specific operations
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

$test_results = [];

if ($_POST) {
    $test_token = trim($_POST['test_token'] ?? '');
    
    if ($test_token) {
        $test_results = test403Endpoint($test_token);
    }
}

function test403Endpoint($token) {
    $results = [];
    
    // The endpoint that gave us 403 - let's try different approaches
    $base_url = 'https://lynx.nayax.com/operational/v1';
    
    $tests = [
        'GET_devices' => ['method' => 'GET', 'endpoint' => '/devices', 'description' => 'List all devices'],
        'GET_machines' => ['method' => 'GET', 'endpoint' => '/machines', 'description' => 'List all machines'],
        'GET_devices_limit' => ['method' => 'GET', 'endpoint' => '/devices?limit=1', 'description' => 'Get one device with limit'],
        'GET_machines_limit' => ['method' => 'GET', 'endpoint' => '/machines?limit=1', 'description' => 'Get one machine with limit'],
        'HEAD_devices' => ['method' => 'HEAD', 'endpoint' => '/devices', 'description' => 'Check if devices endpoint exists'],
        'OPTIONS_devices' => ['method' => 'OPTIONS', 'endpoint' => '/devices', 'description' => 'Get available methods for devices'],
    ];
    
    foreach ($tests as $test_name => $test_config) {
        $url = $base_url . $test_config['endpoint'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $test_config['method']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Galvover-Test/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        
        $results[$test_name] = [
            'method' => $test_config['method'],
            'endpoint' => $test_config['endpoint'],
            'description' => $test_config['description'],
            'url' => $url,
            'http_code' => $http_code,
            'curl_error' => $curl_error,
            'headers' => $headers,
            'body' => $body,
            'success' => $http_code === 200
        ];
        
        // If we get a 200, we found something that works!
        if ($http_code === 200) {
            break;
        }
    }
    
    return $results;
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.test-container {
    background: #1a1a1a;
    color: #ffffff;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.test-result {
    background: #2a2a2a;
    border: 1px solid #444;
    padding: 15px;
    margin: 10px 0;
    border-radius: 6px;
}

.status-success { color: #4caf50; }
.status-error { color: #f44336; }
.status-warning { color: #ff9800; }

.response-box {
    background: #0a0a0a;
    border: 1px solid #555;
    padding: 10px;
    margin: 10px 0;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    max-height: 200px;
    overflow-y: auto;
}

.success-result {
    border-left: 4px solid #4caf50;
    background: #1a3d1a;
}
</style>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="bi bi-shield-exclamation me-2"></i>Test 403 Endpoint</h1>
            <p class="text-muted">Testing the endpoint that gives 403 errors to see if any operations work</p>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Test 403 Endpoints</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>What we're testing:</strong><br>
                        Since <code>https://lynx.nayax.com/operational/v1/devices</code> gives HTTP 403, 
                        let's try different HTTP methods and parameters to see if any work.
                        Sometimes 403 endpoints have partial access.
                    </div>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label for="test_token" class="form-label">Your Nayax Access Token</label>
                            <input type="password" class="form-control" id="test_token" name="test_token" 
                                   value="<?= htmlspecialchars($_POST['test_token'] ?? '') ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-shield-check me-1"></i>Test 403 Endpoint
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($test_results)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="test-container">
                <h3><i class="bi bi-list-check me-2"></i>Test Results</h3>
                
                <?php foreach ($test_results as $test_name => $result): ?>
                <div class="test-result <?= $result['success'] ? 'success-result' : '' ?>">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5>
                            <?= $result['method'] ?> <?= htmlspecialchars($result['endpoint']) ?>
                        </h5>
                        <span class="badge <?= $result['success'] ? 'bg-success' : ($result['http_code'] === 403 ? 'bg-warning' : 'bg-danger') ?>">
                            HTTP <?= $result['http_code'] ?>
                        </span>
                    </div>
                    
                    <p class="mb-2"><?= htmlspecialchars($result['description']) ?></p>
                    
                    <?php if ($result['curl_error']): ?>
                    <div class="status-error">
                        <strong>cURL Error:</strong> <?= htmlspecialchars($result['curl_error']) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($result['success']): ?>
                    <div class="status-success">
                        🎉 SUCCESS! This method works with your token!
                    </div>
                    <div class="mt-3">
                        <strong>Response Body:</strong>
                        <div class="response-box"><?= htmlspecialchars($result['body']) ?></div>
                    </div>
                    <?php elseif ($result['http_code'] === 403): ?>
                    <div class="status-warning">
                        ⚠️ Still forbidden - need API permissions enabled
                    </div>
                    <?php else: ?>
                    <div class="status-error">
                        ❌ HTTP <?= $result['http_code'] ?> - <?= $result['http_code'] === 404 ? 'Not Found' : 'Error' ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($result['headers'] && strlen(trim($result['headers'])) > 0): ?>
                    <details class="mt-2">
                        <summary style="color: #aaa; cursor: pointer;">View Headers</summary>
                        <div class="response-box mt-2"><?= htmlspecialchars($result['headers']) ?></div>
                    </details>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <div class="mt-4 p-3" style="background: #3d2a1a; border-radius: 6px; border-left: 4px solid #ff9800;">
                    <h5>📞 Next Step: Contact Nayax Support</h5>
                    <p>Based on the consistent HTTP 403 responses, your account needs Lynx API permissions enabled.</p>
                    <p><strong>Tell Nayax Support:</strong></p>
                    <ul>
                        <li>✅ Your token authenticates successfully</li>
                        <li>✅ The endpoints exist and are reachable</li>
                        <li>❌ But you get HTTP 403 (Forbidden) on /devices and /machines</li>
                        <li>🎯 Request: Enable full Lynx API access for your account</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 