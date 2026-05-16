<?php
// File: inventory.php
// IMPORTANT: No HTML output before this point
if (!isLoggedIn() || $_SESSION['role'] !== 'Administrator') {
    redirect('index.php?page=dashboard');
}

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                $asset_code = 'AST-' . rand(1000, 9999);
                $stmt = $pdo->prepare("INSERT INTO inventory (asset_code, product_name, category_id, supplier_id, quantity, unit_price, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                // Calculate initial status based on quantity
                $quantity = $_POST['quantity'];
                $status = $quantity > 10 ? 'In Stock' : ($quantity > 0 ? 'Low Stock' : 'Out of Stock');
                
                $stmt->execute([$asset_code, $_POST['product_name'], $_POST['category_id'], $_POST['supplier_id'], $quantity, $_POST['unit_price'], $_POST['description'], $status]);
                
                // Add notification
                $notifStmt = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, 'success')");
                $notifStmt->execute(['New Product Added', $_POST['product_name'] . ' has been added to inventory']);
                
                $success = "Product added successfully";
            } elseif ($_POST['action'] === 'edit') {
                // Calculate status based on new quantity
                $quantity = $_POST['quantity'];
                $status = $quantity > 10 ? 'In Stock' : ($quantity > 0 ? 'Low Stock' : 'Out of Stock');
                
                $stmt = $pdo->prepare("UPDATE inventory SET product_name = ?, category_id = ?, supplier_id = ?, quantity = ?, unit_price = ?, description = ?, status = ? WHERE id = ?");
                $stmt->execute([$_POST['product_name'], $_POST['category_id'], $_POST['supplier_id'], $quantity, $_POST['unit_price'], $_POST['description'], $status, $_POST['id']]);
                
                $success = "Product updated successfully";
            } elseif ($_POST['action'] === 'delete') {
                // Get product name for notification
                $getProduct = $pdo->prepare("SELECT product_name FROM inventory WHERE id = ?");
                $getProduct->execute([$_POST['id']]);
                $product = $getProduct->fetch();
                
                $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                
                // Add notification
                $notifStmt = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, 'warning')");
                $notifStmt->execute(['Product Deleted', $product['product_name'] . ' has been removed from inventory']);
                
                $success = "Product deleted successfully";
            }
        }
    }
}

// Fetch inventory items with pagination
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$countStmt = $pdo->query("SELECT COUNT(*) as total FROM inventory");
$totalItems = $countStmt->fetch()['total'];
$totalPages = ceil($totalItems / $limit);

// Cast to integers for safety
$limit = (int)$limit;
$offset = (int)$offset;

