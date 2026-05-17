<?php
// File: backend/auth/login.php
require_once '../database.php';

if (isLoggedIn()) {
    redirect('../../index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['login_error'] = 'Invalid security token';
        redirect('../../login.php');
    }
    
    $username = filter_var($_POST['username'] ?? '', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';
    
    // Fixed: Use correct table structure from school_supply_db
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['fullname'];  // Fixed: Use fullname for display
        $_SESSION['role'] = $user['role'];
        
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        redirect('../../index.php');
    } else {
        $_SESSION['login_error'] = 'Invalid username or password';
        redirect('../../login.php');
    }
}
?>