<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

if (!isset($_GET['qr'])) {
    header('Location: ' . APP_URL . '/public/vote.php');
    exit;
}

$qr_code = $_GET['qr'];

// Get QR code details
$stmt = $pdo->prepare("
    SELECT qr.*, c.name as campaign_name, c.business_id
    FROM qr_codes qr
    JOIN qr_campaigns c ON qr.campaign_id = c.id
    WHERE qr.code = ?
");
$stmt->execute([$qr_code]);
$qr = $stmt->fetch();

if (!$qr) {
    header('Location: ' . APP_URL . '/public/vote.php');
    exit;
}

// QR code scan tracking (removed due to schema mismatch)
// Log scan event in machine_engagement
$machine_id = $qr['machine_id'] ?? null;
if (!$machine_id && !empty($qr['machine_name'])) {
    $stmt_mid = $pdo->prepare("SELECT id FROM machines WHERE name = ?");
    $stmt_mid->execute([$qr['machine_name']]);
    $machine_id = $stmt_mid->fetchColumn();
}
$stmt = $pdo->prepare("INSERT INTO machine_engagement (qr_code_id, machine_id, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->execute([
    $qr['id'],
    $machine_id,
    $_SERVER['REMOTE_ADDR'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? ''
]);

// Get items for this business
$items = $pdo->query("
    SELECT i.*, 
           (SELECT COUNT(*) FROM votes v WHERE v.item_id = i.id AND v.vote_type = 'in') as vote_in_count,
           (SELECT COUNT(*) FROM votes v WHERE v.item_id = i.id AND v.vote_type = 'out') as vote_out_count
    FROM items i 
    WHERE i.business_id = " . $qr['business_id'] . " 
    AND i.status = 'active' 
    ORDER BY i.item_name
")->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-0">Vote for Items</h1>
        <p class="text-muted"><?php echo htmlspecialchars($qr['campaign_name']); ?></p>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($items as $item): ?>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h6>
                                    <p class="card-text text-muted">
                                        <small>
                                            <i class="bi bi-tag me-1"></i><?php echo ucfirst($item['type']); ?>
                                            <br>
                                            <i class="bi bi-currency-dollar me-1"></i><?php echo number_format($item['price'], 2); ?>
                                        </small>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="btn-group">
                                            <form method="POST" action="vote.php" class="d-inline">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <input type="hidden" name="vote_type" value="in">
                                                <input type="hidden" name="qr_code_id" value="<?php echo $qr['id']; ?>">
                                                <button type="submit" class="btn btn-outline-success btn-sm">
                                                    <i class="bi bi-hand-thumbs-up me-1"></i>Vote In
                                                    <span class="badge bg-success ms-1"><?php echo $item['vote_in_count']; ?></span>
                                                </button>
                                            </form>
                                            <form method="POST" action="vote.php" class="d-inline">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <input type="hidden" name="vote_type" value="out">
                                                <input type="hidden" name="qr_code_id" value="<?php echo $qr['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="bi bi-hand-thumbs-down me-1"></i>Vote Out
                                                    <span class="badge bg-danger ms-1"><?php echo $item['vote_out_count']; ?></span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 