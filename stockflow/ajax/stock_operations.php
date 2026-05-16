<?php
// File: ajax/stock_operations.php
session_start();
require_once '../config/database.php'; // Adjust path as needed

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$response = ['success' => false, 'message' => 'Invalid action'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_stock_in') {
        $product_id = $_POST['product_id'] ?? 0;
        $quantity = $_POST['quantity_added'] ?? 0;
        $date = $_POST['date'] ?? date('Y-m-d');
        $reference_no = $_POST['reference_no'] ?? '';
        $remarks = $_POST['remarks'] ?? '';
        $staff_id = $_POST['staff_id'] ?? $_SESSION['user_id'];
        
        if ($product_id && $quantity > 0) {
            try {
                $pdo->beginTransaction();
                
                // Insert stock in record
                $stmt = $pdo->prepare("INSERT INTO stock_in (product_id, quantity_added, date, reference_no, remarks, staff_id, created_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$product_id, $quantity, $date, $reference_no, $remarks, $staff_id]);
                
                // Update inventory quantity
                $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$quantity, $product_id]);
                
                // Update stock status
                $stmt = $pdo->prepare("UPDATE inventory SET status = CASE 
                    WHEN quantity <= low_stock_threshold THEN 'Low Stock'
                    WHEN quantity = 0 THEN 'Out of Stock'
                    ELSE 'In Stock'
                END WHERE id = ?");
                $stmt->execute([$product_id]);
                
                $pdo->commit();
                $response = ['success' => true, 'message' => 'Stock-In recorded successfully'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => 'Invalid product or quantity'];
        }
    } 
    elseif ($action === 'add_stock_out') {
        $product_id = $_POST['product_id'] ?? 0;
        $quantity = $_POST['quantity_released'] ?? 0;
        $date = $_POST['date'] ?? date('Y-m-d');
        $recipient = $_POST['recipient'] ?? '';
        $purpose = $_POST['purpose'] ?? '';
        $remarks = $_POST['remarks'] ?? '';
        $staff_id = $_POST['staff_id'] ?? $_SESSION['user_id'];
        
        if ($product_id && $quantity > 0) {
            try {
                // Check if enough stock
                $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ?");
                $stmt->execute([$product_id]);
                $current_stock = $stmt->fetchColumn();
                
                if ($current_stock < $quantity) {
                    $response = ['success' => false, 'message' => 'Insufficient stock available'];
                    echo json_encode($response);
                    exit;
                }
                
                $pdo->beginTransaction();
                
                // Insert stock out record
                $stmt = $pdo->prepare("INSERT INTO stock_out (product_id, quantity_released, date, recipient, purpose, remarks, staff_id, created_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$product_id, $quantity, $date, $recipient, $purpose, $remarks, $staff_id]);
                
                // Update inventory quantity
                $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$quantity, $product_id]);
                
                // Update stock status
                $stmt = $pdo->prepare("UPDATE inventory SET status = CASE 
                    WHEN quantity <= low_stock_threshold THEN 'Low Stock'
                    WHEN quantity = 0 THEN 'Out of Stock'
                    ELSE 'In Stock'
                END WHERE id = ?");
                $stmt->execute([$product_id]);
                
                $pdo->commit();
                $response = ['success' => true, 'message' => 'Stock-Out recorded successfully'];
            } catch (Exception $e) {
                $pdo->rollBack();
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => 'Invalid product or quantity'];
        }
    }
}

echo json_encode($response);
?>