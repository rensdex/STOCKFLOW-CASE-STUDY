<?php
ob_start(); // MUST be first thing

// File: frontend/stock_out.php

// Handle Stock-Out ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid security token';
        header("Location: index.php?page=stock_out");
        exit();
    }
    
    $transaction_no = 'SO-' . date('Ymd') . '-' . rand(1000, 9999);
    
    $pdo->beginTransaction();
    
    // Check if enough stock
    $checkStmt = $pdo->prepare("SELECT quantity, supply_name FROM school_supplies WHERE id = ?");
    $checkStmt->execute([$_POST['supply_id']]);
    $supply = $checkStmt->fetch();
    
    if ($supply['quantity'] < $_POST['quantity']) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "Insufficient stock! Available: " . $supply['quantity'];
        header("Location: index.php?page=stock_out");
        exit();
    }
    
    $stmt = $pdo->prepare("INSERT INTO stock_out (transaction_no, supply_id, quantity, issued_to, remarks, issued_by, date_issued) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
    $stmt->execute([$transaction_no, $_POST['supply_id'], $_POST['quantity'], $_POST['issued_to'], $_POST['remarks'] ?? '', $_SESSION['user_id']]);
    
    $updateStmt = $pdo->prepare("UPDATE school_supplies SET quantity = quantity - ? WHERE id = ?");
    $updateStmt->execute([$_POST['quantity'], $_POST['supply_id']]);
    
    $statusStmt = $pdo->prepare("UPDATE school_supplies SET status = CASE 
        WHEN quantity <= 0 THEN 'Out of Stock'
        WHEN quantity <= low_stock_threshold THEN 'Low Stock'
        ELSE 'In Stock'
    END WHERE id = ?");
    $statusStmt->execute([$_POST['supply_id']]);
    
    $notifStmt = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, 'info')");
    $notifStmt->execute(['📤 Stock Released', $_POST['quantity'] . ' units of ' . $supply['supply_name'] . ' issued to ' . $_POST['issued_to']]);
    
    triggerStockUpdate('stock_out', $supply['supply_name'], $_POST['quantity']);
    triggerRealtimeNotification('Stock Released', $_POST['quantity'] . ' units of ' . $supply['supply_name'] . ' issued', 'warning');
    
    $pdo->commit();
    
    $_SESSION['flash_success'] = "Stock-Out recorded successfully";
    header("Location: index.php?page=stock_out");
    exit();
}

// Handle Stock-Out EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid security token';
        header("Location: index.php?page=stock_out");
        exit();
    }
    
    $pdo->beginTransaction();
    
    $oldStmt = $pdo->prepare("SELECT quantity, supply_id FROM stock_out WHERE id = ?");
    $oldStmt->execute([$_POST['id']]);
    $oldData = $oldStmt->fetch();
    
    if (!$oldData) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "Stock-out record not found";
        header("Location: index.php?page=stock_out");
        exit();
    }
    
    // Check if enough stock for the increase
    if ($_POST['quantity'] > $oldData['quantity']) {
        $extraNeeded = $_POST['quantity'] - $oldData['quantity'];
        $checkStmt = $pdo->prepare("SELECT quantity FROM school_supplies WHERE id = ?");
        $checkStmt->execute([$oldData['supply_id']]);
        $currentStock = $checkStmt->fetch();
        
        if ($currentStock['quantity'] < $extraNeeded) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Insufficient stock for this update!";
            header("Location: index.php?page=stock_out");
            exit();
        }
    }
    
    $stmt = $pdo->prepare("UPDATE stock_out SET quantity = ?, issued_to = ?, remarks = ? WHERE id = ?");
    $stmt->execute([$_POST['quantity'], $_POST['issued_to'], $_POST['remarks'] ?? '', $_POST['id']]);
    
    $quantityDiff = $oldData['quantity'] - $_POST['quantity'];
    $updateStmt = $pdo->prepare("UPDATE school_supplies SET quantity = quantity + ? WHERE id = ?");
    $updateStmt->execute([$quantityDiff, $oldData['supply_id']]);
    
    $statusStmt = $pdo->prepare("UPDATE school_supplies SET status = CASE 
        WHEN quantity <= 0 THEN 'Out of Stock'
        WHEN quantity <= low_stock_threshold THEN 'Low Stock'
        ELSE 'In Stock'
    END WHERE id = ?");
    $statusStmt->execute([$oldData['supply_id']]);
    
    $pdo->commit();
    
    $_SESSION['flash_success'] = "Stock-Out updated successfully";
    header("Location: index.php?page=stock_out");
    exit();
}

