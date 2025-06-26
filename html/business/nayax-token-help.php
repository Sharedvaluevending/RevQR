<?php
/**
 * Nayax Token Help and Setup Guide
 * Provides step-by-step instructions for obtaining and configuring Nayax API access
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.help-section {
    background: #1a1a1a;
    color: #ffffff;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
    border-left: 4px solid #4caf50;
}

.step-box {
    background: #2a2a2a;
    border: 1px solid #444;
    padding: 15px;
    margin: 10px 0;
    border-radius: 6px;
}

.code-example {
    background: #0a0a0a;
    border: 1px solid #555;
    padding: 10px;
    margin: 10px 0;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

.warning-box {
    background: #2c1810;
    border: 1px solid #d4620a;
    color: #ff9800;
    padding: 15px;
    border-radius: 6px;
    margin: 15px 0;
}

.success-box {
    background: #0a3d0a;
    border: 1px solid #4caf50;
    color: #4caf50;
    padding: 15px;
    border-radius: 6px;
    margin: 15px 0;
}
</style>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="bi bi-question-circle me-2"></i>Nayax Integration Help</h1>
            <p class="text-muted">Step-by-step guide to set up your Nayax API integration</p>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="help-section">
                <h2>🔑 Step 1: Get Your Nayax Access Token</h2>
                
                <div class="step-box">
                    <h5>1.1 Log into Nayax Core</h5>
                    <p>Go to your Nayax management portal (usually at <code>core.nayax.com</code> or similar)</p>
                </div>

                <div class="step-box">
                    <h5>1.2 Navigate to Account Settings</h5>
                    <p>Click on your username/profile in the top right corner, then select <strong>"Account Settings"</strong></p>
                </div>

                <div class="step-box">
                    <h5>1.3 Go to Security and Login</h5>
                    <p>Look for a tab or section called <strong>"Security and Login"</strong> or <strong>"API Access"</strong></p>
                </div>

                <div class="step-box">
                    <h5>1.4 Find User Tokens Section</h5>
                    <p>Scroll down to find the <strong>"User Tokens"</strong> or <strong>"API Tokens"</strong> section</p>
                </div>

                <div class="step-box">
                    <h5>1.5 Show/Copy Your Token</h5>
                    <p>Click <strong>"Show Token"</strong> button next to an existing token, or create a new one if needed</p>
                    <div class="code-example">
                        Example token format: 6RDWH0sRaodLBFnRz0DfKNnHH_gLHKaJLkz-pihO9AG3QuO0G60us3vU3SNT-rU40
                    </div>
                </div>
            </div>

            <div class="help-section">
                <h2>🌐 Step 2: Determine Your API URL</h2>
                
                <div class="step-box">
                    <h5>Most Common API URLs:</h5>
                    <ul>
                        <li><code>https://lynx.nayax.com/operational/api/v1</code> <span class="badge bg-success">Recommended</span></li>
                        <li><code>https://lynx.nayax.com/operational/v1</code></li>
                        <li><code>https://api.nayax.com/operational/api/v1</code></li>
                        <li><code>https://api.nayax.com/api/v1</code></li>
                    </ul>
                </div>

                <div class="warning-box">
                    <strong>⚠️ Note:</strong> Your specific API URL may depend on your Nayax account region or setup. If the default doesn't work, try the alternatives above.
                </div>
            </div>

            <div class="help-section">
                <h2>🔧 Step 3: Test Your Configuration</h2>
                
                <div class="step-box">
                    <h5>3.1 Use the Debug Tool</h5>
                    <p><a href="nayax-debug.php" class="btn btn-primary">Open Debug Tool</a></p>
                    <p>This will test multiple API URL formats automatically and show you which one works.</p>
                </div>

                <div class="step-box">
                    <h5>3.2 Check the Results</h5>
                    <p>Look for:</p>
                    <ul>
                        <li><span class="text-success">✅ WORKING</span> - API URL is correct</li>
                        <li><span class="text-danger">❌ ALL 404</span> - Try a different API URL</li>
                        <li><span class="text-danger">🔒 Unauthorized</span> - Token is invalid</li>
                    </ul>
                </div>
            </div>

            <div class="help-section">
                <h2>❌ Common Issues & Solutions</h2>
                
                <div class="step-box">
                    <h5>Issue: HTTP 404 - Not Found</h5>
                    <p><strong>Solutions:</strong></p>
                    <ul>
                        <li>Try different API URL formats (use debug tool)</li>
                        <li>Check if your Nayax account has API access enabled</li>
                        <li>Verify you're using the Lynx API (not other Nayax APIs)</li>
                        <li>Contact Nayax support to confirm your API endpoint</li>
                    </ul>
                </div>

                <div class="step-box">
                    <h5>Issue: HTTP 401 - Unauthorized</h5>
                    <p><strong>Solutions:</strong></p>
                    <ul>
                        <li>Check your token is copied correctly (no extra spaces)</li>
                        <li>Regenerate your token in Nayax Core</li>
                        <li>Verify your Nayax account has API permissions</li>
                        <li>Make sure you're using the correct token (not password)</li>
                    </ul>
                </div>

                <div class="step-box">
                    <h5>Issue: HTTP 403 - Forbidden</h5>
                    <p><strong>Solutions:</strong></p>
                    <ul>
                        <li>Your token may not have permission to access certain endpoints</li>
                        <li>Contact Nayax support to enable API access for your account</li>
                        <li>Check if there are rate limits or usage restrictions</li>
                    </ul>
                </div>
            </div>

            <div class="success-box">
                <h3>✅ When Everything Works</h3>
                <p>Once you find a working API URL and have a valid token:</p>
                <ol>
                    <li>Go back to <a href="nayax-settings.php" class="text-success">Nayax Settings</a></li>
                    <li>Enter your token and the working API URL</li>
                    <li>Click "Save & Test Connection"</li>
                    <li>Your machines should sync automatically!</li>
                </ol>
            </div>

            <div class="help-section">
                <h2>📞 Still Need Help?</h2>
                
                <div class="step-box">
                    <h5>Contact Nayax Support</h5>
                    <p>If you're still having issues, contact Nayax support with these details:</p>
                    <ul>
                        <li>Your Nayax account email/username</li>
                        <li>That you need Lynx API access for third-party integration</li>
                        <li>The specific error messages you're seeing</li>
                        <li>Request confirmation of your correct API endpoint URL</li>
                    </ul>
                </div>

                <div class="step-box">
                    <h5>Debug Information</h5>
                    <p>Run the <a href="nayax-debug.php">Debug Tool</a> and share the results with support for faster resolution.</p>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="nayax-settings.php" class="btn btn-primary me-2">
                    <i class="bi bi-arrow-left me-1"></i>Back to Settings
                </a>
                <a href="nayax-debug.php" class="btn btn-info">
                    <i class="bi bi-bug me-1"></i>Open Debug Tool
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 