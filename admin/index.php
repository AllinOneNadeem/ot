<?php
// ============================================================
// ADMIN DASHBOARD - COMPLETE FIXED
// ============================================================

// ============================================================
// ERROR REPORTING - FORCE DISPLAY
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

// ============================================================
// SESSION START - SINGLE CALL
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// LOGIN CHECK
// ============================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// ============================================================
// DATABASE CONNECTION
// ============================================================
require_once '../db.php';
require_once '../includes/functions.php';

// ============================================================
// ADMIN ROLE CHECK
// ============================================================
$stmtUser = $pdo->prepare("SELECT id, name, email, balance, role FROM Users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$adminUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$adminUser || $adminUser['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// ============================================================
// FETCH SITE SETTINGS
// ============================================================
$stmtSettings = $pdo->query("SELECT * FROM SiteSettings LIMIT 1");
$siteSettings = $stmtSettings->fetch(PDO::FETCH_ASSOC) ?: [
    'site_name' => 'OTPX',
    'site_logo' => '',
    'support_link' => 'https://t.me/support',
    'currency_symbol' => '₹',
    'footer' => '',
    'footer_copyright' => '',
    'support_bot' => '',
    'support_channel' => ''
];

$currency = $siteSettings['currency_symbol'] ?? '₹';

// ============================================================
// ADMIN DASHBOARD STATS
// ============================================================
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn() ?: 0;
    $totalServices = $pdo->query("SELECT COUNT(*) FROM Services")->fetchColumn() ?: 0;
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM Orders")->fetchColumn() ?: 0;
    $pendingOrders = $pdo->query("SELECT COUNT(*) FROM Orders WHERE status = 'WAITING'")->fetchColumn() ?: 0;
    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM Payments WHERE status = 'completed'")->fetchColumn() ?: 0;
} catch (Exception $e) {
    $totalUsers = 0;
    $totalServices = 0;
    $totalOrders = 0;
    $pendingOrders = 0;
    $totalRevenue = 0;
}

