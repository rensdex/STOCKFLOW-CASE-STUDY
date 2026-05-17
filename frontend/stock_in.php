<?php
// File: frontend/stock_in.php
ob_start(); // Add output buffering at the top

// Handle Stock-In ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid security token';
        header("Location: index.php?page=stock_in");
        exit();
    }
    
    $transaction_no = 'SI-' . date('Ymd') . '-' . rand(1000, 9999);
    
    $pdo->beginTransaction();
    
    
        $stmt = $pdo->prepare("INSERT INTO stock_in (transaction_no, supply_id, supplier_id, quantity, remarks, received_by, date_received) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
        $stmt->execute([$transaction_no, $_POST['supply_id'], $_POST['supplier_id'], $_POST['quantity'], $_POST['notes'] ?? '', $_SESSION['user_id']]);
        
        $updateStmt = $pdo->prepare("UPDATE school_supplies SET quantity = quantity + ? WHERE id = ?");
        $updateStmt->execute([$_POST['quantity'], $_POST['supply_id']]);
        
        $statusStmt = $pdo->prepare("UPDATE school_supplies SET status = CASE 
            WHEN quantity <= 0 THEN 'Out of Stock'
            WHEN quantity <= low_stock_threshold THEN 'Low Stock'
            ELSE 'In Stock'
        END WHERE id = ?");
        $statusStmt->execute([$_POST['supply_id']]);
        
        $productStmt = $pdo->prepare("SELECT supply_name FROM school_supplies WHERE id = ?");
        $productStmt->execute([$_POST['supply_id']]);
        $supply = $productStmt->fetch();
        
        $notifStmt = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, 'success')");
        $notifStmt->execute(['📦 Stock Received', $_POST['quantity'] . ' units of ' . $supply['supply_name'] . ' added to inventory']);
        
        triggerStockUpdate('stock_in', $supply['supply_name'], $_POST['quantity']);
        triggerRealtimeNotification('Stock Received', $_POST['quantity'] . ' units of ' . $supply['supply_name'] . ' added to inventory', 'success');
        
        $pdo->commit();
        
        $_SESSION['flash_success'] = "Stock-In recorded successfully and inventory updated";
        header("Location: index.php?page=stock_in");
        exit();
        
   
}

// Handle Stock-In EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid security token';
        header("Location: index.php?page=stock_in");
        exit();
    }
    
    $pdo->beginTransaction();
    
    
        $oldStmt = $pdo->prepare("SELECT quantity, supply_id FROM stock_in WHERE id = ?");
        $oldStmt->execute([$_POST['id']]);
        $oldData = $oldStmt->fetch();
        
        if (!$oldData) {
            throw new Exception("Stock-in record not found");
        }
        
        $stmt = $pdo->prepare("UPDATE stock_in SET supplier_id = ?, quantity = ?, remarks = ? WHERE id = ?");
        $stmt->execute([$_POST['supplier_id'], $_POST['quantity'], $_POST['notes'], $_POST['id']]);
        
        $quantityDiff = $_POST['quantity'] - $oldData['quantity'];
        $updateStmt = $pdo->prepare("UPDATE school_supplies SET quantity = quantity + ? WHERE id = ?");
        $updateStmt->execute([$quantityDiff, $oldData['supply_id']]);
        
        $statusStmt = $pdo->prepare("UPDATE school_supplies SET status = CASE 
            WHEN quantity <= 0 THEN 'Out of Stock'
            WHEN quantity <= low_stock_threshold THEN 'Low Stock'
            ELSE 'In Stock'
        END WHERE id = ?");
        $statusStmt->execute([$oldData['supply_id']]);
        
        $pdo->commit();
        
        $_SESSION['flash_success'] = "Stock-In updated successfully";
        header("Location: index.php?page=stock_in");
        exit();
        
   
}

