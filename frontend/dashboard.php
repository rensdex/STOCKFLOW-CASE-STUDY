<?php

$stmt = $pdo->query("SELECT COUNT(*) as total FROM school_supplies");
$stats['total_supplies'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(quantity) as total FROM school_supplies");
$stats['total_units'] = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM categories WHERE is_active = 1");
$stats['total_categories'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM school_supplies WHERE quantity <= low_stock_threshold");
$stats['low_stock'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(quantity * unit_price) as total FROM school_supplies");
$stats['total_value'] = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM suppliers WHERE is_active = 1");
$stats['total_suppliers'] = $stmt->fetch()['total'];

// Recent Stock-In 
$stmt = $pdo->query("SELECT si.*, s.supply_name 
                     FROM stock_in si 
                     JOIN school_supplies s ON si.supply_id = s.id 
                     ORDER BY si.created_at DESC LIMIT 5");
$recent_stock_in = $stmt->fetchAll();

// Recent Stock-Out 
$stmt = $pdo->query("SELECT so.*, s.supply_name 
                     FROM stock_out so 
                     JOIN school_supplies s ON so.supply_id = s.id 
                     ORDER BY so.created_at DESC LIMIT 5");
$recent_stock_out = $stmt->fetchAll();

// Get low stock items
$lowStockItems = $pdo->query("SELECT supply_name, quantity, low_stock_threshold 
                              FROM school_supplies 
                              WHERE quantity <= low_stock_threshold 
                              LIMIT 5")->fetchAll();

// Total items issued
$totalIssued = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM stock_out")->fetchColumn();
?>

<div class="container-fluid px-4 py-3">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-card">
                <h4 class="mb-2">School Supply Inventory System</h4>
                <p class="mb-0">Manage, track, and monitor all school supplies efficiently</p>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1">Total Supplies</p>
                        <h2 class="mb-0"><?php echo $stats['total_supplies']; ?></h2>
                        <small>different items</small>
                    </div>
                    <div class="stat-icon"><i class="bi bi-backpack"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1">Total Units</p>
                        <h2 class="mb-0"><?php echo number_format($stats['total_units']); ?></h2>
                        <small>items in stock</small>
                    </div>
                    <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1">Categories</p>
                        <h2 class="mb-0"><?php echo $stats['total_categories']; ?></h2>
                        <small>supply types</small>
                    </div>
                    <div class="stat-icon"><i class="bi bi-tags"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1">Low Stock Alert</p>
                        <h2 class="mb-0 <?php echo $stats['low_stock'] > 0 ? 'text-warning' : ''; ?>"><?php echo $stats['low_stock']; ?></h2>
                        <small>needs reorder</small>
                    </div>
                    <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- More Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card text-center">
                <i class="bi bi-currency-dollar fs-1 text-success"></i>
                <h5 class="mt-2">Total Value</h5>
                <h3>₱<?php echo number_format($stats['total_value']); ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center">
                <i class="bi bi-truck fs-1 text-info"></i>
                <h5 class="mt-2">Active Suppliers</h5>
                <h3><?php echo $stats['total_suppliers']; ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center">
                <i class="bi bi-people fs-1 text-primary"></i>
                <h5 class="mt-2">Items Issued</h5>
                <h3><?php echo number_format($totalIssued); ?></h3>
            </div>
        </div>
    </div>

    <!-- Low Stock Warning -->
    <?php if (!empty($lowStockItems)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning border-0 shadow-sm">
                <h5><i class="bi bi-exclamation-triangle-fill"></i> Low Stock Alert!</h5>
                <ul class="mb-0">
                    <?php foreach ($lowStockItems as $item): ?>
                    <li><?php echo escape($item['supply_name']); ?> - Only <?php echo $item['quantity']; ?> units left (Threshold: <?php echo $item['low_stock_threshold']; ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card text-center">
                <i class="bi bi-plus-circle fs-1 text-primary"></i>
                <h5 class="mt-2">Add New Supply</h5>
                <a href="index.php?page=inventory" class="btn btn-primary-custom btn-sm mt-2">Add Item</a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center">
                <i class="bi bi-arrow-down-circle fs-1 text-success"></i>
                <h5 class="mt-2">Receive Stock</h5>
                <a href="index.php?page=stock_in" class="btn btn-success btn-sm mt-2">Record Stock-In</a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center">
                 <i class="bi bi-box-seam fs-1 text-secondary"></i>
                <h5 class="mt-2">Inventory</h5>
                <a href="inventory.php?page=inventory" class="btn btn-secondary btn-sm mt-2">View</a>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="stat-card">
                <h5>Recent Stock Received</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Item</th><th>Qty</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_stock_in as $item): ?>
                            <tr>
                                <td><?php echo escape($item['supply_name']); ?></td>
                                <td><span class="badge bg-success">+<?php echo $item['quantity']; ?></span></td>
                                <td><?php echo date('M d', strtotime($item['date_received'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_stock_in)): ?>
                            <tr><td colspan="3" class="text-center text-muted">No records</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <h5>Recent Items Issued</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Item</th><th>Qty</th><th>Issued To</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_stock_out as $item): ?>
                            <tr>
                                <td><?php echo escape($item['supply_name']); ?></td>
                                <td><span class="badge bg-danger">-<?php echo $item['quantity']; ?></span></td>
                                <td><?php echo escape(substr($item['issued_to'], 0, 20)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_stock_out)): ?>
                            <tr><td colspan="3" class="text-center text-muted">No records</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.badge-success { background: #10b981; padding: 4px 10px; border-radius: 30px; font-size: 12px; color: white; }
.badge-danger { background: #ef4444; padding: 4px 10px; border-radius: 30px; font-size: 12px; color: white; }
.stat-icon { width: 52px; height: 52px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background: rgba(59,130,246,0.1); }
.bg-primary { background: rgba(59,130,246,0.1); color: #3b82f6; }
.bg-success { background: rgba(16,185,129,0.1); color: #10b981; }
.bg-info { background: rgba(6,182,212,0.1); color: #06b6d4; }
.bg-warning { background: rgba(245,158,11,0.1); color: #f59e0b; }
</style>