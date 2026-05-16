<?php
// File: dashboard.php

// Fix session start - check if session is already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get selected year from URL parameter
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Handle Add Product Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_product') {
            // Generate asset code if not provided
            $asset_code = !empty($_POST['asset_code']) ? $_POST['asset_code'] : 'PRD-' . strtoupper(uniqid());
            
            try {
                // First, check what columns exist in inventory table
                $columns = $pdo->query("SHOW COLUMNS FROM inventory")->fetchAll(PDO::FETCH_COLUMN);
                
                // Build insert query based on existing columns
                if (in_array('location', $columns) && in_array('low_stock_threshold', $columns)) {
                    $stmt = $pdo->prepare("INSERT INTO inventory (product_name, asset_code, category_id, supplier_id, quantity, unit_price, low_stock_threshold, location, description, status) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'In Stock')");
                    $stmt->execute([
                        $_POST['product_name'],
                        $asset_code,
                        $_POST['category_id'],
                        $_POST['supplier_id'],
                        $_POST['quantity'],
                        $_POST['unit_price'],
                        $_POST['low_stock_threshold'] ?? 10,
                        $_POST['location'] ?? '',
                        $_POST['description'] ?? ''
                    ]);
                } elseif (in_array('low_stock_threshold', $columns)) {
                    $stmt = $pdo->prepare("INSERT INTO inventory (product_name, asset_code, category_id, supplier_id, quantity, unit_price, low_stock_threshold, description, status) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'In Stock')");
                    $stmt->execute([
                        $_POST['product_name'],
                        $asset_code,
                        $_POST['category_id'],
                        $_POST['supplier_id'],
                        $_POST['quantity'],
                        $_POST['unit_price'],
                        $_POST['low_stock_threshold'] ?? 10,
                        $_POST['description'] ?? ''
                    ]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO inventory (product_name, asset_code, category_id, supplier_id, quantity, unit_price, description, status) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, 'In Stock')");
                    $stmt->execute([
                        $_POST['product_name'],
                        $asset_code,
                        $_POST['category_id'],
                        $_POST['supplier_id'],
                        $_POST['quantity'],
                        $_POST['unit_price'],
                        $_POST['description'] ?? ''
                    ]);
                }
            } catch (PDOException $e) {
                // Fallback to minimal columns
                $stmt = $pdo->prepare("INSERT INTO inventory (product_name, asset_code, category_id, supplier_id, quantity, unit_price, status) 
                                       VALUES (?, ?, ?, ?, ?, ?, 'In Stock')");
                $stmt->execute([
                    $_POST['product_name'],
                    $asset_code,
                    $_POST['category_id'],
                    $_POST['supplier_id'],
                    $_POST['quantity'],
                    $_POST['unit_price']
                ]);
            }
            $_SESSION['success_message'] = "Product added successfully!";
            echo '<script>window.location.href = "?page=dashboard";</script>';
            exit();
        }
        
        elseif ($_POST['action'] === 'add_stock_in') {
            $transaction_id = 'SI-' . date('Ymd') . '-' . rand(1000, 9999);
            
            $pdo->beginTransaction();
            try {
                // Check columns in stock_in table
                $columns = $pdo->query("SHOW COLUMNS FROM stock_in")->fetchAll(PDO::FETCH_COLUMN);
                
                if (in_array('reference_no', $columns) && in_array('remarks', $columns)) {
                    $stmt = $pdo->prepare("INSERT INTO stock_in (transaction_id, product_id, quantity_added, reference_no, remarks, staff_id, date) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $transaction_id,
                        $_POST['product_id'],
                        $_POST['quantity_added'],
                        $_POST['reference_no'] ?? '',
                        $_POST['remarks'] ?? '',
                        $_SESSION['user_id'],
                        $_POST['date']
                    ]);
                } elseif (in_array('notes', $columns)) {
                    $stmt = $pdo->prepare("INSERT INTO stock_in (transaction_id, product_id, quantity_added, notes, staff_id, date) 
                                           VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $transaction_id,
                        $_POST['product_id'],
                        $_POST['quantity_added'],
                        $_POST['remarks'] ?? '',
                        $_SESSION['user_id'],
                        $_POST['date']
                    ]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO stock_in (transaction_id, product_id, quantity_added, staff_id, date) 
                                           VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $transaction_id,
                        $_POST['product_id'],
                        $_POST['quantity_added'],
                        $_SESSION['user_id'],
                        $_POST['date']
                    ]);
                }
                
                // Update inventory quantity
                $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
                $updateStmt->execute([$_POST['quantity_added'], $_POST['product_id']]);
                
                $pdo->commit();
                $_SESSION['success_message'] = "Stock-In recorded successfully!";
                echo '<script>window.location.href = "?page=dashboard";</script>';
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Failed to record stock-in: " . $e->getMessage();
                echo '<script>window.location.href = "?page=dashboard";</script>';
                exit();
            }
        }
        
        elseif ($_POST['action'] === 'add_stock_out') {
            // Check if enough stock
            $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ?");
            $stmt->execute([$_POST['product_id']]);
            $current_qty = $stmt->fetchColumn();
            
            if ($current_qty >= $_POST['quantity_released']) {
                $transaction_id = 'SO-' . date('Ymd') . '-' . rand(1000, 9999);
                
                $pdo->beginTransaction();
                try {
                    // Check columns in stock_out table
                    $columns = $pdo->query("SHOW COLUMNS FROM stock_out")->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (in_array('recipient', $columns) && in_array('purpose', $columns) && in_array('remarks', $columns)) {
                        $stmt = $pdo->prepare("INSERT INTO stock_out (transaction_id, product_id, quantity_released, recipient, purpose, remarks, staff_id, date) 
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $transaction_id,
                            $_POST['product_id'],
                            $_POST['quantity_released'],
                            $_POST['recipient'],
                            $_POST['purpose'] ?? '',
                            $_POST['remarks'] ?? '',
                            $_SESSION['user_id'],
                            $_POST['date']
                        ]);
                    } elseif (in_array('released_to', $columns)) {
                        $stmt = $pdo->prepare("INSERT INTO stock_out (transaction_id, product_id, quantity_released, released_to, purpose, staff_id, date) 
                                               VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $transaction_id,
                            $_POST['product_id'],
                            $_POST['quantity_released'],
                            $_POST['recipient'],
                            $_POST['purpose'] ?? '',
                            $_SESSION['user_id'],
                            $_POST['date']
                        ]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO stock_out (transaction_id, product_id, quantity_released, staff_id, date) 
                                               VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $transaction_id,
                            $_POST['product_id'],
                            $_POST['quantity_released'],
                            $_SESSION['user_id'],
                            $_POST['date']
                        ]);
                    }
                    
                    // Update inventory quantity
                    $updateStmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
                    $updateStmt->execute([$_POST['quantity_released'], $_POST['product_id']]);
                    
                    $pdo->commit();
                    $_SESSION['success_message'] = "Stock-Out recorded successfully!";
                    echo '<script>window.location.href = "?page=dashboard";</script>';
                    exit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error_message'] = "Failed to record stock-out: " . $e->getMessage();
                    echo '<script>window.location.href = "?page=dashboard";</script>';
                    exit();
                }
            } else {
                $_SESSION['error_message'] = "Insufficient stock! Available: " . $current_qty;
                echo '<script>window.location.href = "?page=dashboard";</script>';
                exit();
            }
        }
    }
}

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