// Handle Stock-Out DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid security token';
        header("Location: index.php?page=stock_out");
        exit();
    }
    
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT quantity, supply_id FROM stock_out WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $record = $stmt->fetch();
    
    if ($record) {
        $updateStmt = $pdo->prepare("UPDATE school_supplies SET quantity = quantity + ? WHERE id = ?");
        $updateStmt->execute([$record['quantity'], $record['supply_id']]);
        
        $statusStmt = $pdo->prepare("UPDATE school_supplies SET status = CASE 
            WHEN quantity <= 0 THEN 'Out of Stock'
            WHEN quantity <= low_stock_threshold THEN 'Low Stock'
            ELSE 'In Stock'
        END WHERE id = ?");
        $statusStmt->execute([$record['supply_id']]);
        
        $deleteStmt = $pdo->prepare("DELETE FROM stock_out WHERE id = ?");
        $deleteStmt->execute([$_POST['id']]);
        
        $pdo->commit();
        
        $_SESSION['flash_success'] = "Stock-Out record deleted and inventory adjusted";
        header("Location: index.php?page=stock_out");
        exit();
    } else {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "Record not found";
        header("Location: index.php?page=stock_out");
        exit();
    }
}

// Get flash messages from session
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success']);
unset($_SESSION['flash_error']);

// Fetch stock-out records with JOIN to get product names
$stmt = $pdo->query("SELECT so.*, s.supply_name 
                     FROM stock_out so 
                     JOIN school_supplies s ON so.supply_id = s.id 
                     ORDER BY so.created_at DESC");
$stock_out_records = $stmt->fetchAll();

// Fetch supplies for dropdown (only supplies with quantity > 0)
$stmt = $pdo->query("SELECT id, supply_name, quantity FROM school_supplies WHERE quantity > 0 ORDER BY supply_name");
$supplies = $stmt->fetchAll();
?>

<!-- Include SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>📤 Stock-Out Management</h4>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addStockOutModal">
            <i class="bi bi-plus-lg"></i> Release Stock
        </button>
    </div>
    
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="data-table">
        <div class="p-3 d-flex justify-content-between gap-3">
            <input type="text" id="searchStockOut" class="form-control w-50" placeholder="Search by ID, supply, or recipient...">
            <button class="btn btn-outline-secondary" onclick="exportToCSV('stockOutTable', 'stock_out_report')">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="stockOutTable">
                <thead class="table-light">
                    <tr>
                        <th>TXN ID</th>
                        <th>SUPPLY</th>
                        <th>QTY</th>
                        <th>ISSUED TO</th>
                        <th>DATE</th>
                        <th>REMARKS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stock_out_records)): ?>
                    <tr class="text-center">
                        <td colspan="7" class="py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-2">No stock-out records found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($stock_out_records as $record): ?>
                        <tr id="row-<?php echo $record['id']; ?>">
                            <td class="fw-bold"><?php echo escape($record['transaction_no']); ?></td>
                            <td><?php echo escape($record['supply_name']); ?></td>
                            <td class="text-center"><span class="badge bg-danger">-<?php echo number_format($record['quantity']); ?></span></td>
                            <td><?php echo escape($record['issued_to']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($record['date_issued'])); ?></td>
                            <td><?php echo escape($record['remarks'] ?? '-'); ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary edit-btn" 
                                        data-id="<?php echo $record['id']; ?>"
                                        data-supply-id="<?php echo $record['supply_id']; ?>"
                                        data-supply-name="<?php echo escape($record['supply_name']); ?>"
                                        data-quantity="<?php echo $record['quantity']; ?>"
                                        data-issued-to="<?php echo escape($record['issued_to']); ?>"
                                        data-remarks="<?php echo escape($record['remarks'] ?? ''); ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editStockOutModal"
                                        title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-btn" 
                                        data-id="<?php echo $record['id']; ?>"
                                        data-name="<?php echo escape($record['transaction_no']); ?>"
                                        data-supply="<?php echo escape($record['supply_name']); ?>"
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
<div class="modal fade" id="addStockOutModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
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
                        <label class="form-label">School Supply <span class="text-danger">*</span></label>
                        <select name="supply_id" id="supplySelect" class="form-control" required>
                            <option value="">-- Select Supply --</option>
                            <?php foreach ($supplies as $supply): ?>
                                <option value="<?php echo $supply['id']; ?>" data-qty="<?php echo $supply['quantity']; ?>">
                                    <?php echo escape($supply['supply_name']); ?> (Available: <?php echo $supply['quantity']; ?>)
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
                        <label class="form-label">Issued To <span class="text-danger">*</span></label>
                        <input type="text" name="issued_to" class="form-control" required placeholder="e.g., John Doe, IT Department, Grade 7-A">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
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
                        <label class="form-label">School Supply</label>
                        <input type="text" class="form-control" id="edit_supply_name" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity Released <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="edit_quantity" class="form-control" required min="1">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Issued To <span class="text-danger">*</span></label>
                        <input type="text" name="issued_to" id="edit_issued_to" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" id="edit_remarks" class="form-control" rows="2"></textarea>
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
// Stock validation
const supplySelect = document.getElementById('supplySelect');
const releaseQuantity = document.getElementById('releaseQuantity');
const stockWarning = document.getElementById('stockWarning');

