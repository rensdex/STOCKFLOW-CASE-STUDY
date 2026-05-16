<?php
// File: users.php (updated - change status to is_active)
if (!isLoggedIn() || $_SESSION['role'] !== 'Administrator') {
    redirect('index.php?page=dashboard');
}

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        if ($_POST['action'] === 'add') {
            $user_id = 'USR-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (user_id, name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$user_id, $_POST['name'], $_POST['email'], $hashed_password, $_POST['role']]);
            $success = "User added successfully";
        } elseif ($_POST['action'] === 'edit') {
            if (!empty($_POST['password'])) {
                $hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$_POST['name'], $_POST['email'], $hashed_password, $_POST['role'], $_POST['is_active'], $_POST['id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$_POST['name'], $_POST['email'], $_POST['role'], $_POST['is_active'], $_POST['id']]);
            }
            $success = "User updated successfully";
        } elseif ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $success = "User deleted successfully";
        }
    }
}

// Fetch users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Users & Roles Management</h4>
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
    
    <div class="data-table">
        <div class="p-3">
            <input type="text" id="searchUser" class="form-control" placeholder="Search users...">
        </div>
        <div class="table-responsive">
            <table class="table" id="userTable">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
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
                        <td><?php echo escape($user['user_id']); ?></td>
                        <td><?php echo escape($user['name']); ?></td>
                        <td><?php echo escape($user['email']); ?></td>
                        <td>
                            <span class="badge <?php echo $user['role'] === 'Administrator' ? 'bg-danger' : ($user['role'] === 'Inventory Staff' ? 'bg-warning' : 'bg-info'); ?>">
                                <?php echo $user['role']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td><?php echo $user['last_login'] ? date('M d, H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary edit-user" 
                                    data-id="<?php echo $user['id']; ?>"
                                    data-name="<?php echo escape($user['name']); ?>"
                                    data-email="<?php echo escape($user['email']); ?>"
                                    data-role="<?php echo $user['role']; ?>"
                                    data-status="<?php echo $user['is_active']; ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
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
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control" required>
                            <option value="Viewer">Viewer</option>
                            <option value="Inventory Staff">Inventory Staff</option>
                            <option value="Administrator">Administrator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
searchTable('searchUser', 'userTable');
</script>