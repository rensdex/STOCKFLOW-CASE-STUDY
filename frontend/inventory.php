<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        if ($_POST['action'] === 'add') {
            $supply_code = 'SP-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $status = $_POST['quantity'] > 10 ? 'In Stock' : ($_POST['quantity'] > 0 ? 'Low Stock' : 'Out of Stock');
            
            $stmt = $pdo->prepare("INSERT INTO school_supplies (supply_code, supply_name, category_id, supplier_id, quantity, unit_price, description, location, status, low_stock_threshold) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$supply_code, $_POST['supply_name'], $_POST['category_id'], $_POST['supplier_id'], $_POST['quantity'], $_POST['unit_price'], $_POST['description'], $_POST['location'], $status, $_POST['low_stock_threshold'] ?? 10]);
            
            // Trigger realtime notification
            triggerInventoryUpdate('added', $_POST['supply_name']);
            triggerRealtimeNotification('New Supply Added', $_POST['supply_name'] . ' has been added to inventory', 'success');
            
            $_SESSION['flash_success'] = "Supply added successfully";
            header("Location: index.php?page=inventory");
            exit();
        } elseif ($_POST['action'] === 'edit') {
            $status = $_POST['quantity'] > 10 ? 'In Stock' : ($_POST['quantity'] > 0 ? 'Low Stock' : 'Out of Stock');
            
            $stmt = $pdo->prepare("UPDATE school_supplies SET supply_name = ?, category_id = ?, supplier_id = ?, quantity = ?, unit_price = ?, description = ?, location = ?, status = ?, low_stock_threshold = ? WHERE id = ?");
            $stmt->execute([$_POST['supply_name'], $_POST['category_id'], $_POST['supplier_id'], $_POST['quantity'], $_POST['unit_price'], $_POST['description'], $_POST['location'], $status, $_POST['low_stock_threshold'] ?? 10, $_POST['id']]);
            
            triggerInventoryUpdate('updated', $_POST['supply_name']);
            
            $_SESSION['flash_success'] = "Supply updated successfully";
                header("Location: index.php?page=inventory");
            exit();
        } elseif ($_POST['action'] === 'delete') {
            // Get supply name before delete
            $getName = $pdo->prepare("SELECT supply_name FROM school_supplies WHERE id = ?");
            $getName->execute([$_POST['id']]);
            $supplyName = $getName->fetchColumn();
            
            $stmt = $pdo->prepare("DELETE FROM school_supplies WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            triggerInventoryUpdate('deleted', $supplyName);
            
            $_SESSION['flash_success'] = "Supply deleted successfully";
                header("Location: index.php?page=inventory");
            exit();
        }
    }
}

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success']);
unset($_SESSION['flash_error']);

// Fetch inventory
$stmt = $pdo->query("SELECT s.*, c.name as category_name, sup.name as supplier_name 
                     FROM school_supplies s 
                     LEFT JOIN categories c ON s.category_id = c.id 
                     LEFT JOIN suppliers sup ON s.supplier_id = sup.id 
                     ORDER BY s.created_at DESC");
$inventory = $stmt->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name")->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>📦 School Supplies Inventory</h4>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="bi bi-plus-lg"></i> Add Supply
        </button>
    </div>
    
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    
    <div class="data-table">
        <div class="p-3">
            <input type="text" id="searchInventory" class="form-control" placeholder="Search by name, category, or supplier...">
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="inventoryTable">
                <thead class="table-light">
                    <tr>
                        <th>CODE</th><th>SUPPLY NAME</th><th>CATEGORY</th><th>SUPPLIER</th><th>QTY</th><th>PRICE</th><th>LOCATION</th><th>STATUS</th><th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                    <tr>
                        <td><?php echo escape($item['supply_code']); ?></td>
                        <td><?php echo escape($item['supply_name']); ?></td>
                        <td><?php echo escape($item['category_name'] ?? 'N/A'); ?></td>
                        <td><?php echo escape($item['supplier_name'] ?? 'N/A'); ?></td>
                        <td class="text-center"><?php echo number_format($item['quantity']); ?></td>
                        <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td><?php echo escape($item['location'] ?? 'N/A'); ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?php echo $item['status'] === 'In Stock' ? 'success' : ($item['status'] === 'Low Stock' ? 'warning' : 'danger'); ?>">
                                <?php echo $item['status']; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary edit-btn" 
                                    data-id="<?php echo $item['id']; ?>"
                                    data-name="<?php echo escape($item['supply_name']); ?>"
                                    data-category="<?php echo $item['category_id']; ?>"
                                    data-supplier="<?php echo $item['supplier_id']; ?>"
                                    data-qty="<?php echo $item['quantity']; ?>"
                                    data-price="<?php echo $item['unit_price']; ?>"
                                    data-desc="<?php echo escape($item['description']); ?>"
                                    data-location="<?php echo escape($item['location']); ?>"
                                    data-threshold="<?php echo $item['low_stock_threshold']; ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $item['id']; ?>" data-name="<?php echo escape($item['supply_name']); ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add School Supply</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label>Supply Name *</label>
                        <input type="text" name="supply_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Category *</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">Select</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo escape($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Supplier *</label>
                            <select name="supplier_id" class="form-control" required>
                                <option value="">Select</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?php echo $sup['id']; ?>"><?php echo escape($sup['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" class="form-control" required min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Unit Price *</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control" required min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Storage Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g., Cabinet A1, Shelf 2">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Low Stock Threshold</label>
                            <input type="number" name="low_stock_threshold" class="form-control" value="10" min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Add Supply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Edit functionality with SweetAlert2
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        Swal.fire({
            title: 'Edit Supply',
            html: `
                <input id="name" class="swal2-input" placeholder="Supply Name" value="${this.dataset.name}" style="width:90%">
                <input id="qty" class="swal2-input" placeholder="Quantity" value="${this.dataset.qty}" style="width:90%">
                <input id="price" class="swal2-input" placeholder="Unit Price" value="${this.dataset.price}" style="width:90%">
                <input id="location" class="swal2-input" placeholder="Location" value="${this.dataset.location || ''}" style="width:90%">
                <input id="threshold" class="swal2-input" placeholder="Low Stock Threshold" value="${this.dataset.threshold || 10}" style="width:90%">
                <textarea id="desc" class="swal2-textarea" placeholder="Description" style="width:90%">${this.dataset.desc || ''}</textarea>
            `,
            showCancelButton: true,
            confirmButtonText: 'Update',
            preConfirm: () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="${this.dataset.id}">
                    <input type="hidden" name="supply_name" value="${document.getElementById('name').value}">
                    <input type="hidden" name="category_id" value="${this.dataset.category}">
                    <input type="hidden" name="supplier_id" value="${this.dataset.supplier}">
                    <input type="hidden" name="quantity" value="${document.getElementById('qty').value}">
                    <input type="hidden" name="unit_price" value="${document.getElementById('price').value}">
                    <input type="hidden" name="location" value="${document.getElementById('location').value}">
                    <input type="hidden" name="low_stock_threshold" value="${document.getElementById('threshold').value}">
                    <input type="hidden" name="description" value="${document.getElementById('desc').value}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});

// Delete confirmation
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        Swal.fire({
            title: 'Delete Supply?',
            text: `Delete "${this.dataset.name}"? This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Delete'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${this.dataset.id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});

// Search functionality
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll(`#${tableId} tbody tr`);
            rows.forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    }
}
searchTable('searchInventory', 'inventoryTable');
</script>