$user_name = '';
if (isset($_SESSION['user_name'])) {
    $user_name = $_SESSION['user_name'];
} elseif (isset($_SESSION['username'])) {
    $user_name = $_SESSION['username'];
} elseif (isset($_SESSION['fullname'])) {
    $user_name = $_SESSION['fullname'];
} elseif (isset($_SESSION['name'])) {
    $user_name = $_SESSION['name'];
} else {
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user) {
                $user_name = $user['name'];
                $_SESSION['user_name'] = $user_name;
            }
        } catch (Exception $e) {
            $user_name = 'User';
        }
    } else {
        $user_name = 'User';
    }
}

$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM inventory");
$stats['total_products'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(quantity) as total FROM inventory");
$stats['total_assets'] = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COALESCE(SUM(quantity_added), 0) as total FROM stock_in WHERE DATE(date) = CURDATE()");
$stats['stock_in_today'] = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COALESCE(SUM(quantity_released), 0) as total FROM stock_out WHERE DATE(date) = CURDATE()");
$stats['stock_out_today'] = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
$stats['total_categories'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM suppliers WHERE is_active = 1");
$stats['total_suppliers'] = $stmt->fetch()['total'];

// Fix low stock query - check if low_stock_threshold column exists
try {
    $columns = $pdo->query("SHOW COLUMNS FROM inventory")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('low_stock_threshold', $columns)) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM inventory WHERE quantity <= low_stock_threshold");
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM inventory WHERE quantity <= 10");
    }
    $stats['low_stock'] = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM inventory WHERE quantity <= 10");
    $stats['low_stock'] = $stmt->fetch()['total'];
}

