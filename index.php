<?php
ob_start();
require_once 'backend/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

$allowed_pages = ['dashboard', 'inventory', 'categories', 'suppliers', 'stock_in', 'stock_out', 'users', 'audit_logs', 'notifications'];
$page = in_array($page, $allowed_pages) ? $page : 'dashboard';
?>

<?php include 'frontend/includes/header.php'; ?>

<?php
$page_file = 'frontend/' . $page . '.php';
if (file_exists($page_file)) {
    include $page_file;
} else {
    include 'frontend/dashboard.php';
}
?>

<?php include 'frontend/includes/footer.php'; ?>
<?php ob_end_flush(); ?>