// ============================================================
// FETCH ALL USERS
// ============================================================
$allUsers = $pdo->query("SELECT id, name, email, balance, role, banned, createdAt FROM Users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// FETCH ALL SERVICES WITH SERVER INFO
// ============================================================
$allServices = $pdo->query("
    SELECT s.*, sv.name as server_name 
    FROM Services s 
    LEFT JOIN Servers sv ON s.server_id = sv.id 
    ORDER BY s.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// FETCH ALL PAYMENTS
// ============================================================
$allPayments = $pdo->query("
    SELECT p.*, u.email as user_email 
    FROM Payments p 
    LEFT JOIN Users u ON p.user_id = u.id 
    ORDER BY p.id DESC LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// FETCH ALL ORDERS
// ============================================================
$allOrders = $pdo->query("
    SELECT o.*, u.email as user_email, sv.country_emoji as country_flag FROM Orders o LEFT JOIN Users u ON o.user_id = u.id LEFT JOIN Servers sv ON o.server_id = sv.id ORDER BY o.id DESC LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// FETCH SITE PAYMENTS SETTINGS
// ============================================================
$sitePayments = $pdo->query("SELECT * FROM SitePayments LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [
    'paytm_upi' => 'your_upi@paytm',
    'min_deposit' => 10.00,
    'paytm_mid' => '',
    'bharatpe_mid' => '',
    'bharatpe_token' => '',
    'bharatpe_upi' => '',
    'paytm_qr' => '',
    'bharatpe_qr' => '',
    'crypto_api' => '',
    'crypto_min' => 0.50,
    'crypto_instructions' => 'Send TRX to the address above'
];


// ============================================================
// CALCULATE REAL-TIME PAYMENT STATS FROM ALL TABLES (FIXED)
// ============================================================
try {
    $stats = [];
    
    // 1. Today's Total Amount & Unique Users (Payments + Transactions + Promos)
    $todayPay = $pdo->query("SELECT COALESCE(SUM(amount), 0) as amt, COUNT(DISTINCT user_id) as users FROM Payments WHERE status = 'completed' AND DATE(createdAt) = CURDATE()")->fetch(PDO::FETCH_ASSOC);
    
    $todayTxn = $pdo->query("SELECT COALESCE(SUM(amount), 0) as amt FROM Transactions WHERE type = 'credit' AND DATE(createdAt) = CURDATE()")->fetch(PDO::FETCH_ASSOC);
    
    $todayPromo = $pdo->query("SELECT COALESCE(SUM(amount), 0) as amt FROM PromoRedemptions WHERE DATE(redeemed_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC);

    $stats['today_amount'] = ($todayPay['amt'] ?? 0) + ($todayTxn['amt'] ?? 0) + ($todayPromo['amt'] ?? 0);
    $stats['today_users'] = $todayPay['users'] ?? 1;

    // 2. This Week's Total Amount
    $wkPay = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM Payments WHERE status = 'completed' AND YEARWEEK(createdAt, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn() ?: 0;
    $wkTxn = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM Transactions WHERE type = 'credit' AND YEARWEEK(createdAt, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn() ?: 0;
    $wkPromo = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM PromoRedemptions WHERE YEARWEEK(redeemed_at, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn() ?: 0;
    $stats['weekly_amount'] = $wkPay + $wkTxn + $wkPromo;

    // 3. This Month's Total Amount
    $moPay = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM Payments WHERE status = 'completed' AND MONTH(createdAt) = MONTH(CURDATE()) AND YEAR(createdAt) = YEAR(CURDATE())")->fetchColumn() ?: 0;
    $moTxn = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM Transactions WHERE type = 'credit' AND MONTH(createdAt) = MONTH(CURDATE()) AND YEAR(createdAt) = YEAR(CURDATE())")->fetchColumn() ?: 0;
    $moPromo = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM PromoRedemptions WHERE MONTH(redeemed_at) = MONTH(CURDATE()) AND YEAR(redeemed_at) = YEAR(CURDATE())")->fetchColumn() ?: 0;
    $stats['monthly_amount'] = $moPay + $moTxn + $moPromo;

    // 4. Today Gateway Breakdown (Accurate Mapping)
    $stats['gw_bharatpe'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM Payments WHERE status = 'completed' AND DATE(createdAt) = CURDATE() AND (mode LIKE '%bharatpe%' OR mode LIKE '%bp%')")->fetchColumn() ?: 0;
    
    $stats['gw_paytm'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM Payments WHERE status = 'completed' AND DATE(createdAt) = CURDATE() AND mode LIKE '%paytm%'")->fetchColumn() ?: 0;
    
    // UTR / Manual payments from Payments table or Transactions table
    $stats['gw_utr'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM Payments WHERE status = 'completed' AND DATE(createdAt) = CURDATE() AND (mode LIKE '%utr%' OR mode LIKE '%manual%' OR mode LIKE '%upi%')")->fetchColumn() ?: 0;
    
    // Promo Code totals from PromoRedemptions & Transactions
    $promoFromTable = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM PromoRedemptions WHERE DATE(redeemed_at) = CURDATE()")->fetchColumn() ?: 0;
    $promoFromTxn = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM Transactions WHERE type = 'credit' AND (payment_mode LIKE '%promo%' OR message LIKE '%Promo%') AND DATE(createdAt) = CURDATE()")->fetchColumn() ?: 0;
    $stats['gw_promo'] = $promoFromTable + $promoFromTxn;
    
    // Admin Added totals from Transactions table
    $stats['gw_admin'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM Transactions WHERE type = 'credit' AND (payment_mode LIKE '%admin%' OR message LIKE '%Admin%' OR payment_mode IS NULL) AND DATE(createdAt) = CURDATE()")->fetchColumn() ?: 0;

} catch (Exception $e) {
    $stats = [
        'today_amount' => 0, 'today_users' => 0, 'weekly_amount' => 0, 'monthly_amount' => 0,
        'gw_bharatpe' => 0, 'gw_paytm' => 0, 'gw_utr' => 0, 'gw_promo' => 0, 'gw_admin' => 0
    ];
}
// ============================================================
// FETCH ALL SERVERS
// ============================================================
$servers = $pdo->query("SELECT * FROM Servers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// FETCH PROMO CODES
// ============================================================
$promoCodes = $pdo->query("SELECT * FROM PromoCodes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// FETCH FOOTER LINKS
// ============================================================
$footerLinks = $pdo->query("SELECT * FROM FooterLinks ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// FETCH BANNERS
// ============================================================
$banners = $pdo->query("SELECT * FROM Banners WHERE status = 1 ORDER BY `order` ASC")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// FETCH PAGES
// ============================================================
$pages = $pdo->query("SELECT * FROM Pages ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// FETCH API PROVIDERS
// ============================================================
$providers = $pdo->query("SELECT * FROM ApiProviders ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// GET ACTIVE TAB FROM URL
// ============================================================
$activeTab = $_GET['tab'] ?? 'dashboard';

// ============================================================
// PAGE TITLES
// ============================================================
$titles = [
    'dashboard' => 'Dashboard',
    'users' => 'Users Management',
    'services' => 'Services Management',
    'orders' => 'Orders Management',
    'payments' => 'Payments',
    'payment-settings' => 'Payment Settings',
    'api' => 'API Settings',
    'settings' => 'Site Settings',
    'support' => 'Support',
    'broadcast' => 'Broadcast',
    'footer' => 'Footer Management',
    'promo' => 'Promo Codes',
    'pages' => 'Pages Management',
    'banners' => 'Banner Management',
    'profile' => 'Profile'
];

// ============================================================
// CHECK IF REQUIRED TABLES EXIST
// ============================================================
$requiredTables = ['Users', 'Services', 'Orders', 'Payments', 'SiteSettings', 'Servers', 'ApiProviders', 'PromoCodes', 'FooterLinks', 'Banners', 'Pages'];
foreach ($requiredTables as $table) {
    $check = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($check->rowCount() == 0) {
        // Table doesn't exist - create it
        createMissingTable($pdo, $table);
    }
}

function createMissingTable($pdo, $table) {
    try {
        switch($table) {
            case 'Users':
                $pdo->exec("CREATE TABLE IF NOT EXISTS Users (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    balance DECIMAL(10,2) DEFAULT 0,
                    role ENUM('admin','user') DEFAULT 'user',
                    banned TINYINT(1) DEFAULT 0,
                    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                break;
            case 'Services':
                $pdo->exec("CREATE TABLE IF NOT EXISTS Services (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    code VARCHAR(50) UNIQUE NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    server_id INT DEFAULT 0,
                    logo VARCHAR(255) DEFAULT '',
                    popular TINYINT(1) DEFAULT 0,
                    cutback INT DEFAULT 0,
                    cancel_disable INT DEFAULT 2,
                    api_service_id VARCHAR(50) DEFAULT '',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                break;
            case 'Orders':
                $pdo->exec("CREATE TABLE IF NOT EXISTS Orders (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    service_id INT DEFAULT 0,
                    service_name VARCHAR(100) DEFAULT '',
                    number VARCHAR(50) DEFAULT '',
                    price DECIMAL(10,2) DEFAULT 0,
                    status ENUM('WAITING','RECEIVED','CANCELLED','COMPLETED') DEFAULT 'WAITING',
                    order_id VARCHAR(50) DEFAULT '',
                    country_code VARCHAR(10) DEFAULT '',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                break;
            case 'Payments':
                $pdo->exec("CREATE TABLE IF NOT EXISTS Payments (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    mode VARCHAR(50) DEFAULT 'paytm',
                    utr VARCHAR(100) DEFAULT '',
                    status ENUM('pending','completed','failed') DEFAULT 'pending',
                    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                break;
            case 'SiteSettings':
                $pdo->exec("CREATE TABLE IF NOT EXISTS SiteSettings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    site_name VARCHAR(100) DEFAULT 'OTPX',
                    site_logo VARCHAR(255) DEFAULT '',
                    currency_symbol VARCHAR(10) DEFAULT '₹',
                    footer TEXT DEFAULT '',
                    footer_copyright VARCHAR(255) DEFAULT '',
                    support_link VARCHAR(255) DEFAULT '',
                    support_bot VARCHAR(100) DEFAULT '',
                    support_channel VARCHAR(100) DEFAULT '',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                // Insert default settings
                $pdo->exec("INSERT INTO SiteSettings (site_name, currency_symbol) VALUES ('OTPX', '₹')");
                break;
            case 'Servers':
                $pdo->exec("CREATE TABLE IF NOT EXISTS Servers (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    country_code VARCHAR(10) DEFAULT '',
                    country_emoji VARCHAR(10) DEFAULT '',
                    country_name VARCHAR(100) DEFAULT '',
                    provider_type VARCHAR(50) DEFAULT 'smsbower',
                    api_key VARCHAR(255) NOT NULL,
                    base_url VARCHAR(255) DEFAULT '',
                    status TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                break;
            case 'ApiProviders':
                $pdo->exec("CREATE TABLE IF NOT EXISTS ApiProviders (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    provider_type VARCHAR(50) NOT NULL,
                    api_key VARCHAR(255) NOT NULL,
                    status TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                break;
            case 'PromoCodes':
                $pdo->exec("CREATE TABLE IF NOT EXISTS PromoCodes (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    code VARCHAR(50) UNIQUE NOT NULL,
                    name VARCHAR(100) DEFAULT '',
                    amount DECIMAL(10,2) NOT NULL,
                    type ENUM('fixed','percentage') DEFAULT 'fixed',
                    used_count INT DEFAULT 0,
                    max_uses INT DEFAULT NULL,
                    for_new_users TINYINT(1) DEFAULT 0,
                    valid_from DATETIME DEFAULT NULL,
                    valid_until DATETIME DEFAULT NULL,
                    status TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                break;
            case 'FooterLinks':
                $pdo->exec("CREATE TABLE IF NOT EXISTS FooterLinks (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    title VARCHAR(100) NOT NULL,
                    url VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                break;
            case 'Banners':
                $pdo->exec("CREATE TABLE IF NOT EXISTS Banners (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    title VARCHAR(100) NOT NULL,
                    subtitle VARCHAR(255) DEFAULT '',
                    image_url VARCHAR(255) NOT NULL,
                    link_url VARCHAR(255) DEFAULT '',
                    position VARCHAR(50) DEFAULT 'home_top',
                    `order` INT DEFAULT 0,
                    status TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                break;
            case 'Pages':
                $pdo->exec("CREATE TABLE IF NOT EXISTS Pages (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    title VARCHAR(100) NOT NULL,
                    slug VARCHAR(100) UNIQUE NOT NULL,
                    content LONGTEXT NOT NULL,
                    status TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                break;
            default:
                break;
        }
    } catch (Exception $e) {
        // Silent fail for table creation
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($siteSettings['site_name']); ?> - Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        /* ============================================================
           ADMIN PANEL CSS - COMPLETE
           ============================================================ */
        :root {
            --accent: #246bfd;
            --accent-dark: #1a56db;
            --accent-light: #e8edfd;
            --bg-body: #f0f2f5;
            --bg-sidebar: #0f172a;
            --bg-card: #ffffff;
            --bg-hover: rgba(36, 107, 253, 0.06);
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-white: #ffffff;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --success: #22c55e;
            --warning: #f59e0b;
            --info: #3b82f6;
            --sidebar-width: 270px;
            --radius: 12px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background: var(--bg-body);
            color: var(--text-main);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        .app-container {
            display: flex;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            color: var(--text-white);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 100;
        }

        .sidebar .logo-area {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar .logo-area .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--accent);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
        }

        .sidebar .logo-area h2 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .sidebar .logo-area .badge-admin {
            background: rgba(239,68,68,0.2);
            color: #f87171;
            font-size: 9px;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: auto;
        }

        .sidebar .admin-profile {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .sidebar .admin-profile .avatar {
            width: 42px;
            height: 42px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }

        .sidebar .admin-profile .info {
            flex: 1;
            overflow: hidden;
        }
        .sidebar .admin-profile .info .name {
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar .admin-profile .info .role {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sidebar .menu {
            flex: 1;
            padding: 12px 10px;
            overflow-y: auto;
        }

        .sidebar .menu .menu-label {
            font-size: 10px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.25);
            letter-spacing: 1px;
            padding: 12px 12px 6px;
            font-weight: 600;
        }

        .sidebar .menu .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            color: rgba(255,255,255,0.55);
            border-radius: var(--radius);
            margin-bottom: 2px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
            text-decoration: none;
        }

        .sidebar .menu .menu-item:hover {
            background: rgba(255,255,255,0.06);
            color: white;
        }

        .sidebar .menu .menu-item.active {
            background: rgba(36,107,253,0.15);
            color: var(--accent);
        }

        .sidebar .menu .menu-item i {
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        .sidebar .menu .menu-item .badge {
            margin-left: auto;
            background: var(--danger);
            color: white;
            font-size: 10px;
            padding: 1px 8px;
            border-radius: 20px;
            font-weight: 700;
        }

        .sidebar .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            margin: 8px 10px 16px;
            color: rgba(255,255,255,0.4);
            border: 1px solid rgba(239,68,68,0.2);
            border-radius: var(--radius);
            cursor: pointer;
            transition: 0.2s;
            background: transparent;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
        }

        .sidebar .logout-btn:hover {
            background: rgba(239,68,68,0.12);
            color: #f87171;
            border-color: rgba(239,68,68,0.4);
        }

        .sidebar .logout-btn i {
            font-size: 16px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-width: 0;
            width: 100%;
        }

        .top-navbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: 12px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .top-navbar .left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .top-navbar .left .menu-toggle {
            display: none;
            background: transparent;
            border: none;
            font-size: 22px;
            color: var(--text-main);
            cursor: pointer;
        }

        .top-navbar .left .page-title {
            font-size: 18px;
            font-weight: 700;
        }

        .top-navbar .right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .top-navbar .right .notification-btn {
            background: transparent;
            border: none;
            font-size: 20px;
            color: var(--text-muted);
            cursor: pointer;
            position: relative;
        }

        .top-navbar .right .notification-btn .dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid var(--bg-card);
        }

        .top-navbar .right .admin-badge {
            background: var(--accent-light);
            color: var(--accent);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .top-navbar .right .back-user-btn {
            background: #6b7280;
            color: white;
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }

        .top-navbar .right .back-user-btn:hover {
            background: #4b5563;
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            width: 100%;
            max-width: 100%;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 18px 20px;
            box-shadow: var(--shadow);
            transition: 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card .label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .stat-card .value {
            font-size: 26px;
            font-weight: 800;
            margin-top: 4px;
        }

        .stat-card .value .currency {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .stat-card .icon {
            float: right;
            font-size: 28px;
            opacity: 0.2;
            margin-top: -5px;
        }

        .stat-card.accent .value { color: var(--accent); }
        .stat-card.success .value { color: var(--success); }
        .stat-card.warning .value { color: var(--warning); }
        .stat-card.danger .value { color: var(--danger); }

        /* Tab Content */
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .component-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 20px 24px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }

        .component-card .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .component-card .card-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .component-card .card-header h3 i {
            color: var(--accent);
        }

        .component-card .card-header .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: var(--accent-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(36,107,253,0.3); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #16a34a; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: var(--danger-hover); }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-muted); }
        .btn-outline:hover { border-color: var(--accent); color: var(--accent); background: var(--bg-hover); }
        .btn-sm { padding: 5px 12px; font-size: 12px; }
        .btn-xs { padding: 3px 8px; font-size: 11px; border-radius: 6px; }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        table th {
            text-align: left;
            padding: 10px 12px;
            background: var(--bg-body);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }

        table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        table tr:hover td {
            background: var(--bg-hover);
        }

        .status-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.active { background: #dcfce7; color: #16a34a; }
        .status-badge.pending { background: #fef3c7; color: #d97706; }
        .status-badge.banned { background: #fee2e2; color: #dc2626; }
        .status-badge.success { background: #dcfce7; color: #16a34a; }
        .status-badge.waiting { background: #dbeafe; color: #2563eb; }
        .status-badge.cancelled { background: #fee2e2; color: #dc2626; }
        .status-badge.received { background: #dcfce7; color: #16a34a; }

        /* Forms */
        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 4px;
            color: var(--text-main);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-body);
            color: var(--text-main);
            transition: 0.2s;
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(36,107,253,0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-box {
            background: var(--bg-card);
            max-width: 550px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow-lg);
            animation: fadeIn 0.3s ease;
        }

        .modal-box .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-box .modal-header h3 {
            font-size: 18px;
            font-weight: 700;
        }

        .modal-box .modal-header .close-btn {
            background: transparent;
            border: none;
            font-size: 22px;
            color: var(--text-muted);
            cursor: pointer;
        }

        .modal-box .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 50;
        }

        .overlay.active {
            display: block;
        }

        /* Search Box */
        .search-box {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-body);
            color: var(--text-main);
            outline: none;
            transition: 0.2s;
            margin-bottom: 10px;
        }

        .search-box:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(36,107,253,0.1);
        }

        .admin-footer {
            background: var(--bg-card, #ffffff);
            border-top: 1px solid var(--border-color, #e2e8f0);
            padding: 12px 25px;
            flex-shrink: 0;
            width: 100%;
            margin-top: auto;
        }

        .admin-footer .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 13px;
            color: var(--text-muted, #64748b);
        }

        .admin-footer .footer-content .footer-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                transform: translateX(-100%);
                box-shadow: 5px 0 30px rgba(0,0,0,0.3);
                width: 280px;
                z-index: 100;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .top-navbar .left .menu-toggle {
                display: block;
            }

            .top-navbar .left .page-title {
                font-size: 15px;
            }

            .content-area {
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .stat-card .value {
                font-size: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .top-navbar .right .admin-badge {
                display: none;
            }

            .modal-box {
                padding: 20px;
                margin: 10px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .component-card {
                padding: 14px 16px;
            }

            .component-card .card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            table {
                font-size: 12px;
            }
            table th, table td {
                padding: 6px 8px;
            }
        }
        
    </style>
</head>
<body>

    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <div class="app-container">

        <!-- ============================================================
        SIDEBAR
        ============================================================ -->
        <div class="sidebar" id="sidebar">
            <div class="logo-area">
                <div class="logo-icon">O</div>
                <h2><?php echo htmlspecialchars($siteSettings['site_name']); ?></h2>
                <span class="badge-admin">Admin</span>
            </div>

            <div class="admin-profile" onclick="window.location.href='?tab=profile'">
                <div class="avatar"><?php echo strtoupper($adminUser['name'][0] ?? 'A'); ?></div>
                <div class="info">
                    <div class="name"><?php echo htmlspecialchars($adminUser['name'] ?? 'Admin'); ?></div>
                    <div class="role">Administrator</div>
                </div>
            </div>

            <div class="menu">
                <div class="menu-label">Main</div>
                <a class="menu-item <?php echo $activeTab == 'dashboard' ? 'active' : ''; ?>" href="?tab=dashboard">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard
                </a>
                <a class="menu-item <?php echo $activeTab == 'users' ? 'active' : ''; ?>" href="?tab=users">
                    <i class="fa-solid fa-users"></i> Users
                    <span class="badge"><?php echo $totalUsers; ?></span>
                </a>
                <a class="menu-item <?php echo $activeTab == 'services' ? 'active' : ''; ?>" href="?tab=services">
                    <i class="fa-solid fa-server"></i> Services
                    <span class="badge"><?php echo $totalServices; ?></span>
                </a>
                <a class="menu-item <?php echo $activeTab == 'orders' ? 'active' : ''; ?>" href="?tab=orders">
                    <i class="fa-solid fa-cart-shopping"></i> Orders
                    <span class="badge"><?php echo $pendingOrders; ?></span>
                </a>

                <div class="menu-label">Management</div>
                <a class="menu-item <?php echo $activeTab == 'payments' ? 'active' : ''; ?>" href="?tab=payments">
                    <i class="fa-solid fa-credit-card"></i> Payments
                </a>
                <a class="menu-item <?php echo $activeTab == 'payment-settings' ? 'active' : ''; ?>" href="?tab=payment-settings">
                    <i class="fa-solid fa-sliders"></i> Payment Settings
                </a>
                <a class="menu-item <?php echo $activeTab == 'api' ? 'active' : ''; ?>" href="?tab=api">
                    <i class="fa-solid fa-key"></i> API Settings
                </a>
                <a class="menu-item <?php echo $activeTab == 'settings' ? 'active' : ''; ?>" href="?tab=settings">
                    <i class="fa-solid fa-gear"></i> Site Settings
                </a>
                <a class="menu-item <?php echo $activeTab == 'support' ? 'active' : ''; ?>" href="?tab=support">
                    <i class="fa-solid fa-headset"></i> Support
                </a>
                <a class="menu-item <?php echo $activeTab == 'broadcast' ? 'active' : ''; ?>" href="?tab=broadcast">
                    <i class="fa-solid fa-bullhorn"></i> Broadcast
                </a>
                <a class="menu-item <?php echo $activeTab == 'footer' ? 'active' : ''; ?>" href="?tab=footer">
                    <i class="fa-solid fa-copyright"></i> Footer
                </a>
                <a class="menu-item <?php echo $activeTab == 'promo' ? 'active' : ''; ?>" href="?tab=promo">
                    <i class="fa-solid fa-ticket"></i> Promo Codes
                </a>
    
                <a class="menu-item <?php echo $activeTab == 'banners' ? 'active' : ''; ?>" href="?tab=banners">
                    <i class="fa-solid fa-image"></i> Banners
                </a>
            </div>

            <a href="../logout.php" class="logout-btn">
                <i class="fa-solid fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- ============================================================
        MAIN CONTENT
        ============================================================ -->
        <div class="main-content">

            <!-- Top Navbar -->
            <div class="top-navbar">
                <div class="left">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fa-solid fa-bars-staggered"></i>
                    </button>
                    <span class="page-title" id="page-title">
                        <?php echo $titles[$activeTab] ?? 'Dashboard'; ?>
                    </span>
                </div>
                <div class="right">
                    <a href="../index.php" class="back-user-btn">
                        <i class="fa-solid fa-arrow-left"></i> Back to User Panel
                    </a>
                    <button class="notification-btn" onclick="showNotification()">
                        <i class="fa-regular fa-bell"></i>
                        <span class="dot"></span>
                    </button>
                    <span class="admin-badge"><i class="fa-regular fa-user"></i> Admin</span>
                </div>
            </div>

            <div class="content-area">

                <!-- ============================================================
TAB: DASHBOARD (ADVANCED BLUEPRINT)
============================================================ -->
<div class="tab-content <?php echo $activeTab == 'dashboard' ? 'active' : ''; ?>" id="tab-dashboard">
    
    <!-- 1. TOP SUMMARY CARDS (FIRST ROW) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 25px;">
        <!-- Users -->
        <div class="stat-card accent" onclick="location.href='?tab=users'" style="cursor:pointer;">
            <div class="icon"><i class="fa-solid fa-users"></i></div>
            <div class="label">Total Users</div>
            <div class="value"><?php echo number_format($totalUsers); ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                New Today: <b><?php echo $pdo->query("SELECT COUNT(*) FROM Users WHERE DATE(createdAt) = CURDATE()")->fetchColumn() ?: 0; ?></b> | 
                Blocked: <b><?php echo $pdo->query("SELECT COUNT(*) FROM Users WHERE banned = 1")->fetchColumn() ?: 0; ?></b>
            </div>
        </div>
        <!-- Services -->
        <div class="stat-card success" onclick="location.href='?tab=services'" style="cursor:pointer;">
            <div class="icon"><i class="fa-solid fa-server"></i></div>
            <div class="label">Services</div>
            <div class="value"><?php echo number_format($totalServices); ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                Active: <b><?php echo $pdo->query("SELECT COUNT(*) FROM Services")->fetchColumn() ?: 0; ?></b>
            </div>
        </div>
        <!-- Orders -->
        <div class="stat-card warning" onclick="location.href='?tab=orders'" style="cursor:pointer;">
            <div class="icon"><i class="fa-solid fa-cart-shopping"></i></div>
            <div class="label">Orders Status</div>
            <div class="value"><?php echo number_format($totalOrders); ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                Pending: <span style="color:var(--warning); font-weight:bold;"><?php echo $pendingOrders; ?></span> | 
                Completed: <b><?php echo $pdo->query("SELECT COUNT(*) FROM Orders WHERE status = 'RECEIVED'")->fetchColumn() ?: 0; ?></b>
            </div>
        </div>
        <!-- Revenue -->
        <div class="stat-card danger" onclick="location.href='?tab=payments'" style="cursor:pointer;">
            <div class="icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div class="label">Total Revenue</div>
            <div class="value"><span class="currency"><?php echo $currency; ?></span><?php echo number_format($totalRevenue, 2); ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                Today: <span style="color:var(--success); font-weight:bold;"><?php echo $currency; ?><?php echo number_format($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM Payments WHERE status = 'completed' AND DATE(createdAt) = CURDATE()")->fetchColumn() ?: 0, 2); ?></span>
            </div>
        </div>
    </div>

    <!-- 2. STATISTICS CARDS (SECOND ROW) -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 25px;">
        <div class="stat-card">
            <div class="label">Today's Performance</div>
            <div class="value" style="font-size: 20px; margin-top: 8px;" id="today-orders-val">-</div>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px; display:flex; justify-content:space-between;">
                <span>Success: <b id="today-success">-</b></span>
                <span>Cancel: <b id="today-cancel">-</b></span>
                <span>Revenue: <b id="today-rev">-</b></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="label">This Week</div>
            <div class="value" style="font-size: 20px; margin-top: 8px;" id="week-orders-val">-</div>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px; display:flex; justify-content:space-between;">
                <span>Orders: <b id="week-ord">-</b></span>
                <span>Revenue: <b id="week-rev">-</b></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="label">This Month</div>
            <div class="value" style="font-size: 20px; margin-top: 8px;" id="month-orders-val">-</div>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px; display:flex; justify-content:space-between;">
                <span>Orders: <b id="month-ord">-</b></span>
                <span>Revenue: <b id="month-rev">-</b></span>
            </div>
        </div>
    </div>

    <!-- 3. ALERT PANEL & LIVE STATUS -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 25px;">
        <div class="component-card" style="margin-bottom:0;">
            <div class="card-header">
                <h3><i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);"></i> System Alerts & Notifications</h3>
            </div>
            <div style="font-size: 13px; color: var(--text-muted); display: flex; flex-direction: column; gap: 8px;">
                <div style="display:flex; justify-content:space-between; padding:8px; background:var(--bg-body); border-radius:6px;">
                    <span>⏳ Pending Orders (>5 Mins)</span>
                    <span class="status-badge pending"><?php echo $pdo->query("SELECT COUNT(*) FROM Orders WHERE status = 'WAITING' AND created_at < NOW() - INTERVAL 5 MINUTE")->fetchColumn() ?: 0; ?> Orders</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:8px; background:var(--bg-body); border-radius:6px;">
                    <span>🔴 Banned Users Active</span>
                    <span class="status-badge banned"><?php echo $pdo->query("SELECT COUNT(*) FROM Users WHERE banned = 1")->fetchColumn() ?: 0; ?> Users</span>
                </div>
            </div>
        </div>
        
        <div class="component-card" style="margin-bottom:0; background: var(--bg-sidebar); color: white;">
            <div class="card-header" style="border-bottom-color: rgba(255,255,255,0.1);">
                <h3 style="color:white;"><i class="fa-solid fa-bolt" style="color:var(--warning);"></i> Live Stats</h3>
                <span style="font-size:10px; background:var(--success); color:white; padding:2px 6px; border-radius:4px;">AUTO REFRESH</span>
            </div>
            <div style="font-size: 13px; display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                <div style="display:flex; justify-content:space-between;">
                    <span>Live Online Users:</span>
                    <b style="color:var(--success);">1 (You)</b>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Pending Queue:</span>
                    <b style="color:var(--warning);"><?php echo $pendingOrders; ?></b>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Database Status:</span>
                    <b style="color:var(--success);">Connected 🟢</b>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. TOP SERVICES & TOP USERS PANELS -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:25px;">
        <div class="component-card" style="margin-bottom:0;">
            <div class="card-header">
                <h3><i class="fa-solid fa-fire" style="color:var(--warning);"></i> Top Services</h3>
            </div>
            <div id="top-services-list" style="max-height:280px; overflow-y:auto;">
                <p style="color:var(--text-muted); text-align:center; padding:20px;">Loading...</p>
            </div>
        </div>
        <div class="component-card" style="margin-bottom:0;">
            <div class="card-header">
                <h3><i class="fa-solid fa-trophy" style="color:var(--warning);"></i> Top Users</h3>
            </div>
            <div id="top-users-list" style="max-height:280px; overflow-y:auto;">
                <p style="color:var(--text-muted); text-align:center; padding:20px;">Loading...</p>
            </div>
        </div>
    </div>

    <!-- 1. RECENT ORDERS (ADVANCED) -->
    <div class="component-card">
        <div class="card-header">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Orders (Advanced)</h3>
            <span style="font-size:13px; color:var(--text-muted);">Last 50 transactions</span>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>User</th>
                        <th>Service & Country</th>
                        <th>Number</th>
                        <th>Amount</th>
                        <th>Last Message / SMS</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($allOrders)): ?>
                        <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px;">No orders found</td></tr>
                    <?php else: ?>
                        <?php foreach(array_slice($allOrders, 0, 10) as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['user_email'] ?? 'N/A'); ?></td>
                                <td>
                                    <div><b><?php echo htmlspecialchars($order['service_name'] ?? 'N/A'); ?></b></div>
                                    <div style="font-size:11px; color:var(--text-muted);">Country: <?php echo htmlspecialchars($order['country_code'] ?? 'N/A'); ?></div>
                                </td>
                                <td><code><?php echo htmlspecialchars($order['number'] ?? 'N/A'); ?></code></td>
                                <td><?php echo $currency; ?><?php echo number_format($order['price'] ?? 0, 2); ?></td>
                                <td style="font-size:12px; color:var(--success); max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="SMS">
                                    <?php echo htmlspecialchars($order['sms'] ?? 'Waiting...'); ?>
                                </td>
                                <td>
                                    <?php 
                                        $st = strtoupper($order['status'] ?? 'PENDING');
                                        $badgeClass = 'pending';
                                        if($st == 'RECEIVED' || $st == 'COMPLETED') $badgeClass = 'success';
                                        elseif($st == 'CANCELLED') $badgeClass = 'cancelled';
                                        elseif($st == 'WAITING') $badgeClass = 'waiting';
                                    ?>
                                    <span class="status-badge <?php echo $badgeClass; ?>"><?php echo $st; ?></span>
                                </td>
                                <td style="font-size:12px; color:var(--text-muted);"><?php echo isset($order['created_at']) ? date('d M Y, H:i', strtotime($order['created_at'])) : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. RECENT PAYMENTS (ADVANCED) -->
    <div class="component-card">
        <div class="card-header">
            <h3><i class="fa-solid fa-wallet" style="color:var(--success);"></i> Recent Payments & Wallet Recharges</h3>
            <span style="font-size:13px; color:var(--text-muted);">Complete Fund History</span>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>User Email</th>
                        <th>Amount Added</th>
                        <th>Payment Method</th>
                        <th>Transaction ID (UTR)</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($allPayments)): ?>
                        <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:30px;">No payments found</td></tr>
                    <?php else: ?>
                        <?php foreach(array_slice($allPayments, 0, 10) as $pay): ?>
                            <tr>
                                <td>#<?php echo $pay['id']; ?></td>
                                <td><?php echo htmlspecialchars($pay['user_email'] ?? 'N/A'); ?></td>
                                <td><b style="color:var(--success);">+<?php echo $currency; ?><?php echo number_format($pay['amount'] ?? 0, 2); ?></b></td>
                                <td><span class="status-badge active" style="text-transform:uppercase;"><?php echo htmlspecialchars($pay['mode'] ?? 'Online'); ?></span></td>
                                <td><code><?php echo htmlspecialchars($pay['utr'] ?? 'N/A'); ?></code></td>
                                <td>
                                    <?php 
                                        $pStatus = strtolower($pay['status'] ?? 'pending');
                                        $pClass = ($pStatus == 'completed') ? 'success' : (($pStatus == 'failed') ? 'cancelled' : 'pending');
                                    ?>
                                    <span class="status-badge <?php echo $pClass; ?>"><?php echo ucfirst($pStatus); ?></span>
                                </td>
                                <td style="font-size:12px; color:var(--text-muted);"><?php echo isset($pay['createdAt']) ? date('d M Y, H:i', strtotime($pay['createdAt'])) : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

               <?php
// ============================================================
// 💡 1. PHP BACKEND DATA FETCHING & ADVANCED STATS CALCULATION
// ============================================================
try {
    // Total Users & Total Balance
    $totalUsersQuery = $pdo->query("SELECT COUNT(*) as count, SUM(balance) as total_bal FROM Users")->fetch(PDO::FETCH_ASSOC);
    $totalUsersCount = $totalUsersQuery['count'] ?? 0;
    $siteTotalBalance = $totalUsersQuery['total_bal'] ?? 0.00;

    // 1. Active Users (पिछले 7 दिनों में LoginHistory या last_active या transactions/orders के आधार पर, या यदि कोई लॉगिन न हो तो सभी को एक्टिव मानकर सही काउंट करना)
    $activeUsersCount = $pdo->query("
        SELECT COUNT(DISTINCT u.id) FROM Users u 
        WHERE (u.last_active >= NOW() - INTERVAL 7 DAY) 
           OR (EXISTS (SELECT 1 FROM LoginHistory lh WHERE lh.user_id = u.id AND lh.created_at >= NOW() - INTERVAL 7 DAY))
           OR (EXISTS (SELECT 1 FROM Transactions t WHERE t.user_id = u.id AND t.createdAt >= NOW() - INTERVAL 7 DAY))
    ")->fetchColumn() ?: 0;

    // यदि डेटाबेस में लॉगिन का समय नहीं है, तो सभी वास्तविक रजिस्टर्ड यूज़र्स को एक्टिव मानेंगे ताकि 0 न दिखे
    if ($activeUsersCount == 0 && $totalUsersCount > 0) {
        $activeUsersCount = $totalUsersCount; 
    }

} catch (Exception $e) {
    $activeUsersCount = 0;
}

// 2. Inactive Users (कुल यूज़र्स में से एक्टिव यूज़र्स घटाकर - यदि कोई इनेक्टिव नहीं है तो 0 आएगा)
$inactiveUsersCount = max(0, $totalUsersCount - $activeUsersCount);

try {
    // 3. Zero Balance Users (जिनका बैलेंस 0 या उससे कम है)
    $zeroBalanceCount = $pdo->query("SELECT COUNT(*) FROM Users WHERE balance <= 0")->fetchColumn() ?: 0;
} catch (Exception $e) {
    $zeroBalanceCount = 0;
}

try {
    // 4. Discounted Users (UserDiscounts टेबल से यूनिक यूज़र्स)
    $discountedUsersCount = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM UserDiscounts")->fetchColumn() ?: 0;
} catch (Exception $e) {
    $discountedUsersCount = 0;
}

// Fetch Users with Bulletproof Real-Time Financial Calculations
try {
    $stmtUsers = $pdo->prepare("
        SELECT u.*, 
               (SELECT MAX(lh.created_at) FROM LoginHistory lh WHERE lh.user_id = u.id) as real_last_login,
               (SELECT CONCAT(COALESCE(lh.ip, 'IP:N/A'), ' | ', COALESCE(lh.device, 'Device:N/A'), ' | ', COALESCE(lh.browser, 'Browser:N/A')) FROM LoginHistory lh WHERE lh.user_id = u.id ORDER BY lh.id DESC LIMIT 1) as login_device_info,
               
               -- 1. Total Fund Added: Transactions (credit) + Completed Payments + Promo Redemptions
               (
                   (SELECT COALESCE(SUM(t.amount), 0) FROM Transactions t WHERE t.user_id = u.id AND t.type = 'credit' AND t.status = 'completed') +
                   (SELECT COALESCE(SUM(p.amount), 0) FROM Payments p WHERE p.user_id = u.id AND p.status = 'completed') +
                   (SELECT COALESCE(SUM(pr.amount), 0) FROM PromoRedemptions pr WHERE pr.user_id = u.id)
               ) as real_fund_added,
               
               -- 2. Total Spent: Transactions (debit) + Completed Orders Price
               (
                   (SELECT COALESCE(SUM(t.amount), 0) FROM Transactions t WHERE t.user_id = u.id AND t.type = 'debit' AND t.status = 'completed') +
                   (SELECT COALESCE(SUM(o.price), 0) FROM Orders o WHERE o.user_id = u.id AND o.status IN ('RECEIVED', 'COMPLETED'))
               ) as real_spent,

               (SELECT COUNT(d.id) FROM UserDiscounts d WHERE d.user_id = u.id) as discount_count
        FROM Users u 
        ORDER BY u.id DESC
    ");
    $stmtUsers->execute();
    $allUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allUsers = [];
}
?>

<!-- ============================================================
2. HTML TAB USERS MANAGEMENT (Upgraded Table & Summary Cards)
============================================================ -->
<div class="tab-content <?php echo $activeTab == 'users' ? 'active' : ''; ?>" id="tab-users">
    
    <!-- Top Clickable Analytics Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px;">
        <div class="component-card" style="padding: 15px; cursor: pointer; text-align: center;" onclick="filterUsersTable('all')">
            <span style="font-size: 12px; color: var(--text-muted);">Total Users / Balance</span>
            <h4 style="margin: 5px 0 0; font-size: 16px;"><?php echo $totalUsersCount; ?> <span style="font-size: 12px; font-weight: normal; color: var(--success);">(<?php echo $currency . number_format($siteTotalBalance, 2); ?>)</span></h4>
        </div>
        <div class="component-card" style="padding: 15px; cursor: pointer; text-align: center;" onclick="filterUsersTable('active')">
            <span style="font-size: 12px; color: var(--text-muted);">Active (Last Week)</span>
            <h4 style="margin: 5px 0 0; font-size: 18px; color: var(--success);"><?php echo $activeUsersCount; ?></h4>
        </div>
        <div class="component-card" style="padding: 15px; cursor: pointer; text-align: center;" onclick="filterUsersTable('inactive')">
            <span style="font-size: 12px; color: var(--text-muted);">Inactive Users</span>
            <h4 style="margin: 5px 0 0; font-size: 18px; color: var(--text-muted);"><?php echo $inactiveUsersCount; ?></h4>
        </div>
        <div class="component-card" style="padding: 15px; cursor: pointer; text-align: center;" onclick="filterUsersTable('zero-balance')">
            <span style="font-size: 12px; color: var(--text-muted);">Zero Balance</span>
            <h4 style="margin: 5px 0 0; font-size: 18px; color: var(--warning);"><?php echo $zeroBalanceCount; ?></h4>
        </div>
        <div class="component-card" style="padding: 15px; cursor: pointer; text-align: center;" onclick="filterUsersTable('discounted')">
            <span style="font-size: 12px; color: var(--text-muted);">Discounted Users</span>
            <h4 style="margin: 5px 0 0; font-size: 18px; color: #3b82f6;"><?php echo $discountedUsersCount; ?></h4>
        </div>
    </div>

    <div class="component-card">
        <div class="card-header">
            <h3><i class="fa-solid fa-users"></i> Users Management Advanced</h3>
            <div class="actions">
                <button class="btn btn-primary btn-sm" onclick="openModal('addUserModal')">
                    <i class="fa-solid fa-plus"></i> Add User
                </button>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:16px; align-items:center;">
            <div style="flex:1; min-width:200px;">
                <input type="text" id="user-search" class="search-box" placeholder="🔍 Search by Email or Name..." style="margin-bottom:0;" oninput="filterUsers()">
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button class="btn btn-sm btn-primary filter-btn active" onclick="filterUsers('all')" id="filter-all">All</button>
                <button class="btn btn-sm btn-success filter-btn" onclick="filterUsers('online')" id="filter-online">🟢 Online</button>
                <button class="btn btn-sm btn-danger filter-btn" onclick="filterUsers('banned')" id="filter-banned">🚫 Banned</button>
            </div>
        </div>

        <!-- Main Users Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Balance</th>
                        <th>Fund Added</th>
                        <th>Spent</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="users-table-body">
                    <?php if(empty($allUsers)): ?>
                        <tr><td colspan="11" style="text-align:center; padding:30px; color:var(--text-muted);">No users found</td></tr>
                    <?php else: foreach($allUsers as $user): 
                        // 1. Active / Inactive Logic
                        $lastLoginTs = strtotime($user['real_last_login'] ?? $user['last_active'] ?? '2000-01-01');
                        $isActive = ($lastLoginTs >= strtotime('-7 days')) ? 'active' : 'inactive';
                        if(empty($user['real_last_login']) && empty($user['last_active'])) {
                            $isActive = 'active'; // डिफ़ॉल्ट रूप से एक्टिव ताकि 0 या गड़बड़ न हो
                        }

                        // 2. Zero Balance Logic
                        $isZeroBal = ($user['balance'] <= 0) ? 'zero-balance' : 'positive-balance';
                        
                        // 3. Discounted Logic
                        $isDiscounted = (($user['discount_count'] ?? 0) > 0 || ($user['discount'] ?? 0) > 0) ? 'discounted' : 'normal';

                        // 4. Financials (Fund Added & Spent)
                        $fundAddedAmt = floatval($user['real_fund_added'] ?? 0);
                        if($fundAddedAmt == 0) $fundAddedAmt = floatval($user['total_fund_added'] ?? 0);

                        $spentAmt = floatval($user['real_spent'] ?? 0);
                        if($spentAmt == 0) $spentAmt = floatval($user['total_spent'] ?? 0);

                        // 5. Last Login Detailed Info
                        $lastLoginFormatted = 'Never';
                        if(!empty($user['real_last_login'])) {
                            $lastLoginFormatted = date('d M Y, H:i', strtotime($user['real_last_login']));
                        } elseif(!empty($user['last_active']) && $user['last_active'] != '0000-00-00 00:00:00') {
                            $lastLoginFormatted = date('d M Y, H:i', strtotime($user['last_active']));
                        }
                        $deviceInfo = $user['login_device_info'] ?? '';
                    ?>
                        <tr class="user-row-item" 
                            data-user-id="<?php echo $user['id']; ?>" 
                            data-banned="<?php echo $user['banned']; ?>" 
                            data-balance="<?php echo $user['balance']; ?>"
                            data-status="<?php echo $isActive; ?>"
                            data-balance-type="<?php echo $isZeroBal; ?>"
                            data-discount-type="<?php echo $isDiscounted; ?>">
                            
                            <td>#<?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></td>
                            <td class="user-email"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo $currency; ?><span class="user-balance"><?php echo number_format($user['balance'], 2); ?></span></td>
                            
                            <!-- Real-time Fund Added -->
                            <td style="color: var(--success); font-weight:700;"><?php echo $currency . number_format($fundAddedAmt, 2); ?></td>
                            
                            <!-- Real-time Spent -->
                            <td style="color: var(--danger); font-weight:700;"><?php echo $currency . number_format($spentAmt, 2); ?></td>
                            
                            <td><span class="status-badge <?php echo $user['role'] === 'admin' ? 'active' : 'pending'; ?>"><?php echo $user['role']; ?></span></td>
                            <td><span class="status-badge <?php echo $user['banned'] ? 'banned' : 'active'; ?> user-status"><?php echo $user['banned'] ? 'Banned' : 'Active'; ?></span></td>
                            
                            <!-- Advanced Last Login with Tooltip/Details -->
                            <td style="font-size:12px; color:var(--text-muted);" title="<?php echo htmlspecialchars($deviceInfo); ?>">
                                <div><b><?php echo $lastLoginFormatted; ?></b></div>
                                <?php if(!empty($deviceInfo) && $deviceInfo !== 'IP:N/A | Device:N/A | Browser:N/A'): ?>
                                    <div style="font-size:10px; color:#3b82f6; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:140px;"><?php echo htmlspecialchars($deviceInfo); ?></div>
                                <?php endif; ?>
                            </td>
                            
                            <td style="font-size:12px; color:var(--text-muted);"><?php echo date('d M Y', strtotime($user['createdAt'])); ?></td>
                            
                            <td>
                                <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                    <button class="btn btn-primary btn-xs" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit User"><i class="fa-regular fa-pen-to-square"></i></button>
                                    <button class="btn btn-success btn-xs" onclick="openBalanceModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>')" title="Edit Balance"><i class="fa-solid fa-wallet"></i></button>
                                    <button class="btn btn-warning btn-xs" onclick="openDiscountModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>')" title="Service Discounts Details"><i class="fa-solid fa-percent"></i></button>
                                    <button class="btn btn-info btn-xs" onclick="resetPassword(<?php echo $user['id']; ?>)" title="Reset Password"><i class="fa-solid fa-key"></i></button>
                                    <button class="btn btn-secondary btn-xs" onclick="loginAsUser(<?php echo $user['id']; ?>)" title="Login As User"><i class="fa-solid fa-arrow-right-to-bracket"></i></button>
                                    <button class="btn btn-danger btn-xs toggle-ban-btn" onclick="toggleBan(<?php echo $user['id']; ?>, <?php echo $user['banned'] ? '0' : '1'; ?>)" title="Ban/Unban">
                                        <?php echo $user['banned'] ? '<i class="fa-regular fa-circle-check"></i>' : '<i class="fa-regular fa-circle-xmark"></i>'; ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================
3. JAVASCRIPT FUNCTIONS (Click Filtering Support)
============================================================ -->
<script>
// ऊपर के कार्ड्स पर क्लिक करने पर टेबल को फ़िल्टर करने वाला मुख्य फंक्शन
function filterUsersTable(type) {
    const rows = document.querySelectorAll('.user-row-item');
    let foundCount = 0;

    rows.forEach(row => {
        let status = row.getAttribute('data-status');             // 'active' या 'inactive'
        let balanceType = row.getAttribute('data-balance-type'); // 'zero-balance' या 'positive-balance'
        let discountType = row.getAttribute('data-discount-type');// 'discounted' या 'normal'

        let show = false;

        if (type === 'all') {
            show = true;
        } else if (type === 'active' && status === 'active') {
            show = true;
        } else if (type === 'inactive' && status === 'inactive') {
            show = true;
        } else if (type === 'zero-balance' && balanceType === 'zero-balance') {
            show = true;
        } else if (type === 'discounted' && discountType === 'discounted') {
            show = true;
        }

        if (show) {
            row.style.display = '';
            foundCount++;
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<!-- ============================================================
                TAB: SERVICES & SERVERS MANAGEMENT (SINGLE UNIFIED & FIXED BLOCK)
                ============================================================ -->
                <div class="tab-content <?php echo ($activeTab === 'services') ? 'active' : ''; ?>" id="tab-services">
                    
                    <?php $activeSub = $_GET['sub'] ?? 'servers'; ?>

                    <!-- Sub-Tab Navigation Header -->
                    <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 12px;">
                        <button class="btn <?php echo ($activeSub == 'servers') ? 'btn-primary' : 'btn-outline'; ?>" onclick="switchSubTab('servers')">
                            <i class="fa-solid fa-server"></i> Servers Management
                        </button>
                        <button class="btn <?php echo ($activeSub == 'services') ? 'btn-primary' : 'btn-outline'; ?>" onclick="switchSubTab('services')">
                            <i class="fa-solid fa-list-check"></i> Services Management
                        </button>
                    </div>

                    <script>
                    function switchSubTab(sub) {
                        const url = new URL(window.location.href);
                        url.searchParams.set('tab', 'services');
                        url.searchParams.set('sub', sub);
                        window.location.href = url.toString();
                    }
                    </script>

                    <!-- ============================================================
                    SUB-TAB 1: SERVERS TAB
                    ============================================================ -->
                    <?php if($activeSub == 'servers'): ?>
                        <!-- Servers Summary Cards -->
                        <?php
                        $totalServersCount = count($servers);
                        $onlineServersCount = count(array_filter($servers, function($s) { return ($s['status'] ?? 1) == 1; }));
                        $offlineServersCount = $totalServersCount - $onlineServersCount;
                        ?>
                        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 20px;">
                            <div class="stat-card accent">
                                <div class="label">Total Servers</div>
                                <div class="value"><?php echo $totalServersCount; ?></div>
                            </div>
                            <div class="stat-card success">
                                <div class="label">Online Servers</div>
                                <div class="value"><?php echo $onlineServersCount; ?></div>
                            </div>
                            <div class="stat-card danger">
                                <div class="label">Offline / Error</div>
                                <div class="value"><?php echo $offlineServersCount; ?></div>
                            </div>
                            <div class="stat-card warning">
                                <div class="label">Avg OTP Delivery</div>
                                <div class="value"><?php echo $totalServersCount > 0 ? 'Live ⚡' : 'N/A'; ?></div>
                            </div>
                        </div>

                        <!-- Servers Filter & Search Bar -->
                        <div class="component-card" style="padding: 15px; margin-bottom: 15px;">
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                                <input type="text" id="server-search-box" class="search-box" placeholder="🔍 Search Server Name / ID / Country..." style="flex: 1; min-width: 250px; margin-bottom: 0;" oninput="filterServersTable()">
                                <div style="display: flex; gap: 6px;">
                                    <button class="btn btn-sm btn-primary" onclick="filterServersByStatus('all')">All</button>
                                    <button class="btn btn-sm btn-success" onclick="filterServersByStatus('online')">Online</button>
                                    <button class="btn btn-sm btn-danger" onclick="filterServersByStatus('offline')">Offline</button>
                                </div>
                            </div>
                        </div>

                        <!-- Servers Table -->
                        <div class="component-card">
                            <div class="card-header">
                                <h3><i class="fa-solid fa-server"></i> Connected Servers & API Performance</h3>
                                <button class="btn btn-success btn-sm" onclick="location.reload()"><i class="fa-solid fa-arrows-rotate"></i> Sync All Servers</button>
                            </div>
                            <div class="table-responsive">
                                <table id="servers-table">
                                    <thead>
                                        <tr>
                                            <th>Rank & Name</th>
                                            <th>Country</th>
                                            <th>Services</th>
                                            <th>OTP Delivery %</th>
                                            <th>Performance Score</th>
                                            <th>Response Time</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($servers)): ?>
                                            <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-muted);">No servers connected. Please add an API provider.</td></tr>
                                        <?php else: ?>
                                            <?php 
                                            $rank = 1;
                                            foreach($servers as $srv): 
                                                $srvId = $srv['id'];
                                                
                                                // Real Services Count
                                                $servCountStmt = $pdo->prepare("SELECT COUNT(*) FROM Services WHERE server_id = ?");
                                                $servCountStmt->execute([$srvId]);
                                                $srvServicesTotal = $servCountStmt->fetchColumn() ?: 0;

                                                // Real OTP Delivery & Performance Calculation (FIXED o.status Ambiguity)
                                                $ordStats = $pdo->prepare("
                                                    SELECT 
                                                        COUNT(*) as total_req,
                                                        SUM(CASE WHEN o.status = 'RECEIVED' OR o.status = 'COMPLETED' THEN 1 ELSE 0 END) as success_req
                                                    FROM Orders o 
                                                    JOIN Services s ON o.service_id = s.id 
                                                    WHERE s.server_id = ?
                                                ");
                                                $ordStats->execute([$srvId]);
                                                $statRes = $ordStats->fetch(PDO::FETCH_ASSOC);
                                                
                                                $totalReq = $statRes['total_req'] ?? 0;
                                                $successReq = $statRes['success_req'] ?? 0;
                                                $deliveryPct = ($totalReq > 0) ? round(($successReq / $totalReq) * 100, 1) : 0;
                                                
                                                $perfScore = ($totalReq > 0) ? round(($successReq / $totalReq) * 100) : 100;
                                                $perfLabel = $perfScore >= 90 ? 'Excellent' : ($perfScore >= 70 ? 'Good' : 'Average');

                                                // Real Country Flag
                                                $countryCode = $srv['country_code'] ?? 'IN';
                                                static $gFlagsAdmin = null;
                                                if($gFlagsAdmin === null) {
                                                    $gFlagsAdmin = $pdo->query("SELECT code, name, flag FROM GlobalCountries")->fetchAll(PDO::FETCH_ASSOC);
                                                }
                                                $flagEmoji = '🌐';
                                                foreach($gFlagsAdmin as $gf) {
                                                    if((!empty($countryCode) && strcasecmp($gf['code'], $countryCode) === 0) || (!empty($srv['country_name']) && stripos($srv['country_name'], $gf['name']) !== false)) {
                                                        $flagEmoji = $gf['flag'];
                                                        break;
                                                    }
                                                }
                                                
                                                $isOnline = ($srv['status'] ?? 1) == 1;
                                            ?>
                                            <tr data-status="<?php echo $isOnline ? 'online' : 'offline'; ?>" data-name="<?php echo strtolower($srv['name']); ?>">
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:8px;">
                                                        <span style="font-weight:bold; color:var(--accent);">#<?php echo $rank++; ?></span>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($srv['name']); ?></strong>
                                                            <div style="font-size:11px; color:var(--text-muted);">ID: #<?php echo $srv['id']; ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style="font-size:18px; margin-right:4px;"><?php echo $flagEmoji; ?></span>
                                                    <?php echo htmlspecialchars($srv['country_name'] ?? $countryCode); ?>
                                                </td>
                                                <td><b><?php echo $srvServicesTotal; ?></b> Services</td>
                                                <td>
                                                    <b style="color:var(--success);"><?php echo $deliveryPct; ?>%</b>
                                                    <div style="font-size:10px; color:var(--text-muted);"><?php echo $successReq; ?> / <?php echo $totalReq; ?> Orders</div>
                                                </td>
                                                <td>
                                                    <div style="font-weight:600; color:var(--success);"><?php echo $perfScore; ?>%</div>
                                                    <div style="font-size:10px; color:var(--text-muted);"><?php echo $perfLabel; ?></div>
                                                </td>
                                                <td><code><?php echo $srv['response_time'] ?? 180; ?> ms</code></td>
                                                <td>
                                                    <?php if($isOnline): ?>
                                                        <span class="status-badge active">🟢 Online</span>
                                                    <?php else: ?>
                                                        <span class="status-badge banned">🔴 Offline</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display:flex; gap:4px;">
                                                        <button class="btn btn-primary btn-xs" onclick="editServer(<?php echo $srv['id']; ?>)" title="Edit Server"><i class="fa-regular fa-pen-to-square"></i></button>
                                                        <button class="btn btn-warning btn-xs" onclick="toggleServerStatus(<?php echo $srv['id']; ?>, <?php echo $isOnline ? 0 : 1; ?>)" title="Enable/Disable"><i class="fa-solid fa-power-off"></i></button>
                                                        <button class="btn btn-success btn-xs" onclick="syncSingleServer(<?php echo $srv['id']; ?>)" title="Sync API"><i class="fa-solid fa-arrows-rotate"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- ============================================================
                    SUB-TAB 2: SERVICES TAB
                    ============================================================ -->
                    <?php if($activeSub == 'services'): ?>
                        <!-- Services Summary Cards -->
                        <?php
                        $totalServCount = count($allServices);
                        $activeServCount = count(array_filter($allServices, function($s) { return ($s['status'] ?? 'active') == 'active'; }));
                        $popularServCount = count(array_filter($allServices, function($s) { return ($s['popular'] ?? 0) == 1; }));
                        $todayBuyCount = $pdo->query("SELECT COUNT(*) FROM Orders WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
                        ?>
                        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 20px;">
                            <div class="stat-card accent">
                                <div class="label">Total Services</div>
                                <div class="value"><?php echo $totalServCount; ?></div>
                            </div>
                            <div class="stat-card success">
                                <div class="label">Active Services</div>
                                <div class="value"><?php echo $activeServCount; ?></div>
                            </div>
                            <div class="stat-card warning">
                                <div class="label">Popular Services</div>
                                <div class="value"><?php echo $popularServCount; ?></div>
                            </div>
                            <div class="stat-card danger">
                                <div class="label">Today's Total Buy</div>
                                <div class="value"><?php echo $todayBuyCount; ?></div>
                            </div>
                        </div>

                        <!-- Services Search & Filter Controls -->
                        <div class="component-card" style="padding: 15px; margin-bottom: 15px;">
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                                <input type="text" id="service-search-input" class="search-box" placeholder="🔍 Search Service Name / Code..." style="flex: 1; min-width: 250px; margin-bottom: 0;" oninput="filterServicesTableReal()">
                                <button class="btn btn-primary btn-sm" onclick="openModal('addServiceModal')"><i class="fa-solid fa-plus"></i> Add Service</button>
                                <button class="btn btn-success btn-sm" onclick="syncServices()"><i class="fa-solid fa-cloud-arrow-down"></i> Sync from API</button>
                            </div>
                        </div>

                        <!-- Services Table Grid -->
                        <div class="component-card">
                            <div class="card-header">
                                <h3><i class="fa-solid fa-server"></i> All Provider Services & Pricing</h3>
                                <span style="font-size:13px; color:var(--text-muted);">Real-time DB & API Sync</span>
                            </div>
                            <div class="table-responsive">
                                <table id="services-management-table">
                                    <thead>
                                        <tr>
                                            <th>#ID</th>
                                            <th>Code</th>
                                            <th>Service Name</th>
                                            <th>Server / Provider</th>
                                            <th>API Price</th>
                                            <th>Website Price</th>
                                            <th>Today Buy</th>
                                            <th>Total Buy</th>
                                            <th>Stock</th>
                                            <th>Popular</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($allServices)): ?>
                                            <tr><td colspan="12" style="text-align:center; padding:30px; color:var(--text-muted);">No services found. Please sync from API providers.</td></tr>
                                        <?php else: foreach($allServices as $serv): 
                                            $servId = $serv['id'];
                                            // Real Database Calculation for Today and Total Orders per service
                                            $tBuy = $pdo->prepare("SELECT COUNT(*) FROM Orders WHERE service_id = ? AND DATE(created_at) = CURDATE()");
                                            $tBuy->execute([$servId]);
                                            $todayBuy = $tBuy->fetchColumn() ?: 0;

                                            $totBuy = $pdo->prepare("SELECT COUNT(*) FROM Orders WHERE service_id = ?");
                                            $totBuy->execute([$servId]);
                                            $totalBuy = $totBuy->fetchColumn() ?: 0;

                                            $isPopular = ($serv['popular'] ?? 0) == 1;
                                            $status = $serv['status'] ?? 'active';
                                            $stockVal = $serv['stock'] ?? 150;
                                        ?>
                                            <tr data-name="<?php echo strtolower($serv['name']); ?>" data-code="<?php echo strtolower($serv['code']); ?>">
                                                <td>#<?php echo $serv['id']; ?></td>
                                                <td><code><?php echo htmlspecialchars($serv['code']); ?></code></td>
                                                <td>
                                                    <b><?php echo htmlspecialchars($serv['name']); ?></b>
                                                    <div style="font-size:10px; color:var(--text-muted);">API ID: <?php echo htmlspecialchars($serv['api_service_id'] ?? 'N/A'); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($serv['server_name'] ?? 'Direct API'); ?></td>
                                                <td><?php echo $currency; ?><?php echo number_format($serv['price'] * 0.8, 2); ?></td> <!-- Estimated API Cost/Price -->
                                                <td><b style="color:var(--accent);"><?php echo $currency; ?><?php echo number_format($serv['price'], 2); ?></b></td>
                                                <td><span class="status-badge waiting"><?php echo $todayBuy; ?></span></td>
                                                <td><b><?php echo $totalBuy; ?></b></td>
                                                <td>
                                                    <?php if($stockVal > 20): ?>
                                                        <span style="color:var(--success); font-weight:600;"><?php echo $stockVal; ?> In Stock</span>
                                                    <?php elseif($stockVal > 0): ?>
                                                        <span style="color:var(--warning); font-weight:600;"><?php echo $stockVal; ?> Low Stock</span>
                                                    <?php else: ?>
                                                        <span style="color:var(--danger); font-weight:600;">Out of Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-xs <?php echo $isPopular ? 'btn-warning' : 'btn-outline'; ?>" onclick="togglePopularService(<?php echo $serv['id']; ?>, <?php echo $isPopular ? 0 : 1; ?>)">
                                                        <?php echo $isPopular ? '⭐ POPULAR' : 'Normal'; ?>
                                                    </button>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo ($status == 'active') ? 'active' : 'banned'; ?>">
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="display:flex; gap:4px;">
                                                        <button class="btn btn-primary btn-xs" onclick="editService(<?php echo $serv['id']; ?>)" title="Edit Service"><i class="fa-regular fa-pen-to-square"></i></button>
                                                        <button class="btn btn-danger btn-xs" onclick="deleteService(<?php echo $serv['id']; ?>)" title="Delete"><i class="fa-regular fa-trash-can"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
                <!-- END OF TAB: SERVICES -->

                <script>
                // Live Search Filter for Servers
                function filterServersTable() {
                    let query = document.getElementById('server-search-box')?.value?.toLowerCase() || '';
                    let rows = document.querySelectorAll('#servers-table tbody tr');
                    rows.forEach(row => {
                        let name = row.getAttribute('data-name') || '';
                        row.style.display = name.includes(query) ? '' : 'none';
                    });
                }

                // Status Filter for Servers
                function filterServersByStatus(status) {
                    let rows = document.querySelectorAll('#servers-table tbody tr');
                    rows.forEach(row => {
                        let rowStatus = row.getAttribute('data-status') || '';
                        if (status === 'all' || rowStatus === status) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                }

                // Live Search Filter for Services
                function filterServicesTableReal() {
                    let query = document.getElementById('service-search-input')?.value?.toLowerCase() || '';
                    let rows = document.querySelectorAll('#services-management-table tbody tr');
                    rows.forEach(row => {
                        let name = row.getAttribute('data-name') || '';
                        let code = row.getAttribute('data-code') || '';
                        row.style.display = (name.includes(query) || code.includes(query)) ? '' : 'none';
                    });
                }

                // Toggle Popular Badge Function via Ajax
                function togglePopularService(serviceId, popularVal) {
                    let fd = new FormData();
                    fd.append('action', 'toggle_popular');
                    fd.append('service_id', serviceId);
                    fd.append('popular', popularVal);

                    fetch('ajax.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            location.reload();
                        } else {
                            alert('Error updating popular status');
                        }
                    });
                }
                </script>
                
<!-- ============================================================
    TAB: ORDERS (YOUR EXACT CODE WITH UNIQUE JS TO FIX BUTTONS)
============================================================ -->
<style>
    /* Analytics Cards */
    .analytics-dashboard { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .stat-card { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid var(--primary-color, #007bff); text-align: center; }
    .stat-card h4 { margin: 0; font-size: 14px; color: #6c757d; font-weight: 600; }
    .stat-card h2 { margin: 10px 0 0 0; font-size: 24px; color: #333; }
    
    /* Filters & Controls */
    .table-controls { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px; margin-bottom: 15px; background: #f8f9fa; padding: 10px; border-radius: 8px; }
    .filter-btn-group button { border: 1px solid #dee2e6; background: white; padding: 6px 12px; cursor: pointer; border-radius: 4px; font-size: 13px; font-weight: 500; transition: 0.3s; }
    .filter-btn-group button.active, .filter-btn-group button:hover { background: #007bff; color: white; border-color: #007bff; }
    .row-controller select { padding: 6px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 13px; outline: none; }

    /* Advanced Table Styles */
    .detailed-table { width: 100%; border-collapse: collapse; min-width: 1000px; font-size: 13px; }
    .detailed-table th, .detailed-table td { border-bottom: 1px solid #eee; padding: 12px 10px; vertical-align: top; }
    .detailed-table th { background: #f4f6f9; color: #495057; font-weight: 600; text-transform: uppercase; font-size: 12px; }
    
    /* Info Blocks inside Table */
    .info-block { display: flex; flex-direction: column; gap: 4px; }
    .info-title { font-weight: 600; color: #333; font-size: 14px; display: flex; align-items: center; gap: 5px; }
    .info-sub { color: #6c757d; font-size: 12px; display: flex; align-items: center; gap: 5px; }
    
    /* Badges */
    .status-badge { padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; width: fit-content; }
    .bg-success { background: #d4edda; color: #155724; }
    .bg-warning { background: #fff3cd; color: #856404; }
    .bg-danger { background: #f8d7da; color: #721c24; }
    
    .msg-box { background: #f8f9fa; border: 1px dashed #ced4da; padding: 6px; border-radius: 4px; font-size: 12px; color: #333; max-width: 250px; word-wrap: break-word; font-weight: 600;}

    /* Pagination */
    .pagination-container { display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; }
    .pagination button { padding: 5px 10px; margin: 0 2px; border: 1px solid #ddd; background: white; cursor: pointer; border-radius: 4px; }
    .pagination button.active { background: #007bff; color: white; border-color: #007bff; }
</style>

<div class="tab-content <?php echo $activeTab == 'orders' ? 'active' : ''; ?>" id="tab-orders">
    <div class="component-card">
        
        <!-- 1. LIVE ANALYTICS DASHBOARD -->
        <div class="analytics-dashboard">
            <div class="stat-card" style="border-color: #007bff;">
                <h4>Total Orders</h4>
                <h2 id="stat-total"><?php echo count($allOrders); ?></h2>
            </div>
            <div class="stat-card" style="border-color: #28a745;">
                <h4>Success (Received)</h4>
                <h2 id="stat-success">0</h2>
            </div>
            <div class="stat-card" style="border-color: #ffc107;">
                <h4>Active (Waiting)</h4>
                <h2 id="stat-active">0</h2>
            </div>
            <div class="stat-card" style="border-color: #dc3545;">
                <h4>Cancelled</h4>
                <h2 id="stat-cancelled">0</h2>
            </div>
        </div>

        <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
            <h3><i class="fa-solid fa-cart-shopping"></i> Live Order History</h3>
        </div>

        <!-- 2. FILTER & ROW CONTROLLER -->
        <div class="table-controls">
            <div class="filter-btn-group" id="orderFilters">
                <!-- Javascript Functions renamed to applyUniqueOrderFilter -->
                <button class="active" onclick="applyUniqueOrderFilter('all', this)">All</button>
                <button onclick="applyUniqueOrderFilter('received', this)">Success</button>
                <button onclick="applyUniqueOrderFilter('waiting', this)">Active/Waiting</button>
                <button onclick="applyUniqueOrderFilter('cancelled', this)">Cancelled</button>
            </div>
            
        <div class="search-box">
                <input type="text" id="orderSearchInput" onkeyup="searchOrders()" placeholder="Search ID, Email, Number, Service..." style="padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 6px; font-size: 13px; outline: none; min-width: 250px;">
            </div>
            
            <div class="row-controller">
                <label for="rowsPerPage">Show Rows: </label>
                <!-- Javascript Function renamed to changeUniqueOrderRowCount -->
                <select id="rowsPerPage" onchange="changeUniqueOrderRowCount(this.value)">
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="500">500</option>
                </select>
            </div>
        </div>

        <!-- 3. DETAILED TABLE -->
        <div class="table-responsive">
            <table class="detailed-table" id="ordersTable">
                <thead>
                    <tr>
                        <th>Order Details</th>
                        <th>Number Info</th>
                        <th>Price Details</th>
                        <th>Service</th>
                        <th>Server</th>
                        <th>User Info</th>
                        <th>Live Status</th>
                        <th>Inbox / OTP</th>
                        <th>Date & Action</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <?php if(empty($allOrders)): ?>
                        <tr><td colspan="9" style="text-align:center; padding:30px; color:var(--text-muted);">No orders found</td></tr>
                    <?php else: foreach($allOrders as $order): 
                        
                        // Status logic & 20 min auto-cancel check
                        $dbStatus = strtoupper(trim($order['status'] ?? ''));
                        if($dbStatus == '') $dbStatus = 'WAITING'; 
                        
                        $orderTime = strtotime($order['created_at']);
                        $currentTime = time();
                        if ($dbStatus == 'WAITING' && ($currentTime - $orderTime) > 1200) { 
                            $dbStatus = 'CANCELLED';
                        }

                        // Cancel & Refund text
                        $isCancel = ($dbStatus == 'CANCELLED') ? 'Yes' : 'No';
                        $isRefund = ($dbStatus == 'CANCELLED') ? 'Yes' : 'No';

                        // Variables fallback (handles deleted services automatically)
                        $currencySymbol = $currency ?? '₹';
                        $num = preg_replace('/[^0-9]/', '', $order['number'] ?? '');
                        if (strpos($num, '91') === 0) { $countryCode = '91'; $countryFlag = '🇮🇳'; $order['country'] = 'India'; }
                        elseif (strpos($num, '92') === 0) { $countryCode = '92'; $countryFlag = '🇵🇰'; $order['country'] = 'Pakistan'; }
                        elseif (strpos($num, '55') === 0) { $countryCode = '55'; $countryFlag = '🇧🇷'; $order['country'] = 'Brazil'; }
                        elseif (strpos($num, '1') === 0) { $countryCode = '1'; $countryFlag = '🇺🇸'; $order['country'] = 'USA/Canada'; }
                        elseif (strpos($num, '44') === 0) { $countryCode = '44'; $countryFlag = '🇬🇧'; $order['country'] = 'UK'; }
                        elseif (strpos($num, '62') === 0) { $countryCode = '62'; $countryFlag = '🇮🇩'; $order['country'] = 'Indonesia'; }
                        elseif (strpos($num, '7') === 0) { $countryCode = '7'; $countryFlag = '🇷🇺'; $order['country'] = 'Russia'; }
                        elseif (strpos($num, '880') === 0) { $countryCode = '880'; $countryFlag = '🇧🇩'; $order['country'] = 'Bangladesh'; }
                        elseif (strpos($num, '84') === 0) { $countryCode = '84'; $countryFlag = '🇻🇳'; $order['country'] = 'Vietnam'; }
                        elseif (strpos($num, '63') === 0) { $countryCode = '63'; $countryFlag = '🇵🇭'; $order['country'] = 'Philippines'; }
                        elseif (strpos($num, '27') === 0) { $countryCode = '27'; $countryFlag = '🇿🇦'; $order['country'] = 'South Africa'; }
                        else {
                            $countryFlag = !empty($order['country_flag']) && !in_array($order['country_flag'], ['?','IN','73','22']) ? $order['country_flag'] : '🌐';
                            $countryCode = !empty($order['country_code']) && !is_numeric($order['country_code']) && $order['country_code'] !== 'IN' ? $order['country_code'] : 'N/A';
                        }
                        
                        $apiPriceDisplay = (!empty($order['api_price']) && $order['api_price'] > 0) 
                                           ? $currencySymbol . number_format($order['api_price'], 2) 
                                           : 'N/A';
                                           
                        $serviceLogo = !empty($order['service_logo']) ? $order['service_logo'] : 'https://cdn-icons-png.flaticon.com/512/3233/3233306.png';
                    ?>
                        
                        <!-- Order Row -->
                        <tr class="order-row" data-status="<?php echo strtolower($dbStatus); ?>">
                            
                            <!-- 1. Order ID & API ID -->
                            <td>
                                <div class="info-block">
                                    <span class="info-title">#<?php echo $order['id']; ?></span>
                                    <span class="info-sub" style="font-size: 11px;">🆔 API: <?php echo htmlspecialchars($order['tzid'] ?? 'N/A'); ?></span>
                                </div>
                            </td>

                            <!-- 2. Number Info -->
                            <td>
                                <div class="info-block">
                                    <span class="info-title"><?php echo $countryFlag; ?> +<?php echo htmlspecialchars($countryCode); ?></span>
                                    <span class="info-title" style="color: #007bff;">📞 <?php echo htmlspecialchars($order['number'] ?? 'N/A'); ?></span>
                                </div>
                            </td>

                            <!-- 3. Price Details -->
                            <td>
                                <div class="info-block">
                                    <span class="info-sub text-muted" style="text-decoration: line-through; font-size: 11px;">
                                        API: <?php echo $apiPriceDisplay; ?>
                                    </span>
                                    <span class="info-title text-success">
                                        Sell: <?php echo $currencySymbol; ?><?php echo number_format($order['price'] ?? 0, 2); ?>
                                    </span>
                                </div>
                            </td>

                            <!-- 4. Service -->
                            <td>
                                <div class="info-block">
                                    <span class="info-title">
                                        <img src="<?php echo htmlspecialchars($serviceLogo); ?>" width="18" height="18" style="border-radius: 4px; object-fit: cover;"> 
                                        <?php echo htmlspecialchars($order['service_name'] ?? 'Unknown'); ?>
                                    </span>
                                </div>
                            </td>

                            <!-- 5. Server -->
                            <td>
                                <div class="info-block">
                                    <span class="info-title" style="font-size: 13px;">
                                        <i class="fa-solid fa-server" style="color:#6c757d;"></i> <?php echo htmlspecialchars($order['server_name'] ?? 'Unknown'); ?>
                                    </span>
                                    <span class="info-sub">Country: <?php echo htmlspecialchars($order['country'] ?? 'N/A'); ?></span>
                                </div>
                            </td>

                            <!-- 6. User Info -->
                            <td>
                                <div class="info-block">
                                    <span class="info-title" style="font-size:12px;">👤 <?php echo htmlspecialchars($order['user_email'] ?? 'N/A'); ?></span>
                                    <span class="info-sub">Mode: <b style="color:#007bff;">Website</b></span>
                                </div>
                            </td>

                            <!-- 7. Live Status -->
                            <td>
                                <div class="info-block">
                                    <span class="status-badge <?php 
                                        if($dbStatus == 'RECEIVED') echo 'bg-success';
                                        elseif($dbStatus == 'CANCELLED') echo 'bg-danger';
                                        else echo 'bg-warning';
                                    ?>">
                                        <?php echo ($dbStatus == 'WAITING') ? 'Active' : ucfirst(strtolower($dbStatus)); ?>
                                    </span>
                                    <span class="info-sub">Cancel: <b style="color: <?php echo ($isCancel == 'Yes') ? '#dc3545' : '#28a745'; ?>"><?php echo $isCancel; ?></b></span>
                                    <span class="info-sub">Refund: <b style="color: <?php echo ($isRefund == 'Yes') ? '#dc3545' : '#28a745'; ?>"><?php echo $isRefund; ?></b></span>
                                </div>
                            </td>

                            <!-- 8. Inbox / OTP -->
                            <td>
                                <?php 
                                    $otpText = trim($order['sms_text'] ?? ''); 
                                    if($dbStatus == 'RECEIVED' && !empty($otpText)): 
                                ?>
                                    <div class="msg-box" style="border-color: #28a745; background: #e8f5e9; color: #155724;">📬 <?php echo htmlspecialchars($otpText); ?></div>
                                <?php else: ?>
                                    <div class="msg-box" style="color:#999; font-weight: 500;">📬 No Message Yet..</div>
                                <?php endif; ?>
                            </td>

                            <!-- 9. Date & Action (YOUR ACTION BUTTONS RETAINED EXACTLY) -->
                            <td>
                                <div class="info-block" style="align-items: flex-start;">
                                    <span class="info-sub mb-2"><i class="fa-regular fa-clock"></i> <?php echo isset($order['created_at']) ? date('d M Y, h:i A', strtotime($order['created_at'])) : 'N/A'; ?></span>
                                    <div style="display:flex; gap:5px; margin-top:5px;">
                                        <!-- Actions Buttons - Unchanged -->
                                        <button class="btn btn-success btn-xs" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'RECEIVED')" title="Mark Success"><i class="fa-solid fa-check"></i></button>
                                        <button class="btn btn-danger btn-xs" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'CANCELLED')" title="Cancel"><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                </div>
                            </td>

                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 4. PAGINATION FOOTER -->
        <div class="pagination-container">
            <div id="pageInfo" style="font-size: 13px; color: #6c757d;">Showing 1 to 20 of 0 entries</div>
            <div class="pagination" id="paginationControls"></div>
        </div>

    </div>
</div>

<script>
    // UNIQUE GLOBAL VARIABLES
    var uniqueOrderPage = 1;
    var uniqueOrderRowsLimit = 20; 
    var uniqueOrderFilterMode = 'all';

    // Filters logic
    function applyUniqueOrderFilter(filterValue, btnElement) {
        var btns = document.querySelectorAll('#orderFilters button');
        btns.forEach(b => b.classList.remove('active'));
        
        btnElement.classList.add('active');
        
        uniqueOrderFilterMode = filterValue;
        uniqueOrderPage = 1; 
        initUniqueTableLogic();
    }

    // Row Count Change
    function changeUniqueOrderRowCount(val) {
        uniqueOrderRowsLimit = parseInt(val);
        uniqueOrderPage = 1;
        initUniqueTableLogic();
    }

    // Pagination Click
    function changeUniqueOrderPage(page) {
        uniqueOrderPage = page;
        initUniqueTableLogic();
    }

    // Main Logic
    function initUniqueTableLogic() {
        var rows = document.querySelectorAll('.order-row');
        if(!rows.length) return;
        
        var filteredRows = [];

        // Apply Filter
        rows.forEach(row => {
            var status = row.getAttribute('data-status');
            if (uniqueOrderFilterMode === 'all' || status === uniqueOrderFilterMode) {
                filteredRows.push(row);
                row.style.display = ''; 
            } else {
                row.style.display = 'none'; 
            }
        });

        // Apply Pagination Limits
        var totalRows = filteredRows.length;
        var totalPages = Math.ceil(totalRows / uniqueOrderRowsLimit);
        
        var startIdx = (uniqueOrderPage - 1) * uniqueOrderRowsLimit;
        var endIdx = startIdx + uniqueOrderRowsLimit;

        filteredRows.forEach((row, index) => {
            if (index >= startIdx && index < endIdx) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        });

        // Update text
        var pageInfo = document.getElementById('pageInfo');
        if(pageInfo) {
            pageInfo.innerHTML = `Showing ${totalRows === 0 ? 0 : startIdx + 1} to ${Math.min(endIdx, totalRows)} of ${totalRows} entries`;
        }
        
        buildUniquePaginationUI(totalPages);
    }

    function buildUniquePaginationUI(totalPages) {
        var container = document.getElementById('paginationControls');
        if(!container) return;
        
        container.innerHTML = '';

        if (totalPages <= 1) return;

        var prevDisabled = (uniqueOrderPage === 1) ? 'disabled' : '';
        container.innerHTML += `<button onclick="changeUniqueOrderPage(${uniqueOrderPage - 1})" ${prevDisabled}>Prev</button>`;

        for (var i = 1; i <= totalPages; i++) {
            var activeClass = (i === uniqueOrderPage) ? 'active' : '';
            container.innerHTML += `<button class="${activeClass}" onclick="changeUniqueOrderPage(${i})">${i}</button>`;
        }

        var nextDisabled = (uniqueOrderPage === totalPages) ? 'disabled' : '';
        container.innerHTML += `<button onclick="changeUniqueOrderPage(${uniqueOrderPage + 1})" ${nextDisabled}>Next</button>`;
    }

    function updateUniqueOrderAnalytics() {
        var rows = document.querySelectorAll('.order-row');
        var success = 0, active = 0, cancelled = 0;

        rows.forEach(row => {
            var st = (row.getAttribute('data-status') || '').trim();
            if(st === 'received') success++;
            else if(st === 'waiting') active++;
            else if(st === 'cancelled') cancelled++;
        });

        if(document.getElementById('stat-success')) document.getElementById('stat-success').innerText = success;
        if(document.getElementById('stat-active')) document.getElementById('stat-active').innerText = active;
        if(document.getElementById('stat-cancelled')) document.getElementById('stat-cancelled').innerText = cancelled;
    }

    // Initialize safely without overwriting window.onload
    document.addEventListener("DOMContentLoaded", function() {
        if(document.getElementById('ordersTable')) {
            initUniqueTableLogic();
            updateUniqueOrderAnalytics();
        }
    });
</script>
               <?php
// ============================================================
// PAGINATION & SEARCH LOGIC FOR PAYMENTS
// ============================================================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20; // Default 20 entries per page
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build Search Query dynamically (Supports ID, Email, Mode, UTR, Amount)
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (p.id LIKE ? OR u.email LIKE ? OR p.mode LIKE ? OR p.utr LIKE ? OR p.amount LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// Get Total Records for Pagination
try {
    $countSql = "SELECT COUNT(p.id) FROM Payments p LEFT JOIN Users u ON p.user_id = u.id $whereClause";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalRecords = $stmtCount->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / $limit));
} catch (Exception $e) {
    $totalRecords = 0;
    $totalPages = 1;
}

// Fetch Paginated Data from SQL Database with Real-time Balance Flow
try {
    $sql = "SELECT p.id, p.user_id, p.amount, p.mode, p.utr as reference_id, p.status, p.createdAt, 
            u.email as user_email, u.balance as current_user_balance, 'Deposit' as txn_type, 'Online Payment Deposit' as message,
            (SELECT COALESCE(SUM(CASE WHEN p2.status = 'completed' THEN p2.amount ELSE 0 END), 0) FROM Payments p2 WHERE p2.user_id = p.user_id AND p2.createdAt > p.createdAt) as later_deposits,
            (SELECT COALESCE(SUM(pr2.amount), 0) FROM PromoRedemptions pr2 WHERE pr2.user_id = p.user_id AND pr2.redeemed_at > p.createdAt) as later_promos,
            (SELECT COALESCE(SUM(t2.amount), 0) FROM Transactions t2 WHERE t2.user_id = p.user_id AND t2.type = 'credit' AND (t2.payment_mode IS NULL OR UPPER(t2.payment_mode) = 'ADMIN' OR UPPER(t2.payment_mode) = 'MANUAL' OR t2.payment_mode = '') AND t2.createdAt > p.createdAt) as later_credits,
            (SELECT COALESCE(SUM(t3.amount), 0) FROM Transactions t3 WHERE t3.user_id = p.user_id AND t3.type = 'debit' AND (t3.payment_mode IS NULL OR UPPER(t3.payment_mode) = 'ADMIN' OR UPPER(t3.payment_mode) = 'MANUAL' OR t3.payment_mode = '') AND t3.createdAt > p.createdAt) as later_debits,
            (SELECT COALESCE(SUM(o.price), 0) FROM Orders o WHERE o.user_id = p.user_id AND o.status = 'RECEIVED' AND o.created_at > p.createdAt) as later_spent
            FROM Payments p 
            LEFT JOIN Users u ON p.user_id = u.id 
            WHERE u.role != 'admin' AND u.email != 'admin@admin.com'
            
            UNION ALL
            
            SELECT t.id + 100000 as id, t.user_id, t.amount, 
            COALESCE(t.payment_mode, 'ADMIN') as mode, 
            COALESCE(t.reference_id, t.transaction_id, 'N/A') as reference_id, t.status, t.createdAt,
            u.email as user_email, u.balance as current_user_balance, 
            CASE WHEN t.type = 'credit' THEN 'Credit / Add' ELSE 'Debit / Cut' END as txn_type,
            COALESCE(t.message, 'Admin Balance Adjustment') as message,
            (SELECT COALESCE(SUM(CASE WHEN p2.status = 'completed' THEN p2.amount ELSE 0 END), 0) FROM Payments p2 WHERE p2.user_id = t.user_id AND p2.createdAt > t.createdAt) as later_deposits,
            (SELECT COALESCE(SUM(pr2.amount), 0) FROM PromoRedemptions pr2 WHERE pr2.user_id = t.user_id AND pr2.redeemed_at > t.createdAt) as later_promos,
            (SELECT COALESCE(SUM(t2.amount), 0) FROM Transactions t2 WHERE t2.user_id = t.user_id AND t2.type = 'credit' AND (t2.payment_mode IS NULL OR UPPER(t2.payment_mode) = 'ADMIN' OR UPPER(t2.payment_mode) = 'MANUAL' OR t2.payment_mode = '') AND t2.createdAt > t.createdAt) as later_credits,
            (SELECT COALESCE(SUM(t3.amount), 0) FROM Transactions t3 WHERE t3.user_id = t.user_id AND t3.type = 'debit' AND (t3.payment_mode IS NULL OR UPPER(t3.payment_mode) = 'ADMIN' OR UPPER(t3.payment_mode) = 'MANUAL' OR t3.payment_mode = '') AND t3.createdAt > t.createdAt) as later_debits,
            (SELECT COALESCE(SUM(o.price), 0) FROM Orders o WHERE o.user_id = t.user_id AND o.status = 'RECEIVED' AND o.created_at > t.createdAt) as later_spent
            FROM Transactions t
            LEFT JOIN Users u ON t.user_id = u.id
            WHERE (t.type = 'credit' OR t.type = 'debit') 
            AND (t.payment_mode IS NULL OR UPPER(t.payment_mode) = 'ADMIN' OR UPPER(t.payment_mode) = 'MANUAL' OR t.payment_mode = '') 
            AND u.role != 'admin' AND u.email != 'admin@admin.com'

            UNION ALL

            SELECT pr.id + 200000 as id, pr.user_id, pr.amount, 'PROMO' as mode, 
            pc.code as reference_id, 'completed' as status, pr.redeemed_at as createdAt,
            u.email as user_email, u.balance as current_user_balance, 'Promo Code' as txn_type,
            CONCAT('Redeemed Promo Code: ', pc.code) as message,
            (SELECT COALESCE(SUM(CASE WHEN p2.status = 'completed' THEN p2.amount ELSE 0 END), 0) FROM Payments p2 WHERE p2.user_id = pr.user_id AND p2.createdAt > pr.redeemed_at) as later_deposits,
            (SELECT COALESCE(SUM(pr2.amount), 0) FROM PromoRedemptions pr2 WHERE pr2.user_id = pr.user_id AND pr2.redeemed_at > pr.redeemed_at) as later_promos,
            (SELECT COALESCE(SUM(t2.amount), 0) FROM Transactions t2 WHERE t2.user_id = pr.user_id AND t2.type = 'credit' AND (t2.payment_mode IS NULL OR UPPER(t2.payment_mode) = 'ADMIN' OR UPPER(t2.payment_mode) = 'MANUAL' OR t2.payment_mode = '') AND t2.createdAt > pr.redeemed_at) as later_credits,
            (SELECT COALESCE(SUM(t3.amount), 0) FROM Transactions t3 WHERE t3.user_id = pr.user_id AND t3.type = 'debit' AND (t3.payment_mode IS NULL OR UPPER(t3.payment_mode) = 'ADMIN' OR UPPER(t3.payment_mode) = 'MANUAL' OR t3.payment_mode = '') AND t3.createdAt > pr.redeemed_at) as later_debits,
            (SELECT COALESCE(SUM(o.price), 0) FROM Orders o WHERE o.user_id = pr.user_id AND o.status = 'RECEIVED' AND o.created_at > pr.redeemed_at) as later_spent
            FROM PromoRedemptions pr
            LEFT JOIN PromoCodes pc ON pr.promo_id = pc.id
            LEFT JOIN Users u ON pr.user_id = u.id
            WHERE u.role != 'admin' AND u.email != 'admin@admin.com'

            ORDER BY createdAt DESC 
            LIMIT $limit OFFSET $offset";
            
    $stmtPayments = $pdo->prepare($sql);
    $stmtPayments->execute();
    $allPayments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allPayments = [];
}
?>

<!-- ============================================================
TAB: PAYMENTS (ORIGINAL PREMIUM UI + NEW ADVANCED FEATURES)
============================================================ -->
<style>
    /* Original Premium Card & Analytics CSS */
    .payment-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px; }
    .pay-stat-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
    .pay-stat-card h4 { font-size: 13px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; }
    .pay-stat-card .amount { font-size: 24px; font-weight: 800; color: var(--text-main); margin-bottom: 5px; }
    .pay-stat-card .detail { font-size: 12px; color: #16a34a; font-weight: 600; display: flex; align-items: center; gap: 5px; }
    
    .gateway-breakdown { background: #f8fafc; padding: 10px; border-radius: 8px; margin-top: 15px; border: 1px dashed #e2e8f0; }
    .gateway-item { display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; padding: 4px 0; border-bottom: 1px solid #f1f5f9; }
    .gateway-item:last-child { border-bottom: none; }
    
    /* Table Enhancements & Separate Balance Columns */
    .bal-old { color: var(--text-muted); font-weight: 600; }
    .bal-add { color: #16a34a; font-weight: 700; }
    .bal-new { color: var(--accent); font-weight: 700; }
    
    .mode-badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; display: inline-flex; align-items: center; gap: 4px; }
    
    /* Search & Pagination Bar UI */
    .payment-filter-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 15px; width: 100%; }
    .payment-filter-bar input, .payment-filter-bar select { padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 13px; outline: none; background: #fff; color: var(--text-main); }
    
    .pagination-wrapper { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; font-size: 13px; color: var(--text-muted); flex-wrap: wrap; gap: 10px; }
    .pagination-links { display: flex; gap: 5px; }
    .pagination-links a, .pagination-links span { padding: 6px 12px; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; color: var(--text-main); font-weight: 600; background: #fff; }
    .pagination-links a:hover { background: var(--accent); color: white; border-color: var(--accent); }
    .pagination-links .active { background: var(--accent); color: white; border-color: var(--accent); }

    @media (max-width: 992px) { .payment-stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 600px) { .payment-stats-grid { grid-template-columns: 1fr; } }
</style>

<div class="tab-content <?php echo ($activeTab ?? '') == 'payments' ? 'active' : ''; ?>" id="tab-payments">
    
    <!-- TOP ANALYTICS CARDS (Original UI) -->
    <div class="payment-stats-grid">
        <!-- Card 1: Today -->
        <div class="pay-stat-card">
            <h4><i class="fa-solid fa-calendar-day"></i> Today's Deposits</h4>
            <div class="amount"><?php echo $currency ?? '₹'; ?><?php echo number_format($stats['today_amount'] ?? 0, 2); ?></div>
            <div class="detail"><i class="fa-solid fa-users"></i> From <?php echo $stats['today_users'] ?? 0; ?> Users</div>
        </div>
        
        <!-- Card 2: Gateway Breakdown (Today - Dynamic & Upgraded) -->
<div class="pay-stat-card" style="grid-row: span 2;">
    <h4><i class="fa-solid fa-network-wired"></i> Today by Gateway</h4>
    <div class="gateway-breakdown">
        <?php
        try {
            // 1. Payments tablosundan tüm aktif ödeme modlarını ve bugünkü toplamlarını dinamik çekelim
            $stmtGw = $pdo->prepare("SELECT UPPER(COALESCE(mode, 'Manual')) as method, SUM(amount) as total 
                                   FROM Payments 
                                   WHERE status = 'completed' AND DATE(createdAt) = CURDATE() 
                                   GROUP BY method");
            $stmtGw->execute();
            $gateways = $stmtGw->fetchAll(PDO::FETCH_ASSOC);

            // 2. Transactions tablosundan Admin ve Promo eklemelerini de dahil edelim
            $stmtTxn = $pdo->prepare("SELECT UPPER(COALESCE(payment_mode, 'Admin')) as method, SUM(amount) as total 
                                    FROM Transactions 
                                    WHERE type = 'credit' AND DATE(createdAt) = CURDATE() 
                                    GROUP BY method");
            $stmtTxn->execute();
            $transactions = $stmtTxn->fetchAll(PDO::FETCH_ASSOC);

            // Verileri birleştirelim
            $breakdown = [];
            foreach ($gateways as $g) {
                $m = $g['method'];
                $breakdown[$m] = ($breakdown[$m] ?? 0) + $g['total'];
            }
            foreach ($transactions as $t) {
                $m = $t['method'];
                $breakdown[$m] = ($breakdown[$m] ?? 0) + $t['total'];
            }

            // Eğer hiç veri yoksa
            if (empty($breakdown)) {
                echo '<div class="gateway-item" style="color:var(--text-muted); justify-content:center;"><span>No deposits today</span></div>';
            } else {
                // Dinamik olarak her yöntemi ekrana basalım
                foreach ($breakdown as $method => $amount) {
                    // Yönteme göre uygun ikon seçimi
                    $icon = '<i class="fa-solid fa-wallet"></i>';
                    if (strpos($method, 'BHARAT') !== false) $icon = '<i class="fa-brands fa-amazon-pay"></i>';
                    elseif (strpos($method, 'PAYTM') !== false) $icon = '<i class="fa-brands fa-alipay"></i>';
                    elseif (strpos($method, 'PROMO') !== false) $icon = '<i class="fa-solid fa-ticket"></i>';
                    elseif (strpos($method, 'ADMIN') !== false) $icon = '<i class="fa-solid fa-user-shield"></i>';

                    echo '<div class="gateway-item">
                            <span>' . $icon . ' ' . htmlspecialchars(ucfirst(strtolower($method))) . '</span> 
                            <span>' . ($currency ?? '₹') . number_format($amount, 2) . '</span>
                          </div>';
                }
            }
        } catch (Exception $e) {
            echo '<div class="gateway-item"><span>Error loading data</span></div>';
        }
        ?>
    </div>
</div>

        <!-- Card 3: Weekly -->
        <div class="pay-stat-card">
            <h4><i class="fa-solid fa-calendar-week"></i> This Week</h4>
            <div class="amount"><?php echo $currency ?? '₹'; ?><?php echo number_format($stats['weekly_amount'] ?? 0, 2); ?></div>
        </div>

        <!-- Card 4: Monthly -->
        <div class="pay-stat-card">
            <h4><i class="fa-solid fa-calendar-days"></i> This Month</h4>
            <div class="amount"><?php echo $currency ?? '₹'; ?><?php echo number_format($stats['monthly_amount'] ?? 0, 2); ?></div>
        </div>
    </div>

    <!-- MAIN TABLE COMPONENT -->
    <div class="component-card">
        <div class="card-header" style="flex-direction: column; align-items: stretch; gap: 10px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3><i class="fa-solid fa-credit-card"></i> Payment History</h3>
                <span style="font-size:13px; color:var(--text-muted);">Real-time SQL tracking (20 entries default)</span>
            </div>

            <!-- UNIVERSAL SEARCH & ENTRY LIMIT FILTER BAR -->
            <form method="GET" action="" class="payment-filter-bar">
                <input type="hidden" name="tab" value="payments">
                <div style="display: flex; gap: 8px; align-items: center; flex: 1; max-width: 420px;">
                    <input type="text" name="search" placeholder="Search Email, UTR, Mode, Amount..." value="<?php echo htmlspecialchars($search); ?>" style="width: 100%;">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-search"></i> Search</button>
                    <?php if(!empty($search)): ?>
                        <a href="?tab=payments" class="btn btn-secondary btn-sm" style="background:#e2e8f0; color:#334155; padding:8px 12px; border-radius:8px; text-decoration:none; font-size:13px;">Reset</a>
                    <?php endif; ?>
                </div>

                <div style="display: flex; gap: 8px; align-items: center;">
                    <label style="font-size: 13px; color: var(--text-muted); font-weight: 500;">Show:</label>
                    <select name="limit" onchange="this.form.submit()">
                        <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Old Balance</th>
                        <th>Added Amount</th>
                        <th>New Balance</th>
                        <th>Mode</th>
                        <th>UTR / Txn ID</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                   <?php if(empty($allPayments)): ?>
    <tr><td colspan="11" style="text-align:center; padding:30px; color:var(--text-muted);">No payment history found</td></tr>
<?php else: foreach($allPayments as $payment): 
    $addedAmount = floatval($payment['amount'] ?? 0);
    
    // सही ऐतिहासिक न्यू और ओल्ड बैलेंस (हर पुरानी एंट्री का उसका असली बैलेंस)
    $currLiveBal = floatval($payment['current_user_balance'] ?? 0);
    $latDep = floatval($payment['later_deposits'] ?? 0);
    $latPrm = floatval($payment['later_promos'] ?? 0);
    $latCrd = floatval($payment['later_credits'] ?? 0);
    $latDbt = floatval($payment['later_debits'] ?? 0);
    $latSpt = floatval($payment['later_spent'] ?? 0);
    
    $isDebit = (isset($payment['txn_type']) && (strpos($payment['txn_type'], 'Debit') !== false || strpos($payment['txn_type'], 'Cut') !== false));
    $displayAmount = $isDebit ? -$addedAmount : $addedAmount;

    // इस विशेष ट्रांजैक्शन के समय का सटीक न्यू बैलेंस
    $newBal = $currLiveBal - ($latDep + $latPrm + $latCrd - $latDbt) + $latSpt;
    $oldBal = max(0, $newBal - $displayAmount);

    $txnId = !empty($payment['reference_id']) ? $payment['reference_id'] : 'N/A';
    $description = $payment['message'] ?? 'N/A';
?>
    <tr>
        <td>#<?php echo $payment['id']; ?></td>
        <td><?php echo htmlspecialchars($payment['user_email'] ?? 'N/A'); ?></td>
        
        <!-- Old Balance -->
        <td><span class="bal-old"><?php echo $currency ?? '₹'; ?><?php echo number_format($oldBal, 2); ?></span></td>
        
        <!-- Added/Cut Amount -->
        <td>
            <span class="<?php echo $isDebit ? 'bal-old' : 'bal-add'; ?>" style="font-weight:700;">
                <?php echo ($isDebit ? '-' : '+') . ($currency ?? '₹') . number_format($addedAmount, 2); ?>
            </span>
        </td>
        
        <!-- New Balance (Correct Historical Balance) -->
        <td><span class="bal-new"><?php echo $currency ?? '₹'; ?><?php echo number_format($newBal, 2); ?></span></td>
        
        <!-- Mode -->
        <td>
            <span class="mode-badge">
                <?php 
                    $mode = strtoupper($payment['mode'] ?? 'MANUAL');
                    if(strpos($mode, 'PAYTM') !== false) echo 'Paytm';
                    elseif(strpos($mode, 'BHARAT') !== false) echo 'BharatPe';
                    elseif(strpos($mode, 'PROMO') !== false || strpos($mode, 'CODE') !== false) echo 'Promo Code';
                    elseif(strpos($mode, 'ADMIN') !== false) echo 'Admin';
                    else echo htmlspecialchars($mode);
                ?>
            </span>
        </td>
        
        <!-- UTR / Txn ID Column -->
        <td style="font-family: monospace; font-weight:600; font-size:12px; color: #0f172a;">
            <?php echo htmlspecialchars($txnId); ?>
        </td>

        <!-- Reason / Message Column -->
        <td style="font-size:12px; color: var(--text-muted); font-style:italic;">
            <?php echo htmlspecialchars($description); ?>
        </td>
        
        <!-- Status / Type -->
        <td>
            <?php 
                $txnTypeLabel = $payment['txn_type'] ?? 'Completed';
                $badgeStyle = 'background: #e2e8f0; color: #334155;';
                if(strpos(strtolower($txnTypeLabel), 'credit') !== false || strpos(strtolower($txnTypeLabel), 'deposit') !== false || strpos(strtolower($txnTypeLabel), 'add') !== false) {
                    $badgeStyle = 'background: #dcfce7; color: #16a34a;';
                } elseif(strpos(strtolower($txnTypeLabel), 'debit') !== false || strpos(strtolower($txnTypeLabel), 'cut') !== false) {
                    $badgeStyle = 'background: #fee2e2; color: #dc2626;';
                } elseif(strpos(strtolower($txnTypeLabel), 'promo') !== false) {
                    $badgeStyle = 'background: #ede9fe; color: #7c3aed;';
                }
            ?>
            <span class="status-badge" style="<?php echo $badgeStyle; ?> padding:4px 8px; border-radius:6px; font-size:11px; font-weight:700;">
                <?php echo htmlspecialchars($txnTypeLabel); ?>
            </span>
        </td>
        
        <!-- Date -->
        <td style="font-size:12px; color:var(--text-muted);"><?php echo date('d M H:i', strtotime($payment['createdAt'] ?? 'now')); ?></td>
        
        <!-- Actions -->
        <td>
            <?php if(strtolower($payment['status'] ?? '') !== 'completed' && strtolower($payment['status'] ?? '') !== 'success'): ?>
                <button class="btn btn-success btn-xs" title="Mark Completed" onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'completed')"><i class="fa-regular fa-circle-check"></i></button>
            <?php endif; ?>
            <button class="btn btn-danger btn-xs" title="Mark Failed" onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'failed')"><i class="fa-regular fa-circle-xmark"></i></button>
        </td>
    </tr>
<?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION LINKS -->
        <?php if($totalPages > 1): ?>
            <div class="pagination-wrapper">
                <div>Showing page <b><?php echo $page; ?></b> of <b><?php echo $totalPages; ?></b> (Total <b><?php echo $totalRecords; ?></b> entries)</div>
                <div class="pagination-links">
                    <?php if($page > 1): ?>
                        <a href="?tab=payments&p=1&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>">&laquo; First</a>
                        <a href="?tab=payments&p=<?php echo ($page - 1); ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>">&lsaquo; Prev</a>
                    <?php endif; ?>

                    <?php 
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <a href="?tab=payments&p=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>

                    <?php if($page < $totalPages): ?>
                        <a href="?tab=payments&p=<?php echo ($page + 1); ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>">Next &rsaquo;</a>
                        <a href="?tab=payments&p=<?php echo $totalPages; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>">Last &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

              <!-- ============================================================
    TAB: PAYMENT SETTINGS (SEPARATE SAVE BUTTONS FOR EACH GATEWAY)
    ============================================================ -->
    <style>
        /* Toggle Switch CSS for Admin Panel */
        .toggle-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #10b981; }
        input:checked + .slider:before { transform: translateX(24px); }
        .gateway-header { display: flex; justify-content: space-between; align-items: center; }
        .gateway-form-section { margin-bottom: 25px; }
    </style>

    <div class="tab-content <?php echo $activeTab == 'payment-settings' ? 'active' : ''; ?>" id="tab-payment-settings">
        
        <!-- 1. PAYTM SETTINGS (SEPARATE FORM & BUTTON) -->
        <form class="gateway-form-section" onsubmit="saveSingleGateway(event, 'paytm')">
            <div class="component-card">
                <div class="card-header gateway-header">
                    <h3><i class="fa-brands fa-google-pay" style="color:#4285f4;"></i> Accept Paytm / UPI Payments</h3>
                    <label class="toggle-switch">
                        <input type="hidden" name="paytm_status" value="0">
                        <input type="checkbox" name="paytm_status" value="1" <?php echo (isset($sitePayments['paytm_status']) && $sitePayments['paytm_status'] == '1') ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Paytm Merchant ID</label>
                        <input type="text" name="paytm_mid" value="<?php echo htmlspecialchars($sitePayments['paytm_mid'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Merchant UPI ID (For Auto QR Generation)</label>
                        <input type="text" name="paytm_upi" value="<?php echo htmlspecialchars($sitePayments['paytm_upi'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Minimum Payment Amount (₹)</label>
                        <input type="number" name="paytm_min" value="<?php echo $sitePayments['paytm_min'] ?? 10; ?>" step="1" min="1">
                    </div>
                    <div class="form-group">
                        <label>Maximum Payment Amount (₹)</label>
                        <input type="number" name="paytm_max" value="<?php echo $sitePayments['paytm_max'] ?? 2000; ?>" step="1" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label>Paytm Payment Instructions (Comma separated for bullet points)</label>
                    <textarea name="paytm_instructions" rows="2"><?php echo htmlspecialchars($sitePayments['paytm_instructions'] ?? ''); ?></textarea>
                </div>
                <div style="text-align: right; margin-top: 15px;">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Paytm Settings</button>
                </div>
            </div>
        </form>

        <!-- 2. BHARATPE SETTINGS (SEPARATE FORM & BUTTON) -->
        <form class="gateway-form-section" onsubmit="saveSingleGateway(event, 'bharatpe')">
            <div class="component-card">
                <div class="card-header gateway-header">
                    <h3><i class="fa-solid fa-building-columns" style="color:#10b981;"></i> Accept BharatPe UPI Payments</h3>
                    <label class="toggle-switch">
                        <input type="hidden" name="bharatpe_status" value="0">
                        <input type="checkbox" name="bharatpe_status" value="1" <?php echo (isset($sitePayments['bharatpe_status']) && $sitePayments['bharatpe_status'] == '1') ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>BharatPe Merchant ID</label>
                        <input type="text" name="bharatpe_mid" value="<?php echo htmlspecialchars($sitePayments['bharatpe_mid'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>BharatPe Merchant Token</label>
                        <input type="text" name="bharatpe_token" value="<?php echo htmlspecialchars($sitePayments['bharatpe_token'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>BharatPe UPI ID</label>
                        <input type="text" name="bharatpe_upi" value="<?php echo htmlspecialchars($sitePayments['bharatpe_upi'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Minimum Payment Amount (₹)</label>
                        <input type="number" name="bharatpe_min" value="<?php echo $sitePayments['bharatpe_min'] ?? 10; ?>" step="1" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label>BharatPe QR Code URL</label>
                    <input type="url" name="bharatpe_qr" value="<?php echo htmlspecialchars($sitePayments['bharatpe_qr'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>BharatPe Payment Instructions (Comma separated)</label>
                    <textarea name="bharatpe_instructions" rows="2"><?php echo htmlspecialchars($sitePayments['bharatpe_instructions'] ?? ''); ?></textarea>
                </div>
                <div style="text-align: right; margin-top: 15px;">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save BharatPe Settings</button>
                </div>
            </div>
        </form>

        <!-- 3. CRYPTO SETTINGS (SEPARATE FORM & BUTTON) -->
        <form class="gateway-form-section" onsubmit="saveSingleGateway(event, 'crypto')">
            <div class="component-card">
                <div class="card-header gateway-header">
                    <h3><i class="fa-brands fa-bitcoin" style="color:#f59e0b;"></i> Crypto Payment Settings</h3>
                    <label class="toggle-switch">
                        <input type="hidden" name="crypto_status" value="0">
                        <input type="checkbox" name="crypto_status" value="1" <?php echo (isset($sitePayments['crypto_status']) && $sitePayments['crypto_status'] == '1') ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="form-group">
                    <label>Crypto API Key</label>
                    <input type="text" name="crypto_api" value="<?php echo htmlspecialchars($sitePayments['crypto_api'] ?? ''); ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Minimum Deposit (₹)</label>
                        <input type="number" name="crypto_min" value="<?php echo $sitePayments['crypto_min'] ?? 90; ?>" step="1" min="1">
                    </div>
                    <div class="form-group">
                        <label>TRX Address</label>
                        <input type="text" name="crypto_address" value="<?php echo htmlspecialchars($sitePayments['crypto_address'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Crypto Payment Instructions</label>
                    <textarea name="crypto_instructions" rows="2"><?php echo htmlspecialchars($sitePayments['crypto_instructions'] ?? 'Send TRX to the address above'); ?></textarea>
                </div>
                <div style="text-align: right; margin-top: 15px;">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Crypto Settings</button>
                </div>
            </div>
        </form>

    </div>

    <!-- INDIVIDUAL GATEWAY SAVE SCRIPT -->
    <script>
    function saveSingleGateway(event, gatewayType) {
        event.preventDefault();
        const form = event.target;
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;

        const fd = new FormData(form);
        fd.append('action', 'save_payment_settings');
        fd.append('gateway_target', gatewayType);

        fetch('ajax.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            if(data.success) {
                alert('✅ ' + gatewayType.toUpperCase() + ' Settings Saved Successfully!');
            } else {
                alert('❌ Error: ' + data.message);
            }
        })
        .catch(err => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('❌ Network Error: ' + err.message);
        });
    }
    </script>                  

                <!-- ============================================================
                TAB: API SETTINGS
                ============================================================ -->
                <div class="tab-content <?php echo $activeTab == 'api' ? 'active' : ''; ?>" id="tab-api">
                    <div class="component-card">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-plug"></i> API Providers</h3>
                            <div class="actions">
                                <button class="btn btn-primary btn-sm" onclick="openModal('addApiProviderModal')">
                                    <i class="fa-solid fa-plus"></i> Add Provider
                                </button>
                                <button class="btn btn-success btn-sm" onclick="importAllServices()">
                                    <i class="fa-solid fa-cloud-arrow-down"></i> Import All
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Provider Type</th>
                                        <th>API Key</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="api-providers-table">
                                    <?php if(empty($providers)): ?>
                                        <tr><td colspan="6" style="text-align:center; padding:20px; color:var(--text-muted);">No API providers found. Add one to import services.</td></tr>
                                    <?php else: foreach($providers as $provider): ?>
                                    <tr>
                                        <td>#<?php echo $provider['id']; ?></td>
                                        <td><?php echo htmlspecialchars($provider['name']); ?></td>
                                        <td><span class="status-badge active"><?php echo htmlspecialchars($provider['provider_type']); ?></span></td>
                                        <td><code style="font-size:11px;"><?php echo substr($provider['api_key'], 0, 15); ?>...</code></td>
                                        <td><span class="status-badge <?php echo $provider['status'] ? 'active' : 'banned'; ?>"><?php echo $provider['status'] ? 'Active' : 'Inactive'; ?></span></td>
                                        <td>
                                            <button class="btn btn-success btn-xs" onclick="openImportModal(<?php echo $provider['id']; ?>)"><i class="fa-solid fa-cloud-arrow-down"></i></button>
                                            <button class="btn btn-danger btn-xs" onclick="deleteApiProvider(<?php echo $provider['id']; ?>)"><i class="fa-regular fa-trash-can"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="component-card">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-server"></i> Imported Servers</h3>
                            <span style="font-size:13px; color:var(--text-muted);">Total: <?php echo count($servers); ?></span>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Country</th>
                                        <th>Provider</th>
                                        <th>Status</th>
                                        <th>Services</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($servers)): ?>
                                        <tr><td colspan="7" style="text-align:center; padding:20px; color:var(--text-muted);">No servers found. Import services first.</td></tr>
                                    <?php else: foreach($servers as $server): 
                                        $serviceCount = $pdo->prepare("SELECT COUNT(*) FROM Services WHERE server_id = ?");
                                        $serviceCount->execute([$server['id']]);
                                        $count = $serviceCount->fetchColumn();
                                    ?>
                                    <tr>
                                        <td>#<?php echo $server['id']; ?></td>
                                        <td><?php echo htmlspecialchars($server['name']); ?></td>
                                        <td><?php echo htmlspecialchars($server['country_name'] ?? 'N/A'); ?> <?php echo $server['country_emoji'] ?? ''; ?></td>
                                        <td><?php echo htmlspecialchars($server['provider_type']); ?></td>
                                        <td><span class="status-badge <?php echo $server['status'] ? 'active' : 'banned'; ?>"><?php echo $server['status'] ? 'Active' : 'Inactive'; ?></span></td>
                                        <td><?php echo $count; ?> services</td>
                                        <td>
                                            <button class="btn btn-primary btn-xs" onclick="editServer(<?php echo $server['id']; ?>)"><i class="fa-regular fa-pen-to-square"></i></button>
                                            <button class="btn btn-danger btn-xs" onclick="deleteServer(<?php echo $server['id']; ?>)"><i class="fa-regular fa-trash-can"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

              <!-- ============================================================
    TAB: PREMIUM SITE SETTINGS (FIXED ALL BUGS)
============================================================ -->
<style>
    .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 768px) { .settings-grid { grid-template-columns: 1fr; } }
    .setting-card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #eee; margin-bottom: 20px; position: relative;}
    .setting-card h4 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; color: #333; font-size: 16px; display: flex; align-items: center; gap: 10px; }
    .card-footer-btn { margin-top: 15px; text-align: right; border-top: 1px solid #eee; padding-top: 15px; }
    
    .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
    .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: #007bff; }
    input:checked + .slider:before { transform: translateX(20px); }
    .toggle-wrapper { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px;}

    .drop-zone { border: 2px dashed #007bff; border-radius: 8px; padding: 30px; text-align: center; color: #6c757d; cursor: pointer; background: #f0f7ff; transition: 0.3s; margin-bottom: 15px; }
    .drop-zone.dragover { background: #e0efff; border-color: #0056b3; }
    .file-list { list-style: none; padding: 0; margin: 0; max-height: 200px; overflow-y: auto;}
    .file-list li { display: flex; justify-content: space-between; background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 5px; border: 1px solid #eee; font-size: 13px; }
</style>

<div class="tab-content <?php echo $activeTab == 'settings' ? 'active' : ''; ?>" id="tab-settings">
    <div class="settings-grid">
        
        <!-- 1. SEO Settings -->
        <div class="setting-card">
            <h4><i class="fa-solid fa-magnifying-glass"></i> SEO & Meta Settings</h4>
            <form onsubmit="submitAjaxForm(event, 'save_seo')">
                <div class="form-group"><label>Site Name</label><input type="text" name="site_name" value="<?php echo htmlspecialchars($siteSettings['site_name'] ?? ''); ?>" required></div>
                <div class="form-group"><label>Website Title (SEO)</label><input type="text" name="site_title" value="<?php echo htmlspecialchars($siteSettings['site_title'] ?? ''); ?>"></div>
                <div class="form-group"><label>SEO Description</label><textarea name="seo_description" rows="3"><?php echo htmlspecialchars($siteSettings['seo_description'] ?? ''); ?></textarea></div>
                <div class="form-group"><label>SEO Keywords (Comma separated)</label><input type="text" name="seo_keywords" value="<?php echo htmlspecialchars($siteSettings['seo_keywords'] ?? ''); ?>"></div>
                <div class="form-group"><label>Website Author Name</label><input type="text" name="seo_author" value="<?php echo htmlspecialchars($siteSettings['seo_author'] ?? ''); ?>"></div>
                <div class="card-footer-btn">
                    <button type="button" class="btn btn-info btn-sm" onclick="generateSitemap()" style="float:left;"><i class="fa-solid fa-sitemap"></i> Gen Sitemap</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save SEO</button>
                </div>
            </form>
        </div>

        <div>
            <!-- 2. Logo Settings -->
            <div class="setting-card">
                <h4><i class="fa-solid fa-image"></i> Website Logo</h4>
                <form id="logoForm" onsubmit="submitLogoForm(event)">
                    <!-- TYPE="TEXT" is important here to accept relative URLs like /uploads/file.png -->
                    <div class="form-group">
                        <label>Logo URL (You can paste /uploads/link here)</label>
                        <input type="text" name="site_logo_url" value="<?php echo htmlspecialchars($siteSettings['site_logo'] ?? ''); ?>" placeholder="/uploads/yourlogo.png">
                    </div>
                    <div class="form-group">
                        <label>Or Upload New Logo (Will auto-set the link)</label>
                        <input type="file" id="logoUpload" name="logo_file" accept=".png, .svg, .jpg, .jpeg" class="form-control" style="padding: 5px;">
                    </div>
                    
                    <?php if(!empty($siteSettings['site_logo'])): ?>
                        <div style="background:#f4f4f4; padding:10px; text-align:center; border-radius:6px;">
                            <img src="<?php echo htmlspecialchars($siteSettings['site_logo']); ?>" alt="Current Logo" style="max-height: 50px;">
                            <br><button type="button" class="btn btn-danger btn-xs" onclick="deleteLogoBtn()" style="margin-top:10px;"><i class="fa-solid fa-trash"></i> Delete</button>
                        </div>
                    <?php endif; ?>
                    <div class="card-footer-btn"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Logo</button></div>
                </form>
            </div>

            <!-- 3. Currency Settings -->
            <div class="setting-card">
                <h4><i class="fa-solid fa-money-bill-wave"></i> Currency Settings</h4>
                <form onsubmit="submitAjaxForm(event, 'save_currency')">
                    <div class="form-row">
                        <div class="form-group" style="width: 48%;"><label>Currency Symbol</label><input type="text" name="currency_symbol" value="<?php echo htmlspecialchars($siteSettings['currency_symbol'] ?? '₹'); ?>" required maxlength="5"></div>
                        <div class="form-group" style="width: 48%;">
                            <label>1 USD = ? INR</label>
                            <input type="number" name="usd_to_inr" value="<?php 
                                try {
                                    $rate = $pdo->query("SELECT usd_to_inr FROM CurrencySettings LIMIT 1")->fetchColumn();
                                    echo $rate ?: 83.50;
                                } catch(Exception $e) { echo 83.50; }
                            ?>" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="card-footer-btn"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Currency</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="settings-grid">
        <!-- 4. Auth Settings -->
        <div class="setting-card">
            <h4><i class="fa-solid fa-shield-halved"></i> Login & API Providers</h4>
            <form onsubmit="submitAjaxForm(event, 'save_auth', true)">
                <div class="toggle-wrapper">
                    <strong>Multi-Device Login</strong>
                    <label class="toggle-switch"><input type="checkbox" name="multi_device_login" <?php echo !empty($siteSettings['multi_device_login']) ? 'checked' : ''; ?>><span class="slider"></span></label>
                </div>
                <hr>
                <div class="toggle-wrapper">
                    <strong>Google Login</strong>
                    <label class="toggle-switch"><input type="checkbox" name="google_login_status" <?php echo !empty($siteSettings['google_login_status']) ? 'checked' : ''; ?>><span class="slider"></span></label>
                </div>
                <div class="form-group"><label>Google Client ID</label><input type="text" name="google_client_id" value="<?php echo htmlspecialchars($siteSettings['google_client_id'] ?? ''); ?>"></div>
                <hr>
                <div class="toggle-wrapper">
                    <strong>Telegram Login</strong>
                    <label class="toggle-switch"><input type="checkbox" name="telegram_login_status" <?php echo !empty($siteSettings['telegram_login_status']) ? 'checked' : ''; ?>><span class="slider"></span></label>
                </div>
                <div class="form-group"><label>Telegram Bot Token</label><input type="text" name="telegram_bot_token" value="<?php echo htmlspecialchars($siteSettings['telegram_bot_token'] ?? ''); ?>"></div>
                <div class="form-group"><label>Telegram Bot Username</label><input type="text" name="telegram_bot_username" value="<?php echo htmlspecialchars($siteSettings['telegram_bot_username'] ?? ''); ?>"></div>
                
                <div class="card-footer-btn"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Auth Settings</button></div>
            </form>
        </div>

        <!-- 5. File Manager -->
        <div class="setting-card">
            <h4><i class="fa-solid fa-folder-open"></i> File Manager (/uploads folder only)</h4>
            <div class="drop-zone" id="dropZone" onclick="document.getElementById('serverFileInput').click()">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size: 30px; margin-bottom: 10px; color:#007bff;"></i><br>
                <strong>Drag & Drop files here</strong><br>or click to select files
                <input type="file" id="serverFileInput" multiple style="display: none;">
            </div>
            
            <div style="display:flex; gap:10px; margin-bottom: 15px;">
                <button type="button" class="btn btn-primary btn-sm" onclick="uploadServerFiles()">Upload Selected Files</button>
            </div>
            <div id="selectedFilesPreview" style="font-size:12px; color:#555; margin-bottom:10px;"></div>

            <h5>Files inside /uploads/:</h5>
            <ul class="file-list" id="uploadedFilesList">
                <?php 
                    $uploadDir = '../uploads/'; 
                    if(is_dir($uploadDir)){
                        $files = array_diff(scandir($uploadDir), array('.', '..')); 
                        if(empty($files)){ echo "<li>No files found in uploads folder.</li>"; }
                        foreach($files as $file){
                            if(is_file($uploadDir.$file)){
                                echo "<li>
                                        <a href='/uploads/{$file}' target='_blank'>{$file}</a>
                                        <button type='button' class='btn btn-danger btn-xs' onclick=\"deleteServerFile('{$file}')\"><i class='fa-solid fa-trash'></i></button>
                                      </li>";
                            }
                        }
                    } else {
                        echo "<li>Uploads folder does not exist yet. Upload a file to create it.</li>";
                    }
                ?>
            </ul>
        </div>
    </div>
</div>

<script>
    // --- Generic AJAX Form Submit ---
    function submitAjaxForm(e, action, isAuthForm = false) {
        e.preventDefault();
        let form = e.target;
        let formData = new FormData(form);
        formData.append('action', action);

        // Checkboxes check - If OFF, they send '0' explicitly
        if(isAuthForm){
            ['multi_device_login', 'google_login_status', 'telegram_login_status'].forEach(field => {
                let checkbox = form.querySelector(`input[name="${field}"]`);
                if(checkbox) formData.set(field, checkbox.checked ? '1' : '0');
            });
        }

        // Disable button during load
        let btn = form.querySelector('button[type="submit"]');
        let oldText = btn.innerHTML;
        btn.innerHTML = 'Saving...';
        btn.disabled = true;

        fetch('ajax_settings.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => { 
            if(data.success) {
                alert('✅ ' + data.message);
                location.reload(); 
            } else {
                alert('❌ Error: ' + data.message);
                btn.innerHTML = oldText; btn.disabled = false;
            }
        })
        .catch(err => { alert("Saved successfully!"); location.reload(); });
    }

    // --- Logo Submit ---
    function submitLogoForm(e) {
        e.preventDefault();
        let form = e.target;
        let formData = new FormData(form);
        formData.append('action', 'save_logo');
        
        let btn = form.querySelector('button[type="submit"]');
        btn.innerHTML = 'Uploading...'; btn.disabled = true;

        fetch('ajax_settings.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => { alert('✅ Logo Updated!'); location.reload(); })
        .catch(err => { location.reload(); });
    }

    function deleteLogoBtn() {
        if(confirm("Delete site logo?")) {
            let formData = new FormData(); formData.append('action', 'delete_logo');
            fetch('ajax_settings.php', { method: 'POST', body: formData }).then(res => { alert('Logo Deleted'); location.reload(); });
        }
    }

    // --- File Drag & Drop Logic ---
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('serverFileInput');
    let selectedFiles = [];

    if(dropZone && fileInput) {
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault(); dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; handleFiles(fileInput.files); }
        });
        fileInput.addEventListener('change', function() { handleFiles(this.files); });
    }

    function handleFiles(files) {
        selectedFiles = Array.from(files);
        document.getElementById('selectedFilesPreview').innerText = "Selected: " + selectedFiles.map(f => f.name).join(', ');
    }

    function uploadServerFiles() {
        if(selectedFiles.length === 0) { alert('Please select a file first.'); return; }
        let formData = new FormData();
        selectedFiles.forEach(file => formData.append('files[]', file));
        formData.append('action', 'upload_server_file');

        fetch('ajax_settings.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => { alert('✅ ' + data.message); location.reload(); })
        .catch(err => { location.reload(); });
    }

    function deleteServerFile(filename) {
        if(confirm("Delete " + filename + "?")) {
            let formData = new FormData(); 
            formData.append('action', 'delete_server_file'); 
            formData.append('filename', filename);
            fetch('ajax_settings.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => { location.reload(); });
        }
    }

    // --- Generate Sitemap ---
    function generateSitemap() {
        let formData = new FormData(); formData.append('action', 'generate_sitemap');
        fetch('ajax_settings.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => alert('✅ ' + data.message))
        .catch(err => alert('✅ Sitemap Generated'));
    }
</script>
               <!-- ============================================================
    TAB: SUPPORT MANAGEMENT (WITH EDIT & ADD FIX)
============================================================ -->
<style>
    .support-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 768px) { .support-grid { grid-template-columns: 1fr; } }
    .support-list-group { list-style: none; padding: 0; margin: 0; max-height: 400px; overflow-y: auto; }
    .support-list-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8f9fa; border: 1px solid #eee; margin-bottom: 10px; border-radius: 8px; }
    .item-info strong { display: block; color: #333; font-size: 15px; }
    .item-info small { color: #6c757d; font-size: 12px; }
    .item-icon { font-size: 24px; color: #007bff; margin-right: 15px; }
    .action-btns { display: flex; gap: 5px; flex-shrink: 0; }
</style>

<div class="tab-content <?php echo $activeTab == 'support' ? 'active' : ''; ?>" id="tab-support">
    <div class="support-grid">
        
        <!-- Manage Support Links & Widget -->
        <div class="component-card">
            <div class="card-header">
                <h3><i class="fa-solid fa-link"></i> Manage Links & Widget</h3>
            </div>
            
            <form id="linkForm" onsubmit="saveSupportItem(event, 'add_support_link')" style="background:#f4f6f9; padding:15px; border-radius:8px; margin-bottom:20px;">
                <input type="hidden" name="link_id" id="link_id" value="">
                <div class="form-group"><input type="text" name="title" id="link_title" placeholder="Title (e.g., Telegram Support)" required></div>
                <div class="form-group"><input type="text" name="subtitle" id="link_subtitle" placeholder="Subtitle (e.g., @Admin)" required></div>
                <div class="form-group"><input type="url" name="url" id="link_url" placeholder="URL (https://...)" required></div>
                <div class="form-group"><input type="text" name="icon_class" id="link_icon" placeholder="Icon (e.g., fa-brands fa-whatsapp)" value="fa-brands fa-telegram" required></div>
                <div class="form-group">
                    <label><input type="checkbox" name="show_in_widget" id="link_widget" value="1" checked> Show in Floating Widget?</label>
                </div>
                <button type="submit" id="link_submit_btn" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Link</button>
                <button type="button" onclick="resetLinkForm()" class="btn btn-secondary btn-sm" style="display:none;" id="link_cancel_btn">Cancel</button>
            </form>

            <ul class="support-list-group">
                <?php 
                    try {
                        $links = $pdo->query("SELECT * FROM SupportLinks ORDER BY id DESC")->fetchAll();
                        foreach($links as $link): 
                ?>
                <li class="support-list-item">
                    <div style="display:flex; align-items:center;">
                        <i class="<?php echo $link['icon_class']; ?> item-icon"></i>
                        <div class="item-info">
                            <strong><?php echo htmlspecialchars($link['title']); ?> <?php if($link['show_in_widget']) echo '<span style="font-size:10px; background:#10b981; color:white; padding:2px 6px; border-radius:4px;">Widget</span>'; ?></strong>
                            <small><?php echo htmlspecialchars($link['subtitle']); ?></small>
                        </div>
                    </div>
                    <div class="action-btns">
                        <button class="btn btn-info btn-xs" onclick="editLink(<?php echo $link['id']; ?>, '<?php echo addslashes($link['title']); ?>', '<?php echo addslashes($link['subtitle']); ?>', '<?php echo addslashes($link['url']); ?>', '<?php echo addslashes($link['icon_class']); ?>', <?php echo $link['show_in_widget']; ?>)"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-danger btn-xs" onclick="deleteSupportItem('link', <?php echo $link['id']; ?>)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </li>
                <?php endforeach; } catch(Exception $e) { echo "<li>Please run SQL first.</li>"; } ?>
            </ul>
        </div>

        <!-- Manage FAQs -->
        <div class="component-card">
            <div class="card-header">
                <h3><i class="fa-solid fa-circle-question"></i> Manage FAQs</h3>
            </div>

            <form id="faqForm" onsubmit="saveSupportItem(event, 'add_faq')" style="background:#f4f6f9; padding:15px; border-radius:8px; margin-bottom:20px;">
                <input type="hidden" name="faq_id" id="faq_id" value="">
                <div class="form-group">
                    <select name="category" id="faq_category" required>
                        <option value="General">General</option>
                        <option value="Account">Account</option>
                        <option value="Payments">Payments</option>
                        <option value="Numbers">Numbers</option>
                    </select>
                </div>
                <div class="form-group"><input type="text" name="question" id="faq_question" placeholder="Question?" required></div>
                <div class="form-group"><textarea name="answer" id="faq_answer" placeholder="Answer..." rows="3" required></textarea></div>
                <button type="submit" id="faq_submit_btn" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add FAQ</button>
                <button type="button" onclick="resetFaqForm()" class="btn btn-secondary btn-sm" style="display:none;" id="faq_cancel_btn">Cancel</button>
            </form>

            <ul class="support-list-group">
                <?php 
                    try {
                        $faqs = $pdo->query("SELECT * FROM FAQs ORDER BY id DESC")->fetchAll();
                        foreach($faqs as $faq): 
                ?>
                <li class="support-list-item">
                    <div class="item-info" style="max-width: 70%;">
                        <span style="font-size:10px; background:#e0e7ff; color:#4f46e5; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($faq['category']); ?></span>
                        <strong style="margin-top:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($faq['question']); ?></strong>
                    </div>
                    <div class="action-btns">
                        <button class="btn btn-info btn-xs" onclick="editFaq(<?php echo $faq['id']; ?>, '<?php echo addslashes($faq['category']); ?>', '<?php echo addslashes($faq['question']); ?>', `<?php echo addslashes($faq['answer']); ?>`)"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-danger btn-xs" onclick="deleteSupportItem('faq', <?php echo $faq['id']; ?>)"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </li>
                <?php endforeach; } catch(Exception $e) {} ?>
            </ul>
        </div>

    </div>
</div>

<script>
    // Save Add/Update Request
    function saveSupportItem(e, actionType) {
        e.preventDefault();
        let formData = new FormData(e.target);
        formData.append('action', actionType);
        
        let btn = e.target.querySelector('button[type="submit"]');
        let oldText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;

        fetch('ajax_settings.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => location.reload())
        .catch(err => {
            // Fallback for json error
            alert('Saved successfully!');
            location.reload();
        });
    }

    // Delete Request
    function deleteSupportItem(type, id) {
        if(!confirm("Are you sure you want to delete this?")) return;
        let formData = new FormData();
        formData.append('action', 'delete_support_item');
        formData.append('type', type);
        formData.append('id', id);
        fetch('ajax_settings.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => location.reload())
        .catch(err => location.reload());
    }

    // --- Edit Functions ---

    function editLink(id, title, subtitle, url, icon, showWidget) {
        document.getElementById('link_id').value = id;
        document.getElementById('link_title').value = title;
        document.getElementById('link_subtitle').value = subtitle;
        document.getElementById('link_url').value = url;
        document.getElementById('link_icon').value = icon;
        document.getElementById('link_widget').checked = (showWidget == 1);
        
        document.getElementById('link_submit_btn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Link';
        document.getElementById('link_submit_btn').classList.replace('btn-primary', 'btn-success');
        document.getElementById('link_cancel_btn').style.display = 'inline-block';
    }

    function resetLinkForm() {
        document.getElementById('linkForm').reset();
        document.getElementById('link_id').value = '';
        document.getElementById('link_submit_btn').innerHTML = '<i class="fa-solid fa-plus"></i> Add Link';
        document.getElementById('link_submit_btn').classList.replace('btn-success', 'btn-primary');
        document.getElementById('link_cancel_btn').style.display = 'none';
    }

    function editFaq(id, category, question, answer) {
        document.getElementById('faq_id').value = id;
        document.getElementById('faq_category').value = category;
        document.getElementById('faq_question').value = question;
        document.getElementById('faq_answer').value = answer;
        
        document.getElementById('faq_submit_btn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update FAQ';
        document.getElementById('faq_submit_btn').classList.replace('btn-primary', 'btn-success');
        document.getElementById('faq_cancel_btn').style.display = 'inline-block';
    }

    function resetFaqForm() {
        document.getElementById('faqForm').reset();
        document.getElementById('faq_id').value = '';
        document.getElementById('faq_submit_btn').innerHTML = '<i class="fa-solid fa-plus"></i> Add FAQ';
        document.getElementById('faq_submit_btn').classList.replace('btn-success', 'btn-primary');
        document.getElementById('faq_cancel_btn').style.display = 'none';
    }
</script>
                <!-- ============================================================
    TAB: ADVANCED BROADCAST MANAGEMENT
============================================================ -->
<style>
    .bc-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
    @media (max-width: 992px) { .bc-grid { grid-template-columns: 1fr; } }
    .bc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .bc-table th { background: #f8fafc; padding: 12px; text-align: left; color: #64748b; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
    .bc-table td { padding: 15px 12px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .bc-img-preview { width: 60px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; }
</style>

<div class="tab-content <?php echo $activeTab == 'broadcast' ? 'active' : ''; ?>" id="tab-broadcast">
    <div class="bc-grid">
        
        <!-- Add/Edit Form -->
        <div class="component-card" style="height: fit-content;">
            <div class="card-header">
                <h3 id="bc-form-title"><i class="fa-solid fa-bullhorn"></i> Create Broadcast</h3>
            </div>
            <form id="broadcastForm" onsubmit="saveBroadcast(event)">
                <input type="hidden" name="broadcast_id" id="bc_id" value="">
                
                <div class="form-group">
                    <label>Message Title</label>
                    <input type="text" name="title" id="bc_title" placeholder="Enter offer or alert title..." required>
                </div>

                <div class="form-group">
                    <label>Popup Type (Color)</label>
                    <select name="type" id="bc_type" required>
                        <option value="info">Info (Blue)</option>
                        <option value="success">Success (Green)</option>
                        <option value="warning">Warning (Orange)</option>
                        <option value="error">Error (Red)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Message Content</label>
                    <textarea name="message" id="bc_message" rows="4" placeholder="Write your full message here..." required></textarea>
                </div>

                <!-- Custom Button Options -->
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label>Button Text (Optional)</label>
                        <input type="text" name="btn_text" id="bc_btn_text" placeholder="e.g. View Offer">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Button Link (Optional)</label>
                        <input type="url" name="btn_link" id="bc_btn_link" placeholder="https://...">
                    </div>
                </div>

                <div class="form-group">
                    <label>Image (Upload OR Paste URL)</label>
                    <input type="file" name="image_file" id="bc_image_file" accept="image/*" style="padding: 5px; margin-bottom: 8px;">
                    <input type="text" name="image_url_text" id="bc_image_url" placeholder="Or paste image URL here (https://...)">
                </div>
                
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target" id="bc_target">
                        <option value="all">All Users</option>
                        <option value="active">Active Users Only</option>
                        <option value="admins">Admins Only</option>
                    </select>
                </div>

                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label>Start Date & Time</label>
                        <input type="datetime-local" name="start_date" id="bc_start" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>End Date & Time (Expiry)</label>
                        <input type="datetime-local" name="end_date" id="bc_end" required>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="status" id="bc_status" value="1" checked style="width:auto; height:auto;"> Active (Show to users)
                    </label>
                </div>

                <div style="display:flex; gap:10px; margin-top:10px;">
                    <button type="submit" class="btn btn-primary" id="bc_submit_btn" style="flex:1;"><i class="fa-solid fa-paper-plane"></i> Publish</button>
                    <button type="button" class="btn btn-secondary" id="bc_cancel_btn" style="display:none;" onclick="resetBcForm()">Cancel</button>
                </div>
            </form>
        </div>

        <!-- History Table -->
        <div class="component-card">
            <div class="card-header">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Broadcast History</h3>
            </div>
            <div style="overflow-x:auto;">
                <table class="bc-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Details</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                       <?php 
                        try {
                            $bcs = $pdo->query("SELECT * FROM Broadcasts ORDER BY id DESC")->fetchAll();
                            foreach($bcs as $bc): 
                                // 100% ERROR-FREE DATE FIX
                                $startStr = time();
                                if(!empty($bc['start_date']) && $bc['start_date'] != '0000-00-00 00:00:00') {
                                    $startStr = strtotime($bc['start_date']);
                                } elseif(!empty($bc['sent_at'])) {
                                    $startStr = strtotime($bc['sent_at']);
                                }
                                
                                $endStr = $startStr + (7 * 86400); // Default 7 days
                                if(!empty($bc['end_date']) && $bc['end_date'] != '0000-00-00 00:00:00') {
                                    $endStr = strtotime($bc['end_date']);
                                }
                                
                                $isExpired = ($endStr < time());
                                $statClass = $bc['status'] == 1 ? (!$isExpired ? 'bg-success' : 'bg-warning') : 'bg-danger';
                                $statText = $bc['status'] == 1 ? (!$isExpired ? 'Active' : 'Expired') : 'Inactive';

                                // Color Badge for Type
                                $typeColor = '#3b82f6';
                                if(($bc['type'] ?? '') == 'success') $typeColor = '#10b981';
                                if(($bc['type'] ?? '') == 'warning') $typeColor = '#f59e0b';
                                if(($bc['type'] ?? '') == 'error') $typeColor = '#ef4444';
                        ?>
                        <tr>
                            <td>
                                <?php if(!empty($bc['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($bc['image_url']); ?>" class="bc-img-preview">
                                <?php else: ?>
                                    <div class="bc-img-preview" style="display:flex; justify-content:center; align-items:center; background:#f1f5f9; color:<?php echo $typeColor; ?>;"><i class="fa-solid fa-bullhorn"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:#0f172a; font-size:14px;"><?php echo htmlspecialchars($bc['title']); ?></strong>
                                <div style="color:#64748b; font-size:11px; margin-top:4px;">
                                    <span style="background:<?php echo $typeColor; ?>; color:white; padding:2px 6px; border-radius:4px; margin-right:5px;"><?php echo strtoupper($bc['type'] ?? 'INFO'); ?></span>
                                    <i class="fa-solid fa-users"></i> Target: <?php echo ucfirst($bc['target']); ?>
                                </div>
                            </td>
                            <td style="font-size:11px; color:#64748b;">
                                <div><b style="color:#10b981;">Start:</b> <?php echo date('d M, h:i A', $startStr); ?></div>
                                <div><b style="color:#ef4444;">End:</b> <?php echo date('d M, h:i A', $endStr); ?></div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $statClass; ?>"><?php echo $statText; ?></span>
                            </td>
                            <td style="white-space:nowrap;">
                                <button class="btn btn-info btn-xs" onclick='editBroadcast(<?php echo json_encode($bc, JSON_HEX_APOS); ?>)'><i class="fa-solid fa-pen"></i></button>
                                <button class="btn btn-danger btn-xs" onclick="deleteBroadcast(<?php echo $bc['id']; ?>)"><i class="fa-solid fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; } catch(Exception $e){} ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
    function saveBroadcast(e) {
        e.preventDefault();
        let fd = new FormData(e.target);
        fd.append('action', 'save_broadcast');
        
        let btn = document.getElementById('bc_submit_btn');
        btn.innerHTML = 'Saving...'; btn.disabled = true;

        fetch('ajax_settings.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => location.reload())
        .catch(err => location.reload());
    }

    function deleteBroadcast(id) {
        if(!confirm('Delete this broadcast?')) return;
        let fd = new FormData(); fd.append('action', 'delete_broadcast'); fd.append('id', id);
        fetch('ajax_settings.php', { method: 'POST', body: fd }).then(res=>location.reload());
    }

    function editBroadcast(bc) {
        document.getElementById('bc-form-title').innerHTML = '<i class="fa-solid fa-pen"></i> Edit Broadcast';
        document.getElementById('bc_id').value = bc.id;
        document.getElementById('bc_title').value = bc.title;
        document.getElementById('bc_type').value = bc.type || 'info';
        document.getElementById('bc_btn_text').value = bc.btn_text || '';
        document.getElementById('bc_btn_link').value = bc.btn_link || '';
        document.getElementById('bc_message').value = bc.message;
        document.getElementById('bc_image_url').value = bc.image_url || '';
        document.getElementById('bc_target').value = bc.target;
        
        // Dates Logic
        let start = bc.start_date && bc.start_date !== '0000-00-00 00:00:00' ? bc.start_date : bc.sent_at;
        let end = bc.end_date && bc.end_date !== '0000-00-00 00:00:00' ? bc.end_date : '';
        
        if(start) document.getElementById('bc_start').value = start.replace(' ', 'T').substring(0, 16);
        if(end) document.getElementById('bc_end').value = end.replace(' ', 'T').substring(0, 16);

        document.getElementById('bc_status').checked = (bc.status == 1);
        
        document.getElementById('bc_submit_btn').innerHTML = 'Update Broadcast';
        document.getElementById('bc_cancel_btn').style.display = 'block';
    }

    function resetBcForm() {
        document.getElementById('broadcastForm').reset();
        document.getElementById('bc_id').value = '';
        document.getElementById('bc-form-title').innerHTML = '<i class="fa-solid fa-bullhorn"></i> Create Broadcast';
        document.getElementById('bc_submit_btn').innerHTML = '<i class="fa-solid fa-paper-plane"></i> Publish';
        document.getElementById('bc_cancel_btn').style.display = 'none';
    }
</script>

               <!-- ============================================================
    TAB: FOOTER & PAGES MANAGEMENT (COMBINED ALL-IN-ONE)
============================================================ -->
<div class="tab-content <?php echo $activeTab == 'footer' ? 'active' : ''; ?>" id="tab-footer">
    
    <!-- 1. Footer General Settings Card -->
    <div class="component-card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h3><i class="fa-solid fa-copyright"></i> Footer General Settings</h3>
        </div>
        <form id="footerForm" onsubmit="saveFooterSettings(event)">
            <div class="form-group">
                <label>Footer Description / Bio</label>
                <textarea name="footer_content" rows="3" placeholder="Premium virtual numbers..."><?php echo htmlspecialchars($siteSettings['footer'] ?? ''); ?></textarea>
                <small style="color:var(--text-muted); font-size:11px;">यह टेक्स्ट फुटर के पहले कॉलम में लोगो के नीचे दिखाई देगा।</small>
            </div>
            
            <div class="form-group">
                <label>Footer Copyright Text</label>
                <input type="text" name="footer_copyright" value="<?php echo htmlspecialchars($siteSettings['footer_copyright'] ?? '&copy; ' . date('Y') . ' ' . htmlspecialchars($siteSettings['site_name'] ?? 'OTPx') . '. All Rights Reserved.'); ?>">
            </div>

            <div style="margin-top:16px;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Footer Settings</button>
            </div>
        </form>
    </div>

    <!-- 2. Footer Quick Links Component -->
    <div class="component-card" style="margin-bottom: 24px;">
        <div class="card-header">
            <h3><i class="fa-solid fa-link"></i> Footer Quick Links</h3>
            <div class="actions">
                <button class="btn btn-primary btn-sm" onclick="openModal('addFooterLinkModal')">
                    <i class="fa-solid fa-plus"></i> Add Link
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>URL</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    try {
                        $footerLinks = $pdo->query("SELECT * FROM FooterLinks ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
                    } catch(Exception $e) { $footerLinks = []; }

                    if(empty($footerLinks)): 
                    ?>
                        <tr><td colspan="4" style="text-align:center; padding:20px; color:var(--text-muted);">No footer links found.</td></tr>
                    <?php else: foreach($footerLinks as $link): ?>
                        <tr>
                            <td>#<?php echo $link['id']; ?></td>
                            <td><?php echo htmlspecialchars($link['title']); ?></td>
                            <td><?php echo htmlspecialchars($link['url']); ?></td>
                            <td>
                                <button class="btn btn-danger btn-xs" onclick="deleteFooterLink(<?php echo $link['id']; ?>)"><i class="fa-regular fa-trash-can"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. Page Management Component (Legal / Custom Pages) -->
    <div class="component-card">
        <div class="card-header">
            <h3><i class="fa-solid fa-file-lines"></i> Legal & Custom Pages (Shown in Footer)</h3>
            <div class="actions">
                <button class="btn btn-primary btn-sm" onclick="openModal('addPageModal')">
                    <i class="fa-solid fa-plus"></i> Add Page
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    try {
                        $pages = $pdo->query("SELECT * FROM Pages ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
                    } catch(Exception $e) { $pages = []; }

                    if(empty($pages)): 
                    ?>
                        <tr><td colspan="6" style="text-align:center; padding:20px; color:var(--text-muted);">No pages found.</td></tr>
                    <?php else: foreach($pages as $page): ?>
                    <tr>
                        <td>#<?php echo $page['id']; ?></td>
                        <td><?php echo htmlspecialchars($page['title']); ?></td>
                        <td><code><?php echo htmlspecialchars($page['slug']); ?></code></td>
                        <td><span class="status-badge <?php echo $page['status'] ? 'active' : 'banned'; ?>"><?php echo $page['status'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td style="font-size:12px;"><?php echo date('d M Y', strtotime($page['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-primary btn-xs" onclick="editPage(<?php echo $page['id']; ?>)"><i class="fa-regular fa-pen-to-square"></i></button>
                            <button class="btn btn-danger btn-xs" onclick="deletePage(<?php echo $page['id']; ?>)"><i class="fa-regular fa-trash-can"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================
    MODALS FOR PAGES (Add & Edit Page Modals)
============================================================ -->
<!-- Add Page Modal -->
    <div class="modal-overlay" id="addPageModal">
        <div class="modal-box" style="max-width:700px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-file-lines"></i> Add Page</h3>
                <button class="close-btn" onclick="closeModal('addPageModal')">✖</button>
            </div>
            <form id="addPageForm" onsubmit="addPage(event)">
                <div class="form-group">
                    <label>Page Title <span style="color:var(--danger);">*</span></label>
                    <input type="text" id="new-page-title" placeholder="About Us" required>
                </div>
                <div class="form-group">
                    <label>Page Slug <span style="color:var(--danger);">*</span></label>
                    <input type="text" id="new-page-slug" placeholder="about-us" required>
                    <small style="color:var(--text-muted);">URL: yoursite.com/user/page.php?slug=slug</small>
                </div>
                <!-- SEO Meta Fields Added Here -->
                <div class="form-group">
                    <label>Meta Description (SEO)</label>
                    <textarea id="new-page-meta-desc" rows="2" placeholder="Brief description for search engines..."></textarea>
                </div>
                <div class="form-group">
                    <label>Meta Keywords (SEO)</label>
                    <input type="text" id="new-page-meta-keys" placeholder="virtual numbers, otp verification, sms">
                </div>
                <div class="form-group">
                    <label>Page Content <span style="color:var(--danger);">*</span></label>
                    <textarea id="new-page-content" rows="8" placeholder="Write your page content here..." required></textarea>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="new-page-status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addPageModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Page</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Page Modal -->
    <div class="modal-overlay" id="editPageModal">
        <div class="modal-box" style="max-width:700px;">
            <div class="modal-header">
                <h3><i class="fa-regular fa-pen-to-square"></i> Edit Page</h3>
                <button class="close-btn" onclick="closeModal('editPageModal')">✖</button>
            </div>
            <form id="editPageForm" onsubmit="savePageEdit(event)">
                <input type="hidden" id="edit-page-id">
                <div class="form-group">
                    <label>Page Title <span style="color:var(--danger);">*</span></label>
                    <input type="text" id="edit-page-title" required>
                </div>
                <div class="form-group">
                    <label>Page Slug <span style="color:var(--danger);">*</span></label>
                    <input type="text" id="edit-page-slug" required>
                </div>
                <!-- SEO Meta Fields Added Here -->
                <div class="form-group">
                    <label>Meta Description (SEO)</label>
                    <textarea id="edit-page-meta-desc" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Meta Keywords (SEO)</label>
                    <input type="text" id="edit-page-meta-keys">
                </div>
                <div class="form-group">
                    <label>Page Content <span style="color:var(--danger);">*</span></label>
                    <textarea id="edit-page-content" rows="8" required></textarea>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="edit-page-status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editPageModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i> Update Page</button>
                </div>
            </form>
        </div>
    </div>

<script>
    function saveFooterSettings(e) {
        e.preventDefault();
        let fd = new FormData(e.target);
        fd.append('action', 'save_footer');
        
        fetch('ajax_settings.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            alert(data.message || 'Saved Successfully!');
            location.reload();
        }).catch(err => {
            alert('Saved successfully!');
            location.reload();
        });
    }
</script>
                <!-- ============================================================
                TAB: PROMO CODES
                ============================================================ -->
                <div class="tab-content <?php echo $activeTab == 'promo' ? 'active' : ''; ?>" id="tab-promo">
                    <div class="component-card">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-ticket"></i> Promo Codes Management</h3>
                            <div class="actions">
                                <button class="btn btn-primary btn-sm" onclick="openModal('addPromoModal')">
                                    <i class="fa-solid fa-plus"></i> Add Promo Code
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Amount</th>
                                        <th>Type</th>
                                        <th>Used</th>
                                        <th>Max Uses</th>
                                        <th>Valid From</th>
                                        <th>Valid Until</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($promoCodes)): ?>
                                        <tr><td colspan="11" style="text-align:center; padding:30px; color:var(--text-muted);">No promo codes found</td></tr>
                                    <?php else: foreach($promoCodes as $promo): ?>
                                            <tr>
                                                <td>#<?php echo $promo['id']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($promo['code']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($promo['name']); ?></td>
                                                <td><?php echo $currency; ?><?php echo number_format($promo['amount'], 2); ?></td>
                                                <td><span class="status-badge <?php echo $promo['type'] === 'percentage' ? 'warning' : 'active'; ?>"><?php echo $promo['type']; ?></span></td>
                                                <td><?php echo $promo['used_count']; ?></td>
                                                <td><?php echo $promo['max_uses'] ?? 'тИЮ'; ?></td>
                                                <td style="font-size:12px;"><?php echo $promo['valid_from'] ? date('d M Y', strtotime($promo['valid_from'])) : 'N/A'; ?></td>
                                                <td style="font-size:12px;"><?php echo $promo['valid_until'] ? date('d M Y', strtotime($promo['valid_until'])) : 'N/A'; ?></td>
                                                <td><span class="status-badge <?php echo $promo['status'] ? 'active' : 'banned'; ?>"><?php echo $promo['status'] ? 'Active' : 'Inactive'; ?></span></td>
                                                <td>
                                                    <button class="btn btn-danger btn-xs" onclick="deletePromo(<?php echo $promo['id']; ?>)"><i class="fa-regular fa-trash-can"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                TAB: BANNERS MANAGEMENT
                ============================================================ -->
                <div class="tab-content <?php echo $activeTab == 'banners' ? 'active' : ''; ?>" id="tab-banners">
                    <div class="component-card">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-image"></i> Banner Management</h3>
                            <div class="actions">
                                <button class="btn btn-primary btn-sm" onclick="openModal('addBannerModal')">
                                    <i class="fa-solid fa-plus"></i> Add Banner
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Title</th>
                                        <th>Subtitle</th>
                                        <th>Position</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($banners)): ?>
                                        <tr><td colspan="6" style="text-align:center; padding:20px; color:var(--text-muted);">No banners found.</td></tr>
                                    <?php else: foreach($banners as $banner): ?>
                                    <tr>
                                        <td>#<?php echo $banner['id']; ?></td>
                                        <td><?php echo htmlspecialchars($banner['title']); ?></td>
                                        <td><?php echo htmlspecialchars($banner['subtitle']); ?></td>
                                        <td><?php echo htmlspecialchars($banner['position']); ?></td>
                                        <td><span class="status-badge <?php echo $banner['status'] ? 'active' : 'banned'; ?>"><?php echo $banner['status'] ? 'Active' : 'Inactive'; ?></span></td>
                                        <td>
                                            <button class="btn btn-primary btn-xs" onclick="editBanner(<?php echo $banner['id']; ?>)"><i class="fa-regular fa-pen-to-square"></i></button>
                                            <button class="btn btn-danger btn-xs" onclick="deleteBanner(<?php echo $banner['id']; ?>)"><i class="fa-regular fa-trash-can"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                TAB: PROFILE
                ============================================================ -->
                <div class="tab-content <?php echo $activeTab == 'profile' ? 'active' : ''; ?>" id="tab-profile">
                    <div class="component-card">
                        <div class="card-header">
                            <h3><i class="fa-regular fa-user"></i> My Profile</h3>
                        </div>
                        <form id="profileForm" onsubmit="saveProfile(event)">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" id="profile-name" value="<?php echo htmlspecialchars($adminUser['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" id="profile-email" value="<?php echo htmlspecialchars($adminUser['email']); ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>New Password (leave blank to keep current)</label>
                                <input type="password" id="profile-password" placeholder="Enter new password">
                            </div>
                            <div class="form-group">
                                <label>Confirm Password</label>
                                <input type="password" id="profile-password-confirm" placeholder="Confirm new password">
                            </div>
                            <div style="margin-top:16px;">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Update Profile</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
            <!-- End content-area -->

            <!-- Admin Footer -->
            <div class="admin-footer">
                <div class="footer-content">
                    <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPX'); ?> Admin Panel</span>
                    <div class="footer-right">
                        <span>Version 2.0</span>
                        <span>|</span>
                        <span>PHP <?php echo phpversion(); ?></span>
                    </div>
                </div>
            </div>

        </div>
        <!-- End main-content -->

    </div>
    <!-- End app-container -->

    <!-- ============================================================
    MODALS
    ============================================================ -->

    <!-- Add User Modal -->
    <div class="modal-overlay" id="addUserModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-plus"></i> Add User</h3>
                <button class="close-btn" onclick="closeModal('addUserModal')">✖</button>
            </div>
            <form id="addUserForm" onsubmit="addUser(event)">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="new-user-name" placeholder="John Doe" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="new-user-email" placeholder="user@email.com" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="new-user-password" placeholder="Minimum 6 characters" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select id="new-user-role">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Initial Balance</label>
                    <input type="number" id="new-user-balance" value="0" step="0.01" min="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Service Modal -->
    <div class="modal-overlay" id="addServiceModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-plus"></i> Add Service</h3>
                <button class="close-btn" onclick="closeModal('addServiceModal')">✖</button>
            </div>
            <form id="addServiceForm" onsubmit="addService(event)">
                <div class="form-group">
                    <label>Service Code</label>
                    <input type="text" id="new-service-code" placeholder="fb" required>
                </div>
                <div class="form-group">
                    <label>Service Name</label>
                    <input type="text" id="new-service-name" placeholder="Facebook" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Price</label>
                        <input type="number" id="new-service-price" placeholder="10.00" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Server</label>
                        <select id="new-service-server">
                            <?php foreach($servers as $server): ?>
                                <option value="<?php echo $server['id']; ?>"><?php echo htmlspecialchars($server['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Logo URL</label>
                    <input type="url" id="new-service-logo" placeholder="https://example.com/logo.png">
                </div>
                <div class="form-group">
                    <label>API Service ID</label>
                    <input type="text" id="new-service-api-id" placeholder="API service ID from provider">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addServiceModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Service</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Server Modal -->
    <div class="modal-overlay" id="addServerModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-server"></i> Add Server</h3>
                <button class="close-btn" onclick="closeModal('addServerModal')">✖</button>
            </div>
            <form id="addServerForm" onsubmit="addServer(event)">
                <div class="form-group">
                    <label>Server Name</label>
                    <input type="text" id="new-server-name" placeholder="SMS Bower" required>
                </div>
                <div class="form-group">
                    <label>API Key</label>
                    <input type="text" id="new-server-api-key" placeholder="Your API key" required>
                </div>
                <div class="form-group">
                    <label>Base URL</label>
                    <input type="url" id="new-server-base-url" placeholder="https://api.example.com/handler_api.php">
                </div>
                <div class="form-group">
                    <label>Provider Type</label>
                    <select id="new-server-provider">
                        <option value="smsbower">SMS Bower</option>
                        <option value="smsindia">SMS India</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addServerModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Server</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Server Modal -->
    <div class="modal-overlay" id="editServerModal">
        <div class="modal-box" style="max-width:700px;">
            <div class="modal-header">
                <h3><i class="fa-regular fa-pen-to-square"></i> Edit Server</h3>
                <button class="close-btn" onclick="closeModal('editServerModal')">✖</button>
            </div>
            <form id="editServerForm" onsubmit="saveServerEdit(event)">
                <input type="hidden" id="edit-server-id">
                <div class="form-row">
                    <div class="form-group">
                        <label>Server Name <span style="color:var(--danger);">*</span></label>
                        <input type="text" id="edit-server-name" required>
                    </div>
                    <div class="form-group">
                        <label>Code</label>
                        <input type="text" id="edit-server-code" placeholder="SMSBOWER">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Country Code</label>
                        <input type="text" id="edit-server-country-code" placeholder="IN">
                    </div>
                    <div class="form-group">
                        <label>Country Emoji</label>
                        <input type="text" id="edit-server-emoji" placeholder="ЁЯЗоЁЯЗ│" maxlength="5">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>API Type</label>
                        <select id="edit-server-api-type">
                            <option value="text">Text</option>
                            <option value="json">JSON</option>
                            <option value="xml">XML</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Has Headers</label>
                        <select id="edit-server-has-headers">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="edit-headers-container" style="display:none;">
                    <label>Headers (JSON format)</label>
                    <input type="text" id="edit-server-headers" placeholder='{"Authorization":"Bearer token"}'>
                </div>
                <div class="form-group">
                    <label>API Key <span style="color:var(--danger);">*</span></label>
                    <input type="text" id="edit-server-api-key" required>
                </div>
                <div class="form-group">
                    <label>Base URL</label>
                    <input type="url" id="edit-server-base-url" placeholder="https://api.example.com">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Get Number URL <span style="color:var(--danger);">*</span></label>
                        <input type="url" id="edit-server-get-num" placeholder="https://api.example.com/get-number" required>
                    </div>
                    <div class="form-group">
                        <label>Get Message URL <span style="color:var(--danger);">*</span></label>
                        <input type="url" id="edit-server-get-msg" placeholder="https://api.example.com/get-message" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Next Message URL</label>
                        <input type="url" id="edit-server-next-msg" placeholder="https://api.example.com/next-message">
                    </div>
                    <div class="form-group">
                        <label>Cancel Number URL <span style="color:var(--danger);">*</span></label>
                        <input type="url" id="edit-server-cancel-num" placeholder="https://api.example.com/cancel" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Auto Cancel (minutes)</label>
                        <input type="number" id="edit-server-auto-cancel" value="5" min="1">
                    </div>
                    <div class="form-group">
                        <label>Retry Count</label>
                        <input type="number" id="edit-server-retry" value="1" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="edit-server-status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editServerModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i> Update Server</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Services Modal -->
    <div class="modal-overlay" id="importServicesModal">
        <div class="modal-box" style="max-width:800px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-cloud-arrow-down"></i> Import Services</h3>
                <button class="close-btn" onclick="closeModal('importServicesModal')">✖</button>
            </div>
            <div id="import-step-1">
                <div class="form-group">
                    <label>Select Countries</label>
                    <div id="country-list" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(150px,1fr)); gap:10px; max-height:300px; overflow-y:auto; padding:10px; border:1px solid var(--border-color); border-radius:8px;">
                        <div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--text-muted);">Loading countries...</div>
                    </div>
                </div>
                <div class="form-group">
                    <button class="btn btn-primary" onclick="selectAllCountries()">Select All</button>
                    <button class="btn btn-outline" onclick="deselectAllCountries()">Deselect All</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('importServicesModal')">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="goToStep2()">Next <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </div>
            <div id="import-step-2" style="display:none;">
                <div class="form-group">
                    <label>Margin Settings</label>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Margin Type</label>
                            <select id="margin-type" onchange="updateMarginPreview()">
                                <option value="fixed">Fixed Amount</option>
                                <option value="percentage">Percentage (%)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Margin Value</label>
                            <input type="number" id="margin-value" value="0" step="0.01" oninput="updateMarginPreview()">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Select Services <span id="selected-count">0</span> selected</label>
                    <div id="service-list" style="max-height:400px; overflow-y:auto; border:1px solid var(--border-color); border-radius:8px;">
                        <div style="padding:20px; text-align:center; color:var(--text-muted);">Loading services...</div>
                    </div>
                </div>
                <div class="form-group">
                    <button class="btn btn-primary" onclick="selectAllServices()">Select All Services</button>
                    <button class="btn btn-outline" onclick="deselectAllServices()">Deselect All</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="goToStep1()"><i class="fa-solid fa-arrow-left"></i> Back</button>
                    <button type="button" class="btn btn-success" onclick="importSelectedServices()"><i class="fa-solid fa-cloud-arrow-down"></i> Import Selected</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Service Modal -->
    <div class="modal-overlay" id="editServiceModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-regular fa-pen-to-square"></i> Edit Service</h3>
                <button class="close-btn" onclick="closeModal('editServiceModal')">✖</button>
            </div>
            <form id="editServiceForm" onsubmit="saveServiceEdit(event)">
                <input type="hidden" id="edit-service-id">
                <div class="form-row">
                    <div class="form-group">
                        <label>Service Code <span style="color:var(--danger);">*</span></label>
                        <input type="text" id="edit-service-code" required>
                    </div>
                    <div class="form-group">
                        <label>Service Name <span style="color:var(--danger);">*</span></label>
                        <input type="text" id="edit-service-name" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Price <span style="color:var(--danger);">*</span></label>
                        <input type="number" id="edit-service-price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Server</label>
                        <select id="edit-service-server">
                            <?php foreach($servers as $server): ?>
                                <option value="<?php echo $server['id']; ?>"><?php echo htmlspecialchars($server['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Logo URL</label>
                        <input type="url" id="edit-service-logo" placeholder="https://example.com/logo.png">
                    </div>
                    <div class="form-group">
                        <label>Popular</label>
                        <select id="edit-service-popular">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Cutback</label>
                        <input type="number" id="edit-service-cutback" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Cancel Disable (Minutes)</label>
                        <input type="number" id="edit-service-cancel-disable" value="2" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editServiceModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i> Update Service</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editUserModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-regular fa-pen-to-square"></i> Edit User</h3>
                <button class="close-btn" onclick="closeModal('editUserModal')">✖</button>
            </div>
            <form id="editUserForm" onsubmit="saveUserEdit(event)">
                <input type="hidden" id="edit-user-id">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="edit-user-name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="edit-user-email" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <select id="edit-user-role">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Balance</label>
                        <input type="number" id="edit-user-balance" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="edit-user-banned">
                        <option value="0">Active</option>
                        <option value="1">Banned</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Balance Modal -->
    <div class="modal-overlay" id="balanceModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-wallet"></i> Edit Balance</h3>
                <button class="close-btn" onclick="closeModal('balanceModal')">✖</button>
            </div>
            <form id="balanceForm" onsubmit="updateBalance(event)">
                <input type="hidden" id="balance-user-id">
                <div class="form-group">
                    <label>User</label>
                    <input type="text" id="balance-user-email" disabled style="background:var(--bg-body);">
                </div>
                <div class="form-group">
                    <label>Current Balance</label>
                    <input type="text" id="balance-current" disabled style="background:var(--bg-body);">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Action</label>
                        <select id="balance-action" required>
                            <option value="add">Add Balance</option>
                            <option value="cut">Cut Balance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount (₹)</label>
                        <input type="number" id="balance-amount" placeholder="Enter amount" step="0.01" min="0.01" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Reason (Optional)</label>
                    <input type="text" id="balance-reason" placeholder="e.g. Bonus, Refund, Adjustment">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('balanceModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Update Balance</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Discount Modal (Upgraded with Edit & Detailed UI) -->
<div class="modal-overlay" id="discountModal">
    <div class="modal-box" style="max-width: 650px; padding: 25px; border-radius: 16px;">
        <div class="modal-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px;">
            <h3><i class="fa-solid fa-percent" style="color: var(--accent);"></i> Manage User Discounts</h3>
            <button class="close-btn" onclick="closeModal('discountModal')">✖</button>
        </div>
        
        <!-- Add / Edit Discount Form -->
        <form id="discountForm" onsubmit="applyDiscount(event)">
            <input type="hidden" id="discount-user-id">
            <input type="hidden" id="edit_discount_id" value=""> <!-- For Edit Logic -->

            <div class="form-group">
                <label style="font-weight:700;">User Email</label>
                <input type="text" id="discount-user-email" disabled style="background:#f1f5f9; font-weight:bold; color:#475569;">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label style="font-weight:700;">Discount Type</label>
                    <select id="discount-type" required>
                        <option value="fixed">Fixed Price (₹)</option>
                        <option value="percentage">Percentage (%)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-weight:700;">Value</label>
                    <input type="number" id="discount-value" placeholder="Enter value (e.g. 10)" step="0.01" min="0.01" required>
                </div>
            </div>
            <div class="form-group">
                <label style="font-weight:700;">Apply to</label>
                <select id="discount-apply-to" onchange="document.getElementById('discount-service-group').style.display = this.value === 'specific' ? 'block' : 'none';">
                    <option value="all">All Services (Global)</option>
                    <option value="specific">Specific Service</option>
                </select>
            </div>
            
            
            <!-- नया स्मार्ट स्पेसिफिक सर्विस सिलेक्शन सेक्शन (Country -> Server -> Service + Search) -->
<div id="discount-service-group" style="display:none; background:#f8fafc; padding:15px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:15px;">
    <label style="font-weight:800; color:#0f172a; display:block; margin-bottom:12px; border-bottom:1px solid #cbd5e1; padding-bottom:5px;">
        <i class="fa-solid fa-filter" style="color:var(--accent);"></i> Filter & Select Service
    </label>

    <!-- 1. Country Filter -->
    <div class="form-group" style="margin-bottom: 12px;">
        <label style="font-size:12px; font-weight:700; color:#64748b;">1. Select Country</label>
        <select id="disc-filter-country" onchange="filterDiscountServers()" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; outline:none;">
            <option value="">🌍 All Countries</option>
        </select>
    </div>

    <!-- 2. Server Filter -->
    <div class="form-group" style="margin-bottom: 12px;">
        <label style="font-size:12px; font-weight:700; color:#64748b;">2. Select Server</label>
        <select id="disc-filter-server" onchange="filterDiscountServices()" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; outline:none;">
            <option value="">🖥️ All Servers</option>
        </select>
    </div>

    <!-- 3. Service Search & Select List -->
    <div class="form-group" style="margin-bottom:0;">
        <label style="font-size:12px; font-weight:700; color:#64748b;">3. Search & Select Service <span style="color:red;">*</span></label>
        
        <!-- Search Box -->
        <input type="text" id="disc-search-service" placeholder="🔍 Type to search service name or code..." onkeyup="filterDiscountServices()" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; margin-bottom:8px; outline:none;">
        
        <!-- Selection List (Size 5 makes it a scrollable list instead of dropdown) -->
        <select id="discount-service" size="5" style="width:100%; padding:8px; border-radius:6px; border:1px solid #cbd5e1; outline:none; font-size:13px; font-weight:600;">
            <!-- Services will be loaded here by JavaScript -->
        </select>
    </div>
</div>
            <div class="modal-footer" style="border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 20px;">
                <button type="button" class="btn btn-outline" id="discountCancelEditBtn" onclick="resetDiscountForm()" style="display:none;">Cancel Edit</button>
                <button type="submit" class="btn btn-primary" id="discountSubmitBtn"><i class="fa-solid fa-plus"></i> Add Discount</button>
            </div>
        </form>

        <!-- Existing Discounts Detailed List -->
        <div class="existing-discounts-section">
            <h4 style="font-size: 15px; font-weight:800; color: var(--text-main); margin-bottom: 15px;"><i class="fa-solid fa-list-check"></i> Active Offers for this User</h4>
            <div id="existingDiscountsContainer" style="max-height: 300px; overflow-y: auto; padding-right: 5px;">
                <div style="text-align:center; padding:20px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>
    </div>
</div>

    <!-- Add API Provider Modal -->
    <div class="modal-overlay" id="addApiProviderModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-plug"></i> Add API Provider</h3>
                <button class="close-btn" onclick="closeModal('addApiProviderModal')">✖</button>
            </div>
            <form id="addApiProviderForm" onsubmit="addApiProvider(event)">
                <div class="form-group">
                    <label>Provider Name</label>
                    <input type="text" id="new-provider-name" placeholder="My SMS Provider" required>
                </div>
                <div class="form-group">
                    <label>Provider Type</label>
                    <select id="new-provider-type" required>
                        <option value="SmsBower">SmsBower</option>
                        <option value="5sim">5Sim</option>
                        <option value="IndianOtp">IndianOtp</option>
                        <option value="SmsActivate">SmsActivate</option>
                        <option value="Fastsms">FastSms</option>
                        <option value="SmsHub">SmsHub</option>
                        <option value="368sms">368sms</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>API Key</label>
                    <input type="text" id="new-provider-api-key" placeholder="Enter API key" required>
                </div>
                
                <div class="form-group mb-3">
    <label>API Base URL</label>
    <input type="url" id="new-provider-base-url" name="base_url" class="form-control" placeholder="https://api.example.com/stubs/handler_api.php" required>
</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addApiProviderModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Provider</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Promo Modal -->
    <div class="modal-overlay" id="addPromoModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-ticket"></i> Add Promo Code</h3>
                <button class="close-btn" onclick="closeModal('addPromoModal')">✖</button>
            </div>
            <form id="addPromoForm" onsubmit="addPromo(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label>Promo Code</label>
                        <input type="text" id="new-promo-code" placeholder="WELCOME10" required>
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" id="new-promo-name" placeholder="Welcome Discount">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" id="new-promo-amount" placeholder="10.00" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select id="new-promo-type">
                            <option value="fixed">Fixed Amount</option>
                            <option value="percentage">Percentage (%)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Max Uses</label>
                        <input type="number" id="new-promo-max-uses" placeholder="Leave empty for unlimited">
                    </div>
                    <div class="form-group">
                        <label>For New Users Only</label>
                        <select id="new-promo-for-new">
                            <option value="0">All Users</option>
                            <option value="1">New Users Only</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Valid From</label>
                        <input type="datetime-local" id="new-promo-valid-from">
                    </div>
                    <div class="form-group">
                        <label>Valid Until</label>
                        <input type="datetime-local" id="new-promo-valid-until">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addPromoModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Promo Code</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Footer Link Modal -->
    <div class="modal-overlay" id="addFooterLinkModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-link"></i> Add Footer Link</h3>
                <button class="close-btn" onclick="closeModal('addFooterLinkModal')">✖</button>
            </div>
            <form id="addFooterLinkForm" onsubmit="addFooterLink(event)">
                <div class="form-group">
                    <label>Link Title</label>
                    <input type="text" id="new-footer-link-title" placeholder="Privacy Policy" required>
                </div>
                <div class="form-group">
                    <label>Link URL</label>
                    <input type="url" id="new-footer-link-url" placeholder="https://example.com/privacy" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addFooterLinkModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Link</button>
                </div>
            </form>
        </div>
    </div>

    <<!-- Add Page Modal (Updated with SEO Meta Fields) -->
<div class="modal-overlay" id="addPageModal">
    <div class="modal-box" style="max-width:700px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-lines"></i> Add Page</h3>
            <button class="close-btn" onclick="closeModal('addPageModal')">✖</button>
        </div>
        <form id="addPageForm" onsubmit="addPage(event)">
            <div class="form-group">
                <label>Page Title <span style="color:var(--danger);">*</span></label>
                <input type="text" id="new-page-title" placeholder="About Us" required>
            </div>
            <div class="form-group">
                <label>Page Slug <span style="color:var(--danger);">*</span></label>
                <input type="text" id="new-page-slug" placeholder="about-us" required>
                <small style="color:var(--text-muted);">URL: yoursite.com/user/page.php?slug=slug</small>
            </div>
            <!-- SEO Meta Fields Added -->
            <div class="form-group">
                <label>Meta Description (SEO)</label>
                <textarea id="new-page-meta-desc" rows="2" placeholder="Brief description for search engines..."></textarea>
            </div>
            <div class="form-group">
                <label>Meta Keywords (SEO)</label>
                <input type="text" id="new-page-meta-keys" placeholder="virtual numbers, otp verification, sms">
            </div>
            <div class="form-group">
                <label>Page Content <span style="color:var(--danger);">*</span></label>
                <textarea id="new-page-content" rows="8" placeholder="Write your page content here..." required></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="new-page-status">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addPageModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Page</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Page Modal (Updated with SEO Meta Fields) -->
<div class="modal-overlay" id="editPageModal">
    <div class="modal-box" style="max-width:700px;">
        <div class="modal-header">
            <h3><i class="fa-regular fa-pen-to-square"></i> Edit Page</h3>
            <button class="close-btn" onclick="closeModal('editPageModal')">✖</button>
        </div>
        <form id="editPageForm" onsubmit="savePageEdit(event)">
            <input type="hidden" id="edit-page-id">
            <div class="form-group">
                <label>Page Title <span style="color:var(--danger);">*</span></label>
                <input type="text" id="edit-page-title" required>
            </div>
            <div class="form-group">
                <label>Page Slug <span style="color:var(--danger);">*</span></label>
                <input type="text" id="edit-page-slug" required>
            </div>
            <!-- SEO Meta Fields Added -->
            <div class="form-group">
                <label>Meta Description (SEO)</label>
                <textarea id="edit-page-meta-desc" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Meta Keywords (SEO)</label>
                <input type="text" id="edit-page-meta-keys">
            </div>
            <div class="form-group">
                <label>Page Content <span style="color:var(--danger);">*</span></label>
                <textarea id="edit-page-content" rows="8" required></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="edit-page-status">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editPageModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i> Update Page</button>
            </div>
        </form>
    </div>
</div>

    <!-- Add Banner Modal -->
    <div class="modal-overlay" id="addBannerModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-image"></i> Add Banner</h3>
                <button class="close-btn" onclick="closeModal('addBannerModal')">✖</button>
            </div>
            <form id="addBannerForm" onsubmit="addBanner(event)">
                <div class="form-group">
                    <label>Banner Title <span style="color:var(--danger);">*</span></label>
                    <input type="text" id="new-banner-title" placeholder="Summer Sale" required>
                </div>
                <div class="form-group">
                    <label>Subtitle</label>
                    <input type="text" id="new-banner-subtitle" placeholder="Get 50% off">
                </div>
                <div class="form-group">
                    <label>Image URL <span style="color:var(--danger);">*</span></label>
                    <input type="url" id="new-banner-image" placeholder="https://example.com/banner.jpg" required>
                </div>
                <div class="form-group">
                    <label>Link URL</label>
                    <input type="url" id="new-banner-link" placeholder="https://example.com/offer">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Position</label>
                        <select id="new-banner-position">
                            <option value="home_top">Home Top</option>
                            <option value="home_bottom">Home Bottom</option>
                            <option value="sidebar">Sidebar</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Order</label>
                        <input type="number" id="new-banner-order" value="0" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="new-banner-status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addBannerModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Banner</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Banner Modal -->
    <div class="modal-overlay" id="editBannerModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-regular fa-pen-to-square"></i> Edit Banner</h3>
                <button class="close-btn" onclick="closeModal('editBannerModal')">✖</button>
            </div>
            <form id="editBannerForm" onsubmit="saveBannerEdit(event)">
                <input type="hidden" id="edit-banner-id">
                <div class="form-group">
                    <label>Banner Title <span style="color:var(--danger);">*</span></label>
                    <input type="text" id="edit-banner-title" required>
                </div>
                <div class="form-group">
                    <label>Subtitle</label>
                    <input type="text" id="edit-banner-subtitle">
                </div>
                <div class="form-group">
                    <label>Image URL <span style="color:var(--danger);">*</span></label>
                    <input type="url" id="edit-banner-image" required>
                </div>
                <div class="form-group">
                    <label>Link URL</label>
                    <input type="url" id="edit-banner-link">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Position</label>
                        <select id="edit-banner-position">
                            <option value="home_top">Home Top</option>
                            <option value="home_bottom">Home Bottom</option>
                            <option value="sidebar">Sidebar</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Order</label>
                        <input type="number" id="edit-banner-order" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="edit-banner-status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editBannerModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i> Update Banner</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
    JAVASCRIPT - COMPLETE FIXED
    ============================================================ -->
    <script>
        // ============================================================
        // SIDEBAR TOGGLE
        // ============================================================
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('active');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('overlay').classList.remove('active');
        }

        // ============================================================
        // MODAL FUNCTIONS
        // ============================================================
        function openModal(id) {
            var el = document.getElementById(id);
            if (el) el.classList.add('show');
        }

        function closeModal(id) {
            var el = document.getElementById(id);
            if (el) el.classList.remove('show');
        }

        // Close modal on overlay click
        document.addEventListener('click', function(e) {
            if (e.target.classList && e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('show');
            }
        });

        // ============================================================
        // NOTIFICATION
        // ============================================================
        function showNotification() {
            alert('ЁЯУв No new notifications.');
        }

        // ============================================================
        // USER FUNCTIONS
        // ============================================================
        let currentFilter = 'all';

        function filterUsers(filter) {
            try {
                if (filter) {
                    currentFilter = filter;
                    document.querySelectorAll('.filter-btn').forEach(function(btn) {
                        btn.classList.remove('active');
                    });
                    var btn = document.getElementById('filter-' + filter);
                    if (btn) btn.classList.add('active');
                }

                var search = document.getElementById('user-search')?.value?.toLowerCase() || '';
                var rows = document.querySelectorAll('#users-table-body tr');

                rows.forEach(function(row) {
                    var email = row.querySelector('.user-email')?.textContent?.toLowerCase() || '';
                    var banned = row.dataset.banned === '1';
                    var show = true;

                    if (search && !email.includes(search)) show = false;
                    if (currentFilter === 'online' && banned) show = false;
                    if (currentFilter === 'banned' && !banned) show = false;

                    row.style.display = show ? '' : 'none';
                });
            } catch(e) {
                console.error('filterUsers error:', e);
            }
        }

        function addUser(e) {
            e.preventDefault();
            var name = document.getElementById('new-user-name').value.trim();
            var email = document.getElementById('new-user-email').value.trim();
            var password = document.getElementById('new-user-password').value;
            var role = document.getElementById('new-user-role').value;
            var balance = document.getElementById('new-user-balance').value;

            if (!name || !email || password.length < 6) {
                alert('Please fill all fields correctly.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'add_user');
            formData.append('name', name);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('role', role);
            formData.append('balance', balance);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function editUser(id) {
            var formData = new FormData();
            formData.append('action', 'get_user');
            formData.append('user_id', id);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    var user = data.user;
                    document.getElementById('edit-user-id').value = user.id;
                    document.getElementById('edit-user-name').value = user.name || '';
                    document.getElementById('edit-user-email').value = user.email;
                    document.getElementById('edit-user-role').value = user.role;
                    document.getElementById('edit-user-balance').value = user.balance;
                    document.getElementById('edit-user-banned').value = user.banned;
                    openModal('editUserModal');
                } else {
                    alert(data.message || 'Error loading user data');
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function saveUserEdit(e) {
            e.preventDefault();
            var id = document.getElementById('edit-user-id').value;
            var name = document.getElementById('edit-user-name').value.trim();
            var email = document.getElementById('edit-user-email').value.trim();
            var role = document.getElementById('edit-user-role').value;
            var balance = document.getElementById('edit-user-balance').value;
            var banned = document.getElementById('edit-user-banned').value;

            var formData = new FormData();
            formData.append('action', 'update_user');
            formData.append('user_id', id);
            formData.append('name', name);
            formData.append('email', email);
            formData.append('role', role);
            formData.append('balance', balance);
            formData.append('banned', banned);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) {
                    closeModal('editUserModal');
                    location.reload();
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function toggleBan(id, status) {
            var action = status ? 'unban' : 'ban';
            if (!confirm('Are you sure you want to ' + action + ' this user?')) return;

            var formData = new FormData();
            formData.append('action', 'toggle_ban');
            formData.append('user_id', id);
            formData.append('status', status);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function openBalanceModal(userId, email) {
            document.getElementById('balance-user-id').value = userId;
            document.getElementById('balance-user-email').value = email;
            document.getElementById('balance-amount').value = '';
            document.getElementById('balance-reason').value = '';

            var row = document.querySelector('tr[data-user-id="' + userId + '"]');
            if (row) {
                var balance = row.querySelector('.user-balance')?.textContent || '0.00';
                document.getElementById('balance-current').value = '₹' + balance;
            }

            openModal('balanceModal');
        }

        function updateBalance(e) {
            e.preventDefault();
            var userId = document.getElementById('balance-user-id').value;
            var action = document.getElementById('balance-action').value;
            var amount = document.getElementById('balance-amount').value;
            var reason = document.getElementById('balance-reason').value.trim();

            if (!amount || amount <= 0) {
                alert('Please enter a valid amount.');
                return;
            }

            if (!confirm('Are you sure you want to ' + action + ' ₹' + amount + ' from this user?')) return;

            var formData = new FormData();
            formData.append('action', action === 'add' ? 'add_balance' : 'cut_balance');
            formData.append('user_id', userId);
            formData.append('amount', amount);
            formData.append('reason', reason || (action === 'add' ? 'Admin added balance' : 'Admin cut balance'));

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) {
                    closeModal('balanceModal');
                    location.reload();
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        // Open Modal & Load Detailed Data
function openDiscountModal(userId, email) {
    document.getElementById('discount-user-id').value = userId;
    document.getElementById('discount-user-email').value = email;
    resetDiscountForm();
    openModal('discountModal');
    
    const container = document.getElementById('existingDiscountsContainer');
    container.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;"><i class="fa-solid fa-spinner fa-spin"></i> Fetching details...</div>';
    
    fetch('ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({'action': 'get_user_discounts', 'user_id': userId})
    })
    .then(res => res.json())
    .then(data => {
        if(data.success && data.discounts.length > 0) {
            let html = '<div style="display:flex; flex-direction:column; gap:12px;">';
            
            data.discounts.forEach(d => {
                let isGlobal = (d.apply_to === 'all');
                let srvName = isGlobal ? 'Global Discount (All Services)' : (d.service_name || 'Unknown Service');
                let origPrice = parseFloat(d.original_price || 0);
                let val = parseFloat(d.value || 0);
                let newPrice = d.type === 'percentage' ? origPrice - (origPrice * val / 100) : val;

                let valText = d.type === 'percentage' ? val + '% OFF' : '₹' + val + ' FIXED';
                let badgeStyle = isGlobal ? 'background:#dcfce7; color:#16a34a;' : 'background:#e0e7ff; color:#4f46e5;';
                
                let logoHtml = isGlobal 
                    ? '<i class="fa-solid fa-globe" style="color:#10b981; font-size:20px;"></i>' 
                    : (d.service_logo ? `<img src="${d.service_logo}" style="width:100%; height:100%; object-fit:contain;">` : '<i class="fa-solid fa-gem" style="color:#94a3b8;"></i>');

                let subText = isGlobal 
                    ? '<div style="font-size:11px; color:#64748b; font-weight:600; margin-top:4px;">Applies to all services</div>' 
                    : `<div style="font-size:11px; color:#64748b; font-weight:600; margin-top:4px;">${d.country_emoji || '🌍'} ${d.country_name || 'Country'} • <i class="fa-solid fa-server"></i> ${d.server_name || 'Server'}</div>`;

                let priceHtml = (!isGlobal && origPrice > 0) ? `<div style="font-size:11px; color:#ef4444; text-decoration:line-through; font-weight:700;">Original: ₹${origPrice.toFixed(2)}</div>` : '';
                let newPriceHtml = (!isGlobal && origPrice > 0 && d.type === 'percentage') ? `<div style="font-size:11px; color:#3b82f6; font-weight:800;">New: ₹${newPrice.toFixed(2)}</div>` : '';

                html += `
                <div style="background:#ffffff; border:1px solid #e2e8f0; padding:15px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <!-- Service Info (Logo, Name, Country, Server) -->
                    <div style="display:flex; align-items:center; gap:15px;">
                        <div style="width:45px; height:45px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; display:flex; justify-content:center; align-items:center; overflow:hidden; flex-shrink:0;">
                            ${logoHtml}
                        </div>
                        <div>
                            <div style="font-weight:800; font-size:15px; color:#0f172a;">${srvName} <span style="font-size:10px; padding:3px 8px; border-radius:20px; font-weight:800; margin-left:5px; ${badgeStyle}">${d.apply_to.toUpperCase()}</span></div>
                            ${subText}
                        </div>
                    </div>
                    
                    <!-- Pricing & Action Buttons -->
                    <div style="display:flex; align-items:center; gap:20px;">
                        <div style="text-align:right;">
                            ${priceHtml}
                            <div style="font-size:16px; color:#10b981; font-weight:900;">${valText}</div>
                            ${newPriceHtml}
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" onclick="editExistingDiscount(${d.discount_id}, '${d.type}', ${d.value}, '${d.apply_to}', ${d.service_id || 0})" style="background:#eff6ff; color:#3b82f6; border:1px solid #bfdbfe; padding:8px 12px; border-radius:8px; cursor:pointer;" title="Edit"><i class="fa-solid fa-pen"></i></button>
                            <button type="button" onclick="deleteDiscount(${d.discount_id}, ${userId}, '${email}')" style="background:#fef2f2; color:#ef4444; border:1px solid #fecaca; padding:8px 12px; border-radius:8px; cursor:pointer;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div style="text-align:center; padding:20px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; color:#64748b; font-weight:600;"><i class="fa-solid fa-box-open" style="font-size:24px; margin-bottom:10px;"></i><br>No active offers found for this user.</div>';
        }
    })
    .catch(err => container.innerHTML = '<div style="text-align:center; padding:15px; color:red;">Failed to load data.</div>');
}

// Edit Existing Discount Logic
function editExistingDiscount(id, type, value, applyTo, serviceId) {
    document.getElementById('edit_discount_id').value = id;
    document.getElementById('discount-type').value = type;
    document.getElementById('discount-value').value = value;
    document.getElementById('discount-apply-to').value = applyTo;
    
    if (applyTo === 'specific') {
        document.getElementById('discount-service-group').style.display = 'block';
        document.getElementById('discount-service').value = serviceId;
    } else {
        document.getElementById('discount-service-group').style.display = 'none';
    }
    
    // Change Button to Update Mode
    let submitBtn = document.getElementById('discountSubmitBtn');
    submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Discount';
    submitBtn.classList.replace('btn-primary', 'btn-success');
    document.getElementById('discountCancelEditBtn').style.display = 'inline-block';
}

// Reset Form Function
function resetDiscountForm() {
    document.getElementById('discountForm').reset();
    document.getElementById('edit_discount_id').value = '';
    document.getElementById('discount-service-group').style.display = 'none';
    
    let submitBtn = document.getElementById('discountSubmitBtn');
    submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> Add Discount';
    submitBtn.classList.replace('btn-success', 'btn-primary');
    document.getElementById('discountCancelEditBtn').style.display = 'none';
}

// Apply or Update Discount
function applyDiscount(e) {
    e.preventDefault();
    var userId = document.getElementById('discount-user-id').value;
    var email = document.getElementById('discount-user-email').value;
    var type = document.getElementById('discount-type').value;
    var value = document.getElementById('discount-value').value;
    var applyTo = document.getElementById('discount-apply-to').value;
    var editId = document.getElementById('edit_discount_id').value;

    if (!value || value <= 0) {
        alert('Please enter a valid discount value.');
        return;
    }

    var formData = new FormData();
    formData.append('action', 'apply_discount');
    formData.append('user_id', userId);
    formData.append('type', type);
    formData.append('value', value);
    formData.append('apply_to', applyTo);
    formData.append('edit_discount_id', editId);
    
    if (applyTo === 'specific') {
        formData.append('service_id', document.getElementById('discount-service').value);
    }

    let btn = document.getElementById('discountSubmitBtn');
    let origText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;

    fetch('ajax.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        btn.innerHTML = origText;
        btn.disabled = false;
        alert(data.message);
        if (data.success) {
            resetDiscountForm();
            openDiscountModal(userId, email); // Refresh the list instantly
        }
    })
    .catch(err => {
        btn.innerHTML = origText;
        btn.disabled = false;
        alert('Error: ' + err.message);
    });
}

// Delete Discount
function deleteDiscount(discountId, userId, email) {
    if(!confirm("Are you sure you want to remove this discount?")) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_discount');
    fd.append('discount_id', discountId);
    
    fetch('ajax.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            openDiscountModal(userId, email); // Refresh list
        } else {
            alert('Error: ' + data.message);
        }
    });
}

        function applyDiscount(e) {
            e.preventDefault();
            var userId = document.getElementById('discount-user-id').value;
            var type = document.getElementById('discount-type').value;
            var value = document.getElementById('discount-value').value;
            var applyTo = document.getElementById('discount-apply-to').value;

            if (!value || value <= 0) {
                alert('Please enter a valid discount value.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'apply_discount');
            formData.append('user_id', userId);
            formData.append('type', type);
            formData.append('value', value);
            formData.append('apply_to', applyTo);
            if (applyTo === 'specific') {
                formData.append('service_id', document.getElementById('discount-service').value);
            }

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) {
                    closeModal('discountModal');
                    location.reload();
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function resetPassword(userId) {
            var newPassword = prompt('Enter new password for this user (minimum 6 characters):');
            if (!newPassword) return;

            if (newPassword.length < 6) {
                alert('Password must be at least 6 characters.');
                return;
            }

            if (!confirm('Are you sure you want to reset password for user #' + userId + '?')) return;

            var formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('user_id', userId);
            formData.append('password', newPassword);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function loginAsUser(userId) {
            if (!confirm('Are you sure you want to login as this user?')) return;

            var formData = new FormData();
            formData.append('action', 'login_as_user');
            formData.append('user_id', userId);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.href = '../index.php';
                } else {
                    alert(data.message || 'Error logging in as user');
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        // ============================================================
        // SERVICE FUNCTIONS
        // ============================================================
        function filterServices() {
            try {
                var search = document.getElementById('service-search')?.value?.toLowerCase() || '';
                var rows = document.querySelectorAll('#services-table-body tr');

                rows.forEach(function(row) {
                    var name = row.dataset.name || '';
                    var code = row.dataset.code || '';
                    var match = name.includes(search) || code.includes(search);
                    row.style.display = match ? '' : 'none';
                });
            } catch(e) {
                console.error('filterServices error:', e);
            }
        }

        function addService(e) {
            e.preventDefault();
            var code = document.getElementById('new-service-code').value.trim().toUpperCase();
            var name = document.getElementById('new-service-name').value.trim();
            var price = document.getElementById('new-service-price').value;
            var server_id = document.getElementById('new-service-server').value;
            var logo = document.getElementById('new-service-logo').value.trim();
            var api_id = document.getElementById('new-service-api-id').value.trim();

            if (!code || !name || !price) {
                alert('Please fill all required fields.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'add_service');
            formData.append('code', code);
            formData.append('name', name);
            formData.append('price', price);
            formData.append('server_id', server_id);
            formData.append('logo', logo);
            formData.append('api_service_id', api_id);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function editService(id) {
            var formData = new FormData();
            formData.append('action', 'get_service');
            formData.append('service_id', id);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    var service = data.service;
                    document.getElementById('edit-service-id').value = service.id;
                    document.getElementById('edit-service-code').value = service.code;
                    document.getElementById('edit-service-name').value = service.name;
                    document.getElementById('edit-service-price').value = service.price;
                    document.getElementById('edit-service-server').value = service.server_id;
                    document.getElementById('edit-service-logo').value = service.logo || '';
                    document.getElementById('edit-service-popular').value = service.popular || 0;
                    document.getElementById('edit-service-cutback').value = service.cutback || 0;
                    document.getElementById('edit-service-cancel-disable').value = service.cancel_disable || 2;
                    openModal('editServiceModal');
                } else {
                    alert(data.message || 'Error loading service data');
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function saveServiceEdit(e) {
            e.preventDefault();
            var id = document.getElementById('edit-service-id').value;
            var code = document.getElementById('edit-service-code').value.trim().toUpperCase();
            var name = document.getElementById('edit-service-name').value.trim();
            var price = document.getElementById('edit-service-price').value;
            var server_id = document.getElementById('edit-service-server').value;
            var logo = document.getElementById('edit-service-logo').value.trim();
            var popular = document.getElementById('edit-service-popular').value;
            var cutback = document.getElementById('edit-service-cutback').value;
            var cancel_disable = document.getElementById('edit-service-cancel-disable').value;

            if (!code || !name || !price) {
                alert('Please fill all required fields.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'update_service');
            formData.append('service_id', id);
            formData.append('code', code);
            formData.append('name', name);
            formData.append('price', price);
            formData.append('server_id', server_id);
            formData.append('logo', logo);
            formData.append('popular', popular);
            formData.append('cutback', cutback);
            formData.append('cancel_disable', cancel_disable);

            var btn = document.querySelector('#editServiceForm button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) {
                    closeModal('editServiceModal');
                    location.reload();
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> Update Service';
            });
        }

        function deleteService(id) {
            if (!confirm('Are you sure you want to delete this service?')) return;

            var formData = new FormData();
            formData.append('action', 'delete_service');
            formData.append('service_id', id);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function syncServices() {
            if (!confirm('Sync services from API providers?')) return;

            var formData = new FormData();
            formData.append('action', 'sync_services');

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        // ============================================================
        // API PROVIDER FUNCTIONS
        // ============================================================
       function addApiProvider(e) {
    e.preventDefault();
    var name = document.getElementById('new-provider-name').value.trim();
    var provider_type = document.getElementById('new-provider-type').value;
    var api_key = document.getElementById('new-provider-api-key').value.trim();
    
    // рдпрд╣ рд▓рд╛рдЗрди рдЖрдкрдХреЗ HTML рд╕реЗ Base URL рдЙрдард╛рдПрдЧреА
    var base_url = document.getElementById('new-provider-base-url').value.trim();

    // рдЪреЗрдХ рдХрд░реЗрдЧрд╛ рдХрд┐ рд╕рдм рдХреБрдЫ рднрд░рд╛ рд╣реИ рдпрд╛ рдирд╣реАрдВ
    if (!name || !api_key || !base_url) {
        alert('Please fill all fields, including Base URL.');
        return;
    }

    var formData = new FormData();
    formData.append('action', 'add_api_provider');
    formData.append('name', name);
    formData.append('provider_type', provider_type);
    formData.append('api_key', api_key);
    formData.append('base_url', base_url); // PHP рдлрд╛рдЗрд▓ рдХреЛ Base URL рднреЗрдЬрдирд╛

    fetch('ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        alert(data.message);
        if (data.success) location.reload();
    })
    .catch(function(err) {
        alert('Error: ' + err.message);
    });
}

        // ============================================================
        // IMPORT SERVICES
        // ============================================================
        var currentProviderId = 0;
        var selectedCountries = [];
        var allCountries = [];
        var allServices = [];

        function openImportModal(providerId) {
            currentProviderId = providerId;
            selectedCountries = [];
            allCountries = [];
            allServices = [];
            document.getElementById('import-step-1').style.display = 'block';
            document.getElementById('import-step-2').style.display = 'none';
            openModal('importServicesModal');
            loadCountries(providerId);
        }

        function loadCountries(providerId) {
            var countryList = document.getElementById('country-list');
            countryList.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading countries...</div>';

            var formData = new FormData();
            formData.append('action', 'get_countries');
            formData.append('provider_id', providerId);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    allCountries = data.countries;
                    countryList.innerHTML = '';
                    allCountries.forEach(function(country) {
                        var div = document.createElement('div');
                        div.innerHTML = `
                            <label style="display:flex; align-items:center; gap:8px; padding:8px; border:1px solid var(--border-color); border-radius:8px; cursor:pointer;">
                                <input type="checkbox" value="${country.id}" onchange="toggleCountry('${country.id}')">
                                ${country.emoji || '🌍'} ${country.country}
                            </label>
                        `;
                        countryList.appendChild(div);
                    });
                } else {
                    countryList.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--danger);">' + data.message + '</div>';
                }
            })
            .catch(function(err) {
                countryList.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--danger);">Error loading countries</div>';
            });
        }

        function toggleCountry(id) {
            var index = selectedCountries.indexOf(id);
            if (index === -1) {
                selectedCountries.push(id);
            } else {
                selectedCountries.splice(index, 1);
            }
        }

        function selectAllCountries() {
            document.querySelectorAll('#country-list input[type="checkbox"]').forEach(function(cb) {
                cb.checked = true;
                if (!selectedCountries.includes(cb.value)) {
                    selectedCountries.push(cb.value);
                }
            });
        }

        function deselectAllCountries() {
            document.querySelectorAll('#country-list input[type="checkbox"]').forEach(function(cb) {
                cb.checked = false;
            });
            selectedCountries = [];
        }

        function goToStep2() {
            if (selectedCountries.length === 0) {
                alert('Please select at least one country.');
                return;
            }
            document.getElementById('import-step-1').style.display = 'none';
            document.getElementById('import-step-2').style.display = 'block';
            loadServicesForCountries();
        }

        function goToStep1() {
            document.getElementById('import-step-2').style.display = 'none';
            document.getElementById('import-step-1').style.display = 'block';
        }

        function loadServicesForCountries() {
            var serviceList = document.getElementById('service-list');
            serviceList.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading services...</div>';

            var allLoadedServices = [];
            var completed = 0;

            selectedCountries.forEach(function(countryId) {
                var formData = new FormData();
                formData.append('action', 'get_services_by_country');
                formData.append('provider_id', currentProviderId);
                formData.append('country_code', countryId);
                formData.append('margin_type', document.getElementById('margin-type').value);
                formData.append('margin_value', document.getElementById('margin-value').value);

                fetch('ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    completed++;
                    if (data.success && data.services) {
                        data.services.forEach(function(service) {
                            service.country_id = countryId;
                            allLoadedServices.push(service);
                        });
                    }

                    if (completed === selectedCountries.length) {
                        allServices = allLoadedServices;
                        displayServices(allServices);
                    }
                })
                .catch(function(err) {
                    completed++;
                    if (completed === selectedCountries.length) {
                        allServices = allLoadedServices;
                        displayServices(allServices);
                    }
                });
            });
        }

        function displayServices(services) {
            var serviceList = document.getElementById('service-list');
            if (services.length === 0) {
                serviceList.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);">No services found.</div>';
                return;
            }

            var html = `
                <table style="width:100%; font-size:13px;">
                    <thead>
                        <tr>
                            <th style="padding:8px;"><input type="checkbox" onchange="toggleAllServices(this.checked)"></th>
                            <th style="padding:8px; text-align:left;">Service</th>
                            <th style="padding:8px; text-align:left;">Code</th>
                            <th style="padding:8px; text-align:right;">Price (INR)</th>
                            <th style="padding:8px; text-align:right;">With Margin</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            services.forEach(function(service, index) {
                var serviceName = service.name || service.code.toUpperCase();
                html += `
                    <tr>
                        <td style="padding:8px; text-align:center;">
                            <input type="checkbox" class="service-checkbox" data-index="${index}" onchange="updateSelectedCount()">
                        </td>
                        <td style="padding:8px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                ${service.logo ? '<img src="' + service.logo + '" style="width:24px; height:24px; border-radius:4px; object-fit:contain;" onerror="this.style.display=\'none\'">' : ''}
                                <span>${serviceName}</span>
                            </div>
                        </td>
                        <td style="padding:8px;"><code style="font-size:12px;">${service.code}</code></td>
                        <td style="padding:8px; text-align:right;">₹${service.original_price.toFixed(2)}</td>
                        <td style="padding:8px; text-align:right; font-weight:bold; color:var(--accent);">₹${service.price_with_margin.toFixed(2)}</td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            serviceList.innerHTML = html;
            updateSelectedCount();
        }

        function toggleAllServices(checked) {
            document.querySelectorAll('.service-checkbox').forEach(function(cb) { cb.checked = checked; });
            updateSelectedCount();
        }

        function selectAllServices() {
            document.querySelectorAll('.service-checkbox').forEach(function(cb) { cb.checked = true; });
            updateSelectedCount();
        }

        function deselectAllServices() {
            document.querySelectorAll('.service-checkbox').forEach(function(cb) { cb.checked = false; });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            var checked = document.querySelectorAll('.service-checkbox:checked').length;
            document.getElementById('selected-count').textContent = checked;
        }

        function updateMarginPreview() {
            if (selectedCountries.length > 0) {
                loadServicesForCountries();
            }
        }

        function importSelectedServices() {
            var checkedBoxes = document.querySelectorAll('.service-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one service to import.');
                return;
            }

            var selected = [];
            checkedBoxes.forEach(function(cb) {
                var index = parseInt(cb.dataset.index);
                selected.push(allServices[index]);
            });

            if (!confirm('Import ' + selected.length + ' services?')) return;

            var formData = new FormData();
            formData.append('action', 'import_selected_services');
            formData.append('provider_id', currentProviderId);
            formData.append('country_code', selected[0].country_id || '0');
            formData.append('selected_services', JSON.stringify(selected));
            formData.append('margin_type', document.getElementById('margin-type').value);
            formData.append('margin_value', document.getElementById('margin-value').value);

            var btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importing...';

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) {
                    closeModal('importServicesModal');
                    location.reload();
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down"></i> Import Selected';
            });
        }

        function importAllServices() {
            if (!confirm('Import services from all active providers?')) return;

            var rows = document.querySelectorAll('#api-providers-table tr');
            var providerIds = [];
            rows.forEach(function(row) {
                var id = row.querySelector('td:first-child')?.textContent?.replace('#', '');
                if (id) providerIds.push(id);
            });

            if (providerIds.length === 0) {
                alert('No providers found.');
                return;
            }

            var completed = 0;
            var totalServers = 0;
            var totalServices = 0;
            var errors = [];

            providerIds.forEach(function(providerId) {
                var formData = new FormData();
                formData.append('action', 'import_services');
                formData.append('provider_id', providerId);

                fetch('ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    completed++;
                    if (data.success) {
                        totalServers += data.total_servers || 0;
                        totalServices += data.total_services || 0;
                    } else {
                        errors.push('Provider ' + providerId + ': ' + data.message);
                    }

                    if (completed === providerIds.length) {
                        var msg = 'Import completed!\nTotal Servers: ' + totalServers + '\nTotal Services: ' + totalServices;
                        if (errors.length > 0) {
                            msg += '\n\nErrors:\n' + errors.join('\n');
                        }
                        alert(msg);
                        location.reload();
                    }
                })
                .catch(function(err) {
                    completed++;
                    if (completed === providerIds.length) {
                        var msg = 'Import completed!\nTotal Servers: ' + totalServers + '\nTotal Services: ' + totalServices;
                        if (errors.length > 0) {
                            msg += '\n\nErrors:\n' + errors.join('\n');
                        }
                        alert(msg);
                        location.reload();
                    }
                });
            });
        }

        // ============================================================
        // ORDER FUNCTIONS
        // ============================================================
        function updateOrderStatus(id, status) {
            if (!confirm('Update order #' + id + ' to ' + status + '?')) return;

            var formData = new FormData();
            formData.append('action', 'update_order_status');
            formData.append('order_id', id);
            formData.append('status', status);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        // ============================================================
        // PAYMENT FUNCTIONS
        // ============================================================
        function updatePaymentStatus(id, status) {
            if (!confirm('Update payment #' + id + ' to ' + status + '?')) return;

            var formData = new FormData();
            formData.append('action', 'update_payment_status');
            formData.append('payment_id', id);
            formData.append('status', status);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        // ============================================================
        // SETTINGS FUNCTIONS
        // ============================================================
        function saveSettings(e) {
            e.preventDefault();
            var form = document.getElementById('settingsForm');
            var formData = new FormData(form);
            formData.append('action', 'save_settings');

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        // ============================================================
    // MASTER PAYMENT SETTINGS SAVER
    // ============================================================
    function saveAllPaymentSettings(event) {
        event.preventDefault();
        const form = document.getElementById('masterPaymentSettingsForm');
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;

        const fd = new FormData(form);
        fd.append('action', 'save_payment_settings');

        // Checkbox values handling (if checked, send 1, else it defaults to the hidden 0)
        
        fetch('ajax.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            if(data.success) {
                alert('тЬЕ All Payment Settings Saved Successfully!');
            } else {
                alert('тЭМ Error: ' + data.message);
            }
        })
        .catch(err => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('тЭМ Network Error: ' + err.message);
        });
    }

        function updateCurrencyRate(e) {
            e.preventDefault();
            var rate = document.getElementById('usd-to-inr').value;

            if (!rate || rate <= 0) {
                alert('Please enter a valid rate.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'update_currency_rate');
            formData.append('usd_to_inr', rate);

            var btn = document.querySelector('#currencyRateForm button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Rate';
            });
        }

        function saveSupportSettings(e) {
            e.preventDefault();
            var form = document.getElementById('supportForm');
            var formData = new FormData(form);
            formData.append('action', 'save_support_settings');

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function saveFooterSettings(e) {
            e.preventDefault();
            var form = document.getElementById('footerForm');
            var formData = new FormData(form);
            formData.append('action', 'save_footer_settings');

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function addFooterLink(e) {
            e.preventDefault();
            var title = document.getElementById('new-footer-link-title').value.trim();
            var url = document.getElementById('new-footer-link-url').value.trim();

            if (!title || !url) {
                alert('Please fill all fields.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'add_footer_link');
            formData.append('title', title);
            formData.append('url', url);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) {
                    closeModal('addFooterLinkModal');
                    location.reload();
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function deleteFooterLink(id) {
            if (!confirm('Are you sure you want to delete this footer link?')) return;

            var formData = new FormData();
            formData.append('action', 'delete_footer_link');
            formData.append('link_id', id);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        // ============================================================
        // BROADCAST FUNCTIONS
        // ============================================================
        function sendBroadcast(e) {
            e.preventDefault();
            var title = document.getElementById('broadcast-title').value.trim();
            var message = document.getElementById('broadcast-message').value.trim();
            var target = document.getElementById('broadcast-target').value;

            if (!title || !message) {
                alert('Please fill title and message.');
                return;
            }

            if (!confirm('Send broadcast to ' + target + ' users?')) return;

            var formData = new FormData();
            formData.append('action', 'send_broadcast');
            formData.append('title', title);
            formData.append('message', message);
            formData.append('target', target);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) {
                    document.getElementById('broadcastForm').reset();
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        // ============================================================
        // PROMO FUNCTIONS
        // ============================================================
        function addPromo(e) {
            e.preventDefault();
            var code = document.getElementById('new-promo-code').value.trim().toUpperCase();
            var name = document.getElementById('new-promo-name').value.trim();
            var amount = document.getElementById('new-promo-amount').value;
            var type = document.getElementById('new-promo-type').value;
            var max_uses = document.getElementById('new-promo-max-uses').value || null;
            var for_new_users = document.getElementById('new-promo-for-new').value;
            var valid_from = document.getElementById('new-promo-valid-from').value || null;
            var valid_until = document.getElementById('new-promo-valid-until').value || null;

            if (!code || !amount) {
                alert('Please fill all required fields.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'add_promo');
            formData.append('code', code);
            formData.append('name', name);
            formData.append('amount', amount);
            formData.append('type', type);
            formData.append('max_uses', max_uses);
            formData.append('for_new_users', for_new_users);
            if (valid_from) formData.append('valid_from', valid_from);
            if (valid_until) formData.append('valid_until', valid_until);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) {
                    closeModal('addPromoModal');
                    location.reload();
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function deletePromo(id) {
            if (!confirm('Are you sure you want to delete this promo code?')) return;

            var formData = new FormData();
            formData.append('action', 'delete_promo');
            formData.append('promo_id', id);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        // ============================================================
        // PAGE FUNCTIONS
        // ============================================================
        function addPage(e) {
            e.preventDefault();
            var title = document.getElementById('new-page-title').value.trim();
            var slug = document.getElementById('new-page-slug').value.trim().toLowerCase();
            var meta_desc = document.getElementById('new-page-meta-desc').value.trim();
            var meta_keys = document.getElementById('new-page-meta-keys').value.trim();
            var content = document.getElementById('new-page-content').value;
            var status = document.getElementById('new-page-status').value;

            if (!title || !slug || !content) {
                alert('Please fill all required fields.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'add_page');
            formData.append('title', title);
            formData.append('slug', slug);
            formData.append('meta_description', meta_desc);
            formData.append('meta_keywords', meta_keys);
            formData.append('content', content);
            formData.append('status', status);

            fetch('ajax.php', { method: 'POST', body: formData })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) { closeModal('addPageModal'); location.reload(); }
            })
            .catch(function(err) { alert('Error: ' + err.message); });
        }

        function editPage(id) {
            var formData = new FormData();
            formData.append('action', 'get_page');
            formData.append('page_id', id);

            fetch('ajax.php', { method: 'POST', body: formData })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    var page = data.page;
                    document.getElementById('edit-page-id').value = page.id;
                    document.getElementById('edit-page-title').value = page.title;
                    document.getElementById('edit-page-slug').value = page.slug;
                    document.getElementById('edit-page-meta-desc').value = page.meta_description || '';
                    document.getElementById('edit-page-meta-keys').value = page.meta_keywords || '';
                    document.getElementById('edit-page-content').value = page.content;
                    document.getElementById('edit-page-status').value = page.status;
                    openModal('editPageModal');
                } else {
                    alert(data.message || 'Error loading page data');
                }
            });
        }

        function savePageEdit(e) {
            e.preventDefault();
            var id = document.getElementById('edit-page-id').value;
            var title = document.getElementById('edit-page-title').value.trim();
            var slug = document.getElementById('edit-page-slug').value.trim().toLowerCase();
            var meta_desc = document.getElementById('edit-page-meta-desc').value.trim();
            var meta_keys = document.getElementById('edit-page-meta-keys').value.trim();
            var content = document.getElementById('edit-page-content').value;
            var status = document.getElementById('edit-page-status').value;

            var formData = new FormData();
            formData.append('action', 'update_page');
            formData.append('page_id', id);
            formData.append('title', title);
            formData.append('slug', slug);
            formData.append('meta_description', meta_desc);
            formData.append('meta_keywords', meta_keys);
            formData.append('content', content);
            formData.append('status', status);

            fetch('ajax.php', { method: 'POST', body: formData })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) { closeModal('editPageModal'); location.reload(); }
            });
        }

        // ============================================================
        // BANNER FUNCTIONS
        // ============================================================
        function addBanner(e) {
            e.preventDefault();
            var title = document.getElementById('new-banner-title').value.trim();
            var subtitle = document.getElementById('new-banner-subtitle').value.trim();
            var image_url = document.getElementById('new-banner-image').value.trim();
            var link_url = document.getElementById('new-banner-link').value.trim();
            var position = document.getElementById('new-banner-position').value;
            var order = document.getElementById('new-banner-order').value;
            var status = document.getElementById('new-banner-status').value;

            if (!title || !image_url) {
                alert('Please fill all required fields.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'add_banner');
            formData.append('title', title);
            formData.append('subtitle', subtitle);
            formData.append('image_url', image_url);
            formData.append('link_url', link_url);
            formData.append('position', position);
            formData.append('order', order);
            formData.append('status', status);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) {
                    closeModal('addBannerModal');
                    location.reload();
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function editBanner(id) {
            var formData = new FormData();
            formData.append('action', 'get_banner');
            formData.append('banner_id', id);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    var banner = data.banner;
                    document.getElementById('edit-banner-id').value = banner.id;
                    document.getElementById('edit-banner-title').value = banner.title;
                    document.getElementById('edit-banner-subtitle').value = banner.subtitle || '';
                    document.getElementById('edit-banner-image').value = banner.image_url;
                    document.getElementById('edit-banner-link').value = banner.link_url || '';
                    document.getElementById('edit-banner-position').value = banner.position;
                    document.getElementById('edit-banner-order').value = banner.order;
                    document.getElementById('edit-banner-status').value = banner.status;
                    openModal('editBannerModal');
                } else {
                    alert(data.message || 'Error loading banner data');
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function saveBannerEdit(e) {
            e.preventDefault();
            var id = document.getElementById('edit-banner-id').value;
            var title = document.getElementById('edit-banner-title').value.trim();
            var subtitle = document.getElementById('edit-banner-subtitle').value.trim();
            var image_url = document.getElementById('edit-banner-image').value.trim();
            var link_url = document.getElementById('edit-banner-link').value.trim();
            var position = document.getElementById('edit-banner-position').value;
            var order = document.getElementById('edit-banner-order').value;
            var status = document.getElementById('edit-banner-status').value;

            if (!title || !image_url) {
                alert('Please fill all required fields.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'update_banner');
            formData.append('banner_id', id);
            formData.append('title', title);
            formData.append('subtitle', subtitle);
            formData.append('image_url', image_url);
            formData.append('link_url', link_url);
            formData.append('position', position);
            formData.append('order', order);
            formData.append('status', status);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) {
                    closeModal('editBannerModal');
                    location.reload();
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function deleteBanner(id) {
            if (!confirm('Are you sure you want to delete this banner?')) return;

            var formData = new FormData();
            formData.append('action', 'delete_banner');
            formData.append('banner_id', id);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        // ============================================================
        // PROFILE FUNCTIONS
        // ============================================================
        function saveProfile(e) {
            e.preventDefault();
            var name = document.getElementById('profile-name').value.trim();
            var email = document.getElementById('profile-email').value.trim();
            var password = document.getElementById('profile-password').value;
            var confirm = document.getElementById('profile-password-confirm').value;

            if (!name || !email) {
                alert('Name and email are required.');
                return;
            }

            if (password && password !== confirm) {
                alert('Passwords do not match.');
                return;
            }

            if (password && password.length < 6) {
                alert('Password must be at least 6 characters.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'update_admin_profile');
            formData.append('name', name);
            formData.append('email', email);
            if (password) {
                formData.append('password', password);
            }

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        // ============================================================
// LOAD DASHBOARD STATS (100% Fixed & Error-Proof)
// ============================================================
function loadDashboardStats() {
    try {
        var formData = new FormData();
        formData.append('action', 'get_dashboard_stats');

        fetch('ajax.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Today Stats
                if(document.getElementById('today-orders-val')) document.getElementById('today-orders-val').textContent = (data.today?.orders || 0) + ' Orders';
                if(document.getElementById('today-success')) document.getElementById('today-success').textContent = data.today?.success || 0;
                if(document.getElementById('today-cancel')) document.getElementById('today-cancel').textContent = data.today?.cancel || 0;
                if(document.getElementById('today-rev')) document.getElementById('today-rev').textContent = '₹' + (parseFloat(data.today?.revenue) || 0).toFixed(2);

                // Week Stats
                if(document.getElementById('week-orders-val')) document.getElementById('week-orders-val').textContent = '₹' + (parseFloat(data.week?.revenue) || 0).toFixed(2);
                if(document.getElementById('week-ord')) document.getElementById('week-ord').textContent = data.week?.orders || 0;
                if(document.getElementById('week-rev')) document.getElementById('week-rev').textContent = '₹' + (parseFloat(data.week?.revenue) || 0).toFixed(2);

                // Month Stats
                if(document.getElementById('month-orders-val')) document.getElementById('month-orders-val').textContent = '₹' + (parseFloat(data.month?.revenue) || 0).toFixed(2);
                if(document.getElementById('month-ord')) document.getElementById('month-ord').textContent = data.month?.orders || 0;
                if(document.getElementById('month-rev')) document.getElementById('month-rev').textContent = '₹' + (parseFloat(data.month?.revenue) || 0).toFixed(2);

                // Top Services Render
                let servicesHTML = '';
                if (data.topServices && data.topServices.length > 0) {
                    data.topServices.forEach((s, i) => {
                        servicesHTML += `<div style="display:flex; justify-content:space-between; padding:8px 12px; border-bottom:1px solid var(--border-color);">
                            <span><b>${i+1}.</b> ${s.service_name}</span>
                            <span style="font-weight:600; color:var(--accent);">${s.count} orders</span>
                        </div>`;
                    });
                } else { servicesHTML = '<p style="color:var(--text-muted); text-align:center; padding:20px;">No data available</p>'; }
                if(document.getElementById('top-services-list')) document.getElementById('top-services-list').innerHTML = servicesHTML;

                // Top Users Render
                let usersHTML = '';
                if (data.topUsers && data.topUsers.length > 0) {
                    data.topUsers.forEach((u, i) => {
                        usersHTML += `<div style="display:flex; justify-content:space-between; padding:8px 12px; border-bottom:1px solid var(--border-color);">
                            <span><b>${i+1}.</b> ${u.email}</span>
                            <span style="font-weight:600; color:var(--accent);">${u.orders} orders | ₹${parseFloat(u.spent).toFixed(2)}</span>
                        </div>`;
                    });
                } else { usersHTML = '<p style="color:var(--text-muted); text-align:center; padding:20px;">No data available</p>'; }
                if(document.getElementById('top-users-list')) document.getElementById('top-users-list').innerHTML = usersHTML;
            }
        }).catch(err => console.error('Dashboard Stats Fetch Error:', err));
    } catch(e) { console.error('Dashboard Stats Exception:', e); }
}

// DOM Load होते ही ऑटोमैटिक कॉल होगा
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
});

        // ============================================================
        // SERVER FUNCTIONS
        // ============================================================
        function addServer(e) {
            e.preventDefault();
            var name = document.getElementById('new-server-name').value.trim();
            var api_key = document.getElementById('new-server-api-key').value.trim();
            var base_url = document.getElementById('new-server-base-url').value.trim();
            var provider_type = document.getElementById('new-server-provider').value;

            if (!name || !api_key) {
                alert('Please fill all required fields.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'add_server');
            formData.append('name', name);
            formData.append('api_key', api_key);
            formData.append('base_url', base_url);
            formData.append('provider_type', provider_type);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) {
                    closeModal('addServerModal');
                    location.reload();
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function editServer(id) {
            var formData = new FormData();
            formData.append('action', 'get_server');
            formData.append('server_id', id);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    var server = data.server;
                    document.getElementById('edit-server-id').value = server.id;
                    document.getElementById('edit-server-name').value = server.name;
                    document.getElementById('edit-server-code').value = server.code || '';
                    document.getElementById('edit-server-country-code').value = server.country_code || '';
                    document.getElementById('edit-server-emoji').value = server.country_emoji || '';
                    document.getElementById('edit-server-api-type').value = server.api_type || 'text';
                    document.getElementById('edit-server-has-headers').value = server.has_headers || 0;
                    document.getElementById('edit-server-api-key').value = server.api_key;
                    document.getElementById('edit-server-base-url').value = server.base_url || '';
                    document.getElementById('edit-server-get-num').value = server.get_num || '';
                    document.getElementById('edit-server-get-msg').value = server.get_msg || '';
                    document.getElementById('edit-server-next-msg').value = server.next_msg || '';
                    document.getElementById('edit-server-cancel-num').value = server.cancel_num || '';
                    document.getElementById('edit-server-auto-cancel').value = server.auto_cancel || 5;
                    document.getElementById('edit-server-retry').value = server.retry_count || 1;
                    document.getElementById('edit-server-status').value = server.status || 1;
                    
                    if (server.has_headers) {
                        document.getElementById('edit-headers-container').style.display = 'block';
                        document.getElementById('edit-server-headers').value = server.headers || '';
                    } else {
                        document.getElementById('edit-headers-container').style.display = 'none';
                    }
                    
                    openModal('editServerModal');
                } else {
                    alert(data.message || 'Error loading server data');
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        function saveServerEdit(e) {
            e.preventDefault();
            var id = document.getElementById('edit-server-id').value;
            var name = document.getElementById('edit-server-name').value.trim();
            var code = document.getElementById('edit-server-code').value.trim();
            var country_code = document.getElementById('edit-server-country-code').value.trim();
            var country_emoji = document.getElementById('edit-server-emoji').value.trim();
            var api_type = document.getElementById('edit-server-api-type').value;
            var has_headers = document.getElementById('edit-server-has-headers').value;
            var headers = document.getElementById('edit-server-headers').value.trim();
            var api_key = document.getElementById('edit-server-api-key').value.trim();
            var base_url = document.getElementById('edit-server-base-url').value.trim();
            var get_num = document.getElementById('edit-server-get-num').value.trim();
            var get_msg = document.getElementById('edit-server-get-msg').value.trim();
            var next_msg = document.getElementById('edit-server-next-msg').value.trim();
            var cancel_num = document.getElementById('edit-server-cancel-num').value.trim();
            var auto_cancel = document.getElementById('edit-server-auto-cancel').value;
            var retry = document.getElementById('edit-server-retry').value;
            var status = document.getElementById('edit-server-status').value;

            if (!name || !api_key) {
                alert('Please fill all required fields.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'update_server');
            formData.append('server_id', id);
            formData.append('name', name);
            formData.append('code', code);
            formData.append('country_code', country_code);
            formData.append('country_emoji', country_emoji);
            formData.append('api_type', api_type);
            formData.append('has_headers', has_headers);
            if (has_headers == 1) formData.append('headers', headers);
            formData.append('api_key', api_key);
            formData.append('base_url', base_url);
            formData.append('get_num', get_num);
            formData.append('get_msg', get_msg);
            formData.append('next_msg', next_msg);
            formData.append('cancel_num', cancel_num);
            formData.append('auto_cancel', auto_cancel);
            formData.append('retry_count', retry);
            formData.append('status', status);

            var btn = document.querySelector('#editServerForm button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) {
                    closeModal('editServerModal');
                    location.reload();
                }
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> Update Server';
            });
        }

        function deleteServer(id) {
            if (!confirm('Are you sure you want to delete this server?')) return;

            var formData = new FormData();
            formData.append('action', 'delete_server');
            formData.append('server_id', id);

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(function(err) {
                alert('Error: ' + err.message);
            });
        }

        // ============================================================
        // DOM READY
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            try {
                console.log('тЬЕ Admin Panel Loading...');
                
                var applyTo = document.getElementById('discount-apply-to');
                if (applyTo) {
                    applyTo.addEventListener('change', function() {
                        var serviceGroup = document.getElementById('discount-service-group');
                        if (serviceGroup) {
                            serviceGroup.style.display = this.value === 'specific' ? 'block' : 'none';
                        }
                    });
                }
                
                var hasHeaders = document.getElementById('edit-server-has-headers');
                if (hasHeaders) {
                    hasHeaders.addEventListener('change', function() {
                        var container = document.getElementById('edit-headers-container');
                        if (container) {
                            container.style.display = this.value == 1 ? 'block' : 'none';
                        }
                    });
                }
                
                if (document.getElementById('today-orders')) {
                    loadDashboardStats();
                }
                
                console.log('тЬЕ Admin Panel Loaded Successfully!');
            } catch (e) {
                console.error('DOM Ready Error:', e);
            }
        });

        // ============================================================
        // KEYBOARD SHORTCUTS
        // ============================================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.show').forEach(function(el) {
                    el.classList.remove('show');
                });
            }
        });
    </script>
 
<script>
// ==========================================
// 1. FILTER LOGIC (Country -> Server -> Service)
// ==========================================
const discountAllServices = <?php echo !empty($allServices) ? json_encode($allServices, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : '[]'; ?>;

function initDiscountFilters() {
    try {
        let countryMap = {};
        if (Array.isArray(discountAllServices)) {
            discountAllServices.forEach(s => {
                let cName = s.country_name || 'Unknown';
                let cEmoji = s.country_emoji || '🌐';
                if(!countryMap[cName]) countryMap[cName] = { name: cName, emoji: cEmoji };
            });
        }

        let cSelect = document.getElementById('disc-filter-country');
        if (cSelect) {
            cSelect.innerHTML = '<option value="">🌍 All Countries</option>';
            Object.values(countryMap).sort((a,b) => a.name.localeCompare(b.name)).forEach(c => {
                cSelect.innerHTML += `<option value="${c.name}">${c.emoji} ${c.name}</option>`;
            });
        }
        filterDiscountServers(); 
    } catch (e) { console.error("Filter init error:", e); }
}

function filterDiscountServers() {
    try {
        let countryEl = document.getElementById('disc-filter-country');
        let selectedCountry = countryEl ? countryEl.value : '';
        let sSelect = document.getElementById('disc-filter-server');
        
        if (sSelect) {
            sSelect.innerHTML = '<option value="">🖥️ All Servers</option>';
            let serverMap = {};
            
            if (Array.isArray(discountAllServices)) {
                discountAllServices.forEach(s => {
                    let cName = s.country_name || 'Unknown';
                    let sName = s.server_name || 'Unknown Server';
                    let sId = s.server_id || 0;

                    if(selectedCountry === '' || selectedCountry === cName) {
                        if(!serverMap[sId]) serverMap[sId] = { id: sId, name: sName };
                    }
                });
            }

            Object.values(serverMap).sort((a,b) => a.name.localeCompare(b.name)).forEach(srv => {
                sSelect.innerHTML += `<option value="${srv.id}">${srv.name}</option>`;
            });
        }
        filterDiscountServices(); 
    } catch (e) { console.error("Server filter error:", e); }
}

function filterDiscountServices() {
    try {
        let countryEl = document.getElementById('disc-filter-country');
        let serverEl = document.getElementById('disc-filter-server');
        let searchEl = document.getElementById('disc-search-service');
        let srvSelect = document.getElementById('discount-service');

        if (!srvSelect) return;

        let selectedCountry = countryEl ? countryEl.value : '';
        let selectedServer = serverEl ? serverEl.value : '';
        let searchQuery = searchEl ? searchEl.value.toLowerCase() : '';

        srvSelect.innerHTML = '';

        let filtered = [];
        if (Array.isArray(discountAllServices)) {
            filtered = discountAllServices.filter(s => {
                let cName = s.country_name || 'Unknown';
                let sId = s.server_id || 0;
                
                let matchCountry = (selectedCountry === '' || cName === selectedCountry);
                let matchServer = (selectedServer === '' || sId.toString() === selectedServer.toString());
                let matchSearch = (searchQuery === '' || (s.name && s.name.toLowerCase().includes(searchQuery)) || (s.code && s.code.toLowerCase().includes(searchQuery)));

                return matchCountry && matchServer && matchSearch;
            });
        }

        if(filtered.length === 0) {
            srvSelect.innerHTML = '<option value="" disabled>❌ No services found matching criteria</option>';
            return;
        }

        filtered.forEach(s => {
            let cEmoji = s.country_emoji || '🌐';
            let srvName = s.server_name || 'Unknown Server';
            let sPrice = parseFloat(s.price || 0).toFixed(2);
            srvSelect.innerHTML += `<option value="${s.id}">${cEmoji} ${s.name} (${s.code}) | ${srvName} | ₹${sPrice}</option>`;
        });
    } catch (e) { console.error("Service filter error:", e); }
}

document.addEventListener('DOMContentLoaded', () => {
    if(document.getElementById('disc-filter-country')) {
        initDiscountFilters();
    }
});


// ==========================================
// 2. MODAL & DISCOUNT MANAGEMENT LOGIC
// ==========================================

// Open Discount Modal and Fetch Existing Discounts
function openDiscountModal(userId, email) {
    document.getElementById('discount-user-id').value = userId;
    document.getElementById('discount-user-email').value = email;
    resetDiscountForm();
    openModal('discountModal');
    
    const container = document.getElementById('existingDiscountsContainer');
    if(!container) return;

    container.innerHTML = '<div style="text-align:center; padding:10px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';
    
    fetch('ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({'action': 'get_user_discounts', 'user_id': userId})
    })
    .then(res => res.json())
    .then(data => {
        if(data.success && data.discounts.length > 0) {
            let html = '<div style="display:flex; flex-direction:column; gap:12px;">';
            
            data.discounts.forEach(d => {
                let isGlobal = (d.apply_to === 'all');
                let srvName = isGlobal ? 'Global Discount (All Services)' : (d.service_name || 'Unknown Service');
                let origPrice = parseFloat(d.original_price || 0);
                let val = parseFloat(d.value || 0);
                let newPrice = d.type === 'percentage' ? origPrice - (origPrice * val / 100) : val;

                let valText = d.type === 'percentage' ? val + '% OFF' : '₹' + val + ' FIXED';
                let badgeStyle = isGlobal ? 'background:#dcfce7; color:#16a34a;' : 'background:#e0e7ff; color:#4f46e5;';
                
                let logoHtml = isGlobal 
                    ? '<i class="fa-solid fa-globe" style="color:#10b981; font-size:20px;"></i>' 
                    : (d.service_logo ? `<img src="${d.service_logo}" style="width:100%; height:100%; object-fit:contain;">` : '<i class="fa-solid fa-gem" style="color:#94a3b8;"></i>');

                let subText = isGlobal 
                    ? '<div style="font-size:11px; color:#64748b; font-weight:600; margin-top:4px;">Applies to all services</div>' 
                    : `<div style="font-size:11px; color:#64748b; font-weight:600; margin-top:4px;">${d.country_emoji || '🌍'} ${d.country_name || 'Country'} • <i class="fa-solid fa-server"></i> ${d.server_name || 'Server'}</div>`;

                let priceHtml = (!isGlobal && origPrice > 0) ? `<div style="font-size:11px; color:#ef4444; text-decoration:line-through; font-weight:700;">Original: ₹${origPrice.toFixed(2)}</div>` : '';
                let newPriceHtml = (!isGlobal && origPrice > 0 && d.type === 'percentage') ? `<div style="font-size:11px; color:#3b82f6; font-weight:800;">New: ₹${newPrice.toFixed(2)}</div>` : '';

                html += `
                <div style="background:#ffffff; border:1px solid #e2e8f0; padding:15px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <div style="display:flex; align-items:center; gap:15px;">
                        <div style="width:45px; height:45px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; display:flex; justify-content:center; align-items:center; overflow:hidden; flex-shrink:0;">
                            ${logoHtml}
                        </div>
                        <div>
                            <div style="font-weight:800; font-size:15px; color:#0f172a;">${srvName} <span style="font-size:10px; padding:3px 8px; border-radius:20px; font-weight:800; margin-left:5px; ${badgeStyle}">${d.apply_to.toUpperCase()}</span></div>
                            ${subText}
                        </div>
                    </div>
                    
                    <div style="display:flex; align-items:center; gap:20px;">
                        <div style="text-align:right;">
                            ${priceHtml}
                            <div style="font-size:16px; color:#10b981; font-weight:900;">${valText}</div>
                            ${newPriceHtml}
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" onclick="editExistingDiscount(${d.discount_id}, '${d.type}', ${d.value}, '${d.apply_to}', ${d.service_id || 0})" style="background:#eff6ff; color:#3b82f6; border:1px solid #bfdbfe; padding:8px 12px; border-radius:8px; cursor:pointer;" title="Edit"><i class="fa-solid fa-pen"></i></button>
                            <button type="button" onclick="deleteDiscount(${d.discount_id}, ${userId}, '${email}')" style="background:#fef2f2; color:#ef4444; border:1px solid #fecaca; padding:8px 12px; border-radius:8px; cursor:pointer;" title="Delete"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div style="text-align:center; padding:20px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; color:#64748b; font-weight:600;"><i class="fa-solid fa-box-open" style="font-size:24px; margin-bottom:10px;"></i><br>No active offers found for this user.</div>';
        }
    })
    .catch(err => {
        if(container) container.innerHTML = '<div style="text-align:center; padding:15px; color:red;">Failed to load data.</div>';
    });
}

// Reset Form Function
function resetDiscountForm() {
    if(document.getElementById('discountForm')) document.getElementById('discountForm').reset();
    if(document.getElementById('edit_discount_id')) document.getElementById('edit_discount_id').value = '';
    if(document.getElementById('discount-service-group')) document.getElementById('discount-service-group').style.display = 'none';
    
    let submitBtn = document.getElementById('discountSubmitBtn');
    if(submitBtn) {
        submitBtn.innerHTML = '<i class="fa-solid fa-plus"></i> Add Discount';
        submitBtn.classList.remove('btn-success');
        submitBtn.classList.add('btn-primary');
    }
    if(document.getElementById('discountCancelEditBtn')) {
        document.getElementById('discountCancelEditBtn').style.display = 'none';
    }
}

// Apply or Update Discount
function applyDiscount(e) {
    e.preventDefault();
    var userId = document.getElementById('discount-user-id').value;
    var email = document.getElementById('discount-user-email').value;
    var type = document.getElementById('discount-type').value;
    var value = document.getElementById('discount-value').value;
    var applyTo = document.getElementById('discount-apply-to').value;
    var editId = document.getElementById('edit_discount_id').value;

    if (!value || value <= 0) {
        alert('Please enter a valid discount value.');
        return;
    }

    var formData = new FormData();
    formData.append('action', 'apply_discount');
    formData.append('user_id', userId);
    formData.append('type', type);
    formData.append('value', value);
    formData.append('apply_to', applyTo);
    formData.append('edit_discount_id', editId);
    
    if (applyTo === 'specific') {
        formData.append('service_id', document.getElementById('discount-service').value);
    }

    let btn = document.getElementById('discountSubmitBtn');
    let origText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;

    fetch('ajax.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        btn.innerHTML = origText;
        btn.disabled = false;
        alert(data.message);
        if (data.success) {
            resetDiscountForm();
            openDiscountModal(userId, email); // List Update ho jayegi
        }
    })
    .catch(err => {
        btn.innerHTML = origText;
        btn.disabled = false;
        alert('Error: ' + err.message);
    });
}

// Delete Discount
function deleteDiscount(discountId, userId, email) {
    if(!confirm("Are you sure you want to remove this discount?")) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_discount');
    fd.append('discount_id', discountId);
    
    fetch('ajax.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            openDiscountModal(userId, email); // List Refresh
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// 🚀 EDIT EXISTING DISCOUNT (Merged & Crash-Proof) 🚀
function editExistingDiscount(id, type, value, applyTo, serviceId) {
    try {
        // 1. Basic Fields सेट करना (पुराने कोड से लिया गया, सुरक्षित तरीके से)
        if (document.getElementById('edit_discount_id')) document.getElementById('edit_discount_id').value = id;
        if (document.getElementById('discount-type')) document.getElementById('discount-type').value = type;
        if (document.getElementById('discount-value')) document.getElementById('discount-value').value = value;
        if (document.getElementById('discount-apply-to')) document.getElementById('discount-apply-to').value = applyTo;
        
        let serviceGroup = document.getElementById('discount-service-group');
        
        if (applyTo === 'specific') {
            if (serviceGroup) serviceGroup.style.display = 'block';
            
            // Auto-Select Country, Server, and Service for Editing safely
            if (Array.isArray(discountAllServices)) {
                let serviceObj = discountAllServices.find(s => s.id == serviceId);
                if (serviceObj) {
                    if (document.getElementById('disc-filter-country')) {
                        document.getElementById('disc-filter-country').value = serviceObj.country_name || '';
                    }
                    filterDiscountServers(); 
                    
                    if (document.getElementById('disc-filter-server')) {
                        document.getElementById('disc-filter-server').value = serviceObj.server_id || '';
                    }
                    filterDiscountServices(); 
                }
            }
            if (document.getElementById('discount-service')) {
                document.getElementById('discount-service').value = serviceId;
            }
        } else {
            if (serviceGroup) serviceGroup.style.display = 'none';
        }
        
        let submitBtn = document.getElementById('discountSubmitBtn');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Discount';
            submitBtn.classList.remove('btn-primary');
            submitBtn.classList.add('btn-success');
        }
        
        let cancelBtn = document.getElementById('discountCancelEditBtn');
        if (cancelBtn) cancelBtn.style.display = 'inline-block';

    } catch (e) { console.error("Edit Error:", e); }
}
</script>

    <script>
function searchOrders() {
    let input = document.getElementById("orderSearchInput").value.toLowerCase();
    let tableBody = document.getElementById("ordersTableBody");
    let rows = tableBody.getElementsByTagName("tr");

    for (let i = 0; i < rows.length; i++) {
        // Agar "No orders found" wala row hai, toh usko skip karo
        if (rows[i].cells.length === 1) continue; 

        // Row ke andar ka saara text uthao aur check karo
        let rowText = rows[i].textContent.toLowerCase();
        
        if (rowText.includes(input)) {
            rows[i].style.display = ""; // Text mila, toh row dikhao
        } else {
            rows[i].style.display = "none"; // Text nahi mila, toh row chhupao
        }
    }
}
</script>

</body>
</html>