// Monthly stock movement data for selected year
$monthly_data = [];
for ($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity_added), 0) as stock_in FROM stock_in WHERE MONTH(date) = ? AND YEAR(date) = ?");
    $stmt->execute([$i, $selected_year]);
    $monthly_data['stock_in'][] = $stmt->fetch()['stock_in'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity_released), 0) as stock_out FROM stock_out WHERE MONTH(date) = ? AND YEAR(date) = ?");
    $stmt->execute([$i, $selected_year]);
    $monthly_data['stock_out'][] = $stmt->fetch()['stock_out'];
}

// Get available years for dropdown
$stmt = $pdo->query("SELECT DISTINCT YEAR(date) as year FROM stock_in UNION SELECT DISTINCT YEAR(date) as year FROM stock_out ORDER BY year DESC");
$available_years = $stmt->fetchAll();
if (empty($available_years)) {
    $available_years = [['year' => date('Y')]];
}

// Get all products for dropdowns
$products = $pdo->query("SELECT id, product_name, quantity FROM inventory ORDER BY product_name")->fetchAll();
$products_with_stock = $pdo->query("SELECT id, product_name, quantity FROM inventory WHERE quantity > 0 ORDER BY product_name")->fetchAll();

// Get all categories for dropdown
$categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();

// Get all suppliers for dropdown
$suppliers = $pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name")->fetchAll();

