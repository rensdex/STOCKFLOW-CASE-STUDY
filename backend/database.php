<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'school_supply_db');  
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USERNAME,
        DB_PASSWORD
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// CSRF Token generation
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}


$pusher = null;

function initPusher() {
    global $pusher;
    $configPath = __DIR__ . '/pusher_config.php';
    
    if (file_exists($configPath)) {
        require_once $configPath;
    }
}

function getPusher() {
    global $pusher;
    if ($pusher === null) {
        initPusher();
    }
    return $pusher;
}

function triggerStockUpdate($type, $supply_name, $quantity, $new_quantity = null) {
    $pusher = getPusher();
    if ($pusher) {
        $data = [
            'type' => $type,
            'product_name' => $supply_name,
            'supply_name' => $supply_name,
            'quantity' => $quantity,
            'new_quantity' => $new_quantity,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $type === 'stock_in' 
                ? "📦 Stock-In: +{$quantity} {$supply_name}" 
                : "📤 Stock-Out: -{$quantity} {$supply_name}"
        ];
        
        $pusher->trigger('stock-channel', 'stock-update', $data);
        return true;
    }
    return false;
}

function triggerInventoryUpdate($action, $supply_name, $supply_id = null) {
    $pusher = getPusher();
    if ($pusher && $supply_name) {
        $data = [
            'action' => $action,
            'product_name' => $supply_name,
            'supply_name' => $supply_name,
            'supply_id' => $supply_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => "🔄 Supply '{$supply_name}' has been {$action}"
        ];
        
        $pusher->trigger('inventory-channel', 'inventory-update', $data);
        return true;
    }
    return false;
}

function triggerRealtimeNotification($title, $message, $type = 'info') {
    $pusher = getPusher();
    if ($pusher) {
        $data = [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'timestamp' => date('Y-m-d H:i:s'),
            'is_read' => 0
        ];
        
        $pusher->trigger('notification-channel', 'new-notification', $data);
        return true;
    }
    return false;
}

?>