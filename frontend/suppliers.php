<?php
if (!isLoggedIn() || $_SESSION['role'] !== 'Administrator') {
    die('<div style="text-align: center; padding: 50px;"><h1 style="color: #ef4444;">🚫 Access Denied</h1><p>You do not have permission to access this page.</p><a href="index.php?page=dashboard">← Back to Dashboard</a></div>');
}
// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, email, phone, address, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$_POST['name'], $_POST['contact_person'], $_POST['email'], $_POST['phone'], $_POST['address']]);
            $success = "Supplier added successfully";
        } elseif ($_POST['action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, contact_person = ?, email = ?, phone = ?, address = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['contact_person'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['is_active'], $_POST['id']]);
            $success = "Supplier updated successfully";
        } elseif ($_POST['action'] === 'delete') {
            // Check if supplier has related records
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_in WHERE supplier_id = ?");
            $checkStmt->execute([$_POST['id']]);
            $stockInCount = $checkStmt->fetchColumn();
            
            $checkSupplyStmt = $pdo->prepare("SELECT COUNT(*) FROM school_supplies WHERE supplier_id = ?");
            $checkSupplyStmt->execute([$_POST['id']]);
            $supplyCount = $checkSupplyStmt->fetchColumn();
            
            if ($stockInCount > 0 || $supplyCount > 0) {
                $error = "Cannot delete this supplier because it has related records. Consider deactivating instead.";
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
        <h4>🏢 Suppliers Management</h4>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            <i class="bi bi-plus-lg"></i> Add Supplier
        </button>
    </div>
    
    <?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    
    <div class="data-table">
        <div class="p-3">
            <input type="text" id="searchSupplier" class="form-control w-50" placeholder="Search by name, contact person, email, or phone...">
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="supplierTable">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>SUPPLIER NAME</th>
                        <th>CONTACT PERSON</th>
                        <th>EMAIL</th>
                        <th>PHONE</th>
                        <th>ADDRESS</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                    <tr class="text-center">
                        <td colspan="8" class="py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-2">No suppliers found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><?php echo $supplier['id']; ?></td>
                            <td><strong><?php echo escape($supplier['name']); ?></strong></td>
                            <td><?php echo escape($supplier['contact_person'] ?? '-'); ?></td>
                            <td><?php echo escape($supplier['email'] ?? '-'); ?></td>
                            <td><?php echo escape($supplier['phone'] ?? '-'); ?></td>
                            <td><?php echo escape($supplier['address'] ?? '-'); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $supplier['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $supplier['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="text-center">
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
// Search functionality
document.getElementById('searchSupplier')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#supplierTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Edit modal population
const editModal = document.getElementById('editSupplierModal');
if (editModal) {
    editModal.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        document.getElementById('edit_id').value = btn.dataset.id;
        document.getElementById('edit_name').value = btn.dataset.name;
        document.getElementById('edit_contact_person').value = btn.dataset.contact || '';
        document.getElementById('edit_email').value = btn.dataset.email || '';
        document.getElementById('edit_phone').value = btn.dataset.phone || '';
        document.getElementById('edit_address').value = btn.dataset.address || '';
        document.getElementById('edit_is_active').value = btn.dataset.status;
    });
}

// Delete confirmation
document.querySelectorAll('.delete-supplier').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const id = this.dataset.id;
        const name = this.dataset.name;
        
        Swal.fire({
            title: 'Delete Supplier?',
            html: `Are you sure you want to delete <strong>${name}</strong>?<br><span class="text-warning">Note: Suppliers with stock-in records cannot be deleted.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6366f1',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});

// Form validation
document.getElementById('addSupplierForm')?.addEventListener('submit', function(e) {
    const name = document.querySelector('#addSupplierForm input[name="name"]').value;
    if (!name) {
        e.preventDefault();
        Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please enter supplier name' });
        return false;
    }
    document.getElementById('addSubmitBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adding...';
    document.getElementById('addSubmitBtn').disabled = true;
});

document.getElementById('editSupplierForm')?.addEventListener('submit', function(e) {
    document.getElementById('editSubmitBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
    document.getElementById('editSubmitBtn').disabled = true;
});

// Reset buttons on modal close
document.getElementById('addSupplierModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('addSubmitBtn').innerHTML = '<i class="bi bi-check-lg"></i> Add Supplier';
    document.getElementById('addSubmitBtn').disabled = false;
});

document.getElementById('editSupplierModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('editSubmitBtn').innerHTML = '<i class="bi bi-check-lg"></i> Update Supplier';
    document.getElementById('editSubmitBtn').disabled = false;
});
</script>