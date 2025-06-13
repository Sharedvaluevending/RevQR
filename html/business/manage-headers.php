<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';

// Ensure user is logged in and is a business user
if (!is_logged_in() || !has_role('business')) {
    header('Location: /login.php');
    exit;
}

$message = '';
$message_type = '';

// Handle header image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['header_image'])) {
    $file = $_FILES['header_image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        $message = 'Invalid file type. Please upload a JPG, PNG, or GIF image.';
        $message_type = 'danger';
    } elseif ($file['size'] > $max_size) {
        $message = 'File is too large. Maximum size is 5MB.';
        $message_type = 'danger';
    } else {
        $upload_dir = __DIR__ . '/../uploads/headers/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $filename = uniqid() . '_' . basename($file['name']);
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO header_templates (
                    business_id, name, file_path, created_by, created_at
                ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            if ($stmt->execute([$_SESSION['business_id'], $_POST['template_name'], '/uploads/headers/' . $filename, $_SESSION['user_id']])) {
                $message = 'Header template uploaded successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error saving template to database.';
                $message_type = 'danger';
                unlink($target_path); // Delete uploaded file
            }
        } else {
            $message = 'Error uploading file.';
            $message_type = 'danger';
        }
    }
}

// Get all header templates for this business
$stmt = $pdo->prepare("
    SELECT h.*, u.username as created_by_name
    FROM header_templates h
    LEFT JOIN users u ON h.created_by = u.id
    WHERE h.business_id = ?
    ORDER BY h.created_at DESC
");
$stmt->execute([$_SESSION['business_id']]);
$templates = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">Manage Header Templates</h1>
            <p class="text-muted">Upload and manage header images for your voting pages</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Upload Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Upload New Header Template</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="template_name" class="form-label">Template Name</label>
                    <input type="text" class="form-control" id="template_name" name="template_name" required>
                </div>
                <div class="mb-3">
                    <label for="header_image" class="form-label">Header Image</label>
                    <input type="file" class="form-control" id="header_image" name="header_image" accept="image/*" required>
                    <div class="form-text">Max file size: 5MB. Supported formats: JPG, PNG, GIF</div>
                </div>
                <button type="submit" class="btn btn-primary">Upload Template</button>
            </form>
        </div>
    </div>

    <!-- Templates Grid -->
    <div class="row g-4">
        <?php if (empty($templates)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No header templates found. Upload your first template above.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($templates as $template): ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <img src="<?php echo htmlspecialchars($template['file_path']); ?>" 
                             class="card-img-top" 
                             alt="<?php echo htmlspecialchars($template['name']); ?>"
                             style="height: 200px; object-fit: cover;">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($template['name']); ?></h5>
                            <p class="card-text">
                                <small class="text-muted">
                                    Uploaded by <?php echo htmlspecialchars($template['created_by_name']); ?><br>
                                    <?php echo date('M j, Y', strtotime($template['created_at'])); ?>
                                </small>
                            </p>
                            <div class="btn-group w-100">
                                <button type="button" 
                                        class="btn btn-outline-primary preview-template"
                                        data-bs-toggle="modal"
                                        data-bs-target="#previewModal"
                                        data-template-id="<?php echo $template['id']; ?>"
                                        data-template-name="<?php echo htmlspecialchars($template['name']); ?>"
                                        data-template-path="<?php echo htmlspecialchars($template['file_path']); ?>">
                                    <i class="bi bi-eye"></i> Preview
                                </button>
                                <button type="button" 
                                        class="btn btn-outline-danger delete-template"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteModal"
                                        data-template-id="<?php echo $template['id']; ?>"
                                        data-template-name="<?php echo htmlspecialchars($template['name']); ?>">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview Header Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" class="img-fluid" id="previewImage" alt="Template Preview">
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this template?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="delete-template.php" class="d-inline">
                    <input type="hidden" name="template_id" id="deleteTemplateId">
                    <button type="submit" class="btn btn-danger">Delete Template</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Preview template
document.querySelectorAll('.preview-template').forEach(button => {
    button.addEventListener('click', () => {
        const path = button.dataset.templatePath;
        document.getElementById('previewImage').src = path;
    });
});

// Delete template
document.querySelectorAll('.delete-template').forEach(button => {
    button.addEventListener('click', () => {
        const id = button.dataset.templateId;
        document.getElementById('deleteTemplateId').value = id;
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 