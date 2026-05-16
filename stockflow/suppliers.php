<?php
// File: suppliers.php
if (!isLoggedIn() || $_SESSION['role'] === 'Viewer') {
    redirect('index.php?page=dashboard');
}

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        if ($_POST['action'] === 'add') {
            $supplier_id = 'SUP-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_id, name, contact_person, email, phone, address, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$supplier_id, $_POST['name'], $_POST['contact_person'], $_POST['email'], $_POST['phone'], $_POST['address']]);
            $success = "Supplier added successfully";
        } elseif ($_POST['action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, contact_person = ?, email = ?, phone = ?, address = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['contact_person'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['is_active'], $_POST['id']]);
            $success = "Supplier updated successfully";
        } elseif ($_POST['action'] === 'delete') {
            // Check if supplier has related records before deleting
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_in WHERE supplier_id = ?");
            $checkStmt->execute([$_POST['id']]);
            $stockInCount = $checkStmt->fetchColumn();
            
            if ($stockInCount > 0) {
                $error = "Cannot delete this supplier because it has related stock-in records. Consider deactivating instead.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Supplier deleted successfully";
            }
        }
    }
}

// Fetch suppliers
$stmt = $pdo->query("SELECT * FROM suppliers ORDER BY created_at DESC");
$suppliers = $stmt->fetchAll();
?>

<!-- Include SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Suppliers Management</h4>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            <i class="bi bi-plus-lg"></i> Add Supplier
        </button>
    </div>
    
    <div class="data-table">
        <div class="p-3 d-flex justify-content-between gap-3">
            <input type="text" id="searchSupplier" class="form-control w-50" placeholder="Search by supplier name, contact person, email, or phone...">
            <button class="btn btn-outline-secondary" onclick="exportToCSV('supplierTable', 'suppliers_report')">
                <i class="bi bi-download"></i> Export
            </button>
        </div>
        <div class="table-responsive" style="overflow-x: auto;">
            <table class="table table-bordered table-hover" id="supplierTable" style="min-width: 900px;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 8%;">ID</th>
                        <th style="width: 15%;">SUPPLIER</th>
                        <th style="width: 15%;">CONTACT PERSON</th>
                        <th style="width: 18%;">EMAIL</th>
                        <th style="width: 10%;">PHONE</th>
                        <th style="width: 22%;">ADDRESS</th>
                        <th style="width: 7%;">STATUS</th>
                        <th style="width: 5%;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No suppliers found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $supplier): ?>
                        <tr id="row-<?php echo $supplier['id']; ?>">
                            <td style="vertical-align: middle; white-space: nowrap;"><?php echo escape($supplier['supplier_id']); ?></td>
                            <td style="vertical-align: middle; word-wrap: break-word; white-space: normal; min-width: 120px;">
                                <?php echo escape($supplier['name']); ?>
                            </td>
                            <td style="vertical-align: middle; word-wrap: break-word; white-space: normal; min-width: 120px;">
                                <?php echo escape($supplier['contact_person'] ?? '-'); ?>
                            </td>
                            <td style="vertical-align: middle; word-wrap: break-word; white-space: normal; min-width: 150px;">
                                <?php echo escape($supplier['email'] ?? '-'); ?>
                            </td>
                            <td style="vertical-align: middle; white-space: nowrap;"><?php echo escape($supplier['phone'] ?? '-'); ?></td>
                            <td style="vertical-align: middle; word-wrap: break-word; white-space: normal; min-width: 180px;">
                                <?php echo escape($supplier['address'] ?? '-'); ?>
                            </td>
                            <td style="vertical-align: middle; text-align: center;">
                                <span class="badge <?php echo $supplier['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $supplier['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td style="vertical-align: middle; text-align: center; white-space: nowrap;">
                                <button class="btn btn-sm btn-outline-primary edit-supplier" 
                                        data-id="<?php echo $supplier['id']; ?>"
                                        data-name="<?php echo escape($supplier['name']); ?>"
                                        data-contact="<?php echo escape($supplier['contact_person'] ?? ''); ?>"
                                        data-email="<?php echo escape($supplier['email'] ?? ''); ?>"
                                        data-phone="<?php echo escape($supplier['phone'] ?? ''); ?>"
                                        data-address="<?php echo escape($supplier['address'] ?? ''); ?>"
                                        data-status="<?php echo $supplier['is_active']; ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editSupplierModal"
                                        title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-supplier" 
                                        data-id="<?php echo $supplier['id']; ?>"
                                        data-name="<?php echo escape($supplier['name']); ?>"
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

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="addSupplierForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="addSubmitBtn">
                        <i class="bi bi-check-lg"></i> Add Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editSupplierForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" id="edit_contact_person" class="form-control">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="edit_address" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="is_active" id="edit_is_active" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="editSubmitBtn">
                        <i class="bi bi-check-lg"></i> Update Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// SweetAlert2 Functions
