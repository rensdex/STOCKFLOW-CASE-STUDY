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
            // Generate username from name
            $username = strtolower(str_replace(' ', '.', $_POST['name']));
            // Check if username exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $checkStmt->execute([$username]);
            if ($checkStmt->fetchColumn() > 0) {
                $username = $username . rand(1, 999);
            }
            
            $hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, fullname, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$username, $_POST['name'], $_POST['email'], $hashed_password, $_POST['role']]);
            $success = "User added successfully. Username: " . $username;
        } elseif ($_POST['action'] === 'edit') {
            if (!empty($_POST['password'])) {
                $hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, password = ?, role = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$_POST['name'], $_POST['email'], $hashed_password, $_POST['role'], $_POST['is_active'], $_POST['id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$_POST['name'], $_POST['email'], $_POST['role'], $_POST['is_active'], $_POST['id']]);
            }
            $success = "User updated successfully";
        } elseif ($_POST['action'] === 'delete') {
            // Prevent deleting own account
            if ($_POST['id'] == $_SESSION['user_id']) {
                $error = "You cannot delete your own account!";
            } else {
                try {
                    // First, check if user has related records
                    $checkStockIn = $pdo->prepare("SELECT COUNT(*) FROM stock_in WHERE received_by = ?");
                    $checkStockIn->execute([$_POST['id']]);
                    $stockInCount = $checkStockIn->fetchColumn();
                    
                    $checkStockOut = $pdo->prepare("SELECT COUNT(*) FROM stock_out WHERE issued_by = ?");
                    $checkStockOut->execute([$_POST['id']]);
                    $stockOutCount = $checkStockOut->fetchColumn();
                    
                    $checkAudit = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id = ?");
                    $checkAudit->execute([$_POST['id']]);
                    $auditCount = $checkAudit->fetchColumn();
                    
                    if ($stockInCount > 0 || $stockOutCount > 0 || $auditCount > 0) {
                        // Instead of deleting, deactivate the user
                        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                        $stmt->execute([$_POST['id']]);
                        $success = "User has been deactivated because they have existing transaction records. They can be reactivated anytime.";
                    } else {
                        // No related records, safe to delete
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$_POST['id']]);
                        $success = "User deleted successfully";
                    }
                } catch (PDOException $e) {
                    // If deletion fails, deactivate instead
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $success = "User has been deactivated due to existing records. They can be reactivated anytime.";
                }
            }
        }
    }
}

// Fetch users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>👥 Users & Roles Management</h4>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-plus-lg"></i> Add User
        </button>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="data-table">
        <div class="p-3">
            <input type="text" id="searchUser" class="form-control" placeholder="Search by name, username, email, or role...">
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="userTable">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo escape($user['id']); ?></td>
                        <td><code><?php echo escape($user['username']); ?></code></td>
                        <td><?php echo escape($user['fullname']); ?></td>
                        <td><?php echo escape($user['email']); ?></td>
                        <td>
                            <span class="badge <?php echo $user['role'] === 'Administrator' ? 'bg-danger' : 'bg-primary'; ?>">
                                <?php echo escape($user['role']); ?>
                            </span>
                         </td>
                        <td>
                            <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                         </td>
                        <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary edit-user" 
                                    data-id="<?php echo $user['id']; ?>"
                                    data-name="<?php echo escape($user['fullname']); ?>"
                                    data-email="<?php echo escape($user['email']); ?>"
                                    data-role="<?php echo $user['role']; ?>"
                                    data-status="<?php echo $user['is_active']; ?>"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editUserModal">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-sm btn-outline-danger delete-user" 
                                    data-id="<?php echo $user['id']; ?>"
                                    data-name="<?php echo escape($user['fullname']); ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                         </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="addUserForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                        <small class="text-muted">Username will be auto-generated from full name</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-control" required>
                            <option value="Staff">Staff (Inventory management)</option>
                            <option value="Administrator">Administrator (Full access)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="addSubmitBtn">
                        <i class="bi bi-check-lg"></i> Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editUserForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password (leave blank to keep unchanged)</label>
                        <input type="password" name="password" class="form-control" minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" id="edit_role" class="form-control" required>
                            <option value="Staff">Staff (Inventory management)</option>
                            <option value="Administrator">Administrator (Full access)</option>
                        </select>
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
                        <i class="bi bi-save"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Search functionality
document.getElementById('searchUser')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#userTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Edit modal - populate fields
const editModal = document.getElementById('editUserModal');
if (editModal) {
    editModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        document.getElementById('edit_id').value = button.dataset.id;
        document.getElementById('edit_name').value = button.dataset.name;
        document.getElementById('edit_email').value = button.dataset.email;
        document.getElementById('edit_role').value = button.dataset.role;
        document.getElementById('edit_is_active').value = button.dataset.status;
    });
}

// Delete user confirmation
document.querySelectorAll('.delete-user').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const id = this.dataset.id;
        const name = this.dataset.name;
        
        Swal.fire({
            title: 'Delete/Deactivate User?',
            html: `Are you sure you want to delete <strong>${name}</strong>?<br><br>
                   <span class="text-warning">⚠️ Note: If this user has transaction records, they will be deactivated instead of deleted.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6366f1',
            confirmButtonText: 'Yes, proceed!'
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

// Add User form validation
document.getElementById('addUserForm')?.addEventListener('submit', function(e) {
    const password = document.querySelector('#addUserForm input[name="password"]').value;
    if (password.length < 6) {
        e.preventDefault();
        Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Password must be at least 6 characters' });
        return false;
    }
    document.getElementById('addSubmitBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adding...';
    document.getElementById('addSubmitBtn').disabled = true;
});

// Edit User form validation
document.getElementById('editUserForm')?.addEventListener('submit', function(e) {
    document.getElementById('editSubmitBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
    document.getElementById('editSubmitBtn').disabled = true;
});

// Reset buttons on modal close
document.getElementById('addUserModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('addSubmitBtn').innerHTML = '<i class="bi bi-check-lg"></i> Add User';
    document.getElementById('addSubmitBtn').disabled = false;
});

document.getElementById('editUserModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('editSubmitBtn').innerHTML = '<i class="bi bi-save"></i> Update User';
    document.getElementById('editSubmitBtn').disabled = false;
});
</script>

<style>
.data-table {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.table th {
    background: #f8fafc;
    padding: 1rem;
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
    font-size: 0.85rem;
}
.table td {
    padding: 0.85rem 1rem;
    vertical-align: middle;
}
.badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
}
.bg-primary { background: #3b82f6 !important; }
.bg-danger { background: #ef4444 !important; }
.bg-success { background: #10b981 !important; }
.bg-secondary { background: #6c757d !important; }
</style>