$stmt = $pdo->prepare("SELECT i.*, c.name as category_name, s.name as supplier_name 
                     FROM inventory i 
                     LEFT JOIN categories c ON i.category_id = c.id 
                     LEFT JOIN suppliers s ON i.supplier_id = s.id 
                     ORDER BY i.created_at DESC 
                     LIMIT " . $limit . " OFFSET " . $offset);
$stmt->execute();
$inventory = $stmt->fetchAll();

// Fetch categories for dropdown
$stmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1");
$categories = $stmt->fetchAll();

// Fetch suppliers for dropdown
$stmt = $pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1");
$suppliers = $stmt->fetchAll();

// Store success/error in session to display after redirect (if needed)
if (isset($success)) {
    $_SESSION['flash_success'] = $success;
}
if (isset($error)) {
    $_SESSION['flash_error'] = $error;
}
?>

<!-- Include SweetAlert2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Inventory Management</h4>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
            <i class="bi bi-plus-lg"></i> Add Product
        </button>
    </div>
    
    <div class="data-table">
        <div class="p-3 d-flex justify-content-between gap-3">
            <input type="text" id="searchInventory" class="form-control w-50" placeholder="Search by asset code, product name, category, or supplier...">
            <button class="btn btn-outline-secondary" onclick="exportToCSV('inventoryTable', 'inventory_report')">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
        <div class="table-responsive" style="overflow-x: auto;">
            <table class="table table-bordered table-hover" id="inventoryTable" style="min-width: 800px; white-space: nowrap;">
                <thead>
                    <tr>
                        <th style="width: 10%;">ASSET CODE</th>
                        <th style="width: 25%;">PRODUCT</th>
                        <th style="width: 15%;">CATEGORY</th>
                        <th style="width: 20%;">SUPPLIER</th>
                        <th style="width: 8%;">QTY</th>
                        <th style="width: 12%;">UNIT PRICE</th>
                        <th style="width: 10%;">STATUS</th>
                        <th style="width: 10%;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No products found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inventory as $item): ?>
                        <tr class="inventory-row">
                            <td style="vertical-align: middle;"><?php echo escape($item['asset_code']); ?></td>
                            <td style="vertical-align: middle; word-wrap: break-word; white-space: normal; min-width: 150px;">
                                <?php echo escape($item['product_name']); ?>
                            </td>
                            <td style="vertical-align: middle;"><?php echo escape($item['category_name'] ?? 'N/A'); ?></td>
                            <td style="vertical-align: middle; word-wrap: break-word; white-space: normal; min-width: 150px;">
                                <?php echo escape($item['supplier_name'] ?? 'N/A'); ?>
                            </td>
                            <td style="vertical-align: middle; text-align: center;"><?php echo number_format($item['quantity']); ?></td>
                            <td style="vertical-align: middle;">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td style="vertical-align: middle; text-align: center;">
                                <?php
                                $status = $item['status'];
                                $badgeClass = $status === 'In Stock' ? 'badge-success' : ($status === 'Low Stock' ? 'badge-warning' : 'badge-danger');
                                ?>
                                <span class="<?php echo $badgeClass; ?>"><?php echo $status; ?></span>
                            </td>
                            <td style="vertical-align: middle; text-align: center; white-space: nowrap;">
                                <button class="btn btn-sm btn-outline-primary edit-btn" 
                                        data-id="<?php echo $item['id']; ?>" 
                                        data-asset-code="<?php echo escape($item['asset_code']); ?>"
                                        data-product="<?php echo escape($item['product_name']); ?>" 
                                        data-category="<?php echo $item['category_id']; ?>" 
                                        data-supplier="<?php echo $item['supplier_id']; ?>" 
                                        data-quantity="<?php echo $item['quantity']; ?>" 
                                        data-price="<?php echo $item['unit_price']; ?>" 
                                        data-desc="<?php echo escape($item['description']); ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-btn" 
                                        data-id="<?php echo $item['id']; ?>" 
                                        data-name="<?php echo escape($item['product_name']); ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="p-3 d-flex justify-content-between align-items-center">
            <div>
                <small class="text-muted">Showing <?php echo count($inventory); ?> of <?php echo $totalItems; ?> items</small>
            </div>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=inventory&page_num=<?php echo $page - 1; ?>">Previous</a>
                    </li>
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=inventory&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($endPage < $totalPages): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <li class="page-item">
                            <a class="page-link" href="?page=inventory&page_num=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                        </li>
                    <?php endif; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=inventory&page_num=<?php echo $page + 1; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addInventoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="addInventoryForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="product_name" id="add_product_name" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category_id" id="add_category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo escape($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" id="add_supplier_id" class="form-control" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?php echo $sup['id']; ?>"><?php echo escape($sup['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="add_quantity" class="form-control" required min="0">
                            <small class="text-muted">Status will be auto-calculated</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit Price <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="unit_price" id="add_unit_price" class="form-control" required min="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="add_description" class="form-control" rows="3" placeholder="Optional product description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="addSubmitBtn">
                        <i class="bi bi-check-lg"></i> Add Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editInventoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editInventoryForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Asset Code</label>
                        <input type="text" class="form-control" id="edit_asset_code" disabled>
                        <small class="text-muted">Asset code cannot be changed</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category_id" id="edit_category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo escape($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" id="edit_supplier_id" class="form-control" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?php echo $sup['id']; ?>"><?php echo escape($sup['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="edit_quantity" class="form-control" required min="0">
                            <small class="text-muted">Status will be auto-updated</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit Price <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="unit_price" id="edit_unit_price" class="form-control" required min="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="editSubmitBtn">
                        <i class="bi bi-check-lg"></i> Update Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// SweetAlert2 Functions
function showDeleteConfirmModal(productId, productName, deleteCallback) {
    Swal.fire({
        title: 'Delete Product?',
        text: `Are you sure you want to delete "${productName}"? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6366f1',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed && deleteCallback) {
            deleteCallback(productId);
        }
    });
}

function showSuccessModal(title, message) {
    Swal.fire({
        icon: 'success',
        title: title,
        text: message,
        confirmButtonColor: '#6366f1',
        timer: 2000,
        timerProgressBar: true
    });
}

function showErrorModal(title, message) {
    Swal.fire({
        icon: 'error',
        title: title,
        text: message,
        confirmButtonColor: '#6366f1'
    });
}

function showInfoModal(title, message) {
    Swal.fire({
        icon: 'info',
        title: title,
        text: message,
        confirmButtonColor: '#6366f1'
    });
}

// Enhanced Search functionality
function searchInventoryTable() {
    const input = document.getElementById('searchInventory');
    if (!input) return;
    
    input.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const table = document.getElementById('inventoryTable');
        const rows = table.getElementsByTagName('tr');
        
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            if (!row.querySelector('td')) continue;
            
            const assetCode = row.querySelector('td:first-child')?.innerText.toLowerCase() || '';
            const productName = row.querySelector('td:nth-child(2)')?.innerText.toLowerCase() || '';
            const category = row.querySelector('td:nth-child(3)')?.innerText.toLowerCase() || '';
            const supplier = row.querySelector('td:nth-child(4)')?.innerText.toLowerCase() || '';
            
            const matches = assetCode.includes(filter) || 
                           productName.includes(filter) || 
                           category.includes(filter) || 
                           supplier.includes(filter);
            
            row.style.display = matches ? '' : 'none';
        }
    });
}

// Edit functionality
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_asset_code').value = this.dataset.assetCode || '';
        document.getElementById('edit_product_name').value = this.dataset.product;
        document.getElementById('edit_category_id').value = this.dataset.category;
        document.getElementById('edit_supplier_id').value = this.dataset.supplier;
        document.getElementById('edit_quantity').value = this.dataset.quantity;
        document.getElementById('edit_unit_price').value = this.dataset.price;
        document.getElementById('edit_description').value = this.dataset.desc || '';
        
        const editModal = new bootstrap.Modal(document.getElementById('editInventoryModal'));
        editModal.show();
    });
});

// Delete functionality with SweetAlert2 confirmation
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const productId = this.dataset.id;
        const productName = this.dataset.name;
        
        showDeleteConfirmModal(productId, productName, function(id) {
            // Create and submit delete form
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        });
    });
});

// Add form validation with SweetAlert2
document.getElementById('addInventoryForm')?.addEventListener('submit', function(e) {
    const productName = document.getElementById('add_product_name').value;
    const categoryId = document.getElementById('add_category_id').value;
    const supplierId = document.getElementById('add_supplier_id').value;
    const quantity = document.getElementById('add_quantity').value;
    const unitPrice = document.getElementById('add_unit_price').value;
    
    if (!productName) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please enter product name');
        return false;
    }
    if (!categoryId) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please select a category');
        return false;
    }
    if (!supplierId) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please select a supplier');
        return false;
    }
    if (!quantity || quantity < 0) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please enter a valid quantity');
        return false;
    }
    if (!unitPrice || unitPrice < 0) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please enter a valid unit price');
        return false;
    }
    
    const submitBtn = document.getElementById('addSubmitBtn');
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adding...';
    submitBtn.disabled = true;
});

// Edit form validation with SweetAlert2
document.getElementById('editInventoryForm')?.addEventListener('submit', function(e) {
    const productName = document.getElementById('edit_product_name').value;
    const categoryId = document.getElementById('edit_category_id').value;
    const supplierId = document.getElementById('edit_supplier_id').value;
    const quantity = document.getElementById('edit_quantity').value;
    const unitPrice = document.getElementById('edit_unit_price').value;
    
    if (!productName) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please enter product name');
        return false;
    }
    if (!categoryId) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please select a category');
        return false;
    }
    if (!supplierId) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please select a supplier');
        return false;
    }
    if (!quantity || quantity < 0) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please enter a valid quantity');
        return false;
    }
    if (!unitPrice || unitPrice < 0) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please enter a valid unit price');
        return false;
    }
    
    const submitBtn = document.getElementById('editSubmitBtn');
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
    submitBtn.disabled = true;
});

// Reset add form when modal closes
document.getElementById('addInventoryModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('addInventoryForm')?.reset();
    const submitBtn = document.getElementById('addSubmitBtn');
    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Add Product';
    submitBtn.disabled = false;
});

// Reset edit modal when closed
document.getElementById('editInventoryModal')?.addEventListener('hidden.bs.modal', function() {
    const submitBtn = document.getElementById('editSubmitBtn');
    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Update Product';
    submitBtn.disabled = false;
});

// Show success/error messages from PHP
<?php if (isset($success)): ?>
setTimeout(function() {
    let title = 'Success!';
    let message = '<?php echo addslashes($success); ?>';
    if (message.includes('added')) {
        title = 'Product Added!';
    } else if (message.includes('updated')) {
        title = 'Product Updated!';
    } else if (message.includes('deleted')) {
        title = 'Product Deleted!';
    }
    showSuccessModal(title, message);
}, 500);
<?php endif; ?>

<?php if (isset($error)): ?>
setTimeout(function() {
    showErrorModal('Error', '<?php echo addslashes($error); ?>');
}, 500);
<?php endif; ?>

// Export to CSV function
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) {
        showErrorModal('Export Failed', 'Table not found');
        return;
    }
    
    try {
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let row of rows) {
            const rowData = [];
            const cols = row.querySelectorAll('td, th');
            for (let col of cols) {
                let text = col.innerText.trim();
                text = text.replace(/[+₱]/g, '');
                rowData.push('"' + text.replace(/"/g, '""') + '"');
            }
            if (rowData.length > 0 && rowData.some(cell => cell !== '""')) {
                csv.push(rowData.join(','));
            }
        }
        
        const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename + '_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showSuccessModal('Export Complete', 'Your report has been downloaded successfully!');
    } catch (error) {
        showErrorModal('Export Failed', 'An error occurred while exporting');
    }
}

// Initialize search
searchInventoryTable();

// Real-time quantity status preview
document.getElementById('add_quantity')?.addEventListener('input', function() {
    const qty = parseInt(this.value) || 0;
    let status = '';
    if (qty > 10) status = 'In Stock';
    else if (qty > 0) status = 'Low Stock';
    else status = 'Out of Stock';
    
    let statusPreview = document.getElementById('statusPreview');
    if (!statusPreview && this.parentNode) {
        const preview = document.createElement('small');
        preview.id = 'statusPreview';
        preview.className = 'text-muted d-block mt-1';
        this.parentNode.appendChild(preview);
        statusPreview = preview;
    }
    if (statusPreview) {
        statusPreview.innerHTML = `Status will be: <strong>${status}</strong>`;
    }
});
</script>

<style>
/* Table Styles */
.data-table {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.table {
    margin-bottom: 0;
}

.table th {
    background: #f8fafc;
    padding: 1rem;
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    padding: 0.75rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

/* Allow text wrapping for long names */
.table td:nth-child(2),
.table td:nth-child(4) {
    white-space: normal;
    word-wrap: break-word;
    word-break: break-word;
    max-width: 250px;
}

/* Fixed width columns */
.table th:nth-child(1) { width: 10%; }
.table th:nth-child(2) { width: 25%; }
.table th:nth-child(3) { width: 12%; }
.table th:nth-child(4) { width: 18%; }
.table th:nth-child(5) { width: 8%; text-align: center; }
.table th:nth-child(6) { width: 12%; }
.table th:nth-child(7) { width: 10%; text-align: center; }
.table th:nth-child(8) { width: 10%; text-align: center; }

/* Align specific columns */
.table td:nth-child(5),
.table td:nth-child(7) {
    text-align: center;
}

/* Badge styles */
.badge-success {
    background: #10b981;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    color: white;
    display: inline-block;
    min-width: 80px;
    text-align: center;
}

.badge-warning {
    background: #f59e0b;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    color: white;
    display: inline-block;
    min-width: 80px;
    text-align: center;
}

.badge-danger {
    background: #ef4444;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    color: white;
    display: inline-block;
    min-width: 80px;
    text-align: center;
}

/* Buttons */
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    margin: 0 2px;
}

/* Pagination */
.page-item.active .page-link {
    background: linear-gradient(135deg, #6366f1, #818cf8);
    border-color: #6366f1;
    color: white;
}

.page-link {
    color: #6366f1;
}

.page-link:hover {
    background-color: #f3f4f6;
    border-color: #e2e8f0;
    color: #4f46e5;
}

/* Row hover effect */
.inventory-row:hover {
    background-color: rgba(99, 102, 241, 0.02);
}

/* Responsive */
@media (max-width: 768px) {
    .table td:nth-child(2),
    .table td:nth-child(4) {
        max-width: 150px;
    }
    
    .btn-sm {
        padding: 0.2rem 0.4rem;
    }
}

/* Card styles */
.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.25rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 4px 16px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.inventory-row {
    transition: background-color 0.2s ease;
}
</style>