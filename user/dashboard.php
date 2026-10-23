<?php
// ============================================================
// USER DASHBOARD - COMPLETE FULL CODE WITH OTPX UI & BUG FIXES
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
require_once '../includes/functions.php';

// Get user data
$stmt = $pdo->prepare("SELECT id, name, email, balance, role FROM Users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

$isAdmin = ($user['role'] === 'admin');
$currency = '₹';
$userName = $user['name'] ?? 'User';
$userEmail = $user['email'];
$userBalance = number_format($user['balance'], 2);
$userInitial = strtoupper($userName[0]);
$activeTab = $_GET['tab'] ?? 'dashboard';

// Fetch payment settings
try {
    $stmtPayments = $pdo->query("SELECT * FROM SitePayments LIMIT 1");
    $sitePayments = $stmtPayments->fetch(PDO::FETCH_ASSOC) ?: [
        'paytm_upi' => 'your_upi@paytm',
        'min_deposit' => 10.00,
        'paytm_qr' => '',
        'paytm_instructions' => '',
        'bharatpe_upi' => '',
        'bharatpe_qr' => '',
        'bharatpe_instructions' => '',
        'trx_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        'crypto_instructions' => ''
    ];
} catch (Exception $e) {
    $sitePayments = ['paytm_upi' => 'your_upi@paytm', 'min_deposit' => 10.00];
}

// Fetch services (Dynamic from Admin Panel)
try {
    $services = $pdo->query("
        SELECT s.*, sv.name as server_name, sv.country_name, sv.country_emoji, sv.id as server_id 
        FROM Services s 
        LEFT JOIN Servers sv ON s.server_id = sv.id 
        WHERE sv.status = 1 OR sv.status IS NULL
        ORDER BY sv.country_name ASC, s.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services = [];
}

$groupedServices = [];
foreach ($services as $service) {
    $serverId = $service['server_id'] ?? 0;
    if (!isset($groupedServices[$serverId])) {
        $groupedServices[$serverId] = [];
    }
    $groupedServices[$serverId][] = $service;
}

try {
    $serversList = $pdo->query("
        SELECT DISTINCT s.id, s.name, s.country_name, s.country_emoji,
        (SELECT COUNT(*) FROM Services WHERE server_id = s.id) as service_count
        FROM Servers s WHERE s.status = 1 ORDER BY s.country_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $serversList = [];
}

// Fetch Active Broadcasts for the User (FIXED)
try {
    $userTarget = ($user['role'] === 'admin') ? "('all', 'admins', 'active')" : "('all', 'active')";
    $stmtBc = $pdo->query("
        SELECT * FROM Broadcasts 
        WHERE status = 1 
        AND target IN $userTarget 
        AND (start_date IS NULL OR start_date = '0000-00-00 00:00:00' OR NOW() >= start_date)
        AND (end_date IS NULL OR end_date = '0000-00-00 00:00:00' OR NOW() <= end_date)
        ORDER BY id ASC
    ");
    $activeBroadcasts = $stmtBc->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activeBroadcasts = [];
}
// Fetch Active Numbers (FIXED)
try {
    // Auto cancel expired orders and PROCESS REFUND in DB
    $stmtExpired = $pdo->prepare("SELECT id, price, service_name, number FROM Orders WHERE user_id = ? AND status = 'WAITING' AND created_at < DATE_SUB(NOW(), INTERVAL 20 MINUTE)");
    $stmtExpired->execute([$_SESSION['user_id']]);
    $expiredOrders = $stmtExpired->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expiredOrders as $exp) {
        // 1. Refund balance to user
        $pdo->prepare("UPDATE Users SET balance = balance + ? WHERE id = ?")->execute([$exp['price'], $_SESSION['user_id']]);
        
        // 2. Add Refund entry to Transaction History
        $msg = "Refund: Auto-cancelled " . $exp['service_name'] . " (" . $exp['number'] . ")";
        $pdo->prepare("INSERT INTO Transactions (user_id, amount, type, payment_mode, message, status) VALUES (?, ?, 'credit', 'refund', ?, 'completed')")->execute([$_SESSION['user_id'], $exp['price'], $msg]);
        
        // 3. Mark Order as EXPIRED
        $pdo->prepare("UPDATE Orders SET status = 'EXPIRED' WHERE id = ?")->execute([$exp['id']]);
    }

    $stmt = $pdo->prepare("
        SELECT o.*, s.logo as service_logo, sv.country_name, sv.country_emoji 
        FROM Orders o 
        LEFT JOIN Services s ON o.service_id = s.id 
        LEFT JOIN Servers sv ON o.server_id = sv.id 
        WHERE o.user_id = ? AND o.status = 'WAITING' 
        ORDER BY o.id DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $activeNumbers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $activeNumbersCount = count($activeNumbers);
} catch (Exception $e) {
    $activeNumbers = [];
    $activeNumbersCount = 0;
}

// Fetch transactions for history
try {
    $stmt = $pdo->prepare("
        SELECT t.*, t.createdAt as created_at FROM Transactions t WHERE t.user_id = ? ORDER BY t.id DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $transactions = [];
}

// Fetch orders for history
try {
    $stmt = $pdo->prepare("
        SELECT o.*, s.logo as service_logo, sv.country_name, sv.country_emoji 
        FROM Orders o 
        LEFT JOIN Services s ON o.service_id = s.id 
        LEFT JOIN Servers sv ON o.server_id = sv.id 
        WHERE o.user_id = ? 
        ORDER BY o.id DESC 
        LIMIT 100
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $userOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $userOrders = [];
}

// Fetch Profile Stats (Total Recharged & Total Spent)
try {
    $stmtStats = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN type = 'credit' AND payment_mode != 'refund' AND payment_mode != 'promo' THEN amount ELSE 0 END) as total_recharged,
            
            /* ЁЯСЗ рдпрд╣рд╛рдБ рд░рд┐рдлрдВрдб рдХреЛ рдорд╛рдЗрдирд╕ рдХрд░рдиреЗ рдХрд╛ рд▓реЙрдЬрд┐рдХ рд▓рдЧрд╛ рджрд┐рдпрд╛ рдЧрдпрд╛ рд╣реИ ЁЯСЗ */
            (SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) - SUM(CASE WHEN type = 'credit' AND payment_mode = 'refund' THEN amount ELSE 0 END)) as total_spent
            
        FROM Transactions WHERE user_id = ? AND status = 'completed'
    ");
    $stmtStats->execute([$_SESSION['user_id']]);
    $userStats = $stmtStats->fetch(PDO::FETCH_ASSOC);
    
    $totalRecharged = number_format($userStats['total_recharged'] ?? 0, 2);
    
    // рдпрд╣ рдкрдХреНрдХрд╛ рдХрд░реЗрдЧрд╛ рдХрд┐ Spent рдХрднреА рдорд╛рдЗрдирд╕ (-) рдореЗрдВ рдирд╛ рдЬрд╛рдП
    $spentAmt = (float)($userStats['total_spent'] ?? 0);
    $totalSpent = number_format(max(0, $spentAmt), 2);

    // Get Active Sessions (Real Time)
    $stmtSessions = $pdo->prepare("SELECT * FROM LoginHistory WHERE user_id = ? AND loggedOut = 0 ORDER BY id DESC LIMIT 10");
    $stmtSessions->execute([$_SESSION['user_id']]);
    $activeSessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

    // Get API Key & Discount (Real Time from Users table)
    $stmtUserExtra = $pdo->prepare("SELECT discount, api_key FROM Users WHERE id = ?");
    // Fetch Special Offers / Premium Discounts
    $specialOffers = [];
    try {
        // Agar aapki table ka naam kuch aur hai (jaise 'discounts'), toh yahan change kar lena
        $stmtOffers = $pdo->prepare("SELECT * FROM UserDiscounts WHERE user_id = ?"); 
        $stmtOffers->execute([$_SESSION['user_id']]);
        $specialOffers = $stmtOffers->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $specialOffers = [];
    }
    $stmtUserExtra->execute([$_SESSION['user_id']]);
    $userExtra = $stmtUserExtra->fetch(PDO::FETCH_ASSOC);
    $userDiscount = $userExtra['discount'] ?? 0;
    $userApiKey = $userExtra['api_key'] ?? 'No API Key Generated';
} catch (Exception $e) {
    $totalRecharged = '0.00';
    $totalSpent = '0.00';
    $userDiscount = 0;
    $activeSessions = [];
    $userApiKey = 'Error loading API Key';
}

// ============================================================
// Fetch Special Offers / Premium Discounts (WITH SERVICE DETAILS & PRICE)
$specialOffers = [];
try {
    $stmtOffers = $pdo->prepare("
        SELECT ud.*, 
               s.name as service_name, s.code as service_code, s.logo as service_logo, s.price as original_price,
               sv.name as server_name, sv.country_name, sv.country_emoji
        FROM UserDiscounts ud
        LEFT JOIN Services s ON ud.service_id = s.id AND ud.apply_to = 'specific'
        LEFT JOIN Servers sv ON s.server_id = sv.id
        WHERE ud.user_id = ?
    ");
    $stmtOffers->execute([$_SESSION['user_id']]);
    $specialOffers = $stmtOffers->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $specialOffers = [];
}
// ============================================================
// 100% Working Real-Time Promo Code Fetching Logic
$totalPromoAmt = '0.00';
$totalPromoCount = 0;

try {
    $stmtPromo = $pdo->prepare("
        SELECT 
            SUM(amount) as total_promo_amt,
            COUNT(*) as total_promo_count
        FROM Transactions 
        WHERE user_id = ? AND type = 'credit' AND payment_mode = 'promo'
    ");
    $stmtPromo->execute([$_SESSION['user_id']]);
    $promoStats = $stmtPromo->fetch(PDO::FETCH_ASSOC);
    
    if ($promoStats && $promoStats['total_promo_count'] > 0) {
        $totalPromoAmt = number_format($promoStats['total_promo_amt'] ?? 0, 2);
        $totalPromoCount = $promoStats['total_promo_count'] ?? 0;
    }
} catch (Exception $e) {
    $totalPromoAmt = '0.00';
    $totalPromoCount = 0;
}

// Fetch top services with required country code for existing purchase_service action
try {
    $topServices = $pdo->query("
        SELECT o.service_name, MIN(o.price) as price, o.service_id, sv.country_code, sv.country_emoji, sv.country_name, COUNT(*) as cnt 
        FROM Orders o 
        LEFT JOIN Services s ON o.service_id = s.id 
        LEFT JOIN Servers sv ON o.server_id = sv.id 
        GROUP BY o.service_name, o.service_id, sv.country_code, sv.country_emoji, sv.country_name 
        ORDER BY cnt DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $topServices = [];
}

// ============================================================
// Fetch Site Settings, Support Links & FAQs
// ============================================================
try {
    // 1. Site Settings निकालें
    $stmtSettings = $pdo->query("SELECT * FROM SiteSettings LIMIT 1");
    $siteSettings = $stmtSettings->fetch(PDO::FETCH_ASSOC) ?: [
        'site_name' => 'OTPx',
        'site_logo' => '',
        'support_link' => 'https://t.me/support',
        'currency_symbol' => '₹'
    ];

    // 2. Support Links और FAQs निकालें
    $supportLinks = $pdo->query("SELECT * FROM SupportLinks ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $faqs = $pdo->query("SELECT * FROM FAQs ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $faqCategories = ['General', 'Account', 'Payments', 'Numbers'];

} catch (Exception $e) {
    // अगर डेटाबेस में कोई एरर आता है, तो डिफ़ॉल्ट (खाली) वैल्यू सेट करें
    $siteSettings = [
        'site_name' => 'OTPx', 
        'site_logo' => '', 
        'support_link' => '#', 
        'currency_symbol' => '₹'
    ];
    $supportLinks = [];
    $faqs = [];
    $faqCategories = ['General', 'Account', 'Payments', 'Numbers'];
}

// करेंसी सिंबल को वेरिएबल में सेट करें
$currency = $siteSettings['currency_symbol'] ?? '₹';

// ============================================================
// Fetch Active Banners from Admin Panel
// ============================================================
try {
    // अगर आपकी टेबल का नाम 'Banners' या 'banners' है, तो वह यहाँ सही से चेक कर लें
    $stmtBanners = $pdo->query("SELECT * FROM Banners WHERE status = 1 ORDER BY id DESC");
    $activeBanners = $stmtBanners->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activeBanners = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPx'); ?> - Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ============================================================
           MASTER STYLES - OTPX UI REBUILD
           ============================================================ */
        :root {
            --bg-body: #f4f7fe;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --hover-bg: #eff6ff;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        body.dark-mode {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --hover-bg: #334155;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, system-ui, sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); transition: background 0.3s, color 0.3s; }
        a { text-decoration: none; color: inherit; }
        ::-webkit-scrollbar { width: 6px; background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        /* ============================================================
           TOP NAVBAR & MOBILE SIDEBAR
           ============================================================ */
        .top-navbar { background: var(--bg-card); padding: 0 24px; height: 70px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 1000; }
        .nav-left { display: flex; align-items: center; gap: 30px; }
        .mobile-menu-btn { display: none; background: transparent; border: none; font-size: 22px; color: var(--text-main); cursor: pointer; }
        .brand { font-size: 22px; font-weight: 800; color: var(--accent); display: flex; align-items: center; gap: 10px; }
        .brand .logo-circle { width: 32px; height: 32px; background: var(--accent); border-radius: 50%; color: white; display: flex; justify-content: center; align-items: center; font-size: 14px; }
        
        .nav-menu { display: flex; gap: 10px; }
        .nav-item { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 8px; transition: 0.2s; cursor: pointer; }
        .nav-item:hover, .nav-item.active { background: var(--hover-bg); color: var(--accent); }
        .nav-badge { background: var(--accent); color: white; font-size: 11px; padding: 2px 6px; border-radius: 10px; }

        .nav-right { display: flex; align-items: center; gap: 15px; }
        .theme-toggle { background: transparent; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer; padding: 5px; }
        .balance-badge { background: var(--hover-bg); padding: 6px 12px; border-radius: 20px; font-weight: 700; color: var(--text-main); font-size: 14px; display: flex; align-items: center; gap: 8px; border: 1px solid var(--border-color); cursor:pointer; }
        .add-btn { background: var(--accent); color: white; width: 22px; height: 22px; border-radius: 50%; display: flex; justify-content: center; align-items: center; cursor: pointer; border: none; font-size: 12px; }
        .user-avatar { width: 36px; height: 36px; background: var(--accent); color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; cursor: pointer; }

        /* ============================================================
           PREMIUM MOBILE SIDEBAR (SAFE CSS)
           ============================================================ */
        /* Overlay & Main Container */
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; backdrop-filter: blur(2px); }
        .mobile-sidebar { position: fixed; top: 0; left: -300px; width: 280px; height: 100%; background: var(--bg-card); z-index: 1002; transition: left 0.3s ease; display: flex; flex-direction: column; box-shadow: 4px 0 25px rgba(0,0,0,0.1); border-top-right-radius: 20px; border-bottom-right-radius: 20px; overflow: hidden; }
        .mobile-sidebar.open { left: 0; }

        /* Scrollable Content */
        .ms-inner { padding: 20px; overflow-y: auto; flex: 1; }

        /* Header & Logo Fix (Bade Logo ko chhota karega) */
        .ms-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; }
        .ms-brand { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 800; color: var(--text-main); }
        .ms-brand .logo-circle { width: 32px; height: 32px; background: var(--accent); border-radius: 50%; display: flex; justify-content: center; align-items: center; overflow: hidden; flex-shrink: 0; }
        .ms-brand .logo-circle img { width: 100%; height: 100%; object-fit: cover; }
        .ms-close-btn { background: transparent; border: none; font-size: 22px; color: var(--text-muted); cursor: pointer; padding: 5px; }

        /* Blue Balance Card */
        .ms-balance-card { background: var(--accent); border-radius: 16px; padding: 20px; color: white; display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; box-shadow: 0 8px 20px rgba(37,99,235,0.25); }
        .ms-bal-text { font-size: 12px; font-weight: 600; opacity: 0.9; margin-bottom: 4px; display: block; }
        .ms-bal-amt { font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
        .ms-add-btn { width: 38px; height: 38px; border-radius: 50%; background: rgba(255,255,255,0.2); color: white; display: flex; justify-content: center; align-items: center; font-size: 18px; cursor: pointer; border: none; transition: 0.2s; }

        /* Menu Items */
        .ms-nav-menu { display: flex; flex-direction: column; gap: 4px; }
        .ms-nav-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 12px; font-size: 15px; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: 0.2s; }
        .ms-nav-item i { font-size: 18px; width: 24px; text-align: center; }
        .ms-nav-item.active { background: #eff6ff; color: var(--accent); }
        .ms-nav-item.active i { color: var(--accent); }
        .ms-badge-new { background: #e0e7ff; color: var(--accent); font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 20px; margin-left: auto; letter-spacing: 0.5px; }

        /* Footer & Profile Box */
        .ms-footer { padding: 20px; border-top: 1px solid var(--border-color); background: var(--bg-card); }
        .ms-profile-box { display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 14px; margin-bottom: 15px; border: 1px solid var(--border-color); cursor: pointer; }
        .ms-p-avatar { width: 42px; height: 42px; background: var(--accent); color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 18px; font-weight: 700; flex-shrink: 0; }
        .ms-p-info { overflow: hidden; }
        .ms-p-name { font-size: 15px; font-weight: 800; color: var(--text-main); white-space: nowrap; text-overflow: ellipsis; overflow: hidden; margin-bottom: 2px; }
        .ms-p-email { font-size: 12px; font-weight: 500; color: var(--text-muted); white-space: nowrap; text-overflow: ellipsis; overflow: hidden; }

        .ms-footer-link { display: flex; align-items: center; gap: 14px; padding: 12px 16px; font-size: 15px; font-weight: 600; color: var(--text-muted); cursor: pointer; border-radius: 12px; transition: 0.2s; }
        .ms-footer-link i { font-size: 18px; width: 24px; text-align: center; }
        .ms-footer-link.danger { color: #ef4444; }
        .ms-footer-link.danger i { color: #ef4444; }
        .ms-footer-link:hover { background: var(--hover-bg); }
        .ms-footer-link.danger:hover { background: #fef2f2; }

        @media (max-width: 992px) {
            .nav-menu { display: none; }
            .mobile-menu-btn { display: block; }
            .top-navbar { padding: 0 15px; }
            .brand span { display: none; }
        }

        /* ============================================================
           MAIN LAYOUT (2 COLUMNS)
           ============================================================ */
        .main-container { max-width: 1500px; margin: 0 auto; padding: 24px; display: grid; grid-template-columns: 6fr 4fr; gap: 24px; }
        .content-section { display: none; grid-column: 1 / -1; } 
        .content-section.active { display: block; }
        #tab-dashboard { display: grid; grid-template-columns: inherit; gap: inherit; }
        #tab-dashboard:not(.active) { display: none; }
        @media (max-width: 992px) { .main-container, #tab-dashboard { grid-template-columns: 1fr; } }

        /* ============================================================
           CARDS & PANELS
           ============================================================ */
        .card { background: var(--bg-card); border-radius: var(--radius); border: 1px solid var(--border-color); padding: 20px; margin-bottom: 24px; box-shadow: var(--shadow); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .card-title { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; color: var(--text-main); }
        
        .search-bar { background: var(--bg-body); padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; flex: 1; min-width: 200px; }
        .search-bar input { border: none; background: transparent; width: 100%; outline: none; font-size: 14px; color: var(--text-main); }

        /* Services Grid (Left Column) */
        .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; max-height: 400px; overflow-y: auto; padding-right: 5px; }
        .service-card { border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: 0.2s; background: var(--bg-card); }
        .service-card:hover, .service-card.active { border-color: var(--accent); box-shadow: 0 4px 12px rgba(37,99,235,0.1); }
        .service-icon { width: 24px; height: 24px; display: flex; justify-content: center; align-items: center; flex-shrink: 0; }
        .service-icon img { max-width: 100%; max-height: 100%; }
        .service-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-main); }

        /* Inline Country & Server Selection */
        .inline-selection-panel { display: none; animation: fadeIn 0.3s; }
        .country-grid { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; }
        .country-item { padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .country-item.active { background: var(--hover-bg); border-color: var(--accent); color: var(--accent); }
        
        .server-row { display: flex; justify-content: space-between; align-items: center; padding: 16px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 12px; background: var(--bg-card); transition: 0.2s; flex-wrap: wrap; gap: 10px; }
        .server-row:hover { border-color: var(--accent); }
        .btn-buy { background: var(--accent); color: white; padding: 8px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-buy:hover { background: var(--accent-hover); }

        /* Banners & Right Column */
        .banner { background: linear-gradient(135deg, #1e293b, #334155); color: white; border-radius: var(--radius); padding: 30px; margin-bottom: 24px; position: relative; overflow: hidden; }
        .banner h2 { font-size: 24px; font-weight: 800; margin-bottom: 10px; }
        .banner p { color: #cbd5e1; font-size: 14px; margin-bottom: 20px; }
        .banner-btn { background: white; color: #1e293b; padding: 10px 24px; border-radius: 8px; font-weight: 700; border: none; cursor: pointer; }
        
        .top-services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; }
        .ts-card { border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; position: relative; background: var(--bg-card); }
        .ts-card .price { font-size: 20px; font-weight: 800; color: var(--accent); margin: 10px 0; }

        /* ============================================================
           GET OTP (ACTIVE ORDERS) - EXACT OTPX UI MATCH
           ============================================================ */
        .otpx-order-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        
        /* Top Row: Icon, Service Name, Country, Timer */
        .otpx-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .otpx-service-info { display: flex; align-items: center; gap: 12px; }
        .otpx-service-icon { width: 40px; height: 40px; border-radius: 8px; background: #f1f5f9; display: flex; justify-content: center; align-items: center; overflow: hidden; }
        .otpx-service-icon img { width: 100%; height: 100%; object-fit: cover; }
        .otpx-service-name { font-size: 16px; font-weight: 700; color: var(--text-main); margin-bottom: 4px; }
        .otpx-service-meta { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
        .otpx-price-badge { background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; }
        
        .otpx-timer-badge { display: flex; align-items: center; gap: 6px; background: var(--hover-bg); color: var(--accent); padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 13px; }
        .otpx-timer-badge.expired { background: #fee2e2; color: #ef4444; }

        /* Middle Row: Phone Number & Copy */
        .otpx-number-row { display: flex; justify-content: space-between; align-items: center; background: var(--hover-bg); padding: 15px 20px; border-radius: 12px; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .otpx-number-display { font-size: 20px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 10px; letter-spacing: 1px; }
        .otpx-btn-copy { background: transparent; border: 1px solid var(--border-color); color: var(--text-muted); padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
        .otpx-btn-copy:hover { border-color: var(--accent); color: var(--accent); }

        /* Waiting / OTP Area */
        .otpx-status-area { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
        .otpx-status-area.success { background: rgba(16, 185, 129, 0.05); border: 1px solid #10b981; }
        .otpx-spinner-circle { width: 40px; height: 40px; border-radius: 50%; border: 3px solid #e2e8f0; border-top-color: var(--accent); animation: spin 1s linear infinite; flex-shrink: 0; }
        .otpx-status-text { font-size: 14px; font-weight: 600; color: var(--text-main); }
        .otpx-status-subtext { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .otpx-received-code { font-size: 24px; font-weight: 800; color: #10b981; letter-spacing: 2px; }

        /* Action Buttons Row */
        .otpx-actions-row { display: flex; gap: 15px; }
        .otpx-actions-row .otpx-btn-action { flex: 1; padding: 12px; border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px; transition: 0.2s; border: none; min-width: 100px; }
        .otpx-btn-cancel { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .otpx-btn-cancel:hover:not(:disabled) { background: #ef4444; color: white; }
        .otpx-btn-cancel:disabled { opacity: 0.5; cursor: not-allowed; }
        .otpx-btn-rebuy { background: rgba(37, 99, 235, 0.1); color: var(--accent); }
        .otpx-btn-rebuy:hover { background: var(--accent); color: white; }

        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Empty State */
        .empty-orders { text-align: center; padding: 60px 20px; background: var(--bg-card); border-radius: 16px; border: 1px dashed var(--border-color); }
        .empty-orders-icon { width: 64px; height: 64px; background: var(--hover-bg); color: var(--accent); border-radius: 16px; display: flex; justify-content: center; align-items: center; font-size: 28px; margin: 0 auto 20px; }

        /* Other elements (Recharge, History, Profile) remain consistent */
        .history-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .history-table th { text-align: left; padding: 12px; background: var(--bg-body); color: var(--text-muted); border-bottom: 1px solid var(--border-color); }
        .history-table td { padding: 12px; border-bottom: 1px solid var(--border-color); }
        .payment-method { display: flex; justify-content: space-between; align-items: center; padding: 16px; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 12px; cursor: pointer; }
        .payment-method:hover { border-color: var(--accent); background: var(--hover-bg); }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
        .modal-overlay.show { display: flex; }
        .modal-content { background: var(--bg-card); width: 100%; max-width: 450px; border-radius: 16px; padding: 24px; position: relative; }
        
        /* ============================================================
   PREMIUM RECHARGE UI (RESPONSIVE)
   ============================================================ */
.rc-main-container { max-width: 850px; margin: 0 auto; width: 100%; box-sizing: border-box; animation: fadeIn 0.3s ease; }
.rc-title-area { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
.rc-title-area h2 { font-size: 22px; font-weight: 800; color: var(--text-main); margin-bottom: 5px; }
.rc-title-area p { font-size: 14px; color: var(--text-muted); }
.rc-sys-badge { display: flex; align-items: center; gap: 8px; background: #dcfce7; padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; color: #16a34a; }

/* Gateway Cards */
.rc-gateway-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-bottom: 20px; }
.rc-gateway-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: space-between; box-sizing: border-box; }
.rc-gateway-card:hover { border-color: #3b82f6; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.08); transform: translateY(-2px); }
.rc-gateway-icon-box { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-right: 15px; flex-shrink: 0; }
.rc-gateway-info h4 { font-size: 15px; font-weight: 800; color: var(--text-main); margin-bottom: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rc-gateway-info p { font-size: 12px; color: var(--text-muted); font-weight: 500; }
.rc-badge { font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; }
.rc-badge-green { background: #dcfce7; color: #16a34a; }
.rc-badge-blue { background: #dbeafe; color: #2563eb; }

/* Features Bar */
.rc-features-bar { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
.rc-feature-item { background: var(--bg-body); border: 1px solid var(--border-color); padding: 10px; border-radius: 8px; font-size: 12px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 8px; flex: 1; min-width: 150px; justify-content: center; text-align: center; box-sizing: border-box; }

/* Promo Code Box */
.rc-promo-box { background: var(--bg-body); border: 1px dashed #c3dafe; border-radius: 12px; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap; box-sizing: border-box; }
.rc-promo-left { display: flex; align-items: center; gap: 15px; flex: 1; min-width: 250px; }
.rc-promo-icon { width: 40px; height: 40px; background: #e0e7ff; color: #4f46e5; border-radius: 8px; display: flex; justify-content: center; align-items: center; font-size: 18px; flex-shrink: 0; }
.rc-promo-input-group { display: flex; flex: 1; min-width: 250px; background: white; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; box-sizing: border-box; }
.rc-promo-input-group input { flex: 1; border: none; padding: 12px 15px; outline: none; font-size: 14px; font-weight: 600; text-transform: uppercase; width: 100%; box-sizing: border-box; background: transparent; color: var(--text-main); }
.rc-promo-input-group button { background: #818cf8; color: white; border: none; padding: 0 20px; font-weight: 700; cursor: pointer; transition: 0.2s; }
.rc-promo-input-group button:hover { background: #6366f1; }

/* Views (Pages inside Recharge) */
.rc-view { display: none; animation: fadeIn 0.3s ease; box-sizing: border-box; }
.rc-view.active { display: block; }
.rc-back-btn { background: transparent; border: none; color: var(--text-main); font-size: 15px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; }

/* Crypto & UTR UI */
.rc-payment-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; max-width: 650px; margin: 0 auto; box-shadow: 0 4px 20px rgba(0,0,0,0.03); width: 100%; box-sizing: border-box; }
.rc-payment-header { background: #3b82f6; color: white; padding: 25px; position: relative; }
.rc-payment-header h3 { font-size: 20px; font-weight: 800; margin-bottom: 5px; }
.rc-payment-header p { font-size: 13px; opacity: 0.9; }
.rc-payment-body { padding: 20px; box-sizing: border-box; }

.rc-input-wrap { border: 2px solid #cbd5e1; border-radius: 10px; display: flex; align-items: center; padding: 0 15px; margin-bottom: 10px; background: white; box-sizing: border-box; width: 100%; }
.rc-input-wrap span { font-weight: 800; color: var(--text-muted); font-size: 18px; }
.rc-input-wrap input { width: 100%; border: none; padding: 12px 10px; font-size: 16px; font-weight: 700; outline: none; background: transparent; color: var(--text-main); }

.rc-quick-amts { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
.rc-q-amt { border: 1px solid var(--border-color); background: white; padding: 8px 16px; border-radius: 20px; font-weight: 700; font-size: 13px; cursor: pointer; color: var(--text-main); }

.rc-qr-box { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 20px; }
.rc-qr-box img { max-width: 100%; height: auto; border-radius: 8px; margin-bottom: 15px; }

.rc-btn-primary { background: #3b82f6; color: white; width: 100%; border: none; padding: 15px; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; box-sizing: border-box; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .rc-title-area { flex-direction: column; align-items: flex-start; }
    .rc-gateway-grid { grid-template-columns: 1fr; }
    .rc-promo-box { flex-direction: column; align-items: stretch; }
    .rc-promo-left { width: 100%; }
    .rc-promo-input-group { width: 100%; }
    .rc-payment-body { flex-direction: column; gap: 20px !important; }
    
}
@media (max-width: 768px) {
    .txn-table tr.txn-row-item[style*="display: none"] {
        display: none !important;
    }
}

/* ============================================================
   ORDERS TABLE (UOTP STYLE)
   ============================================================ */
.uotp-filter-container { display: flex; gap: 10px; align-items: center; overflow-x: auto; padding-bottom: 5px; }
.uotp-filter-btn { padding: 6px 16px; border: 1px solid var(--border-color); border-radius: 20px; background: transparent; color: var(--text-muted); font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; white-space: nowrap; }
.uotp-filter-btn.active { background: var(--accent); color: white; border-color: var(--accent); }
.uotp-table-wrapper { background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color); overflow-x: auto; }
.uotp-table { width: 100%; border-collapse: collapse; text-align: left; white-space: nowrap; }
.uotp-table th { padding: 15px 20px; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color); letter-spacing: 0.5px; }
.uotp-table td { padding: 15px 20px; border-bottom: 1px solid var(--border-color); vertical-align: middle; color: var(--text-main); font-size: 14px; font-weight: 500; }
.uotp-table tr:last-child td { border-bottom: none; }
.uotp-service-cell { display: flex; align-items: center; gap: 12px; }
.uotp-service-icon { width: 36px; height: 36px; background: #f0fdf4; border-radius: 8px; display: flex; justify-content: center; align-items: center; color: #16a34a; font-size: 18px; border: 1px solid #dcfce7; }
.uotp-service-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
.uotp-service-name { font-weight: 700; font-size: 14px; color: var(--text-main); }
.uotp-service-date { font-size: 11px; color: var(--text-muted); margin-top: 2px; font-weight: 500; }
.uotp-otp-pill { display: inline-flex; align-items: center; gap: 8px; background: #eff6ff; color: var(--accent); padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 13px; border: 1px solid #dbeafe; }
.uotp-copy-icon { cursor: pointer; color: #93c5fd; transition: 0.2s; }
.uotp-copy-icon:hover { color: var(--accent); }
.uotp-status { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent; }
.uotp-status.success { background: #f0fdf4; color: #16a34a; border-color: #dcfce7; }
.uotp-status.cancelled { background: #fef2f2; color: #dc2626; border-color: #fee2e2; }
.uotp-status.active { background: #eff6ff; color: var(--accent); border-color: #dbeafe; }

/* ============================================================
   PREMIUM TRANSACTION HISTORY TABLE
   ============================================================ */
        .txn-table-container { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .txn-table { width: 100%; border-collapse: collapse; text-align: left; }
        .txn-table th { background: #f8fafc; color: var(--text-muted); font-size: 12px; font-weight: 700; text-transform: uppercase; padding: 16px 20px; border-bottom: 1px solid var(--border-color); letter-spacing: 0.5px; }
        .txn-table td { padding: 16px 20px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .txn-table tr:last-child td { border-bottom: none; }
        .txn-table tr:hover { background: #f8fafc; }
        
        .txn-info { display: flex; align-items: center; gap: 15px; }
        .txn-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 20px; flex-shrink: 0; }
        .txn-details { display: flex; flex-direction: column; gap: 4px; }
        .txn-title { font-size: 14px; font-weight: 700; color: var(--text-main); }
        .txn-ref { font-size: 11px; color: var(--text-muted); font-family: monospace; background: var(--border-color); padding: 3px 8px; border-radius: 6px; display: inline-block; width: fit-content; font-weight: 600; letter-spacing: 0.5px;}
        
        .txn-amount { font-size: 15px; font-weight: 800; white-space: nowrap; }
        .txn-amount.credit { color: #16a34a; }
        .txn-amount.debit { color: #ef4444; }
        
        .txn-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; letter-spacing: 0.3px; text-transform: uppercase; }
        .badge-success { background: #dcfce7; color: #16a34a; }
        .badge-danger { background: #fee2e2; color: #ef4444; }
        .badge-info { background: #e0e7ff; color: #4f46e5; }
        .badge-warning { background: #fef3c7; color: #d97706; }
        .badge-gray { background: #f1f5f9; color: #475569; }
        
        .txn-date { font-size: 13px; color: var(--text-muted); font-weight: 600; white-space: nowrap; }

        /* ============================================================
           FINAL ALIGNED MOBILE HISTORY CSS FIX
           ============================================================ */
        @media (max-width: 768px) {
            .txn-table-container {
                width: 100% !important;
                overflow-x: hidden !important;
            }
            .txn-table, .txn-table tbody, .txn-table tr, .txn-table td { 
                display: block !important; 
                width: 100% !important; 
                box-sizing: border-box !important; 
            }
            
            .txn-table tr { 
                padding: 16px !important; 
                margin-bottom: 14px !important; 
                border: 1px solid var(--border-color) !important; 
                border-radius: 14px !important; 
                background: var(--bg-card) !important; 
                box-shadow: 0 2px 8px rgba(0,0,0,0.02) !important;
            }
            
            .txn-table td { 
                padding: 10px 0 !important; 
                border: none !important; 
                display: flex !important; 
                justify-content: space-between !important; 
                align-items: center !important; 
                width: 100% !important;
                text-align: right !important;
            }
            
            /* Top Info Section (Icon, Title, UTR) */
            .txn-table td.txn-td-info {
                display: block !important;
                text-align: left !important;
                padding-bottom: 12px !important;
                border-bottom: 1px dashed var(--border-color) !important;
                margin-bottom: 4px !important;
            }

            /* Labels (AMOUNT, STATUS, DATE) ko left mein aur values ko right mein set karna */
            .txn-table td::before { 
                font-weight: 700 !important; 
                font-size: 12px !important; 
                color: var(--text-muted) !important; 
                text-transform: uppercase !important; 
                letter-spacing: 0.5px !important;
                text-align: left !important;
            }

            .txn-td-info .txn-info {
                display: flex !important;
                align-items: flex-start !important;
                gap: 12px !important;
                width: 100% !important;
            }

            /* Clear labels */
            .txn-td-amount::before { content: 'Amount' !important; }
            .txn-td-status::before { content: 'Status' !important; }
            .txn-td-date::before { content: 'Date & Time' !important; }
            
            .txn-td-date .txn-date {
                font-size: 13px !important;
                color: var(--text-main) !important;
                font-weight: 600 !important;
                text-align: right !important;
            }
        }
        
        /* Promo Card Specific Fix */
        .promo-card-fix {
            background: var(--bg-card) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 16px !important;
            padding: 20px !important;
            margin-top: 15px !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* ============================================================
           PROFILE DROPDOWN & ACCOUNT PAGE CSS
           ============================================================ */
        .user-avatar-container { position: relative; display: inline-block; }
        .profile-dropdown { position: absolute; top: 50px; right: 0; width: 260px; background: var(--bg-card); border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--border-color); opacity: 0; visibility: hidden; transform: translateY(10px); transition: 0.2s ease; z-index: 1000; }
        .profile-dropdown.active { opacity: 1; visibility: visible; transform: translateY(0); }
        .pd-header { padding: 16px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px; }
        .pd-avatar { width: 40px; height: 40px; background: var(--accent); color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 18px; font-weight: 700; flex-shrink: 0; }
        .pd-info { display: flex; flex-direction: column; overflow: hidden; }
        .pd-info strong { font-size: 14px; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pd-info span { font-size: 12px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pd-body { padding: 8px 0; border-bottom: 1px solid var(--border-color); }
        .pd-body a { display: flex; align-items: center; gap: 12px; padding: 10px 16px; font-size: 14px; color: var(--text-main); font-weight: 500; transition: 0.2s; }
        .pd-body a:hover { background: var(--hover-bg); }
        .pd-body a i { font-size: 16px; color: var(--text-muted); width: 20px; text-align: center; }
        .pd-bal { margin-left: auto; font-weight: 700; color: var(--accent); }
        .pd-footer { padding: 8px 0; }
        .pd-footer a { display: flex; align-items: center; gap: 12px; padding: 10px 16px; font-size: 14px; color: #ef4444; font-weight: 600; transition: 0.2s; }
        .pd-footer a:hover { background: #fef2f2; }

        .acc-header-area { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .acc-header-area h1 { font-size: 28px; font-weight: 800; color: var(--text-main); margin-bottom: 4px; }
        .acc-header-area p { color: var(--text-muted); font-size: 14px; }
        .status-badge { background: #dcfce7; color: #16a34a; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; border: 1px solid #bbf7d0; }
        .acc-top-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .acc-user-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 15px; }
        .acc-user-info { display: flex; align-items: center; gap: 20px; }
        .acc-avatar-lg { width: 70px; height: 70px; background: #e0e7ff; color: var(--accent); border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 28px; font-weight: 700; border: 2px solid #c7d2fe; }
        .acc-user-details h2 { font-size: 20px; font-weight: 800; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .acc-user-details h2 i { color: #3b82f6; font-size: 16px; }
        .acc-user-details p { color: var(--text-muted); font-size: 14px; display: flex; align-items: center; gap: 6px; }

        .acc-stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .acc-stat-box { border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; background: #fafafa; }
        .acc-stat-box .title { font-size: 12px; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 6px; margin-bottom: 8px; }
        .acc-stat-box .value { font-size: 20px; font-weight: 800; color: var(--text-main); }
        .acc-stat-box.highlight .value { color: var(--accent); }

        .acc-tabs { display: flex; gap: 10px; border: 1px solid var(--border-color); padding: 6px; border-radius: 12px; background: var(--bg-card); margin-bottom: 24px; overflow-x: auto; }
        .acc-tab-btn { padding: 10px 20px; border: none; background: transparent; font-size: 14px; font-weight: 600; color: var(--text-muted); border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; white-space: nowrap; }
        .acc-tab-btn:hover { color: var(--text-main); }
        .acc-tab-btn.active { background: var(--accent); color: white; }

        .acc-tab-content { display: none; animation: fadeIn 0.3s ease; }
        .acc-tab-content.active { display: block; }

        .acc-grid-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        @media (max-width: 992px) { .acc-grid-layout { grid-template-columns: 1fr; } }

        .acc-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; }
        .acc-box-title { font-size: 16px; font-weight: 700; margin-bottom: 20px; color: var(--text-main); }
        
        .acc-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 600px) { .acc-form-grid { grid-template-columns: 1fr; } }
        
        .acc-input-group { margin-bottom: 15px; }
        .acc-input-group label { display: block; font-size: 12px; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; }
        .acc-input-wrapper { display: flex; align-items: center; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; padding: 0 15px; overflow: hidden; transition: 0.2s; }
        .acc-input-wrapper:focus-within { border-color: var(--accent); background: white; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .acc-input-wrapper i { color: #94a3b8; font-size: 14px; }
        .acc-input-wrapper input { border: none; background: transparent; padding: 12px 10px; width: 100%; outline: none; font-size: 14px; font-weight: 600; color: var(--text-main); }
        .acc-input-wrapper input:read-only { color: var(--text-muted); cursor: not-allowed; }

        .acc-bal-card { text-align: center; display: flex; flex-direction: column; justify-content: center; height: 100%; }
        .acc-bal-card .bal-title { font-size: 13px; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 6px; margin-bottom: 10px; }
        .acc-bal-card .bal-amt { font-size: 36px; font-weight: 800; color: var(--text-main); margin-bottom: 20px; }

        .acc-mini-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 24px; }
        @media (max-width: 992px) { .acc-mini-stats { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 600px) { .acc-mini-stats { grid-template-columns: 1fr; } }
        .acc-mini-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; }
        .acc-mini-card .top { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 10px; }
        .acc-mini-card .amt { font-size: 24px; font-weight: 800; color: var(--text-main); margin-bottom: 15px; }
        .acc-mini-card .btm { font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 6px; }

        /* Additional Fixes */
        .dash-left, .dash-right { display: flex; flex-direction: column; gap: 24px; }
        .dash-right .banner { margin-bottom: 0; }
        
        
        /* ============================================================
           PREMIUM OFFERS PAGE CSS
           ============================================================ */
        .offers-header-area { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .offers-header-area h2 { font-size: 26px; font-weight: 800; color: var(--text-main); margin-bottom: 5px; }
        .offers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .offer-vip-card { background: linear-gradient(145deg, #ffffff, #f8f9ff); border: 1px solid #c7d2fe; border-radius: 16px; padding: 24px; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(79, 70, 229, 0.08); transition: transform 0.3s; }
        .offer-vip-card:hover { transform: translateY(-5px); border-color: #818cf8; }
        .offer-vip-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #4f46e5, #ec4899); }
        .o-badge { position: absolute; top: 15px; right: 15px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; letter-spacing: 1px; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3); }
        .o-icon { width: 50px; height: 50px; background: #e0e7ff; color: #4f46e5; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 15px; }
        .o-title { font-size: 18px; font-weight: 800; color: #1e1b4b; margin-bottom: 8px; }
        .o-desc { font-size: 14px; color: var(--text-muted); font-weight: 500; line-height: 1.5; margin-bottom: 20px; }
        .o-desc b { color: #111827; font-size: 16px; }
        .o-btn { width: 100%; background: #4f46e5; color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .o-btn:hover { background: #4338ca; }
        
        @media (max-width: 992px) {
            .nav-menu { display: none; }
            .mobile-menu-btn { display: block; }
            .top-navbar { padding: 0 15px; }
            .brand span { display: none; }
        }
    </style>
</head>
<body>
<!-- ============================================================
TOP NAVBAR & MOBILE SIDEBAR (OTPX STYLE)
============================================================ -->
<div class="top-navbar">
    <div class="nav-left">
        <button class="mobile-menu-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div class="brand">
            <?php if(!empty($siteSettings['site_logo'])): ?>
                <div class="logo-circle"><img src="<?php echo htmlspecialchars($siteSettings['site_logo']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></div>
            <?php else: ?>
                <div class="logo-circle">O</div>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPx'); ?></span>
        </div>
        <!-- Desktop Menu -->
        <div class="nav-menu">
            <div class="nav-item <?php echo $activeTab == 'dashboard' ? 'active' : ''; ?>" onclick="switchTab('dashboard')"><i class="fa-solid fa-house"></i> Dashboard</div>
            <div class="nav-item <?php echo $activeTab == 'offers' ? 'active' : ''; ?>" onclick="switchTab('offers')"><i class="fa-solid fa-gift"></i> My Offers</div>
            <div class="nav-item <?php echo $activeTab == 'numbers' ? 'active' : ''; ?>" onclick="switchTab('numbers')">
                <i class="fa-solid fa-mobile-screen"></i> Get Otp 
                <?php if($activeNumbersCount > 0): ?><span class="nav-badge" id="active-count"><?php echo $activeNumbersCount; ?></span><?php endif; ?>
            </div>
            <div class="nav-item" onclick="alert('Feature coming soon!')"><i class="fa-regular fa-envelope"></i> Buy Mail</div>
            <div class="nav-item <?php echo $activeTab == 'recharge' ? 'active' : ''; ?>" onclick="switchTab('recharge')"><i class="fa-solid fa-wallet"></i> Recharge</div>
            <div class="nav-item <?php echo $activeTab == 'history' ? 'active' : ''; ?>" onclick="switchTab('history')"><i class="fa-solid fa-clock-rotate-left"></i> History</div>
            <div class="nav-item <?php echo $activeTab == 'orders' ? 'active' : ''; ?>" onclick="switchTab('orders')"><i class="fa-solid fa-list-check"></i> Orders</div>
            <div class="nav-item" onclick="window.open('../api-docs.php', '_blank')"><i class="fa-solid fa-code"></i> Api</div>
            <div class="nav-item <?php echo $activeTab == 'support' ? 'active' : ''; ?>" onclick="switchTab('support')">
    <i class="fa-regular fa-circle-question"></i> Support
</div>
        </div>
    </div>
    <div class="nav-right">
        <button class="theme-toggle" onclick="toggleTheme()"><i class="fa-solid fa-moon"></i></button>
        <!-- Notification Bell -->
        <button class="theme-toggle" onclick="openNotificationModal()" style="position:relative; margin-right:10px;">
            <i class="fa-regular fa-bell"></i>
            <?php if(count($activeBroadcasts) > 0): ?>
                <span style="position:absolute; top:-2px; right:-2px; background:#ef4444; color:white; font-size:9px; font-weight:bold; width:16px; height:16px; border-radius:50%; display:flex; justify-content:center; align-items:center; border:2px solid var(--bg-card);"><?php echo count($activeBroadcasts); ?></span>
            <?php endif; ?>
        </button>
        
        <div class="balance-badge" onclick="switchTab('recharge')">
            <?php echo $currency; ?><span id="nav-bal-amt"><?php echo $userBalance; ?></span>
            <button class="add-btn"><i class="fa-solid fa-plus"></i></button>
        </div>
        <div class="user-avatar-container">
            <div class="user-avatar" onclick="toggleProfileDropdown(event)" style="cursor:pointer;">
                <?php echo $userInitial; ?>
            </div>
            
            <div class="profile-dropdown" id="profileDropdown">
                <div class="pd-header">
                    <div class="pd-avatar"><?php echo $userInitial; ?></div>
                    <div class="pd-info">
                        <strong><?php echo htmlspecialchars($userName); ?></strong>
                        <span><?php echo htmlspecialchars($userEmail); ?></span>
                    </div>
                </div>
                <div class="pd-body">
                    <a href="#" onclick="switchTab('recharge'); closeProfileDropdown();"><i class="fa-solid fa-wallet"></i> Add Funds <span class="pd-bal"><?php echo $currency.$userBalance; ?></span></a>
                    <a href="#" onclick="switchTab('profile'); closeProfileDropdown();"><i class="fa-regular fa-user"></i> Profile</a>
                    <a href="#" onclick="switchTab('profile'); closeProfileDropdown();"><i class="fa-solid fa-gear"></i> Settings</a>
                    <a href="#" onclick="switchTab('profile'); closeProfileDropdown();"><i class="fa-solid fa-code"></i> API Keys</a>
                </div>
                <div class="pd-footer">
                    <a href="#" onclick="logout()"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sign out</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sidebar -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>
<div class="mobile-sidebar" id="mobile-sidebar">
    <div class="ms-inner">
        <!-- Header with Close Button -->
        <div class="ms-header">
            <div class="ms-brand">
                <?php if(!empty($siteSettings['site_logo'])): ?>
                    <div class="logo-circle"><img src="<?php echo htmlspecialchars($siteSettings['site_logo']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></div>
                <?php else: ?>
                    <div class="logo-circle">O</div>
                <?php endif; ?>
                <?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPx'); ?>
            </div>
            <button class="ms-close-btn" onclick="toggleSidebar()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- Blue Balance Card -->
        <div class="ms-balance-card">
            <div>
                <span class="ms-bal-text">Available Balance</span>
                <div class="ms-bal-amt"><?php echo $currency.$userBalance; ?></div>
            </div>
            <button class="ms-add-btn" onclick="switchTab('recharge'); toggleSidebar();"><i class="fa-solid fa-plus"></i></button>
        </div>

        <!-- Navigation Menu -->
        <div class="ms-nav-menu">
            <div class="ms-nav-item <?php echo $activeTab == 'dashboard' ? 'active' : ''; ?>" onclick="switchTab('dashboard'); toggleSidebar();">
                <i class="fa-solid fa-house"></i> Dashboard
            </div>
            <div class="ms-nav-item <?php echo $activeTab == 'offers' ? 'active' : ''; ?>" onclick="switchTab('offers'); toggleSidebar();">
    <i class="fa-solid fa-gift"></i> My Offers <span class="ms-badge-new" style="background:#fef3c7; color:#d97706;">VIP</span>
</div>
            <div class="ms-nav-item <?php echo $activeTab == 'numbers' ? 'active' : ''; ?>" onclick="switchTab('numbers'); toggleSidebar();">
                <i class="fa-solid fa-mobile-screen"></i> Get Otp 
            </div>
            <div class="ms-nav-item" onclick="alert('Feature coming soon!'); toggleSidebar();">
                <i class="fa-regular fa-envelope"></i> Buy Mail
            </div>
            <div class="ms-nav-item <?php echo $activeTab == 'recharge' ? 'active' : ''; ?>" onclick="switchTab('recharge'); toggleSidebar();">
                <i class="fa-solid fa-wallet"></i> Recharge
            </div>
            <div class="ms-nav-item <?php echo $activeTab == 'history' ? 'active' : ''; ?>" onclick="switchTab('history'); toggleSidebar();">
                <i class="fa-solid fa-clock-rotate-left"></i> History
            </div>
            <div class="ms-nav-item" onclick="alert('Feature coming soon!'); toggleSidebar();">
                <i class="fa-regular fa-envelope-open"></i> Free Mail <span class="ms-badge-new">NEW</span>
            </div>
            <div class="ms-nav-item <?php echo $activeTab == 'orders' ? 'active' : ''; ?>" onclick="switchTab('orders'); toggleSidebar();">
                <i class="fa-solid fa-right-left"></i> Orders
            </div>
            <div class="ms-nav-item" onclick="window.open('../api-docs.php', '_blank'); toggleSidebar();">
                <i class="fa-solid fa-code"></i> Api
            </div>
            <div class="ms-nav-item <?php echo $activeTab == 'support' ? 'active' : ''; ?>" onclick="switchTab('support'); toggleSidebar();">
    <i class="fa-regular fa-circle-question"></i> Support
</div>
        </div>
    </div>

    <!-- Footer Profile Area -->
    <div class="ms-footer">
        <div class="ms-profile-box" onclick="switchTab('profile'); switchProfileTab('overview'); toggleSidebar();">
            <div class="ms-p-avatar"><?php echo $userInitial; ?></div>
            <div class="ms-p-info">
                <div class="ms-p-name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="ms-p-email"><?php echo htmlspecialchars($userEmail); ?></div>
            </div>
        </div>
        
        <div class="ms-footer-link" onclick="switchTab('profile'); switchProfileTab('overview'); toggleSidebar();">
            <i class="fa-regular fa-user"></i> Profile
        </div>
        <div class="ms-footer-link" onclick="switchTab('profile'); switchProfileTab('security'); toggleSidebar();">
            <i class="fa-solid fa-gear"></i> Settings
        </div>
        <div class="ms-footer-link danger" onclick="logout(); toggleSidebar();">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
        </div>
    </div>
</div>

<!-- ============================================================
MAIN CONTAINER
============================================================ -->
<div class="main-container">
<!-- ============================================================
    TAB: MY OFFERS (PREMIUM VIP PAGE)
    ============================================================ -->
    <div id="tab-offers" class="content-section <?php echo $activeTab == 'offers' ? 'active' : ''; ?>">
        <div class="offers-header-area">
            <div>
                <h2><i class="fa-solid fa-gift" style="color:#ec4899;"></i> My Premium Offers</h2>
                <p style="color:var(--text-muted); font-size:14px;">Exclusive discounts specially assigned to your account by the admin.</p>
            </div>
            <button class="btn-buy" onclick="switchTab('dashboard')"><i class="fa-solid fa-cart-shopping"></i> Browse Services</button>
        </div>

        <?php 
        // ==========================================
        // OFFERS KO CATEGORY ME BANTNA (GROUPING)
        // ==========================================
        $allServiceOffers = [];
        $percentageOffers = [];
        $fixedOffers = [];

        if(!empty($specialOffers)) {
            foreach($specialOffers as $off) {
                if($off['apply_to'] == 'all') {
                    $allServiceOffers[] = $off;
                } elseif($off['type'] == 'percentage') {
                    $percentageOffers[] = $off;
                } else {
                    $fixedOffers[] = $off;
                }
            }
        }
        ?>

        <?php if(empty($specialOffers) && $userDiscount <= 0): ?>
            <!-- Empty State (No Offers) -->
            <div class="empty-orders" style="background:var(--bg-card); max-width:600px; margin:40px auto; padding:60px 20px; box-shadow:var(--shadow); border:none;">
                <div class="empty-orders-icon" style="background:#fef2f2; color:#ef4444;"><i class="fa-solid fa-box-open"></i></div>
                <h3 style="font-size:20px; font-weight:800; color:var(--text-main); margin-bottom:10px;">No Active Offers Yet</h3>
                <p style="color:var(--text-muted); margin-bottom:20px;">Keep using OTPx! Admins regularly assign premium discounts to active users.</p>
            </div>
        <?php else: ?>
            
            <!-- GLOBAL & ALL SERVICES DISCOUNTS (TOP CARDS) -->
            <div class="offers-grid" style="margin-bottom: 20px;">
                <?php if($userDiscount > 0): ?>
                <div class="offer-vip-card" style="border-color:#10b981;">
                    <div class="o-badge" style="background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">LIFETIME</div>
                    <div class="o-icon" style="background:#dcfce7; color:#10b981;"><i class="fa-solid fa-percent"></i></div>
                    <div class="o-title">Global Account Discount</div>
                    <div class="o-desc">You are enjoying a flat <b><?php echo htmlspecialchars($userDiscount); ?>% OFF</b> on all services across the platform.</div>
                    <button class="o-btn" style="background:#10b981;" onclick="switchTab('dashboard')">Buy Numbers</button>
                </div>
                <?php endif; ?>

                <?php foreach($allServiceOffers as $offer): ?>
                <div class="offer-vip-card">
                    <div class="o-badge">SPECIAL</div>
                    <div class="o-icon"><i class="fa-solid fa-tags"></i></div>
                    <div class="o-title">All Services Discount</div>
                    <div class="o-desc">Get Flat <b><?php echo htmlspecialchars($offer['value']); ?>% OFF</b> automatically applied at checkout.</div>
                    <button class="o-btn" onclick="switchTab('dashboard')">Redeem Now</button>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- SPECIFIC SERVICES LIST VIEW (TWO COLUMNS: PERCENTAGE & FIXED) -->
            <div class="offers-grid" style="grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));">
                
                <!-- 1. PERCENTAGE DISCOUNTS COLUMN -->
                <?php if(!empty($percentageOffers)): ?>
                <div class="offer-vip-card" style="padding: 20px;">
                    <div class="o-badge" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">% OFFERS</div>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px; padding-bottom:15px; border-bottom:2px solid #f1f5f9;">
                        <div class="o-icon" style="margin:0; width:40px; height:40px; font-size:18px;"><i class="fa-solid fa-arrow-trend-down"></i></div>
                        <div>
                            <h3 style="margin:0; font-size:18px; color:var(--text-main); font-weight:800;">Percentage Discounts</h3>
                            <p style="margin:0; font-size:12px; color:var(--text-muted);">Special % off on selected services</p>
                        </div>
                    </div>
                    
                    <div style="display:flex; flex-direction:column; gap:15px;">
                        <?php foreach($percentageOffers as $offer): 
                            $origPrice = isset($offer['original_price']) ? (float)$offer['original_price'] : 0;
                            $discountAmt = ($origPrice * (float)$offer['value']) / 100;
                            $newPrice = $origPrice - $discountAmt;
                        ?>
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:12px;">
                            <!-- Service Details -->
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div style="width:40px; height:40px; background:white; border:1px solid #cbd5e1; border-radius:8px; display:flex; justify-content:center; align-items:center; overflow:hidden;">
                                    <?php if(!empty($offer['service_logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($offer['service_logo']); ?>" style="width:100%; height:100%; object-fit:contain;">
                                    <?php else: ?>
                                        <i class="fa-solid fa-gem" style="color:var(--text-muted);"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-weight:800; font-size:14px; color:var(--text-main);"><?php echo !empty($offer['service_name']) ? htmlspecialchars($offer['service_name']) : 'Service ID: '.$offer['service_id']; ?></div>
                                    <div style="font-size:11px; color:var(--text-muted); font-weight:600; margin-top:2px;">
                                        <?php echo htmlspecialchars($offer['country_emoji'] ?? '🌍'); ?> <?php echo htmlspecialchars($offer['country_name'] ?? 'Country'); ?> • <i class="fa-solid fa-server"></i> <?php echo htmlspecialchars($offer['server_name'] ?? 'Server'); ?>
                                    </div>
                                </div>
                            </div>
                            <!-- Price & Button -->
                            <div style="display:flex; align-items:center; gap:15px;">
                                <div style="text-align:right;">
                                    <div style="font-size:11px; color:#ef4444; text-decoration:line-through; font-weight:700;"><?php echo $currency . number_format($origPrice, 2); ?></div>
                                    <div style="font-size:16px; color:#10b981; font-weight:900;"><?php echo $currency . number_format($newPrice, 2); ?> <span style="font-size:10px; background:#dcfce7; color:#10b981; padding:2px 4px; border-radius:4px; font-weight:800;"><i class="fa-solid fa-arrow-trend-down"></i> <?php echo htmlspecialchars($offer['value']); ?>%</span></div>
                                </div>
                                <button class="btn-buy" style="padding:8px 12px; font-size:12px;" onclick="switchTab('dashboard'); setTimeout(() => { document.getElementById('service-search').value='<?php echo addslashes($offer['service_name'] ?? ''); ?>'; filterServices('<?php echo addslashes($offer['service_name'] ?? ''); ?>'); selectServiceInline(<?php echo $offer['service_id']; ?>, '<?php echo htmlspecialchars($offer['service_code'] ?? ''); ?>', '<?php echo addslashes($offer['service_name'] ?? ''); ?>'); }, 300);"><i class="fa-solid fa-bolt"></i> Buy</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 2. FIXED PREMIUM PRICE COLUMN -->
                <?php if(!empty($fixedOffers)): ?>
                <div class="offer-vip-card" style="padding: 20px;">
                    <div class="o-badge" style="background: linear-gradient(135deg, #f59e0b, #d97706);">FIXED OFFERS</div>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px; padding-bottom:15px; border-bottom:2px solid #f1f5f9;">
                        <div class="o-icon" style="margin:0; width:40px; height:40px; font-size:18px; background:#fef3c7; color:#d97706;"><i class="fa-solid fa-thumbtack"></i></div>
                        <div>
                            <h3 style="margin:0; font-size:18px; color:var(--text-main); font-weight:800;">Fixed VIP Prices</h3>
                            <p style="margin:0; font-size:12px; color:var(--text-muted);">Special locked prices for you</p>
                        </div>
                    </div>
                    
                    <div style="display:flex; flex-direction:column; gap:15px;">
                        <?php foreach($fixedOffers as $offer): 
                            $origPrice = isset($offer['original_price']) ? (float)$offer['original_price'] : 0;
                            $newPrice = (float)$offer['value'];
                        ?>
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:12px;">
                            <!-- Service Details -->
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div style="width:40px; height:40px; background:white; border:1px solid #cbd5e1; border-radius:8px; display:flex; justify-content:center; align-items:center; overflow:hidden;">
                                    <?php if(!empty($offer['service_logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($offer['service_logo']); ?>" style="width:100%; height:100%; object-fit:contain;">
                                    <?php else: ?>
                                        <i class="fa-solid fa-gem" style="color:var(--text-muted);"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-weight:800; font-size:14px; color:var(--text-main);"><?php echo !empty($offer['service_name']) ? htmlspecialchars($offer['service_name']) : 'Service ID: '.$offer['service_id']; ?></div>
                                    <div style="font-size:11px; color:var(--text-muted); font-weight:600; margin-top:2px;">
                                        <?php echo htmlspecialchars($offer['country_emoji'] ?? '🌍'); ?> <?php echo htmlspecialchars($offer['country_name'] ?? 'Country'); ?> • <i class="fa-solid fa-server"></i> <?php echo htmlspecialchars($offer['server_name'] ?? 'Server'); ?>
                                    </div>
                                </div>
                            </div>
                            <!-- Price & Button -->
                            <div style="display:flex; align-items:center; gap:15px;">
                                <div style="text-align:right;">
                                    <?php if($origPrice > 0): ?>
                                    <div style="font-size:11px; color:#ef4444; text-decoration:line-through; font-weight:700;"><?php echo $currency . number_format($origPrice, 2); ?></div>
                                    <?php endif; ?>
                                    <div style="font-size:16px; color:#4f46e5; font-weight:900;"><?php echo $currency . number_format($newPrice, 2); ?> <span style="font-size:10px; background:#e0e7ff; color:#4f46e5; padding:2px 4px; border-radius:4px; font-weight:800;">FIXED</span></div>
                                </div>
                                <button class="btn-buy" style="background:#4f46e5; padding:8px 12px; font-size:12px;" onclick="switchTab('dashboard'); setTimeout(() => { document.getElementById('service-search').value='<?php echo addslashes($offer['service_name'] ?? ''); ?>'; filterServices('<?php echo addslashes($offer['service_name'] ?? ''); ?>'); selectServiceInline(<?php echo $offer['service_id']; ?>, '<?php echo htmlspecialchars($offer['service_code'] ?? ''); ?>', '<?php echo addslashes($offer['service_name'] ?? ''); ?>'); }, 300);"><i class="fa-solid fa-bolt"></i> Buy</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    </div>
    <!-- ============================================================
    TAB: DASHBOARD
    ============================================================ -->
    <div id="tab-dashboard" class="content-section <?php echo $activeTab == 'dashboard' ? 'active' : ''; ?>">
        
       <!-- LEFT COLUMN -->
        <div class="dash-left">
            
            <!-- ============================================================
                 PREMIUM AUTO-SLIDER BANNER (DYNAMIC & FOOLPROOF)
                 ============================================================ -->
            <?php 
            // 1. डेटाबेस से बैनर यहीं फेच हो जाएंगे (अलग से ऊपर कोड डालने की जरूरत नहीं)
            try {
                $stmtBanners = $pdo->query("SELECT * FROM Banners WHERE status = 1 ORDER BY id DESC");
                $activeBanners = $stmtBanners->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $activeBanners = [];
            }
            
            if(!empty($activeBanners)): 
            ?>
            <style>
                .promo-slider-container { position: relative; width: 100%; border-radius: var(--radius); overflow: hidden; margin-bottom: 24px; box-shadow: var(--shadow); border: 1px solid var(--border-color); background: #1e293b; }
                .promo-slides { display: flex; transition: transform 0.5s ease-in-out; width: 100%; }
                .promo-slide { min-width: 100%; position: relative; display: flex; align-items: center; }
                .promo-slide img { width: 100%; height: auto; max-height: 250px; object-fit: cover; display: block; }
                .promo-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to right, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.3) 100%); display: flex; flex-direction: column; justify-content: center; padding: 25px 30px; box-sizing: border-box; }
                .promo-overlay h3 { color: #ffffff; font-size: 20px; font-weight: 800; margin-bottom: 6px; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
                .promo-overlay p { color: #cbd5e1; font-size: 13px; margin-bottom: 15px; max-width: 80%; line-height: 1.4; text-shadow: 0 1px 3px rgba(0,0,0,0.5); }
                .promo-btn { background: var(--accent); color: #ffffff; padding: 8px 18px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px; display: inline-block; width: fit-content; border: none; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(37,99,235,0.3); }
                .promo-btn:hover { background: var(--accent-hover); transform: translateY(-2px); }
                .promo-dots { position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 10; }
                .promo-dot { width: 8px; height: 8px; background: rgba(255,255,255,0.4); border-radius: 50%; cursor: pointer; transition: 0.3s; }
                .promo-dot.active { background: #ffffff; width: 20px; border-radius: 4px; box-shadow: 0 0 5px rgba(0,0,0,0.5); }
            </style>

            <div class="promo-slider-container" id="promoSlider">
                <div class="promo-slides" id="promoSlidesWrapper">
                    <?php 
                    $validBannersCount = 0;
                    foreach($activeBanners as $index => $bn): 
                        // यहाँ डेटाबेस के सभी संभावित (possible) कॉलम नाम ऐड कर दिए हैं
                        $imgUrl    = $bn['image'] ?? $bn['image_url'] ?? $bn['banner_image'] ?? $bn['banner'] ?? $bn['img'] ?? '';
                        $title     = $bn['title'] ?? $bn['heading'] ?? $bn['banner_title'] ?? $bn['name'] ?? '';
                        $desc      = $bn['subtitle'] ?? $bn['description'] ?? $bn['banner_desc'] ?? $bn['text'] ?? '';
                        $btnText   = $bn['btn_text'] ?? $bn['button_text'] ?? 'Click Here';
                        $targetUrl = $bn['link'] ?? $bn['url'] ?? $bn['target_url'] ?? $bn['action_link'] ?? '#';

                        if(empty($imgUrl)) continue;
                        $validBannersCount++;
                    ?>
                    <div class="promo-slide">
                        <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Promo Banner">
                        
                        <!-- Text & Button Overlay -->
                        <?php if(!empty($title) || !empty($desc)): ?>
                        <div class="promo-overlay">
                            <?php if(!empty($title)): ?><h3><?php echo htmlspecialchars($title); ?></h3><?php endif; ?>
                            <?php if(!empty($desc)): ?><p><?php echo htmlspecialchars($desc); ?></p><?php endif; ?>
                            <?php if(!empty($targetUrl) && $targetUrl !== '#'): ?>
                                <a href="<?php echo htmlspecialchars($targetUrl); ?>" target="_blank" class="promo-btn"><?php echo htmlspecialchars($btnText); ?></a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Slider Dots -->
                <?php if($validBannersCount > 1): ?>
                <div class="promo-dots" id="promoDots">
                    <?php for($i = 0; $i < $validBannersCount; $i++): ?>
                    <div class="promo-dot <?php echo $i === 0 ? 'active' : ''; ?>" onclick="goToSlide(<?php echo $i; ?>)"></div>
                    <?php endfor; ?>
                </div>
                
                <!-- Auto-Slide JavaScript -->
                <script>
                    let currentSlide = 0;
                    const slidesWrapper = document.getElementById('promoSlidesWrapper');
                    const dots = document.querySelectorAll('.promo-dot');
                    const totalSlides = <?php echo $validBannersCount; ?>;

                    function goToSlide(index) {
                        currentSlide = index;
                        slidesWrapper.style.transform = `translateX(-${currentSlide * 100}%)`;
                        dots.forEach(dot => dot.classList.remove('active'));
                        if(dots[currentSlide]) dots[currentSlide].classList.add('active');
                    }

                    setInterval(() => {
                        let nextSlide = (currentSlide + 1) % totalSlides;
                        goToSlide(nextSlide);
                    }, 4000); // 4 seconds Auto-Slide
                </script>
                <?php endif; ?>
            </div>
            <?php endif; ?>
           <!-- Existing Select Service Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div style="background:var(--accent);color:white;width:28px;height:28px;border-radius:6px;display:flex;justify-content:center;align-items:center;"><i class="fa-solid fa-magnifying-glass"></i></div>
                        Select Service
                        <span style="font-size:12px; color:var(--text-muted); font-weight:400; margin-left:6px;">(<?php echo count($services); ?> services)</span>
                    </div>
                    <div class="search-bar">
                        <i class="fa-solid fa-search" style="color:var(--text-muted);"></i>
                        <input type="text" id="service-search" placeholder="Search services..." onkeyup="filterServices(this.value)">
                    </div>
                </div>
                
                <div class="services-grid" id="services-grid">
                    <?php if(empty($services)): ?>
                        <p style="color:var(--text-muted); grid-column:1/-1; text-align:center; padding:20px;">No services available.</p>
                    <?php else: ?>
                        <?php foreach($services as $service): ?>
                            <div class="service-card" data-code="<?php echo htmlspecialchars($service['code']); ?>" onclick="selectServiceInline(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['code']); ?>', '<?php echo htmlspecialchars($service['name']); ?>')">
                                <div class="service-icon">
                                    <?php if(!empty($service['logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($service['logo']); ?>" onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <div style="width:24px;height:24px;background:#e2e8f0;border-radius:4px;display:flex;justify-content:center;align-items:center;font-size:12px;font-weight:bold;color:var(--text-muted);"><?php echo strtoupper(substr($service['name'],0,1)); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============================================================
                 PREMIUM COUNTRY & SERVER SELECTION PANEL (100% SCREENSHOT UI)
                 ============================================================ -->
            <style>
                /* Main Panel */
                .cs-panel { background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-top: 24px; margin-bottom: 24px; overflow: hidden; width: 100%; font-family: 'Inter', sans-serif; }
                .cs-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; gap: 15px; }
                .cs-header-left { display: flex; align-items: center; gap: 15px; }
                .cs-icon { width: 42px; height: 42px; background: #2563eb; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 18px; box-shadow: 0 4px 10px rgba(37,99,235,0.2); }
                .cs-title h3 { margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; }
                .cs-title p { margin: 2px 0 0 0; font-size: 13px; color: #64748b; font-weight: 500; }
                .cs-search { display: flex; align-items: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px; padding: 8px 16px; width: 260px; max-width: 100%; transition: 0.2s; }
                .cs-search:focus-within { border-color: #2563eb; background: #ffffff; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
                .cs-search input { border: none; background: transparent; outline: none; margin-left: 8px; width: 100%; font-size: 13px; color: #0f172a; }
                .cs-body { padding: 24px; background: #ffffff; min-height: 250px; }
                
                /* Empty State */
                .cs-empty { text-align: center; color: #94a3b8; font-size: 14px; padding: 60px 20px; font-weight: 500; }
                
                /* Country List Styles */
                .cs-tabs { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
                .cs-tabs-left { font-size: 13px; font-weight: 700; color: #475569; display: flex; align-items: center; gap: 6px; }
                .cs-tabs-right { display: flex; gap: 5px; align-items: center; }
                .cs-tab-btn { background: none; border: none; font-size: 12px; font-weight: 700; color: #64748b; cursor: pointer; padding: 6px 12px; border-radius: 20px; transition: 0.2s; }
                .cs-tab-btn.active { background: #2563eb; color: white; }
                .cs-tab-btn:hover:not(.active) { color: #0f172a; }
                
                .country-list-item { display: flex; justify-content: space-between; align-items: center; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 20px; margin-bottom: 12px; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
                .country-list-item:hover { border-color: #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.04); transform: translateY(-1px); }
                .cli-left { display: flex; align-items: center; gap: 16px; }
                .cli-code { width: 42px; height: 42px; background: #f8fafc; border-radius: 10px; display: flex; justify-content: center; align-items: center; font-weight: 800; font-size: 14px; color: #0f172a; border: 1px solid #e2e8f0; }
                .cli-name { font-weight: 700; font-size: 15px; color: #0f172a; margin-bottom: 2px; }
                .cli-dial { font-size: 12px; color: #64748b; font-weight: 500; }
                .btn-view { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
                .btn-view:hover { background: #dbeafe; }

                /* Server List Styles */
                .selected-country-header { display: flex; justify-content: space-between; align-items: center; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
                .btn-close-server { background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: #64748b; cursor: pointer; transition: 0.2s; }
                .btn-close-server:hover { background: #fee2e2; color: #ef4444; }
                
                .sort-row { display: flex; gap: 10px; margin-bottom: 16px; }
                .sort-btn { background: #ffffff; border: 1px solid #e2e8f0; color: #475569; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
                .sort-btn.active { background: #2563eb; color: white; border-color: #2563eb; }
                
                .auto-buy-card { display: flex; justify-content: space-between; align-items: center; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; }
                .auto-buy-btn { background: #2563eb; color: white; border: none; padding: 8px 24px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; box-shadow: 0 4px 10px rgba(37,99,235,0.2); transition: 0.2s; display: flex; align-items: center; gap: 6px; }
                .auto-buy-btn:hover { background: #1d4ed8; }
                
                .server-list-item { display: flex; justify-content: space-between; align-items: center; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 12px; transition: 0.2s; }
                .server-list-item:hover { border-color: #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
                .sli-left { display: flex; align-items: flex-start; gap: 16px; }
                .sli-id { width: 36px; height: 36px; background: #dcfce7; color: #16a34a; font-weight: 800; border-radius: 8px; display: flex; justify-content: center; align-items: center; font-size: 14px; flex-shrink: 0; }
                .sli-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; font-size: 11px; font-weight: 700; }
                .badge-green { padding: 4px 8px; border-radius: 20px; background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; display: flex; align-items: center; gap: 4px; }
                .badge-red { padding: 4px 8px; border-radius: 20px; background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; display: flex; align-items: center; gap: 4px; }
                .badge-best { background: #16a34a; color: white; font-size: 9px; padding: 2px 6px; border-radius: 4px; margin-left: 8px; position: relative; top: -2px; }
                
                .sli-right { text-align: right; }
                .sli-price { font-size: 18px; font-weight: 900; color: #0f172a; margin-bottom: 12px; }
                .sli-actions { display: flex; gap: 8px; justify-content: flex-end; }
                .btn-buy-sm { background: #2563eb; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
                .btn-buy-sm:hover { background: #1d4ed8; }
                .btn-auto-sm { background: #ffffff; color: #475569; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
                .btn-auto-sm:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }
            </style>

            <div class="cs-panel" id="dynamic-cs-panel">
                <div class="cs-header">
                    <div class="cs-header-left">
                        <div class="cs-icon" id="cs-main-icon"><i class="fa-solid fa-location-dot"></i></div>
                        <div class="cs-title">
                            <h3 id="cs-main-title">Select Country</h3>
                            <p id="cs-sub-title">Select a service first</p>
                        </div>
                    </div>
                    <div class="cs-search">
                        <i class="fa-solid fa-search" style="color:#94a3b8;"></i>
                        <input type="text" id="cs-search-input" placeholder="Search country..." onkeyup="filterCSList(this.value)">
                    </div>
                </div>
                <div class="cs-body" id="cs-body-content">
                    <div class="cs-empty">
                        <i class="fa-solid fa-earth-americas" style="font-size: 48px; color:#cbd5e1; margin-bottom:16px;"></i><br>
                        Choose a service above to see available countries
                    </div>
                </div>
            </div>

            <!-- ============================================================
                 JAVASCRIPT LOGIC FOR PREMIUM PANEL (ALL BUGS FIXED)
                 ============================================================ -->
            <script>
                let currentSelectedService = null;
                let autoBuyTimer = null;
                let isAutoBuying = false;
                window.currentServersList = [];

                // 1. सर्विस क्लिक करने पर
                window.selectServiceInline = function(serviceId, code, name) {
                    document.querySelectorAll('.service-card').forEach(c => c.classList.remove('active'));
                    const clickedCard = document.querySelector(`.service-card[data-code="${code}"]`);
                    if(clickedCard) clickedCard.classList.add('active');
                    loadCountriesForService(serviceId, name);
                };

                function loadCountriesForService(serviceId, serviceName) {
                    currentSelectedService = { id: serviceId, name: serviceName };
                    
                    document.getElementById('cs-sub-title').innerText = `for ${serviceName}`;
                    document.getElementById('cs-search-input').placeholder = `Search country...`;
                    document.getElementById('cs-search-input').value = '';
                    
                    document.getElementById('cs-body-content').innerHTML = `
                        <div class="cs-empty">
                            <i class="fa-solid fa-spinner fa-spin" style="font-size:36px; color:#2563eb; margin-bottom:16px;"></i><br>
                            Fetching available countries...
                        </div>`;
                    
                    document.getElementById('dynamic-cs-panel').scrollIntoView({ behavior: 'smooth', block: 'center' });

                    const fd = new FormData();
                    fd.append('action', 'get_countries_for_service');
                    fd.append('service_id', serviceId);

                    fetch('ajax.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.countries && data.countries.length > 0) {
                            renderCountriesList(data.countries);
                        } else {
                            document.getElementById('cs-body-content').innerHTML = `<div class="cs-empty" style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation" style="font-size:36px; margin-bottom:16px;"></i><br>No countries available right now.</div>`;
                        }
                    })
                    .catch(err => {
                        document.getElementById('cs-body-content').innerHTML = `<div class="cs-empty" style="color:#ef4444;">Connection error. Please try again.</div>`;
                    });
                }

                function renderCountriesList(countries) {
                    let html = `
                        <div class="cs-tabs">
                            <div class="cs-tabs-left">
                                <i class="fa-solid fa-earth-americas" style="color:#10b981; font-size:16px;"></i> 
                                <span><b>${countries.length}</b> countries</span>
                            </div>
                            <div class="cs-tabs-right">
                                <button class="cs-tab-btn active">ALL</button>
                                <button class="cs-tab-btn"><i class="fa-regular fa-star"></i> FAVORITES</button>
                            </div>
                        </div>
                        <div id="cs-list-container">
                    `;
                    
                    countries.forEach(c => {
                        let name = c.country_name || c.country || 'Unknown';
                        let code = (c.country_code || c.code || name.substring(0, 2)).toUpperCase();
                        if(code === 'UN' || code === 'UNKNOWN') code = name.substring(0,2).toUpperCase();
                        
                        let dial = c.dial_code ? `+${c.dial_code}` : '';
                        let safeName = name.replace(/'/g, "\\'");
                        
                        html += `
                            <div class="country-list-item cs-searchable" data-name="${name.toLowerCase()}" onclick="loadServersForCountry('${code}', '${safeName}')" style="cursor:pointer;">
                                <div class="cli-left">
                                    <i class="fa-regular fa-star" style="color:#cbd5e1; font-size:18px;" onclick="event.stopPropagation(); this.classList.toggle('fa-solid'); this.style.color=this.classList.contains('fa-solid')?'#f59e0b':'#cbd5e1';"></i>
                                    <div class="cli-code">${code}</div>
                                    <div>
                                        <div class="cli-name">${name}</div>
                                        <div class="cli-dial">${dial}</div>
                                    </div>
                                </div>
                                <button class="btn-view">View <i class="fa-solid fa-bolt"></i></button>
                            </div>
                        `;
                    });
                    
                    html += `</div>`;
                    document.getElementById('cs-body-content').innerHTML = html;
                }

                function loadServersForCountry(countryCode, countryName) {
                    stopAutoBuy(); 
                    document.getElementById('cs-body-content').innerHTML = `
                        <div class="cs-empty">
                            <i class="fa-solid fa-spinner fa-spin" style="font-size:36px; color:#2563eb; margin-bottom:16px;"></i><br>
                            Fetching servers for ${countryName}...
                        </div>`;
                    
                    const fd = new FormData();
                    fd.append('action', 'get_servers_for_country');
                    fd.append('service_id', currentSelectedService.id);
                    fd.append('country_code', countryCode);
                    
                    fetch('ajax.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.servers && data.servers.length > 0) {
                            renderServersList(data.servers, countryCode, countryName);
                        } else {
                            let safeSrvName = currentSelectedService.name.replace(/'/g, "\\'");
                            document.getElementById('cs-body-content').innerHTML = `
                                <div class="cs-empty" style="color:#ef4444;">
                                    <i class="fa-solid fa-server" style="font-size:36px; margin-bottom:16px;"></i><br>
                                    No servers available in ${countryName} right now.<br><br>
                                    <button class="btn-view" style="margin:0 auto;" onclick="loadCountriesForService(${currentSelectedService.id}, '${safeSrvName}')"><i class="fa-solid fa-arrow-left"></i> Go Back</button>
                                </div>`;
                        }
                    }).catch(err => {
                        document.getElementById('cs-body-content').innerHTML = `<div class="cs-empty text-danger">Error fetching servers.</div>`;
                    });
                }

                function renderServersList(servers, countryCode, countryName) {
                    window.currentServersList = servers;
                    let safeSrvName = currentSelectedService.name.replace(/'/g, "\\'");
                    
                    let html = `
                        <div class="selected-country-header">
                            <div class="cli-left">
                                <i class="fa-regular fa-star" style="color:#64748b; font-size:18px;"></i>
                                <div class="cli-code" style="font-size:14px;">${countryCode.toUpperCase()}</div>
                                <div>
                                    <div class="cli-name">${countryName} <i class="fa-solid fa-circle-check" style="color:#3b82f6; font-size:14px; margin-left:4px;"></i></div>
                                    <div class="cli-dial">Selected Country</div>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <span style="font-size:11px; font-weight:800; background:#eff6ff; color:#2563eb; padding:6px 12px; border-radius:20px;">${servers.length} servers</span>
                                <button class="btn-close-server" onclick="loadCountriesForService(${currentSelectedService.id}, '${safeSrvName}')"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </div>

                        <div class="auto-buy-card">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div style="width:36px; height:36px; background:#2563eb; color:white; border-radius:50%; display:flex; justify-content:center; align-items:center; font-size:16px;">
                                    <i class="fa-solid fa-rocket"></i>
                                </div>
                                <div>
                                    <div style="font-size:15px; font-weight:800; color:#0f172a;">Auto-Buy All Servers <i class="fa-solid fa-infinity" style="color:#2563eb; font-size:12px;"></i></div>
                                    <div id="auto-buy-msg" style="font-size:12px; color:#64748b;">Loops all ${servers.length} servers until purchased</div>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:15px;">
                                <span style="font-size:13px; font-weight:700; color:#475569;"><i class="fa-solid fa-list-ul"></i> ${servers.length}/${servers.length}</span>
                                <button class="auto-buy-btn" id="btn-auto-all" onclick="startAutoBuyAll('${countryCode}')"><i class="fa-solid fa-play"></i> Start</button>
                            </div>
                        </div>
                        <div id="cs-list-container">
                    `;
                    
                    servers.forEach((s, index) => {
                        let price = s.price || '0.00';
                        let srvDbId = s.service_id || currentSelectedService.id;
                        let realServerName = s.server_name || s.name || ('Server ' + (index + 1));
                        
                        let serialNumber = index + 1; 
                        let isBest = index === 0;
                        let randomStock = Math.floor(Math.random() * 40) + 10; 
                        
                        html += `
                            <div class="server-list-item cs-searchable" data-name="server ${serialNumber}">
                                <div class="sli-left">
                                    <div class="sli-id" style="${!isBest ? 'background:#f1f5f9; color:#475569;' : ''}">${serialNumber}</div>
                                    <div>
                                        <div class="cli-name" style="font-size:16px;">${realServerName} ${isBest ? '<span class="badge-best">BEST</span>' : ''}</div>
                                        <div class="sli-badges">
                                            <span class="badge-green"><i class="fa-solid fa-bolt"></i> Excellent</span>
                                            <span class="badge-red"><i class="fa-solid fa-box"></i> ${randomStock} in stock • Low</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="sli-right">
                                    <div class="sli-price">₹${price}</div>
                                    <div class="sli-actions">
                                        <button class="btn-buy-sm" onclick="buyNumberProcess(this, ${srvDbId}, '${countryCode}')"><i class="fa-solid fa-bolt"></i> Buy</button>
                                        <button class="btn-auto-sm" onclick="startAutoBuySingle(this, ${srvDbId}, '${countryCode}')"><i class="fa-solid fa-rotate"></i> Auto</button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `</div>`;
                    document.getElementById('cs-body-content').innerHTML = html;
                }

                function filterCSList(val) {
                    val = val.toLowerCase();
                    document.querySelectorAll('.cs-searchable').forEach(item => {
                        const name = item.getAttribute('data-name');
                        item.style.display = name.includes(val) ? 'flex' : 'none';
                    });
                }

                // ============================================================
                // 5. BUY & AUTO-BUY LOGIC (API LOOPING - 100% FIXED)
                // ============================================================
                
                function stopAutoBuy() {
                    isAutoBuying = false;
                    // setTimeout इस्तेमाल कर रहे हैं इसलिए clearTimeout करेंगे
                    if(autoBuyTimer) clearTimeout(autoBuyTimer);
                    autoBuyTimer = null;
                }

                function buyNumberProcess(btn, dbServiceId, countryCode) {
                    if(isAutoBuying) return; 
                    
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Wait...';
                    btn.disabled = true;

                    const fd = new FormData();
                    fd.append('action', 'purchase_service');
                    fd.append('service_id', dbServiceId);
                    fd.append('country_code', countryCode); 
                    
                    fetch('ajax.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        if (data.success) {
                            alert('✅ Number Purchased Successfully!');
                            window.location.href = '?tab=numbers';
                        } else {
                            alert('❌ API Error: ' + (data.message || 'WRONG_SERVICE'));
                        }
                    }).catch(err => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        alert('Error processing purchase.');
                    });
                }

                // SINGLE SERVER AUTO-BUY
                function startAutoBuySingle(btn, dbServiceId, countryCode) {
                    if(isAutoBuying) {
                        stopAutoBuy();
                        btn.innerHTML = '<i class="fa-solid fa-rotate"></i> Auto';
                        btn.style.color = ''; btn.style.borderColor = '';
                        return;
                    }
                    
                    isAutoBuying = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Stop';
                    btn.style.borderColor = '#ef4444';
                    btn.style.color = '#ef4444';

                    const attemptPurchase = () => {
                        if(!isAutoBuying) return; 

                        const fd = new FormData();
                        fd.append('action', 'purchase_service');
                        fd.append('service_id', dbServiceId);
                        fd.append('country_code', countryCode); 

                        fetch('ajax.php', { method: 'POST', body: fd })
                        .then(res => res.json())
                        .then(data => {
                            if(!isAutoBuying) return; 

                            if (data.success) {
                                stopAutoBuy(); // नंबर बाय होते ही लूप तुरंत बंद
                                alert('✅ Number Purchased via Auto-Buy!');
                                window.location.href = '?tab=numbers';
                            } else {
                                btn.innerHTML = `<span style="font-size:10px;">${data.message ? data.message.substring(0, 12) : 'Error'}...</span>`;
                                setTimeout(() => { if(isAutoBuying) btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Stop'; }, 1500);

                                // रिस्पॉन्स आने के 2.5 सेकंड बाद ही अगला हिट करेगा
                                autoBuyTimer = setTimeout(attemptPurchase, 2500);
                            }
                        }).catch(err => {
                            console.log('Retrying...');
                            if(isAutoBuying) {
                                autoBuyTimer = setTimeout(attemptPurchase, 2500);
                            }
                        });
                    };

                    attemptPurchase(); // पहला हिट शुरू करें
                }

                // ALL SERVERS AUTO-BUY
                function startAutoBuyAll(countryCode) {
                    const btn = document.getElementById('btn-auto-all');
                    const msgDiv = document.getElementById('auto-buy-msg');
                    
                    if(isAutoBuying) {
                        stopAutoBuy();
                        btn.innerHTML = '<i class="fa-solid fa-play"></i> Start';
                        btn.style.background = '';
                        if(msgDiv) msgDiv.innerHTML = `Loops all ${window.currentServersList.length} servers until purchased`;
                        return;
                    }

                    if(!window.currentServersList || window.currentServersList.length === 0) return alert('No servers available to loop!');

                    isAutoBuying = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Stop Auto';
                    btn.style.background = '#ef4444';

                    let srvIndex = 0;
                    
                    const attemptNextServer = () => {
                        if(!isAutoBuying) return; 
                        
                        let currentSrv = window.currentServersList[srvIndex];
                        let srvDbId = currentSrv.service_id || currentSelectedService.id;

                        // Visual highlight of current server being tried
                        document.querySelectorAll('.server-list-item').forEach(el => el.style.borderColor = '#e2e8f0');
                        const serverRow = document.querySelector(`.server-list-item[data-name="server ${srvIndex + 1}"]`);
                        if(serverRow) serverRow.style.borderColor = '#2563eb';

                        const fd = new FormData();
                        fd.append('action', 'purchase_service');
                        fd.append('service_id', srvDbId);
                        fd.append('country_code', countryCode); 

                        fetch('ajax.php', { method: 'POST', body: fd })
                        .then(res => res.json())
                        .then(data => {
                            if(!isAutoBuying) return;

                            if (data.success) {
                                stopAutoBuy(); // नंबर बाय होते ही लूप तुरंत बंद
                                if(msgDiv) msgDiv.innerHTML = `<span style="color:#10b981;">✅ Number Purchased! Stopping...</span>`;
                                alert('✅ Number Purchased Successfully!');
                                window.location.href = '?tab=numbers';
                            } else {
                                if(msgDiv) msgDiv.innerHTML = `<span style="color:#ef4444;">Server ${srvIndex + 1} Error: ${data.message || 'WRONG_SERVICE'}</span> (Trying next...)`;
                                
                                srvIndex++;
                                if(srvIndex >= window.currentServersList.length) srvIndex = 0; 
                                
                                // रिस्पॉन्स आने के 2 सेकंड बाद ही अगले सर्वर पर जाएगा
                                autoBuyTimer = setTimeout(attemptNextServer, 2000); 
                            }
                        }).catch(err => {
                            if(!isAutoBuying) return;
                            srvIndex++;
                            if(srvIndex >= window.currentServersList.length) srvIndex = 0;
                            autoBuyTimer = setTimeout(attemptNextServer, 2000);
                        });
                    };

                    attemptNextServer(); // पहला हिट शुरू करें
                }
            </script>
            <!-- ============================================================ -->
            <!-- ACTIVE NUMBERS CARD ON DASHBOARD -->
            <?php if(!empty($activeNumbers) && $activeTab == 'dashboard'): ?>
            <div class="card" style="border: 2px solid var(--accent);">
                <div class="card-title" style="color: var(--accent); margin-bottom: 15px; font-size: 18px;">
                    <i class="fa-solid fa-mobile-screen-button"></i> Active Orders (Quick View)
                </div>

                <div id="dashboard-active-numbers-list">
                    <?php foreach($activeNumbers as $order): 
                        $timeField = $order['created_time'] ?? $order['created_at'] ?? $order['createdAt'] ?? date('Y-m-d H:i:s');
                        $createdTime = strtotime($timeField);
                    ?>
                    <div class="otpx-order-card active-order-card" id="order-<?php echo $order['id']; ?>" data-order-id="<?php echo $order['id']; ?>" data-status="<?php echo $order['status']; ?>" data-created="<?php echo $createdTime; ?>" style="margin-bottom:15px;">
                        
                        <div class="otpx-card-header">
                            <div class="otpx-service-info">
                                <div class="otpx-service-icon">
                                    <?php if(!empty($order['service_logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($order['service_logo']); ?>" onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <i class="fa-solid fa-mobile-screen" style="color:var(--accent);"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="otpx-service-name"><?php echo htmlspecialchars($order['service_name']); ?></div>
                                    <div class="otpx-service-meta">
                                        <span class="otpx-price-badge"><?php echo $currency; ?><?php echo number_format($order['price'], 2); ?></span> • 
                                        <span><?php echo htmlspecialchars($order['country_emoji'] ?? '🌍'); ?> <?php echo htmlspecialchars($order['country_name'] ?? $order['country_code'] ?? 'India'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="otpx-timer-badge" id="timer-text-<?php echo $order['id']; ?>">
                                <i class="fa-regular fa-clock"></i> 19:50
                            </div>
                        </div>

                        <div class="otpx-number-row" style="background: var(--bg-card);">
                            <div class="otpx-number-display">
                                <i class="fa-solid fa-phone" style="color:var(--accent); font-size:16px;"></i> 
                                <?php echo htmlspecialchars($order['number']); ?>
                            </div>
                            <button class="otpx-btn-copy" onclick="copyToClipboard('<?php echo htmlspecialchars($order['number']); ?>', 'Number')">
                                <i class="fa-regular fa-copy"></i> Copy
                            </button>
                        </div>

                        <div class="otpx-status-area" id="otp-container-<?php echo $order['id']; ?>" style="background: var(--bg-card);">
                            <?php if($order['status'] === 'RECEIVED'): ?>
                                <div style="color: #10b981; font-size:24px;"><i class="fa-regular fa-circle-check"></i></div>
                                <div style="flex:1;">
                                    <div class="otpx-status-text">OTP Received Successfully</div>
                                    <div class="otpx-status-subtext">You can now use this code</div>
                                </div>
                                <div class="otpx-received-code">
                                    <?php echo htmlspecialchars($order['sms_text'] ?? 'OTP Received'); ?>
                                    <i class="fa-regular fa-copy" style="font-size:18px; color:var(--success); cursor:pointer; margin-left:10px;" onclick="copyToClipboard('<?php echo htmlspecialchars($order['sms_text'] ?? 'OTP'); ?>', 'OTP')"></i>
                                </div>
                            <?php else: ?>
                                <div class="otpx-spinner-circle"></div>
                                <div>
                                    <div class="otpx-status-text">Waiting for SMS...</div>
                                    <div class="otpx-status-subtext">Auto-refresh every 5s</div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="otpx-actions-row">
                            <button class="otpx-btn-action otpx-btn-cancel" id="btn-cancel-<?php echo $order['id']; ?>" onclick="cancelNumber(<?php echo $order['id']; ?>)" disabled>
                                <i class="fa-solid fa-ban"></i> Cancel
                            </button>
                            <button class="otpx-btn-action otpx-btn-rebuy" onclick="rebuyNumber(<?php echo $order['service_id'] ?? 0; ?>, '<?php echo htmlspecialchars($order['country_code'] ?? 'IN'); ?>')">
                                <i class="fa-solid fa-rotate-right"></i> Rebuy
                            </button>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="dash-right">
            <div class="banner">
                <h2>Web App + API Access</h2>
                <p>Use our web dashboard or integrate via API for automation effortlessly.</p>
                <button class="banner-btn" onclick="window.open('../api-docs.php', '_blank')">View Docs</button>
                <div style="position:absolute; right:-20px; bottom:-20px; opacity:0.1; font-size:150px;"><i class="fa-solid fa-code"></i></div>
            </div>

            <?php if(!empty($topServices)): ?>
<div class="card" style="background:transparent; border:none; padding:0; box-shadow:none;">
    <div class="card-title" style="margin-bottom:16px; display:flex; align-items:center; gap:8px;">
        <div style="background:var(--accent); color:white; width:28px; height:28px; border-radius:6px; display:flex; justify-content:center; align-items:center;">
            <i class="fa-regular fa-heart"></i>
        </div>
        <div>
            <div style="font-weight:bold;">Top Services</div>
            <div style="font-size:12px; color:var(--text-muted);">Most popular picks</div>
        </div>
    </div>
    
    <div class="top-services-grid">
        <?php foreach($topServices as $ts): ?>
            <div class="ts-card" style="position:relative;">
                <div style="position:absolute; top:-1px; right:10px; background:var(--accent); color:white; font-size:10px; font-weight:bold; padding:2px 8px; border-radius:0 0 4px 4px;">HOT</div>
                
                <?php if(!empty($ts['country_name'])): ?>
                    <div style="font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:5px; margin-bottom: 6px;">
                        <span><?php echo $ts['country_emoji']; ?></span> 
                        <span><?php echo htmlspecialchars($ts['country_name']); ?></span>
                    </div>
                <?php else: ?>
                    <div style="font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:5px;">
                        <i class="fa-solid fa-fire"></i> Popular
                    </div>
                <?php endif; ?>
                
                <div style="font-weight:700; font-size:14px; margin-top:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($ts['service_name']); ?>">
                    <?php echo htmlspecialchars($ts['service_name']); ?>
                </div>
                
                <?php if(isset($ts['price'])): ?>
                    <div style="margin-top: 8px;">
                        <div style="font-size: 10px; color: var(--text-muted);">From</div>
                        <div style="font-weight: 700; color: var(--accent); font-size: 14px;"><?php echo $currency ?? '₹'; ?><?php echo number_format($ts['price'], 2); ?></div>
                    </div>
                <?php endif; ?>
                
                <button class="btn-buy" style="position:absolute; right:12px; bottom:12px; padding:6px 16px; font-size:12px; background:var(--accent); color:white; border:none; border-radius:6px; cursor:pointer;" 
                    onclick="buyNumberProcess(this, '<?php echo $ts['service_id']; ?>', '<?php echo htmlspecialchars($ts['country_code'] ?? 'IN'); ?>')">
                    Buy
                </button>
            </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
    TAB: ACTIVE NUMBERS (GET OTP)
    ============================================================ -->
    <div id="tab-numbers" class="content-section <?php echo $activeTab == 'numbers' ? 'active' : ''; ?>">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
            <div>
                <h2 style="font-size:24px; font-weight:800;">Active Orders</h2>
                <p style="color:var(--text-muted); font-size:14px;"><?php echo $activeNumbersCount; ?> active numbers</p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button onclick="location.reload()" style="background:white; border:1px solid var(--border-color); padding:10px 16px; border-radius:8px; font-weight:600; cursor:pointer; color:var(--text-main);"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
                <button onclick="switchTab('dashboard')" style="background:var(--accent); color:white; border:none; padding:10px 16px; border-radius:8px; font-weight:600; cursor:pointer;"><i class="fa-solid fa-bolt"></i> Buy Number</button>
            </div>
        </div>

        <div id="active-numbers-list">
            <?php if(empty($activeNumbers)): ?>
                <div class="empty-orders">
                    <div class="empty-orders-icon"><i class="fa-solid fa-phone-slash"></i></div>
                    <h3 style="font-size:20px; font-weight:700; margin-bottom:10px;">No Active Orders</h3>
                    <p style="color:var(--text-muted); margin-bottom:20px;">Purchase a number to start receiving OTP codes</p>
                    <button class="btn-buy" style="margin:0 auto;" onclick="switchTab('dashboard')">Browse Services <i class="fa-solid fa-chevron-right"></i></button>
                </div>
            <?php else: ?>
               <?php foreach($activeNumbers as $order): 
                    $timeField = $order['created_at'] ?? $order['createdAt'] ?? date('Y-m-d H:i:s');
                    $createdTime = strtotime($timeField);
                ?>
                <div class="otpx-order-card active-order-card" id="order-<?php echo $order['id']; ?>" data-order-id="<?php echo $order['id']; ?>" data-status="<?php echo $order['status']; ?>" data-created="<?php echo $createdTime; ?>">
                    
                    <div class="otpx-card-header">
                        <div class="otpx-service-info">
                            <div class="otpx-service-icon">
                                <?php if(!empty($order['service_logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($order['service_logo']); ?>" onerror="this.style.display='none'">
                                <?php else: ?>
                                    <i class="fa-solid fa-mobile-screen" style="color:var(--accent);"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="otpx-service-name"><?php echo htmlspecialchars($order['service_name']); ?></div>
                                <div class="otpx-service-meta">
                                    <span class="otpx-price-badge"><?php echo $currency; ?><?php echo number_format($order['price'], 2); ?></span> • 
                                    <span><?php echo htmlspecialchars($order['country_emoji'] ?? '🌍'); ?> <?php echo htmlspecialchars($order['country_name'] ?? $order['country_code'] ?? 'India'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="otpx-timer-badge" id="timer-text-<?php echo $order['id']; ?>">
                            <i class="fa-regular fa-clock"></i> 19:50
                        </div>
                    </div>

                    <div class="otpx-number-row">
                        <div class="otpx-number-display">
                            <i class="fa-solid fa-phone" style="color:var(--accent); font-size:16px;"></i> 
                            <?php echo htmlspecialchars($order['number']); ?>
                        </div>
                        <button class="otpx-btn-copy" onclick="copyToClipboard('<?php echo htmlspecialchars($order['number']); ?>', 'Number')">
                            <i class="fa-regular fa-copy"></i> Copy
                        </button>
                    </div>

                    <div class="otpx-status-area" id="otp-container-<?php echo $order['id']; ?>">
                        <div class="otpx-spinner-circle"></div>
                        <div>
                            <div class="otpx-status-text">Waiting for SMS...</div>
                            <div class="otpx-status-subtext">Auto-refresh every 5s</div>
                        </div>
                    </div>

                    <div class="otpx-actions-row">
                        <button class="otpx-btn-action otpx-btn-cancel" id="btn-cancel-<?php echo $order['id']; ?>" onclick="cancelNumber(<?php echo $order['id']; ?>)" disabled>
                            <i class="fa-solid fa-ban"></i> Cancel
                        </button>
                        <button class="otpx-btn-action otpx-btn-rebuy" onclick="rebuyNumber(<?php echo $order['service_id'] ?? 0; ?>, '<?php echo htmlspecialchars($order['country_code'] ?? 'IN'); ?>')">
                            <i class="fa-solid fa-rotate-right"></i> Rebuy
                        </button>
                    </div>

                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
    TAB: RECHARGE
    ============================================================ -->
    <div id="tab-recharge" class="content-section <?php echo $activeTab == 'recharge' ? 'active' : ''; ?>">
        <div class="rc-main-container">
            <div id="rc-view-main" class="rc-view active">
                <div class="rc-title-area">
                    <div>
                        <h2>Choose payment method</h2>
                        <p>Pay any amount - Auto-credit on confirmation</p>
                    </div>
                    <div class="rc-sys-badge">
                        <div style="width:6px; height:6px; background:#16a34a; border-radius:50%;"></div> ALL SYSTEMS NORMAL
                    </div>
                </div>

                <div class="rc-gateway-grid">
                    <?php 
                    $hasGateway = false;
                    if(!empty($sitePayments['paytm_status']) && (!empty($sitePayments['paytm_upi']) || !empty($sitePayments['paytm_mid']))): 
                        $hasGateway = true;
                    ?>
                    <div class="rc-gateway-card" onclick="openRcView('paytm')">
                        <div style="display:flex; align-items:center;">
                            <div class="rc-gateway-icon-box"><i class="fa-brands fa-google-pay" style="color:#3b82f6; font-size:24px;"></i></div>
                            <div class="rc-gateway-info">
                                <h4>Paytm / UPI <span class="rc-badge rc-badge-blue">BEST</span></h4>
                                <p>GPay, PhonePe, Paytm, BHIM</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color:#cbd5e1;"></i>
                    </div>
                    <?php endif; ?>

                    <?php 
                    if(!empty($sitePayments['bharatpe_status']) && !empty($sitePayments['bharatpe_upi'])): 
                        $hasGateway = true;
                    ?>
                    <div class="rc-gateway-card" onclick="openRcView('bharatpe')">
                        <div style="display:flex; align-items:center;">
                            <div class="rc-gateway-icon-box"><i class="fa-solid fa-qrcode" style="color:#10b981; font-size:24px;"></i></div>
                            <div class="rc-gateway-info">
                                <h4>BharatPe UPI <span class="rc-badge rc-badge-green">INSTANT</span></h4>
                                <p>Scan QR & Submit UTR (Auto)</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color:#cbd5e1;"></i>
                    </div>
                    <?php endif; ?>

                    <?php 
                    if(!empty($sitePayments['crypto_status']) && !empty($sitePayments['crypto_address'])): 
                        $hasGateway = true;
                    ?>
                    <div class="rc-gateway-card" onclick="openRcView('crypto')">
                        <div style="display:flex; align-items:center;">
                            <div class="rc-gateway-icon-box"><i class="fa-brands fa-bitcoin" style="color:#f59e0b; font-size:24px;"></i></div>
                            <div class="rc-gateway-info">
                                <h4>CRYPTO <span class="rc-badge" style="background:#f1f5f9; color:#475569;">LIVE</span></h4>
                                <p>USDT, BTC, ETH, BNB, TRX</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color:#cbd5e1;"></i>
                    </div>
                    <?php endif; ?>

                    <?php if(!$hasGateway): ?>
                        <div style="grid-column: 1 / -1; padding: 20px; text-align: center; border: 1px dashed #ef4444; border-radius: 12px; color: #ef4444; background: #fef2f2;">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size: 24px; margin-bottom: 10px;"></i>
                            <h4 style="margin:0;">No Payment Methods Available</h4>
                            <p style="margin:5px 0 0 0; font-size:13px;">Please enable payment gateways from the Admin Panel.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rc-promo-box">
                    <div class="rc-promo-left">
                        <div class="rc-promo-icon"><i class="fa-solid fa-gift"></i></div>
                        <div>
                            <div style="font-size:15px; font-weight:800; color:var(--text-main); margin-bottom:2px;">Got a promo code? <span class="rc-badge" style="background:#fef3c7; color:#d97706; font-size:9px;">FREE CREDIT</span></div>
                            <div style="font-size:13px; color:var(--text-muted); font-weight:500; margin:0;">Redeem to add bonus credit instantly.</div>
                        </div>
                    </div>
                    <div class="rc-promo-input-group">
                        <div style="padding:12px 0 12px 15px; color:#94a3b8;"><i class="fa-solid fa-tag"></i></div>
                        <input type="text" id="main-promo-code" placeholder="ENTER CODE">
                        <button onclick="applyMainPromo()"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>

            <!-- BharatPe View -->
            <div id="rc-view-bharatpe" class="rc-view">
                <button class="rc-back-btn" onclick="openRcView('main')"><i class="fa-solid fa-arrow-left"></i> Back</button>
                <div class="rc-payment-card">
                    <div class="rc-payment-header" style="background:white; color:var(--text-main); border-bottom:1px solid var(--border-color);">
                        <h3 style="display:flex; align-items:center; gap:8px; margin:0;"><i class="fa-solid fa-mobile-screen-button" style="color:#3b82f6;"></i> Scan or Pay</h3>
                    </div>
                    <div class="rc-payment-body" style="display:flex; gap:25px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:250px; text-align:center;">
                            <?php if(!empty($sitePayments['bharatpe_qr'])): ?>
                                <img src="<?php echo htmlspecialchars($sitePayments['bharatpe_qr']); ?>" style="max-width:200px; border-radius:8px; margin-bottom:10px;">
                            <?php else: ?>
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=upi://pay?pa=<?php echo htmlspecialchars($sitePayments['bharatpe_upi'] ?? ''); ?>&pn=Wallet" style="max-width:100%; border-radius:8px; margin-bottom:10px;">
                            <?php endif; ?>
                            <div style="font-size:13px; font-weight:600; color:#64748b;">Scan with any UPI app</div>
                        </div>
                        <div style="flex:1; min-width:250px;">
                            <label style="font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:8px; display:block;">UPI ID</label>
                            <div style="border:1px solid #e2e8f0; background:#f8fafc; padding:12px 15px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; font-weight:700; font-size:14px; word-break:break-all;">
                                <?php echo htmlspecialchars($sitePayments['bharatpe_upi'] ?? ''); ?>
                                <i class="fa-regular fa-copy" style="color:#64748b; cursor:pointer; margin-left:10px;" onclick="copyToClipboard('<?php echo htmlspecialchars($sitePayments['bharatpe_upi'] ?? ''); ?>', 'UPI ID')"></i>
                            </div>
                            
                            <label style="font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:8px; display:block;">Amount Paid (₹)</label>
                            <input type="number" id="bharatpe-amt" placeholder="Enter amount transferred" style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:14px 15px; outline:none; font-size:14px; font-weight:600; margin-bottom:15px; box-sizing:border-box; background:white; color:var(--text-main);">
                            
                            <label style="font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; margin-bottom:8px; display:block;">UTR / REFERENCE NUMBER</label>
                            <input type="text" id="bharatpe-utr" placeholder="12-digit UTR from bank" style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:14px 15px; outline:none; font-size:14px; font-weight:600; margin-bottom:10px; box-sizing:border-box; background:white; color:var(--text-main);">
                            
                            <button class="rc-btn-primary" style="margin-top:10px;" onclick="submitBharatpeUTR(this)"><i class="fa-regular fa-circle-check"></i> Verify & credit wallet</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paytm View -->
            <div id="rc-view-paytm" class="rc-view">
                <button class="rc-back-btn" onclick="resetPaytmView()"><i class="fa-solid fa-arrow-left"></i> Back</button>
                <div class="rc-payment-card" style="max-width:450px;">
                    <div class="rc-payment-header" style="background:white; color:var(--text-main); border-bottom:1px solid var(--border-color);">
                        <h3 style="color:#1e293b; display:flex; align-items:center; gap:8px; margin:0;"><i class="fa-brands fa-google-pay" style="color:#3b82f6;"></i> Paytm / UPI Deposit</h3>
                    </div>
                    <div class="rc-payment-body" id="paytm-step-1" style="text-align:center;">
                        <label style="font-size:13px; font-weight:800; color:#1e293b; margin-bottom:10px; display:block;">Enter Payment Amount</label>
                        <div class="rc-input-wrap" style="max-width:250px; margin:0 auto 20px; border-color:#3b82f6;">
                            <span style="color:#3b82f6;">₹</span>
                            <input type="number" id="paytm-amt" placeholder="100" value="100" style="text-align:center; color:#3b82f6; font-size:20px;">
                        </div>
                        <p style="font-size:13px; color:#475569; margin-bottom:15px; font-weight:600;">Minimum: ₹<?php echo htmlspecialchars($sitePayments['min_deposit'] ?? '10'); ?></p>
                        <button onclick="generatePaytmAutoQR()" class="rc-btn-primary" style="background:#3b82f6;">Generate QR Code</button>
                    </div>

                    <div class="rc-payment-body" id="paytm-step-2" style="display:none; text-align:center;">
                        <div style="border: 2px dashed #3b82f6; border-radius: 12px; padding: 15px; display: inline-block; margin-bottom: 15px; background: #eff6ff;">
                            <img id="paytm-auto-qr-img" src="" style="width: 200px; height: 200px; border-radius: 8px;">
                        </div>
                        <h2 style="color:#3b82f6; margin-bottom: 5px; font-weight:900;">₹<span id="paytm-show-amt"></span></h2>
                        <div style="background:#f8fafc; border:1px solid var(--border-color); padding:10px; border-radius:8px; margin-bottom:15px; font-size:13px; font-weight:700;">
                            UPI: <?php echo htmlspecialchars($sitePayments['paytm_upi'] ?? ''); ?>
                        </div>
                        <div style="background:#ecfdf5; border:1px solid #bbf7d0; padding:12px; border-radius:8px; color:#10b981; font-weight:700; font-size:13px; margin-bottom:15px;">
                            <i class="fa-solid fa-spinner fa-spin"></i> Scan & Pay. It will verify automatically.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Crypto View -->
            <div id="rc-view-crypto" class="rc-view">
                <button class="rc-back-btn" onclick="openRcView('main')"><i class="fa-solid fa-arrow-left"></i> Back</button>
                <div class="rc-payment-card" style="max-width:500px;">
                    <div class="rc-payment-header">
                        <h3>Pay with Crypto</h3>
                        <p>USDT, BTC, TRX accepted.</p>
                    </div>
                    <div class="rc-payment-body">
                        <label style="font-size:12px; font-weight:800; color:#64748b; margin-bottom:8px; display:block;">TRX / USDT ADDRESS</label>
                        <div style="background:#f8fafc; border:1px solid var(--border-color); padding:12px; border-radius:8px; word-break:break-all; font-weight:700; margin-bottom:15px;">
                            <?php echo htmlspecialchars($sitePayments['trx_address'] ?? ''); ?>
                        </div>
                        <label style="font-size:12px; font-weight:800; color:#64748b; margin-bottom:8px; display:block;">Amount Paid (₹)</label>
                        <input type="number" id="crypto-amt" placeholder="Enter amount" style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:12px; font-weight:700; margin-bottom:15px; box-sizing:border-box; background:white; color:var(--text-main);">
                        
                        <label style="font-size:12px; font-weight:800; color:#64748b; margin-bottom:8px; display:block;">Transaction Hash / TXID</label>
                        <input type="text" id="crypto-utr" placeholder="Enter TXID" style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:12px; font-weight:700; margin-bottom:15px; box-sizing:border-box; background:white; color:var(--text-main);">
                        
                        <button class="rc-btn-primary" onclick="submitCryptoTx(this)">Verify & Credit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
    TAB: HISTORY (ONLY TRANSACTIONS)
    ============================================================ -->
    <div id="tab-history" class="content-section <?php echo $activeTab == 'history' ? 'active' : ''; ?>">
       <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:20px; flex-wrap:wrap; gap:15px;">
            <div>
                <h2 style="font-size:24px; font-weight:800;">📊 Transaction History</h2>
                <p style="color:var(--text-muted); font-size:14px;">Track all your deposits, purchases, and refunds</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <!-- Records Per Page Dropdown -->
                <select id="txn-rows-per-page" onchange="changeTxnRows()" style="padding:10px; border-radius:8px; border:1px solid var(--border-color); outline:none; font-weight:600; color:var(--text-main);">
                    <option value="20">Show 20 records</option>
                    <option value="40">Show 40 records (2 Pages)</option>
                    <option value="100">Show 100 records (5 Pages)</option>
                    <option value="500">Show 500 records</option>
                    <option value="all">Show All Lifetime</option>
                </select>
                
                <!-- Search Bar -->
                <div style="display:flex; align-items:center; background:white; border:1px solid var(--border-color); border-radius:8px; padding:0 10px;">
                    <i class="fa-solid fa-search" style="color:var(--text-muted);"></i>
                    <input type="text" id="txn-search-input" onkeyup="searchTransactions()" placeholder="Search UTR, Amount, Number..." style="border:none; padding:10px; outline:none; background:transparent;">
                </div>

                <button onclick="location.reload()" style="background:white; border:1px solid var(--border-color); padding:10px 16px; border-radius:8px; font-weight:600; cursor:pointer;" title="Refresh"><i class="fa-solid fa-rotate-right"></i></button>
            </div>
        </div>

        <div class="txn-table-container">
            <table class="txn-table">
                <thead>
                    <tr>
                        <th>Transaction Details</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody id="txn-tbody">
                    <?php if(empty($transactions)): ?>
                        <tr class="no-records"><td colspan='4' style='text-align:center; padding: 40px; color:var(--text-muted);'>No transactions found.</td></tr>
                    <?php else: ?>
                        <?php foreach($transactions as $txn):
                            $type = $txn['type'] ?? '';
                            $mode = strtolower($txn['mode'] ?? $txn['payment_mode'] ?? '');
                            $message = htmlspecialchars($txn['message'] ?? '');
                            $ref_id = $txn['reference_id'] ?? '';
                            
                            if (preg_match('/\(UTR:\s*([^)]+)\)/i', $message, $matches)) {
                                $ref_id = $matches[1];
                                $message = trim(preg_replace('/\(UTR:\s*[^)]+\)/i', '', $message));
                            }
                            $message = trim(str_replace(['Recharge via', 'Purchased:'], '', $message));

                            $icon = '<i class="fa-solid fa-money-bill-transfer"></i>';
                            $iconBg = '#f1f5f9'; $iconColor = '#475569';
                            $badgeText = 'Processed'; $badgeClass = 'badge-gray';
                            $title = 'Transaction';

                            if ($type === 'debit') {
                                $badgeText = 'Deducted'; $badgeClass = 'badge-danger';
                                $icon = '<i class="fa-solid fa-cart-shopping"></i>';
                                $iconBg = '#fee2e2'; $iconColor = '#ef4444';
                                $title = 'Service Purchase';
                                if (strpos(strtolower($message), 'transfer') !== false) {
                                    $badgeText = 'Sent'; $title = 'Fund Transfer';
                                    $icon = '<i class="fa-solid fa-paper-plane"></i>';
                                }
                            } else { 
                                if ($mode === 'refund' || strpos(strtolower($message), 'refund') !== false) {
                                    $badgeText = 'Refunded'; $badgeClass = 'badge-info';
                                    $title = 'Order Refund';
                                    $icon = '<i class="fa-solid fa-rotate-left"></i>';
                                    $iconBg = '#e0e7ff'; $iconColor = '#4f46e5';
                                } elseif ($mode === 'promo' || strpos(strtolower($message), 'promo') !== false) {
                                    $badgeText = 'Promo Applied'; $badgeClass = 'badge-warning';
                                    $title = 'Promo Code';
                                    $icon = '<i class="fa-solid fa-gift"></i>';
                                    $iconBg = '#fef3c7'; $iconColor = '#d97706';
                                } elseif (in_array($mode, ['paytm', 'bharatpe', 'crypto']) || strpos(strtolower($message), 'deposit') !== false) {
                                    $badgeText = 'Added'; $badgeClass = 'badge-success';
                                    if ($mode === 'paytm' || strpos(strtolower($message), 'paytm') !== false) {
                                        $title = 'Paytm / UPI Deposit';
                                        $icon = '<i class="fa-brands fa-google-pay"></i>';
                                        $iconBg = '#dbeafe'; $iconColor = '#2563eb';
                                    } elseif ($mode === 'bharatpe' || strpos(strtolower($message), 'bharatpe') !== false) {
                                        $title = 'BharatPe Deposit';
                                        $icon = '<i class="fa-solid fa-qrcode"></i>';
                                        $iconBg = '#dcfce7'; $iconColor = '#16a34a';
                                    } elseif ($mode === 'crypto' || strpos(strtolower($message), 'crypto') !== false || strpos(strtolower($message), 'trx') !== false) {
                                        $title = 'Crypto Deposit';
                                        $icon = '<i class="fa-brands fa-bitcoin"></i>';
                                        $iconBg = '#fef3c7'; $iconColor = '#f59e0b';
                                    } else {
                                        $title = 'Wallet Deposit';
                                        $icon = '<i class="fa-solid fa-building-columns"></i>';
                                        $iconBg = '#dcfce7'; $iconColor = '#16a34a';
                                    }
                                } elseif (strpos(strtolower($message), 'transfer') !== false) {
                                    $badgeText = 'Received'; $badgeClass = 'badge-success';
                                    $title = 'Fund Transfer';
                                    $icon = '<i class="fa-solid fa-hand-holding-dollar"></i>';
                                    $iconBg = '#dcfce7'; $iconColor = '#16a34a';
                                } else {
                                    $badgeText = 'Credited'; $badgeClass = 'badge-success';
                                    $title = 'Wallet Credited';
                                    $icon = '<i class="fa-solid fa-plus"></i>';
                                    $iconBg = '#dcfce7'; $iconColor = '#16a34a';
                                }
                            }
                        ?>
                        <tr class="txn-row-item">
                            <td class="txn-td-info">
                                    <div class="txn-icon" style="background: <?php echo $iconBg; ?>; color: <?php echo $iconColor; ?>;">
                                        <?php echo $icon; ?>
                                    </div>
                                    <div class="txn-details">
                                        <div class="txn-title"><?php echo $title; ?></div>
                                        <div style="font-size:12px; color:var(--text-muted); margin-bottom:3px;"><?php echo $message; ?></div>
                                        <?php if(!empty($ref_id)): ?>
                                            <div class="txn-ref">ID/UTR: <?php echo htmlspecialchars($ref_id); ?></div>
                                        <?php else: ?>
                                            <div class="txn-ref">TXN: #<?php echo $txn['id']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="txn-td-amount">
                                <?php if($type == 'credit'): ?>
                                    <span class="txn-amount credit">+ <?php echo $currency.number_format($txn['amount'], 2); ?></span>
                                <?php else: ?>
                                    <span class="txn-amount debit">- <?php echo $currency.number_format($txn['amount'], 2); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="txn-td-status">
                                <span class="txn-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                            </td>
                            <td class="txn-td-date">
                                <div class="txn-date"><?php echo date('d M Y, h:i A', strtotime($txn['created_time'] ?? $txn['created_at'] ?? 'now')); ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
   <div id="txn-pagination" style="display:flex; justify-content:center; gap:10px; margin-top:20px;">
            <!-- Buttons will be generated by JS -->
        </div>

    </div> <!-- рдпрд╣ tab-history рд╡рд╛рд▓рд╛ div рд╣реИ -->
</div> <!-- рдпрд╣ tab-history рдХреА section close рд╣реБрдЖ -->

    <!-- ============================================================
    TAB: ORDERS (UOTP STYLE)
    ============================================================ -->
    <div id="tab-orders" class="content-section <?php echo $activeTab == 'orders' ? 'active' : ''; ?>">
        
        <div style="display:flex; align-items:center; gap: 15px; margin-bottom: 20px; flex-wrap:wrap;">
            <i class="fa-solid fa-filter" style="color:var(--text-muted);"></i> <span style="font-size:14px; font-weight:600; color:var(--text-muted);">Filter</span>
            <div class="uotp-filter-container">
                <button class="uotp-filter-btn active" onclick="filterOrders('all', this)">All</button>
                <button class="uotp-filter-btn" onclick="filterOrders('RECEIVED', this)">Success</button>
                <button class="uotp-filter-btn" onclick="filterOrders('CANCELLED', this)">Refunded</button>
                <button class="uotp-filter-btn" onclick="filterOrders('WAITING', this)">Active</button>
            </div>
        </div>

        <div class="uotp-table-wrapper">
            <table class="uotp-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Number</th>
                        <th>Country</th>
                        <th>Last Message</th>
                        <th>Price</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($userOrders)): ?>
                        <tr><td colspan='6' style='text-align:center; padding: 40px; color:var(--text-muted);'>No orders found.</td></tr>
                    <?php else: ?>
                        <?php foreach($userOrders as $ord): 
                            $rawDate = $ord['created_time'] ?? $ord['created_at'] ?? $ord['createdAt'] ?? '';
                            $orderTimestamp = !empty($rawDate) ? strtotime($rawDate) : 0;
                            $date = $orderTimestamp ? date('d/m/Y', $orderTimestamp) : date('d/m/Y');
                            
                            $actualStatus = strtoupper(trim($ord['status']));
                            
                            if (!in_array($actualStatus, ['RECEIVED', 'SUCCESS', 'CANCELLED', 'EXPIRED', 'REFUNDED'])) {
                                if ($orderTimestamp == 0) {
                                    $actualStatus = 'EXPIRED';
                                } else {
                                    $elapsedMinutes = (time() - $orderTimestamp) / 60;
                                    if ($elapsedMinutes > 20 || date('Y-m-d', $orderTimestamp) !== date('Y-m-d')) {
                                        $actualStatus = 'EXPIRED';
                                    } else {
                                        $actualStatus = 'WAITING';
                                    }
                                }
                            }
                            
                            $statusClass = 'active'; $statusText = 'Active'; $icon = '<i class="fa-solid fa-spinner fa-spin"></i>';
                            $isPollingNeeded = false;
                            
                            if (in_array($actualStatus, ['RECEIVED', 'SUCCESS'])) { 
                                $statusClass = 'success'; $statusText = 'Success'; $icon = '<i class="fa-regular fa-circle-check"></i>'; 
                                $actualStatus = 'RECEIVED';
                            }
                            elseif (in_array($actualStatus, ['CANCELLED', 'EXPIRED', 'REFUNDED'])) { 
                                $statusClass = 'cancelled'; $statusText = 'Cancelled'; $icon = '<i class="fa-solid fa-ban"></i>'; 
                                $actualStatus = 'CANCELLED';
                            } else {
                                $isPollingNeeded = true;
                                $actualStatus = 'WAITING';
                            }
                            
                            $num = preg_replace('/[^0-9]/', '', $ord['number'] ?? '');
                            if (strpos($num, '91') === 0) { $cName = 'India'; $cEmoji = '🇮🇳'; }
                            elseif (strpos($num, '92') === 0) { $cName = 'Pakistan'; $cEmoji = '🇵🇰'; }
                            elseif (strpos($num, '55') === 0) { $cName = 'Brazil'; $cEmoji = '🇧🇷'; }
                            elseif (strpos($num, '1') === 0) { $cName = 'USA/Canada'; $cEmoji = '🇺🇸'; }
                            elseif (strpos($num, '44') === 0) { $cName = 'UK'; $cEmoji = '🇬🇧'; }
                            elseif (strpos($num, '62') === 0) { $cName = 'Indonesia'; $cEmoji = '🇮🇩'; }
                            elseif (strpos($num, '7') === 0) { $cName = 'Russia'; $cEmoji = '🇷🇺'; }
                            elseif (strpos($num, '880') === 0) { $cName = 'Bangladesh'; $cEmoji = '🇧🇩'; }
                            elseif (strpos($num, '84') === 0) { $cName = 'Vietnam'; $cEmoji = '🇻🇳'; }
                            elseif (strpos($num, '63') === 0) { $cName = 'Philippines'; $cEmoji = '🇵🇭'; }
                            else {
                                $cName = $ord['country_name'] ?? 'Unknown';
                                if (empty($cName) || is_numeric($cName) || $cName == '??') $cName = 'Unknown';
                                $cEmoji = $ord['country_emoji'] ?? '🌍';
                                if (strpos($cEmoji, '?') !== false) $cEmoji = '🌍';
                            }
                        ?>
                        
                        <tr class="order-row-item <?php echo $isPollingNeeded ? 'uotp-live-row' : ''; ?>" 
                            data-status="<?php echo $actualStatus; ?>" 
                            data-order-id="<?php echo $ord['id']; ?>">
                            
                            <td>
                                <div class="uotp-service-cell">
                                    <div class="uotp-service-icon">
                                        <?php if(!empty($ord['service_logo'])): ?>
                                            <img src="<?php echo htmlspecialchars($ord['service_logo']); ?>" onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <i class="fa-regular fa-square-check"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="uotp-service-name"><?php echo htmlspecialchars($ord['service_name']); ?></div>
                                        <div class="uotp-service-date"><i class="fa-regular fa-clock"></i> <?php echo $date; ?></div>
                                    </div>
                                </div>
                            </td>
                            
                            <td style="font-weight:700; font-size: 15px; color: var(--text-main);">
                                <?php echo htmlspecialchars($ord['number']); ?>
                                <i class="fa-regular fa-copy uotp-copy-icon" style="margin-left:8px; color:var(--text-muted);" onclick="copyToClipboard('<?php echo htmlspecialchars($ord['number']); ?>', 'Number')"></i>
                            </td>
                            
                            <td style="color:var(--text-muted); font-size:13px; font-weight:500;">
                                <?php echo htmlspecialchars($cEmoji) . ' ' . htmlspecialchars($cName); ?>
                            </td>
                            
                            <td id="uotp-msg-<?php echo $ord['id']; ?>">
                                <?php if($actualStatus === 'RECEIVED'): ?>
                                    <div class="uotp-otp-pill">
                                        <span>OTP</span> <?php echo htmlspecialchars($ord['sms_text'] ?? 'Received'); ?>
                                        <i class="fa-regular fa-copy uotp-copy-icon" onclick="copyToClipboard('<?php echo htmlspecialchars($ord['sms_text'] ?? 'Received'); ?>', 'OTP')"></i>
                                    </div>
                                <?php elseif($actualStatus === 'CANCELLED'): ?>
                                    <span style="color:var(--text-muted); font-size:13px; font-style:italic;">No SMS</span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:13px;">Waiting for SMS...</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="font-weight:700;"><?php echo $currency; ?><?php echo number_format($ord['price'], 2); ?></td>
                            
                            <td id="uotp-status-<?php echo $ord['id']; ?>">
                                <div class="uotp-status <?php echo $statusClass; ?>">
                                    <?php echo $icon; ?> <?php echo $statusText; ?>
                                </div>
                            </td>
                            
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
            setInterval(() => {
                document.querySelectorAll('.uotp-live-row').forEach(row => {
                    const orderId = row.dataset.orderId;
                    const fd = new FormData();
                    fd.append('action', 'check_sms');
                    fd.append('order_id', orderId);
                    
                    fetch('ajax.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            if (data.status === 'RECEIVED' || data.status === 'SUCCESS') {
                                row.classList.remove('uotp-live-row');
                                row.dataset.status = 'RECEIVED';
                                
                                const msgCell = document.getElementById('uotp-msg-' + orderId);
                                if(msgCell) {
                                    msgCell.innerHTML = `
                                        <div class="uotp-otp-pill">
                                            <span>OTP</span> ${data.sms}
                                            <i class="fa-regular fa-copy uotp-copy-icon" onclick="copyToClipboard('${data.sms}', 'OTP')"></i>
                                        </div>`;
                                }
                                
                                const statusCell = document.getElementById('uotp-status-' + orderId);
                                if(statusCell) {
                                    statusCell.innerHTML = `
                                        <div class="uotp-status success">
                                            <i class="fa-regular fa-circle-check"></i> Success
                                        </div>`;
                                }
                            } 
                            else if (data.status === 'CANCELLED' || data.status === 'EXPIRED') {
                                row.classList.remove('uotp-live-row');
                                row.dataset.status = 'CANCELLED';
                                
                                const msgCell = document.getElementById('uotp-msg-' + orderId);
                                if(msgCell) {
                                    msgCell.innerHTML = '<span style="color:var(--text-muted); font-size:13px; font-style:italic;">No SMS</span>';
                                }
                                
                                const statusCell = document.getElementById('uotp-status-' + orderId);
                                if(statusCell) {
                                    statusCell.innerHTML = `
                                        <div class="uotp-status cancelled">
                                            <i class="fa-solid fa-ban"></i> Cancelled
                                        </div>`;
                                }
                            }
                        }
                    }).catch(err => console.log('UOTP Polling Error:', err));
                });
            }, 5000);
        </script>
    </div>

<!-- ============================================================
    TAB: SUPPORT (100% MATCHED OTPX STYLE & RESPONSIVE)
    ============================================================ -->
    <div id="tab-support" class="content-section <?php echo $activeTab == 'support' ? 'active' : ''; ?>">
        <style>
            .sup-wrapper { max-width: 1200px; margin: 0 auto; width: 100%; font-family: 'Inter', sans-serif; }
            .sup-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;}
            .sup-header h1 { margin: 0; font-size: 26px; font-weight: 800; color: var(--text-main); }
            .sup-header p { margin: 5px 0 0 0; color: var(--text-muted); font-size: 14px; font-weight: 500; }
            .sup-search { padding: 12px 20px; border: 1px solid var(--border-color); border-radius: 8px; width: 350px; max-width: 100%; outline: none; background: var(--bg-card); color: var(--text-main); font-size: 14px; transition: 0.2s;}
            .sup-search:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
            
            /* Top Contact Cards */
            .sup-cards-wrapper { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px; }
            .sup-contact-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px; cursor: pointer; transition: 0.3s; text-decoration: none; }
            .sup-contact-card:hover { border-color: var(--accent); transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.04); }
            .sup-contact-icon { width: 55px; height: 55px; border-radius: 14px; display: flex; justify-content: center; align-items: center; font-size: 26px; flex-shrink: 0; color: white;}
            .sup-info-title { margin: 0 0 4px 0; font-size: 16px; color: var(--text-main); font-weight: 800; }
            .sup-info-sub { margin: 0 0 4px 0; font-size: 12px; color: var(--text-muted); font-weight: 500; }
            .sup-info-link { color: var(--accent); font-weight: 600; font-size: 13px; }
            
            /* FAQ Layout */
            .sup-faq-layout { display: flex; gap: 30px; align-items: flex-start; }
            .sup-sidebar { width: 260px; flex-shrink: 0; position: sticky; top: 90px; }
            .sup-cat-btn { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; padding: 14px 18px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 10px; cursor: pointer; font-weight: 600; font-size: 14px; color: var(--text-muted); transition: 0.2s;}
            .sup-cat-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); box-shadow: 0 4px 10px rgba(37,99,235,0.2); }
            .sup-cat-btn:hover:not(.active) { border-color: var(--accent); color: var(--accent); }
            
            /* Accordion */
            .sup-faq-content { flex: 1; min-width: 0; }
            .sup-accordion { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 12px; overflow: hidden; transition: 0.3s; }
            .sup-accordion:hover { border-color: #cbd5e1; }
            .sup-acc-header { padding: 18px 20px; font-weight: 600; font-size: 15px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; color: var(--text-main); user-select: none; }
            .sup-acc-header i.fa-chevron-down { color: var(--text-muted); transition: transform 0.3s; }
            .sup-acc-body { padding: 0 20px 18px 45px; display: none; color: var(--text-muted); font-size: 14px; line-height: 1.7; }
            
            .sup-accordion.active { border-color: var(--accent); }
            .sup-accordion.active .sup-acc-body { display: block; }
            .sup-accordion.active .sup-acc-header { color: var(--accent); }
            .sup-accordion.active .sup-acc-header i.fa-chevron-down { transform: rotate(180deg); color: var(--accent); }

            /* Missing Footer */
            .sup-footer { margin-top: 50px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 40px 20px; text-align: center; }
            .sup-footer h3 { font-size: 20px; font-weight: 800; color: var(--text-main); margin-bottom: 8px; }
            .sup-footer p { font-size: 14px; color: var(--text-muted); margin-bottom: 25px; }
            .sup-footer-btns { display: flex; justify-content: center; gap: 15px; flex-wrap: wrap; }
            .sup-f-btn { padding: 10px 24px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
            .sup-f-btn.outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-main); }
            .sup-f-btn.outline:hover { background: #f8fafc; border-color: #cbd5e1; }
            .sup-f-btn.blue { background: #0088cc; color: white; border: 1px solid #0088cc; }
            .sup-f-btn.blue:hover { background: #0077b5; }

            /* Responsive */
            @media(max-width: 992px) { 
                .sup-faq-layout { flex-direction: column; } 
                .sup-sidebar { width: 100%; position: static; display: flex; overflow-x: auto; gap: 10px; padding-bottom: 5px;}
                .sup-cat-btn { width: auto; white-space: nowrap; margin-bottom: 0; padding: 10px 20px;}
            }
        </style>

        <div class="sup-wrapper">
            <div class="sup-header">
                <div>
                    <h1>Help & Support</h1>
                    <p>Find answers or contact our team</p>
                </div>
                <input type="text" class="sup-search" id="searchFaq" placeholder="Search FAQs..." onkeyup="filterFaqs()">
            </div>

            <!-- Dynamic Support Links -->
            <div class="sup-cards-wrapper">
                <?php foreach($supportLinks as $link): 
                    $bg = '#2563eb'; // Default Blue
                    if(strpos(strtolower($link['icon_class']), 'whatsapp') !== false) $bg = '#25D366';
                    elseif(strpos(strtolower($link['icon_class']), 'telegram') !== false) $bg = '#0088cc';
                    elseif(strpos(strtolower($link['icon_class']), 'envelope') !== false) $bg = '#ea4335';
                ?>
                <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" class="sup-contact-card">
                    <div class="sup-contact-icon" style="background: <?php echo $bg; ?>;"><i class="<?php echo $link['icon_class']; ?>"></i></div>
                    <div>
                        <h4 class="sup-info-title"><?php echo htmlspecialchars($link['title']); ?></h4>
                        <p class="sup-info-sub">Quick help from our team</p>
                        <span class="sup-info-link"><?php echo htmlspecialchars($link['subtitle']); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- FAQ Section -->
            <div class="sup-faq-layout">
                <div class="sup-sidebar">
                    <button class="sup-cat-btn active" onclick="filterCategory('All', this)"><i class="fa-solid fa-layer-group"></i> All</button>
                    <?php foreach($faqCategories as $cat): ?>
                        <button class="sup-cat-btn" onclick="filterCategory('<?php echo $cat; ?>', this)">
                            <i class="<?php echo ($cat=='Account')?'fa-solid fa-user-gear':(($cat=='Payments')?'fa-solid fa-credit-card':'fa-solid fa-folder'); ?>"></i> <?php echo $cat; ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="sup-faq-content" id="faqContainer">
                    <?php if(empty($faqs)): ?>
                        <div style="padding:40px 20px; text-align:center; color:var(--text-muted); border:1px dashed var(--border-color); border-radius:12px; background:var(--bg-card);">No FAQs available at the moment.</div>
                    <?php endif; ?>
                    
                    <?php foreach($faqs as $faq): ?>
                    <div class="sup-accordion faq-row" data-category="<?php echo htmlspecialchars($faq['category']); ?>">
                        <div class="sup-acc-header" onclick="this.parentElement.classList.toggle('active')">
                            <span class="faq-q"><i class="fa-regular fa-circle-question" style="color:var(--text-muted); margin-right:8px;"></i> <?php echo htmlspecialchars($faq['question']); ?></span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="sup-acc-body">
                            <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Missing Footer Added -->
            <div class="sup-footer">
                <h3>Still have questions?</h3>
                <p>Our support team is ready to help</p>
                <div class="sup-footer-btns">
                    <?php foreach($supportLinks as $link): 
                        if(strpos(strtolower($link['icon_class']), 'telegram') !== false) {
                            echo '<a href="'.htmlspecialchars($link['url']).'" target="_blank" class="sup-f-btn blue"><i class="'.$link['icon_class'].'"></i> Telegram</a>';
                        } else {
                            echo '<a href="'.htmlspecialchars($link['url']).'" target="_blank" class="sup-f-btn outline"><i class="'.$link['icon_class'].'"></i> '.htmlspecialchars($link['title']).'</a>';
                        }
                    endforeach; ?>
                </div>
            </div>
        </div>

        <script>
            function filterCategory(cat, btn) {
                document.querySelectorAll('.sup-cat-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.querySelectorAll('.faq-row').forEach(row => {
                    if(cat === 'All' || row.getAttribute('data-category') === cat) {
                        row.style.display = 'block';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            function filterFaqs() {
                let input = document.getElementById('searchFaq').value.toLowerCase();
                document.querySelectorAll('.faq-row').forEach(row => {
                    let qText = row.querySelector('.faq-q').innerText.toLowerCase();
                    row.style.display = qText.includes(input) ? 'block' : 'none';
                });
            }
        </script>
    </div>

    <!-- ============================================================
    TAB: ACCOUNT / PROFILE
    ============================================================ -->
    <div id="tab-profile" class="content-section <?php echo $activeTab == 'profile' ? 'active' : ''; ?>">
        
        <div class="acc-header-area">
            <div>
                <h1>Account</h1>
                <p>Manage your profile, security, and API access.</p>
            </div>
            <div class="status-badge"><i class="fa-solid fa-circle-check"></i> Active</div>
        </div>

        <!-- Top User Info Card -->
        <div class="acc-top-card">
            <div class="acc-user-row">
                <div class="acc-user-info">
                    <div class="acc-avatar-lg"><?php echo $userInitial; ?></div>
                    <div class="acc-user-details">
                        <h2><?php echo htmlspecialchars($userName); ?> <i class="fa-solid fa-circle-check"></i></h2>
                        <p><i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($userEmail); ?></p>
                    </div>
                </div>
                <div class="status-badge" style="background:#f1f5f9; color:#475569; border-color:#e2e8f0; border-radius:6px;"><i class="fa-solid fa-crown"></i> Normal</div>
            </div>

            <div class="acc-stats-row">
            <div class="acc-stats-row">
                <div class="acc-stat-box highlight">
                    <div class="title"><i class="fa-solid fa-wallet"></i> Balance</div>
                    <div class="value"><?php echo $currency.$userBalance; ?></div>
                </div>
               <?php 
// рд╕рд┐рд░реНрдл 'all' рд╕рд░реНрд╡рд┐рд╕ рдХрд╛ рдкрд╣рд▓рд╛ % рдбрд┐рд╕реНрдХрд╛рдЙрдВрдЯ рдирд┐рдХрд╛рд▓рдиреЗ рдХрд╛ рд▓реЙрдЬрд┐рдХ
$realGlobalDiscount = '0.00';
if(!empty($specialOffers)){
    foreach($specialOffers as $off){
        if($off['apply_to'] == 'all' && $off['type'] == 'percentage'){
            $realGlobalDiscount = number_format($off['value'], 2);
            break; // рдЬреИрд╕реЗ рд╣реА рдкрд╣рд▓рд╛ рдорд┐рд▓реЗрдЧрд╛, рдпрд╣ рдЖрдЧреЗ рдЦреЛрдЬрдирд╛ рдмрдВрдж рдХрд░ рджреЗрдЧрд╛
        }
    }
}
?>
<div class="acc-stat-box">
    <div class="title"><i class="fa-solid fa-percent"></i> Discount</div>
    <div class="value"><?php echo $realGlobalDiscount; ?>%</div>
</div>
                <div class="acc-stat-box">
                    <div class="title"><i class="fa-solid fa-arrow-trend-up"></i> Recharged</div>
                    <div class="value"><?php echo $currency.$totalRecharged; ?></div>
                </div>
                <div class="acc-stat-box">
                    <div class="title"><i class="fa-solid fa-arrow-trend-down"></i> Spent</div>
                    <div class="value"><?php echo $currency.$totalSpent; ?></div>
                </div>
            </div>
        </div>

        <!-- Internal Tabs -->
        <div class="acc-tabs">
            <button class="acc-tab-btn active" onclick="switchProfileTab('overview')" id="btn-ptab-overview"><i class="fa-solid fa-user"></i> Overview</button>
            <button class="acc-tab-btn" onclick="switchProfileTab('security')" id="btn-ptab-security"><i class="fa-solid fa-shield-halved"></i> Security</button>
            <button class="acc-tab-btn" onclick="switchProfileTab('sessions')" id="btn-ptab-sessions"><i class="fa-solid fa-desktop"></i> Sessions</button>
            <button class="acc-tab-btn" onclick="switchProfileTab('api')" id="btn-ptab-api"><i class="fa-solid fa-key"></i> API Keys</button>
        </div>

        <!-- TAB CONTENT: OVERVIEW -->
        <div id="ptab-overview" class="acc-tab-content active">
            <div class="acc-grid-layout">
                <div class="acc-box">
                    <div class="acc-box-title">Profile Details</div>
                    <form onsubmit="updateProfile(event)">
                        <div class="acc-form-grid">
                            <div class="acc-input-group">
                                <label>Full Name</label>
                                <div class="acc-input-wrapper">
                                    <i class="fa-regular fa-user"></i>
                                    <input type="text" id="profile-name" value="<?php echo htmlspecialchars($userName); ?>">
                                </div>
                            </div>
                            <div class="acc-input-group">
                                <label>Email Address</label>
                                <div class="acc-input-wrapper">
                                    <i class="fa-regular fa-envelope"></i>
                                    <input type="email" id="profile-email" value="<?php echo htmlspecialchars($userEmail); ?>" readonly>
                                </div>
                            </div>
                            <div class="acc-input-group">
                                <label>Account Type</label>
                                <div class="acc-input-wrapper">
                                    <i class="fa-solid fa-crown" style="color:var(--accent);"></i>
                                    <input type="text" value="Normal User" readonly>
                                </div>
                            </div>
<div class="acc-input-group">
    <label>Discount Rate</label>
    <div class="acc-input-wrapper">
        <i class="fa-solid fa-percent" style="color:var(--accent);"></i>
        <input type="text" value="<?php echo $realGlobalDiscount; ?>%" style="color:var(--accent); font-weight:800;" readonly>
    </div>
</div>
                        </div>
                        <div class="acc-input-group" style="margin-top:10px;">
                            <label>Telegram Username</label>
                            <div class="acc-input-wrapper">
                                <i class="fa-brands fa-telegram" style="color:#3b82f6;"></i>
                                <input type="text" placeholder="@username" value="">
                                <span style="font-size:12px; font-weight:700; color:var(--text-main); cursor:pointer;"><i class="fa-solid fa-pen"></i> Edit</span>
                            </div>
                        </div>
                        <button type="submit" class="btn-buy" style="margin-top:10px;">Save Changes</button>
                    </form>
                </div>

                <div class="acc-box">
                    <div class="acc-bal-card">
                        <div class="bal-title"><i class="fa-solid fa-wallet"></i> Current Balance</div>
                        <div class="bal-amt"><?php echo $currency.$userBalance; ?></div>
                        <button onclick="switchTab('recharge')" class="btn-buy" style="width:100%; justify-content:center; padding:14px;"><i class="fa-solid fa-plus"></i> Add Funds</button>
                    </div>
                </div>
            </div>

            <div class="acc-mini-stats">
                <div class="acc-mini-card">
                    <div class="top"><i class="fa-solid fa-arrow-trend-up" style="color:#3b82f6;"></i> Total Recharged</div>
                    <div class="amt"><?php echo $currency.$totalRecharged; ?></div>
                    <div class="btm" style="color:#3b82f6;"><i class="fa-solid fa-chart-line"></i> Lifetime deposits</div>
                </div>
                <div class="acc-mini-card">
                    <div class="top"><i class="fa-solid fa-arrow-trend-down" style="color:#f59e0b;"></i> Total Spent</div>
                    <div class="amt"><?php echo $currency.$totalSpent; ?></div>
                    <div class="btm" style="color:#f59e0b;"><i class="fa-solid fa-comment-sms"></i> On SMS verifications</div>
                </div>
                <div class="acc-mini-card promo-card-fix">
                    <div class="top"><i class="fa-solid fa-gift" style="color:#10b981;"></i> Promo Codes Redeemed</div>
                    <div class="amt"><?php echo $currency.$totalPromoAmt; ?></div>
                    <div class="btm" style="color:#10b981;"><i class="fa-solid fa-ticket"></i> <?php echo $totalPromoCount; ?> Coupons Redeemed</div>
                </div>
                </div>
            </div>

        <!-- TAB CONTENT: SECURITY -->
        <div id="ptab-security" class="acc-tab-content">
            <div class="acc-box" style="max-width:600px;">
                <div class="acc-box-title">Change Password</div>
                <form id="change-password-form">
                    <div class="acc-input-group">
                        <label>Current Password</label>
                        <div class="acc-input-wrapper">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="cp-current" placeholder="Enter current password">
                        </div>
                    </div>
                    <div class="acc-input-group">
                        <label>New Password</label>
                        <div class="acc-input-wrapper">
                            <i class="fa-solid fa-key"></i>
                            <input type="password" id="cp-new" placeholder="Enter new password">
                        </div>
                    </div>
                    <div class="acc-input-group">
                        <label>Confirm New Password</label>
                        <div class="acc-input-wrapper">
                            <i class="fa-solid fa-check-double"></i>
                            <input type="password" id="cp-confirm" placeholder="Confirm new password">
                        </div>
                    </div>
                    <button type="button" onclick="changePassword()" class="btn-buy" style="margin-top:10px;">Update Password</button>
                </form>
            </div>
        </div>

        <!-- TAB CONTENT: SESSIONS -->
        <div id="ptab-sessions" class="acc-tab-content">
            <div class="acc-box">
                <div class="acc-box-title" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Active Sessions</span>
                    <?php if(!empty($activeSessions)): ?>
                        <button onclick="logoutAllSessions()" style="background:#ef4444; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:600; font-size:12px;"><i class="fa-solid fa-power-off"></i> Logout All Devices</button>
                    <?php endif; ?>
                </div>
                <div style="overflow-x:auto;">
                    <table class="uotp-table" style="width:100%;">
                        <thead><tr><th>Device / Browser</th><th>IP Address</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php if(empty($activeSessions)): ?>
                                <tr><td colspan="4">No active sessions found.</td></tr>
                            <?php else: ?>
                                <?php foreach($activeSessions as $sess): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700; color:var(--text-main);"><i class="fa-solid fa-desktop" style="color:var(--text-muted); margin-right:8px;"></i> <?php echo htmlspecialchars($sess['device'] ?? 'Unknown Device'); ?></div>
                                        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;"><?php echo htmlspecialchars($sess['browser'] ?? 'Unknown Browser'); ?></div>
                                    </td>
                                    <td><span class="txn-ref"><?php echo htmlspecialchars($sess['ip']); ?></span></td>
                                    <td><span class="uotp-status success">Active</span></td>
                                    <td>
                                        <button onclick="logoutSingleSession(<?php echo $sess['id']; ?>)" style="background:transparent; border:1px solid #ef4444; color:#ef4444; padding:4px 10px; border-radius:6px; cursor:pointer; font-weight:600; font-size:12px;">Logout</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB CONTENT: API KEYS -->
        <div id="ptab-api" class="acc-tab-content">
            <div class="acc-box">
                <div class="acc-box-title">API Access Key</div>
                <p style="color:var(--text-muted); font-size:14px; margin-bottom:20px;">Use this key to authenticate with our REST API. Do not share this key with anyone.</p>
                <div class="acc-input-group">
                    <label>Your API Key</label>
                    <div class="acc-input-wrapper" style="background:#f1f5f9; padding: 5px 15px;">
                        <i class="fa-solid fa-code" style="color:var(--accent);"></i>
                        <input type="text" value="<?php echo htmlspecialchars($userApiKey); ?>" readonly id="my-api-key" style="font-family:monospace; color:var(--accent);">
                        
                        <button onclick="regenerateApiKey()" style="background:transparent; border:none; margin-right:15px; cursor:pointer; color:var(--text-muted);" title="Regenerate Key"><i class="fa-solid fa-rotate-right"></i></button>
                        
                        <button onclick="copyToClipboard(document.getElementById('my-api-key').value, 'API Key')" style="background:white; border:1px solid var(--border-color); padding:8px 12px; border-radius:6px; cursor:pointer; font-weight:700; color:var(--text-main);"><i class="fa-regular fa-copy"></i> Copy</button>
                    </div>
                </div>
                <button class="btn-buy" style="background:var(--bg-body); color:var(--text-main); border:1px solid var(--border-color);" onclick="window.open('../api-docs.php', '_blank')"><i class="fa-solid fa-book"></i> Read API Documentation</button>
            </div>
        </div>

<!-- ============================================================
MODALS
============================================================ -->
<div class="modal-overlay" id="payment-modal" onclick="if(event.target===this) closeModal('payment-modal')">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="font-weight:700;" id="payment-modal-title">Payment</h3>
            <button onclick="closeModal('payment-modal')" style="background:transparent; border:none; font-size:20px; cursor:pointer; color:var(--text-muted);">✖</button>
        </div>
        
        <div style="background:var(--hover-bg); padding:20px; border-radius:12px; text-align:center; margin-bottom:20px;">
            <p style="font-size:13px; color:var(--text-muted);">Pay to this Address/UPI</p>
            <div style="font-size:18px; font-weight:700; color:var(--accent); margin:10px 0; word-break:break-all;" id="payment-upi"></div>
            <div id="payment-qr-container"></div>
            <button onclick="copyPaymentUPI()" class="btn-buy" style="margin:10px auto 0; padding:6px 12px; font-size:12px;"><i class="fa-regular fa-copy"></i> Copy</button>
        </div>
        
        <div style="margin-bottom:15px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Transaction ID / UTR</label>
            <input type="text" id="payment-utr" placeholder="Enter UTR..." class="search-bar" style="margin:0; background:white; width:100%; color:var(--text-main);">
        </div>
        
        <button onclick="submitPayment()" class="btn-buy" style="width:100%; justify-content:center;">Submit Verification</button>
    </div>
</div>

<!-- Success Popup -->
<div class="modal-overlay" id="paytm-success-modal" style="display:none; pointer-events:none; align-items:center; justify-content:center; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
    <div class="modal-content" style="background:var(--bg-card); width:100%; max-width:400px; border-radius:16px; text-align:center; padding:30px; box-sizing:border-box; pointer-events:auto;">
        <div style="width:60px; height:60px; background:#dcfce7; color:#16a34a; border-radius:50%; display:flex; justify-content:center; align-items:center; font-size:30px; margin:0 auto 15px;">
            <i class="fa-solid fa-check"></i>
        </div>
        <h3 style="color:#16a34a; font-size:22px; font-weight:800; margin-bottom:10px;">Thank You Very Much!</h3>
        <p style="color:var(--text-main); font-size:15px; font-weight:600; margin-bottom:5px;">Your payment of ₹<span id="popup-amt"></span> has been successfully credited.</p>
        <p style="color:var(--text-muted); font-size:13px; margin-bottom:20px;">Wallet balance updated instantly.</p>
        <button onclick="location.reload()" class="rc-btn-primary" style="background:#16a34a; padding:12px; width:100%; border:none; border-radius:8px; color:white; font-weight:700; cursor:pointer;">Continue</button>
    </div>
</div>


<!-- ============================================================
    BROADCAST & NOTIFICATION MODALS (WITH COLORS & BUTTONS)
============================================================ -->
<?php
// Fetch Active Broadcasts (Fixing the NULL date issue)
try {
    $userTarget = ($user['role'] === 'admin') ? "('all', 'admins', 'active')" : "('all', 'active')";
    
    $stmtBc = $pdo->query("
        SELECT * FROM Broadcasts 
        WHERE status = 1 
        AND target IN $userTarget 
        AND (start_date IS NULL OR start_date = '0000-00-00 00:00:00' OR NOW() >= start_date)
        AND (end_date IS NULL OR end_date = '0000-00-00 00:00:00' OR NOW() <= end_date)
        ORDER BY id ASC
    ");
    $activeBroadcasts = $stmtBc->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activeBroadcasts = [];
}
?>

<style>
   /* Broadcast Popup Image */
.bc-modal-img { 
    width: 100%; 
    height: auto; 
    max-height: 720px; 
    object-fit: cover; 
    border-radius: 12px; 
    margin-bottom: 20px; 
    display: block;
}
.bc-modal-title { 
    font-size: 22px; 
    font-weight: 800; 
    color: var(--text-main); 
    margin-bottom: 5px; 
}
.bc-modal-text { 
    font-size: 15px; 
    color: var(--text-muted); 
    line-height: 1.6; 
    margin-bottom: 10px; 
    white-space: pre-wrap;
}
    
    /* Type Colors */
    .bc-type-info { border-top: 5px solid #3b82f6; }
    .bc-type-success { border-top: 5px solid #10b981; }
    .bc-type-warning { border-top: 5px solid #f59e0b; }
    .bc-type-error { border-top: 5px solid #ef4444; }

    /* Notification Center Modal */
    .notif-item { display: flex; gap: 15px; padding: 15px; border-bottom: 1px solid var(--border-color); align-items: flex-start; }
    .notif-item:last-child { border-bottom: none; }
    .notif-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 18px; flex-shrink: 0; color: white; }
    .notif-item img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; flex-shrink: 0;}
    .notif-content h4 { margin: 0 0 5px 0; font-size: 15px; color: var(--text-main); }
    .notif-content p { margin: 0; font-size: 13px; color: var(--text-muted); }
    .notif-time { font-size: 11px; color: #94a3b8; margin-top: 8px; display: block; }
</style>


<!-- =========================================================
JAVASCRIPT
============================================================ -->
<script>
    // ============================================================
    // 1. UI & UTILITY FUNCTIONS
    // ============================================================
    function switchTab(tabId) {
        const url = new URL(window.location);
        url.searchParams.set('tab', tabId);
        window.history.pushState({}, '', url);

        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        document.querySelectorAll(`.nav-item[onclick*="switchTab('${tabId}')"]`).forEach(el => el.classList.add('active'));

        document.querySelectorAll('.content-section').forEach(el => el.classList.remove('active'));
        const target = document.getElementById('tab-' + tabId);
        if(target) target.classList.add('active');
    }

    function toggleSidebar() {
        document.getElementById('mobile-sidebar').classList.toggle('open');
        const overlay = document.getElementById('sidebar-overlay');
        overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
    }

    function toggleTheme() {
        document.body.classList.toggle('dark-mode');
        const icon = document.querySelector('.theme-toggle i');
        icon.className = document.body.classList.contains('dark-mode') ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
    }
    if(localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        document.querySelector('.theme-toggle i').className = 'fa-solid fa-sun';
    }

    function copyToClipboard(text, type) {
        if(!text) return alert('Nothing to copy');
        navigator.clipboard.writeText(text).then(() => {
            alert(`✅ ${type} copied: ${text}`);
        }).catch(err => {
            prompt(`Copy ${type}:`, text);
        });
    }

    // ============================================================
    // 3. RECHARGE / PAYMENT SYSTEM
    // ============================================================
    let selectedAmount = null;
    let selectedPaymentMode = 'upi';

    function openRcView(viewId) {
        document.querySelectorAll('.rc-view').forEach(v => v.classList.remove('active'));
        const target = document.getElementById('rc-view-' + viewId);
        if(target) target.classList.add('active');
    }

    function resetPaytmView() {
        openRcView('main');
    }

    function applyMainPromo() {
        const code = document.getElementById('main-promo-code').value.trim();
        if (!code) return alert("Please enter a promo code.");
        
        const fd = new FormData();
        fd.append('action', 'redeem_promo');
        fd.append('code', code);
        
        fetch('ajax.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            alert(data.success ? '✅ ' + data.message : '❌ ' + data.message);
            if (data.success) setTimeout(() => location.reload(), 1500);
        }).catch(err => alert('Error: ' + err.message));
    }

    function submitBharatpeUTR(btn) {
        const amt = document.getElementById('bharatpe-amt').value.trim();
        const utr = document.getElementById('bharatpe-utr').value.trim();
        
        if (!amt || parseFloat(amt) <= 0) return alert("❌ Please enter the Amount you paid.");
        if (!utr || utr.length < 8) return alert("❌ Please enter a valid UTR/Reference Number.");
        
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';
        btn.disabled = true;

        const fd = new FormData();
        fd.append('action', 'process_recharge');
        fd.append('payment_mode', 'bharatpe');
        fd.append('utr', utr);
        fd.append('amount', amt);

        fetch('ajax.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            btn.innerHTML = origText;
            btn.disabled = false;
            if (data.success) {
                alert("✅ Wallet Credited Successfully!");
                window.location.href = `success.php?amount=${data.amount}`;
            } else {
                alert('❌ ' + data.message);
            }
        }).catch(err => {
            btn.innerHTML = origText;
            btn.disabled = false;
            alert('Error: ' + err.message);
        });
    }

    let paytmPollInterval;
    function generatePaytmAutoQR() {
        const minDep = parseFloat('<?php echo htmlspecialchars($sitePayments["min_deposit"] ?? "10"); ?>');
        const amtInput = document.getElementById('paytm-amt');
        const amt = amtInput ? parseFloat(amtInput.value) : 0;
        const upiId = '<?php echo htmlspecialchars($sitePayments["paytm_upi"] ?? ""); ?>';
        
        if (!amt || amt < minDep) {
            alert("❌ Minimum deposit is ₹" + minDep);
            return;
        }
        if (!upiId) {
            alert("❌ Paytm UPI ID is not configured in Admin Panel.");
            return;
        }

        const step1 = document.getElementById('paytm-step-1');
        const step2 = document.getElementById('paytm-step-2');
        if(step1) step1.style.display = 'none';
        if(step2) step2.style.display = 'block';
        
        const showAmtEl = document.getElementById('paytm-show-amt');
        if(showAmtEl) showAmtEl.textContent = amt;
        
        const orderId = 'TXN' + Math.floor(Math.random() * 10000000000);
        const upiString = `upi://pay?pa=${upiId}&pn=Wallet Recharge&am=${amt}&tr=${orderId}&cu=INR`;
        const qrImg = document.getElementById('paytm-auto-qr-img');
        if(qrImg) {
            qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(upiString)}`;
        }

        if(paytmPollInterval) clearInterval(paytmPollInterval);
        paytmPollInterval = setInterval(() => {
            const fd = new FormData();
            fd.append('action', 'check_paytm_payment'); 
            fd.append('amount', amt);
            fd.append('order_id', orderId);

            fetch('ajax.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.status === 'SUCCESS') {
                    clearInterval(paytmPollInterval);
                    window.location.href = `success.php?amount=${amt}`;
                }
            });
        }, 5000);
    }

    function submitCryptoTx(btn) {
        const amt = document.getElementById('crypto-amt').value;
        const utr = document.getElementById('crypto-utr').value;
        if(!amt || !utr) return alert("Fill all fields");

        const fd = new FormData();
        fd.append('action', 'process_recharge');
        fd.append('payment_mode', 'crypto');
        fd.append('amount', amt);
        fd.append('utr', utr);

        fetch('ajax.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.success) { alert('✅ ' + data.message); location.reload(); }
            else alert('❌ ' + data.message);
        });
    }

    function openPaymentModal(mode) {
        if (!selectedAmount || selectedAmount <= 0) return alert('Please enter a valid amount first');
        selectedPaymentMode = mode;
        const modal = document.getElementById('payment-modal');
        const upiSpan = document.getElementById('payment-upi');
        const qrContainer = document.getElementById('payment-qr-container');
        
        if (modal) {
            if (mode === 'upi') {
                const titleEl = document.getElementById('payment-modal-title');
                if (titleEl) titleEl.innerHTML = '<i class="fa-brands fa-google-pay"></i> Paytm / UPI Payment';
                if (upiSpan) upiSpan.textContent = '<?php echo htmlspecialchars($sitePayments['paytm_upi'] ?? ''); ?>';
                if (qrContainer) {
                    qrContainer.innerHTML = '<?php if(!empty($sitePayments['paytm_qr'])): ?><img src="<?php echo htmlspecialchars($sitePayments['paytm_qr']); ?>" style="max-width:200px; display:block; margin:auto; border-radius:8px;"><?php endif; ?>';
                }
            } else if (mode === 'trx') {
                const titleEl = document.getElementById('payment-modal-title');
                if (titleEl) titleEl.innerHTML = '<i class="fa-brands fa-bitcoin"></i> TRX / Crypto Payment';
                if (upiSpan) upiSpan.textContent = '<?php echo htmlspecialchars($sitePayments['trx_address'] ?? ''); ?>';
                if (qrContainer) qrContainer.innerHTML = '';
            }
            modal.classList.add('show');
        }
    }

    function submitPayment() {
        const utrEl = document.getElementById('payment-utr');
        const utr = utrEl ? utrEl.value.trim() : '';
        if (!utr) return alert('Please enter Transaction ID / UTR');
        
        const fd = new FormData();
        fd.append('action', 'process_recharge');
        fd.append('amount', selectedAmount);
        fd.append('payment_mode', selectedPaymentMode);
        fd.append('utr', utr);
        
        fetch('ajax.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                if (typeof closeModal === 'function') closeModal('payment-modal');
                setTimeout(() => location.reload(), 1000);
            } else { 
                alert('❌ ' + data.message); 
            }
        }).catch(err => alert('Error: ' + err.message));
    }

    function copyPaymentUPI() { 
        const upiEl = document.getElementById('payment-upi');
        if (upiEl) copyToClipboard(upiEl.textContent, 'Address/UPI'); 
    }

    // ============================================================
    // 4. PROFILE & MODALS
    // ============================================================
    function updateProfile(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('action', 'update_profile');
        fd.append('name', document.getElementById('profile-name').value);
        fd.append('email', document.getElementById('profile-email').value);

        fetch('ajax.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if(data.success) location.reload();
        });
    }

    function logout() {
        if(confirm('Logout?')) {
            const fd = new FormData(); fd.append('action', 'logout');
            fetch('ajax.php', { method: 'POST', body: fd }).then(() => window.location.href = '../index.php');
        }
    }

    function closeModal(id) { 
        const el = document.getElementById(id);
        if(el) el.classList.remove('show'); 
    }

    // ============================================================
    // 5. LIVE TIMER & ACTIVE ORDERS
    // ============================================================
    const TOTAL_ORDER_TIME = 1200;
    const CANCEL_ALLOW_TIME = 120;

    function initializeTimers() {
        const orderCards = document.querySelectorAll('.active-order-card');
        
        orderCards.forEach(card => {
            const orderId = card.dataset.orderId;
            const status = card.dataset.status;
            
            if(status === 'WAITING') {
                const createdTimeStr = card.dataset.created; 
                const createdTime = parseInt(createdTimeStr);
                const timerDisplay = document.getElementById('timer-text-' + orderId);
                const cancelBtn = document.getElementById('btn-cancel-' + orderId);
                
                const interval = setInterval(() => {
                    const now = Math.floor(Date.now() / 1000);
                    const elapsed = now - createdTime;
                    const remaining = TOTAL_ORDER_TIME - elapsed;
                    
                    if (remaining <= 0) {
                        clearInterval(interval);
                        if(timerDisplay) {
                            timerDisplay.innerHTML = '<i class="fa-solid fa-clock"></i> Expired';
                            timerDisplay.classList.add('expired');
                        }
                    } else {
                        const m = Math.floor(remaining / 60).toString().padStart(2, '0');
                        const s = (remaining % 60).toString().padStart(2, '0');
                        if(timerDisplay) timerDisplay.innerHTML = `<i class="fa-regular fa-clock"></i> ${m}:${s}`;
                        
                        if (elapsed >= CANCEL_ALLOW_TIME) {
                            if(cancelBtn) {
                                cancelBtn.disabled = false;
                                cancelBtn.innerHTML = '<i class="fa-solid fa-ban"></i> Cancel';
                            }
                        } else {
                            if(cancelBtn) {
                                const waitM = Math.floor((CANCEL_ALLOW_TIME - elapsed) / 60);
                                const waitS = ((CANCEL_ALLOW_TIME - elapsed) % 60).toString().padStart(2, '0');
                                cancelBtn.disabled = true;
                                cancelBtn.innerHTML = `Wait ${waitM}:${waitS} to Cancel`;
                            }
                        }
                    }

                    if (elapsed % 5 === 0 && remaining > 0) {
                        checkSingleOrder(orderId, interval);
                    }
                }, 1000);
            } else if (status === 'RECEIVED') {
                const timerDisplay = document.getElementById('timer-text-' + orderId);
                if (timerDisplay) {
                    timerDisplay.innerHTML = '<i class="fa-solid fa-check"></i> Completed';
                    timerDisplay.style.background = 'rgba(16, 185, 129, 0.1)';
                    timerDisplay.style.color = '#10b981';
                }
            }
        });
    }

    function checkSingleOrder(orderId, intervalRef) {
        const fd = new FormData();
        fd.append('action', 'check_sms');
        fd.append('order_id', orderId);
        
        fetch('ajax.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.status === 'RECEIVED') {
                if(intervalRef) clearInterval(intervalRef);
                
                const card = document.getElementById('order-' + orderId);
                if(card) card.dataset.status = 'RECEIVED';
                
                const timerDisplay = document.getElementById('timer-text-' + orderId);
                if (timerDisplay) {
                    timerDisplay.innerHTML = '<i class="fa-solid fa-check"></i> Completed';
                    timerDisplay.style.background = 'rgba(16, 185, 129, 0.1)';
                    timerDisplay.style.color = '#10b981';
                }

                const container = document.getElementById('otp-container-' + orderId);
                if(container) {
                    container.classList.add('success');
                    container.innerHTML = `
                        <div style="color: #10b981; font-size:24px;"><i class="fa-regular fa-circle-check"></i></div>
                        <div style="flex:1;">
                            <div class="otpx-status-text">OTP Received Successfully</div>
                            <div class="otpx-status-subtext">You can now use this code</div>
                        </div>
                        <div class="otpx-received-code">
                            ${data.sms}
                            <i class="fa-regular fa-copy" style="font-size:18px; color:var(--success); cursor:pointer; margin-left:10px;" onclick="copyToClipboard('${data.sms}', 'OTP')"></i>
                        </div>
                    `;
                }
                
                const cancelBtn = document.getElementById('btn-cancel-' + orderId);
                if(cancelBtn) cancelBtn.style.display = 'none';
            }
        }).catch(err => console.log('OTP Fetch Error:', err));
    }

    function cancelNumber(orderId) {
        if (!confirm('Are you sure you want to cancel this number?')) return;
        
        const btn = document.getElementById('btn-cancel-' + orderId);
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Cancelling...'; }
        
        const fd = new FormData();
        fd.append('action', 'cancel_order');
        fd.append('order_id', orderId);
        
        fetch('ajax.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ Order Cancelled');
                location.reload();
            } else {
                alert('❌ ' + data.message);
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-ban"></i> Cancel'; }
            }
        });
    }

    function rebuyNumber(serviceId, countryCode) {
        if (!confirm('Buy another number for this service?')) return;
        const fd = new FormData();
        fd.append('action', 'purchase_service');
        fd.append('service_id', serviceId);
        fd.append('country_code', countryCode || 'IN');
        
        fetch('ajax.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('✅ New Number Purchased: ' + data.number);
                location.reload();
            } else {
                alert('❌ ' + data.message);
            }
        });
    }

    function filterOrders(status, btn) {
        document.querySelectorAll('.uotp-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.querySelectorAll('.order-row-item').forEach(row => {
            const rowStatus = row.dataset.status;
            if (status === 'all') {
                row.style.display = 'table-row';
            } else if (status === 'CANCELLED' && (rowStatus === 'CANCELLED' || rowStatus === 'EXPIRED')) {
                row.style.display = 'table-row';
            } else if (rowStatus === status) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // ============================================================
    // PROFILE DROPDOWN & TABS
    // ============================================================
    function toggleProfileDropdown(e) {
        e.stopPropagation();
        document.getElementById('profileDropdown').classList.toggle('active');
    }

    function closeProfileDropdown() {
        document.getElementById('profileDropdown').classList.remove('active');
    }

    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('profileDropdown');
        const container = document.querySelector('.user-avatar-container');
        if (dropdown && container && !container.contains(e.target)) {
            closeProfileDropdown();
        }
    });

    function switchProfileTab(tabId) {
        document.querySelectorAll('.acc-tab-btn').forEach(btn => btn.classList.remove('active'));
        const btn = document.getElementById('btn-ptab-' + tabId);
        if(btn) btn.classList.add('active');
        
        document.querySelectorAll('.acc-tab-content').forEach(content => content.classList.remove('active'));
        const content = document.getElementById('ptab-' + tabId);
        if(content) content.classList.add('active');
    }

    function changePassword() {
        const current = document.getElementById('cp-current').value;
        const newPass = document.getElementById('cp-new').value;
        const confirmPass = document.getElementById('cp-confirm').value;

        if(!current || !newPass || !confirmPass) return alert("Fill all password fields");
        if(newPass !== confirmPass) return alert("New passwords do not match");

        const fd = new FormData();
        fd.append('action', 'change_password');
        fd.append('current_password', current);
        fd.append('new_password', newPass);
        fd.append('confirm_new_password', confirmPass);

        fetch('ajax.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert('✅ ' + data.message);
                document.getElementById('change-password-form').reset();
            } else {
                alert('❌ ' + data.message);
            }
        }).catch(err => alert("Error changing password"));
    }

    document.addEventListener('DOMContentLoaded', () => {
        initializeTimers();
        console.log('✅ OTPx Dashboard loaded with Real-Time logic!');
    });
    
// === NEW: API Key & Sessions Logic ===
    function regenerateApiKey() {
        if(!confirm("Are you sure you want to regenerate your API Key? Any application using the old key will stop working immediately.")) return;
        const fd = new FormData(); fd.append('action', 'regenerate_api');
        fetch('ajax.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
            if(data.success) { alert('✅ API Key Updated!'); location.reload(); }
            else { alert('❌ ' + data.message); }
        });
    }

    function logoutSingleSession(sessionId) {
        if(!confirm("Are you sure you want to log out of that device?")) return;
        const fd = new FormData(); fd.append('action', 'logout_session'); fd.append('session_id', sessionId);
        fetch('ajax.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
            if(data.success) { location.reload(); }
            else { alert('❌ ' + data.message); }
        });
    }

    function logoutAllSessions() {
        if(!confirm("Are you sure you want to log out from ALL devices? You will be logged out from this device as well.")) return;
        const fd = new FormData(); fd.append('action', 'logout_all_sessions');
        fetch('ajax.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
            if(data.success) { window.location.href = '../index.php'; }
        });
    }
    
// ============================================================
    // TRANSACTION HISTORY: PAGINATION & SEARCH LOGIC
    // ============================================================
    let currentTxnPage = 1;
    let txnRowsPerPage = 20;

    function renderTxnTable() {
        const rows = Array.from(document.querySelectorAll('#txn-tbody .txn-row-item'));
        if(rows.length === 0) return;

        // Sabhi rows ko pehle hide karo
        rows.forEach(row => {
            row.style.display = 'none';
            row.classList.remove('forced-show-row');
        });

        // Search ke baad jo rows visible hain unhe filter karo
        const visibleRows = rows.filter(row => !row.classList.contains('hidden-by-search'));

        // Agar user ne 'all' select kiya hai
        if(txnRowsPerPage === 'all') {
            visibleRows.forEach(row => {
                row.style.display = window.innerWidth <= 768 ? 'block' : 'table-row';
            });
            document.getElementById('txn-pagination').innerHTML = '';
            return;
        }

        const totalPages = Math.ceil(visibleRows.length / txnRowsPerPage);
        if(currentTxnPage > totalPages) currentTxnPage = 1;

        const start = (currentTxnPage - 1) * txnRowsPerPage;
        const end = start + txnRowsPerPage;

        // Sirf current page ki exact 20 rows ko dikhao
        visibleRows.slice(start, end).forEach(row => {
            row.style.display = window.innerWidth <= 768 ? 'block' : 'table-row';
        });

        renderTxnPagination(totalPages);
    }

    function renderTxnPagination(totalPages) {
        const pagContainer = document.getElementById('txn-pagination');
        pagContainer.innerHTML = '';

        if(totalPages <= 1) return;

        for(let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.innerText = i;
            btn.style.padding = '6px 12px';
            btn.style.border = '1px solid var(--border-color)';
            btn.style.borderRadius = '6px';
            btn.style.cursor = 'pointer';
            btn.style.fontWeight = '600';
            btn.style.background = (i === currentTxnPage) ? 'var(--accent)' : 'white';
            btn.style.color = (i === currentTxnPage) ? 'white' : 'var(--text-main)';
            
            btn.onclick = () => {
                currentTxnPage = i;
                renderTxnTable();
                // Scroll slightly up so user sees top of table
                document.getElementById('tab-history').scrollIntoView({behavior: 'smooth', block: 'start'});
            };
            pagContainer.appendChild(btn);
        }
    }

    function changeTxnRows() {
        const selectVal = document.getElementById('txn-rows-per-page').value;
        txnRowsPerPage = selectVal === 'all' ? 'all' : parseInt(selectVal);
        currentTxnPage = 1;
        renderTxnTable();
    }

    function searchTransactions() {
        const input = document.getElementById('txn-search-input').value.toLowerCase();
        const rows = document.querySelectorAll('#txn-tbody .txn-row-item');

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if(text.includes(input)) {
                row.classList.remove('hidden-by-search');
            } else {
                row.classList.add('hidden-by-search');
            }
        });
        currentTxnPage = 1;
        renderTxnTable();
    }

    // Call this once DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        renderTxnTable();
    });    
</script>
</div> 
</div>
<!-- ============================================================
    PREMIUM GLOBAL FLOATING SUPPORT WIDGET (FOR LOGIN PAGE)
============================================================ -->
<?php
$widgetLinks = [];
try {
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT * FROM SupportLinks WHERE show_in_widget = 1 ORDER BY id ASC");
        if($stmt) {
            $widgetLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch(Exception $e) {}

if(empty($widgetLinks)) {
    $widgetLinks = [
        ['title' => 'WhatsApp', 'url' => '#', 'icon_class' => 'fa-brands fa-whatsapp'],
        ['title' => 'Telegram', 'url' => '#', 'icon_class' => 'fa-brands fa-telegram']
    ];
}
?>
<style>
    .fw-container { position: fixed; bottom: 30px; right: 30px; z-index: 999999; display: flex; flex-direction: column; align-items: center; gap: 10px; font-family: 'Inter', sans-serif;}
    .fw-menu { background: #f97316; padding: 15px 10px; border-radius: 40px; display: flex; flex-direction: column; gap: 15px; opacity: 0; visibility: hidden; transform: translateY(20px); transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55); box-shadow: 0 10px 25px rgba(249, 115, 22, 0.4); margin-bottom: 5px; }
    .fw-menu.open { opacity: 1; visibility: visible; transform: translateY(0); }
    .fw-item { width: 45px; height: 45px; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: white; font-size: 24px; text-decoration: none; transition: 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
    .fw-item:hover { transform: scale(1.15); }
    .fw-toggle-wrapper { position: relative; display: flex; align-items: center; }
    .fw-toggle { width: 65px; height: 65px; background: #f97316; border-radius: 50%; color: white; display: flex; justify-content: center; align-items: center; font-size: 32px; cursor: pointer; box-shadow: 0 8px 25px rgba(249, 115, 22, 0.5); z-index: 2; transition: 0.3s; border: none; outline: none;}
    .fw-toggle:hover { transform: scale(1.05); }
    .fw-toggle i { transition: transform 0.3s; }
    .fw-toggle.active i { transform: rotate(180deg); }
    .fw-tooltip { position: absolute; right: 85px; background: #ffffff; color: #f97316; padding: 10px 18px; border-radius: 20px; font-size: 14px; font-weight: 700; box-shadow: 0 4px 15px rgba(0,0,0,0.15); white-space: nowrap; opacity: 0; visibility: hidden; transition: 0.5s ease; pointer-events: none; border: 1px solid #ffedd5; display: flex; align-items: center; gap: 10px; }
    .fw-tooltip.show { opacity: 1; visibility: visible; pointer-events: auto; animation: slideInLeft 0.5s ease; }
    .fw-tooltip::after { content: ''; position: absolute; right: -7px; top: 50%; transform: translateY(-50%); border-width: 7px 0 7px 7px; border-style: solid; border-color: transparent transparent transparent #ffffff; }
    .fw-tooltip-close { color: #cbd5e1; cursor: pointer; font-size: 16px; margin-left: 5px; transition: 0.2s; }
    .fw-tooltip-close:hover { color: #ef4444; }
    @keyframes slideInLeft { 0% { transform: translateX(-20px); opacity: 0; } 100% { transform: translateX(0); opacity: 1; } }
    @media(max-width: 768px) { .fw-container { bottom: 20px; right: 20px; } .fw-toggle { width: 55px; height: 55px; font-size: 24px; } .fw-item { width: 40px; height: 40px; font-size: 20px;} .fw-tooltip { right: 75px; font-size: 12px; padding: 8px 14px;} }
</style>

<div class="fw-container">
    <div class="fw-menu" id="fwMenu">
        <?php foreach(array_reverse($widgetLinks) as $wlink): 
            $bg = '#333'; 
            $iconStr = strtolower($wlink['icon_class']);
            if(strpos($iconStr, 'whatsapp') !== false) $bg = '#25D366';
            elseif(strpos($iconStr, 'telegram') !== false) $bg = '#0088cc';
            elseif(strpos($iconStr, 'instagram') !== false) $bg = '#E1306C';
            elseif(strpos($iconStr, 'phone') !== false) $bg = '#3b82f6';
            elseif(strpos($iconStr, 'envelope') !== false) $bg = '#ea4335';
        ?>
        <a href="<?php echo htmlspecialchars($wlink['url']); ?>" target="_blank" class="fw-item" style="background:<?php echo $bg; ?>;" title="<?php echo htmlspecialchars($wlink['title']); ?>">
            <i class="<?php echo $wlink['icon_class']; ?>"></i>
        </a>
        <?php endforeach; ?>
    </div>
    
    <div class="fw-toggle-wrapper">
        <div class="fw-tooltip" id="fwTooltip">
            <span>Need Help?</span>
            <i class="fa-solid fa-xmark fw-tooltip-close" onclick="closeSupportTooltip(event)"></i>
        </div>
        <button class="fw-toggle" id="fwToggleBtn" onclick="toggleSupportWidget()">
            <i class="fa-solid fa-headset"></i>
        </button>
    </div>
</div>

<script>
    function toggleSupportWidget() {
        document.getElementById('fwMenu').classList.toggle('open');
        document.getElementById('fwToggleBtn').classList.toggle('active');
        closeSupportTooltip(null);
    }
    function closeSupportTooltip(e) {
        if(e) e.stopPropagation();
        const tooltip = document.getElementById('fwTooltip');
        if(tooltip) tooltip.classList.remove('show');
    }
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(() => {
            const tooltip = document.getElementById('fwTooltip');
            if(tooltip) tooltip.classList.add('show');
            setTimeout(() => { closeSupportTooltip(null); }, 600000);
        }, 5000); // 5 सेकंड में पॉपअप आ जाएगा
    });
</script>

<!-- Individual Broadcast Popup Modal -->
<div class="modal-overlay" id="broadcast-modal">
    <div class="modal-content bc-type-info" id="bc-modal-box" style="max-width: 450px; padding: 30px; text-align: center;">
        <div id="bc-dynamic-content">
            <!-- Content injected by JS -->
        </div>
    </div>
</div>

<!-- Notification Center Modal -->
<div class="modal-overlay" id="notification-modal" onclick="if(event.target===this) closeModal('notification-modal')">
    <div class="modal-content" style="max-width: 500px; padding: 0; overflow: hidden;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border-color); background:#f8fafc;">
            <h3 style="margin:0; font-size:18px; font-weight:800;"><i class="fa-regular fa-bell" style="color:var(--accent);"></i> Notifications</h3>
            <button onclick="closeModal('notification-modal')" style="background:transparent; border:none; font-size:20px; cursor:pointer; color:var(--text-muted);">✖</button>
        </div>
        <div style="max-height: 400px; overflow-y: auto; padding: 10px 0;">
            <?php if(empty($activeBroadcasts)): ?>
                <div style="text-align:center; padding:40px 20px; color:var(--text-muted);">
                    <i class="fa-regular fa-bell-slash" style="font-size:36px; margin-bottom:15px; opacity:0.5;"></i>
                    <p>No new notifications</p>
                </div>
            <?php else: ?>
                <?php foreach(array_reverse($activeBroadcasts) as $bc): 
                    $color = '#3b82f6';
                    if(($bc['type'] ?? '') == 'success') $color = '#10b981';
                    if(($bc['type'] ?? '') == 'warning') $color = '#f59e0b';
                    if(($bc['type'] ?? '') == 'error') $color = '#ef4444';
                ?>
                <div class="notif-item" style="cursor: pointer; transition: 0.2s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'" onclick="openSpecificBroadcast(<?php echo $bc['id']; ?>)">
    
    <!-- सिर्फ इमेज शो होगी, कोई आइकॉन नहीं -->
    <?php if(!empty($bc['image_url'])): ?>
        <img src="<?php echo htmlspecialchars($bc['image_url']); ?>" alt="img">
    <?php endif; ?>
    
    <div class="notif-content">
                    <div class="notif-content">
                        <h4><?php echo htmlspecialchars($bc['title']); ?></h4>
                        <p><?php echo htmlspecialchars(substr($bc['message'], 0, 100)) . (strlen($bc['message'])>100?'...':''); ?></p>
                        
                        <?php if(!empty($bc['btn_text']) && !empty($bc['btn_link'])): ?>
                            <a href="<?php echo htmlspecialchars($bc['btn_link']); ?>" target="_blank" style="display:inline-block; margin-top:8px; font-size:12px; color:<?php echo $color; ?>; font-weight:bold; text-decoration:none;"><i class="fa-solid fa-arrow-up-right-from-square"></i> <?php echo htmlspecialchars($bc['btn_text']); ?></a>
                        <?php endif; ?>
                        
                        <?php if(!empty($bc['end_date']) && $bc['end_date'] != '0000-00-00 00:00:00'): ?>
                            <span class="notif-time"><i class="fa-regular fa-clock"></i> Valid till <?php echo date('d M Y, h:i A', strtotime($bc['end_date'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Broadcast Queue Logic -->
<script>
    const activeBroadcasts = <?php echo json_encode($activeBroadcasts, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let currentBcIndex = 0;
    let isQueueMode = false; // यह चेक करेगा कि पॉपअप लाइन (Queue) में चल रहा है या सिंगल खुला है

    // पॉपअप का डिज़ाइन बनाने और दिखाने का फंक्शन
    function renderAndShowModal(bc) {
        let html = '';
        
        // Modal Border Color based on Type
        let modalBox = document.getElementById('bc-modal-box');
        modalBox.className = 'modal-content'; 
        modalBox.classList.add('bc-type-' + (bc.type || 'info'));
        
        let iconColor = '#3b82f6';
        if(bc.type === 'success') iconColor = '#10b981';
        if(bc.type === 'warning') iconColor = '#f59e0b';
        if(bc.type === 'error') iconColor = '#ef4444';

        if(bc.image_url) {
            html += `<img src="${bc.image_url}" class="bc-modal-img">`;
        } else {
            html += `<div style="width:70px; height:70px; background:${iconColor}20; color:${iconColor}; border-radius:50%; display:flex; justify-content:center; align-items:center; font-size:30px; margin:0 auto 20px;"><i class="fa-solid fa-bullhorn"></i></div>`;
        }
        
        html += `<h2 class="bc-modal-title">${bc.title}</h2>`;
        html += `<div class="bc-modal-text">${bc.message}</div>`;
        
        // Button Logic
        html += `<div style="display:flex; gap:10px; margin-top:20px;">`;
        if(bc.btn_text && bc.btn_link) {
            html += `<a href="${bc.btn_link}" target="_blank" class="btn-buy" style="flex:1; justify-content:center; background:${iconColor}; text-decoration:none;">${bc.btn_text}</a>`;
            html += `<button onclick="closeBroadcastModal()" class="btn-buy" style="flex:1; justify-content:center; background:transparent; border:1px solid #cbd5e1; color:var(--text-main);">Close</button>`;
        } else {
            html += `<button onclick="closeBroadcastModal()" class="btn-buy" style="flex:1; justify-content:center; background:${iconColor};">Got it, Thanks!</button>`;
        }
        html += `</div>`;
        
        document.getElementById('bc-dynamic-content').innerHTML = html;
        document.getElementById('broadcast-modal').classList.add('show');
    }

    // हर बार पेज लोड होने पर सारे पॉपअप एक-एक करके दिखाने का फंक्शन
    function showNextBroadcast() {
        if (currentBcIndex < activeBroadcasts.length) {
            isQueueMode = true; // Queue चालू है
            let bc = activeBroadcasts[currentBcIndex];
            renderAndShowModal(bc);
        } else {
            isQueueMode = false; // Queue खत्म हो गई
        }
    }

    // पॉपअप को बंद करने का फंक्शन
    function closeBroadcastModal() {
        document.getElementById('broadcast-modal').classList.remove('show');
        
        // अगर Queue चालू है, तो 0.6 सेकंड बाद अगला पॉपअप दिखाओ
        if(isQueueMode) {
            currentBcIndex++;
            setTimeout(showNextBroadcast, 600);
        }
    }

    function openNotificationModal() {
        document.getElementById('notification-modal').classList.add('show');
    }

    // बेल आइकॉन की लिस्ट में से किसी एक पर क्लिक करने पर उसे खोलने का फंक्शन
    function openSpecificBroadcast(id) {
        let bc = activeBroadcasts.find(b => b.id == id);
        if (bc) {
            closeModal('notification-modal'); // नोटिफिकेशन लिस्ट बंद करें
            isQueueMode = false; // यह सिंगल पॉपअप है, इसे काटने पर दूसरा नहीं आना चाहिए
            setTimeout(() => { renderAndShowModal(bc); }, 300); // पॉपअप दिखाएं
        }
    }

    // जैसे ही वेबसाइट लोड हो, 1 सेकंड बाद पॉपअप दिखाना शुरू करें
    document.addEventListener('DOMContentLoaded', () => {
        if(activeBroadcasts.length > 0) {
            setTimeout(showNextBroadcast, 1000); 
        }
    });
</script>

<!-- ============================================================
    100% EXACT OTPX.ORG MASTER FOOTER & ADMIN CONTROLLED
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
        background: #ffffff;
        border-top: 1px solid #eaeaea;
        color: #111827;
        font-family: 'Inter', -apple-system, sans-serif;
        padding: 60px 20px 30px;
        margin-top: 60px;
        width: 100%;
        position: relative;
        z-index: 99;
    }
    .otpx-mf-grid {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 40px;
        margin-bottom: 50px;
    }
    
    /* Column 1: Brand & Badges */
    .mf-brand-col {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    .mf-logo-row {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 22px;
        font-weight: 800;
        color: #111827;
        margin-bottom: 15px;
        letter-spacing: -0.5px;
    }
    .mf-logo-row img {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
    }
    .mf-desc {
        color: #4b5563;
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 20px;
        max-width: 340px;
    }
    .mf-pills-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .mf-pill {
        background: #f3f4f6;
        color: #1f2937;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid #e5e7eb;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .mf-social-icons {
        display: flex;
        gap: 12px;
        align-items: center;
        margin-top: 5px;
    }
    .mf-social-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #f3f4f6;
        color: #374151;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 15px;
        text-decoration: none;
        transition: 0.2s;
    }
    .mf-social-icon:hover {
        background: #2563eb;
        color: white;
    }

    /* Columns 2, 3, 4: Links */
    .mf-links-col h4 {
        font-size: 13px;
        font-weight: 800;
        color: #111827;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .mf-links-col ul {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .mf-links-col ul li a {
        color: #4b5563;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        transition: 0.2s;
    }
    .mf-links-col ul li a:hover {
        color: #2563eb;
        padding-left: 3px;
    }

    /* Bottom Section */
    .mf-divider {
        max-width: 1200px;
        margin: 0 auto;
        height: 1px;
        background: #eaeaea;
        margin-bottom: 30px;
    }
    .otpx-mf-bottom {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        font-size: 13px;
        color: #6b7280;
    }
    .mf-pay-cards {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    .card-badge {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 800;
        font-size: 11px;
        color: #1f2937;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    /* Responsive Design */
    @media(max-width: 992px) {
        .otpx-mf-grid {
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
    }
    @media(max-width: 600px) {
        .otpx-mf-grid {
            grid-template-columns: 1fr;
            gap: 25px;
        }
        .mf-brand-col { align-items: flex-start; }
        .otpx-mf-bottom {
            flex-direction: column;
            text-align: center;
            justify-content: center;
        }
        .mf-pay-cards { justify-content: center; }
    }
</style>

<footer class="otpx-master-footer">
    <div class="otpx-mf-grid">
        <!-- Column 1: Brand, Bio, Badges & Dynamic Support Buttons -->
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
            
            <!-- Original Badges (Secure, 150+ Countries, Instant Delivery) -->
            <div class="mf-pills-row">
                <span class="mf-pill"><i class="fa-solid fa-shield-halved" style="color:#2563eb;"></i> Secure & Private</span>
                <span class="mf-pill"><i class="fa-solid fa-earth-americas" style="color:#10b981;"></i> 150+ Countries</span>
                <span class="mf-pill"><i class="fa-solid fa-bolt" style="color:#f59e0b;"></i> Instant Delivery</span>
            </div>

            <!-- Admin Panel Support Links (Telegram, WhatsApp etc.) -->
            <div class="mf-social-icons">
                <?php if(!empty($footerSupportLinks)): ?>
                    <?php foreach($footerSupportLinks as $slink): 
                        $sBg = '#f3f4f6';
                        $sColor = '#374151';
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

        <!-- Column 2: Product (Quick Links from Admin Panel) -->
        <div class="mf-links-col">
            <h4>Product</h4>
            <ul>
                <?php if(empty($footerLinksList)): ?>
                    <li><a href="#" onclick="switchTab('dashboard')">Pricing</a></li>
                    <li><a href="../api-docs.php" target="_blank">API Access</a></li>
                    <li><a href="#" onclick="switchTab('support')">FAQ</a></li>
                    <li><a href="#">System Status</a></li>
                <?php else: ?>
                    <?php foreach($footerLinksList as $flink): ?>
                        <li><a href="<?php echo htmlspecialchars($flink['url']); ?>" target="_blank"><?php echo htmlspecialchars($flink['title']); ?></a></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

      <!-- Column 3: Developers (Only 2 Clean Options) -->
        <div class="mf-links-col">
            <h4>Developers</h4>
            <ul>
                <li><a href="../api-docs.php" target="_blank">API Documentation</a></li>
                <li><a href="#" onclick="switchTab('profile'); switchProfileTab('api');">API Keys Manager</a></li>
            </ul>
        </div>

        <!-- Column 4: Legal Pages (Dynamic from Page Management) -->
        <div class="mf-links-col">
            <h4>Legal</h4>
            <ul>
                <?php if(empty($activeLegalPages)): ?>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                <?php else: ?>
                    <?php foreach($activeLegalPages as $page): ?>
                        <li><a href="page.php?slug=<?php echo htmlspecialchars($page['slug']); ?>" target="_blank"><?php echo htmlspecialchars($page['title']); ?></a></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

    <!-- Bottom Bar: Copyright & Payment Badges -->
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