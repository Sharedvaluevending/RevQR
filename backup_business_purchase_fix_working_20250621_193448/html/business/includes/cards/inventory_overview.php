<?php
// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Get inventory statistics from voting_list_items
$stmt = $pdo->prepare("
    SELECT 
        SUM(vli.inventory) as total_items,
        COUNT(CASE WHEN vli.inventory <= 5 THEN 1 END) as low_stock_items,
        COUNT(CASE WHEN vli.inventory <= 20 AND vli.inventory > 5 THEN 1 END) as medium_stock_items,
        COUNT(DISTINCT vl.id) as machines_with_stock
    FROM voting_list_items vli
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? AND vli.inventory > 0
");
$stmt->execute([$business_id]);
$inventoryStats = $stmt->fetch();
$totalItems = $inventoryStats['total_items'] ?? 0;
$lowStockItems = $inventoryStats['low_stock_items'] ?? 0;
$mediumStockItems = $inventoryStats['medium_stock_items'] ?? 0;
$machinesWithStock = $inventoryStats['machines_with_stock'] ?? 0;

// Get inventory by machine for modal
$stmt = $pdo->prepare("
    SELECT 
        vl.name as machine_name, 
        SUM(vli.inventory) as total_quantity, 
        COUNT(*) as item_types
    FROM voting_list_items vli
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? AND vli.inventory > 0
    GROUP BY vl.id, vl.name
    ORDER BY total_quantity DESC
    LIMIT 10
");
$stmt->execute([$business_id]);
$machineInventory = $stmt->fetchAll();

// Get low stock items for modal
$stmt = $pdo->prepare("
    SELECT 
        vli.item_name, 
        vl.name as machine_name, 
        vli.inventory as quantity
    FROM voting_list_items vli
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? AND vli.inventory <= 5 AND vli.inventory > 0
    ORDER BY vli.inventory ASC
    LIMIT 10
");
$stmt->execute([$business_id]);
$lowStockDetails = $stmt->fetchAll();

// Calculate stock status
$criticalItems = $lowStockItems;
$warningItems = $mediumStockItems;
$statusColor = $criticalItems > 0 ? 'danger' : ($warningItems > 0 ? 'warning' : 'success');
$statusText = $criticalItems > 0 ? 'Critical' : ($warningItems > 0 ? 'Low Stock' : 'Good');
?>
<div class="card dashboard-card">
  <div class="card-body">
    <div class="card-title d-flex align-items-center">
      <i class="bi bi-boxes text-warning me-2 fs-4"></i>
      Inventory Overview
    </div>
    <div class="card-metric" id="inventory-metric"><?php echo number_format($totalItems); ?></div>
    <div class="small text-muted mb-2">Total items in stock</div>
    <div class="row text-center">
      <div class="col-4">
        <div class="small text-muted">Status</div>
        <div class="fw-bold text-<?php echo $statusColor; ?>"><?php echo $statusText; ?></div>
      </div>
      <div class="col-4">
        <div class="small text-muted">Low Stock</div>
        <div class="fw-bold text-<?php echo $criticalItems > 0 ? 'danger' : 'success'; ?>"><?php echo $criticalItems; ?></div>
      </div>
      <div class="col-4">
        <div class="small text-muted">Machines</div>
        <div class="fw-bold text-info"><?php echo $machinesWithStock; ?></div>
      </div>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/business/stock-management.php" class="btn btn-outline-warning btn-sm">Manage Stock</a>
  </div>
</div>

<!-- Inventory Details Modal -->
<div class="modal fade" id="inventoryDetailsModal" tabindex="-1" aria-labelledby="inventoryDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="inventoryDetailsModalLabel">Inventory Overview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <h6>Inventory by Machine</h6>
            <?php if (empty($machineInventory)): ?>
              <p class="text-muted small">No inventory data available.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Machine</th>
                      <th>Items</th>
                      <th>Types</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($machineInventory as $machine): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                        <td><?php echo number_format($machine['total_quantity']); ?></td>
                        <td><?php echo $machine['item_types']; ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <h6>Low Stock Alerts (≤5 units)</h6>
            <?php if (empty($lowStockDetails)): ?>
              <p class="text-success small">No low stock items!</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Machine</th>
                      <th>Qty</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($lowStockDetails as $item): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['machine_name']); ?></td>
                        <td><span class="badge bg-danger"><?php echo $item['quantity']; ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="/business/stock-management.php" class="btn btn-primary">Manage Inventory</a>
        <a href="/business/manual-sales.php" class="btn btn-success">Record Sale</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div> 