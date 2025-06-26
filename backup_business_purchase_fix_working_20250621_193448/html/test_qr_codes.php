<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/includes/QRGenerator.php';

// Create a test session for business ID 1
$_SESSION = [
    'user_id' => 1,
    'business_id' => 1,
    'role' => 'business',
    'logged_in' => true
];

$generator = new QRGenerator();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Test Suite - RevenueQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <style>
        .qr-test-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .qr-test-card:hover {
            transform: translateY(-5px);
        }
        .qr-image {
            max-width: 200px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .status-success {
            background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
            color: #2e7d32;
        }
        .status-failed {
            background: linear-gradient(135deg, #fce4ec, #f8bbd9);
            color: #d32f2f;
        }
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
        }
        .qr-type-badge {
            font-size: 0.8em;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1 class="display-4 fw-bold mb-3">🎯 QR Code Test Suite</h1>
                    <p class="lead">Comprehensive testing of all supported QR code types</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <?php
        $test_results = [];
        $qr_codes_data = [];

        // Test 1: Static QR Code
        $options1 = [
            'type' => 'static',
            'content' => 'https://example.com/static-test',
            'size' => 300,
            'foreground_color' => '#000000',
            'background_color' => '#FFFFFF',
            'error_correction_level' => 'H',
            'preview' => false
        ];

        try {
            $result1 = $generator->generate($options1);
            if ($result1['success']) {
                $test_results['static'] = true;
                $qr_codes_data['static'] = [
                    'title' => 'Static QR Code',
                    'description' => 'Basic static QR code linking to a fixed URL',
                    'url' => $result1['data']['qr_code_url'],
                    'content' => $options1['content'],
                    'icon' => 'bi-link-45deg',
                    'color' => '#000000',
                    'success' => true
                ];
            } else {
                $test_results['static'] = false;
                $qr_codes_data['static'] = ['success' => false, 'error' => $result1['error']];
            }
        } catch (Exception $e) {
            $test_results['static'] = false;
            $qr_codes_data['static'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // Test 2: Dynamic QR Code
        $options2 = [
            'type' => 'dynamic',
            'content' => 'https://example.com/dynamic-test?id=12345',
            'size' => 300,
            'foreground_color' => '#1565c0',
            'background_color' => '#ffffff',
            'error_correction_level' => 'H',
            'preview' => false
        ];

        try {
            $result2 = $generator->generate($options2);
            if ($result2['success']) {
                $test_results['dynamic'] = true;
                $qr_codes_data['dynamic'] = [
                    'title' => 'Dynamic QR Code',
                    'description' => 'Dynamic QR code with changeable destination',
                    'url' => $result2['data']['qr_code_url'],
                    'content' => $options2['content'],
                    'icon' => 'bi-arrow-repeat',
                    'color' => '#1565c0',
                    'success' => true
                ];
            } else {
                $test_results['dynamic'] = false;
                $qr_codes_data['dynamic'] = ['success' => false, 'error' => $result2['error']];
            }
        } catch (Exception $e) {
            $test_results['dynamic'] = false;
            $qr_codes_data['dynamic'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // Test 3: Dynamic Voting QR Code
        $qr_code3 = uniqid('qr_voting_', true);
        $options3 = [
            'type' => 'dynamic_voting',
            'content' => APP_URL . '/vote.php?code=' . $qr_code3,
            'size' => 300,
            'foreground_color' => '#2e7d32',
            'background_color' => '#ffffff',
            'error_correction_level' => 'H',
            'preview' => false
        ];

        try {
            $result3 = $generator->generate($options3);
            if ($result3['success']) {
                $test_results['dynamic_voting'] = true;
                $qr_codes_data['dynamic_voting'] = [
                    'title' => 'Dynamic Voting QR Code',
                    'description' => 'QR code for voting campaigns and user engagement',
                    'url' => $result3['data']['qr_code_url'],
                    'content' => $options3['content'],
                    'icon' => 'bi-check2-square',
                    'color' => '#2e7d32',
                    'success' => true,
                    'extra' => 'Campaign: More tests (ID: 11)'
                ];
            } else {
                $test_results['dynamic_voting'] = false;
                $qr_codes_data['dynamic_voting'] = ['success' => false, 'error' => $result3['error']];
            }
        } catch (Exception $e) {
            $test_results['dynamic_voting'] = false;
            $qr_codes_data['dynamic_voting'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // Test 4: Dynamic Vending QR Code
        $qr_code4 = uniqid('qr_vending_', true);
        $options4 = [
            'type' => 'dynamic_vending',
            'content' => APP_URL . '/vote.php?code=' . $qr_code4,
            'size' => 300,
            'foreground_color' => '#f57c00',
            'background_color' => '#ffffff',
            'error_correction_level' => 'H',
            'preview' => false
        ];

        try {
            $result4 = $generator->generate($options4);
            if ($result4['success']) {
                $test_results['dynamic_vending'] = true;
                $qr_codes_data['dynamic_vending'] = [
                    'title' => 'Dynamic Vending QR Code',
                    'description' => 'QR code for vending machine voting and selection',
                    'url' => $result4['data']['qr_code_url'],
                    'content' => $options4['content'],
                    'icon' => 'bi-cup-straw',
                    'color' => '#f57c00',
                    'success' => true,
                    'extra' => 'Machine: More tests (ID: 226)'
                ];
            } else {
                $test_results['dynamic_vending'] = false;
                $qr_codes_data['dynamic_vending'] = ['success' => false, 'error' => $result4['error']];
            }
        } catch (Exception $e) {
            $test_results['dynamic_vending'] = false;
            $qr_codes_data['dynamic_vending'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // Test 5: Machine Sales QR Code
        $machine_name = "More tests";
        $options5 = [
            'type' => 'machine_sales',
            'content' => APP_URL . '/public/promotions.php?machine=' . urlencode($machine_name),
            'size' => 300,
            'foreground_color' => '#d32f2f',
            'background_color' => '#ffffff',
            'error_correction_level' => 'H',
            'preview' => false
        ];

        try {
            $result5 = $generator->generate($options5);
            if ($result5['success']) {
                $test_results['machine_sales'] = true;
                $qr_codes_data['machine_sales'] = [
                    'title' => 'Machine Sales QR Code',
                    'description' => 'QR code for vending machine sales and promotions',
                    'url' => $result5['data']['qr_code_url'],
                    'content' => $options5['content'],
                    'icon' => 'bi-cart-plus',
                    'color' => '#d32f2f',
                    'success' => true,
                    'extra' => 'Machine: ' . $machine_name
                ];
            } else {
                $test_results['machine_sales'] = false;
                $qr_codes_data['machine_sales'] = ['success' => false, 'error' => $result5['error']];
            }
        } catch (Exception $e) {
            $test_results['machine_sales'] = false;
            $qr_codes_data['machine_sales'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // Test 6: Promotion QR Code
        $options6 = [
            'type' => 'promotion',
            'content' => APP_URL . '/public/promotions.php?machine=' . urlencode($machine_name) . '&view=promotions',
            'size' => 300,
            'foreground_color' => '#7b1fa2',
            'background_color' => '#ffffff',
            'error_correction_level' => 'H',
            'preview' => false
        ];

        try {
            $result6 = $generator->generate($options6);
            if ($result6['success']) {
                $test_results['promotion'] = true;
                $qr_codes_data['promotion'] = [
                    'title' => 'Promotion QR Code',
                    'description' => 'QR code for special promotions and offers',
                    'url' => $result6['data']['qr_code_url'],
                    'content' => $options6['content'],
                    'icon' => 'bi-percent',
                    'color' => '#7b1fa2',
                    'success' => true,
                    'extra' => 'Machine: ' . $machine_name
                ];
            } else {
                $test_results['promotion'] = false;
                $qr_codes_data['promotion'] = ['success' => false, 'error' => $result6['error']];
            }
        } catch (Exception $e) {
            $test_results['promotion'] = false;
            $qr_codes_data['promotion'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // Test 7: Spin Wheel QR Code
        $spin_wheel_id = 1;
        $options7 = [
            'type' => 'spin_wheel',
            'content' => APP_URL . '/public/spin-wheel.php?wheel_id=' . $spin_wheel_id,
            'size' => 300,
            'foreground_color' => '#00796b',
            'background_color' => '#ffffff',
            'error_correction_level' => 'H',
            'preview' => false
        ];

        try {
            $result7 = $generator->generate($options7);
            if ($result7['success']) {
                $test_results['spin_wheel'] = true;
                $qr_codes_data['spin_wheel'] = [
                    'title' => 'Spin Wheel QR Code',
                    'description' => 'QR code for interactive spin wheel games',
                    'url' => $result7['data']['qr_code_url'],
                    'content' => $options7['content'],
                    'icon' => 'bi-arrow-clockwise',
                    'color' => '#00796b',
                    'success' => true,
                    'extra' => 'Spin Wheel: Shared Value Vending - Default Wheel (ID: ' . $spin_wheel_id . ')'
                ];
            } else {
                $test_results['spin_wheel'] = false;
                $qr_codes_data['spin_wheel'] = ['success' => false, 'error' => $result7['error']];
            }
        } catch (Exception $e) {
            $test_results['spin_wheel'] = false;
            $qr_codes_data['spin_wheel'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // Calculate summary
        $successful = array_sum($test_results);
        $total = count($test_results);
        ?>

        <!-- Summary Section -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card <?php echo $successful == $total ? 'status-success' : 'status-failed'; ?>">
                    <div class="card-body text-center">
                        <h2 class="card-title">
                            <?php if ($successful == $total): ?>
                                🎉 All QR Code Types Working Perfectly!
                            <?php else: ?>
                                ⚠️ Some Issues Found
                            <?php endif; ?>
                        </h2>
                        <h3 class="mb-3">Test Results: <?php echo $successful; ?>/<?php echo $total; ?> QR Codes Generated Successfully</h3>
                        <p class="mb-0">
                            <?php if ($successful == $total): ?>
                                Your QR code system is fully functional and ready for use.
                            <?php else: ?>
                                Please check the individual results below for troubleshooting.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Codes Grid -->
        <div class="row g-4 mb-5">
            <?php foreach ($qr_codes_data as $type => $data): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card qr-test-card h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <i class="<?php echo $data['success'] ? $data['icon'] : 'bi-exclamation-triangle'; ?> me-2" style="color: <?php echo $data['success'] ? $data['color'] : '#d32f2f'; ?>; font-size: 1.2em;"></i>
                                <h5 class="mb-0"><?php echo $data['success'] ? $data['title'] : ucfirst(str_replace('_', ' ', $type)) . ' QR Code'; ?></h5>
                            </div>
                            <span class="badge qr-type-badge <?php echo $data['success'] ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $data['success'] ? 'SUCCESS' : 'FAILED'; ?>
                            </span>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($data['success']): ?>
                                <img src="<?php echo $data['url']; ?>" alt="<?php echo $data['title']; ?>" class="qr-image mb-3">
                                <p class="text-muted mb-2"><?php echo $data['description']; ?></p>
                                <div class="mt-3">
                                    <small class="text-muted d-block mb-1"><strong>Content:</strong></small>
                                    <small class="text-break" style="font-size: 0.75em;"><?php echo htmlspecialchars($data['content']); ?></small>
                                </div>
                                <?php if (isset($data['extra'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted"><?php echo $data['extra']; ?></small>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Error:</strong> <?php echo htmlspecialchars($data['error']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Quick Links -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-link-45deg me-2"></i>Quick Links</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><a href="/qr-generator.php" target="_blank" class="btn btn-outline-primary btn-sm me-2 mb-2"><i class="bi bi-plus-square me-1"></i>QR Generator Interface</a></li>
                                    <li><a href="/qr-codes.php" target="_blank" class="btn btn-outline-secondary btn-sm me-2 mb-2"><i class="bi bi-collection me-1"></i>View All QR Codes</a></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><a href="/qr-display.php" target="_blank" class="btn btn-outline-info btn-sm me-2 mb-2"><i class="bi bi-display me-1"></i>QR Display Page</a></li>
                                    <li><a href="/uploads/qr/" target="_blank" class="btn btn-outline-warning btn-sm me-2 mb-2"><i class="bi bi-folder me-1"></i>QR Files Directory</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Instructions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-info-circle me-2"></i>Test Instructions</h5>
                    </div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li><strong>Scan each QR code</strong> with your phone to test functionality</li>
                            <li><strong>Verify the URLs</strong> point to the correct pages</li>
                            <li><strong>Test user flows</strong> - voting, promotions, spin wheel, etc.</li>
                            <li><strong>Check mobile responsiveness</strong> of landing pages</li>
                            <li><strong>Validate data tracking</strong> - ensure scans are logged properly</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 