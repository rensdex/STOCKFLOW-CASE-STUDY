<?php
// File: stock_in.php
if (!isLoggedIn() || $_SESSION['role'] === 'Viewer') {
    redirect('index.php?page=dashboard');
}

// Handle Stock-In ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $transaction_id = 'SI-' . rand(1000, 9999);
        
        // Start transaction to ensure data consistency
        $pdo->beginTransaction();
        
        try {
            // Insert into stock_in table
            $stmt = $pdo->prepare("INSERT INTO stock_in (transaction_id, product_id, supplier_id, quantity_added, notes, staff_id, date) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
            $stmt->execute([$transaction_id, $_POST['product_id'], $_POST['supplier_id'], $_POST['quantity'], $_POST['notes'], $_SESSION['user_id']]);
            
            // Update inventory quantity
            $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
            $updateStmt->execute([$_POST['quantity'], $_POST['product_id']]);
            
            // Add notification
            $productStmt = $pdo->prepare("SELECT product_name FROM inventory WHERE id = ?");
            $productStmt->execute([$_POST['product_id']]);
            $product = $productStmt->fetch();
            
            $notifStmt = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, 'info')");
            $notifStmt->execute(['New Stock-In', $_POST['quantity'] . ' units of ' . $product['product_name'] . ' added']);
            
            $pdo->commit();
            $success = "Stock-In recorded successfully and inventory updated";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to record stock-in: " . $e->getMessage();
        }
    }
}

// Handle Stock-In EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $pdo->beginTransaction();
        try {
            // Get old quantity before update
            $oldStmt = $pdo->prepare("SELECT quantity_added, product_id FROM stock_in WHERE id = ?");
            $oldStmt->execute([$_POST['id']]);
            $oldData = $oldStmt->fetch();
            
            if (!$oldData) {
                throw new Exception("Stock-in record not found");
            }
            
            // Update stock_in record
            $stmt = $pdo->prepare("UPDATE stock_in SET supplier_id = ?, quantity_added = ?, notes = ? WHERE id = ?");
            $stmt->execute([$_POST['supplier_id'], $_POST['quantity'], $_POST['notes'], $_POST['id']]);
            
            // Adjust inventory quantity (remove old, add new)
            $quantityDiff = $_POST['quantity'] - $oldData['quantity_added'];
            $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
            $updateStmt->execute([$quantityDiff, $oldData['product_id']]);
            
            $pdo->commit();
            $success = "Stock-In updated successfully";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update stock-in: " . $e->getMessage();
        }
    }
}

// Handle Stock-In DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $pdo->beginTransaction();
        try {
            // Get stock-in details before deletion
            $stmt = $pdo->prepare("SELECT quantity_added, product_id FROM stock_in WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $record = $stmt->fetch();
            
            if ($record) {
                // Remove the quantity from inventory
                $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
                $updateStmt->execute([$record['quantity_added'], $record['product_id']]);
                
                // Delete the stock-in record
                $deleteStmt = $pdo->prepare("DELETE FROM stock_in WHERE id = ?");
                $deleteStmt->execute([$_POST['id']]);
                
                $pdo->commit();
                $success = "Stock-In record deleted and inventory adjusted";
            } else {
                throw new Exception("Record not found");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to delete stock-in: " . $e->getMessage();
        }
    }
}

