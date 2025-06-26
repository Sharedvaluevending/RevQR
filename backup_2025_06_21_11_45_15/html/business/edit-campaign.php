<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

$message = '';
$message_type = '';

// Get business details
$stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    header("Location: manage-campaigns.php");
    exit;
}

$business_id = $business['id'];

// Get campaign ID from URL
$campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$campaign_id) {
    header("Location: manage-campaigns.php");
    exit;
}

// Get campaign details
$stmt = $pdo->prepare("
    SELECT c.*, cvl.voting_list_id
    FROM campaigns c
    LEFT JOIN campaign_voting_lists cvl ON c.id = cvl.campaign_id
    WHERE c.id = ? AND c.business_id = ?
");
$stmt->execute([$campaign_id, $business_id]);
$campaign = $stmt->fetch();

if (!$campaign) {
    header("Location: manage-campaigns.php");
    exit;
}

// After fetching $lists and $campaign, fetch spin settings for the attached list
$spin_enabled = 0;
$spin_trigger_count = 3;
$rewards_for_list = [];
if (!empty($campaign['voting_list_id'])) {
    $stmt = $pdo->prepare("SELECT spin_enabled, spin_trigger_count FROM lists WHERE id = ?");
    $stmt->execute([$campaign['voting_list_id']]);
    $list_settings = $stmt->fetch();
    if ($list_settings) {
        $spin_enabled = $list_settings['spin_enabled'];
        $spin_trigger_count = $list_settings['spin_trigger_count'];
    }
    // Fetch rewards for this list
    $stmt = $pdo->prepare("SELECT * FROM rewards WHERE list_id = ?");
    $stmt->execute([$campaign['voting_list_id']]);
    $rewards_for_list = $stmt->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Update campaign
        $stmt = $pdo->prepare("
            UPDATE campaigns 
            SET name = ?, 
                description = ?, 
                start_date = ?, 
                end_date = ?, 
                status = ?
            WHERE id = ? AND business_id = ?
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $_POST['start_date'] ?: null,
            $_POST['end_date'] ?: null,
            $_POST['status'],
            $campaign_id,
            $business_id
        ]);
        
        // Update list association
        $stmt = $pdo->prepare("DELETE FROM campaign_voting_lists WHERE campaign_id = ?");
        $stmt->execute([$campaign_id]);
        
        if (!empty($_POST['list_id'])) {
            $stmt = $pdo->prepare("
                INSERT INTO campaign_voting_lists (campaign_id, voting_list_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$campaign_id, $_POST['list_id']]);
        }
        
        // Update spin settings for the list
        if (!empty($_POST['list_id'])) {
            $stmt = $pdo->prepare("UPDATE lists SET spin_enabled = ?, spin_trigger_count = ? WHERE id = ?");
            $stmt->execute([
                isset($_POST['spin_enabled']) ? 1 : 0,
                intval($_POST['spin_trigger_count'] ?? 3),
                intval($_POST['list_id'])
            ]);
        }
        
        $pdo->commit();
        $message = "Campaign updated successfully!";
        $message_type = "success";
        
        // Refresh campaign data
        $stmt = $pdo->prepare("
            SELECT c.*, cvl.voting_list_id
            FROM campaigns c
            LEFT JOIN campaign_voting_lists cvl ON c.id = cvl.campaign_id
            WHERE c.id = ? AND c.business_id = ?
        ");
        $stmt->execute([$campaign_id, $business_id]);
        $campaign = $stmt->fetch();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error updating campaign: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Handle reward add/edit/deactivate for this list
if (isset($_POST['reward_action']) && !empty($campaign['voting_list_id'])) {
    $fields = [
        'list_id' => $campaign['voting_list_id'],
        'name' => $_POST['reward_name'],
        'description' => $_POST['reward_description'],
        'rarity_level' => intval($_POST['reward_rarity']),
        'image_url' => $_POST['reward_image_url'],
        'code' => $_POST['reward_code'],
        'link' => $_POST['reward_link'],
        'active' => isset($_POST['reward_active']) ? 1 : 0
    ];
    if ($_POST['reward_action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO rewards (list_id, name, description, rarity_level, image_url, code, link, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$fields['list_id'], $fields['name'], $fields['description'], $fields['rarity_level'], $fields['image_url'], $fields['code'], $fields['link'], $fields['active']]);
        $message = 'Prize added!'; $message_type = 'success';
    } elseif ($_POST['reward_action'] === 'edit' && isset($_POST['reward_id'])) {
        $stmt = $pdo->prepare("UPDATE rewards SET name=?, description=?, rarity_level=?, image_url=?, code=?, link=?, active=? WHERE id=? AND list_id=?");
        $stmt->execute([$fields['name'], $fields['description'], $fields['rarity_level'], $fields['image_url'], $fields['code'], $fields['link'], $fields['active'], intval($_POST['reward_id']), $fields['list_id']]);
        $message = 'Prize updated!'; $message_type = 'success';
    } elseif ($_POST['reward_action'] === 'deactivate' && isset($_POST['reward_id'])) {
        $stmt = $pdo->prepare("UPDATE rewards SET active=0 WHERE id=? AND list_id=?");
        $stmt->execute([intval($_POST['reward_id']), $fields['list_id']]);
        $message = 'Prize deactivated.'; $message_type = 'info';
    }
    // Refresh rewards
    $stmt = $pdo->prepare("SELECT * FROM rewards WHERE list_id = ?");
    $stmt->execute([$fields['list_id']]);
    $rewards_for_list = $stmt->fetchAll();
}

// Get all lists for this business
$stmt = $pdo->prepare("
    SELECT vl.*, 
           COUNT(vli.id) as item_count,
           DATE_FORMAT(vl.created_at, '%Y-%m-%d %H:%i') as formatted_date
    FROM voting_lists vl
    LEFT JOIN voting_list_items vli ON vl.id = vli.voting_list_id
    WHERE vl.business_id = ?
    GROUP BY vl.id
    ORDER BY vl.name
");
$stmt->execute([$business_id]);
$lists = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Edit Campaign</h1>
            <p class="text-muted">Update your marketing campaign</p>
        </div>
        <a href="manage-campaigns.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to Campaigns
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
            <form method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label">Campaign Name</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($campaign['name']); ?>" required>
                            <div class="form-text">Give your campaign a descriptive name</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($campaign['description']); ?></textarea>
                            <div class="form-text">Describe the purpose and goals of this campaign</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date" value="<?php echo $campaign['start_date']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" name="end_date" value="<?php echo $campaign['end_date']; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="draft" <?php echo $campaign['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="active" <?php echo $campaign['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Attach List (Optional)</label>
                            <select class="form-select" name="list_id">
                                <option value="">Select a list (optional)</option>
                                <?php foreach ($lists as $list): ?>
                                    <option value="<?php echo $list['id']; ?>" <?php echo $campaign['voting_list_id'] == $list['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($list['name']); ?> 
                                        (<?php echo $list['item_count']; ?> items)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Optionally select a list to attach to this campaign</div>
                        </div>

                        <?php if (!empty($campaign['voting_list_id'])): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="spin_enabled" id="spin_enabled" value="1" <?php if ($spin_enabled) echo 'checked'; ?>>
                                <label class="form-check-label" for="spin_enabled">Enable Spin Wheel for this Campaign</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Spin Trigger Count</label>
                            <input type="number" class="form-control" name="spin_trigger_count" value="<?php echo htmlspecialchars($spin_trigger_count); ?>" min="1" max="10">
                            <div class="form-text">Number of votes/scans before spin is allowed</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-end mt-4">
                    <a href="manage-campaigns.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Campaign</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($rewards_for_list)): ?>
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Rewards for This Campaign/List</h5>
            <a href="spin-wheel.php" class="btn btn-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Manage Prizes</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Rarity</th>
                            <th>Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rewards_for_list as $reward): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($reward['name']); ?></td>
                                <td><?php echo htmlspecialchars($reward['description']); ?></td>
                                <td><?php echo $reward['rarity_level']; ?></td>
                                <td><span class="badge bg-<?php echo $reward['active'] ? 'success' : 'secondary'; ?>"><?php echo $reward['active'] ? 'Active' : 'Inactive'; ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#rewardModal" onclick='openRewardModal("edit", <?php echo json_encode($reward); ?>)'>Edit</button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="reward_action" value="deactivate">
                                        <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger ms-1">Deactivate</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="rewardModal" tabindex="-1" aria-labelledby="rewardModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="rewardForm">
        <div class="modal-header">
          <h5 class="modal-title" id="rewardModalLabel">Add/Edit Prize</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="reward_action" id="reward_action" value="add">
          <input type="hidden" name="reward_id" id="reward_id">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" name="reward_name" id="reward_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="reward_description" id="reward_description" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Rarity Level (1-10)</label>
            <input type="number" class="form-control" name="reward_rarity" id="reward_rarity" min="1" max="10" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Image URL</label>
            <input type="url" class="form-control" name="reward_image_url" id="reward_image_url">
          </div>
          <div class="mb-3">
            <label class="form-label">Code</label>
            <input type="text" class="form-control" name="reward_code" id="reward_code">
          </div>
          <div class="mb-3">
            <label class="form-label">Link</label>
            <input type="url" class="form-control" name="reward_link" id="reward_link">
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="reward_active" id="reward_active">
            <label class="form-check-label" for="reward_active">Active</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Prize</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

function openRewardModal(action, reward = null) {
  document.getElementById('reward_action').value = action;
  document.getElementById('rewardModalLabel').textContent = action === 'add' ? 'Add Prize' : 'Edit Prize';
  if (action === 'edit' && reward) {
    document.getElementById('reward_id').value = reward.id;
    document.getElementById('reward_name').value = reward.name;
    document.getElementById('reward_description').value = reward.description;
    document.getElementById('reward_rarity').value = reward.rarity_level;
    document.getElementById('reward_image_url').value = reward.image_url || '';
    document.getElementById('reward_code').value = reward.code || '';
    document.getElementById('reward_link').value = reward.link || '';
    document.getElementById('reward_active').checked = reward.active == 1;
  } else {
    document.getElementById('rewardForm').reset();
    document.getElementById('reward_id').value = '';
    document.getElementById('reward_active').checked = true;
  }
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 