// Recent transactions
$stmt = $pdo->query("SELECT si.*, i.product_name 
                     FROM stock_in si 
                     JOIN inventory i ON si.product_id = i.id 
                     ORDER BY si.created_at DESC LIMIT 5");
$recent_stock_in = $stmt->fetchAll();

$stmt = $pdo->query("SELECT so.*, i.product_name 
                     FROM stock_out so 
                     JOIN inventory i ON so.product_id = i.id 
                     ORDER BY so.created_at DESC LIMIT 5");
$recent_stock_out = $stmt->fetchAll();
?>

<!-- REST OF YOUR HTML REMAINS THE SAME FROM HERE -->
<!-- Main Dashboard Layout -->
<div class="container-fluid px-4 py-3">
    
    <!-- Welcome Back Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-card">
                <div class="d-flex align-items-center gap-3">
                    </div>
                    <div>
                        <h4 class="mb-0 fw-semibold">
                            Welcome back, <span class="text-primary"><?php echo htmlspecialchars($user_name); ?></span>!
                        </h4>
                        <p class="text-muted mb-0">
                            Here's what's happening with your inventory today — <?php echo date('l, F j, Y'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert" id="successAlert">
        <i class="bi bi-check-circle me-2"></i> <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert" id="errorAlert">
        <i class="bi bi-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Primary Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 text-uppercase small fw-semibold">Total Products</p>
                        <h2 class="mb-0 fw-bold"><?php echo number_format($stats['total_products']); ?></h2>
                        <small class="text-muted">active items</small>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-box"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 text-uppercase small fw-semibold">Total Assets</p>
                        <h2 class="mb-0 fw-bold"><?php echo number_format($stats['total_assets']); ?></h2>
                        <small class="text-muted">units in stock</small>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-calculator"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 text-uppercase small fw-semibold">Stock-In Today</p>
                        <h2 class="mb-0 fw-bold"><?php echo number_format($stats['stock_in_today']); ?></h2>
                        <small class="text-muted">items received</small>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-arrow-down-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 text-uppercase small fw-semibold">Stock-Out Today</p>
                        <h2 class="mb-0 fw-bold"><?php echo number_format($stats['stock_out_today']); ?></h2>
                        <small class="text-muted">items released</small>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-arrow-up-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-6 col-md-3">
            <a href="index.php?page=categories" class="text-decoration-none">
                <div class="stat-card stat-card-compact text-center">
                    <div class="stat-icon-sm bg-secondary bg-opacity-10 text-secondary mx-auto mb-2">
                        <i class="bi bi-tags"></i>
                    </div>
                    <h4 class="mb-0 fw-bold"><?php echo number_format($stats['total_categories']); ?></h4>
                    <small class="text-muted">Categories</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="index.php?page=suppliers" class="text-decoration-none">
                <div class="stat-card stat-card-compact text-center">
                    <div class="stat-icon-sm bg-info bg-opacity-10 text-info mx-auto mb-2">
                        <i class="bi bi-truck"></i>
                    </div>
                    <h4 class="mb-0 fw-bold"><?php echo number_format($stats['total_suppliers']); ?></h4>
                    <small class="text-muted">Active Suppliers</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="index.php?page=inventory&filter=low_stock" class="text-decoration-none">
                <div class="stat-card stat-card-compact text-center">
                    <div class="stat-icon-sm bg-danger bg-opacity-10 text-danger mx-auto mb-2">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h4 class="mb-0 fw-bold"><?php echo number_format($stats['low_stock']); ?></h4>
                    <small class="text-muted">Low Stock Items</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-card-compact text-center">
                <div class="stat-icon-sm bg-success bg-opacity-10 text-success mx-auto mb-2">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <h4 class="mb-0 fw-bold">₱<?php 
                    $stmt = $pdo->query("SELECT SUM(quantity * unit_price) as total FROM inventory");
                    $total_value = $stmt->fetch()['total'] ?? 0;
                    echo number_format($total_value);
                ?></h4>
                <small class="text-muted">Total Value</small>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0 fw-semibold">Stock Movement Trends</h5>
                        <p class="text-muted small mb-0">Monthly comparison of stock in vs stock out</p>
                    </div>
                    <div class="d-flex gap-2">
                        <select id="yearSelect" class="form-select form-select-sm" style="width: auto;">
                            <?php foreach ($available_years as $year): ?>
                                <option value="<?php echo $year['year']; ?>" <?php echo $selected_year == $year['year'] ? 'selected' : ''; ?>>
                                    Year <?php echo $year['year']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="refreshChart()" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-arrow-repeat"></i> Refresh
                        </button>
                    </div>
                </div>
                <canvas id="stockMovementChart" height="280" style="max-height: 320px;"></canvas>
            </div>
        </div>
        
        <!-- Quick Actions Card -->
        <div class="col-lg-4">
            <div class="stat-card h-100 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0 fw-semibold">Quick Actions</h5>
                        <p class="text-muted small mb-0">Frequently used operations</p>
                    </div>
                    <i class="bi bi-lightning-charge-fill text-warning fs-4"></i>
                </div>
                
                <div class="d-flex flex-column gap-3 flex-grow-1">
                    <button type="button" class="quick-action-btn text-decoration-none bg-transparent border-0 w-100 text-start" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light bg-opacity-50 transition-hover">
                            <div class="bg-primary bg-opacity-10 p-2 rounded-3">
                                <i class="bi bi-plus-lg text-primary fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0 fw-semibold">Add New Product</h6>
                                <small class="text-muted">Create a new inventory item</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </div>
                    </button>
                    
                    <button type="button" class="quick-action-btn text-decoration-none bg-transparent border-0 w-100 text-start" data-bs-toggle="modal" data-bs-target="#stockInModal">
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light bg-opacity-50 transition-hover">
                            <div class="bg-success bg-opacity-10 p-2 rounded-3">
                                <i class="bi bi-arrow-down-circle text-success fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0 fw-semibold">Record Stock-In</h6>
                                <small class="text-muted">Receive products from suppliers</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </div>
                    </button>
                    
                    <button type="button" class="quick-action-btn text-decoration-none bg-transparent border-0 w-100 text-start" data-bs-toggle="modal" data-bs-target="#stockOutModal">
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light bg-opacity-50 transition-hover">
                            <div class="bg-danger bg-opacity-10 p-2 rounded-3">
                                <i class="bi bi-arrow-up-circle text-danger fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0 fw-semibold">Record Stock-Out</h6>
                                <small class="text-muted">Release products to departments</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity Section -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0 fw-semibold">Recent Stock-In</h5>
                        <p class="text-muted small mb-0">Latest incoming transactions</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Product</th><th>Quantity</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_stock_in as $item): ?>
                            <tr>
                                <td class="fw-medium"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><span class="badge bg-success bg-opacity-10 text-success px-3 py-2">+<?php echo number_format($item['quantity_added']); ?></span></td>
                                <td><small><?php echo date('M d, Y', strtotime($item['date'])); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_stock_in)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No recent stock-in records</td>?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0 fw-semibold">Recent Stock-Out</h5>
                        <p class="text-muted small mb-0">Latest outgoing transactions</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Product</th><th>Quantity</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_stock_out as $item): ?>
                            <tr>
                                <td class="fw-medium"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2">-<?php echo number_format($item['quantity_released']); ?></span></td>
                                <td><small><?php echo date('M d, Y', strtotime($item['date'])); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_stock_out)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No recent stock-out records</td>?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Product</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Product Name *</label>
                            <input type="text" name="product_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Asset Code</label>
                            <input type="text" name="asset_code" class="form-control" placeholder="Auto-generated if empty">
                            <small class="text-muted">Optional unique identifier</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Category *</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Supplier *</label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">-- Select Supplier --</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Quantity *</label>
                            <input type="number" name="quantity" class="form-control" required min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Unit Price *</label>
                            <input type="number" name="unit_price" class="form-control" required min="0" step="0.01">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Product description..."></textarea>
                    </div>
                    <input type="hidden" name="action" value="add_product">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stock In Modal -->