// Handle Stock-In DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid security token';
        header("Location: index.php?page=stock_in");
        exit();
    }
    
    $pdo->beginTransaction();
    
    
        $stmt = $pdo->prepare("SELECT quantity, supply_id FROM stock_in WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $record = $stmt->fetch();
        
        if ($record) {
            $updateStmt = $pdo->prepare("UPDATE school_supplies SET quantity = quantity - ? WHERE id = ?");
            $updateStmt->execute([$record['quantity'], $record['supply_id']]);
            
            $statusStmt = $pdo->prepare("UPDATE school_supplies SET status = CASE 
                WHEN quantity <= 0 THEN 'Out of Stock'
                WHEN quantity <= low_stock_threshold THEN 'Low Stock'
                ELSE 'In Stock'
            END WHERE id = ?");
            $statusStmt->execute([$record['supply_id']]);
            
            $deleteStmt = $pdo->prepare("DELETE FROM stock_in WHERE id = ?");
            $deleteStmt->execute([$_POST['id']]);
            
            $pdo->commit();
            
            $_SESSION['flash_success'] = "Stock-In record deleted and inventory adjusted";
            header("Location: index.php?page=stock_in");
            exit();
        } else {
            throw new Exception("Record not found");
        }
   
}

// Get flash messages from session
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success']);
unset($_SESSION['flash_error']);

// Fetch stock-in records
$stmt = $pdo->query("SELECT si.*, s.supply_name, sup.name as supplier_name, u.fullname as staff_name 
                     FROM stock_in si 
                     JOIN school_supplies s ON si.supply_id = s.id 
                     JOIN suppliers sup ON si.supplier_id = sup.id 
                     JOIN users u ON si.received_by = u.id 
                     ORDER BY si.created_at DESC");
$stock_in_records = $stmt->fetchAll();

// Fetch supplies for dropdown
$stmt = $pdo->query("SELECT id, supply_name, quantity FROM school_supplies ORDER BY supply_name");
$supplies = $stmt->fetchAll();

// Fetch suppliers for dropdown
$stmt = $pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
$suppliers = $stmt->fetchAll();
?>

<!-- Include SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>📦 Stock-In Management</h4>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addStockInModal">
            <i class="bi bi-plus-lg"></i> New Stock-In
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
            <input type="text" id="searchStockIn" class="form-control w-50" placeholder="Search transactions by ID, product, or supplier...">
            <button class="btn btn-outline-secondary" onclick="exportToCSV('stockInTable', 'stock_in_report')">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="stockInTable">
                <thead class="table-light">
                    <tr>
                        <th>TXN ID</th>
                        <th>SUPPLY</th>
                        <th>SUPPLIER</th>
                        <th>QTY ADDED</th>
                        <th>DATE</th>
                        <th>NOTES</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stock_in_records)): ?>
                    <tr class="text-center">
                        <td colspan="7" class="py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-2">No stock-in records found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($stock_in_records as $record): ?>
                        <tr id="row-<?php echo $record['id']; ?>">
                            <td class="fw-bold"><?php echo escape($record['transaction_no']); ?></td>
                            <td><?php echo escape($record['supply_name']); ?></td>
                            <td><?php echo escape($record['supplier_name']); ?></td>
                            <td class="text-center">
                                <span class="badge bg-success">+<?php echo number_format($record['quantity']); ?></span>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($record['date_received'])); ?></td>
                            <td><?php echo escape($record['remarks'] ?? '-'); ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary edit-btn" 
                                        data-id="<?php echo $record['id']; ?>"
                                        data-supply-id="<?php echo $record['supply_id']; ?>"
                                        data-supply-name="<?php echo escape($record['supply_name']); ?>"
                                        data-supplier-id="<?php echo $record['supplier_id']; ?>"
                                        data-quantity="<?php echo $record['quantity']; ?>"
                                        data-notes="<?php echo escape($record['remarks'] ?? ''); ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editStockInModal"
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

<!-- Add Stock-In Modal -->
<div class="modal fade" id="addStockInModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
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
                        <label class="form-label">School Supply <span class="text-danger">*</span></label>
                        <select name="supply_id" class="form-control" id="supply_id" required>
                            <option value="">-- Select Supply --</option>
                            <?php foreach ($supplies as $supply): ?>
                                <option value="<?php echo $supply['id']; ?>">
                                    <?php echo escape($supply['supply_name']); ?> (Current: <?php echo $supply['quantity']; ?>)
                                </option>
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
                        <label class="form-label">Notes / Reference</label>
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
                        <label class="form-label">School Supply</label>
                        <input type="text" class="form-control" id="edit_supply_name" disabled>
                        <small class="text-muted">Supply cannot be changed</small>
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
                        <label class="form-label">Notes / Reference</label>
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
// Edit modal - populate fields
const editModal = document.getElementById('editStockInModal');
if (editModal) {
    editModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        document.getElementById('edit_id').value = button.getAttribute('data-id');
        document.getElementById('edit_supply_name').value = button.getAttribute('data-supply-name');
        document.getElementById('edit_supplier_id').value = button.getAttribute('data-supplier-id');
        document.getElementById('edit_quantity').value = button.getAttribute('data-quantity');
        document.getElementById('edit_notes').value = button.getAttribute('data-notes');
    });
}

// Delete confirmation
function showDeleteConfirmModal(id, name, supplyName) {
    Swal.fire({
        title: 'Delete Stock-In Record?',
        html: `Are you sure you want to delete <strong>${name}</strong>?<br><br>
               <span class="text-danger">⚠️ Warning: This will remove the added quantity from inventory!</span><br>
               <small>Supply: ${supplyName}</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6366f1',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
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
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        const supplyName = this.getAttribute('data-supply');
        showDeleteConfirmModal(id, name, supplyName);
    });
});

// Search functionality
document.getElementById('searchStockIn')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#stockInTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Form submission
document.getElementById('stockInForm')?.addEventListener('submit', function(e) {
    const supply = document.getElementById('supply_id').value;
    const quantity = document.getElementById('quantity').value;
    
    if (!supply) {
        e.preventDefault();
        Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please select a supply' });
        return false;
    }
    
    if (!quantity || quantity < 1) {
        e.preventDefault();
        Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please enter a valid quantity' });
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