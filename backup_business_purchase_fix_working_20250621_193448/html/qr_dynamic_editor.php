<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';

// Require business role
require_role('business');

$business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_voting_list':
                $name = $_POST['list_name'];
                $description = $_POST['list_description'];
                
                $stmt = $pdo->prepare("INSERT INTO voting_lists (business_id, name, description) VALUES (?, ?, ?)");
                $stmt->execute([$business_id, $name, $description]);
                $list_id = $pdo->lastInsertId();
                
                $message = "Created voting list '{$name}' (ID: {$list_id})";
                $message_type = 'success';
                break;
                
            case 'create_campaign':
                $name = $_POST['campaign_name'];
                $description = $_POST['campaign_description'];
                
                $stmt = $pdo->prepare("INSERT INTO campaigns (business_id, name, description, status) VALUES (?, ?, ?, 'active')");
                $stmt->execute([$business_id, $name, $description]);
                $campaign_id = $pdo->lastInsertId();
                
                $message = "Created campaign '{$name}' (ID: {$campaign_id})";
                $message_type = 'success';
                break;
                
            case 'create_qr_code':
                $qr_type = $_POST['qr_type'];
                $campaign_id = $_POST['campaign_id'] ?? null;
                $machine_name = $_POST['machine_name'];
                
                $qr_code = 'dyn_' . uniqid();
                
                // Build correct URL based on QR type
                switch ($qr_type) {
                    case 'dynamic_voting':
                        $url = APP_URL . '/vote.php?code=' . $qr_code;
                        break;
                    case 'machine_sales':
                        $url = APP_URL . '/purchase.php?code=' . $qr_code;
                        break;
                    case 'promotion':
                        $url = APP_URL . '/promotion.php?code=' . $qr_code;
                        break;
                    case 'spin_wheel':
                        $url = APP_URL . '/spin-wheel.php?code=' . $qr_code;
                        break;
                    case 'dynamic_vending':
                        $url = APP_URL . '/vending.php?code=' . $qr_code;
                        break;
                    default:
                        $url = APP_URL . '/vote.php?code=' . $qr_code;
                        break;
                }
                
                $meta_data = json_encode([
                    'campaign_id' => $campaign_id,
                    'file_path' => '/uploads/qr/' . $qr_code . '.png',
                    'editor_created' => true,
                    'qr_type' => $qr_type
                ]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO qr_codes (business_id, campaign_id, qr_type, code, machine_name, url, meta, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    $business_id, 
                    $campaign_id, 
                    $qr_type, 
                    $qr_code, 
                    $machine_name,
                    $url,
                    $meta_data
                ]);
                
                $qr_id = $pdo->lastInsertId();
                $message = "Created QR code '{$qr_code}' - Type: {$qr_type} - Test URL: {$url}";
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get existing data
$voting_lists = $pdo->prepare("SELECT * FROM voting_lists WHERE business_id = ? ORDER BY name");
$voting_lists->execute([$business_id]);
$voting_lists = $voting_lists->fetchAll();

$campaigns = $pdo->prepare("SELECT * FROM campaigns WHERE business_id = ? ORDER BY name");
$campaigns->execute([$business_id]);
$campaigns = $campaigns->fetchAll();

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h2><i class="bi bi-pencil-square"></i> Dynamic QR Code Editor</h2>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Create Voting List -->
                        <div class="col-md-4">
                            <h4>Create Voting List</h4>
                            <form method="POST">
                                <input type="hidden" name="action" value="create_voting_list">
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="list_name" placeholder="List Name" required>
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="list_description" placeholder="Description">
                                </div>
                                <button type="submit" class="btn btn-success">Create List</button>
                            </form>
                        </div>

                        <!-- Create Campaign -->
                        <div class="col-md-4">
                            <h4>Create Campaign</h4>
                            <form method="POST">
                                <input type="hidden" name="action" value="create_campaign">
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="campaign_name" placeholder="Campaign Name" required>
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="campaign_description" placeholder="Description">
                                </div>
                                <button type="submit" class="btn btn-warning">Create Campaign</button>
                            </form>
                        </div>

                        <!-- Create QR Code -->
                        <div class="col-md-4">
                            <h4>Create QR Code</h4>
                            <form method="POST">
                                <input type="hidden" name="action" value="create_qr_code">
                                <div class="mb-3">
                                    <select class="form-select" name="qr_type" required>
                                        <option value="dynamic_voting">Voting QR</option>
                                        <option value="machine_sales">Sales QR</option>
                                        <option value="promotion">Promotion QR</option>
                                        <option value="spin_wheel">Spin Wheel QR</option>
                                        <option value="dynamic_vending">Vending QR</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <select class="form-select" name="campaign_id">
                                        <option value="">Select Campaign</option>
                                        <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo $campaign['id']; ?>">
                                            <?php echo htmlspecialchars($campaign['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="machine_name" placeholder="Machine Name" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Create QR</button>
                            </form>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h3>Quick Actions</h3>
                        <div class="btn-group">
                            <a href="qr_manager.php" class="btn btn-primary">QR Manager</a>
                            <a href="qr_comprehensive_test.php" class="btn btn-success">Run Tests</a>
                            <a href="vote.php" class="btn btn-info" target="_blank">Test Voting</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 