function showDeleteConfirmModal(id, name) {
    Swal.fire({
        title: 'Delete Supplier?',
        html: `Are you sure you want to delete <strong>${name}</strong>?<br><br>
               <span class="text-warning">⚠️ Note: Suppliers with stock-in records cannot be deleted.</span>`,
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
document.getElementById('searchSupplier')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('supplierTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let row of rows) {
        if (row.classList.contains('no-data-row')) continue;
        
        const supplierId = row.cells[0]?.innerText.toLowerCase() || '';
        const name = row.cells[1]?.innerText.toLowerCase() || '';
        const contactPerson = row.cells[2]?.innerText.toLowerCase() || '';
        const email = row.cells[3]?.innerText.toLowerCase() || '';
        const phone = row.cells[4]?.innerText.toLowerCase() || '';
        const address = row.cells[5]?.innerText.toLowerCase() || '';
        
        if (supplierId.includes(searchTerm) || name.includes(searchTerm) || contactPerson.includes(searchTerm) || 
            email.includes(searchTerm) || phone.includes(searchTerm) || address.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
});

// Edit modal - populate fields
const editModal = document.getElementById('editSupplierModal');
if (editModal) {
    editModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        const contact = button.getAttribute('data-contact');
        const email = button.getAttribute('data-email');
        const phone = button.getAttribute('data-phone');
        const address = button.getAttribute('data-address');
        const status = button.getAttribute('data-status');
        
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_contact_person').value = contact;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_phone').value = phone;
        document.getElementById('edit_address').value = address;
        document.getElementById('edit_is_active').value = status;
    });
}

// Delete button with SweetAlert confirmation
document.querySelectorAll('.delete-supplier').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        
        showDeleteConfirmModal(id, name);
    });
});

// Add Supplier form validation with SweetAlert
document.getElementById('addSupplierForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.querySelector('#addSupplierForm input[name="name"]').value;
    
    if (!name) {
        showErrorModal('Validation Error', 'Please enter supplier name');
        return false;
    }
    
    showConfirmModal('Add Supplier', 'Are you sure you want to add this supplier?', () => {
        const submitBtn = document.getElementById('addSubmitBtn');
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
        submitBtn.disabled = true;
        document.getElementById('addSupplierForm').submit();
    });
    
    return false;
});

// Edit Supplier form validation with SweetAlert
document.getElementById('editSupplierForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.getElementById('edit_name').value;
    
    if (!name) {
        showErrorModal('Validation Error', 'Please enter supplier name');
        return false;
    }
    
    showConfirmModal('Update Supplier', 'Are you sure you want to update this supplier?', () => {
        const submitBtn = document.getElementById('editSubmitBtn');
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
        submitBtn.disabled = true;
        document.getElementById('editSupplierForm').submit();
    });
    
    return false;
});

// Clear form when modals close
document.getElementById('addSupplierModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('addSupplierForm')?.reset();
    const submitBtn = document.getElementById('addSubmitBtn');
    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Add Supplier';
    submitBtn.disabled = false;
});

document.getElementById('editSupplierModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('editSupplierForm')?.reset();
    const submitBtn = document.getElementById('editSubmitBtn');
    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Update Supplier';
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
                // Skip the actions column (last column)
                if (col.cellIndex === 7 && col.tagName === 'TD') {
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
    if (message.includes('added')) {
        title = 'Supplier Added!';
    } else if (message.includes('updated')) {
        title = 'Supplier Updated!';
    } else if (message.includes('deleted')) {
        title = 'Supplier Deleted!';
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
.table td:nth-child(4),
.table td:nth-child(6) {
    white-space: normal;
    word-wrap: break-word;
    word-break: break-word;
}

/* Fixed column widths */
.table th:nth-child(1) { width: 8%; }  /* ID */
.table th:nth-child(2) { width: 15%; } /* SUPPLIER */
.table th:nth-child(3) { width: 15%; } /* CONTACT PERSON */
.table th:nth-child(4) { width: 18%; } /* EMAIL */
.table th:nth-child(5) { width: 10%; } /* PHONE */
.table th:nth-child(6) { width: 22%; } /* ADDRESS */
.table th:nth-child(7) { width: 7%; }  /* STATUS */
.table th:nth-child(8) { width: 5%; }  /* ACTIONS */

/* Center align specific columns */
.table td:nth-child(7),
.table th:nth-child(7) {
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
    .table td:nth-child(3),
    .table td:nth-child(4),
    .table td:nth-child(6) {
        min-width: 120px;
    }
    
    .btn-sm {
        padding: 0.2rem 0.4rem;
    }
}
</style>