<?php
// ============================================================
// MAIN ROUTER - Redirects to appropriate page
// ============================================================

// ============================================================
// ERROR REPORTING
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// ============================================================
// SESSION START
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// DATABASE CONNECTION
// ============================================================
try {
    require_once 'db.php';
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

try {
    require_once 'includes/functions.php';
} catch (Exception $e) {
    die("Functions file not found: " . $e->getMessage());
}

// ============================================================
// CHECK IF USER IS LOGGED IN
// ============================================================
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT role FROM Users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && $user['role'] === 'admin') {
            header("Location: admin/index.php");
            exit();
        } else {
            header("Location: user/dashboard.php");
            exit();
        }
    } catch (Exception $e) {
        session_destroy();
    }
}

// ============================================================
// NOT LOGGED IN - SHOW LOGIN/REGISTER PAGE
// ============================================================

// Get site settings
try {
    $stmtSettings = $pdo->query("SELECT * FROM SiteSettings LIMIT 1");
    $siteSettings = $stmtSettings->fetch(PDO::FETCH_ASSOC) ?: [
        'site_name' => 'OTPSMS',
        'site_logo' => '',
        'support_link' => 'https://t.me/support',
        'currency_symbol' => '₹'
    ];
} catch (Exception $e) {
    $siteSettings = [
        'site_name' => 'OTPSMS',
        'site_logo' => '',
        'support_link' => 'https://t.me/support',
        'currency_symbol' => '₹'
    ];
}

