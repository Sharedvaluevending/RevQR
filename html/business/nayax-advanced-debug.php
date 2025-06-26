<?php
/**
 * Advanced Nayax API Debug Tool
 * Enhanced debugging with redirect following and additional endpoint testing
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

$debug_results = [];

if ($_POST) {
    $test_token = trim($_POST['test_token'] ?? '');
    $test_api_url = trim($_POST['test_api_url'] ?? 'https://lynx.nayax.com/operational/api/v1');
    
    if ($test_token && $test_api_url) {
        $debug_results = performAdvancedNayaxDebug($test_token, $test_api_url);
    }
}

function performAdvancedNayaxDebug($token, $base_url) {
    $results = [];
    
    // Based on user's results, let's focus on the promising endpoints
    $focused_tests = [
        // The one that gave 403 - likely the right endpoint, wrong permissions
        'https://lynx.nayax.com/operational/v1',
        // The one that gave 301 redirects
        'https://lynx.nayax.com/api/v1',
        // Original that gave 404
        'https://lynx.nayax.com/operational/api/v1',
        // Try some variations
        'https://lynx.nayax.com/operational',
        'https://lynx.nayax.com/api',
        // Maybe it's region-specific
        'https://us.lynx.nayax.com/operational/api/v1',
        'https://eu.lynx.nayax.com/operational/api/v1',
    ];
    
    foreach ($focused_tests as $api_base) {
        $results[] = [
            'type' => 'FOCUSED_TEST',
            'api_base' => $api_base,
            'test_result' => testApiBaseAdvanced($token, $api_base)
        ];
    }
    
    // Also test some completely different approaches
    $alternative_approaches = [
        // Maybe it needs a different path structure
        'auth_test' => testAuthenticationOnly($token),
        'redirect_follow' => followRedirectsFrom($token, 'https://lynx.nayax.com/api/v1/devices'),
        'base_urls' => testBasePaths($token)
    ];
    
    foreach ($alternative_approaches as $approach_name => $result) {
        $results[] = [
            'type' => 'ALTERNATIVE_APPROACH',
            'approach' => $approach_name,
            'result' => $result
        ];
    }
    
    return $results;
}

function testApiBaseAdvanced($token, $api_base) {
    $endpoints_to_test = [
        // Standard endpoints
        '/devices',
        '/machines', 
        '/operators',
        '/hierarchy',
        // Try some variations
        '',  // Just the base URL
        '/status',
        '/health',
        '/ping',
        // Maybe it needs specific operator context
        '/operator/devices',
        '/operator/machines',
    ];
    
    $results = [];
    
    foreach ($endpoints_to_test as $endpoint) {
        $url = $api_base . $endpoint;
        $result = makeAdvancedRequest($token, $url);
        $results[$endpoint ?: 'base'] = $result;
        
        // If we get a 200, we found something!
        if ($result['http_code'] === 200) {
            break;
        }
    }
    
    return $results;
}

function makeAdvancedRequest($token, $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: Galvover-Nayax-Integration/1.0',
        'Cache-Control: no-cache'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $redirect_count = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
    curl_close($ch);
    
    return [
        'http_code' => $http_code,
        'curl_error' => $curl_error,
        'effective_url' => $effective_url,
        'content_type' => $content_type,
        'redirect_count' => $redirect_count,
        'response' => $response,
        'response_size' => strlen($response)
    ];
}

function testAuthenticationOnly($token) {
    // Test if the token itself is valid by trying a simple auth endpoint
    $auth_tests = [
        'https://lynx.nayax.com/auth/validate',
        'https://lynx.nayax.com/api/auth',
        'https://lynx.nayax.com/operational/auth',
    ];
    
    $results = [];
    foreach ($auth_tests as $url) {
        $results[$url] = makeAdvancedRequest($token, $url);
    }
    
    return $results;
}

function followRedirectsFrom($token, $url) {
    // Manually follow redirects to see where they lead
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't auto-follow
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    
    return [
        'original_url' => $url,
        'http_code' => $http_code,
        'redirect_url' => $redirect_url,
        'response' => $response
    ];
}

function testBasePaths($token) {
    // Test various base paths to see what responds
    $base_paths = [
        'https://lynx.nayax.com',
        'https://lynx.nayax.com/operational',
        'https://lynx.nayax.com/api',
        'https://core.nayax.com/api',
        'https://management.nayax.com/api'
    ];
    
    $results = [];
    foreach ($base_paths as $base) {
        $results[$base] = makeAdvancedRequest($token, $base);
    }
    
    return $results;
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

.test-section {
    background: #2a2a2a;
    border: 1px solid #444;
    padding: 15px;
    margin: 10px 0;
    border-radius: 6px;
}

.status-success { color: #4caf50; }
.status-error { color: #f44336; }
.status-warning { color: #ff9800; }
.status-info { color: #2196f3; }

.response-box {
    background: #0a0a0a;
    border: 1px solid #555;
    padding: 10px;
    margin: 10px 0;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    max-height: 150px;
    overflow-y: auto;
}

.promising-result {
    border-left: 4px solid #4caf50;
    background: #1a3d1a;
}

.redirect-result {
    border-left: 4px solid #ff9800;
    background: #3d2a1a;
}
</style>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="bi bi-search me-2"></i>Advanced Nayax API Debug</h1>
            <p class="text-muted">Deep analysis based on your 403/301 responses - we're getting closer!</p>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Advanced Testing</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Based on your results:</strong><br>
                        • HTTP 403 on <code>/operational/v1</code> means the endpoint exists but needs different permissions<br>
                        • HTTP 301 on <code>/api/v1</code> means redirects are happening - let's follow them<br>
                        • This suggests your token and account are valid, just need the right endpoint format
                    </div>
                    
                    <form method="post">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="test_token" class="form-label">Your Nayax Access Token</label>
                                    <input type="password" class="form-control" id="test_token" name="test_token" 
                                           value="<?= htmlspecialchars($_POST['test_token'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="test_api_url" class="form-label">Base URL (optional)</label>
                                    <input type="url" class="form-control" id="test_api_url" name="test_api_url" 
                                           value="<?= htmlspecialchars($_POST['test_api_url'] ?? 'https://lynx.nayax.com/operational/v1') ?>">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>Run Advanced Analysis
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
                <h3><i class="bi bi-cpu me-2"></i>Advanced Debug Results</h3>
                
                <?php foreach ($debug_results as $result): ?>
                    <?php if ($result['type'] === 'FOCUSED_TEST'): ?>
                    <div class="test-section">
                        <h5>🎯 Testing: <code><?= htmlspecialchars($result['api_base']) ?></code></h5>
                        
                        <?php foreach ($result['test_result'] as $endpoint => $test): ?>
                        <div class="mt-2 p-2 <?= $test['http_code'] === 200 ? 'promising-result' : ($test['redirect_count'] > 0 ? 'redirect-result' : '') ?>" 
                             style="background: #333; border-radius: 4px;">
                            <div class="d-flex justify-content-between">
                                <span><strong><?= $endpoint === 'base' ? '(base URL)' : $endpoint ?></strong></span>
                                <span class="badge <?= $test['http_code'] === 200 ? 'bg-success' : ($test['http_code'] === 403 ? 'bg-warning' : ($test['http_code'] >= 300 && $test['http_code'] < 400 ? 'bg-info' : 'bg-danger')) ?>">
                                    HTTP <?= $test['http_code'] ?>
                                    <?php if ($test['redirect_count'] > 0): ?>
                                    (<?= $test['redirect_count'] ?> redirects)
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <?php if ($test['effective_url'] !== $result['api_base'] . $endpoint): ?>
                            <small class="status-info">→ Redirected to: <?= htmlspecialchars($test['effective_url']) ?></small><br>
                            <?php endif; ?>
                            
                            <?php if ($test['curl_error']): ?>
                            <small class="status-error">Error: <?= htmlspecialchars($test['curl_error']) ?></small><br>
                            <?php endif; ?>
                            
                            <?php if ($test['http_code'] === 200): ?>
                            <div class="status-success">🎉 SUCCESS! This endpoint works!</div>
                            <?php if ($test['response']): ?>
                            <div class="response-box"><?= htmlspecialchars(substr($test['response'], 0, 500)) ?><?= strlen($test['response']) > 500 ? '...' : '' ?></div>
                            <?php endif; ?>
                            <?php elseif ($test['http_code'] === 403): ?>
                            <div class="status-warning">⚠️ Endpoint exists but token lacks permission</div>
                            <?php elseif ($test['http_code'] >= 300 && $test['http_code'] < 400): ?>
                            <div class="status-info">🔄 Redirect detected - following automatically</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php elseif ($result['type'] === 'ALTERNATIVE_APPROACH'): ?>
                    <div class="test-section">
                        <h5>🔧 <?= ucfirst(str_replace('_', ' ', $result['approach'])) ?></h5>
                        
                        <?php if ($result['approach'] === 'redirect_follow'): ?>
                        <div class="p-2" style="background: #333; border-radius: 4px;">
                            <strong>Original:</strong> <?= htmlspecialchars($result['result']['original_url']) ?><br>
                            <strong>Status:</strong> HTTP <?= $result['result']['http_code'] ?><br>
                            <?php if ($result['result']['redirect_url']): ?>
                            <strong class="status-info">Redirects to:</strong> <?= htmlspecialchars($result['result']['redirect_url']) ?><br>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <?php foreach ($result['result'] as $test_name => $test_result): ?>
                        <div class="mt-1 p-2" style="background: #333; border-radius: 4px;">
                            <div class="d-flex justify-content-between">
                                <span><?= htmlspecialchars($test_name) ?></span>
                                <span class="badge <?= $test_result['http_code'] === 200 ? 'bg-success' : 'bg-secondary' ?>">
                                    HTTP <?= $test_result['http_code'] ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <div class="mt-4 p-3" style="background: #0a3d0a; border-radius: 6px;">
                    <h5>🎯 Next Steps Based on Results:</h5>
                    <ul>
                        <li><strong>If you see HTTP 200:</strong> Use that exact URL in your Nayax settings!</li>
                        <li><strong>If you see HTTP 403:</strong> Contact Nayax support to enable API permissions for your token</li>
                        <li><strong>If you see redirects:</strong> Note the final URL and try using that</li>
                        <li><strong>If all still fail:</strong> Your account may need Lynx API access enabled by Nayax</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 