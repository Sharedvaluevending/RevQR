<?php
/**
 * Nayax API Debug and Troubleshooting Page
 * Helps diagnose connection issues with the Nayax API
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

$business_id = get_business_id();
$debug_results = [];

if ($_POST) {
    $test_token = trim($_POST['test_token'] ?? '');
    $test_api_url = trim($_POST['test_api_url'] ?? 'https://lynx.nayax.com/operational/api/v1');
    
    if ($test_token && $test_api_url) {
        $debug_results = performNayaxDebug($test_token, $test_api_url);
    }
}

function performNayaxDebug($token, $base_url) {
    $results = [];
    
    // Test different API URL formats first
    $api_variations = [
        $base_url,
        'https://lynx.nayax.com/operational/api/v1',
        'https://lynx.nayax.com/operational/v1',
        'https://lynx.nayax.com/api/v1',
        'https://api.nayax.com/operational/api/v1',
        'https://api.nayax.com/api/v1'
    ];
    
    // Remove duplicates
    $api_variations = array_unique($api_variations);
    
    foreach ($api_variations as $api_base) {
        $results[] = [
            'type' => 'API_BASE_TEST',
            'api_base' => $api_base,
            'description' => 'Testing API Base URL',
            'url' => $api_base,
            'test_result' => testApiBase($token, $api_base)
        ];
        
        // If we find a working API base, test its endpoints
        $base_test = testApiBase($token, $api_base);
        if ($base_test['working_endpoint']) {
            // Test different endpoints on this working API base
            $endpoints = [
                '/devices' => 'Device List',
                '/machines' => 'Machine List', 
                '/operators' => 'Operator List',
                '/hierarchy' => 'Hierarchy',
                '/operators/hierarchy' => 'Operator Hierarchy',
                '/products' => 'Products',
                '/operators/' . getFirstOperatorId($token, $api_base) . '/machines' => 'Operator Machines'
            ];
            
            foreach ($endpoints as $endpoint => $description) {
                if (strpos($endpoint, 'null') !== false) continue; // Skip if operator ID is null
                
                $url = $api_base . $endpoint;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: Galvover-Integration/1.0'
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                
                $start_time = microtime(true);
                $response = curl_exec($ch);
                $end_time = microtime(true);
                
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);
                
                $response_time = round(($end_time - $start_time) * 1000, 2);
                
                $results[] = [
                    'type' => 'ENDPOINT_TEST',
                    'api_base' => $api_base,
                    'endpoint' => $endpoint,
                    'description' => $description,
                    'url' => $url,
                    'effective_url' => $effective_url,
                    'http_code' => $http_code,
                    'response_time' => $response_time,
                    'content_type' => $content_type,
                    'curl_error' => $curl_error,
                    'response' => $response,
                    'response_size' => strlen($response)
                ];
            }
            
            // If we found a working API base, we can stop testing others
            break;
        }
    }
    
    return $results;
}

function testApiBase($token, $api_base) {
    $test_endpoints = ['/devices', '/machines', '/operators', '/hierarchy'];
    $working_endpoint = null;
    $errors = [];
    
    foreach ($test_endpoints as $endpoint) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_base . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code === 200) {
            $working_endpoint = $endpoint;
            break;
        } else {
            $errors[] = "$endpoint: HTTP $http_code" . ($curl_error ? " ($curl_error)" : "");
        }
    }
    
    return [
        'working_endpoint' => $working_endpoint,
        'errors' => $errors,
        'api_base_works' => !is_null($working_endpoint)
    ];
}

function getFirstOperatorId($token, $api_base) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_base . '/operators');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if (is_array($data) && !empty($data)) {
            return $data[0]['OperatorID'] ?? $data[0]['ID'] ?? null;
        }
    }
    
    return null;
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.debug-container {
    background: #1a1a1a;
    color: #ffffff;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.endpoint-test {
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
    font-size: 12px;
    max-height: 200px;
    overflow-y: auto;
}

.token-input {
    font-family: 'Courier New', monospace;
    background: #2a2a2a;
    color: #ffffff;
    border: 1px solid #555;
    padding: 8px;
    width: 100%;
    border-radius: 4px;
}
</style>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="bi bi-bug me-2"></i>Nayax API Debug Tool</h1>
            <p class="text-muted">Use this tool to diagnose Nayax API connection issues and test different endpoints.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Test Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="test_token" class="form-label">Nayax Access Token</label>
                                    <input type="password" class="form-control token-input" id="test_token" name="test_token" 
                                           value="<?= htmlspecialchars($_POST['test_token'] ?? '') ?>"
                                           placeholder="Enter your Nayax access token...">
                                    <small class="text-muted">Your token from Nayax Core → Account Settings → Security and Login → User Tokens</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="test_api_url" class="form-label">API Base URL</label>
                                    <input type="url" class="form-control" id="test_api_url" name="test_api_url" 
                                           value="<?= htmlspecialchars($_POST['test_api_url'] ?? 'https://lynx.nayax.com/operational/api/v1') ?>">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-play-circle me-1"></i>Run Debug Tests
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($debug_results)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="debug-container">
                <h3><i class="bi bi-terminal me-2"></i>Debug Results</h3>
                
                <?php foreach ($debug_results as $result): ?>
                <div class="endpoint-test">
                    <?php if ($result['type'] === 'API_BASE_TEST'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5>
                            🌐 API Base URL Test
                            <code><?= htmlspecialchars($result['api_base']) ?></code>
                        </h5>
                        <span class="badge <?= $result['test_result']['api_base_works'] ? 'bg-success' : 'bg-danger' ?>">
                            <?= $result['test_result']['api_base_works'] ? '✅ WORKING' : '❌ ALL 404' ?>
                        </span>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <?php if ($result['test_result']['api_base_works']): ?>
                            <div class="status-success">
                                ✅ Found working endpoint: <code><?= htmlspecialchars($result['test_result']['working_endpoint']) ?></code>
                            </div>
                            <?php else: ?>
                            <div class="status-error">
                                ❌ All test endpoints failed:<br>
                                <?php foreach ($result['test_result']['errors'] as $error): ?>
                                <small>• <?= htmlspecialchars($error) ?></small><br>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5>
                            <?= htmlspecialchars($result['description']) ?> 
                            <code><?= htmlspecialchars($result['endpoint']) ?></code>
                        </h5>
                        <span class="badge <?= $result['http_code'] === 200 ? 'bg-success' : ($result['http_code'] === 404 ? 'bg-danger' : 'bg-warning') ?>">
                            HTTP <?= $result['http_code'] ?>
                        </span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <strong>URL:</strong> <code><?= htmlspecialchars($result['url']) ?></code><br>
                            <?php if ($result['effective_url'] !== $result['url']): ?>
                            <strong>Redirected to:</strong> <code><?= htmlspecialchars($result['effective_url']) ?></code><br>
                            <?php endif; ?>
                            <strong>Response Time:</strong> <?= $result['response_time'] ?>ms<br>
                            <strong>Content Type:</strong> <?= htmlspecialchars($result['content_type'] ?: 'Unknown') ?><br>
                            <strong>Response Size:</strong> <?= number_format($result['response_size']) ?> bytes
                        </div>
                        <div class="col-md-6">
                            <?php if ($result['curl_error']): ?>
                            <div class="status-error">
                                <strong>cURL Error:</strong> <?= htmlspecialchars($result['curl_error']) ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($result['http_code'] === 200): ?>
                            <div class="status-success">✅ Success - API endpoint is working</div>
                            <?php elseif ($result['http_code'] === 404): ?>
                            <div class="status-error">❌ Not Found - Endpoint doesn't exist</div>
                            <?php elseif ($result['http_code'] === 401): ?>
                            <div class="status-error">🔒 Unauthorized - Check your token</div>
                            <?php else: ?>
                            <div class="status-warning">⚠️ Unexpected response</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (isset($result['response']) && $result['response']): ?>
                    <div class="mt-3">
                        <strong>Response Body:</strong>
                        <div class="response-box">
                            <?php 
                            $json_response = json_decode($result['response'], true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                echo htmlspecialchars(json_encode($json_response, JSON_PRETTY_PRINT));
                            } else {
                                echo htmlspecialchars($result['response']);
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; // End of else block for endpoint test ?>
                </div>
                <?php endforeach; ?>
                
                <div class="mt-4 p-3" style="background: #0a3d0a; border-radius: 6px;">
                    <h5>💡 Troubleshooting Tips:</h5>
                    <ul>
                        <li><strong>HTTP 404:</strong> The endpoint doesn't exist. Verify your API URL format.</li>
                        <li><strong>HTTP 401:</strong> Authentication failed. Check your access token.</li>
                        <li><strong>HTTP 403:</strong> Forbidden. Your token may not have the required permissions.</li>
                        <li><strong>cURL Errors:</strong> Network connectivity issues or SSL problems.</li>
                        <li><strong>Expected API Base URL:</strong> <code>https://lynx.nayax.com/operational/api/v1</code></li>
                        <li><strong>Token Format:</strong> Should be a long alphanumeric string from Nayax Core</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 