// Get stats for landing page
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn() ?: 0;
    $totalServices = $pdo->query("SELECT COUNT(*) FROM Services")->fetchColumn() ?: 0;
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM Orders")->fetchColumn() ?: 0;
} catch (Exception $e) {
    $totalUsers = 0;
    $totalServices = 0;
    $totalOrders = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPSMS'); ?> - Premium SMS Verification</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           LOGIN PAGE - MODERN DESIGN
           ============================================================ */
        :root {
            --accent: #246bfd;
            --accent-dark: #1a56db;
            --accent-light: #e8edfd;
            --bg-body: #f0f4ff;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --shadow-lg: 0 20px 60px rgba(36, 107, 253, 0.15);
            --radius: 24px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, system-ui, sans-serif;
        }

        body {
            background: var(--bg-body);
            min-height: 100vh;
            display: flex;
            flex-direction: column;  /* ✅ यह एलिमेंट्स को ऊपर से नीचे (vertical) सेट करेगा */
            justify-content: space-between;
            align-items: stretch;    /* ✅ यह फुटर को पूरी चौड़ाई लेने देगा */
            padding: 20px 0 0 0;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 1200px;
            margin: auto auto; /* ऊपर-नीचे ऑटो मार्जिन ताकि बीच में रहे */
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }

/* ============================================================
       PREMIUM LANDING PAGE SECTIONS CSS
       ============================================================ */
    .landing-header {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
    }
    .landing-nav-links { display: flex; gap: 25px; align-items: center; }
    .landing-nav-links a { color: #475569; text-decoration: none; font-weight: 500; font-size: 14px; }
    .landing-nav-links a:hover { color: var(--accent); }
    
    /* Hero Banner Area */
    .landing-hero {
        text-align: center;
        padding: 50px 20px 30px;
        max-width: 900px;
        margin: 0 auto;
    }
    .landing-badge { display: inline-flex; align-items: center; gap: 6px; background: #eff6ff; color: var(--accent); padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 20px; border: 1px solid #dbeafe; }
    .landing-hero h1 { font-size: 46px; font-weight: 900; color: #0f172a; letter-spacing: -1px; margin-bottom: 15px; line-height: 1.1; }
    .landing-hero h1 span { color: var(--accent); }
    .landing-hero p { font-size: 16px; color: #64748b; margin-bottom: 30px; }

    /* Sections Container */
    .landing-container { max-width: 1100px; margin: 40px auto; padding: 0 20px; width: 100%; }
    .landing-title { text-align: center; font-size: 28px; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
    .landing-desc { text-align: center; color: #64748b; font-size: 14px; margin-bottom: 35px; }

    /* Pricing Grid */
    .landing-pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 50px; }
    .landing-price-card { background: white; border: 1px solid var(--border-color); border-radius: 14px; padding: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .landing-price-card .serv-name { font-weight: 700; font-size: 15px; color: #0f172a; }
    .landing-price-card .serv-country { font-size: 12px; color: #64748b; }
    .landing-price-card .serv-price { font-size: 18px; font-weight: 800; color: var(--accent); text-align: right; }
    .landing-price-card .serv-price span { display: block; font-size: 10px; color: #94a3b8; font-weight: normal; }

    /* Features Grid (Why Choose Us) */
    .landing-features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 50px; }
    .landing-feature-box { background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .landing-feature-box .f-icon { width: 40px; height: 40px; background: #eff6ff; color: var(--accent); border-radius: 10px; display: flex; justify-content: center; align-items: center; font-size: 18px; margin-bottom: 14px; }
    .landing-feature-box h4 { font-size: 15px; font-weight: 700; margin-bottom: 6px; }
    .landing-feature-box p { font-size: 13px; color: #64748b; line-height: 1.5; }

    /* Bottom CTA Box */
    .landing-cta-box { background: linear-gradient(135deg, #eff6ff 0%, #e0e7ff 100%); border: 1px solid #bfdbfe; border-radius: 24px; padding: 45px 20px; text-align: center; margin-bottom: 50px; }
    .landing-cta-box h3 { font-size: 26px; font-weight: 800; color: #0f172a; margin-bottom: 10px; }
    .landing-cta-box p { font-size: 14px; color: #475569; margin-bottom: 25px; }

    @media(max-width: 992px) {
        .landing-pricing-grid, .landing-features-grid { grid-template-columns: 1fr 1fr; }
    }
    @media(max-width: 600px) {
        .landing-hero h1 { font-size: 32px; }
        .landing-pricing-grid, .landing-features-grid { grid-template-columns: 1fr; }
        .landing-nav-links { display: none; }
    }

        /* ============================================================
           LEFT SIDE - BRANDING
           ============================================================ */
        .brand-section {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .brand-section .brand-logo {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-section .brand-logo .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--accent), #bc13fe);
            border-radius: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 28px;
            font-weight: 900;
            color: white;
            box-shadow: 0 10px 30px rgba(36, 107, 253, 0.3);
        }

        .brand-section .brand-logo h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-main);
        }

        .brand-section .tagline {
            font-size: 18px;
            color: var(--text-muted);
            max-width: 400px;
            line-height: 1.6;
        }

        .brand-section .tagline strong {
            color: var(--accent);
        }

        .brand-section .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 10px;
        }

        .brand-section .stats-grid .stat-item {
            background: var(--bg-card);
            padding: 18px 20px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .brand-section .stats-grid .stat-item .number {
            font-size: 24px;
            font-weight: 800;
            color: var(--accent);
        }

        .brand-section .stats-grid .stat-item .label {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 4px;
        }

        .brand-section .features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .brand-section .features .feature {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-main);
            font-weight: 500;
        }

        .brand-section .features .feature i {
            color: var(--accent);
            font-size: 18px;
        }

        /* ============================================================
           RIGHT SIDE - AUTH CARD
           ============================================================ */
        .auth-container {
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
        }

        .auth-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 40px 35px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(226, 232, 240, 0.5);
            transition: all 0.3s;
        }

        .auth-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 25px 70px rgba(36, 107, 253, 0.18);
        }

        .auth-card .auth-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .auth-card .auth-header .auth-icon {
            width: 64px;
            height: 64px;
            background: var(--accent-light);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 12px;
            font-size: 28px;
            color: var(--accent);
        }

        .auth-card .auth-header h2 {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
        }

        .auth-card .auth-header p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 4px;
        }

        .auth-card .form-group {
            margin-bottom: 16px;
        }

        .auth-card .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-main);
            margin-bottom: 5px;
        }

        .auth-card .form-group .input-wrapper {
            position: relative;
        }

        .auth-card .form-group .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
        }

        .auth-card .form-group .input-wrapper input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 15px;
            color: var(--text-main);
            background: #f8fafc;
            transition: 0.3s;
            outline: none;
        }

        .auth-card .form-group .input-wrapper input:focus {
            border-color: var(--accent);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(36, 107, 253, 0.1);
        }

        .auth-card .form-group .input-wrapper input::placeholder {
            color: #94a3b8;
        }

        .auth-card .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .auth-card .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(36, 107, 253, 0.3);
        }

        .auth-card .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .auth-card .auth-switch {
            text-align: center;
            margin-top: 18px;
            font-size: 14px;
            color: var(--text-muted);
        }

        .auth-card .auth-switch a {
            color: var(--accent);
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .auth-card .auth-switch a:hover {
            color: var(--accent-dark);
            text-decoration: underline;
        }

        .auth-card .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
            text-align: center;
        }

        .auth-card .error-message.show {
            display: block;
        }

        .auth-card .success-message {
            background: #dcfce7;
            color: #16a34a;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
            text-align: center;
        }

        .auth-card .success-message.show {
            display: block;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        #register-form {
            display: none;
        }

        .auth-card .support-link {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .auth-card .support-link a {
            color: var(--accent);
            text-decoration: none;
        }

        .auth-card .support-link a:hover {
            text-decoration: underline;
        }

        .auth-card .divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 18px 0;
        }

        .auth-card .divider::before,
        .auth-card .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .auth-card .divider span {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .auth-card .social-login {
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .auth-card .social-login .social-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: transparent;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 18px;
            color: var(--text-muted);
            cursor: pointer;
            transition: 0.2s;
        }

        .auth-card .social-login .social-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--accent-light);
            transform: translateY(-2px);
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 992px) {
            .auth-wrapper {
                grid-template-columns: 1fr;
                gap: 30px;
                min-height: auto;
                padding: 20px 0;
            }

            .brand-section {
                text-align: center;
                align-items: center;
            }

            .brand-section .tagline {
                max-width: 100%;
                text-align: center;
            }

            .brand-section .stats-grid {
                width: 100%;
                max-width: 400px;
            }

            .brand-section .features {
                justify-content: center;
                max-width: 400px;
            }

            .auth-container {
                max-width: 440px;
            }
        }

        @media (max-width: 600px) {
            .auth-card {
                padding: 30px 20px;
            }

            .brand-section .brand-logo h1 {
                font-size: 24px;
            }

            .brand-section .stats-grid {
                grid-template-columns: 1fr 1fr 1fr;
                gap: 10px;
            }

            .brand-section .stats-grid .stat-item {
                padding: 12px 10px;
            }

            .brand-section .stats-grid .stat-item .number {
                font-size: 18px;
            }

            .brand-section .features {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .brand-section .features .feature {
                font-size: 12px;
            }
        }

        @media (max-width: 400px) {
            .brand-section .stats-grid {
                grid-template-columns: 1fr;
            }

            .brand-section .features {
                grid-template-columns: 1fr;
            }

            .auth-card .auth-header h2 {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    
    <!-- ============================================================
    LEFT SIDE - BRANDING
    ============================================================ -->
    <div class="brand-section">
        <div class="brand-logo">
            <div class="logo-icon">
                <?php echo substr($siteSettings['site_name'] ?? 'O', 0, 1); ?>
            </div>
            <h1><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPSMS'); ?></h1>
        </div>

        <div class="tagline">
            Premium <strong>virtual numbers</strong> for SMS verification.<br>
            Fast, reliable & secure service trusted by thousands.
        </div>

        <div class="stats-grid">
            <div class="stat-item">
                <div class="number"><?php echo number_format($totalUsers); ?></div>
                <div class="label">Active Users</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo number_format($totalServices); ?></div>
                <div class="label">Services</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo number_format($totalOrders); ?></div>
                <div class="label">Orders Completed</div>
            </div>
        </div>

        <div class="features">
            <div class="feature">
                <i class="fa-solid fa-shield-halved"></i> Secure & Private
            </div>
            <div class="feature">
                <i class="fa-solid fa-bolt"></i> Instant Delivery
            </div>
            <div class="feature">
                <i class="fa-solid fa-globe"></i> 150+ Countries
            </div>
            <div class="feature">
                <i class="fa-solid fa-headset"></i> 24/7 Support
            </div>
        </div>
    </div>

    <!-- ============================================================
    RIGHT SIDE - AUTH CARD
    ============================================================ -->
    <div class="auth-container">

        <!-- LOGIN FORM -->
        <div class="auth-card" id="login-form">
            <div class="auth-header">
                <div class="auth-icon">
                    <i class="fa-regular fa-user"></i>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to continue to your dashboard</p>
            </div>

            <div class="error-message" id="login-error"></div>
            <div class="success-message" id="login-success"></div>

            <form id="loginForm" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" id="login-email" placeholder="your@email.com" required autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="login-password" placeholder="Enter your password" required autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="login-btn">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Log In
                </button>
            </form>

            <div class="auth-switch">
                Don't have an account? <a onclick="showRegister()">Create Account</a>
            </div>

            <div class="divider">
                <span>or continue with</span>
            </div>

            <div class="social-login">
    
    <!-- Google Login Button (जब एडमिन पैनल से ON होगा, तभी दिखेगा) -->
    <?php if(isset($siteSettings['google_login_status']) && $siteSettings['google_login_status'] == 1): ?>
        <button type="button" class="social-btn" onclick="window.location.href='google_auth.php'" title="Login with Google">
            <i class="fa-brands fa-google"></i>
        </button>
    <?php endif; ?>

    <!-- Telegram Login Button (जब एडमिन पैनल से ON होगा, तभी दिखेगा) -->
    <?php if(isset($siteSettings['telegram_login_status']) && $siteSettings['telegram_login_status'] == 1): ?>
        <button type="button" class="social-btn" onclick="window.location.href='telegram_auth.php'" title="Login with Telegram">
            <i class="fa-brands fa-telegram"></i>
        </button>
    <?php endif; ?>

    <!-- GitHub Login Button (चूंकि इसकी कोई सेटिंग नहीं है, इसलिए यह हमेशा दिखेगा) -->
    <button type="button" class="social-btn" onclick="alert('GitHub login coming soon')">
        <i class="fa-brands fa-github"></i>
    </button>

</div>

            <div class="support-link">
                <i class="fa-regular fa-circle-question"></i> Need help? 
                <a href="<?php echo htmlspecialchars($siteSettings['support_link'] ?? '#'); ?>" target="_blank">Contact Support</a>
            </div>
        </div>

        <!-- REGISTER FORM -->
        <div class="auth-card" id="register-form">
            <div class="auth-header">
                <div class="auth-icon">
                    <i class="fa-regular fa-user-plus"></i>
                </div>
                <h2>Create Account</h2>
                <p>Start using premium SMS verification</p>
            </div>

            <div class="error-message" id="register-error"></div>
            <div class="success-message" id="register-success"></div>

            <form id="registerForm" onsubmit="handleRegister(event)">
                <div class="form-group">
                    <label>Full Name</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" id="register-name" placeholder="John Doe" required autocomplete="name">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" id="register-email" placeholder="your@email.com" required autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="register-password" placeholder="Min 6 characters" required minlength="6" autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="register-btn">
                    <i class="fa-solid fa-user-plus"></i> Create Account
                </button>
            </form>

            <div class="auth-switch">
                Already have an account? <a onclick="showLogin()">Log In</a>
            </div>

            <div class="divider">
                <span>or continue with</span>
            </div>

            <div class="social-login">
    
    <!-- Google Login Button (जब एडमिन पैनल से ON होगा, तभी दिखेगा) -->
    <?php if(isset($siteSettings['google_login_status']) && $siteSettings['google_login_status'] == 1): ?>
        <button type="button" class="social-btn" onclick="window.location.href='google_auth.php'" title="Login with Google">
            <i class="fa-brands fa-google"></i>
        </button>
    <?php endif; ?>

    <!-- Telegram Login Button (जब एडमिन पैनल से ON होगा, तभी दिखेगा) -->
    <?php if(isset($siteSettings['telegram_login_status']) && $siteSettings['telegram_login_status'] == 1): ?>
        <button type="button" class="social-btn" onclick="window.location.href='telegram_auth.php'" title="Login with Telegram">
            <i class="fa-brands fa-telegram"></i>
        </button>
    <?php endif; ?>

    <!-- GitHub Login Button (चूंकि इसकी कोई सेटिंग नहीं है, इसलिए यह हमेशा दिखेगा) -->
    <button type="button" class="social-btn" onclick="alert('GitHub login coming soon')">
        <i class="fa-brands fa-github"></i>
    </button>

</div>

            <div class="support-link">
                <i class="fa-regular fa-circle-question"></i> Need help? 
                <a href="<?php echo htmlspecialchars($siteSettings['support_link'] ?? '#'); ?>" target="_blank">Contact Support</a>
            </div>
        </div>

    </div>

</div>

<!-- ============================================================
JAVASCRIPT
============================================================ -->
<script>
    // ============================================================
    // FORM SWITCH
    // ============================================================
    function showRegister() {
        document.getElementById('login-form').style.display = 'none';
        document.getElementById('register-form').style.display = 'block';
        document.getElementById('register-error').classList.remove('show');
        document.getElementById('register-success').classList.remove('show');
    }
    
    function showLogin() {
        document.getElementById('register-form').style.display = 'none';
        document.getElementById('login-form').style.display = 'block';
        document.getElementById('login-error').classList.remove('show');
        document.getElementById('login-success').classList.remove('show');
    }

    // ============================================================
    // HANDLE LOGIN
    // ============================================================
    function handleLogin(e) {
        e.preventDefault();
        
        const email = document.getElementById('login-email').value.trim();
        const password = document.getElementById('login-password').value.trim();
        const errorDiv = document.getElementById('login-error');
        const successDiv = document.getElementById('login-success');
        const btn = document.getElementById('login-btn');
        
        errorDiv.classList.remove('show');
        successDiv.classList.remove('show');
        
        if (!email || !password) {
            errorDiv.textContent = 'Please fill in all fields';
            errorDiv.classList.add('show');
            return;
        }
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Logging in...';
        
        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('email', email);
        formData.append('password', password);
        
        fetch('user/ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket"></i> Log In';
            
            if (data.success) {
                successDiv.textContent = '✅ ' + data.message + ' Redirecting...';
                successDiv.classList.add('show');
                setTimeout(() => {
                    window.location.href = data.user?.role === 'admin' ? 'admin/index.php' : 'user/dashboard.php';
                }, 500);
            } else {
                errorDiv.textContent = data.message || 'Login failed';
                errorDiv.classList.add('show');
            }
        })
        .catch((err) => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket"></i> Log In';
            errorDiv.textContent = 'Network error. Please try again.';
            errorDiv.classList.add('show');
            console.error('Login error:', err);
        });
    }

    // ============================================================
    // HANDLE REGISTER
    // ============================================================
    function handleRegister(e) {
        e.preventDefault();
        
        const name = document.getElementById('register-name').value.trim();
        const email = document.getElementById('register-email').value.trim();
        const password = document.getElementById('register-password').value.trim();
        const errorDiv = document.getElementById('register-error');
        const successDiv = document.getElementById('register-success');
        const btn = document.getElementById('register-btn');
        
        errorDiv.classList.remove('show');
        successDiv.classList.remove('show');
        
        if (!name || !email || !password) {
            errorDiv.textContent = 'Please fill in all fields';
            errorDiv.classList.add('show');
            return;
        }
        
        if (password.length < 6) {
            errorDiv.textContent = 'Password must be at least 6 characters';
            errorDiv.classList.add('show');
            return;
        }
        
        if (!email.includes('@') || !email.includes('.')) {
            errorDiv.textContent = 'Please enter a valid email address';
            errorDiv.classList.add('show');
            return;
        }
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Creating account...';
        
        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('name', name);
        formData.append('email', email);
        formData.append('password', password);
        
        fetch('user/ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Create Account';
            
            if (data.success) {
                successDiv.textContent = '✅ ' + data.message + ' Redirecting...';
                successDiv.classList.add('show');
                setTimeout(() => {
                    window.location.href = 'user/dashboard.php';
                }, 500);
            } else {
                errorDiv.textContent = data.message || 'Registration failed';
                errorDiv.classList.add('show');
            }
        })
        .catch((err) => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Create Account';
            errorDiv.textContent = 'Network error. Please try again.';
            errorDiv.classList.add('show');
            console.error('Register error:', err);
        });
    }

    // ============================================================
    // ENTER KEY SUPPORT
    // ============================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Login form enter key
        document.getElementById('login-password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') handleLogin(e);
        });
        document.getElementById('login-email').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') handleLogin(e);
        });
        
        // Register form enter key
        document.getElementById('register-password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') handleRegister(e);
        });
        document.getElementById('register-email').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') handleRegister(e);
        });
        document.getElementById('register-name').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') handleRegister(e);
        });

        console.log('✅ Login Page Loaded Successfully');
    });
