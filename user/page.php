<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db.php';
require_once '../includes/functions.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: dashboard.php");
    exit();
}

// डेटाबेस से पेज का डेटा सुरक्षित तरीके से निकालें
try {
    $stmt = $pdo->prepare("SELECT * FROM Pages WHERE slug = ? AND status = 1 LIMIT 1");
    $stmt->execute([$slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $page = null;
}

if (!$page) {
    http_response_code(404);
    $pageTitle = "Page Not Found";
    $pageContent = "<div style='text-align:center; padding:40px;'><i class='fa-solid fa-triangle-exclamation' style='font-size:48px; color:#ef4444; margin-bottom:15px;'></i><h2>404 - Page Not Found</h2><p style='color:var(--text-muted); margin-bottom:20px;'>The page you are looking for does not exist or has been disabled.</p><a href='dashboard.php' style='background:var(--accent); color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:600;'>Go to Dashboard</a></div>";
    $pageIcon = "fa-solid fa-ban";
} else {
    $pageTitle = $page['title'];
    $pageContent = $page['content']; 
    
    // स्लग के हिसाब से ऑटोमैटिक प्रीमियम आइकन सेट करना
    $pageIcon = "fa-solid fa-file-shield";
    $s = strtolower($slug);
    if(strpos($s, 'privacy') !== false) $pageIcon = "fa-solid fa-user-shield";
    elseif(strpos($s, 'term') !== false) $pageIcon = "fa-solid fa-scale-balanced";
    elseif(strpos($s, 'about') !== false) $pageIcon = "fa-solid fa-circle-info";
    elseif(strpos($s, 'refund') !== false) $pageIcon = "fa-solid fa-rotate-left";
    elseif(strpos($s, 'cookie') !== false) $pageIcon = "fa-solid fa-cookie-bite";
}

// Site Settings & Footer Data
try {
    $stmtSettings = $pdo->query("SELECT * FROM SiteSettings LIMIT 1");
    $siteSettings = $stmtSettings->fetch(PDO::FETCH_ASSOC) ?: ['site_name' => 'OTPx'];

    $footerLinksList = $pdo->query("SELECT * FROM FooterLinks ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $activeLegalPages = $pdo->query("SELECT * FROM Pages WHERE status = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $footerSupportLinks = $pdo->query("SELECT * FROM SupportLinks ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $siteSettings = ['site_name' => 'OTPx'];
    $footerLinksList = [];
    $activeLegalPages = [];
    $footerSupportLinks = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="description" content="<?php echo htmlspecialchars($page['meta_description'] ?? ''); ?>">
<meta name="keywords" content="<?php echo htmlspecialchars($page['meta_keywords'] ?? ''); ?>">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteSettings['site_name']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #f4f7fe;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #475569;
            --border-color: #e2e8f0;
            --accent: #2563eb;
            --accent-light: #eff6ff;
            --radius: 16px;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); line-height: 1.7; display: flex; flex-direction: column; min-height: 100vh; }

        /* Top Minimal Navbar */
        .page-navbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: 0 30px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .page-brand {
            font-size: 20px;
            font-weight: 800;
            color: var(--accent);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .back-dashboard-btn {
            background: var(--accent-light);
            color: var(--accent);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }
        .back-dashboard-btn:hover { background: var(--accent); color: white; }

        /* Main Wrapper Card */
        .page-wrapper {
            max-width: 1000px;
            width: 92%;
            margin: 40px auto;
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            overflow: hidden;
            flex: 1;
        }

        /* Colorful Premium Header */
        .page-header-banner {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 45px 40px;
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }
        .page-banner-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 28px;
            color: #60a5fa;
            flex-shrink: 0;
            backdrop-filter: blur(4px);
        }
        .page-banner-title h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
            color: #ffffff;
        }
        .page-banner-title p {
            color: #94a3b8;
            font-size: 13.5px;
            font-weight: 500;
        }

        /* Content Body Styling (Fixing text wrap and line spacing) */
        .page-content-body {
            padding: 45px 40px;
            color: var(--text-muted);
            font-size: 15px;
            white-space: pre-line; /* एडमिन के दिए गए पैराग्राफ और एंटर्स को सुरक्षित रखेगा */
        }
        .page-content-body h2, .page-content-body h3 {
            color: var(--text-main);
            margin-top: 25px;
            margin-bottom: 12px;
            font-weight: 700;
        }
        .page-content-body p {
            margin-bottom: 16px;
        }

        /* Master Footer Styles */
        .otpx-master-footer {
            background: #ffffff;
            border-top: 1px solid #eaeaea;
            color: #111827;
            font-family: 'Inter', -apple-system, sans-serif;
            padding: 60px 20px 30px;
            margin-top: auto;
            width: 100%;
        }
        .otpx-mf-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 50px;
        }
        .mf-brand-col { display: flex; flex-direction: column; align-items: flex-start; }
        .mf-logo-row { display: flex; align-items: center; gap: 12px; font-size: 22px; font-weight: 800; color: #111827; margin-bottom: 15px; }
        .mf-logo-row img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .mf-desc { color: #4b5563; font-size: 14px; line-height: 1.6; margin-bottom: 20px; max-width: 340px; }
        .mf-pills-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
        .mf-pill { background: #f3f4f6; color: #1f2937; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid #e5e7eb; display: inline-flex; align-items: center; gap: 6px; }
        .mf-social-icons { display: flex; gap: 12px; align-items: center; margin-top: 5px; }
        .mf-social-icon { width: 36px; height: 36px; border-radius: 50%; background: #f3f4f6; color: #374151; display: flex; justify-content: center; align-items: center; font-size: 15px; text-decoration: none; transition: 0.2s; }
        .mf-social-icon:hover { background: #2563eb; color: white; }

        .mf-links-col h4 { font-size: 13px; font-weight: 800; color: #111827; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }
        .mf-links-col ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
        .mf-links-col ul li a { color: #4b5563; font-size: 14px; font-weight: 500; text-decoration: none; transition: 0.2s; }
        .mf-links-col ul li a:hover { color: #2563eb; padding-left: 3px; }

        .mf-divider { max-width: 1200px; margin: 0 auto; height: 1px; background: #eaeaea; margin-bottom: 30px; }
        .otpx-mf-bottom { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; font-size: 13px; color: #6b7280; }
        .mf-pay-cards { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .card-badge { background: #ffffff; border: 1px solid #e5e7eb; padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 11px; color: #1f2937; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: inline-flex; align-items: center; gap: 5px; }

        @media(max-width: 992px) { .otpx-mf-grid { grid-template-columns: 1fr 1fr; gap: 30px; } }
        @media(max-width: 600px) {
            .otpx-mf-grid { grid-template-columns: 1fr; gap: 25px; }
            .otpx-mf-bottom { flex-direction: column; text-align: center; justify-content: center; }
            .mf-pay-cards { justify-content: center; }
            .page-header-banner { padding: 30px 20px; flex-direction: column; text-align: center; }
            .page-content-body { padding: 30px 20px; }
        }
    </style>
</head>
<body>

    <!-- Minimal Header Navigation -->
    <div class="page-navbar">
        <a href="dashboard.php" class="page-brand">
            <i class="fa-solid fa-shield-halved"></i> <?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPx'); ?>
        </a>
        <a href="dashboard.php" class="back-dashboard-btn">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
    </div>

    <!-- Main Content Container -->
    <div class="page-wrapper">
        <!-- Premium Header Banner with Dynamic Icon -->
        <div class="page-header-banner">
            <div class="page-banner-icon">
                <i class="<?php echo $pageIcon; ?>"></i>
            </div>
            <div class="page-banner-title">
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <p>Last updated &bull; Official <?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPx'); ?> Guidelines</p>
            </div>
        </div>

        <!-- Rendered Content with proper spacing -->
        <div class="page-content-body">
            <?php echo $pageContent; ?>
        </div>
    </div>

    <!-- Master Footer -->
    <footer class="otpx-master-footer">
        <div class="otpx-mf-grid">
            <div class="mf-brand-col">
                <div class="mf-logo-row">
                    <?php if(!empty($siteSettings['site_logo'])): ?>
                        <img src="<?php echo htmlspecialchars($siteSettings['site_logo']); ?>">
                    <?php else: ?>
                        <div style="width:36px; height:36px; background:#2563eb; color:white; border-radius:50%; display:flex; justify-content:center; align-items:center;">O</div>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPx'); ?></span>
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
                            $sBg = '#f3f4f6'; $sColor = '#374151';
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

            <div class="mf-links-col">
                <h4>Product</h4>
                <ul>
                    <?php if(empty($footerLinksList)): ?>
                        <li><a href="dashboard.php">Pricing</a></li>
                        <li><a href="../api-docs.php" target="_blank">API Access</a></li>
                    <?php else: ?>
                        <?php foreach($footerLinksList as $flink): ?>
                            <li><a href="<?php echo htmlspecialchars($flink['url']); ?>" target="_blank"><?php echo htmlspecialchars($flink['title']); ?></a></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="mf-links-col">
                <h4>Developers</h4>
                <ul>
                    <li><a href="../api-docs.php" target="_blank">API Documentation</a></li>
                    <li><a href="dashboard.php?tab=profile">API Keys Manager</a></li>
                </ul>
            </div>

            <div class="mf-links-col">
                <h4>Legal</h4>
                <ul>
                    <?php if(empty($activeLegalPages)): ?>
                        <li><a href="#">Privacy Policy</a></li>
                    <?php else: ?>
                        <?php foreach($activeLegalPages as $page): ?>
                            <li><a href="page.php?slug=<?php echo htmlspecialchars($page['slug']); ?>"><?php echo htmlspecialchars($page['title']); ?></a></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="mf-divider"></div>

        <div class="otpx-mf-bottom">
            <div>
                <?php echo !empty($siteSettings['footer_copyright']) ? htmlspecialchars($siteSettings['footer_copyright']) : '&copy; ' . date('Y') . ' ' . htmlspecialchars($siteSettings['site_name'] ?? 'OTPx') . '. All Rights Reserved.'; ?>
            </div>
            <div class="mf-pay-cards">
                <span style="color:#6b7280; font-weight:600; margin-right:4px;">We accept:</span>
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