// Fetch stock-in records with JOIN to get product and supplier names
$stmt = $pdo->query("SELECT si.*, i.product_name, s.name as supplier_name, u.name as staff_name 
                     FROM stock_in si 
                     JOIN inventory i ON si.product_id = i.id 
                     JOIN suppliers s ON si.supplier_id = s.id 
                     JOIN users u ON si.staff_id = u.id 
                     ORDER BY si.created_at DESC");
$stock_in_records = $stmt->fetchAll();

// Fetch products for dropdown
$stmt = $pdo->query("SELECT id, product_name FROM inventory ORDER BY product_name");
$products = $stmt->fetchAll();

// Fetch suppliers for dropdown
$stmt = $pdo->query("SELECT id, name FROM suppliers ORDER BY name");
$suppliers = $stmt->fetchAll();
?>

<!-- Include SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Stock-In Management</h4>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addStockInModal">
            <i class="bi bi-plus-lg"></i> New Stock-In
        </button>
    </div>
    
    <div class="data-table">
        <div class="p-3 d-flex justify-content-between gap-3">
            <input type="text" id="searchStockIn" class="form-control w-50" placeholder="Search transactions by ID, product, or supplier...">
            <button class="btn btn-outline-secondary" onclick="exportToCSV('stockInTable', 'stock_in_report')">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
        <div class="table-responsive" style="overflow-x: auto;">
            <table class="table table-bordered table-hover" id="stockInTable" style="min-width: 800px;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 12%;">TXN ID</th>
                        <th style="width: 25%;">PRODUCT</th>
                        <th style="width: 20%;">SUPPLIER</th>
                        <th style="width: 10%;">QTY ADDED</th>
                        <th style="width: 10%;">DATE</th>
                        <th style="width: 15%;">NOTES</th>
                        <th style="width: 8%;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stock_in_records)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No stock-in records found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stock_in_records as $record): ?>
                        <tr id="row-<?php echo $record['id']; ?>">
                            <td style="vertical-align: middle; white-space: nowrap;" class="fw-bold"><?php echo escape($record['transaction_id']); ?></td>
                            <td style="vertical-align: middle; word-wrap: break-word; white-space: normal; min-width: 150px;">
                                <?php echo escape($record['product_name']); ?>
                            </td>
                            <td style="vertical-align: middle; word-wrap: break-word; white-space: normal; min-width: 150px;">
                                <?php echo escape($record['supplier_name']); ?>
                            </td>
                            <td style="vertical-align: middle; text-align: center;">
                                <span class="badge bg-success">+<?php echo number_format($record['quantity_added']); ?></span>
                            </td>
                            <td style="vertical-align: middle; text-align: center; white-space: nowrap;">
                                <?php echo date('Y-m-d', strtotime($record['date'])); ?>
                            </td>
                            <td style="vertical-align: middle; word-wrap: break-word; white-space: normal; min-width: 120px;">
                                <?php echo escape($record['notes'] ?? '-'); ?>
                            </td>
                            <td style="vertical-align: middle; text-align: center; white-space: nowrap;">
                                <button class="btn btn-sm btn-outline-primary edit-btn" 
                                        data-id="<?php echo $record['id']; ?>"
                                        data-product-id="<?php echo $record['product_id']; ?>"
                                        data-product-name="<?php echo escape($record['product_name']); ?>"
                                        data-supplier-id="<?php echo $record['supplier_id']; ?>"
                                        data-quantity="<?php echo $record['quantity_added']; ?>"
                                        data-notes="<?php echo escape($record['notes'] ?? ''); ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editStockInModal"
                                        title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-btn" 
                                        data-id="<?php echo $record['id']; ?>"
                                        data-name="<?php echo escape($record['transaction_id']); ?>"
                                        data-product="<?php echo escape($record['product_name']); ?>"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Stock-In Modal -->
<div class="modal fade" id="addStockInModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="stockInForm">
                <div class="modal-header">
                    <h5 class="modal-title">Record Stock-In</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-control" id="product_id" required>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>"><?php echo escape($product['product_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-control" required>
                            <option value="">-- Select Supplier --</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>"><?php echo escape($supplier['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity Added <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" required min="1" id="quantity">
                        <small class="text-muted">Enter the number of units being added to inventory</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Optional: Purchase order #, invoice #, batch #, etc..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="submitBtn">
                        <i class="bi bi-check-lg"></i> Record Stock-In
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Stock-In Modal -->
<div class="modal fade" id="editStockInModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editStockInForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Stock-In Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <input type="text" class="form-control" id="edit_product_name" disabled>
                        <small class="text-muted">Product cannot be changed</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-control" id="edit_supplier_id" required>
                            <option value="">-- Select Supplier --</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>"><?php echo escape($supplier['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity Added <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" id="edit_quantity" required min="1">
                        <small class="text-muted">Update the quantity. Inventory will be adjusted automatically.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" id="edit_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="editSubmitBtn">
                        <i class="bi bi-check-lg"></i> Update Stock-In
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// SweetAlert2 Functions
function showDeleteConfirmModal(id, name, productName) {
    Swal.fire({
        title: 'Delete Stock-In Record?',
        html: `Are you sure you want to delete <strong>${name}</strong>?<br><br>
               <span class="text-danger">⚠️ Warning: This will remove the added quantity from inventory!</span><br>
               <small>Product: ${productName}</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6366f1',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Create and submit form
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

function showConfirmModal(title, message, confirmCallback) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#6366f1',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, proceed!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed && confirmCallback) {
            confirmCallback();
        }
    });
}

// Enhanced search functionality
document.getElementById('searchStockIn')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('stockInTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let row of rows) {
        if (row.classList.contains('no-data-row')) continue;
        
        const txnId = row.cells[0]?.innerText.toLowerCase() || '';
        const product = row.cells[1]?.innerText.toLowerCase() || '';
        const supplier = row.cells[2]?.innerText.toLowerCase() || '';
        const quantity = row.cells[3]?.innerText.toLowerCase() || '';
        const date = row.cells[4]?.innerText.toLowerCase() || '';
        const notes = row.cells[5]?.innerText.toLowerCase() || '';
        
        if (txnId.includes(searchTerm) || product.includes(searchTerm) || supplier.includes(searchTerm) || 
            quantity.includes(searchTerm) || date.includes(searchTerm) || notes.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
});

// Edit modal - populate fields
const editModal = document.getElementById('editStockInModal');
if (editModal) {
    editModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const productName = button.getAttribute('data-product-name') || button.closest('tr')?.cells[1]?.innerText || '';
        const supplierId = button.getAttribute('data-supplier-id');
        const quantity = button.getAttribute('data-quantity');
        const notes = button.getAttribute('data-notes');
        
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_product_name').value = productName;
        document.getElementById('edit_supplier_id').value = supplierId;
        document.getElementById('edit_quantity').value = quantity;
        document.getElementById('edit_notes').value = notes;
    });
}

// Delete button with SweetAlert confirmation
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        const productName = this.getAttribute('data-product') || this.closest('tr')?.cells[1]?.innerText || '';
        
        showDeleteConfirmModal(id, name, productName);
    });
});

// Add Stock-In form validation
document.getElementById('stockInForm')?.addEventListener('submit', function(e) {
    const product = document.getElementById('product_id').value;
    const quantity = document.getElementById('quantity').value;
    
    if (!product) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please select a product');
        return false;
    }
    
    if (!quantity || quantity < 1) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please enter a valid quantity (minimum 1)');
        return false;
    }
    
    showConfirmModal('Record Stock-In', 'This will increase the inventory quantity. Continue?', () => {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
        submitBtn.disabled = true;
        document.getElementById('stockInForm').submit();
    });
    
    e.preventDefault();
    return false;
});

// Edit Stock-In form validation
document.getElementById('editStockInForm')?.addEventListener('submit', function(e) {
    const supplierId = document.getElementById('edit_supplier_id').value;
    const quantity = document.getElementById('edit_quantity').value;
    
    if (!supplierId) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please select a supplier');
        return false;
    }
    
    if (!quantity || quantity < 1) {
        e.preventDefault();
        showErrorModal('Validation Error', 'Please enter a valid quantity (minimum 1)');
        return false;
    }
    
    showConfirmModal('Update Stock-In', 'Updating this stock-in will adjust the inventory quantity accordingly. Continue?', () => {
        const submitBtn = document.getElementById('editSubmitBtn');
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
        submitBtn.disabled = true;
        document.getElementById('editStockInForm').submit();
    });
    
    e.preventDefault();
    return false;
});

// Clear form when modals close
document.getElementById('addStockInModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('stockInForm')?.reset();
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Record Stock-In';
    submitBtn.disabled = false;
});

document.getElementById('editStockInModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('editStockInForm')?.reset();
    const submitBtn = document.getElementById('editSubmitBtn');
    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Update Stock-In';
    submitBtn.disabled = false;
});

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
                text = text.replace(/[+]/g, '');
                // Skip the actions column (index may vary)
                if (col.cellIndex === 6 && col.tagName === 'TD') {
                    continue;
                }
                rowData.push('"' + text.replace(/"/g, '""') + '"');
            }
            if (rowData.length > 0 && rowData.some(cell => cell !== '""')) {
                csv.push(rowData.join(','));
            }
        }
        
        const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename + '_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        showSuccessModal('Export Complete', 'Your report has been downloaded successfully!');
    } catch (error) {
        showErrorModal('Export Failed', 'An error occurred while exporting');
    }
}

// Show success/error messages from PHP
<?php if (isset($success)): ?>
document.addEventListener('DOMContentLoaded', function() {
    let title = 'Success!';
    let message = '<?php echo addslashes($success); ?>';
    if (message.includes('recorded')) {
        title = 'Stock-In Recorded!';
    } else if (message.includes('updated')) {
        title = 'Stock-In Updated!';
    } else if (message.includes('deleted')) {
        title = 'Stock-In Deleted!';
    }
    showSuccessModal(title, message);
});
<?php endif; ?>

<?php if (isset($error)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showErrorModal('Error', '<?php echo addslashes($error); ?>');
});
<?php endif; ?>
</script>

<style>
/* Data Table Container */
.data-table {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Table Styles */
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
.table td:nth-child(3),
.table td:nth-child(6) {
    white-space: normal;
    word-wrap: break-word;
    word-break: break-word;
}

/* Fixed column widths */
.table th:nth-child(1) { width: 12%; } /* TXN ID */
.table th:nth-child(2) { width: 25%; } /* PRODUCT */
.table th:nth-child(3) { width: 20%; } /* SUPPLIER */
.table th:nth-child(4) { width: 10%; } /* QTY ADDED */
.table th:nth-child(5) { width: 10%; } /* DATE */
.table th:nth-child(6) { width: 15%; } /* NOTES */
.table th:nth-child(7) { width: 8%; }  /* ACTIONS */

/* Center align specific columns */
.table td:nth-child(4),
.table td:nth-child(5),
.table th:nth-child(4),
.table th:nth-child(5) {
    text-align: center;
}

/* Badge styles */
.badge {
    font-size: 0.85rem;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    display: inline-block;
    min-width: 70px;
    text-align: center;
}

.badge.bg-success {
    background: #10b981 !important;
}

/* Button styles */
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    margin: 0 2px;
}

.action-icons {
    white-space: nowrap;
}

/* Row hover effect */
.table-hover tbody tr:hover {
    background-color: rgba(99, 102, 241, 0.02);
}

/* Empty state */
.text-center.py-5 {
    padding: 3rem !important;
}

/* Responsive */
@media (max-width: 768px) {
    .table td:nth-child(2),
    .table td:nth-child(3),
    .table td:nth-child(6) {
        min-width: 120px;
    }
    
    .btn-sm {
        padding: 0.2rem 0.4rem;
    }
}
</style>