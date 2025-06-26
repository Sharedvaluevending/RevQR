<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/includes/header.php';

// Get all QR codes from database for display (public view)
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$sort = $_GET['sort'] ?? 'created_desc';

// Build query conditions
$where_conditions = ["status != 'deleted'"];
$params = [];

if ($search) {
    $where_conditions[] = "(code LIKE ? OR machine_name LIKE ? OR meta LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($type_filter) {
    $where_conditions[] = "qr_type = ?";
    $params[] = $type_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get QR codes
$order_clause = match($sort) {
    'created_asc' => 'ORDER BY created_at ASC',
    'name_asc' => 'ORDER BY machine_name ASC',
    'name_desc' => 'ORDER BY machine_name DESC',
    'type_asc' => 'ORDER BY qr_type ASC',
    default => 'ORDER BY created_at DESC'
};

$sql = "SELECT * FROM qr_codes WHERE $where_clause $order_clause LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$qr_codes = $stmt->fetchAll();

// Get analytics summary
$analytics_sql = "
    SELECT 
        qr_type,
        COUNT(*) as count,
        SUM((SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = qr_codes.id)) as total_scans
    FROM qr_codes
    WHERE status != 'deleted'
    GROUP BY qr_type
    ORDER BY count DESC
";
$stmt = $pdo->prepare($analytics_sql);
$stmt->execute();
$analytics_data = $stmt->fetchAll();

// Get recent scan activity - Use qr_scans if qr_code_stats doesn't exist
$recent_scans_sql = "
    SELECT 
        COALESCE(qcs.scan_time, qs.created_at) as scanned_at,
        COALESCE(qcs.device_type, qs.device_type) as device_type,
        qc.code,
        qc.machine_name,
        qc.qr_type
    FROM qr_codes qc
    LEFT JOIN qr_code_stats qcs ON qcs.qr_code_id = qc.id
    LEFT JOIN qr_scans qs ON qs.qr_code_id = qc.id
    WHERE qcs.id IS NOT NULL OR qs.id IS NOT NULL
    ORDER BY COALESCE(qcs.scan_time, qs.created_at) DESC
    LIMIT 10
";
$stmt = $pdo->prepare($recent_scans_sql);
$stmt->execute();
$recent_scans = $stmt->fetchAll();
?>

<style>
.qr-display-container {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.qr-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    height: 100%;
}

.qr-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    border-color: rgba(255, 255, 255, 0.3);
}

.qr-preview {
    width: 150px;
    height: 150px;
    border: 3px solid #fff;
    border-radius: 15px;
    background: white;
    padding: 10px;
    margin: 0 auto 1rem;
    display: block;
    object-fit: contain;
}

.qr-info h5 {
    color: #fff;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.qr-details {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.qr-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.stat-item {
    text-align: center;
}

.stat-value {
    color: #00ff88;
    font-weight: 700;
    font-size: 1.2rem;
}

.stat-label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
}

.qr-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

.analytics-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.analytics-card {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
}

.analytics-value {
    font-size: 2rem;
    font-weight: 700;
    color: #00ff88;
    margin-bottom: 0.5rem;
}

.analytics-label {
    color: #fff;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.analytics-sub {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.filter-bar {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    padding: 1rem;
    margin-bottom: 2rem;
}

.recent-activity {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    padding: 1.5rem;
    margin-top: 2rem;
}

.activity-item {
    display: flex;
    justify-content: between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-time {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.8rem;
}

.qr-type-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-weight: 600;
}
</style>

