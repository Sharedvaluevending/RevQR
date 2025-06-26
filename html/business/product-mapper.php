<?php
/**
 * Product Mapper - Nayax Integration
 * Maps QR store items to actual Nayax machine products using real-time API data
 * Based on: https://developerhub.nayax.com/reference/get-machine-products
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business') {
    header('Location: /login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$success_message = '';
$error_message = '';

// Check if business has Nayax integration
$stmt = $pdo->prepare("
    SELECT AES_DECRYPT(access_token, 'nayax_secure_key_2025') as access_token, api_url, total_machines
    FROM business_nayax_credentials 
    WHERE business_id = ? AND is_active = 1
");
$stmt->execute([$business_id]);
$nayax_credentials = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nayax_credentials) {
    header('Location: /html/business/nayax-settings.php?error=' . urlencode('Please connect your Nayax account first'));
    exit;
}

// Get business machines
$stmt = $pdo->prepare("
    SELECT nm.*, 
           COUNT(ppm.id) as mapped_products_count,
           (SELECT COUNT(*) FROM nayax_machine_inventory nmi WHERE nmi.machine_id = nm.nayax_machine_id) as total_products_count
    FROM nayax_machines nm
    LEFT JOIN product_mapping ppm ON nm.nayax_machine_id = ppm.nayax_machine_id AND ppm.business_id = ?
    WHERE nm.business_id = ? AND nm.status = 'active'
    GROUP BY nm.id
    ORDER BY nm.machine_name
");
$stmt->execute([$business_id, $business_id]);
$machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get QR store items available for mapping
$stmt = $pdo->prepare("
    SELECT bsi.*, qsi.id as qr_store_item_id,
           (SELECT COUNT(*) FROM product_mapping pm WHERE pm.qr_store_item_id = qsi.id) as mapping_count
    FROM business_store_items bsi
    LEFT JOIN qr_store_items qsi ON bsi.id = qsi.business_store_item_id
    WHERE bsi.business_id = ? AND bsi.is_active = 1 AND bsi.category != 'discount'
    ORDER BY bsi.name
");
$stmt->execute([$business_id]);
$qr_store_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_mapping') {
        $qr_store_item_id = (int)$_POST['qr_store_item_id'];
        $nayax_machine_id = $_POST['nayax_machine_id'];
        $nayax_product_selection = $_POST['nayax_product_selection'];
        $mapping_type = $_POST['mapping_type'] ?? 'direct';
        
        try {
            // Get product details from Nayax API first
            $product_details = fetchNayaxProductDetails($nayax_machine_id, $nayax_product_selection, $nayax_credentials);
            
            if (!$product_details) {
                throw new Exception('Could not fetch product details from Nayax API');
            }
            
            // Create mapping record
            $stmt = $pdo->prepare("
                INSERT INTO product_mapping (
                    business_id, qr_store_item_id, nayax_machine_id, nayax_product_selection,
                    nayax_product_name, nayax_product_price, mapping_type, confidence_score,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 95, NOW())
            ");
            
            $result = $stmt->execute([
                $business_id,
                $qr_store_item_id,
                $nayax_machine_id,
                $nayax_product_selection,
                $product_details['name'] ?? 'Unknown Product',
                $product_details['price'] ?? 0,
                $mapping_type
            ]);
            
            if ($result) {
                $success_message = 'Product mapping created successfully!';
                
                // Update QR store item with Nayax details
                $stmt = $pdo->prepare("
                    UPDATE business_store_items 
                    SET nayax_item_selection = ?, original_price_cents = ?
                    WHERE id = (SELECT business_store_item_id FROM qr_store_items WHERE id = ?)
                ");
                $stmt->execute([
                    $nayax_product_selection,
                    ($product_details['price'] ?? 0) * 100, // Convert to cents
                    $qr_store_item_id
                ]);
            } else {
                $error_message = 'Failed to create product mapping';
            }
            
        } catch (Exception $e) {
            $error_message = 'Error: ' . $e->getMessage();
        }
    }
    
    elseif ($action === 'sync_machine_products') {
        $machine_id = $_POST['machine_id'];
        $result = syncMachineProducts($machine_id, $nayax_credentials, $business_id);
        
        if ($result['success']) {
            $success_message = "Synced {$result['product_count']} products from machine";
        } else {
            $error_message = $result['error'];
        }
    }
    
    elseif ($action === 'delete_mapping') {
        $mapping_id = (int)$_POST['mapping_id'];
        
        $stmt = $pdo->prepare("DELETE FROM product_mapping WHERE id = ? AND business_id = ?");
        if ($stmt->execute([$mapping_id, $business_id])) {
            $success_message = 'Product mapping deleted successfully';
        } else {
            $error_message = 'Failed to delete mapping';
        }
    }
}

/**
 * Fetch product details from Nayax machine products API
 * Based on: https://developerhub.nayax.com/reference/get-machine-products
 */
