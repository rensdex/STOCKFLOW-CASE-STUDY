<?php
// File: login.php
$skip_auth_check = true;
require_once 'backend/database.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockFlow - School Supply Inventory System</title>
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
            background: linear-gradient(135deg, #d3d3d6 );
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            top: -200px;
            right: -200px;
            animation: float 25s infinite ease-in-out;
        }
        
        body::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -250px;
            left: -250px;
            animation: float 20s infinite ease-in-out reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(40px, 30px) rotate(10deg); }
        }
        
        /* Decorative shapes */
        .shape {
            position: absolute;
            z-index: 0;
        }
        
        .shape-1 {
            top: 10%;
            left: 5%;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.05);
            border-radius: 30px;
            transform: rotate(45deg);
            animation: pulse 8s infinite;
        }
        
        .shape-2 {
            bottom: 15%;
            right: 8%;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            animation: pulse 10s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.1); }
        }
        
        .login-wrapper {
            width: 100%;
            max-width: 1200px;
            margin: 2rem;
            position: relative;
            z-index: 1;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        
        .login-container:hover {
            transform: translateY(-5px);
        }
        
        /* Left Panel - Brand Section */
        .brand-panel {
            background: linear-gradient(135deg, #13157e);
            padding: 3rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .brand-panel::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
            background-size: 50px 50px;
            animation: moveDots 20s linear infinite;
            opacity: 0.3;
        }
        
        @keyframes moveDots {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .brand-logo {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }
        
        .logo-circle {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .logo-circle img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .brand-panel h2 {
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .brand-panel p {
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
            line-height: 1.6;
        }
        
        .feature-list {
            margin-top: 2rem;
            list-style: none;
            padding: 0;
        }
        
        .feature-list li {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .feature-list i {
            font-size: 1.2rem;
        }
        
        /* Right Panel - Form Section */
        .form-panel {
            padding: 3rem;
            background: white;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-header h3 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #374151;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .input-group-custom {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            color: #9ca3af;
            z-index: 10;
        }
        
        .form-control-custom {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 2.8rem;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }
        
        .form-control-custom:focus {
            outline: none;
            border-color: #13157e;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .password-toggle {
            position: absolute;
            right: 1rem;
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            z-index: 10;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: #13157e;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #6b7280;
            cursor: pointer;
        }
        
        .checkbox-label input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #13157e;
        }
        
        .forgot-link {
            color: #13157e;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .forgot-link:hover {
            color: #13157e;
            text-decoration: underline;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #13157e);
            border: none;
            border-radius: 16px;
            padding: 0.9rem;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .demo-card {
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            border-radius: 16px;
            padding: 1rem;
            margin-top: 1.5rem;
        }
        
        .demo-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            color: #6b7280;
            margin-bottom: 0.75rem;
        }
        
        .demo-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.8rem;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .demo-item i {
            color: #667eea;
            font-size: 0.9rem;
        }
        
        /* Alert styling */
        .alert-custom {
            border-radius: 16px;
            border: none;
            font-size: 0.85rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .login-wrapper {
                margin: 1rem;
            }
            
            .brand-panel {
                padding: 2rem;
            }
            
            .form-panel {
                padding: 2rem;
            }
            
            .brand-panel h2 {
                font-size: 1.5rem;
            }
            
            .feature-list li {
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 576px) {
            .form-panel {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    
    <div class="login-wrapper">
        <div class="row g-0 login-container">
            <!-- Left Panel - Branding -->
            <div class="col-lg-6">
                <div class="brand-panel">
                    <div class="brand-logo">
                        <div class="logo-circle">
                            <img src="frontend/assets/images/logo3.png" alt="StockFlow Logo" onerror="this.src='https://placehold.co/80x80/ffffff/667eea?text=SF'">
                        </div>
                        <h2>StockFlow</h2>
                        <p>School Supply Inventory System</p>
                    </div>
                    
                    <ul class="feature-list">
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Real-time inventory tracking</span>
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Automated stock alerts</span>
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Generate detailed reports</span>
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Supplier management</span>
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Secure & reliable</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Right Panel - Login Form -->
            <div class="col-lg-6">
                <div class="form-panel">
                    <div class="form-header">
                        <h3>Welcome Back</h3>
                        <p>Please enter your credentials to access your account</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-custom">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="backend/auth/login.php">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Email Address / Username</label>
                            <div class="input-group-custom">
                                <i class="bi bi-envelope-fill input-icon"></i>
                                <input type="text" name="username" class="form-control-custom" required placeholder="Enter your email or username" >
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <div class="input-group-custom">
                                <i class="bi bi-lock-fill input-icon"></i>
                                <input type="password" name="password" id="password" class="form-control-custom" required placeholder="Enter your password" >
                                <button type="button" class="password-toggle" onclick="togglePassword()">
                                    <i class="bi bi-eye-slash-fill" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-options">
                            <label class="checkbox-label">
                                <input type="checkbox" name="remember_me"> Remember me
                            </label>
                            <a href="#" class="forgot-link" onclick="showForgotPasswordMessage(event)">Forgot Password?</a>
                        </div>
                        
                        <button type="submit" class="btn-login">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            Sign In
                        </button>
                    </form>
                    
                    
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="bi bi-shield-check me-1"></i> 
                            Secure Login | Protected by SSL Encryption
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
        
        function showForgotPasswordMessage(event) {
            event.preventDefault();
            Swal.fire({
                title: 'Forgot Password?',
                text: 'Please contact your system administrator to reset your password.',
                icon: 'info',
                confirmButtonColor: '#667eea',
                confirmButtonText: 'OK',
                background: 'white',
                customClass: {
                    popup: 'rounded-4'
                }
            });
        }
        
        // Add animation on load
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.login-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>