<div class="modal fade" id="stockInModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-arrow-down-circle me-2"></i>Record Stock-In</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Product *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>">
                                    <?php echo htmlspecialchars($product['product_name']); ?> (Current: <?php echo $product['quantity']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Quantity *</label>
                            <input type="number" name="quantity_added" class="form-control" required min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Date *</label>
                            <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reference Number</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="PO #, OR #, etc.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Optional remarks..."></textarea>
                    </div>
                    <input type="hidden" name="staff_id" value="<?php echo $_SESSION['user_id']; ?>">
                    <input type="hidden" name="action" value="add_stock_in">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Stock-In</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stock Out Modal -->
<div class="modal fade" id="stockOutModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="stockOutForm">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-arrow-up-circle me-2"></i>Record Stock-Out</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Product *</label>
                        <select name="product_id" class="form-select" id="stockOutProductSelect" required>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products_with_stock as $product): ?>
                                <option value="<?php echo $product['id']; ?>" data-stock="<?php echo $product['quantity']; ?>">
                                    <?php echo htmlspecialchars($product['product_name']); ?> (Available: <?php echo $product['quantity']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Quantity *</label>
                            <input type="number" name="quantity_released" id="stockOutQuantityInput" class="form-control" required min="1">
                            <small class="text-muted" id="quantityWarningMsg"></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Date *</label>
                            <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Recipient / Department *</label>
                        <input type="text" name="recipient" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Purpose</label>
                        <textarea name="purpose" class="form-control" rows="2" placeholder="Purpose of stock-out..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Optional remarks..."></textarea>
                    </div>
                    <input type="hidden" name="staff_id" value="<?php echo $_SESSION['user_id']; ?>">
                    <input type="hidden" name="action" value="add_stock_out">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Record Stock-Out</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.welcome-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 20px;
    padding: 1.5rem 1.75rem;
    border: 1px solid rgba(0, 0, 0, 0.05);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.welcome-avatar { transition: all 0.2s ease; }
.welcome-avatar:hover { transform: scale(1.05); background-color: rgba(13, 110, 253, 0.15) !important; }
.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.25rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 4px 16px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
    height: 100%;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); }
