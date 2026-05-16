<?php
// File: stock_out.php
if (!isLoggedIn() || $_SESSION['role'] === 'Viewer') {
    redirect('index.php?page=dashboard');
}

// Handle Stock-Out ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        // Check if enough stock is available
        $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ?");
        $stmt->execute([$_POST['product_id']]);
        $current_qty = $stmt->fetchColumn();
        
        if ($current_qty >= $_POST['quantity']) {
            $transaction_id = 'SO-' . rand(1000, 9999);
            $stmt = $pdo->prepare("INSERT INTO stock_out (transaction_id, product_id, quantity_released, released_to, purpose, staff_id, date) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
            $stmt->execute([$transaction_id, $_POST['product_id'], $_POST['quantity'], $_POST['released_to'], $_POST['purpose'], $_SESSION['user_id']]);
            
            // Update inventory quantity
            $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
            $updateStmt->execute([$_POST['quantity'], $_POST['product_id']]);
            
            $success = "Stock-Out recorded successfully and inventory updated";
        } else {
            $error = "Insufficient stock! Available: " . $current_qty;
        }
    }
}

// Handle Stock-Out EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $pdo->beginTransaction();
        try {
            // Get old quantity and product before update
            $oldStmt = $pdo->prepare("SELECT quantity_released, product_id FROM stock_out WHERE id = ?");
            $oldStmt->execute([$_POST['id']]);
            $oldData = $oldStmt->fetch();
            
            if (!$oldData) {
                throw new Exception("Stock-out record not found");
            }
            
            // Calculate quantity difference (new - old)
            $quantityDiff = $_POST['quantity'] - $oldData['quantity_released'];
            
            // Check if enough stock for increase
            if ($quantityDiff > 0) {
                $stockStmt = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ?");
                $stockStmt->execute([$oldData['product_id']]);
                $currentStock = $stockStmt->fetchColumn();
                if ($currentStock < $quantityDiff) {
                    throw new Exception("Insufficient stock! Available: " . $currentStock);
                }
            }
            
            // Update stock_out record
            $stmt = $pdo->prepare("UPDATE stock_out SET quantity_released = ?, released_to = ?, purpose = ? WHERE id = ?");
            $stmt->execute([$_POST['quantity'], $_POST['released_to'], $_POST['purpose'], $_POST['id']]);
            
            // Adjust inventory quantity
            $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
            $updateStmt->execute([$quantityDiff, $oldData['product_id']]);
            
            $pdo->commit();
            $success = "Stock-Out updated successfully";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update stock-out: " . $e->getMessage();
        }
    }
}

// Handle Stock-Out DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $pdo->beginTransaction();
        try {
            // Get stock-out details before deletion
            $stmt = $pdo->prepare("SELECT quantity_released, product_id FROM stock_out WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $record = $stmt->fetch();
            
            if ($record) {
                // Add the quantity back to inventory
                $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
                $updateStmt->execute([$record['quantity_released'], $record['product_id']]);
                
                // Delete the stock-out record
                $deleteStmt = $pdo->prepare("DELETE FROM stock_out WHERE id = ?");
                $deleteStmt->execute([$_POST['id']]);
                
                $pdo->commit();
                $success = "Stock-Out record deleted and inventory adjusted";
            } else {
                throw new Exception("Record not found");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to delete stock-out: " . $e->getMessage();
        }
    }
}