</script>

<script>
// ============================================================
// PAYTM AUTO-VERIFICATION - FRONTEND
// ============================================================

let paytmCheckInterval = null;
let paytmOrderId = '';

function openPaytmModal(amount) {
    // Generate unique order ID
    paytmOrderId = 'PAYTM_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
    
    // Show modal
    document.getElementById('paytm-modal').style.display = 'flex';
    document.getElementById('paytm-amount-display').textContent = '₹' + amount;
    document.getElementById('paytm-order-id').textContent = paytmOrderId;
    document.getElementById('paytm-status-text').textContent = '⏳ Waiting for payment...';
    document.getElementById('paytm-status-text').style.color = '#f59e0b';
    
    // Start checking
    startPaytmVerification(amount);
}

function startPaytmVerification(amount) {
    if (paytmCheckInterval) {
        clearInterval(paytmCheckInterval);
    }
    
    // Check every 5 seconds
    paytmCheckInterval = setInterval(function() {
        checkPaytmPayment(amount);
    }, 5000);
    
    // Stop after 3 minutes
    setTimeout(function() {
        if (paytmCheckInterval) {
            clearInterval(paytmCheckInterval);
            paytmCheckInterval = null;
            document.getElementById('paytm-status-text').textContent = '⏰ Timeout. Please enter UTR manually.';
            document.getElementById('paytm-status-text').style.color = '#ef4444';
        }
    }, 180000);
}

