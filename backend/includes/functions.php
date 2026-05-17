<?php
// File: backend/includes/functions.php
// Additional helper functions

function getCategoryCount($pdo, $category_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE category_id = ?");
    $stmt->execute([$category_id]);
    return $stmt->fetchColumn();
}

function getSupplierCount($pdo, $supplier_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE supplier_id = ?");
    $stmt->execute([$supplier_id]);
    return $stmt->fetchColumn();
}

function logAudit($pdo, $user_id, $action, $module, $details) {
    $logId = 'LOG-' . date('Ymd') . '-' . rand(1000, 9999);
    $stmt = $pdo->prepare("INSERT INTO audit_logs (log_id, user_id, action, module, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$logId, $user_id, $action, $module, $details, $_SERVER['REMOTE_ADDR']]);
}

function getLowStockItems($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= low_stock_threshold");
    return $stmt->fetchColumn();
}

function getTotalInventoryValue($pdo) {
    $stmt = $pdo->query("SELECT SUM(quantity * unit_price) as total FROM inventory");
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}
?>