function fetchNayaxProductDetails($machine_id, $product_selection, $credentials) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $credentials['api_url'] . '/machines/' . $machine_id . '/products');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $credentials['access_token'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $products = json_decode($response, true);
        
        // Find the specific product by selection code
        if (isset($products['products']) && is_array($products['products'])) {
            foreach ($products['products'] as $product) {
                if (isset($product['selection']) && $product['selection'] === $product_selection) {
                    return $product;
                }
            }
        }
    }
    
    return null;
}

/**
 * Sync all products from a Nayax machine
 */
function syncMachineProducts($machine_id, $credentials, $business_id) {
    global $pdo;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $credentials['api_url'] . '/machines/' . $machine_id . '/products');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $credentials['access_token'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return ['success' => false, 'error' => 'Failed to fetch products from Nayax API'];
    }
    
    $data = json_decode($response, true);
    if (!isset($data['products'])) {
        return ['success' => false, 'error' => 'Invalid API response format'];
    }
    
    $product_count = 0;
    
    // Update/insert products into cache
    foreach ($data['products'] as $product) {
        $stmt = $pdo->prepare("
            INSERT INTO nayax_machine_inventory (
                business_id, machine_id, product_selection, product_name, 
                product_price, quantity, last_updated
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            product_name = VALUES(product_name),
            product_price = VALUES(product_price),
            quantity = VALUES(quantity),
            last_updated = NOW()
        ");
        
        $stmt->execute([
            $business_id,
            $machine_id,
            $product['selection'] ?? '',
            $product['name'] ?? 'Unknown Product',
            $product['price'] ?? 0,
            $product['quantity'] ?? 0
        ]);
        
        $product_count++;
    }
    
    return ['success' => true, 'product_count' => $product_count];
}

// Get existing mappings
$stmt = $pdo->prepare("
    SELECT pm.*, 
           bsi.name as qr_item_name,
           nm.machine_name,
           qsi.id as qr_store_item_id
    FROM product_mapping pm
    JOIN qr_store_items qsi ON pm.qr_store_item_id = qsi.id
    JOIN business_store_items bsi ON qsi.business_store_item_id = bsi.id
    JOIN nayax_machines nm ON pm.nayax_machine_id = nm.nayax_machine_id AND nm.business_id = ?
    WHERE pm.business_id = ?
    ORDER BY pm.created_at DESC
");
$stmt->execute([$business_id, $business_id]);
$existing_mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-diagram-3 me-2"></i>Product Mapper</h2>
                    <p class="text-muted mb-0">Map QR Store items to Nayax machine products for seamless integration</p>
                </div>
                <div>
                    <a href="/html/business/nayax-settings.php" class="btn btn-outline-secondary">
                        <i class="bi bi-gear me-1"></i>Nayax Settings
                    </a>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Machine Overview Cards -->
            <div class="row mb-4">
                <?php foreach ($machines as $machine): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="bi bi-hdd-stack me-1"></i>
                                <?php echo htmlspecialchars($machine['machine_name']); ?>
                            </h6>
                            <span class="badge bg-<?php echo $machine['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($machine['status']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h4 class="mb-0 text-primary"><?php echo $machine['mapped_products_count']; ?></h4>
                                        <small class="text-muted">Mapped</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h4 class="mb-0 text-info"><?php echo $machine['total_products_count']; ?></h4>
                                    <small class="text-muted">Available</small>
                                </div>
                            </div>
                            <div class="mt-3">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="sync_machine_products">
                                    <input type="hidden" name="machine_id" value="<?php echo htmlspecialchars($machine['nayax_machine_id']); ?>">
                                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Sync Products
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php if ($machine['last_sync_at']): ?>
                        <div class="card-footer text-muted">
                            <small>Last synced: <?php echo date('M j, Y g:i A', strtotime($machine['last_sync_at'])); ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Create New Mapping -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create New Product Mapping</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="mappingForm">
                        <input type="hidden" name="action" value="create_mapping">
                        
                        <div class="row">
                            <div class="col-md-4">
                                <label for="qr_store_item_id" class="form-label">QR Store Item</label>
                                <select class="form-select" id="qr_store_item_id" name="qr_store_item_id" required>
                                    <option value="">Select QR Store Item...</option>
                                    <?php foreach ($qr_store_items as $item): ?>
                                    <option value="<?php echo $item['qr_store_item_id']; ?>" 
                                            data-price="<?php echo $item['price_qr_coins']; ?>">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                        (<?php echo $item['price_qr_coins']; ?> QR Coins)
                                        <?php if ($item['mapping_count'] > 0): ?>
                                        - <span class="text-warning">Already mapped</span>
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="nayax_machine_id" class="form-label">Nayax Machine</label>
                                <select class="form-select" id="nayax_machine_id" name="nayax_machine_id" required>
                                    <option value="">Select Machine...</option>
                                    <?php foreach ($machines as $machine): ?>
                                    <option value="<?php echo htmlspecialchars($machine['nayax_machine_id']); ?>">
                                        <?php echo htmlspecialchars($machine['machine_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="nayax_product_selection" class="form-label">Machine Product</label>
                                <select class="form-select" id="nayax_product_selection" name="nayax_product_selection" required>
                                    <option value="">Select machine first...</option>
                                </select>
                                <div class="form-text">Product selection code (A1, B2, etc.)</div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="mapping_type" class="form-label">Mapping Type</label>
                                <select class="form-select" id="mapping_type" name="mapping_type">
                                    <option value="direct">Direct Mapping (1:1)</option>
                                    <option value="substitute">Substitute Product</option>
                                    <option value="bundle">Bundle Component</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-link me-1"></i>Create Mapping
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Existing Mappings -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Existing Product Mappings</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($existing_mappings)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-diagram-3 display-4 text-muted"></i>
                        <h5 class="mt-3 text-muted">No Product Mappings Yet</h5>
                        <p class="text-muted">Create your first mapping using the form above to connect QR Store items with Nayax machine products.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>QR Store Item</th>
                                    <th>Machine</th>
                                    <th>Product Selection</th>
                                    <th>Nayax Product</th>
                                    <th>Type</th>
                                    <th>Confidence</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existing_mappings as $mapping): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($mapping['qr_item_name']); ?></strong>
                                    </td>
                                    <td>
                                        <i class="bi bi-hdd-stack me-1"></i>
                                        <?php echo htmlspecialchars($mapping['machine_name']); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($mapping['nayax_product_selection']); ?></span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($mapping['nayax_product_name']); ?>
                                        <?php if ($mapping['nayax_product_price']): ?>
                                        <br><small class="text-muted">$<?php echo number_format($mapping['nayax_product_price'], 2); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo ucfirst($mapping['mapping_type']); ?></span>
                                    </td>
                                    <td>
                                        <div class="progress" style="width: 60px; height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $mapping['confidence_score']; ?>%"
                                                 aria-valuenow="<?php echo $mapping['confidence_score']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $mapping['confidence_score']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small><?php echo date('M j, Y', strtotime($mapping['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this mapping?')">
                                            <input type="hidden" name="action" value="delete_mapping">
                                            <input type="hidden" name="mapping_id" value="<?php echo $mapping['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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
    </div>
</div>

<script>
// Load products when machine is selected
document.getElementById('nayax_machine_id').addEventListener('change', function() {
    const machineId = this.value;
    const productSelect = document.getElementById('nayax_product_selection');
    
    // Clear existing options
    productSelect.innerHTML = '<option value="">Loading products...</option>';
    
    if (!machineId) {
        productSelect.innerHTML = '<option value="">Select machine first...</option>';
        return;
    }
    
    // Fetch products via AJAX
    fetch('/html/api/nayax/get-machine-products.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            machine_id: machineId
        })
    })
    .then(response => response.json())
    .then(data => {
        productSelect.innerHTML = '<option value="">Select product...</option>';
        
        if (data.success && data.products) {
            data.products.forEach(product => {
                const option = document.createElement('option');
                option.value = product.selection;
                option.textContent = `${product.selection} - ${product.name} ($${product.price})`;
                if (product.quantity === 0) {
                    option.textContent += ' - OUT OF STOCK';
                    option.disabled = true;
                }
                productSelect.appendChild(option);
            });
        } else {
            productSelect.innerHTML = '<option value="">No products found</option>';
        }
    })
    .catch(error => {
        console.error('Error fetching products:', error);
        productSelect.innerHTML = '<option value="">Error loading products</option>';
    });
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>