function checkPaytmPayment(amount) {
    const formData = new FormData();
    formData.append('action', 'check_paytm_payment');
    formData.append('amount', amount);
    formData.append('order_id', paytmOrderId);
    
    fetch('ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Payment Status:', data);
        
        if (data.success && data.status === 'SUCCESS') {
            clearInterval(paytmCheckInterval);
            paytmCheckInterval = null;
            
            document.getElementById('paytm-status-text').textContent = '✅ Payment Verified!';
            document.getElementById('paytm-status-text').style.color = '#22c55e';
            
            // Show success and reload
            setTimeout(function() {
                location.reload();
            }, 2000);
        } else if (data.success && data.status === 'PENDING') {
            document.getElementById('paytm-status-text').textContent = '⏳ Waiting for payment...';
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function submitPaytmUTR() {
    const utr = document.getElementById('paytm-utr-input').value.trim();
    const amountText = document.getElementById('paytm-amount-display').textContent;
    const amount = amountText.replace('₹', '');
    
    if (!utr || utr.length < 8) {
        alert('Please enter a valid UTR number (minimum 8 characters)');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'process_recharge');
    formData.append('payment_mode', 'paytm');
    formData.append('amount', amount);
    formData.append('utr', utr);
    
    fetch('ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Error: ' + error.message);
    });
}

