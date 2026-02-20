<?php
/**
 * Admin: Halaman Login
 */

// Include config database
require_once '../config/config.php';

// Include auth system
require_once 'auth.php';

// Redirect jika sudah login
redirectIfLoggedIn();

// Ambil koneksi database
$conn = getDBConnection();

// Variabel untuk menyimpan error
$error = '';

// Proses form login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validasi input
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } else {
        // Query untuk cari user berdasarkan username
        $query = "SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        // Cek apakah user ditemukan
        if (mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Password benar! 
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                // Set session
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_full_name'] = $user['full_name'];
                $_SESSION['admin_role'] = $user['role'];
                
                // Update last login
                $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, 'i', $user['id']);
                mysqli_stmt_execute($update_stmt);
                
                // Redirect ke dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Password salah!';
            }
        } else {
            $error = 'Username tidak ditemukan atau akun tidak aktif!';
        }
    }
}

// Ambil flash message jika ada
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - TVRI Sulawesi Utara</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --tvri-primary: #1A428A;
            --tvri-primary-light: #2557B0;
            --tvri-dark: #0B1B3A;
            --tvri-darker: #060F1F;
            --tvri-gold: #F0B429;
            --tvri-gold-light: #F7C948;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            background: var(--tvri-darker);
            display: flex;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* LEFT PANEL - CINEMATIC BRANDING */
        .login-branding {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 50%;
            min-height: 100vh;
            background: var(--tvri-dark);
            position: relative;
            overflow: hidden;
        }

        /* Animated gradient mesh background */
        .branding-bg {
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(ellipse 80% 50% at 20% 80%, rgba(26,66,138,0.4) 0%, transparent 70%),
                radial-gradient(ellipse 60% 40% at 80% 20%, rgba(240,180,41,0.15) 0%, transparent 60%),
                radial-gradient(ellipse 50% 60% at 50% 50%, rgba(37,87,176,0.2) 0%, transparent 70%);
            animation: meshShift 12s ease-in-out infinite alternate;
        }

        @keyframes meshShift {
            0% { opacity: 0.7; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.05); }
            100% { opacity: 0.8; transform: scale(1.02); }
        }

        /* Floating geometric orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(1px);
            opacity: 0;
            animation: orbFloat linear infinite;
        }

        .orb-1 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(240,180,41,0.12) 0%, transparent 70%);
            top: -5%; left: -5%;
            animation: orbPulse1 8s ease-in-out infinite;
            opacity: 1;
            filter: blur(40px);
        }

        .orb-2 {
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(26,66,138,0.25) 0%, transparent 70%);
            bottom: 10%; right: -3%;
            animation: orbPulse2 10s ease-in-out infinite;
            opacity: 1;
            filter: blur(30px);
        }

        .orb-3 {
            width: 150px; height: 150px;
            background: radial-gradient(circle, rgba(240,180,41,0.08) 0%, transparent 70%);
            top: 40%; left: 60%;
            animation: orbPulse3 7s ease-in-out infinite;
            opacity: 1;
            filter: blur(25px);
        }

        @keyframes orbPulse1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, 20px) scale(1.1); }
            66% { transform: translate(-10px, 30px) scale(0.95); }
        }
        @keyframes orbPulse2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-20px, -30px) scale(1.15); }
        }
        @keyframes orbPulse3 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(20px, -20px) scale(1.1); }
        }

        /* Grid pattern overlay */
        .grid-pattern {
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridScroll 20s linear infinite;
        }

        @keyframes gridScroll {
            0% { transform: translate(0, 0); }
            100% { transform: translate(60px, 60px); }
        }

        /* Floating particles */
        .particles {
            position: absolute;
            inset: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: rgba(240,180,41,0.4);
            border-radius: 50%;
            animation: particleRise linear infinite;
        }

        @keyframes particleRise {
            0% { opacity: 0; transform: translateY(100vh) scale(0); }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; transform: translateY(-10vh) scale(1); }
        }

        /* Branding content */
        .branding-content {
            position: relative;
            z-index: 10;
            text-align: center;
            color: white;
            padding: 40px;
        }

        /* Logo container with glow ring */
        .logo-container {
            position: relative;
            display: inline-block;
            margin-bottom: 32px;
        }

        .logo-glow {
            position: absolute;
            inset: -20px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(240,180,41,0.2) 0%, transparent 70%);
            animation: logoGlow 3s ease-in-out infinite alternate;
        }

        @keyframes logoGlow {
            0% { opacity: 0.5; transform: scale(0.95); }
            100% { opacity: 1; transform: scale(1.05); }
        }

        .logo-ring {
            position: absolute;
            inset: -15px;
            border: 1px solid rgba(240,180,41,0.15);
            border-radius: 50%;
            animation: ringRotate 20s linear infinite;
        }

        .logo-ring::before {
            content: '';
            position: absolute;
            top: -3px; left: 50%;
            width: 6px; height: 6px;
            background: var(--tvri-gold);
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(240,180,41,0.6);
        }

        @keyframes ringRotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .branding-logo {
            width: 110px;
            height: auto;
            position: relative;
            z-index: 2;
            filter: drop-shadow(0 8px 24px rgba(0,0,0,0.4));
            animation: logoEntrance 1s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            opacity: 0;
        }

        @keyframes logoEntrance {
            0% { opacity: 0; transform: scale(0.5) rotate(-10deg); }
            100% { opacity: 1; transform: scale(1) rotate(0deg); }
        }

        .branding-title {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 2px;
            margin-bottom: 6px;
            background: linear-gradient(135deg, #FFFFFF 0%, var(--tvri-gold-light) 50%, #FFFFFF 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmerText 4s linear infinite, fadeSlideUp 0.8s 0.2s ease forwards;
            opacity: 0;
        }

        @keyframes shimmerText {
            0% { background-position: 200% center; }
            100% { background-position: -200% center; }
        }

        @keyframes fadeSlideUp {
            0% { opacity: 0; transform: translateY(15px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .branding-tagline {
            font-size: 13px;
            opacity: 0;
            font-weight: 400;
            color: rgba(255,255,255,0.5);
            letter-spacing: 3px;
            text-transform: uppercase;
            animation: fadeSlideUp 0.8s 0.4s ease forwards;
        }

        .branding-divider {
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--tvri-gold), transparent);
            margin: 28px auto;
            opacity: 0;
            animation: fadeSlideUp 0.8s 0.5s ease forwards;
        }

        .branding-features {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-width: 320px;
            margin: 0 auto;
        }

        .branding-feature {
            display: flex;
            align-items: center;
            gap: 14px;
            text-align: left;
            padding: 12px 16px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            opacity: 0;
            animation: featureSlide 0.6s ease forwards;
            transition: all 0.3s ease;
        }

        .branding-feature:nth-child(1) { animation-delay: 0.6s; }
        .branding-feature:nth-child(2) { animation-delay: 0.75s; }
        .branding-feature:nth-child(3) { animation-delay: 0.9s; }

        .branding-feature:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(240,180,41,0.2);
            transform: translateX(4px);
        }

        @keyframes featureSlide {
            0% { opacity: 0; transform: translateX(-20px); }
            100% { opacity: 1; transform: translateX(0); }
        }

        .feature-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(240,180,41,0.2), rgba(240,180,41,0.05));
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-icon i {
            color: var(--tvri-gold);
            font-size: 14px;
        }

        .feature-text {
            font-size: 12.5px;
            font-weight: 500;
            color: rgba(255,255,255,0.7);
            line-height: 1.4;
        }

        /* Corner accents */
        .corner-accent {
            position: absolute;
            width: 80px;
            height: 80px;
            border: 1px solid rgba(240,180,41,0.1);
            z-index: 5;
        }
        .corner-accent.top-left { top: 30px; left: 30px; border-right: none; border-bottom: none; }
        .corner-accent.bottom-right { bottom: 30px; right: 30px; border-left: none; border-top: none; }

        /* RIGHT PANEL - FORM */
        .login-form-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: #FFFFFF;
            position: relative;
            overflow: hidden;
        }

        /* Subtle decorative circle */
        .form-bg-circle {
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(26,66,138,0.03) 0%, transparent 70%);
            bottom: -100px;
            right: -100px;
        }

        .login-form-container {
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 2;
            animation: formEntrance 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        @keyframes formEntrance {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .login-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(26,66,138,0.06);
            border: 1px solid rgba(26,66,138,0.1);
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            color: var(--tvri-primary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }

        .login-badge .dot {
            width: 6px; height: 6px;
            background: #10b981;
            border-radius: 50%;
            animation: dotPulse 2s ease-in-out infinite;
        }

        @keyframes dotPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }

        .login-welcome h1 {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
            line-height: 1.2;
        }

        .login-welcome p {
            font-size: 14px;
            color: #94A3B8;
            font-weight: 400;
            margin-bottom: 32px;
        }

        /* Alert styling */
        .login-alert {
            border: none;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            animation: alertShake 0.5s ease;
        }

        @keyframes alertShake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-3px); }
            20%, 40%, 60%, 80% { transform: translateX(3px); }
        }

        .login-alert.alert-danger {
            background: #FEE2E2;
            color: #DC2626;
            border-left: 4px solid #DC2626;
        }
        .login-alert.alert-success {
            background: #D1FAE5;
            color: #059669;
            border-left: 4px solid #059669;
        }

        /* Form groups */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-size: 12.5px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 7px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i.input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #CBD5E1;
            font-size: 14px;
            transition: color 0.25s;
            z-index: 2;
        }

        .input-wrapper .form-control {
            padding: 13px 16px 13px 44px;
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #1E293B;
            background: #F8FAFC;
            transition: all 0.25s;
            width: 100%;
        }

        .input-wrapper .form-control::placeholder {
            color: #CBD5E1;
            font-size: 13px;
        }

        .input-wrapper .form-control:focus {
            border-color: var(--tvri-primary);
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(26, 66, 138, 0.08);
            outline: none;
        }

        .input-wrapper:focus-within i.input-icon {
            color: var(--tvri-primary);
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: #94A3B8;
            cursor: pointer;
            padding: 4px;
            font-size: 14px;
            z-index: 2;
            transition: color 0.2s;
        }
        .toggle-password:hover { color: var(--tvri-primary); }

        /* Submit button */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--tvri-primary);
            border: none;
            border-radius: 10px;
            color: white;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 6px;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover {
            background: var(--tvri-primary-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(26, 66, 138, 0.35);
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(26, 66, 138, 0.25);
        }

        /* Footer */
        .login-footer {
            margin-top: 28px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
        }

        .login-footer .back-link {
            color: var(--tvri-primary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .login-footer .back-link:hover {
            gap: 10px;
            opacity: 0.8;
        }

        .login-footer .copyright {
            font-size: 11.5px;
            color: #94A3B8;
        }

        /* RESPONSIVE */
        @media (max-width: 991.98px) {
            body { flex-direction: column; overflow-y: auto; }
            
            .login-branding {
                width: 100%;
                min-height: auto;
                padding: 50px 30px 40px;
            }
            
            .branding-features { display: none; }
            .corner-accent { display: none; }
            .branding-divider { margin: 16px auto; }

            .login-form-panel {
                padding: 32px 24px;
            }
        }

        @media (max-width: 575.98px) {
            .login-branding { padding: 36px 20px 28px; }
            .branding-logo { width: 80px; }
            .branding-title { font-size: 22px; }
            .login-form-panel { padding: 24px 20px; }
            .login-welcome h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <!-- LEFT PANEL - CINEMATIC BRANDING -->
    <div class="login-branding">
        <div class="branding-bg"></div>
        <div class="grid-pattern"></div>
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
        
        <div class="particles" id="particles"></div>
        
        <div class="corner-accent top-left"></div>
        <div class="corner-accent bottom-right"></div>

        <div class="branding-content">
            <div class="logo-container">
                <div class="logo-glow"></div>
                <div class="logo-ring"></div>
                <img src="<?php echo SITE_URL; ?>assets/images/TVRI_Sulawesi_Utara.png" 
                     alt="TVRI Sulawesi Utara" 
                     class="branding-logo">
            </div>
            <h2 class="branding-title">SULUT NEWS HUB</h2>
            <p class="branding-tagline">TVRI Sulawesi Utara</p>
            
            <div class="branding-divider"></div>

            <div class="branding-features">
                <div class="branding-feature">
                    <div class="feature-icon">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <span class="feature-text">Kelola &amp; publikasi berita dengan cepat</span>
                </div>
                <div class="branding-feature">
                    <div class="feature-icon">
                        <i class="fas fa-broadcast-tower"></i>
                    </div>
                    <span class="feature-text">Monitoring live broadcast real-time</span>
                </div>
                <div class="branding-feature">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <span class="feature-text">Dashboard analitik &amp; statistik lengkap</span>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL - LOGIN FORM -->
    <div class="login-form-panel">
        <div class="form-bg-circle"></div>
        <div class="login-form-container">
            <!-- Mobile Logo -->
            <div class="text-center d-lg-none mb-3">
                <img src="<?php echo SITE_URL; ?>assets/images/TVRI_Sulawesi_Utara.png" 
                     alt="TVRI" style="width: 72px; height: auto;">
            </div>

            <div class="login-badge">
                <span class="dot"></span>
                Admin Panel
            </div>

            <div class="login-welcome">
                <h1>Selamat Datang!</h1>
                <p>Masuk ke dashboard SULUT NEWS HUB</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="login-alert alert-<?php echo $flash['type']; ?>">
                    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="login-alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               placeholder="Masukkan username"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               required autofocus>
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Masukkan password"
                               required>
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="toggle-password" onclick="togglePwd()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    Masuk ke Dashboard
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
            
            <div class="login-footer">
                <a href="<?php echo SITE_URL; ?>" class="back-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke Website
                </a>
                <span class="copyright">&copy; <?php echo date('Y'); ?> TVRI Sulawesi Utara. All rights reserved.</span>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle password
        function togglePwd() {
            const p = document.getElementById('password');
            const i = document.getElementById('toggleIcon');
            if (p.type === 'password') {
                p.type = 'text';
                i.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                p.type = 'password';
                i.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Generate particles
        (function() {
            const container = document.getElementById('particles');
            if (!container) return;
            for (let i = 0; i < 20; i++) {
                const p = document.createElement('div');
                p.className = 'particle';
                p.style.left = Math.random() * 100 + '%';
                p.style.animationDuration = (8 + Math.random() * 12) + 's';
                p.style.animationDelay = (Math.random() * 10) + 's';
                p.style.width = p.style.height = (2 + Math.random() * 3) + 'px';
                p.style.opacity = 0.2 + Math.random() * 0.4;
                container.appendChild(p);
            }
        })();
    </script>
</body>
</html>