function validateStock() {
    const selectedOption = supplySelect?.options[supplySelect.selectedIndex];
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

if (supplySelect) supplySelect.addEventListener('change', validateStock);
if (releaseQuantity) releaseQuantity.addEventListener('input', validateStock);

// Search functionality
document.getElementById('searchStockOut')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#stockOutTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Delete confirmation
function showDeleteConfirmModal(id, name, supplyName) {
    Swal.fire({
        title: 'Delete Stock-Out Record?',
        html: `Are you sure you want to delete <strong>${name}</strong>?<br><br>
               <span class="text-danger">⚠️ Warning: This will add the quantity back to inventory!</span><br>
               <small>Supply: ${supplyName}</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6366f1',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
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

document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        showDeleteConfirmModal(this.dataset.id, this.dataset.name, this.dataset.supply);
    });
});

// Edit modal population
const editModal = document.getElementById('editStockOutModal');
if (editModal) {
    editModal.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        document.getElementById('edit_id').value = btn.dataset.id;
        document.getElementById('edit_supply_name').value = btn.dataset.supplyName;
        document.getElementById('edit_quantity').value = btn.dataset.quantity;
        document.getElementById('edit_issued_to').value = btn.dataset.issuedTo;
        document.getElementById('edit_remarks').value = btn.dataset.remarks || '';
    });
}

// Form submission handlers
document.getElementById('stockOutForm')?.addEventListener('submit', function(e) {
    if (!validateStock()) {
        e.preventDefault();
        Swal.fire({ icon: 'error', title: 'Insufficient Stock', text: 'Please check the available quantity!' });
        return false;
    }
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
    submitBtn.disabled = true;
});

// Export function
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    for (let row of rows) {
        const rowData = [];
        const cols = row.querySelectorAll('td, th');
        for (let col of cols) {
            if (col.cellIndex === 6 && col.tagName === 'TD') continue;
            rowData.push('"' + col.innerText.trim().replace(/"/g, '""') + '"');
        }
        if (rowData.length > 0) csv.push(rowData.join(','));
    }
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// Auto-hide alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert) alert.style.display = 'none';
    });
}, 5000);
</script>
<?php ob_end_flush(); ?>