function closePaytmModal() {
    if (paytmCheckInterval) {
        clearInterval(paytmCheckInterval);
        paytmCheckInterval = null;
    }
    document.getElementById('paytm-modal').style.display = 'none';
}
</script>

<!-- ============================================================
    LANDING PAGE MASTER FOOTER - PREMIUM REDESIGN
============================================================ -->
<?php
try {
    $footerLinksList = $pdo->query("SELECT * FROM FooterLinks ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $activeLegalPages = $pdo->query("SELECT * FROM Pages WHERE status = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $footerSupportLinks = $pdo->query("SELECT * FROM SupportLinks ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $footerLinksList = [];
    $activeLegalPages = [];
    $footerSupportLinks = [];
}
?>

<style>
    .otpx-master-footer {
        width: 100%;
        background: #ffffff;
        border-top: 1px solid #eaeaea;
        color: #111827;
        font-family: 'Inter', -apple-system, sans-serif;
        padding: 45px 20px 25px;
        margin-top: 60px;
        box-sizing: border-box;
        clear: both;
        grid-column: 1 / -1;
    }
    .otpx-mf-grid {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 30px;
        margin-bottom: 35px;
        box-sizing: border-box;
    }
    .mf-brand-col { display: flex; flex-direction: column; align-items: flex-start; }
    .mf-logo-row { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 10px; }
    .mf-logo-row img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
    .mf-desc { color: #64748b; font-size: 13.5px; line-height: 1.5; margin-bottom: 15px; max-width: 320px; text-align: left; }
    
    /* Premium Pills */
    .mf-pills-row { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 15px; }
    .mf-pill { background: #f8fafc; color: #334155; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 5px; }
    
    /* Social Icons */
    .mf-social-icons { display: flex; gap: 8px; align-items: center; }
    .mf-social-icon { width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; color: #475569; display: flex; justify-content: center; align-items: center; font-size: 13px; text-decoration: none; transition: 0.2s; }
    .mf-social-icon:hover { background: #2563eb; color: white; }

    /* Links Columns */
    .mf-links-col h4 { font-size: 12px; font-weight: 800; color: #0f172a; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.8px; text-align: left; }
    .mf-links-col ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
    .mf-links-col ul li a { color: #64748b; font-size: 13.5px; font-weight: 500; text-decoration: none; transition: 0.2s; }
    .mf-links-col ul li a:hover { color: #2563eb; padding-left: 2px; }

    /* Bottom Section */
    .mf-divider { max-width: 1200px; margin: 0 auto; height: 1px; background: #eaeaea; margin-bottom: 20px; }
    .otpx-mf-bottom { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; font-size: 12.5px; color: #64748b; }
    .mf-pay-cards { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .card-badge { background: #ffffff; border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 10.5px; color: #334155; box-shadow: 0 1px 2px rgba(0,0,0,0.02); display: inline-flex; align-items: center; gap: 4px; }

    /* Responsive Design */
    @media(max-width: 992px) { 
        .otpx-mf-grid { grid-template-columns: 1fr 1fr; gap: 25px; } 
    }
    @media(max-width: 600px) {
        .otpx-master-footer { padding: 40px 15px 20px; }
        .otpx-mf-grid { grid-template-columns: 1fr; gap: 20px; text-align: left; }
        .otpx-mf-bottom { flex-direction: column; text-align: center; justify-content: center; gap: 12px; }
        .mf-pay-cards { justify-content: center; }
    }
</style>

<footer class="otpx-master-footer">
    <div class="otpx-mf-grid">
        <!-- Column 1: Brand & Info -->
        <div class="mf-brand-col">
            <div class="mf-logo-row">
                <?php if(!empty($siteSettings['site_logo'])): ?>
                    <img src="<?php echo htmlspecialchars($siteSettings['site_logo']); ?>">
                <?php else: ?>
                    <div style="width:32px; height:32px; background:#2563eb; color:white; border-radius:50%; display:flex; justify-content:center; align-items:center; font-size:14px;">O</div>
                <?php endif; ?>
                <span><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPSMS'); ?></span>
            </div>
            <p class="mf-desc">
                <?php echo !empty($siteSettings['footer']) ? htmlspecialchars($siteSettings['footer']) : 'Premium virtual numbers for SMS verification. Fast, reliable, and secure service trusted by thousands worldwide.'; ?>
            </p>
            <div class="mf-pills-row">
                <span class="mf-pill"><i class="fa-solid fa-shield-halved" style="color:#2563eb;"></i> Secure & Private</span>
                <span class="mf-pill"><i class="fa-solid fa-earth-americas" style="color:#10b981;"></i> 150+ Countries</span>
                <span class="mf-pill"><i class="fa-solid fa-bolt" style="color:#f59e0b;"></i> Instant Delivery</span>
            </div>
            <div class="mf-social-icons">
                <?php if(!empty($footerSupportLinks)): ?>
                    <?php foreach($footerSupportLinks as $slink): 
                        $sBg = '#f1f5f9'; $sColor = '#475569';
                        $sIcon = strtolower($slink['icon_class']);
                        if(strpos($sIcon, 'whatsapp') !== false) { $sBg = '#dcfce7'; $sColor = '#16a34a'; }
                        elseif(strpos($sIcon, 'telegram') !== false) { $sBg = '#e0f2fe'; $sColor = '#0284c7'; }
                        elseif(strpos($sIcon, 'envelope') !== false) { $sBg = '#fee2e2'; $sColor = '#dc2626'; }
                    ?>
                        <a href="<?php echo htmlspecialchars($slink['url']); ?>" target="_blank" class="mf-social-icon" style="background: <?php echo $sBg; ?>; color: <?php echo $sColor; ?>;" title="<?php echo htmlspecialchars($slink['title']); ?>">
                            <i class="<?php echo $slink['icon_class']; ?>"></i>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="https://t.me/support" target="_blank" class="mf-social-icon" style="background: #e0f2fe; color: #0284c7;"><i class="fa-brands fa-telegram"></i></a>
                    <a href="https://wa.me/" target="_blank" class="mf-social-icon" style="background: #dcfce7; color: #16a34a;"><i class="fa-brands fa-whatsapp"></i></a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Column 2: Product Links -->
        <div class="mf-links-col">
            <h4>Product</h4>
            <ul>
                <?php if(empty($footerLinksList)): ?>
                    <li><a href="index.php">Pricing</a></li>
                    <li><a href="api-docs.php" target="_blank">API Access</a></li>
                <?php else: ?>
                    <?php foreach($footerLinksList as $flink): ?>
                        <li><a href="<?php echo htmlspecialchars($flink['url']); ?>" target="_blank"><?php echo htmlspecialchars($flink['title']); ?></a></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Column 3: Developers -->
        <div class="mf-links-col">
            <h4>Developers</h4>
            <ul>
                <li><a href="api-docs.php" target="_blank">API Documentation</a></li>
                <li><a href="index.php">API Keys Manager</a></li>
            </ul>
        </div>

        <!-- Column 4: Legal -->
        <div class="mf-links-col">
            <h4>Legal</h4>
            <ul>
                <?php if(empty($activeLegalPages)): ?>
                    <li><a href="#">Privacy Policy</a></li>
                <?php else: ?>
                    <?php foreach($activeLegalPages as $page): ?>
                        <li><a href="user/page.php?slug=<?php echo htmlspecialchars($page['slug']); ?>" target="_blank"><?php echo htmlspecialchars($page['title']); ?></a></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="mf-divider"></div>

    <div class="otpx-mf-bottom">
        <div>
            <?php echo !empty($siteSettings['footer_copyright']) ? htmlspecialchars($siteSettings['footer_copyright']) : '&copy; ' . date('Y') . ' ' . htmlspecialchars($siteSettings['site_name'] ?? 'OTPSMS') . '. All Rights Reserved.'; ?>
        </div>
        <div class="mf-pay-cards">
            <span style="color:#64748b; font-weight:600; margin-right:4px;">We accept:</span>
            <span class="card-badge"><i class="fa-solid fa-credit-card" style="color:#1d4ed8;"></i> VISA</span>
            <span class="card-badge"><i class="fa-solid fa-credit-card" style="color:#dc2626;"></i> MASTERCARD</span>
            <span class="card-badge"><i class="fa-brands fa-bitcoin" style="color:#f59e0b;"></i> BITCOIN</span>
            <span class="card-badge"><i class="fa-solid fa-diamond" style="color:#6366f1;"></i> ETHEREUM</span>
            <span class="card-badge"><i class="fa-solid fa-shield-halved" style="color:#10b981;"></i> USDT</span>
        </div>
    </div>
</footer>
</body>
</html>