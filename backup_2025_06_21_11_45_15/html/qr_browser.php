<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

// Require login
require_login();

$qr_directory = __DIR__ . '/uploads/qr/';
$qr_url_base = '/uploads/qr/';

// Get all QR code files
function getQRCodes($directory) {
    $qr_codes = [];
    
    if (is_dir($directory)) {
        $files = glob($directory . '*.png');
        foreach ($files as $file) {
            $filename = basename($file);
            $qr_codes[] = [
                'filename' => $filename,
                'path' => $file,
                'url' => '/uploads/qr/' . $filename,
                'size' => filesize($file),
                'created' => filemtime($file),
                'is_preview' => strpos($filename, '_preview') !== false
            ];
        }
        
        // Sort by creation time (newest first)
        usort($qr_codes, function($a, $b) {
            return $b['created'] - $a['created'];
        });
    }
    
    return $qr_codes;
}

$qr_codes = getQRCodes($qr_directory);

// Filter out preview versions for main display
$main_qr_codes = array_filter($qr_codes, function($qr) {
    return !$qr['is_preview'];
});

// Get search/filter parameters
$search = $_GET['search'] ?? '';
$show_previews = isset($_GET['show_previews']);

if ($search) {
    $main_qr_codes = array_filter($main_qr_codes, function($qr) use ($search) {
        return stripos($qr['filename'], $search) !== false;
    });
}

require_once __DIR__ . '/core/includes/header.php';
?>

<style>
.qr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.qr-card {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    transition: transform 0.2s;
}

.qr-card:hover {
    transform: translateY(-5px);
    background: rgba(255, 255, 255, 0.15);
}

.qr-image {
    max-width: 200px;
    max-height: 200px;
    border: 2px solid #fff;
    border-radius: 8px;
    margin-bottom: 10px;
    background: white;
    padding: 10px;
}

.qr-info {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9em;
}

.qr-filename {
    word-break: break-all;
    margin: 5px 0;
    font-family: monospace;
    font-size: 0.8em;
    background: rgba(0, 0, 0, 0.3);
    padding: 5px;
    border-radius: 5px;
}

.qr-actions {
    margin-top: 10px;
}

.qr-actions a {
    margin: 0 5px;
    padding: 5px 10px;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    text-decoration: none;
    border-radius: 5px;
    font-size: 0.8em;
}

.qr-actions a:hover {
    background: rgba(255, 255, 255, 0.3);
}

.search-bar {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.search-bar input, .search-bar button {
    margin: 5px;
}

.stats {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
    color: rgba(255, 255, 255, 0.9);
}

.empty-state {
    text-align: center;
    padding: 50px;
    color: rgba(255, 255, 255, 0.7);
}

.delete-btn {
    background: rgba(220, 53, 69, 0.8) !important;
}

.delete-btn:hover {
    background: rgba(220, 53, 69, 1) !important;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">QR Code Browser</h1>
            <p class="text-muted">View and manage all your generated QR codes</p>
        </div>
        <div>
            <a href="/html/qr-generator.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Generate New QR Code
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats">
        <div class="row">
            <div class="col-md-3">
                <strong><?php echo count($main_qr_codes); ?></strong> QR Codes
            </div>
            <div class="col-md-3">
                <strong><?php echo count(array_filter($qr_codes, function($qr) { return $qr['is_preview']; })); ?></strong> Preview Files
            </div>
            <div class="col-md-3">
                <strong><?php echo round(array_sum(array_column($qr_codes, 'size')) / 1024 / 1024, 2); ?> MB</strong> Total Size
            </div>
            <div class="col-md-3">
                <strong><?php echo date('Y-m-d H:i', max(array_column($qr_codes, 'created'))); ?></strong> Latest
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-bar">
        <form method="GET" class="row align-items-center">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" 
                       placeholder="Search QR codes..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="show_previews" 
                           <?php echo $show_previews ? 'checked' : ''; ?>>
                    <label class="form-check-label" style="color: rgba(255, 255, 255, 0.9);">
                        Show preview files
                    </label>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-light">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </form>
    </div>

    <?php if (empty($main_qr_codes)): ?>
        <div class="empty-state">
            <i class="bi bi-qr-code" style="font-size: 4rem; margin-bottom: 20px;"></i>
            <h3>No QR codes found</h3>
            <p>
                <?php if ($search): ?>
                    No QR codes match your search criteria. <a href="?">View all QR codes</a>
                <?php else: ?>
                    You haven't generated any QR codes yet. <a href="/html/qr-generator.php">Create your first QR code</a>
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <!-- QR Code Grid -->
        <div class="qr-grid">
            <?php foreach ($main_qr_codes as $qr): ?>
                <div class="qr-card">
                    <img src="<?php echo $qr['url']; ?>" alt="QR Code" class="qr-image">
                    
                    <div class="qr-info">
                        <div class="qr-filename"><?php echo htmlspecialchars($qr['filename']); ?></div>
                        <div>Size: <?php echo round($qr['size'] / 1024, 1); ?> KB</div>
                        <div>Created: <?php echo date('M j, Y H:i', $qr['created']); ?></div>
                    </div>
                    
                    <div class="qr-actions">
                        <a href="<?php echo $qr['url']; ?>" target="_blank">
                            <i class="bi bi-eye"></i> View
                        </a>
                        <a href="<?php echo $qr['url']; ?>" download="<?php echo $qr['filename']; ?>">
                            <i class="bi bi-download"></i> Download
                        </a>
                        <a href="#" onclick="copyToClipboard('<?php echo APP_URL . $qr['url']; ?>')">
                            <i class="bi bi-clipboard"></i> Copy URL
                        </a>
                        <a href="#" class="delete-btn" onclick="deleteQR('<?php echo $qr['filename']; ?>')">
                            <i class="bi bi-trash"></i> Delete
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('URL copied to clipboard!');
    }, function(err) {
        console.error('Could not copy text: ', err);
        alert('Failed to copy URL');
    });
}

function deleteQR(filename) {
    if (confirm('Are you sure you want to delete this QR code? This action cannot be undone.')) {
        fetch('/html/api/delete_qr.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                filename: filename
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting QR code: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting QR code');
        });
    }
}
</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 