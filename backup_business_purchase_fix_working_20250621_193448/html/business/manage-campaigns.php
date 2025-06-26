<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

$message = '';
$message_type = '';

// Helper function to get or create business_id (same as list-maker.php)
function getOrCreateBusinessId($pdo, $user_id) {
    try {
        // First try to get business_id from users table
        $stmt = $pdo->prepare("SELECT business_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && $user['business_id']) {
            return $user['business_id'];
        }
        
        // If no business_id in users table, check businesses table
        $stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $business = $stmt->fetch();
        
        if ($business) {
            // Update users table with business_id for consistency
            $stmt = $pdo->prepare("UPDATE users SET business_id = ? WHERE id = ?");
            $stmt->execute([$business['id'], $user_id]);
            return $business['id'];
        }
        
        // Create new business if none exists
        $stmt = $pdo->prepare("INSERT INTO businesses (user_id, name, slug) VALUES (?, ?, ?)");
        $slug = 'my-business-' . time() . '-' . $user_id;
        $stmt->execute([$user_id, 'My Business', $slug]);
        $business_id = $pdo->lastInsertId();
        
        // Update users table with new business_id
        $stmt = $pdo->prepare("UPDATE users SET business_id = ? WHERE id = ?");
        $stmt->execute([$business_id, $user_id]);
        
        return $business_id;
    } catch (Exception $e) {
        error_log("Error in getOrCreateBusinessId: " . $e->getMessage());
        throw new Exception("Unable to determine business association");
    }
}

// Get business_id with proper error handling
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = "danger";
    $business_id = null;
}

// Handle campaign deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_campaign') {
    try {
        $campaign_id = (int)$_POST['campaign_id'];
        
        $pdo->beginTransaction();
        
        // Delete campaign lists first
        $stmt = $pdo->prepare("DELETE FROM campaign_voting_lists WHERE campaign_id = ?");
        $stmt->execute([$campaign_id]);
        
        // Delete the campaign
        $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = ? AND business_id = ?");
        $stmt->execute([$campaign_id, $business_id]);
        
        $pdo->commit();
        $message = "Campaign deleted successfully!";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error deleting campaign: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get all campaigns for this business
$stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(DISTINCT cvl.voting_list_id) as list_count,
           COUNT(DISTINCT qr.id) as qr_count,
           DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i') as formatted_date
    FROM campaigns c
    LEFT JOIN campaign_voting_lists cvl ON c.id = cvl.campaign_id
    LEFT JOIN qr_codes qr ON c.id = qr.campaign_id
    WHERE c.business_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->execute([$business_id]);
$campaigns = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Custom table styling to fix visibility issues */
.table {
    background: rgba(255, 255, 255, 0.15) !important;
    backdrop-filter: blur(20px) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
}

.table thead th {
    background: rgba(30, 60, 114, 0.6) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3) !important;
    padding: 1rem 0.75rem !important;
}

.table tbody td {
    background: rgba(255, 255, 255, 0.12) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    padding: 0.875rem 0.75rem !important;
}

.table tbody tr:hover td {
    background: rgba(255, 255, 255, 0.18) !important;
    color: #ffffff !important;
}

/* Badge styling improvements */
.table .badge {
    font-weight: 500 !important;
    padding: 0.375rem 0.5rem !important;
}

/* Button styling inside tables */
.table .btn-outline-primary,
.table .btn-outline-secondary,
.table .btn-outline-danger {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.table .btn-outline-primary:hover {
    background: rgba(13, 110, 253, 0.8) !important;
    color: #ffffff !important;
}

.table .btn-outline-secondary:hover {
    background: rgba(108, 117, 125, 0.8) !important;
    color: #ffffff !important;
}

.table .btn-outline-danger:hover {
    background: rgba(220, 53, 69, 0.8) !important;
    color: #ffffff !important;
}

/* Empty state styling */
.table tbody td.text-center.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Manage Campaigns</h1>
            <p class="text-muted">Create and manage your marketing campaigns</p>
        </div>
        <a href="create-campaign.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Create New Campaign
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if (empty($campaigns)): ?>
                <div class="text-center py-4">
                    <p class="text-muted mb-0">No campaigns found</p>
                    <a href="create-campaign.php" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-lg me-2"></i>Create Your First Campaign
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Campaign Name</th>
                                <th>Status</th>
                                <th>Lists</th>
                                <th>QR Codes</th>
                                <th>Duration</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $campaign): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $campaign['status'] === 'active' ? 'success' : 
                                                ($campaign['status'] === 'draft' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($campaign['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $campaign['list_count']; ?> lists</td>
                                    <td><?php echo $campaign['qr_count']; ?> codes</td>
                                    <td>
                                        <?php if ($campaign['start_date'] && $campaign['end_date']): ?>
                                            <?php echo date('M d', strtotime($campaign['start_date'])); ?> - 
                                            <?php echo date('M d', strtotime($campaign['end_date'])); ?>
                                        <?php else: ?>
                                            Not set
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $campaign['formatted_date']; ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary view-campaign" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewCampaignModal"
                                                    data-campaign-id="<?php echo $campaign['id']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="edit-campaign.php?id=<?php echo $campaign['id']; ?>" 
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger delete-campaign"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteCampaignModal"
                                                    data-campaign-id="<?php echo $campaign['id']; ?>"
                                                    data-campaign-name="<?php echo htmlspecialchars($campaign['name']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Campaign Modal -->
<div class="modal fade" id="viewCampaignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Campaign Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="campaignDetailsContainer">
                    <!-- Details will be loaded here via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Campaign Modal -->
<div class="modal fade" id="deleteCampaignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Campaign</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the campaign "<span id="deleteCampaignName"></span>"?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="action" value="delete_campaign">
                    <input type="hidden" name="campaign_id" id="deleteCampaignId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Campaign</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle view campaign button click
    document.querySelectorAll('.view-campaign').forEach(button => {
        button.addEventListener('click', function() {
            const campaignId = this.dataset.campaignId;
            const container = document.getElementById('campaignDetailsContainer');
            
            // Show loading spinner
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            // Fetch campaign details
            fetch(`get-campaign-details.php?id=${campaignId}`)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div class="mb-4">
                            <h6>Description</h6>
                            <p>${data.description || 'No description provided'}</p>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Lists (${data.lists.length})</h6>
                                <ul class="list-group">
                                    ${data.lists.map(list => `
                                        <li class="list-group-item">
                                            ${list.name}
                                            <span class="badge bg-primary float-end">${list.item_count} items</span>
                                        </li>
                                    `).join('')}
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>QR Codes (${data.qr_codes.length})</h6>
                                <ul class="list-group">
                                    ${data.qr_codes.map(qr => `
                                        <li class="list-group-item">
                                            ${qr.machine_name}
                                            <span class="badge bg-secondary float-end">${qr.type}</span>
                                        </li>
                                    `).join('')}
                                </ul>
                            </div>
                        </div>
                    `;
                    container.innerHTML = html;
                })
                .catch(error => {
                    container.innerHTML = '<p class="text-center text-danger">Error loading campaign details</p>';
                    console.error('Error:', error);
                });
        });
    });
    
    // Handle delete campaign button click
    document.querySelectorAll('.delete-campaign').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('deleteCampaignId').value = this.dataset.campaignId;
            document.getElementById('deleteCampaignName').textContent = this.dataset.campaignName;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 