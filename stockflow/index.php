<?php
// File: index.php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: #f3f4f6;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            background: white;
        }
        
        /* Logo wrapper - logo ABOVE text */
        .logo-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            margin-bottom: 5px;
        }
        
        .sidebar-logo-img {
            width: 60px;
            height: 60px;
            object-fit: contain;
            filter: drop-shadow(0 0 5px rgba(14, 75, 168, 0.5));
            animation: logoGlow 3s infinite ease-in-out;
        }
        
        @keyframes logoGlow {
            0%, 100% {
                filter: drop-shadow(0 0 3px rgba(14, 75, 168, 0.3));
            }
            50% {
                filter: drop-shadow(0 0 10px rgba(14, 75, 168, 0.6));
            }
        }
        
        .sidebar-logo-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #0e4ba8, #1e6bd8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            animation: logoGlow 3s infinite ease-in-out;
        }
        
        .sidebar-brand {
            line-height: 1.2;
            text-align: center;
        }
        
        .sidebar-brand h3 {
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0;
        }
        
        .sidebar-brand .stock {
            color: black;
        }
        
        .sidebar-brand .flow {
            color: #0e4ba8;
            text-shadow: 0 0 8px rgba(14, 75, 168, 0.5);
            font-weight: 800;
        }
        
        .sidebar-header p {
            font-size: 0.7rem;
            opacity: 0.7;
            margin: 0;
            margin-top: 5px;
            color: #94a3b8;
        }
        
        .nav-menu {
            padding: 1.5rem 0;
        }
        
        .nav-section {
            margin-bottom: 1.5rem;
        }
        
        .nav-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.5;
            font-weight: 600;
        }
        
        .nav-item {
            padding: 0.5rem 1.5rem;
            margin: 0.25rem 0;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #cbd5e1;
        }
        
        .nav-item:hover, .nav-item.active {
            background: rgba(14, 75, 168, 0.2);
            color: white;
            border-left: 3px solid #0e4ba8;
        }
        
        .nav-item i {
            font-size: 1.25rem;
            width: 24px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        
        /* Top Navbar */
        .top-navbar {
            background: white;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #0e4ba8, #1e6bd8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        /* Cards */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: none;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        /* Tables */
        .data-table {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .data-table th {
            background: #f8fafc;
            padding: 1rem;
            font-weight: 600;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .data-table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        
        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, #0e4ba8, #1e6bd8);
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 10px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(14, 75, 168, 0.3);
        }
        
        /* Badges */
        .badge-success {
            background: #10b981;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            color: white;
        }
        
        .badge-warning {
            background: #f59e0b;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            color: white;
        }
        
        .badge-danger {
            background: #ef4444;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            color: white;
        }
        
        /* Modal */
        .modal-content {
            border-radius: 20px;
            border: none;
        }
        
        .modal-header {
            border-bottom: 1px solid #e2e8f0;
            padding: 1.5rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease;
        }
        
        /* Toast Notifications */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
        }
        
        .breadcrumb-item a {
            color: #0e4ba8;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <!-- Logo ABOVE STOCKFLOW text -->
            <div class="logo-wrapper">
                <img src="images/logo3.png" alt="Stockflow Logo" class="sidebar-logo-img" 
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="sidebar-logo-icon" style="display: none;">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
                <div class="sidebar-brand">
                    <h3>
                        <span class="stock">STOCK</span><span class="flow">FLOW</span>
                    </h3>
                </div>
            </div>
            <p style="color:black;">Inventory Management System</p>
        </div>
        
        <div class="nav-menu">
            <div class="nav-section">
                <div class="nav-section-title">OVERVIEW</div>
                <div class="nav-item <?php echo $page == 'dashboard' ? 'active' : ''; ?>" data-page="dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </div>
                <div class="nav-item <?php echo $page == 'inventory' ? 'active' : ''; ?>" data-page="inventory">
                    <i class="bi bi-box-seam"></i>
                    <span>Inventory</span>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">TRANSACTIONS</div>
                <div class="nav-item <?php echo $page == 'stock_in' ? 'active' : ''; ?>" data-page="stock_in">
                    <i class="bi bi-arrow-down-circle"></i>
                    <span>Stock-In</span>
                </div>
                <div class="nav-item <?php echo $page == 'stock_out' ? 'active' : ''; ?>" data-page="stock_out">
                    <i class="bi bi-arrow-up-circle"></i>
                    <span>Stock-Out</span>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">MANAGEMENT</div>
                <div class="nav-item <?php echo $page == 'suppliers' ? 'active' : ''; ?>" data-page="suppliers">
                    <i class="bi bi-truck"></i>
                    <span>Suppliers</span>
                </div>
                <div class="nav-item <?php echo $page == 'categories' ? 'active' : ''; ?>" data-page="categories">
                    <i class="bi bi-tags"></i>
                    <span>Categories</span>
                </div>
                
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">SYSTEM</div>
                <div class="nav-item" data-page="logout">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <div>
                <h4 class="page-title"><?php echo ucfirst(str_replace('_', ' ', $page)); ?></h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Stockflow</a></li>
                        <li class="breadcrumb-item active"><?php echo ucfirst(str_replace('_', ' ', $page)); ?></li>
                    </ol>
                </nav>
            </div>
            <div class="user-info">
                <i class="bi bi-bell fs-5" style="cursor: pointer;" onclick="location.href='index.php?page=notifications'"></i>
                <div class="user-avatar">
                    <?php 
                    $name = $_SESSION['name'] ?? 'Maria Cruz';
                    $initials = strtoupper(substr($name, 0, 1) . substr(strrchr($name, ' '), 1, 1));
                    echo $initials;
                    ?>
                </div>
                <div>
                    <strong><?php echo $_SESSION['name'] ?? 'Maria Cruz'; ?></strong><br>
                    <small class="text-muted"><?php echo $_SESSION['role'] ?? 'Administrator'; ?></small>
                </div>
            </div>
        </div>
        
        <div id="page-content" class="fade-in">
            <?php
            $page_file = $page . '.php';
            if (file_exists($page_file)) {
                include $page_file;
            } else {
                include 'dashboard.php';
            }
            ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navigation
        document.querySelectorAll('.nav-item[data-page]').forEach(item => {
            item.addEventListener('click', function() {
                const page = this.dataset.page;
                if (page === 'logout') {
                    window.location.href = 'logout.php';
                } else {
                    window.location.href = `index.php?page=${page}`;
                }
            });
        });
        
        // Real-time updates using AJAX polling (every 5 seconds)
        let lastUpdateTime = <?php echo time(); ?>;
        
        function checkForUpdates() {
            $.ajax({
                url: 'ajax_check_updates.php',
                method: 'GET',
                data: { last_update: lastUpdateTime },
                success: function(response) {
                    if (response.has_update) {
                        showToast(response.message, response.type);
                        lastUpdateTime = response.timestamp;
                        // Reload current page content if needed
                        if (response.reload_needed) {
                            location.reload();
                        }
                    }
                }
            });
        }
        
        // Check for updates every 5 seconds
        setInterval(checkForUpdates, 5000);
        
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast-notification alert alert-${type === 'success' ? 'success' : 'warning'} shadow-lg`;
            toast.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} fs-4 me-2"></i>
                    <div>${message}</div>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        // Search functionality
        function searchTable(inputId, tableId) {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('keyup', function() {
                    const filter = this.value.toLowerCase();
                    const table = document.getElementById(tableId);
                    if (table) {
                        const rows = table.getElementsByTagName('tr');
                        for (let i = 1; i < rows.length; i++) {
                            const cells = rows[i].getElementsByTagName('td');
                            let found = false;
                            for (let j = 0; j < cells.length; j++) {
                                if (cells[j] && cells[j].innerText.toLowerCase().indexOf(filter) > -1) {
                                    found = true;
                                    break;
                                }
                            }
                            rows[i].style.display = found ? '' : 'none';
                        }
                    }
                });
            }
        }
        
        // Export to CSV function
        function exportToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let row of rows) {
                const rowData = [];
                const cols = row.querySelectorAll('td, th');
                for (let col of cols) {
                    rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
                }
                csv.push(rowData.join(','));
            }
            
            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        // Check if logo loaded, if not show fallback
        document.addEventListener('DOMContentLoaded', function() {
            const logoImg = document.querySelector('.sidebar-logo-img');
            const fallbackIcon = document.querySelector('.sidebar-logo-icon');
            
            if (logoImg && logoImg.complete && logoImg.naturalHeight === 0) {
                logoImg.style.display = 'none';
                if (fallbackIcon) fallbackIcon.style.display = 'flex';
            }
        });
    </script>
</body>
</html>