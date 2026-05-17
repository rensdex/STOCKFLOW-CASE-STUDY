<?php
ob_start();
require_once 'backend/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Process POST requests for stock_in BEFORE including header
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Handle Stock-In POST requests
if ($page === 'stock_in' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Stock-In ADD
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid security token';
            header("Location: index.php?page=stock_in");
            exit();
        }
        
        $transaction_no = 'SI-' . date('Ymd') . '-' . rand(1000, 9999);
        
        $pdo->beginTransaction();
        
        try {
            $stmt = $pdo->prepare("INSERT INTO stock_in (transaction_no, supply_id, supplier_id, quantity, remarks, received_by, date_received) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
            $stmt->execute([$transaction_no, $_POST['supply_id'], $_POST['supplier_id'], $_POST['quantity'], $_POST['notes'] ?? '', $_SESSION['user_id']]);
            
            $updateStmt = $pdo->prepare("UPDATE school_supplies SET quantity = quantity + ? WHERE id = ?");
            $updateStmt->execute([$_POST['quantity'], $_POST['supply_id']]);
            
            $statusStmt = $pdo->prepare("UPDATE school_supplies SET status = CASE 
                WHEN quantity <= 0 THEN 'Out of Stock'
                WHEN quantity <= low_stock_threshold THEN 'Low Stock'
                ELSE 'In Stock'
            END WHERE id = ?");
            $statusStmt->execute([$_POST['supply_id']]);
            
            $productStmt = $pdo->prepare("SELECT supply_name FROM school_supplies WHERE id = ?");
            $productStmt->execute([$_POST['supply_id']]);
            $supply = $productStmt->fetch();
            
            $notifStmt = $pdo->prepare("INSERT INTO notifications (title, message, type) VALUES (?, ?, 'success')");
            $notifStmt->execute(['📦 Stock Received', $_POST['quantity'] . ' units of ' . $supply['supply_name'] . ' added to inventory']);
            
            triggerStockUpdate('stock_in', $supply['supply_name'], $_POST['quantity']);
            triggerRealtimeNotification('Stock Received', $_POST['quantity'] . ' units of ' . $supply['supply_name'] . ' added to inventory', 'success');
            
            $pdo->commit();
            
            $_SESSION['flash_success'] = "Stock-In recorded successfully and inventory updated";
            header("Location: index.php?page=stock_in");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Error: " . $e->getMessage();
            header("Location: index.php?page=stock_in");
            exit();
        }
    }
    
    // Handle Stock-In EDIT
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid security token';
            header("Location: index.php?page=stock_in");
            exit();
        }
        
        $pdo->beginTransaction();
        
        try {
            $oldStmt = $pdo->prepare("SELECT quantity, supply_id FROM stock_in WHERE id = ?");
            $oldStmt->execute([$_POST['id']]);
            $oldData = $oldStmt->fetch();
            
            if (!$oldData) {
                throw new Exception("Stock-in record not found");
            }
            
            $stmt = $pdo->prepare("UPDATE stock_in SET supplier_id = ?, quantity = ?, remarks = ? WHERE id = ?");
            $stmt->execute([$_POST['supplier_id'], $_POST['quantity'], $_POST['notes'], $_POST['id']]);
            
            $quantityDiff = $_POST['quantity'] - $oldData['quantity'];
            $updateStmt = $pdo->prepare("UPDATE school_supplies SET quantity = quantity + ? WHERE id = ?");
            $updateStmt->execute([$quantityDiff, $oldData['supply_id']]);
            
            $statusStmt = $pdo->prepare("UPDATE school_supplies SET status = CASE 
                WHEN quantity <= 0 THEN 'Out of Stock'
                WHEN quantity <= low_stock_threshold THEN 'Low Stock'
                ELSE 'In Stock'
            END WHERE id = ?");
            $statusStmt->execute([$oldData['supply_id']]);
            
            $pdo->commit();
            
            $_SESSION['flash_success'] = "Stock-In updated successfully";
            header("Location: index.php?page=stock_in");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = $e->getMessage();
            header("Location: index.php?page=stock_in");
            exit();
        }
    }
    
    // Handle Stock-In DELETE
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_error'] = 'Invalid security token';
            header("Location: index.php?page=stock_in");
            exit();
        }
        
        $pdo->beginTransaction();
        
        try {
            $stmt = $pdo->prepare("SELECT quantity, supply_id FROM stock_in WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $record = $stmt->fetch();
            
            if ($record) {
                $updateStmt = $pdo->prepare("UPDATE school_supplies SET quantity = quantity - ? WHERE id = ?");
                $updateStmt->execute([$record['quantity'], $record['supply_id']]);
                
                $statusStmt = $pdo->prepare("UPDATE school_supplies SET status = CASE 
                    WHEN quantity <= 0 THEN 'Out of Stock'
                    WHEN quantity <= low_stock_threshold THEN 'Low Stock'
                    ELSE 'In Stock'
                END WHERE id = ?");
                $statusStmt->execute([$record['supply_id']]);
                
                $deleteStmt = $pdo->prepare("DELETE FROM stock_in WHERE id = ?");
                $deleteStmt->execute([$_POST['id']]);
                
                $pdo->commit();
                
                $_SESSION['flash_success'] = "Stock-In record deleted and inventory adjusted";
                header("Location: index.php?page=stock_in");
                exit();
            } else {
                throw new Exception("Record not found");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = $e->getMessage();
            header("Location: index.php?page=stock_in");
            exit();
        }
    }
}

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