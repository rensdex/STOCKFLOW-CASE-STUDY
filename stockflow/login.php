<?php
// File: login.php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Use is_active instead of status
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Log audit
            $logId = 'LOG-' . rand(1000, 9999);
            $stmt = $pdo->prepare("INSERT INTO audit_logs (log_id, user_id, action, module, ip_address) VALUES (?, ?, 'LOGIN', 'Auth', ?)");
            $stmt->execute([$logId, $user['id'], $_SERVER['REMOTE_ADDR']]);
            
            redirect('index.php');
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stockflow - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background shapes */
        body::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(13, 110, 253, 0.1);
            border-radius: 50%;
            top: -150px;
            right: -150px;
            animation: float 20s infinite ease-in-out;
        }
        
        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(13, 202, 240, 0.08);
            border-radius: 50%;
            bottom: -200px;
            left: -200px;
            animation: float 15s infinite ease-in-out reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(30px, 20px) rotate(180deg); }
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 2.5rem;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: fadeInUp 0.6s ease;
            position: relative;
            z-index: 1;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Logo Section - Logo on the LEFT side (gilid) with NO GAP */
        .logo-container {
            margin-bottom: 2rem;
        }
        
        .logo-wrapper {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 0; /* REMOVED THE GAP - was 15px, now 0 */
            margin-bottom: 0;
        }
        
        /* Logo image styling - on the left side */
        .logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            animation: logoGlow 3s infinite ease-in-out;
            margin-right: 0; /* No margin on right */
        }
        
        @keyframes logoGlow {
            0%, 100% {
                filter: drop-shadow(0 0 5px rgba(13, 110, 253, 0.3));
            }
            50% {
                filter: drop-shadow(0 0 15px rgba(13, 110, 253, 0.6));
            }
        }
        
        .logo-text {
            text-align: left;
        }
        
        .logo-text h1 {
            font-size: 30px;
            font-weight: 800;
            margin: 0;
            line-height: 1.2;
        }
        
        .logo-text .stock {
            background: linear-gradient(135deg, #1e293b, #334155);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logo-text .flow {
            background: linear-gradient(135deg, #0e4ba8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logo-tagline {
            font-size: 12px;
            color: #94a3b8;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #64748b;
            font-size: 14px;
        }
        
        .form-control {
            border-radius: 14px;
            padding: 0.85rem 1rem;
            border: 2px solid #e2e8f0;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #0e4ba8;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
            outline: none;
        }
        
        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: #334155;
            margin-bottom: 8px;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #0e4ba8);
            border: none;
            border-radius: 14px;
            padding: 0.85rem;
            font-weight: 700;
            font-size: 15px;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(13, 110, 253, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .demo-credentials {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            padding: 1rem;
            margin-top: 1.8rem;
            border: 1px solid #e2e8f0;
        }
        
        .demo-credentials small {
            font-size: 12px;
        }
        
        .input-group-text {
            background: transparent;
            border-right: none;
            border-radius: 14px 0 0 14px;
            border: 2px solid #e2e8f0;
            border-right: none;
            color: #94a3b8;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 14px 14px 0;
        }
        
        /* Alert styling */
        .alert {
            border-radius: 14px;
            border: none;
            font-size: 13px;
            padding: 0.85rem 1rem;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                margin: 1rem;
                padding: 1.8rem;
            }
            
            .logo-img {
                width: 50px;
                height: 50px;
            }
            
            .logo-icon {
                width: 50px;
                height: 50px;
                font-size: 25px;
            }
            
            .logo-text h1 {
                font-size: 24px;
            }
            
            .logo-tagline {
                font-size: 9px;
            }
        }
        
        /* Footer link */
        .footer-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 12px;
            color: #94a3b8;
        }
        
        .footer-link a {
            color: #0e4ba8;
            text-decoration: none;
        }
        
        .footer-link a:hover {
            text-decoration: underline;
        }
        
        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0 1rem;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .divider::before {
            margin-right: 0.5rem;
        }
        
        .divider::after {
            margin-left: 0.5rem;
        }
        
        .divider span {
            color: #94a3b8;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <!-- Logo Section - Logo on the LEFT side (gilid) with NO GAP -->
        <div class="logo-container">
            <div class="logo-wrapper">
                <!-- Logo Image on the left -->
                <img src="images/logo3.png" alt="Stockflow Logo" class="logo-img" 
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <!-- Fallback icon if image fails to load -->
                <div class="logo-icon" style="display: none;">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
                <div class="logo-text">
                    <h1>
                        <span class="stock">STOCK</span><span class="flow">FLOW</span>
                    </h1>
                    <div class="logo-tagline">Inventory Management System</div>
                </div>
            </div>
        </div>
        <div class="login-header">
            <h2>Welcome Back!</h2>
            <p>Sign in to continue to your dashboard</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-envelope-fill"></i>
                    </span>
                    <input type="email" name="email" class="form-control" required placeholder="maria.cruz@corp.ph" value="maria.cruz@corp.ph">
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock-fill"></i>
                    </span>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••" value="password" id="password">
                    <button type="button" class="input-group-text" onclick="togglePassword()" style="cursor: pointer;">
                        <i class="bi bi-eye-slash-fill" id="toggleIcon"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i> Sign In
            </button>
        </form>
        
        <div class="footer-link">
            <i class="bi bi-shield-check me-1"></i> Secure Login | <a href="#">Forgot Password?</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye-slash-fill');
                toggleIcon.classList.add('bi-eye-fill');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-fill');
                toggleIcon.classList.add('bi-eye-slash-fill');
            }
        }
        
        // Check if logo loaded, if not show fallback
        document.addEventListener('DOMContentLoaded', function() {
            const logoImg = document.querySelector('.logo-img');
            const fallbackIcon = document.querySelector('.logo-icon');
            
            if (logoImg && logoImg.complete && logoImg.naturalHeight === 0) {
                logoImg.style.display = 'none';
                if (fallbackIcon) fallbackIcon.style.display = 'flex';
            }
        });
    </script>
</body>
</html>