<?php
// File: categories.php
if (!isLoggedIn() || $_SESSION['role'] === 'Viewer') {
    redirect('index.php?page=dashboard');
}

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description, is_active) VALUES (?, ?, 1)");
            $stmt->execute([$_POST['name'], $_POST['description']]);
            $success = "Category added successfully";
        } elseif ($_POST['action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['description'], $_POST['is_active'], $_POST['id']]);
            $success = "Category updated successfully";
        } elseif ($_POST['action'] === 'delete') {
            // Check if category has related products before deleting
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE category_id = ?");
            $checkStmt->execute([$_POST['id']]);
            $productCount = $checkStmt->fetchColumn();
            
            if ($productCount > 0) {
                $error = "Cannot delete this category because it has $productCount product(s) associated with it. Consider deactivating instead.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Category deleted successfully";
            }
        }
    }
}

// Fetch categories
$stmt = $pdo->query("SELECT c.*, COALESCE(SUM(i.quantity), 0) as total_items 
                     FROM categories c 
                     LEFT JOIN inventory i ON c.id = i.category_id 
                     GROUP BY c.id 
                     ORDER BY c.created_at DESC");
$categories = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Categories Management</h4>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-lg"></i> Add Category
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
    
    <div class="data-table mb-4">
        <div class="p-3">
            <input type="text" id="searchCategory" class="form-control w-50" placeholder="Search categories by name or description...">
        </div>
    </div>
    
    <div class="row g-4" id="categoriesContainer">
        <?php if (empty($categories)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center">No categories found. Click "Add Category" to create one.</div>
            </div>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
            <div class="col-md-6 col-lg-4 category-card" data-category-name="<?php echo strtolower(escape($category['name'])); ?>" data-category-desc="<?php echo strtolower(escape($category['description'])); ?>">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1"><?php echo escape($category['name']); ?></h5>
                            <p class="text-muted small mb-0"><?php echo escape($category['description']) ?: 'No description'; ?></p>
                        </div>
                        <span class="badge bg-primary"><?php echo number_format($category['total_items']); ?> ITEMS</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?php echo $category['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                            <button class="btn btn-sm btn-icon edit-category" 
                                    data-id="<?php echo $category['id']; ?>"
                                    data-name="<?php echo escape($category['name']); ?>"
                                    data-desc="<?php echo escape($category['description']); ?>"
                                    data-status="<?php echo $category['is_active']; ?>"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editCategoryModal"
                                    title="Manage Category">
                                <i class="bi bi-gear-fill"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="addCategoryForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Optional description for this category"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" id="addSubmitBtn">
                        <i class="bi bi-check-lg"></i> Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit/Manage Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editCategoryForm">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
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
                    <button type="button" class="btn btn-danger" id="deleteCategoryBtn">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                    <button type="submit" class="btn btn-primary-custom" id="editSubmitBtn">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Search functionality for categories
document.getElementById('searchCategory')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const cards = document.querySelectorAll('.category-card');
    
    cards.forEach(card => {
        const name = card.getAttribute('data-category-name') || '';
        const desc = card.getAttribute('data-category-desc') || '';
        
        if (name.includes(searchTerm) || desc.includes(searchTerm)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
});

// Edit modal - populate fields
const editModal = document.getElementById('editCategoryModal');
let currentCategoryId = null;
let currentCategoryName = null;

if (editModal) {
    editModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const name = button.getAttribute('data-name');
        const desc = button.getAttribute('data-desc');
        const status = button.getAttribute('data-status');
        
        currentCategoryId = id;
        currentCategoryName = name;
        
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_description').value = desc || '';
        document.getElementById('edit_is_active').value = status;
    });
}

// Delete button in edit modal
document.getElementById('deleteCategoryBtn')?.addEventListener('click', function() {
    const id = currentCategoryId;
    const name = currentCategoryName;
    
    showDeleteConfirmModal(id, name);
});

function showDeleteConfirmModal(id, name) {
    Swal.fire({
        title: 'Delete Category?',
        html: `Are you sure you want to delete category <strong>${name}</strong>?<br><span class="text-warning">Note: Categories with products cannot be deleted.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo generateCSRFToken(); ?>';
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            form.appendChild(csrfInput);
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Add Category form validation with SweetAlert
document.getElementById('addCategoryForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.querySelector('#addCategoryForm input[name="name"]').value;
    
    if (!name) {
        showErrorModal('Please enter category name');
        return false;
    }
    
    showConfirmModal('Add Category', 'Are you sure you want to add this category?', () => {
        document.getElementById('addSubmitBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
        document.getElementById('addSubmitBtn').disabled = true;
        document.getElementById('addCategoryForm').submit();
    });
    
    return false;
});

// Edit Category form validation with SweetAlert
document.getElementById('editCategoryForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.getElementById('edit_name').value;
    
    if (!name) {
        showErrorModal('Please enter category name');
        return false;
    }
    
    showConfirmModal('Update Category', 'Are you sure you want to update this category?', () => {
        document.getElementById('editSubmitBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
        document.getElementById('editSubmitBtn').disabled = true;
        document.getElementById('editCategoryForm').submit();
    });
    
    return false;
});

// SweetAlert helper functions
function showConfirmModal(title, message, confirmCallback) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#121350',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, proceed!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed && confirmCallback) {
            confirmCallback();
        }
    });
}

function showErrorModal(message) {
    Swal.fire({
        icon: 'error',
        title: 'Validation Error',
        text: message,
        confirmButtonColor: '#121350'
    });
}

function showSuccessModal(message) {
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: message,
        confirmButtonColor: '#121350',
        timer: 2000,
        showConfirmButton: false
    });
}

// Clear form when modals close
document.getElementById('addCategoryModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('addCategoryForm')?.reset();
    document.getElementById('addSubmitBtn').innerHTML = '<i class="bi bi-check-lg"></i> Add Category';
    document.getElementById('addSubmitBtn').disabled = false;
});

document.getElementById('editCategoryModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('editCategoryForm')?.reset();
    document.getElementById('editSubmitBtn').innerHTML = '<i class="bi bi-save"></i> Save Changes';
    document.getElementById('editSubmitBtn').disabled = false;
    currentCategoryId = null;
    currentCategoryName = null;
});

// Check for success message and show SweetAlert
<?php if (isset($success)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showSuccessModal('<?php echo addslashes($success); ?>');
});
<?php endif; ?>

<?php if (isset($error)): ?>
document.addEventListener('DOMContentLoaded', function() {
    showErrorModal('<?php echo addslashes($error); ?>');
});
<?php endif; ?>
</script>

<style>
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
    border: 1px solid #e9ecef;
    height: 100%;
}
.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.65rem;
}
.btn-icon {
    background: transparent;
    border: none;
    padding: 6px 8px;
    font-size: 1rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #6c757d;
}
.btn-icon:hover {
    background-color: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
    transform: scale(1.05);
}
.btn-icon i {
    font-size: 1.1rem;
}
</style>