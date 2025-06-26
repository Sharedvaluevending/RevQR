<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';

// Require business role
require_role('business');

// Set JSON header
header('Content-Type: application/json');

// Verify CSRF token
if (!verify_csrf_token()) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

try {
    // Get business details
    $stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $business = $stmt->fetch();
    $business_id = $business ? $business['id'] : 0;
    
    if (!$business) {
        echo json_encode(['success' => false, 'message' => 'Business not found']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    $item_id = (int)($_POST['item_id'] ?? 0);
    
    if (!$item_id) {
        echo json_encode(['success' => false, 'message' => 'Item ID required']);
        exit;
    }
    
    switch ($action) {
        case 'restock':
            // Restock a machine
        $machine_id = (int)($_POST['machine_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
            $notes = $_POST['notes'] ?? '';
        
        if (!$machine_id || $quantity <= 0) {
                echo json_encode(['success' => false, 'message' => 'Machine ID and quantity required']);
                exit;
            }

            // Update voting_list_items inventory
            $stmt = $pdo->prepare("
                UPDATE voting_list_items 
                SET inventory = inventory + ? 
                WHERE voting_list_id = ? AND master_item_id = ?
            ");
            $stmt->execute([$quantity, $machine_id, $item_id]);

            // Log the transaction
            $stmt = $pdo->prepare("
                INSERT INTO inventory_transactions 
                (business_id, master_item_id, transaction_type, to_location_type, to_location_name, quantity, notes, user_id) 
                VALUES (?, ?, 'restock', 'machine', 
                    (SELECT name FROM voting_lists WHERE id = ?), 
                    ?, ?, ?)
            ");
            $stmt->execute([$business_id, $item_id, $machine_id, $quantity, $notes, $_SESSION['user_id']]);

            echo json_encode(['success' => true, 'message' => 'Machine restocked successfully']);
            break;

        case 'adjust_stock':
            // Adjust stock levels across machines
            $stock_data = $_POST['stock'] ?? [];

            $pdo->beginTransaction();
        
            foreach ($stock_data as $machine_id => $new_stock) {
            $machine_id = (int)$machine_id;
            $new_stock = (int)$new_stock;
            
                if ($machine_id > 0) {
                    // Get current stock
                    $stmt = $pdo->prepare("
                        SELECT inventory FROM voting_list_items 
                        WHERE voting_list_id = ? AND master_item_id = ?
                    ");
                    $stmt->execute([$machine_id, $item_id]);
                    $current_stock = (int)($stmt->fetchColumn() ?: 0);

                    // Update stock
                    $stmt = $pdo->prepare("
                        UPDATE voting_list_items 
                        SET inventory = ? 
                        WHERE voting_list_id = ? AND master_item_id = ?
                    ");
                    $stmt->execute([$new_stock, $machine_id, $item_id]);

                    // Log the adjustment
                    $difference = $new_stock - $current_stock;
                    if ($difference != 0) {
                        $stmt = $pdo->prepare("
                            INSERT INTO inventory_transactions 
                            (business_id, master_item_id, transaction_type, to_location_type, to_location_name, quantity, notes, user_id) 
                            VALUES (?, ?, 'adjustment', 'machine', 
                                (SELECT name FROM voting_lists WHERE id = ?), 
                                ?, 'Stock level adjustment', ?)
                        ");
                        $stmt->execute([$business_id, $item_id, $machine_id, $difference, $_SESSION['user_id']]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Stock levels updated successfully']);
            break;

        case 'add_warehouse_stock':
            // Add stock to warehouse
            $quantity = (int)($_POST['quantity'] ?? 0);

            if ($quantity <= 0) {
                echo json_encode(['success' => false, 'message' => 'Quantity must be positive']);
                exit;
            }

            // Update or insert warehouse inventory
            $stmt = $pdo->prepare("
                INSERT INTO warehouse_inventory 
                (business_id, master_item_id, location_type, location_name, quantity, minimum_stock, maximum_stock) 
                VALUES (?, ?, 'warehouse', 'Main Warehouse', ?, 20, 500)
                ON DUPLICATE KEY UPDATE quantity = quantity + ?
            ");
            $stmt->execute([$business_id, $item_id, $quantity, $quantity]);

            // Log the transaction
            $stmt = $pdo->prepare("
                INSERT INTO inventory_transactions 
                (business_id, master_item_id, transaction_type, to_location_type, to_location_name, quantity, user_id) 
                VALUES (?, ?, 'restock', 'warehouse', 'Main Warehouse', ?, ?)
            ");
            $stmt->execute([$business_id, $item_id, $quantity, $_SESSION['user_id']]);

            echo json_encode(['success' => true, 'message' => 'Warehouse stock added successfully']);
            break;

        case 'transfer_stock':
            // Transfer stock from warehouse to machine
            $machine_id = (int)($_POST['machine_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 0);

            if (!$machine_id || $quantity <= 0) {
                echo json_encode(['success' => false, 'message' => 'Machine ID and quantity required']);
                exit;
            }

            $pdo->beginTransaction();

            // Check warehouse stock
                        $stmt = $pdo->prepare("
                SELECT quantity FROM warehouse_inventory 
                WHERE business_id = ? AND master_item_id = ? AND location_type = 'warehouse'
                        ");
            $stmt->execute([$business_id, $item_id]);
            $warehouse_stock = (int)($stmt->fetchColumn() ?: 0);

            if ($warehouse_stock < $quantity) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Insufficient warehouse stock']);
                exit;
            }

            // Reduce warehouse stock
            $stmt = $pdo->prepare("
                UPDATE warehouse_inventory 
                SET quantity = quantity - ? 
                WHERE business_id = ? AND master_item_id = ? AND location_type = 'warehouse'
            ");
            $stmt->execute([$quantity, $business_id, $item_id]);

            // Add to machine stock
                    $stmt = $pdo->prepare("
                INSERT INTO voting_list_items (voting_list_id, master_item_id, inventory) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE inventory = inventory + ?
                    ");
            $stmt->execute([$machine_id, $item_id, $quantity, $quantity]);

            // Log the transfer
                        $stmt = $pdo->prepare("
                INSERT INTO inventory_transactions 
                (business_id, master_item_id, transaction_type, from_location_type, from_location_name, 
                 to_location_type, to_location_name, quantity, user_id) 
                VALUES (?, ?, 'transfer', 'warehouse', 'Main Warehouse', 'machine', 
                    (SELECT name FROM voting_lists WHERE id = ?), ?, ?)
                        ");
            $stmt->execute([$business_id, $item_id, $machine_id, $quantity, $_SESSION['user_id']]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Stock transferred successfully']);
            break;

        case 'add_inventory_item':
            // Add a new inventory item to warehouse/storage
            $master_item_id = (int)($_POST['master_item_id'] ?? 0);
            $location_type = $_POST['location_type'] ?? 'warehouse';
            $location_name = trim($_POST['location_name'] ?? '');
            $quantity = (int)($_POST['quantity'] ?? 0);
            $minimum_stock = (int)($_POST['minimum_stock'] ?? 20);
            $maximum_stock = (int)($_POST['maximum_stock'] ?? 500);
            $cost_per_unit = (float)($_POST['cost_per_unit'] ?? 0);
            $supplier_info = trim($_POST['supplier_info'] ?? '');
            $expiry_date = $_POST['expiry_date'] ?? null;
            $notes = trim($_POST['notes'] ?? '');

            // Validate required fields
            if (!$master_item_id || !$location_name || $quantity < 0) {
                echo json_encode(['success' => false, 'message' => 'Master item, location name, and valid quantity are required']);
                exit;
            }

            // Validate location type
            $valid_location_types = ['warehouse', 'storage', 'home', 'supplier'];
            if (!in_array($location_type, $valid_location_types)) {
                echo json_encode(['success' => false, 'message' => 'Invalid location type']);
                exit;
            }

            // Validate expiry date format if provided
            if ($expiry_date && !empty($expiry_date)) {
                $date = DateTime::createFromFormat('Y-m-d', $expiry_date);
                if (!$date || $date->format('Y-m-d') !== $expiry_date) {
                    echo json_encode(['success' => false, 'message' => 'Invalid expiry date format']);
                    exit;
                }
            } else {
                $expiry_date = null;
            }

            // Check if master item exists
            $stmt = $pdo->prepare("SELECT name FROM master_items WHERE id = ?");
            $stmt->execute([$master_item_id]);
            $master_item = $stmt->fetch();
            
            if (!$master_item) {
                echo json_encode(['success' => false, 'message' => 'Selected item not found']);
                exit;
            }

            // Insert or update warehouse inventory
            $stmt = $pdo->prepare("
                INSERT INTO warehouse_inventory 
                (business_id, master_item_id, location_type, location_name, quantity, minimum_stock, maximum_stock, 
                 cost_per_unit, supplier_info, expiry_date, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    quantity = quantity + VALUES(quantity),
                    minimum_stock = VALUES(minimum_stock),
                    maximum_stock = VALUES(maximum_stock),
                    cost_per_unit = VALUES(cost_per_unit),
                    supplier_info = VALUES(supplier_info),
                    expiry_date = VALUES(expiry_date),
                    notes = VALUES(notes),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $result = $stmt->execute([
                $business_id, 
                $master_item_id, 
                $location_type, 
                $location_name, 
                $quantity, 
                $minimum_stock, 
                $maximum_stock, 
                $cost_per_unit > 0 ? $cost_per_unit : null,
                !empty($supplier_info) ? $supplier_info : null,
                $expiry_date,
                !empty($notes) ? $notes : null
            ]);

            if ($result) {
                // Log the transaction
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_transactions 
                    (business_id, master_item_id, transaction_type, to_location_type, to_location_name, 
                     quantity, unit_cost, notes, user_id) 
                    VALUES (?, ?, 'restock', ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $business_id, 
                    $master_item_id, 
                    $location_type, 
                    $location_name, 
                    $quantity, 
                    $cost_per_unit > 0 ? $cost_per_unit : null,
                    "Added new inventory item: {$master_item['name']}" . (!empty($notes) ? " - {$notes}" : ''),
                    $_SESSION['user_id']
                ]);
    
    echo json_encode([
        'success' => true,
                    'message' => "Added {$master_item['name']} to {$location_name} successfully"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add inventory item']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
    $pdo->rollBack();
    }
    error_log("Error updating stock: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating stock']);
}
?> 