<div class="container-fluid mt-5 pt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="qr-display-container">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1 text-white">
                            <i class="bi bi-qr-code me-2"></i>QR Code Gallery
                        </h1>
                        <p class="text-light mb-0">Interactive QR codes with live analytics and test links</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="qr-generator.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Generate QR
                        </a>
                        <a href="qr_manager.php" class="btn btn-outline-light">
                            <i class="bi bi-gear me-2"></i>Manager View
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Overview -->
    <div class="analytics-overview">
        <?php foreach ($analytics_data as $analytics): ?>
            <div class="analytics-card">
                <div class="analytics-value"><?php echo $analytics['count']; ?></div>
                <div class="analytics-label"><?php echo ucfirst(str_replace('_', ' ', $analytics['qr_type'])); ?></div>
                <div class="analytics-sub"><?php echo number_format($analytics['total_scans']); ?> total scans</div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label text-light">Search</label>
                <input type="text" class="form-control" name="search" 
                       placeholder="Search by code, machine, or content..." 
                       value="<?php echo htmlspecialchars($search ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-light">Filter by Type</label>
                <select class="form-select" name="type">
                    <option value="">All Types</option>
                    <?php
                    $all_types = array_unique(array_column($qr_codes, 'qr_type'));
                    foreach ($all_types as $type):
                    ?>
                        <option value="<?php echo $type; ?>" <?php echo $type_filter === $type ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-light">Sort by</label>
                <select class="form-select" name="sort">
                    <option value="created_desc" <?php echo $sort === 'created_desc' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="created_asc" <?php echo $sort === 'created_asc' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                    <option value="type_asc" <?php echo $sort === 'type_asc' ? 'selected' : ''; ?>>Type A-Z</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-light w-100">Apply</button>
            </div>
        </form>
    </div>

    <!-- QR Codes Grid -->
    <?php if (empty($qr_codes)): ?>
        <div class="text-center py-5">
            <i class="bi bi-qr-code text-muted" style="font-size: 4rem;"></i>
            <h3 class="text-muted mt-3">No QR Codes Found</h3>
            <p class="text-muted mb-4">Try adjusting your search filters or create a new QR code.</p>
            <a href="qr-generator.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Generate QR Code
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-4">
            <?php foreach ($qr_codes as $qr): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="qr-card">
                        <!-- QR Preview -->
                        <img src="<?php echo htmlspecialchars($qr['qr_url'] ?? ''); ?>" 
                             alt="QR Code <?php echo htmlspecialchars($qr['code'] ?? ''); ?>" 
                             class="qr-preview">
                        
                        <!-- QR Info -->
                        <div class="qr-info">
                            <h5><?php echo htmlspecialchars($qr['machine_name'] ?: 'QR-' . $qr['code']); ?></h5>
                            <div class="qr-details">
                                <div class="mb-2">
                                    <span class="badge bg-<?php echo $qr['qr_type'] === 'dynamic' ? 'success' : 'primary'; ?> qr-type-badge">
                                        <?php echo ucfirst(str_replace('_', ' ', $qr['qr_type'])); ?>
                                    </span>
                                </div>
                                <div><strong>Code:</strong> <?php echo htmlspecialchars($qr['code'] ?? ''); ?></div>
                                <div><strong>Created:</strong> <?php echo date('M j, Y', strtotime($qr['created_at'])); ?></div>
                                <?php if ($qr['machine_location']): ?>
                                    <div><strong>Location:</strong> <?php echo htmlspecialchars($qr['machine_location'] ?? ''); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Stats -->
                        <div class="qr-stats">
                            <div class="stat-item">
                                <div class="stat-value">
                                    <?php
                                    $scan_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = ?");
                                    $scan_count_stmt->execute([$qr['id']]);
                                    echo number_format($scan_count_stmt->fetchColumn());
                                    ?>
                                </div>
                                <div class="stat-label">Scans</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">
                                    <?php
                                    try {
                                        $vote_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM votes v JOIN machines m ON v.machine_id = m.id WHERE m.name = ?");
                                        $vote_count_stmt->execute([$qr['machine_name']]);
                                        echo number_format($vote_count_stmt->fetchColumn());
                                    } catch (Exception $e) {
                                        echo "0";
                                    }
                                    ?>
                                </div>
                                <div class="stat-label">Votes</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $qr['status'] === 'active' ? '🟢' : '🔴'; ?></div>
                                <div class="stat-label">Status</div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="qr-actions">
                            <a href="<?php echo htmlspecialchars($qr['url'] ?: '/vote.php?code=' . $qr['code']); ?>" 
                               class="btn btn-sm btn-success" target="_blank" title="Test QR Code">
                                <i class="bi bi-play-circle"></i>
                            </a>
                            <a href="<?php echo htmlspecialchars($qr['qr_url'] ?? ''); ?>" 
                               class="btn btn-sm btn-primary" download title="Download">
                                <i class="bi bi-download"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-light" 
                                    onclick="copyToClipboard('<?php echo $qr['code']; ?>')" title="Copy Code">
                                <i class="bi bi-clipboard"></i>
                            </button>
                            <button class="btn btn-sm btn-info" 
                                    onclick="showQRDetails(<?php echo $qr['id']; ?>)" title="View Details">
                                <i class="bi bi-info-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Recent Activity -->
    <?php if (!empty($recent_scans)): ?>
        <div class="recent-activity">
            <h4 class="text-white mb-3">
                <i class="bi bi-activity me-2"></i>Recent Scan Activity
            </h4>
            <?php foreach ($recent_scans as $scan): ?>
                <div class="activity-item">
                    <div class="flex-grow-1">
                        <div class="text-light">
                            <strong><?php echo htmlspecialchars($scan['machine_name'] ?: $scan['code']); ?></strong>
                        </div>
                        <div class="activity-time">
                            <?php echo date('M j, g:i A', strtotime($scan['scanned_at'])); ?> • 
                            <?php echo htmlspecialchars($scan['device_type'] ?? ''); ?>
                        </div>
                    </div>
                    <span class="badge bg-secondary"><?php echo $scan['qr_type']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- QR Details Modal -->
<div class="modal fade" id="qrDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title">QR Code Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="qrDetailsContent">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show toast or alert
        const toast = document.createElement('div');
        toast.className = 'position-fixed top-0 end-0 m-3 bg-success text-white p-2 rounded';
        toast.style.zIndex = '9999';
        toast.textContent = 'Code copied: ' + text;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    });
}

function showQRDetails(qrId) {
    // Load QR details via AJAX
    fetch(`/html/api/qr/details.php?id=${qrId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('qrDetailsContent').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <img src="${data.qr_url}" alt="QR Code" class="img-fluid mb-3">
                    </div>
                    <div class="col-md-6">
                        <h6>Code Information</h6>
                        <p><strong>Code:</strong> ${data.code}</p>
                        <p><strong>Type:</strong> ${data.qr_type}</p>
                        <p><strong>Machine:</strong> ${data.machine_name || 'N/A'}</p>
                        <p><strong>URL:</strong> <a href="${data.url}" target="_blank">${data.url}</a></p>
                        <p><strong>Created:</strong> ${new Date(data.created_at).toLocaleDateString()}</p>
                        <p><strong>Status:</strong> ${data.status}</p>
                    </div>
                </div>
            `;
            new bootstrap.Modal(document.getElementById('qrDetailsModal')).show();
        })
        .catch(error => {
            console.error('Error loading QR details:', error);
        });
}
</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 