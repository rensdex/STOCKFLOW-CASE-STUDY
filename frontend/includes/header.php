<?php
if (!isset($skip_auth_check) && !isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

$base_path = ''; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stockflow - Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Use absolute path from root -->
    <link href="/STOCKFLOW-CASE-STUDY/frontend/assets/css/style.css" rel="stylesheet">
    
    <style>
        /* Fallback styles in case CSS doesn't load */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        body {
            background: #f1f5f9;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        .sidebar-brand h3 {
            font-weight: 700;
            font-size: 1.5rem;
            color: white;
        }
        .sidebar-brand .stock { color: #f1f5f9; }
        .sidebar-brand .flow { color: #38bdf8; }
        .nav-item {
            padding: 0.6rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #cbd5e1;
            text-decoration: none;
        }
        .nav-item:hover, .nav-item.active {
            background: rgba(59, 130, 246, 0.2);
            color: white;
        }
        .main-content {
            margin-left: 280px;
            padding: 1.5rem;
            min-height: 100vh;
        }
        .top-navbar {
            background: white;
            border-radius: 16px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #0f172a;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .user-avatar {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .welcome-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 20px;
            padding: 1.5rem 1.75rem;
            color: white;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            height: 100%;
        }
        .stat-card-compact {
            background: white;
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
            border: 1px solid #e2e8f0;
            height: 100%;
            text-decoration: none;
            display: block;
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 12px;
            color: white;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-wrapper">
                <div class="sidebar-brand">
                    <h3>
                        
                        <span class="stock">STOCK</span><span class="flow">FLOW</span>
                    </h3>
                </div>
            </div>
            <p style="color: #94a3b8; font-size: 0.7rem;">Inventory Management System</p>
        </div>
        
        <div class="nav-menu">
    <div class="nav-section">
        <div class="nav-section-title">OVERVIEW</div>
        <a href="index.php?page=dashboard" class="nav-item <?php echo ($_GET['page'] ?? 'dashboard') == 'dashboard' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a href="index.php?page=inventory" class="nav-item <?php echo ($_GET['page'] ?? '') == 'inventory' ? 'active' : ''; ?>">
            <i class="bi bi-box-seam"></i>
            <span>Inventory</span>
        </a>
    </div>
    
    <div class="nav-section">
        <div class="nav-section-title">TRANSACTIONS</div>
        <a href="index.php?page=stock_in" class="nav-item <?php echo ($_GET['page'] ?? '') == 'stock_in' ? 'active' : ''; ?>">
            <i class="bi bi-arrow-down-circle"></i>
            <span>Stock-In</span>
        </a>
        <a href="index.php?page=stock_out" class="nav-item <?php echo ($_GET['page'] ?? '') == 'stock_out' ? 'active' : ''; ?>">
            <i class="bi bi-arrow-up-circle"></i>
            <span>Stock-Out</span>
        </a>
    </div>
    
    <div class="nav-section">
        <div class="nav-section-title">MANAGEMENT</div>
        <a href="index.php?page=suppliers" class="nav-item <?php echo ($_GET['page'] ?? '') == 'suppliers' ? 'active' : ''; ?>">
            <i class="bi bi-truck"></i>
            <span>Suppliers</span>
        </a>
        <a href="index.php?page=categories" class="nav-item <?php echo ($_GET['page'] ?? '') == 'categories' ? 'active' : ''; ?>">
            <i class="bi bi-tags"></i>
            <span>Categories</span>
        </a>
        <a href="index.php?page=users" class="nav-item <?php echo ($_GET['page'] ?? '') == 'users' ? 'active' : ''; ?>">
            <i class="bi bi-people"></i>
            <span>Users</span>
        </a>
        <a href="index.php?page=audit_logs" class="nav-item <?php echo ($_GET['page'] ?? '') == 'audit_logs' ? 'active' : ''; ?>">
            <i class="bi bi-journal-text"></i>
            <span>Audit Logs</span>
        </a>
    </div>
    
    <div class="nav-section">
        <div class="nav-section-title">SYSTEM</div>
        <a href="backend/auth/logout.php" class="nav-item">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </div>
</div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <div>
                <h4 class="page-title"><?php echo ucfirst(str_replace('_', ' ', $_GET['page'] ?? 'Dashboard')); ?></h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Stockflow</a></li>
                        <li class="breadcrumb-item active"><?php echo ucfirst(str_replace('_', ' ', $_GET['page'] ?? 'Dashboard')); ?></li>
                    </ol>
                </nav>
            </div>
            <div class="user-info">
                <i class="bi bi-bell fs-5" style="cursor: pointer;" onclick="location.href='index.php?page=notifications'"></i>
                <div class="user-avatar">
                    <?php 
                    $name = $_SESSION['name'] ?? 'User';
                    $initials = strtoupper(substr($name, 0, 1) . (strpos($name, ' ') ? substr(strrchr($name, ' '), 1, 1) : ''));
                    echo $initials;
                    ?>
                </div>
                <div>
                    <strong><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Viewer'); ?></small>
                </div>
            </div>
        </div>
        
        <div id="page-content" class="fade-in">