// Fetch stock-out records with JOIN to get product names
$stmt = $pdo->query("SELECT so.*, i.product_name 
                     FROM stock_out so 
                     JOIN inventory i ON so.product_id = i.id 
                     ORDER BY so.created_at DESC");
$stock_out_records = $stmt->fetchAll();

// Fetch products for dropdown (only products with quantity > 0)
$stmt = $pdo->query("SELECT id, product_name, quantity FROM inventory WHERE quantity > 0 ORDER BY product_name");
$products = $stmt->fetchAll();
?>

<!-- Include SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Stock-Out Management</h4>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addStockOutModal">
            <i class="bi bi-plus-lg"></i> Release Stock
        </button>
    </div>
    
    <div class="data-table">
        <div class="p-3 d-flex justify-content-between gap-3">
            <input type="text" id="searchStockOut" class="form-control w-50" placeholder="Search transactions by ID, product, released to, or purpose...">
            <button class="btn btn-outline-secondary" onclick="exportToCSV('stockOutTable', 'stock_out_report')">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
        <div class="table-responsive" style="overflow-x: auto;">
            <table class="table table-bordered table-hover" id="stockOutTable" style="min-width: 800px;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 12%;">TXN ID</th>
                        <th style="width: 25%;">PRODUCT</th>
                        <th style="width: 10%;">QTY RELEASED</th>
                        <th style="width: 18%;">RELEASED TO</th>
                        <th style="width: 20%;">PURPOSE</th>
                        <th style="width: 10%;">DATE</th>
                        <th style="width: 5%;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stock_out_records)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No stock-out records found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stock_out_records as $record): ?>
                        <tr id="row-<?php echo $record['id']; ?>">
                            <td style="vertical-align: middle; white-space: nowrap;" class="fw-bold"><?php echo escape($record['transaction_id']); ?></td>
                            <td style="vertical-align: middle; word-wrap: break-word; white-space: normal; min-width: 150px;">
                                <?php echo escape($record['product_name']); ?>
                            </td>
                            <td style="vertical-align: middle; text-align: center;">
                                <span class="badge bg-danger">-<?php echo number_format($record['quantity_released']); ?></span>
                            </td>
                            <td style="vertical-align: middle; word-wrap: break-word; white-space: normal; min-width: 120px;">
                                <?php echo escape($record['released_to']); ?>
                            </td>
                            <td style="vertical-align: middle; word-wrap: break-word; white-space: normal; min-width: 150px;">
                                <?php echo escape($record['purpose']); ?>
                            </td>
                            <td style="vertical-align: middle; text-align: center; white-space: nowrap;">
                                <?php echo date('Y-m-d', strtotime($record['date'])); ?>
                            </td>
                            <td style="vertical-align: middle; text-align: center; white-space: nowrap;">
                                <button class="btn btn-sm btn-outline-primary edit-btn" 
                                        data-id="<?php echo $record['id']; ?>"
                                        data-product-id="<?php echo $record['product_id']; ?>"
                                        data-product-name="<?php echo escape($record['product_name']); ?>"
                                        data-quantity="<?php echo $record['quantity_released']; ?>"
                                        data-released-to="<?php echo escape($record['released_to']); ?>"
                                        data-purpose="<?php echo escape($record['purpose']); ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editStockOutModal"
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

<!-- Add Stock-Out Modal -->
<div class="modal fade" id="addStockOutModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="stockOutForm">
                <div class="modal-header">
                    <h5 class="modal-title">Release Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="productSelect" class="form-control" required>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" data-qty="<?php echo $product['quantity']; ?>">
                                    <?php echo escape($product['product_name']); ?> (Available: <?php echo $product['quantity']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity to Release <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="releaseQuantity" class="form-control" required min="1">
                        <small class="text-muted" id="stockWarning"></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Released To <span class="text-danger">*</span></label>
                        <input type="text" name="released_to" class="form-control" required placeholder="e.g., John Doe, IT Department">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Purpose <span class="text-danger">*</span></label>
                        <select name="purpose" class="form-control" required>
                            <option value="">-- Select Purpose --</option>
                            <option value="New hires">New hires</option>
                            <option value="Replacement">Replacement</option>
                            <option value="Field work">Field work</option>
                            <option value="Training">Training</option>
                            <option value="Department transfer">Department transfer</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="submitBtn">
                        <i class="bi bi-check-lg"></i> Release Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Stock-Out Modal -->
<div class="modal fade" id="editStockOutModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editStockOutForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Stock-Out Record</h5>
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
                        <label class="form-label">Quantity Released <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="edit_quantity" class="form-control" required min="1">
                        <small class="text-muted" id="editStockWarning"></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Released To <span class="text-danger">*</span></label>
                        <input type="text" name="released_to" id="edit_released_to" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Purpose <span class="text-danger">*</span></label>
                        <select name="purpose" id="edit_purpose" class="form-control" required>
                            <option value="">-- Select Purpose --</option>
                            <option value="New hires">New hires</option>
                            <option value="Replacement">Replacement</option>
                            <option value="Field work">Field work</option>
                            <option value="Training">Training</option>
                            <option value="Department transfer">Department transfer</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="editSubmitBtn">
                        <i class="bi bi-check-lg"></i> Update Stock-Out
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
        title: 'Delete Stock-Out Record?',
        html: `Are you sure you want to delete <strong>${name}</strong>?<br><br>
               <span class="text-danger">⚠️ Warning: This will add the released quantity back to inventory!</span><br>
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
document.getElementById('searchStockOut')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('stockOutTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let row of rows) {
        if (row.classList.contains('no-data-row')) continue;
        
        const txnId = row.cells[0]?.innerText.toLowerCase() || '';
        const product = row.cells[1]?.innerText.toLowerCase() || '';
        const quantity = row.cells[2]?.innerText.toLowerCase() || '';
        const releasedTo = row.cells[3]?.innerText.toLowerCase() || '';
        const purpose = row.cells[4]?.innerText.toLowerCase() || '';
        const date = row.cells[5]?.innerText.toLowerCase() || '';
        
        if (txnId.includes(searchTerm) || product.includes(searchTerm) || quantity.includes(searchTerm) || 
            releasedTo.includes(searchTerm) || purpose.includes(searchTerm) || date.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
});

// Add Stock-Out form validation with stock check
const productSelect = document.getElementById('productSelect');
const releaseQuantity = document.getElementById('releaseQuantity');
const stockWarning = document.getElementById('stockWarning');

function validateStock() {
    const selectedOption = productSelect?.options[productSelect.selectedIndex];
    const availableQty = selectedOption?.dataset?.qty || 0;
    const qtyToRelease = parseInt(releaseQuantity?.value) || 0;
    
    if (qtyToRelease > availableQty) {
        stockWarning.innerHTML = `<span class="text-danger">❌ Insufficient stock! Only ${availableQty} available.</span>`;
        return false;
    } else if (qtyToRelease <= 0) {
        stockWarning.innerHTML = `<span class="text-warning">⚠️ Please enter a valid quantity.</span>`;
        return false;
    } else {
        stockWarning.innerHTML = `<span class="text-success">✅ Stock available: ${availableQty}</span>`;
        return true;
    }
}

if (productSelect) productSelect.addEventListener('change', validateStock);
if (releaseQuantity) releaseQuantity.addEventListener('input', validateStock);

// Edit modal - populate fields
const editModal = document.getElementById('editStockOutModal');
if (editModal) {
    editModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const productName = button.getAttribute('data-product-name');
        const quantity = button.getAttribute('data-quantity');
        const releasedTo = button.getAttribute('data-released-to');
        const purpose = button.getAttribute('data-purpose');
        
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_product_name').value = productName;
        document.getElementById('edit_quantity').value = quantity;
        document.getElementById('edit_released_to').value = releasedTo;
        document.getElementById('edit_purpose').value = purpose;
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

// Add Stock-Out form validation with SweetAlert
document.getElementById('stockOutForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const product = document.getElementById('productSelect').value;
    const quantity = document.getElementById('releaseQuantity').value;
    
    if (!product) {
        showErrorModal('Validation Error', 'Please select a product');
        return false;
    }
    
    if (!quantity || quantity < 1) {
        showErrorModal('Validation Error', 'Please enter a valid quantity (minimum 1)');
        return false;
    }
    
    // Check stock before submit
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    const availableQty = selectedOption?.dataset?.qty || 0;
    
    if (parseInt(quantity) > parseInt(availableQty)) {
        showErrorModal('Insufficient Stock', `Only ${availableQty} units available!`);
        return false;
    }
    
    showConfirmModal('Release Stock', 'This will decrease the inventory quantity. Continue?', function() {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
        submitBtn.disabled = true;
        document.getElementById('stockOutForm').submit();
    });
    
    return false;
});

// Edit Stock-Out form validation with SweetAlert
document.getElementById('editStockOutForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const quantity = document.getElementById('edit_quantity').value;
    const releasedTo = document.getElementById('edit_released_to').value;
    const purpose = document.getElementById('edit_purpose').value;
    
    if (!quantity || quantity < 1) {
        showErrorModal('Validation Error', 'Please enter a valid quantity (minimum 1)');
        return false;
    }
    
    if (!releasedTo) {
        showErrorModal('Validation Error', 'Please enter who/where the stock is released to');
        return false;
    }
    
    if (!purpose) {
        showErrorModal('Validation Error', 'Please select a purpose');
        return false;
    }
    
    showConfirmModal('Update Stock-Out', 'Updating this stock-out will adjust the inventory quantity accordingly. Continue?', function() {
        const submitBtn = document.getElementById('editSubmitBtn');
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
        submitBtn.disabled = true;
        document.getElementById('editStockOutForm').submit();
    });
    
    return false;
});

// Clear form when modals close
document.getElementById('addStockOutModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('stockOutForm')?.reset();
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Release Stock';
    submitBtn.disabled = false;
    if (stockWarning) stockWarning.innerHTML = '';
});

document.getElementById('editStockOutModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('editStockOutForm')?.reset();
    const submitBtn = document.getElementById('editSubmitBtn');
    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Update Stock-Out';
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
                text = text.replace(/[-]/g, '');
                // Skip the actions column
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
        title = 'Stock-Out Recorded!';
    } else if (message.includes('updated')) {
        title = 'Stock-Out Updated!';
    } else if (message.includes('deleted')) {
        title = 'Stock-Out Deleted!';
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
.table td:nth-child(4),
.table td:nth-child(5) {
    white-space: normal;
    word-wrap: break-word;
    word-break: break-word;
}

/* Fixed column widths */
.table th:nth-child(1) { width: 12%; }  /* TXN ID */
.table th:nth-child(2) { width: 25%; }  /* PRODUCT */
.table th:nth-child(3) { width: 10%; }  /* QTY RELEASED */
.table th:nth-child(4) { width: 18%; }  /* RELEASED TO */
.table th:nth-child(5) { width: 20%; }  /* PURPOSE */
.table th:nth-child(6) { width: 10%; }  /* DATE */
.table th:nth-child(7) { width: 5%; }   /* ACTIONS */

/* Center align specific columns */
.table td:nth-child(3),
.table td:nth-child(6),
.table th:nth-child(3),
.table th:nth-child(6) {
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

.badge.bg-danger {
    background: #ef4444 !important;
}

/* Button styles */
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    margin: 0 2px;
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
    .table td:nth-child(4),
    .table td:nth-child(5) {
        min-width: 120px;
    }
    
    .btn-sm {
        padding: 0.2rem 0.4rem;
    }
}
</style>