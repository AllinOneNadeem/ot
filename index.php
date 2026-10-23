<?php
// ============================================================
// MAIN PREMIUM LANDING PAGE (index.php) - OTPX CLONE
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

// Redirect to dashboard/admin if already logged in
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
    } catch (Exception $e) {}
}

// Fetch Site Settings dynamically
try {
    $stmtSettings = $pdo->query("SELECT * FROM SiteSettings LIMIT 1");
    $siteSettings = $stmtSettings->fetch(PDO::FETCH_ASSOC) ?: ['site_name' => 'OTPSMS', 'currency_symbol' => '₹'];
} catch (Exception $e) {
    $siteSettings = ['site_name' => 'OTPSMS', 'currency_symbol' => '₹'];
}

$siteName = htmlspecialchars($siteSettings['site_name']);

// Fetch Live Stats for Landing Page
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn() ?: 50000;
    $totalServices = $pdo->query("SELECT COUNT(*) FROM Services")->fetchColumn() ?: 500;
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM Orders")->fetchColumn() ?: 10000000;
} catch (Exception $e) {
    $totalUsers = 50000; $totalServices = 500; $totalOrders = 10000000;
}

// Fetch Popular Services for Pricing Grid
try {
    $popularServices = $pdo->query("SELECT * FROM Services ORDER BY popular DESC, id ASC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $popularServices = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $siteName; ?> - Virtual Numbers for SMS Verification</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f62fe;
            --primary-hover: #004ee6;
            --bg-main: #f8fafc;
            --text-dark: #0f172a;
            --text-gray: #64748b;
            --border-light: #e2e8f0;
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-dark); overflow-x: hidden; -webkit-font-smoothing: antialiased; }
        a { text-decoration: none; }

        /* ================= PRELOADER ================= */
        #preloader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #ffffff; z-index: 99999; display: flex; flex-direction: column; justify-content: center; align-items: center; transition: opacity 0.5s ease; }
        .loader-title { font-size: 32px; font-weight: 900; color: var(--primary); margin-bottom: 15px; letter-spacing: -1px; }
        .loader-line { width: 120px; height: 4px; background: #e2e8f0; border-radius: 4px; overflow: hidden; position: relative; }
        .loader-line::after { content: ''; position: absolute; left: -50%; width: 50%; height: 100%; background: var(--primary); border-radius: 4px; animation: load 1s infinite linear; }
        @keyframes load { 0% { left: -50%; } 100% { left: 100%; } }

        /* ================= NAVBAR & MOBILE MENU FIX ================= */
        .navbar { max-width: 1200px; margin: 0 auto; padding: 20px 20px; display: flex; justify-content: space-between; align-items: center; width: 100%; position: relative; z-index: 100; }
        .nav-logo { display: flex; align-items: center; gap: 10px; font-size: 22px; font-weight: 900; color: #0f172a; letter-spacing: -0.5px; text-decoration: none; }
        .nav-logo img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .nav-logo .text-logo { width: 35px; height: 35px; background: var(--primary); color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 18px; }
        
        /* Desktop Links Spacing & Color Fixed */
        .nav-center { display: flex; gap: 35px; align-items: center; justify-content: center; flex: 1; }
        .nav-center a { color: #64748b !important; font-size: 14px; font-weight: 500; text-decoration: none !important; transition: 0.2s; }
        .nav-center a:hover { color: #0f172a !important; }
        
        .nav-actions { display: flex; gap: 20px; align-items: center; }
        .btn-signin { color: #0f172a !important; font-size: 14px; font-weight: 700; text-decoration: none; }
        .btn-get-started { background: var(--primary); color: white !important; padding: 10px 22px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; box-shadow: 0 4px 10px rgba(0,102,255,0.2); transition: 0.3s; }
        .btn-get-started:hover { background: var(--primary-hover); transform: translateY(-1px); }
        
        /* Mobile Hamburger Button */
        .mobile-menu-btn { display: none; font-size: 24px; background: none; border: none; color: #0f172a; cursor: pointer; padding: 5px; }
        
        /* ================= MOBILE MENU (OTPX CLONE) ================= */
        .mobile-menu-wrapper { 
            display: none; 
            position: fixed; 
            top: 0; left: 0; right: 0; 
            background: white; 
            padding: 20px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.1); 
            z-index: 99999; 
            flex-direction: column; 
            text-align: left; 
        }
        .mobile-menu-wrapper.active { display: flex !important; }
        
        .mobile-menu-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .mobile-menu-header .nav-logo { font-size: 22px; font-weight: 900; color: #0f172a; display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .mobile-menu-header .nav-logo img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .mobile-menu-header .close-btn { background: none; border: none; font-size: 24px; color: #0f172a; cursor: pointer; padding: 5px; }

        .mobile-menu-wrapper a.menu-link { 
            padding: 16px 0; 
            font-size: 15px; 
            font-weight: 500; 
            color: #0f172a !important; 
            border-bottom: 1px solid #e2e8f0; 
            text-decoration: none; 
        }
        
        .mobile-auth-btns { display: flex; gap: 12px; margin-top: 25px; }
        .mobile-auth-btns a { flex: 1; text-align: center; padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 700; text-decoration: none; transition: 0.2s; }
        .mob-btn-signin { background: white; color: #0f172a !important; border: 1px solid #e2e8f0; }
        .mob-btn-start { background: var(--primary); color: white !important; border: 1px solid var(--primary); box-shadow: 0 4px 10px rgba(0,102,255,0.2); }

        /* Testimonials (Reviews) Top Spacing Reduced */
        #reviews { margin-top: -30px; }

        /* ================= HERO SECTION ================= */
        .hero { text-align: center; padding: 40px 20px 30px; position: relative; max-width: 900px; margin: 0 auto; }
        .hero-badge { display: inline-flex; align-items: center; gap: 8px; background: #eff6ff; color: var(--primary); padding: 8px 16px; border-radius: 30px; font-size: 12px; font-weight: 700; border: 1px solid #dbeafe; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(15,98,254,0.05); }
        .hero h1 { font-size: 52px; font-weight: 900; letter-spacing: -1.5px; line-height: 1.1; margin-bottom: 15px; color: var(--text-dark); }
        .hero h1 span { color: var(--primary); }
        .hero p { font-size: 16px; color: var(--text-gray); max-width: 600px; margin: 0 auto 25px; }
        .hero-btns { display: flex; justify-content: center; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
        .btn-primary-hero { background: var(--primary); color: white; padding: 15px 30px; border-radius: 10px; font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 8px; transition: 0.2s; box-shadow: 0 4px 15px rgba(15,98,254,0.2); }
        .btn-primary-hero:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-outline-hero { background: white; color: var(--text-dark); border: 1px solid var(--border-light); padding: 15px 30px; border-radius: 10px; font-weight: 700; font-size: 15px; transition: 0.2s; }
        .btn-outline-hero:hover { border-color: var(--primary); color: var(--primary); }

        /* ================= STATS GRID ================= */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; max-width: 900px; margin: 0 auto 50px; padding: 0 20px; }
        .stat-card { background: white; border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 20px 15px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .stat-card i { color: var(--primary); font-size: 26px; margin-bottom: 10px; }
        .stat-card h3 { font-size: 24px; font-weight: 900; color: var(--primary); margin-bottom: 2px; }
        .stat-card p { font-size: 11px; font-weight: 700; color: var(--text-gray); text-transform: uppercase; letter-spacing: 0.5px; }

        /* ================= MARQUEE ================= */
        .marquee-wrapper { background: white; padding: 20px 0; border-top: 1px solid var(--border-light); border-bottom: 1px solid var(--border-light); overflow: hidden; white-space: nowrap; margin-bottom: 80px; }
        .marquee-content { display: inline-block; animation: marquee 20s linear infinite; }
        .marquee-item { display: inline-flex; align-items: center; gap: 8px; margin: 0 30px; font-size: 14px; font-weight: 600; color: var(--text-gray); }
        .marquee-item i { font-size: 20px; }
        /* Brand Colors */
        .brand-wa { color: #25D366; } .brand-tg { color: #229ED9; } .brand-ig { color: #E1306C; } .brand-fb { color: #1877F2; } .brand-gl { color: #EA4335; } .brand-tw { color: #1DA1F2; } .brand-dc { color: #5865F2; } .brand-az { color: #FF9900; }
        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

        /* ================= SECTION HEADERS ================= */
        .section-container { max-width: 1000px; margin: 0 auto 80px; padding: 0 20px; text-align: center; }
        .sec-label { display: inline-block; background: #eff6ff; color: var(--primary); padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; margin-bottom: 15px; border: 1px solid #dbeafe; }
        .sec-title { font-size: 32px; font-weight: 900; margin-bottom: 10px; color: var(--text-dark); letter-spacing: -0.5px; }
        .sec-desc { color: var(--text-gray); font-size: 15px; margin-bottom: 40px; }

        /* ================= PRICING ================= */
        .pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; text-align: left; }
        .price-card { background: white; border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 20px; display: flex; justify-content: space-between; align-items: center; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .price-card:hover { border-color: var(--primary); box-shadow: 0 10px 25px rgba(15,98,254,0.08); transform: translateY(-2px); }
        .price-left { display: flex; align-items: center; gap: 12px; }
        .icon-box { width: 40px; height: 40px; border-radius: 10px; background: #f1f5f9; display: flex; justify-content: center; align-items: center; font-size: 20px; }
        .price-left h4 { font-size: 15px; font-weight: 800; margin-bottom: 2px; color: var(--text-dark); }
        .price-left p { font-size: 12px; color: var(--text-gray); font-weight: 500; }
        .price-right { text-align: right; }
        .price-right .amt { font-size: 20px; font-weight: 900; color: var(--primary); }
        .price-right .lbl { font-size: 10px; color: var(--text-gray); font-weight: 600; text-transform: uppercase; }

        /* ================= FEATURES ================= */
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: left; }
        .feature-card { background: white; border: 1px solid var(--border-light); padding: 30px; border-radius: var(--radius-lg); transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .f-icon { width: 45px; height: 45px; background: #eff6ff; color: var(--primary); display: flex; justify-content: center; align-items: center; border-radius: 10px; font-size: 20px; margin-bottom: 20px; }
        .feature-card h4 { font-size: 16px; font-weight: 800; margin-bottom: 10px; color: var(--text-dark); }
        .feature-card p { font-size: 13px; color: var(--text-gray); line-height: 1.6; font-weight: 500; }

       /* ================= HOW IT WORKS ================= */
        .steps-wrapper { display: flex; justify-content: center; align-items: flex-start; gap: 40px; }
        .step-box { flex: 1; max-width: 250px; text-align: center; }
        .step-icon { width: 75px; height: 75px; margin: 0 auto 20px; background: white; border: 1px solid var(--border-light); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 26px; color: var(--primary); position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
        .step-badge { position: absolute; top: -5px; right: -5px; background: var(--primary); color: white; width: 24px; height: 24px; border-radius: 50%; font-size: 11px; font-weight: 900; display: flex; justify-content: center; align-items: center; border: 3px solid white; }
        .step-box h4 { font-size: 16px; font-weight: 800; margin-bottom: 8px; color: var(--text-dark); }
        .step-box p { font-size: 13px; color: var(--text-gray); }
        .step-arrow { color: #cbd5e1; font-size: 20px; margin-top: 25px; }

        /* सिर्फ मोबाइल व्यू के लिए कंटेनर की सेटिंग */
        @media(max-width: 768px) {
            .steps-wrapper { 
                flex-direction: column; 
                align-items: center; 
                gap: 20px; 
            }
            .step-box { 
                background: #ffffff; 
                border: 1px solid var(--border-light); 
                border-radius: 16px; 
                padding: 30px 20px; 
                max-width: 100%; 
                width: 100%; 
                box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
            }
            .step-arrow { 
                display: none; 
            }
        }

        /* ================= TESTIMONIALS ================= */
        .reviews-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: left; }
        .review-card { background: white; border: 1px solid var(--border-light); padding: 30px; border-radius: var(--radius-lg); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .stars { color: var(--primary); font-size: 13px; margin-bottom: 15px; }
        .review-text { font-size: 14px; color: var(--text-dark); font-style: italic; margin-bottom: 20px; line-height: 1.6; font-weight: 500; }
        .reviewer { font-weight: 800; font-size: 14px; color: var(--text-dark); }
        .reviewer span { display: block; font-size: 12px; color: var(--text-gray); font-weight: 500; margin-top: 2px; }

        /* ================= CTA BANNER ================= */
        .cta-banner { background: linear-gradient(135deg, #e0e7ff 0%, #eff6ff 100%); border: 1px solid #bfdbfe; border-radius: 20px; padding: 50px 20px; text-align: center; max-width: 850px; margin: 0 auto; }
        .cta-icon { font-size: 36px; color: var(--primary); margin-bottom: 15px; }
        .cta-banner h2 { font-size: 28px; font-weight: 900; margin-bottom: 10px; color: var(--text-dark); }
        .cta-banner p { color: var(--text-gray); font-size: 15px; margin-bottom: 30px; font-weight: 500; }

        /* ================= FOOTER ================= */
        .otpx-master-footer { width: 100%; background: #ffffff; border-top: 1px solid var(--border-light); padding: 50px 20px 20px; text-align: left; }
        .otpx-mf-grid { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 40px; }
        .mf-brand-col { display: flex; flex-direction: column; align-items: flex-start; }
        .mf-logo-row { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 800; margin-bottom: 12px; color: var(--text-dark); }
        .mf-logo-row img { width: 32px; height: 32px; border-radius: 50%; }
        .mf-desc { color: var(--text-gray); font-size: 13px; line-height: 1.6; margin-bottom: 15px; max-width: 300px; text-align: left; }
        
        /* Premium Pills */
        .mf-pills-row { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 15px; justify-content: flex-start; }
        .mf-pill { background: #f8fafc; color: #334155; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 5px; }
        
        .mf-social-icons { display: flex; gap: 8px; justify-content: flex-start; }
        .mf-social-icon { width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; color: var(--text-gray); display: flex; justify-content: center; align-items: center; font-size: 14px; transition: 0.2s; }
        .mf-social-icon:hover { background: var(--primary); color: white; }
        
        .mf-links-col { text-align: left; }
        .mf-links-col h4 { font-size: 12px; font-weight: 800; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-dark); }
        .mf-links-col ul { list-style: none; display: flex; flex-direction: column; gap: 10px; padding: 0; margin: 0; align-items: flex-start; }
        .mf-links-col a { color: var(--text-gray); font-size: 13px; font-weight: 500; transition: 0.2s; text-decoration: none; }
        .mf-links-col a:hover { color: var(--primary); padding-left: 2px; }
        
        .mf-divider { max-width: 1200px; margin: 0 auto; height: 1px; background: var(--border-light); margin-bottom: 20px; }
        .otpx-mf-bottom { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; color: var(--text-gray); font-size: 12px; font-weight: 500; padding-bottom: 10px; }
        .mf-pay-cards { display: flex; gap: 6px; flex-wrap: wrap; }
        .card-badge { border: 1px solid var(--border-light); padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 10px; color: var(--text-dark); display: inline-flex; align-items: center; gap: 4px; background: white; }

        @media(max-width: 992px) {
            .otpx-mf-grid { grid-template-columns: 1fr 1fr; gap: 30px; }
        }
        @media(max-width: 600px) {
            .otpx-mf-grid { grid-template-columns: 1fr; text-align: left; gap: 25px; }
            .otpx-mf-bottom { flex-direction: column; gap: 15px; text-align: center; }
            .mf-pay-cards { justify-content: center; }
        }

        /* ================= MOBILE RESPONSIVE ================= */
        .mobile-menu-wrapper { display: none; position: absolute; top: 70px; left: 20px; right: 20px; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid var(--border-light); padding: 10px; z-index: 99; flex-direction: column; }
        .mobile-menu-wrapper a { padding: 12px; font-size: 15px; font-weight: 600; color: var(--text-dark); border-bottom: 1px solid var(--border-light); text-align: center; }
        .mobile-menu-wrapper a:last-child { border-bottom: none; color: var(--primary); }

        @media(max-width: 992px) {
            .pricing-grid, .features-grid, .reviews-grid { grid-template-columns: 1fr 1fr; }
            .mf-grid { grid-template-columns: 1fr 1fr; gap: 30px; }
        }
        @media(max-width: 768px) {
            .nav-center, .nav-actions { display: none !important; }
            .mobile-menu-btn { display: block !important; }
        }
        @media(max-width: 600px) {
            .hero { padding: 20px 20px 25px; } 
            .hero-btns { flex-direction: column; padding: 0 10px; margin-bottom: 30px; }
            .hero-btns a { width: 100%; justify-content: center; }
            
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; padding: 0 15px; margin-bottom: 40px; }
            .stat-card { padding: 15px 10px; }
            .stat-card i { font-size: 22px; margin-bottom: 8px; }
            .stat-card h3 { font-size: 20px; }

            /* Pricing Grid Mobile Fix (List View) */
            .pricing-grid { grid-template-columns: 1fr; gap: 12px; }
            .price-card { flex-direction: row !important; justify-content: space-between !important; padding: 15px 20px !important; align-items: center !important; text-align: left !important; }
            .price-left { flex-direction: row !important; text-align: left !important; gap: 15px !important; margin: 0 !important; }
            .price-right { text-align: right !important; margin: 0 !important; }
            
            /* Other grids */
            .features-grid, .reviews-grid, .mf-grid { grid-template-columns: 1fr; text-align: center; }
            .mf-bottom { flex-direction: column; gap: 15px; text-align: center; }
            .mf-brand-col { align-items: center; text-align: center; }
            .mf-desc { text-align: center; }
            .mf-col ul { align-items: center; }
        }
    </style>
</head>
<body>

    <!-- Dynamic Preloader -->
    <div id="preloader">
        <div class="loader-title"><?php echo $siteName; ?></div>
        <div class="loader-line"></div>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <a href="index.php" class="nav-logo">
            <?php if(!empty($siteSettings['site_logo'])): ?>
                <img src="<?php echo htmlspecialchars($siteSettings['site_logo']); ?>" alt="Logo">
            <?php else: ?>
                <div class="text-logo"><?php echo substr($siteName, 0, 1); ?></div>
            <?php endif; ?>
            <span><?php echo $siteName; ?></span>
        </a>
        
        <div class="nav-center">
            <a href="#features">Features</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#pricing">Pricing</a>
            <a href="#reviews">Reviews</a>
        </div>
        
        <div class="nav-actions">
            <a href="login.php" class="btn-signin">Sign In</a>
            <a href="login.php" class="btn-get-started">Get Started Free</a>
        </div>
        
        <button class="mobile-menu-btn" onclick="toggleMenu()"><i class="fa-solid fa-bars"></i></button>
    </nav>

<!-- Mobile Menu Hidden Box (OTPX Style) -->
    <div class="mobile-menu-wrapper" id="mobileMenu">
        <div class="mobile-menu-header">
            <a href="index.php" class="nav-logo">
                <?php if(!empty($siteSettings['site_logo'])): ?>
                    <img src="<?php echo htmlspecialchars($siteSettings['site_logo']); ?>" alt="Logo">
                <?php else: ?>
                    <div style="width:35px; height:35px; background:var(--primary); color:white; border-radius:50%; display:flex; justify-content:center; align-items:center; font-size:18px;"><?php echo substr($siteName, 0, 1); ?></div>
                <?php endif; ?>
                <span><?php echo $siteName; ?></span>
            </a>
            <button class="close-btn" onclick="toggleMenu()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <a href="#features" class="menu-link" onclick="toggleMenu()">Features</a>
        <a href="#how-it-works" class="menu-link" onclick="toggleMenu()">How It Works</a>
        <a href="#pricing" class="menu-link" onclick="toggleMenu()">Pricing</a>
        <a href="#reviews" class="menu-link" onclick="toggleMenu()">Reviews</a>
        
        <div class="mobile-auth-btns">
            <a href="login.php" class="mob-btn-signin">Sign In</a>
            <a href="login.php" class="mob-btn-start">Get Started</a>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-badge"><i class="fa-solid fa-bolt"></i> No Subscription - Pay Only Per OTP</div>
        <h1>Virtual Numbers for<br><span>SMS Verification</span></h1>
        <p>Get instant OTP codes for WhatsApp, Telegram, Google & 500+ services starting from just <?php echo $siteSettings['currency_symbol']; ?>2 per verification. No monthly fees.</p>
        
        <div class="hero-btns">
            <a href="login.php" class="btn-primary-hero">Start Verifying Now <i class="fa-solid fa-arrow-right"></i></a>
            <a href="login.php" class="btn-outline-hero">Login</a>
        </div>
    </section>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fa-solid fa-earth-americas"></i>
            <h3>150+</h3>
            <p>Countries</p>
        </div>
        <div class="stat-card">
            <i class="fa-solid fa-server"></i>
            <h3><?php echo number_format($totalServices); ?>+</h3>
            <p>Services</p>
        </div>
        <div class="stat-card">
            <i class="fa-solid fa-users"></i>
            <h3><?php echo number_format($totalUsers); ?>+</h3>
            <p>Happy Users</p>
        </div>
        <div class="stat-card">
            <i class="fa-regular fa-message"></i>
            <h3><?php echo number_format($totalOrders); ?>+</h3>
            <p>OTPs Delivered</p>
        </div>
    </div>

    <!-- Colored Marquee -->
    <div class="marquee-wrapper">
        <div class="marquee-content">
            <span class="marquee-item"><i class="fa-brands fa-whatsapp brand-wa"></i> <span>WhatsApp</span></span>
            <span class="marquee-item"><i class="fa-brands fa-telegram brand-tg"></i> <span>Telegram</span></span>
            <span class="marquee-item"><i class="fa-brands fa-instagram brand-ig"></i> <span>Instagram</span></span>
            <span class="marquee-item"><i class="fa-brands fa-facebook brand-fb"></i> <span>Facebook</span></span>
            <span class="marquee-item"><i class="fa-brands fa-google brand-gl"></i> <span>Google</span></span>
            <span class="marquee-item"><i class="fa-brands fa-twitter brand-tw"></i> <span>Twitter</span></span>
            <span class="marquee-item"><i class="fa-brands fa-discord brand-dc"></i> <span>Discord</span></span>
            <span class="marquee-item"><i class="fa-brands fa-amazon brand-az"></i> <span>Amazon</span></span>
            <!-- Duplicated for infinite scroll -->
            <span class="marquee-item"><i class="fa-brands fa-whatsapp brand-wa"></i> <span>WhatsApp</span></span>
            <span class="marquee-item"><i class="fa-brands fa-telegram brand-tg"></i> <span>Telegram</span></span>
            <span class="marquee-item"><i class="fa-brands fa-instagram brand-ig"></i> <span>Instagram</span></span>
            <span class="marquee-item"><i class="fa-brands fa-facebook brand-fb"></i> <span>Facebook</span></span>
            <span class="marquee-item"><i class="fa-brands fa-google brand-gl"></i> <span>Google</span></span>
            <span class="marquee-item"><i class="fa-brands fa-twitter brand-tw"></i> <span>Twitter</span></span>
            <span class="marquee-item"><i class="fa-brands fa-discord brand-dc"></i> <span>Discord</span></span>
            <span class="marquee-item"><i class="fa-brands fa-amazon brand-az"></i> <span>Amazon</span></span>
        </div>
    </div>

<!-- ============================================================
         HIGHLIGHTS BAR CSS (Fixed for Desktop & Mobile 2x2)
    ============================================================ -->
    <style>
        .highlights-container { max-width: 1200px; margin: 0 auto 80px; padding: 0 20px; }
        .highlights-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .hl-card { background: white; border: 1px solid var(--border-light); border-radius: 12px; padding: 18px 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: 0.3s; }
        .hl-card:hover { border-color: var(--primary); box-shadow: 0 8px 20px rgba(0,102,255,0.06); transform: translateY(-2px); }
        .hl-icon { width: 42px; height: 42px; background: #eff6ff; color: var(--primary); display: flex; justify-content: center; align-items: center; border-radius: 10px; font-size: 18px; flex-shrink: 0; }
        .hl-text h4 { font-size: 15px; font-weight: 800; color: var(--text-dark); margin-bottom: 2px; line-height: 1.2; }
        .hl-text p { font-size: 12px; color: var(--text-gray); font-weight: 500; margin: 0; line-height: 1.2; }

        /* Responsive Fixes */
        @media(max-width: 992px) { 
            .highlights-container { margin-bottom: 50px; }
        }
        @media(max-width: 768px) { 
            /* मोबाइल और टैबलेट के लिए 2x2 ग्रिड (2 कॉलम्स) */
            .highlights-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 12px; } 
            .hl-card { padding: 12px; gap: 10px; flex-direction: column; text-align: center; justify-content: center; }
            .hl-icon { width: 36px; height: 36px; font-size: 16px; margin-bottom: 5px; }
            .hl-text h4 { font-size: 14px; }
            .hl-text p { font-size: 11px; }
        }
    </style>

    <div class="highlights-container">
        <div class="highlights-grid">
            <div class="hl-card">
                <div class="hl-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                <div class="hl-text">
                    <h4>Lowest Prices</h4>
                    <p>Starting from <?php echo $siteSettings['currency_symbol']; ?>2 per OTP</p>
                </div>
            </div>
            <div class="hl-card">
                <div class="hl-icon"><i class="fa-solid fa-bolt"></i></div>
                <div class="hl-text">
                    <h4>Lightning Fast</h4>
                    <p>OTP in under 30 seconds</p>
                </div>
            </div>
            <div class="hl-card">
                <div class="hl-icon"><i class="fa-solid fa-lock"></i></div>
                <div class="hl-text">
                    <h4>Secure & Private</h4>
                    <p>No data logging</p>
                </div>
            </div>
            <div class="hl-card">
                <div class="hl-icon"><i class="fa-solid fa-headset"></i></div>
                <div class="hl-text">
                    <h4>24/7 Support</h4>
                    <p>Always here to help</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pricing Section -->
    <section class="section-container" id="pricing">
        <div class="sec-label"><i class="fa-solid fa-chart-line"></i> Most Popular</div>
        <h2 class="sec-title">Service Pricing</h2>
        <p class="sec-desc">Pay only for what you use — no subscriptions required</p>

        <div class="pricing-grid">
            <?php if(!empty($popularServices)): foreach($popularServices as $serv): ?>
                <div class="price-card">
                    <div class="price-left">
                        <?php if($serv['logo']): ?>
                            <img src="<?php echo htmlspecialchars($serv['logo']); ?>" style="width:40px; height:40px; border-radius:10px;">
                        <?php else: ?>
                            <div class="icon-box"><i class="fa-solid fa-comment-sms"></i></div>
                        <?php endif; ?>
                        <div>
                            <h4><?php echo htmlspecialchars($serv['name']); ?></h4>
                            <p>Global</p>
                        </div>
                    </div>
                    <div class="price-right">
                        <div class="amt"><?php echo $siteSettings['currency_symbol']; ?><?php echo number_format($serv['price'], 2); ?></div>
                        <div class="lbl">per OTP</div>
                    </div>
                </div>
           <?php endforeach; else: ?>
                <!-- Hardcoded Default Services (Low Price) -->
                <div class="price-card"><div class="price-left"><div class="icon-box" style="color:#25d366; background:#dcfce7;"><i class="fa-brands fa-whatsapp"></i></div><div><h4>WhatsApp</h4><p>India</p></div></div><div class="price-right"><div class="amt">₹6</div><div class="lbl">per OTP</div></div></div>
                <div class="price-card"><div class="price-left"><div class="icon-box" style="color:#1877f2; background:#dbeafe;"><i class="fa-brands fa-facebook"></i></div><div><h4>Facebook</h4><p>Global</p></div></div><div class="price-right"><div class="amt">₹5</div><div class="lbl">per OTP</div></div></div>
                <div class="price-card"><div class="price-left"><div class="icon-box" style="color:#e4405f; background:#fce7f3;"><i class="fa-brands fa-instagram"></i></div><div><h4>Instagram</h4><p>India</p></div></div><div class="price-right"><div class="amt">₹4</div><div class="lbl">per OTP</div></div></div>
                <div class="price-card"><div class="price-left"><div class="icon-box" style="color:#f59e0b; background:#fef3c7;"><i class="fa-solid fa-cart-shopping"></i></div><div><h4>JioMart</h4><p>India</p></div></div><div class="price-right"><div class="amt">₹3</div><div class="lbl">per OTP</div></div></div>
                <div class="price-card"><div class="price-left"><div class="icon-box" style="color:#8b5cf6; background:#e0e7ff;"><i class="fa-solid fa-tv"></i></div><div><h4>SonyLIV / TV</h4><p>India</p></div></div><div class="price-right"><div class="amt">₹4</div><div class="lbl">per OTP</div></div></div>
                <div class="price-card"><div class="price-left"><div class="icon-box" style="color:#ef4444; background:#fee2e2;"><i class="fa-solid fa-gamepad"></i></div><div><h4>Dream11</h4><p>India</p></div></div><div class="price-right"><div class="amt">₹3</div><div class="lbl">per OTP</div></div></div>
            <?php endif; ?>
        </div>
        <p style="font-size:13px; color:var(--text-gray); margin-bottom: 20px;">500+ more services available with prices starting from <?php echo $siteSettings['currency_symbol']; ?>2</p>
        <a href="login.php" class="btn-get-started" style="display:inline-flex;">View All Services <i class="fa-solid fa-arrow-right" style="margin-left:5px;"></i></a>
    </section>

    <!-- Why Choose Us -->
    <section class="section-container" id="features">
        <h2 class="sec-title">Why Choose <?php echo $siteName; ?>?</h2>
        <p class="sec-desc">Everything you need for seamless SMS verification</p>

        <div class="features-grid">
            <div class="feature-card">
                <div class="f-icon"><i class="fa-solid fa-globe"></i></div>
                <h4>150+ Countries</h4>
                <p>Access virtual numbers from countries worldwide including USA, UK, India, Russia, and more.</p>
            </div>
            <div class="feature-card">
                <div class="f-icon"><i class="fa-solid fa-bolt"></i></div>
                <h4>Instant Delivery</h4>
                <p>Receive OTP codes within 5-30 seconds, fastest in the industry with 99.9% success rate.</p>
            </div>
            <div class="feature-card">
                <div class="f-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <h4>100% Privacy</h4>
                <p>Your personal number stays private. We never store or share your confidential data.</p>
            </div>
            <div class="feature-card">
                <div class="f-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                <h4>Pay Per Use</h4>
                <p>No subscriptions – Only pay for what you use, starting from just <?php echo $siteSettings['currency_symbol']; ?>2 per OTP.</p>
            </div>
            <div class="feature-card">
                <div class="f-icon"><i class="fa-solid fa-server"></i></div>
                <h4>99.9% Success Rate</h4>
                <p>Reliable infrastructure with automatic retry and backup numbers for guaranteed delivery.</p>
            </div>
            <div class="feature-card">
                <div class="f-icon"><i class="fa-solid fa-code"></i></div>
                <h4>Developer API</h4>
                <p>RESTful API with clear documentation to integrate automated SMS verification in your app.</p>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="section-container" id="how-it-works">
        <h2 class="sec-title">How It Works</h2>
        <p class="sec-desc">Get verified in three simple steps</p>

        <div class="steps-wrapper">
            <div class="step-box">
                <div class="step-icon"><i class="fa-solid fa-globe"></i><div class="step-badge">01</div></div>
                <h4>Select Service</h4>
                <p>Choose from 500+ supported services and 150+ countries worldwide.</p>
            </div>
            <i class="fa-solid fa-chevron-right step-arrow"></i>
            <div class="step-box">
                <div class="step-icon"><i class="fa-solid fa-phone"></i><div class="step-badge">02</div></div>
                <h4>Get Virtual Number</h4>
                <p>Instantly receive a temporary phone number to use on your desired app.</p>
            </div>
            <i class="fa-solid fa-chevron-right step-arrow"></i>
            <div class="step-box">
                <div class="step-icon"><i class="fa-solid fa-comment-dots"></i><div class="step-badge">03</div></div>
                <h4>Receive OTP</h4>
                <p>Get your verification code directly on your screen in seconds.</p>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="section-container" id="reviews">
        <h2 class="sec-title">Trusted by Thousands</h2>
        <p class="sec-desc">See what our users have to say</p>

        <div class="reviews-grid">
            <div class="review-card">
                <div class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
                <div class="review-text">"Best OTP service for developers. API is clean, prices are unbeatable, and support responds within minutes."</div>
                <div class="reviewer">Liam A.<span>App Developer</span></div>
            </div>
            <div class="review-card">
                <div class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
                <div class="review-text">"I manage 50+ social accounts. <?php echo $siteName; ?> saves me hours every week with instant verifications."</div>
                <div class="reviewer">Emma M.<span>Digital Marketer</span></div>
            </div>
            <div class="review-card">
                <div class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
                <div class="review-text">"We integrated their API in under an hour. Handles 1000+ verifications daily without any issues."</div>
                <div class="reviewer">Noah D.<span>Startup Founder</span></div>
            </div>
        </div>
    </section>

    <!-- Bottom CTA Banner -->
    <section class="section-container" style="padding-top:0; padding-bottom:0;">
        <div class="cta-banner">
            <i class="fa-solid fa-bolt cta-icon"></i>
            <h2>Start Verifying Today</h2>
            <p>Join <?php echo number_format($totalUsers); ?>+ users. No subscription needed - just add balance and start using.</p>
            <div style="display:flex; justify-content:center; gap:15px; flex-wrap:wrap;">
                <a href="login.php" class="btn-primary-hero">Create Free Account <i class="fa-solid fa-arrow-right"></i></a>
                <a href="login.php" class="btn-outline-hero" style="padding: 14px 25px;">Sign In</a>
            </div>
            <div style="font-size:12px; color:#64748b; margin-top:20px; font-weight:500;">Free to sign up. Add balance when ready.</div>
        </div>
    </section>

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
    
    <!-- Scripts -->
    <script>
        // Remove Preloader after 0.8s
        window.addEventListener('load', function() {
            setTimeout(function() {
                var preloader = document.getElementById('preloader');
                preloader.style.opacity = '0';
                setTimeout(() => preloader.style.display = 'none', 500);
            }, 800);
        });

        // Hamburger Menu Logic (Mobile)
        document.querySelector('.mobile-menu-btn').style.display = window.innerWidth <= 768 ? 'block' : 'none';
        window.addEventListener('resize', () => {
            document.querySelector('.mobile-menu-btn').style.display = window.innerWidth <= 768 ? 'block' : 'none';
            if(window.innerWidth > 768) document.getElementById('mobileMenu').classList.remove('active');
        });
    </script>
    
    <!-- Scripts -->
    <script>
        // Remove Preloader after 0.8s
        window.addEventListener('load', function() {
            setTimeout(function() {
                var preloader = document.getElementById('preloader');
                if(preloader) {
                    preloader.style.opacity = '0';
                    setTimeout(() => preloader.style.display = 'none', 500);
                }
            }, 800);
        });

        // Hamburger Mobile Menu Toggle Fix
        function toggleMenu() {
            var menu = document.getElementById('mobileMenu');
            if (menu) {
                menu.classList.toggle('active');
            }
        }
    </script>
</body>
</html>