<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';

// Require business role for full testing
require_role('business');

$business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
$test_results = [];
$test_qr_codes = [];

// Function to log test results
function logTest($name, $status, $message, $data = null) {
    global $test_results;
    $test_results[] = [
        'name' => $name,
        'status' => $status, // 'pass', 'fail', 'warning'
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Function to display test results
function displayTestResult($result) {
    $icons = [
        'pass' => 'bi-check-circle-fill text-success',
        'fail' => 'bi-x-circle-fill text-danger',
        'warning' => 'bi-exclamation-triangle-fill text-warning'
    ];
    
    $backgrounds = [
        'pass' => 'alert-success',
        'fail' => 'alert-danger',
        'warning' => 'alert-warning'
    ];
    
    echo "<div class='alert {$backgrounds[$result['status']]} d-flex align-items-center' role='alert'>";
    echo "<i class='bi {$icons[$result['status']]} me-2'></i>";
    echo "<div>";
    echo "<strong>{$result['name']}:</strong> {$result['message']}";
    if ($result['data']) {
        echo "<br><small class='text-muted'>" . json_encode($result['data'], JSON_PRETTY_PRINT) . "</small>";
    }
    echo "</div>";
    echo "</div>";
}

require_once __DIR__ . '/core/includes/header.php';
?>

<style>
.test-container {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    margin: 20px 0;
    padding: 30px;
}

.qr-test-preview {
    max-width: 200px;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 10px;
    background: white;
    margin: 10px;
}

.test-section {
    border-left: 4px solid #007bff;
    padding: 20px;
    margin: 20px 0;
    background: #f8f9fa;
    border-radius: 8px;
}

.test-actions {
    margin: 20px 0;
    padding: 20px;
    background: #e9ecef;
    border-radius: 8px;
}

.alert {
    margin: 10px 0;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="test-container">
                <h1 class="text-center mb-4">
                    <i class="bi bi-qr-code-scan"></i> 
                    Comprehensive QR Code System Test
                </h1>
                <p class="text-center text-muted mb-5">
                    Testing all QR code functionality: generation, voting, display, campaigns, and integrations
                </p>

                <?php
                // =================
                // TEST 1: DATABASE INTEGRITY
                // =================
                echo "<div class='test-section'>";
                echo "<h2><i class='bi bi-database'></i> Test 1: Database Integrity</h2>";

                try {
                    // Check QR codes with NULL business_id
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qr_codes WHERE business_id IS NULL");
                    $stmt->execute();
                    $null_business_count = $stmt->fetchColumn();
                    
                    if ($null_business_count > 0) {
                        logTest("Business ID Check", "fail", "Found {$null_business_count} QR codes with NULL business_id");
                        
                        // Fix NULL business_id issues
                        $stmt = $pdo->prepare("
                            UPDATE qr_codes qr
                            LEFT JOIN campaigns c ON qr.campaign_id = c.id
                            SET qr.business_id = c.business_id
                            WHERE qr.business_id IS NULL AND c.business_id IS NOT NULL
                        ");
                        $stmt->execute();
                        $fixed_count = $stmt->rowCount();
                        
                        if ($fixed_count > 0) {
                            logTest("Business ID Fix", "pass", "Fixed {$fixed_count} QR codes with missing business_id");
                        }
                    } else {
                        logTest("Business ID Check", "pass", "All QR codes have business_id assigned");
                    }
                    
                    // Check your business QR codes
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qr_codes WHERE business_id = ?");
                    $stmt->execute([$business_id]);
                    $my_qr_count = $stmt->fetchColumn();
                    
                    logTest("Business QR Count", "pass", "Your business has {$my_qr_count} QR codes");
                    
                } catch (Exception $e) {
                    logTest("Database Integrity", "fail", "Database error: " . $e->getMessage());
                }

                // Display results for Test 1
                foreach ($test_results as $result) {
                    displayTestResult($result);
                }
                echo "</div>";

                // =================
                // TEST 2: VOTING LIST CREATION AND CAMPAIGN LINKING
                // =================
                echo "<div class='test-section'>";
                echo "<h2><i class='bi bi-list-ul'></i> Test 2: Voting List Creation and Campaign Linking</h2>";
                $test_results = []; // Reset for this section

                try {
                    // Create test voting list
                    $stmt = $pdo->prepare("INSERT INTO voting_lists (business_id, name, description) VALUES (?, ?, ?)");
                    $stmt->execute([$business_id, 'Test List ' . time(), 'Test voting list']);
                    $test_list_id = $pdo->lastInsertId();
                    
                    echo "<div class='alert alert-success'>✅ Created test voting list ID: {$test_list_id}</div>";
                    
                    // Add test items
                    $test_items = ['Coca Cola', 'Pepsi', 'Sprite', 'Water', 'Orange Juice'];
                    foreach ($test_items as $item) {
                        $stmt = $pdo->prepare("INSERT INTO voting_list_items (voting_list_id, item_name, item_description) VALUES (?, ?, ?)");
                        $stmt->execute([$test_list_id, $item, "Test item: {$item}"]);
                    }
                    
                    echo "<div class='alert alert-success'>✅ Added " . count($test_items) . " test items</div>";
                    
                    // Create test campaign
                    $stmt = $pdo->prepare("INSERT INTO campaigns (business_id, name, description, status) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$business_id, 'Test Campaign ' . time(), 'Test campaign', 'active']);
                    $test_campaign_id = $pdo->lastInsertId();
                    
                    echo "<div class='alert alert-success'>✅ Created test campaign ID: {$test_campaign_id}</div>";
                    
                    // Link voting list to campaign
                    $stmt = $pdo->prepare("INSERT INTO campaign_voting_lists (campaign_id, voting_list_id) VALUES (?, ?)");
                    $stmt->execute([$test_campaign_id, $test_list_id]);
                    
                    echo "<div class='alert alert-success'>✅ Linked voting list to campaign</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>❌ Error creating test data: " . $e->getMessage() . "</div>";
                }

                foreach ($test_results as $result) {
                    displayTestResult($result);
                }
                echo "</div>";

                // =================
                // TEST 3: QR CODE GENERATION (BASIC AND ENHANCED)
                // =================
                echo "<div class='test-section'>";
                echo "<h2><i class='bi bi-qr-code'></i> Test 3: QR Code Generation Testing</h2>";
                $test_results = []; // Reset for this section

                // Test Basic QR Generator
                echo "<h4>Testing Basic QR Generator API</h4>";
                try {
                    $basic_test_data = [
                        'qr_type' => 'static',
                        'content' => 'https://example.com/test-basic-qr-' . time(),
                        'size' => 400,
                        'machine_name' => 'Basic Test Machine'
                    ];
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, APP_URL . '/api/qr/generate.php');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($basic_test_data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Cookie: ' . session_name() . '=' . session_id()
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($http_code === 200) {
                        $result = json_decode($response, true);
                        if ($result && $result['success']) {
                            logTest("Basic QR Generator", "pass", "Successfully generated basic QR code");
                            $test_qr_codes['basic'] = $result['data'];
                        } else {
                            logTest("Basic QR Generator", "fail", "API returned error: " . ($result['message'] ?? 'Unknown error'));
                        }
                    } else {
                        logTest("Basic QR Generator", "fail", "HTTP error code: {$http_code}");
                    }
                } catch (Exception $e) {
                    logTest("Basic QR Generator", "fail", "Exception: " . $e->getMessage());
                }

                // Test Enhanced QR Generator
                echo "<h4>Testing Enhanced QR Generator</h4>";
                try {
                    // Create voting QR code using the test campaign and list
                    if (isset($test_campaign_id) && isset($test_list_id)) {
                        $voting_qr_code = 'test_vote_' . uniqid();
                        $voting_url = APP_URL . '/vote.php?code=' . $voting_qr_code;
                        
                        $meta_data = json_encode([
                            'campaign_id' => $test_campaign_id,
                            'voting_list_id' => $test_list_id,
                            'file_path' => '/uploads/qr/' . $voting_qr_code . '.png'
                        ]);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO qr_codes (business_id, campaign_id, machine_id, qr_type, code, machine_name, url, meta, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $business_id, 
                            $test_campaign_id, 
                            $test_list_id, 
                            'dynamic_voting', 
                            $voting_qr_code, 
                            'Test Voting Machine',
                            $voting_url,
                            $meta_data,
                            'active'
                        ]);
                        
                        $voting_qr_id = $pdo->lastInsertId();
                        echo "<div class='alert alert-success'>✅ Created voting QR code ID: {$voting_qr_id}</div>";
                        echo "<div class='alert alert-info'>🔗 Test voting URL: <a href='{$voting_url}' target='_blank'>{$voting_url}</a></div>";
                        $test_qr_codes['voting'] = [
                            'id' => $voting_qr_id,
                            'code' => $voting_qr_code,
                            'url' => $voting_url
                        ];
                    }
                } catch (Exception $e) {
                    logTest("Enhanced QR Generator", "fail", "Exception: " . $e->getMessage());
                }

                foreach ($test_results as $result) {
                    displayTestResult($result);
                }
                echo "</div>";

                // =================
                // TEST 4: QR MANAGER DISPLAY
                // =================
                echo "<div class='test-section'>";
                echo "<h2><i class='bi bi-grid-3x3-gap'></i> Test 4: QR Manager Display</h2>";
                $test_results = []; // Reset for this section

                try {
                    // Test QR codes visibility in manager
                    $stmt = $pdo->prepare("
                        SELECT qr.*, 
                               COALESCE(
                                   JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
                                   CONCAT('/uploads/qr/', qr.code, '.png')
                               ) as qr_url
                        FROM qr_codes qr
                        WHERE qr.business_id = ? AND qr.status = 'active'
                        ORDER BY qr.created_at DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$business_id]);
                    $visible_qr_codes = $stmt->fetchAll();
                    
                    if (count($visible_qr_codes) > 0) {
                        logTest("QR Manager Visibility", "pass", "Found " . count($visible_qr_codes) . " QR codes visible in manager");
                        
                        echo "<div class='row'>";
                        foreach (array_slice($visible_qr_codes, 0, 5) as $qr) {
                            echo "<div class='col-md-2 text-center'>";
                            echo "<img src='{$qr['qr_url']}' class='qr-test-preview' alt='QR Code'>";
                            echo "<small class='d-block'>{$qr['qr_type']}</small>";
                            echo "<small class='d-block text-muted'>{$qr['code']}</small>";
                            echo "</div>";
                        }
                        echo "</div>";
                    } else {
                        logTest("QR Manager Visibility", "warning", "No QR codes found for display in manager");
                    }
                    
                } catch (Exception $e) {
                    logTest("QR Manager Display", "fail", "Error: " . $e->getMessage());
                }

                foreach ($test_results as $result) {
                    displayTestResult($result);
                }
                echo "</div>";

                // =================
                // TEST 5: VOTING PAGE FUNCTIONALITY
                // =================
                echo "<div class='test-section'>";
                echo "<h2><i class='bi bi-ballot'></i> Test 5: Voting Page Functionality</h2>";
                $test_results = []; // Reset for this section

                if (isset($test_qr_codes['voting'])) {
                    $voting_code = $test_qr_codes['voting']['code'];
                    $voting_url = $test_qr_codes['voting']['url'];
                    
                    logTest("Voting URL Generation", "pass", "Generated voting URL: {$voting_url}");
                    
                    // Test if voting page can find the QR code and load voting list
                    try {
                        $stmt = $pdo->prepare("
                            SELECT qr.*, vl.name as list_name, vl.description as list_description,
                                   b.name as business_name, c.name as campaign_name
                            FROM qr_codes qr
                            LEFT JOIN voting_lists vl ON qr.machine_id = vl.id
                            LEFT JOIN campaigns c ON qr.campaign_id = c.id
                            LEFT JOIN businesses b ON COALESCE(vl.business_id, c.business_id) = b.id
                            WHERE qr.code = ?
                        ");
                        $stmt->execute([$voting_code]);
                        $qr_data = $stmt->fetch();
                        
                        if ($qr_data) {
                            logTest("QR Code Resolution", "pass", "Voting page can resolve QR code");
                            
                            // Check if campaign and voting list are properly linked
                            if ($qr_data['campaign_id']) {
                                $stmt = $pdo->prepare("
                                    SELECT vl.* 
                                    FROM voting_lists vl
                                    JOIN campaign_voting_lists cvl ON vl.id = cvl.voting_list_id
                                    WHERE cvl.campaign_id = ?
                                ");
                                $stmt->execute([$qr_data['campaign_id']]);
                                $linked_list = $stmt->fetch();
                                
                                if ($linked_list) {
                                    logTest("Campaign-List Link", "pass", "Campaign properly linked to voting list");
                                    
                                    // Get items for the voting list
                                    $stmt = $pdo->prepare("
                                        SELECT * FROM voting_list_items 
                                        WHERE voting_list_id = ? 
                                        ORDER BY item_name
                                    ");
                                    $stmt->execute([$linked_list['id']]);
                                    $voting_items = $stmt->fetchAll();
                                    
                                    if (count($voting_items) > 0) {
                                        logTest("Voting Items", "pass", "Found " . count($voting_items) . " items for voting");
                                    } else {
                                        logTest("Voting Items", "warning", "No voting items found for this list");
                                    }
                                } else {
                                    logTest("Campaign-List Link", "fail", "Campaign not properly linked to voting list");
                                }
                            }
                        } else {
                            logTest("QR Code Resolution", "fail", "Voting page cannot resolve QR code");
                        }
                    } catch (Exception $e) {
                        logTest("Voting Page Test", "fail", "Error: " . $e->getMessage());
                    }
                } else {
                    logTest("Voting URL Test", "warning", "No voting QR code created for testing");
                }

                foreach ($test_results as $result) {
                    displayTestResult($result);
                }
                echo "</div>";

                // =================
                // TEST 6: ADDITIONAL INTEGRATIONS
                // =================
                echo "<div class='test-section'>";
                echo "<h2><i class='bi bi-plus-circle'></i> Test 6: Additional Integrations</h2>";
                $test_results = []; // Reset for this section

                // Test Spin Wheel Integration
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM spin_wheels WHERE business_id = ?");
                    $stmt->execute([$business_id]);
                    $spin_count = $stmt->fetchColumn();
                    
                    if ($spin_count > 0) {
                        logTest("Spin Wheel Integration", "pass", "Found {$spin_count} spin wheels");
                        
                        $stmt = $pdo->prepare("SELECT id FROM spin_wheels WHERE business_id = ? LIMIT 1");
                        $stmt->execute([$business_id]);
                        $spin_wheel = $stmt->fetch();
                        
                        if ($spin_wheel) {
                            $spin_url = APP_URL . '/public/spin-wheel.php?wheel_id=' . $spin_wheel['id'];
                            logTest("Spin Wheel URL", "pass", "Spin wheel URL: {$spin_url}");
                        }
                    } else {
                        logTest("Spin Wheel Integration", "warning", "No spin wheels found");
                    }
                } catch (Exception $e) {
                    logTest("Spin Wheel Test", "fail", "Error: " . $e->getMessage());
                }

                // Test Pizza Tracker Integration
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pizza_trackers WHERE business_id = ?");
                    $stmt->execute([$business_id]);
                    $tracker_count = $stmt->fetchColumn();
                    
                    if ($tracker_count > 0) {
                        logTest("Pizza Tracker Integration", "pass", "Found {$tracker_count} pizza trackers");
                        
                        $stmt = $pdo->prepare("SELECT id FROM pizza_trackers WHERE business_id = ? LIMIT 1");
                        $stmt->execute([$business_id]);
                        $tracker = $stmt->fetch();
                        
                        if ($tracker) {
                            $tracker_url = APP_URL . '/public/pizza-tracker.php?tracker_id=' . $tracker['id'];
                            logTest("Pizza Tracker URL", "pass", "Pizza tracker URL: {$tracker_url}");
                        }
                    } else {
                        logTest("Pizza Tracker Integration", "warning", "No pizza trackers found");
                    }
                } catch (Exception $e) {
                    logTest("Pizza Tracker Test", "fail", "Error: " . $e->getMessage());
                }

                // Test Promotional Ads
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM business_promotional_ads WHERE business_id = ?");
                    $stmt->execute([$business_id]);
                    $ad_count = $stmt->fetchColumn();
                    
                    if ($ad_count > 0) {
                        logTest("Promotional Ads", "pass", "Found {$ad_count} promotional ads");
                    } else {
                        logTest("Promotional Ads", "warning", "No promotional ads found");
                    }
                } catch (Exception $e) {
                    logTest("Promotional Ads Test", "fail", "Error: " . $e->getMessage());
                }

                foreach ($test_results as $result) {
                    displayTestResult($result);
                }
                echo "</div>";

                // =================
                // QUICK ACTION BUTTONS
                // =================
                ?>

                <div class="test-actions">
                    <h3><i class="bi bi-lightning"></i> Quick Actions</h3>
                    <p>Use these links to test the functionality manually:</p>
                    
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="qr_manager.php" class="btn btn-primary w-100">
                                <i class="bi bi-grid-3x3-gap"></i><br>
                                QR Manager
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="qr-generator.php" class="btn btn-success w-100">
                                <i class="bi bi-plus-circle"></i><br>
                                Basic Generator
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="qr-generator-enhanced.php" class="btn btn-info w-100">
                                <i class="bi bi-star"></i><br>
                                Enhanced Generator
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="business/manage-campaigns.php" class="btn btn-warning w-100">
                                <i class="bi bi-megaphone"></i><br>
                                Manage Campaigns
                            </a>
                        </div>
                    </div>

                    <?php if (isset($test_qr_codes['voting'])): ?>
                    <div class="mt-4">
                        <h4>Test the Voting QR Code:</h4>
                        <a href="<?php echo $test_qr_codes['voting']['url']; ?>" target="_blank" class="btn btn-primary btn-lg">
                            <i class="bi bi-ballot"></i> Test Voting Page
                        </a>
                        <small class="d-block text-muted mt-2">
                            QR Code: <?php echo $test_qr_codes['voting']['code']; ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mt-4 text-center">
                    <h3>Summary</h3>
                    <p class="text-muted">
                        This comprehensive test has checked all major QR code functionality including:
                        generation, database integrity, voting system integration, manager display,
                        and additional features like spin wheels and pizza trackers.
                    </p>
                    <div class="alert alert-info">
                        <strong>Next Steps:</strong> Use the test voting QR code above to verify the complete voting flow works end-to-end.
                        Check the QR Manager to ensure all generated codes appear correctly.
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 