.stat-card-compact {
    padding: 1rem;
    background: white;
    border-radius: 16px;
    transition: all 0.2s ease;
    height: 100%;
}
.stat-card-compact:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
.stat-icon { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 16px; font-size: 24px; }
.stat-icon-sm { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 20px; margin: 0 auto; }
.quick-action-btn .transition-hover { transition: all 0.2s ease; cursor: pointer; }
.quick-action-btn .transition-hover:hover { background-color: rgba(13, 110, 253, 0.08) !important; transform: translateX(4px); }
.table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; }
.badge { font-weight: 500; letter-spacing: 0.3px; }
@media (max-width: 768px) {
    .stat-card { padding: 1rem; }
    .stat-card h2 { font-size: 1.5rem; }
    .welcome-card { padding: 1.25rem; }
    .welcome-avatar { width: 48px !important; height: 48px !important; }
    .welcome-avatar i { font-size: 1.75rem !important; }
    .quick-action-btn .transition-hover { padding: 0.75rem !important; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
let stockMovementChart;

function initializeChart() {
    const ctx = document.getElementById('stockMovementChart').getContext('2d');
    const stockInData = <?php echo json_encode($monthly_data['stock_in']); ?>;
    const stockOutData = <?php echo json_encode($monthly_data['stock_out']); ?>;
    
    stockMovementChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [
                { label: 'Stock-In', data: stockInData, backgroundColor: '#10b981', borderRadius: 8, borderWidth: 0, barPercentage: 0.7 },
                { label: 'Stock-Out', data: stockOutData, backgroundColor: '#ef4444', borderRadius: 8, borderWidth: 0, barPercentage: 0.7 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top', labels: { font: { size: 12 }, usePointStyle: true, boxWidth: 8 } },
                tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', padding: 10, cornerRadius: 8 }
            },
            scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: function(v) { return v.toLocaleString(); } } }, x: { grid: { display: false } } }
        }
    });
}

function refreshChart() {
    const year = document.getElementById('yearSelect').value;
    window.location.href = '?page=dashboard&year=' + year;
}

// Stock validation for Stock Out form
function initializeStockValidation() {
    const productSelect = document.getElementById('stockOutProductSelect');
    const quantityInput = document.getElementById('stockOutQuantityInput');
    const warningSpan = document.getElementById('quantityWarningMsg');
    
    if (productSelect && quantityInput) {
        productSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const maxStock = selectedOption.getAttribute('data-stock');
            if (maxStock) {
                quantityInput.max = maxStock;
                quantityInput.placeholder = 'Max: ' + maxStock;
            }
            validateQuantity();
        });
        
        quantityInput.addEventListener('input', validateQuantity);
        
        function validateQuantity() {
            const max = parseInt(quantityInput.max);
            const value = parseInt(quantityInput.value);
            if (isNaN(value) || value <= 0) {
                warningSpan.innerHTML = '<span class="text-warning">Please enter a valid quantity.</span>';
                quantityInput.style.borderColor = '#ffc107';
                return false;
            } else if (value > max) {
                warningSpan.innerHTML = '<span class="text-danger">⚠️ Quantity exceeds available stock! Only ' + max + ' available.</span>';
                quantityInput.style.borderColor = '#dc3545';
                return false;
            } else {
                warningSpan.innerHTML = '<span class="text-success">✓ Stock available: ' + max + ' units</span>';
                quantityInput.style.borderColor = '#28a745';
                return true;
            }
        }
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const successAlert = document.getElementById('successAlert');
    const errorAlert = document.getElementById('errorAlert');
    if (successAlert) successAlert.style.display = 'none';
    if (errorAlert) errorAlert.style.display = 'none';
}, 5000);

document.addEventListener('DOMContentLoaded', function() {
    initializeChart();
    initializeStockValidation();
});
</script>