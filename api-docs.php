<?php
// ============================================================
// API DOCUMENTATION - FULL WORKING
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once 'includes/functions.php';

// Check login
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$userApiKey = '';
$userName = $_SESSION['user_name'] ?? 'Guest';
$userEmail = $_SESSION['user_email'] ?? 'guest@example.com';

// Get user API key
if ($isLoggedIn) {
    try {
        // Check if api_key column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM Users LIKE 'api_key'");
        $columnExists = $stmt->fetch();
        
        if ($columnExists) {
            $stmt = $pdo->prepare("SELECT api_key FROM Users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user && !empty($user['api_key'])) {
                $userApiKey = $user['api_key'];
            } else {
                $userApiKey = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE Users SET api_key = ? WHERE id = ?");
                $stmt->execute([$userApiKey, $userId]);
            }
        } else {
            $userApiKey = bin2hex(hash('sha256', $userId . 'otpsms_secret_' . date('Y-m-d'), true));
        }
    } catch (Exception $e) {
        $userApiKey = bin2hex(hash('sha256', $userId . 'otpsms_secret', true));
    }
}

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
    $siteSettings = ['site_name' => 'OTPSMS', 'site_logo' => '', 'support_link' => '#', 'currency_symbol' => '₹'];
}

// Get API stats
try {
    $totalCountries = $pdo->query("SELECT COUNT(DISTINCT country_code) FROM Servers WHERE status = 1 AND country_code IS NOT NULL")->fetchColumn() ?: 150;
    $totalServices = $pdo->query("SELECT COUNT(*) FROM Services WHERE server_id IN (SELECT id FROM Servers WHERE status = 1)")->fetchColumn() ?: 500;
    $totalServers = $pdo->query("SELECT COUNT(*) FROM Servers WHERE status = 1")->fetchColumn() ?: 10;
} catch (Exception $e) {
    $totalCountries = 150;
    $totalServices = 500;
    $totalServers = 10;
}

// Get base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $host;
$apiBaseUrl = $baseUrl . '/api/handler_api.php';

// ============================================================
// FETCH REFERENCE DATA
// ============================================================

// Fetch Countries
try {
    $countries = $pdo->query("
        SELECT DISTINCT 
            country_code as code,
            country_name as name,
            country_emoji as flag,
            country_dial_code as dial_code
        FROM Servers 
        WHERE status = 1 
        AND country_code IS NOT NULL 
        AND country_code != ''
        ORDER BY country_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $countries = [];
}

// Fetch Services
try {
    $services = $pdo->query("
        SELECT DISTINCT 
            s.code,
            s.name,
            s.logo as icon
        FROM Services s
        WHERE s.server_id IN (SELECT id FROM Servers WHERE status = 1)
        ORDER BY s.name ASC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services = [];
}

// Fetch Servers
try {
    $servers = $pdo->query("
        SELECT 
            id,
            name,
            code,
            country_code,
            country_name,
            country_emoji,
            provider_type
        FROM Servers 
        WHERE status = 1
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $servers = [];
}

// Fetch Operators
try {
    $operators = $pdo->query("
        SELECT DISTINCT 
            operator as code,
            operator as name
        FROM Servers 
        WHERE status = 1 
        AND operator IS NOT NULL 
        AND operator != ''
        ORDER BY operator ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $operators = [];
}

// ============================================================
// HANDLE API TEST REQUESTS
// ============================================================
$testResult = null;
$testError = null;
$testAction = $_POST['test_action'] ?? '';

if ($testAction && $isLoggedIn) {
    $apiKey = $_POST['api_key'] ?? $userApiKey;
    $service = $_POST['service'] ?? 'wa';
    $country = $_POST['country'] ?? '22';
    $operator = $_POST['operator'] ?? 'any';
    $activationId = $_POST['activation_id'] ?? '';
    $statusCode = $_POST['status_code'] ?? '8';
    
    $params = ['api_key' => $apiKey, 'action' => $testAction];
    
    if ($testAction === 'getNumber') {
        $params['service'] = $service;
        $params['country'] = $country;
        if ($operator && $operator !== 'any') {
            $params['operator'] = $operator;
        }
    } elseif ($testAction === 'getStatus' || $testAction === 'setStatus') {
        $params['id'] = $activationId;
        if ($testAction === 'setStatus') {
            $params['status'] = $statusCode;
        }
    }
    
    $url = $apiBaseUrl . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $testResult = [
        'url' => $url,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $curlError
    ];
}

// ============================================================
// HANDLE REGENERATE API KEY
// ============================================================
if ($_POST['regenerate_key'] ?? false) {
    header('Content-Type: application/json');
    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'message' => 'Please login first']);
        exit();
    }
    try {
        $newKey = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("UPDATE Users SET api_key = ? WHERE id = ?");
        $stmt->execute([$newKey, $userId]);
        echo json_encode(['success' => true, 'api_key' => $newKey]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPSMS'); ?> - API Documentation</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #f0f4ff;
            --bg-sidebar: #ffffff;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --accent: #246bfd;
            --accent-dark: #1a56db;
            --hover-bg: rgba(36,107,253,0.06);
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --code-bg: #1e293b;
            --code-text: #e2e8f0;
            --radius: 12px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.12);
        }

        body.dark-mode {
            --bg-body: #0a0e17;
            --bg-sidebar: #131824;
            --bg-card: #131824;
            --text-main: #ffffff;
            --text-muted: #8a939f;
            --border-color: #1e2532;
            --hover-bg: #1c2230;
            --code-bg: #0a0e17;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, system-ui, sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; transition: background 0.3s, color 0.3s; }
        ::-webkit-scrollbar { width: 6px; background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }

        .app-container { display: flex; width: 100%; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 20px 0;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }

        .sidebar .logo-area {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .sidebar .logo-area .logo-inner {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 700;
        }

        .sidebar .logo-area .logo-circle {
            width: 36px;
            height: 36px;
            background: var(--accent);
            border-radius: 50%;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 16px;
            font-weight: 800;
            overflow: hidden;
            flex-shrink: 0;
        }

        .sidebar .logo-area .theme-toggle {
            float: right;
            background: transparent;
            border: none;
            font-size: 18px;
            color: var(--text-muted);
            cursor: pointer;
            padding: 5px;
            margin-top: -8px;
        }

        .sidebar .menu-label {
            padding: 12px 20px 6px;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .sidebar .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 8px;
            margin: 2px 10px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            font-size: 14px;
        }

        .sidebar .menu-item:hover { background: var(--hover-bg); color: var(--accent); }
        .sidebar .menu-item.active { background: var(--hover-bg); color: var(--accent); }
        .sidebar .menu-item i { font-size: 16px; width: 20px; text-align: center; }
        .sidebar .menu-item .badge { margin-left: auto; background: var(--accent); color: white; font-size: 10px; padding: 1px 8px; border-radius: 20px; font-weight: 700; }

        .sidebar .user-profile {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .sidebar .user-profile .avatar {
            width: 40px;
            height: 40px;
            background: var(--accent);
            color: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            font-size: 16px;
            flex-shrink: 0;
        }

        .sidebar .user-profile .user-info { overflow: hidden; }
        .sidebar .user-profile .user-info .user-name { font-size: 14px; font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar .user-profile .user-info .user-email { font-size: 12px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
            min-height: 100vh;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .top-bar .page-title h1 { font-size: 28px; font-weight: 800; }
        .top-bar .page-title p { color: var(--text-muted); font-size: 14px; }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: var(--accent-dark); transform: translateY(-2px); box-shadow: 0 4px 15px rgba(36,107,253,0.3); }
        .btn-outline { background: transparent; border: 2px solid var(--border-color); color: var(--text-muted); }
        .btn-outline:hover { border-color: var(--accent); color: var(--accent); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #16a34a; transform: translateY(-2px); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
        .btn-sm { padding: 4px 12px; font-size: 12px; }

        /* API Key Card */
        .api-key-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .api-key-card .key-label { font-weight: 600; font-size: 14px; color: var(--text-muted); }
        .api-key-card .key-value {
            font-family: 'Courier New', monospace;
            font-size: 15px;
            font-weight: 700;
            color: var(--accent);
            background: var(--hover-bg);
            padding: 6px 14px;
            border-radius: 8px;
            word-break: break-all;
        }

        .api-key-card .key-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .api-key-card .key-actions button { padding: 6px 14px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 13px; }
        .btn-copy { background: var(--hover-bg); color: var(--accent); }
        .btn-copy:hover { background: var(--accent); color: white; }
        .btn-regenerate { background: var(--warning); color: white; }
        .btn-regenerate:hover { background: #d97706; }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 16px;
            border: 1px solid var(--border-color);
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .stat-card .number { font-size: 28px; font-weight: 800; color: var(--accent); }
        .stat-card .label { font-size: 13px; color: var(--text-muted); font-weight: 500; margin-top: 4px; }

        /* Search Box */
        .search-box {
            background: var(--bg-card);
            padding: 10px 16px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .search-box input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 14px;
            background: transparent;
            color: var(--text-main);
        }
        .search-box input::placeholder { color: var(--text-muted); }

        /* Reference Tables */
        .ref-section {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .ref-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            cursor: pointer;
            transition: 0.2s;
        }
        .ref-header:hover { background: var(--hover-bg); }
        .ref-header h3 { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .ref-header .count { font-size: 13px; color: var(--text-muted); font-weight: 500; }

        .ref-body {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.4s ease;
        }
        .ref-body.open { max-height: 600px; padding: 16px 20px; overflow-y: auto; }

        .ref-body table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .ref-body table th {
            text-align: left;
            padding: 8px 12px;
            background: var(--bg-body);
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .ref-body table td { padding: 8px 12px; border-bottom: 1px solid var(--border-color); }
        .ref-body table tr:hover td { background: var(--hover-bg); }
        .ref-body table .flag { font-size: 20px; }
        .ref-body table .copy-btn {
            background: var(--hover-bg);
            border: none;
            border-radius: 4px;
            padding: 2px 8px;
            cursor: pointer;
            font-size: 12px;
            color: var(--text-muted);
            transition: 0.2s;
        }
        .ref-body table .copy-btn:hover { background: var(--accent); color: white; }

        .ref-body .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
            margin-top: 12px;
            font-size: 13px;
            color: var(--text-muted);
        }
        .ref-body .pagination .pages { display: flex; gap: 4px; flex-wrap: wrap; }
        .ref-body .pagination .pages button {
            padding: 4px 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: transparent;
            cursor: pointer;
            font-size: 12px;
            color: var(--text-muted);
            transition: 0.2s;
        }
        .ref-body .pagination .pages button:hover { background: var(--hover-bg); border-color: var(--accent); }
        .ref-body .pagination .pages button.active { background: var(--accent); color: white; border-color: var(--accent); }

        /* API Tester */
        .api-tester {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .api-tester .tester-header {
            padding: 16px 24px;
            background: var(--hover-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .api-tester .tester-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 8px; }

        .api-tester .tester-body { padding: 24px; }

        .api-tester .tester-body .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .api-tester .tester-body .form-group { margin-bottom: 14px; }
        .api-tester .tester-body .form-group label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px; color: var(--text-main); }
        .api-tester .tester-body .form-group select,
        .api-tester .tester-body .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-body);
            color: var(--text-main);
            outline: none;
            transition: 0.2s;
        }
        .api-tester .tester-body .form-group select:focus,
        .api-tester .tester-body .form-group input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(36,107,253,0.1);
        }

        /* Response Box */
        .response-box {
            background: var(--code-bg);
            color: var(--code-text);
            padding: 16px 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 16px;
        }
        .response-box .success { color: #6ee7b7; }
        .response-box .error { color: #f87171; }
        .response-box .info { color: #93c5fd; }

        /* Status Codes */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        .status-item {
            background: var(--bg-body);
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid var(--accent);
        }
        .status-item .code { font-weight: 700; font-size: 18px; color: var(--accent); }
        .status-item .desc { font-size: 13px; color: var(--text-muted); }

        /* Errors Grid */
        .error-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }
        .error-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            background: var(--bg-body);
            border-radius: 6px;
            font-size: 13px;
        }
        .error-item .code { font-family: 'Courier New', monospace; font-weight: 600; color: var(--danger); }
        .error-item .desc { color: var(--text-muted); }
        .error-item .resolution { font-size: 12px; color: var(--text-muted); }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 16px; }
            .top-bar { flex-direction: column; align-items: flex-start; }
            .api-key-card { flex-direction: column; align-items: stretch; }
            .api-tester .tester-body .form-row { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .error-grid { grid-template-columns: 1fr; }
            .ref-body { padding: 0 12px; }
            .ref-body.open { padding: 12px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .top-bar .page-title h1 { font-size: 22px; }
            .api-tester .tester-body { padding: 16px; }
            .response-box { font-size: 11px; padding: 12px; }
        }

        .menu-toggle { display: none; background: transparent; border: none; font-size: 24px; color: var(--text-main); cursor: pointer; padding: 5px; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99; backdrop-filter: blur(3px); }
        .overlay.active { display: block; }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--bg-card);
            color: var(--text-main);
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border-left: 4px solid var(--success);
            font-size: 14px;
            font-weight: 500;
            z-index: 99999;
            animation: slideIn 0.3s ease;
            max-width: 350px;
        }
        .toast.error { border-left-color: var(--danger); }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(50px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .ref-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .ref-tabs button {
            padding: 8px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-muted);
            transition: 0.2s;
        }
        .ref-tabs button:hover { border-color: var(--accent); color: var(--accent); }
        .ref-tabs button.active { background: var(--accent); color: white; border-color: var(--accent); }

        .ref-content { display: none; }
        .ref-content.active { display: block; }
    </style>
</head>
<body>

<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<div class="app-container">

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-area">
            <div class="logo-inner">
                <?php if(!empty($siteSettings['site_logo'])): ?>
                    <div class="logo-circle"><img src="<?php echo htmlspecialchars($siteSettings['site_logo']); ?>" style="width:100%;height:100%;object-fit:cover;"></div>
                <?php else: ?>
                    <div class="logo-circle">O</div>
                <?php endif; ?>
                <span><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPSMS'); ?></span>
            </div>
            <button class="theme-toggle" onclick="toggleTheme()">
                <i class="fa-solid fa-moon"></i>
            </button>
        </div>

        <div class="menu-label">API Documentation</div>
        <a class="menu-item active" href="#overview"><i class="fa-solid fa-house"></i> Overview</a>
        <a class="menu-item" href="#tester"><i class="fa-solid fa-flask"></i> API Tester</a>
        <a class="menu-item" href="#endpoints"><i class="fa-solid fa-code"></i> Endpoints</a>
        <a class="menu-item" href="#reference"><i class="fa-solid fa-book"></i> Reference</a>
        <a class="menu-item" href="#status-codes"><i class="fa-solid fa-list-check"></i> Status Codes</a>
        <a class="menu-item" href="#errors"><i class="fa-solid fa-triangle-exclamation"></i> Errors</a>

        <div class="menu-label">Navigation</div>
        <a class="menu-item" href="user/dashboard.php"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        <?php if($isLoggedIn): ?>
            <a class="menu-item" href="logout.php" style="color:var(--danger);"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
            <a class="menu-item" href="index.php"><i class="fa-solid fa-sign-in-alt"></i> Login</a>
        <?php endif; ?>

        <div class="user-profile" onclick="window.location.href='user/dashboard.php?tab=profile'">
            <div class="avatar"><?php echo isset($_SESSION['user_name']) ? strtoupper($_SESSION['user_name'][0]) : 'G'; ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($userEmail); ?></div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>📚 API Documentation</h1>
                <p>Integrate SMS verification into your applications</p>
            </div>
            <div class="actions">
                <button class="menu-toggle" onclick="toggleSidebar()" style="display:none;">
                    <i class="fa-solid fa-bars-staggered"></i>
                </button>
                <a href="#tester" class="btn btn-primary"><i class="fa-solid fa-play"></i> Test API</a>
            </div>
        </div>

        <!-- API Key Card -->
        <div class="api-key-card">
            <div>
                <div class="key-label">🔑 Your API Key</div>
                <div class="key-value" id="api-key-display">
                    <?php if($isLoggedIn && !empty($userApiKey)): ?>
                        <?php echo htmlspecialchars($userApiKey); ?>
                    <?php else: ?>
                        <span style="color:var(--text-muted); font-weight:400;">Please login to get your API key</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="key-actions">
                <?php if($isLoggedIn && !empty($userApiKey)): ?>
                    <button class="btn-copy" onclick="copyApiKey()"><i class="fa-regular fa-copy"></i> Copy</button>
                    <button class="btn-regenerate" onclick="regenerateApiKey()"><i class="fa-solid fa-arrows-rotate"></i> Regenerate</button>
                <?php endif; ?>
                <?php if(!$isLoggedIn): ?>
                    <a href="index.php" class="btn btn-primary"><i class="fa-solid fa-sign-in-alt"></i> Login to Get Key</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" onclick="switchRefTab('countries')"><div class="number"><?php echo number_format($totalCountries); ?></div><div class="label">🌍 Countries</div></div>
            <div class="stat-card" onclick="switchRefTab('services')"><div class="number"><?php echo number_format($totalServices); ?></div><div class="label">📱 Services</div></div>
            <div class="stat-card" onclick="switchRefTab('servers')"><div class="number"><?php echo number_format($totalServers); ?></div><div class="label">🖥️ Servers</div></div>
            <div class="stat-card"><div class="number">99.9%</div><div class="label">⚡ Uptime</div></div>
        </div>

        <!-- Overview -->
        <div id="overview" class="ref-section">
            <div class="ref-header">
                <h3>📖 Overview</h3>
            </div>
            <div class="ref-body open" style="max-height:500px; padding:16px 20px;">
                <p style="color:var(--text-muted); line-height:1.8; font-size:15px;">
                    Welcome to the <strong><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPSMS'); ?></strong> API. 
                    This API allows you to integrate SMS verification services into your applications.
                </p>
                <div style="margin-top:14px;">
                    <h4 style="margin-bottom:8px;">📋 Quick Start Workflow</h4>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">
                        <div style="background:var(--bg-body); padding:12px 16px; border-radius:8px; border-left:4px solid var(--accent);">
                            <strong>1. Check Balance</strong>
                            <div style="font-size:13px; color:var(--text-muted);">Verify sufficient funds</div>
                        </div>
                        <div style="background:var(--bg-body); padding:12px 16px; border-radius:8px; border-left:4px solid var(--success);">
                            <strong>2. Get Number</strong>
                            <div style="font-size:13px; color:var(--text-muted);">Purchase a virtual number</div>
                        </div>
                        <div style="background:var(--bg-body); padding:12px 16px; border-radius:8px; border-left:4px solid var(--warning);">
                            <strong>3. Get Status</strong>
                            <div style="font-size:13px; color:var(--text-muted);">Poll for incoming SMS</div>
                        </div>
                        <div style="background:var(--bg-body); padding:12px 16px; border-radius:8px; border-left:4px solid var(--danger);">
                            <strong>4. Set Status</strong>
                            <div style="font-size:13px; color:var(--text-muted);">Complete or cancel</div>
                        </div>
                    </div>
                </div>
                <div style="margin-top:14px; padding:12px 16px; background:var(--bg-body); border-radius:8px;">
                    <strong>🔗 Base URL</strong>
                    <div style="font-family:'Courier New',monospace; font-size:14px; color:var(--accent); margin-top:4px;">
                        <?php echo htmlspecialchars($apiBaseUrl); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Tester -->
        <div id="tester" class="api-tester">
            <div class="tester-header">
                <h3><i class="fa-solid fa-flask" style="color:var(--accent);"></i> API Tester</h3>
                <span style="font-size:13px; color:var(--text-muted);">Test API endpoints in real-time</span>
            </div>
            <div class="tester-body">
                <?php if(!$isLoggedIn): ?>
                    <div style="text-align:center; padding:30px; background:var(--bg-body); border-radius:12px;">
                        <i class="fa-solid fa-lock" style="font-size:40px; color:var(--text-muted); display:block; margin-bottom:12px;"></i>
                        <p style="font-size:16px; font-weight:600;">Please login to test the API</p>
                        <a href="index.php" class="btn btn-primary" style="margin-top:12px;"><i class="fa-solid fa-sign-in-alt"></i> Login</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>API Key</label>
                                <input type="text" name="api_key" value="<?php echo htmlspecialchars($userApiKey); ?>">
                            </div>
                            <div class="form-group">
                                <label>Action</label>
                                <select name="test_action" id="test-action" onchange="toggleParams(this.value)">
                                    <option value="getBalance">Get Balance</option>
                                    <option value="getNumber" selected>Get Number</option>
                                    <option value="getStatus">Get Status</option>
                                    <option value="setStatus">Set Status</option>
                                </select>
                            </div>
                        </div>

                        <div id="params-getNumber" class="form-row">
                            <div class="form-group">
                                <label>Service Code</label>
                                <select name="service">
                                    <?php foreach($services as $svc): ?>
                                        <option value="<?php echo htmlspecialchars($svc['code']); ?>">
                                            <?php echo htmlspecialchars($svc['code']); ?> - <?php echo htmlspecialchars($svc['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Country Code</label>
                                <select name="country">
                                    <?php foreach($countries as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['code']); ?>">
                                            <?php echo $c['flag'] ?? '🌍'; ?> <?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="grid-column:1/-1;">
                                <label>Operator</label>
                                <select name="operator">
                                    <option value="any">Any</option>
                                    <?php foreach($operators as $op): ?>
                                        <option value="<?php echo htmlspecialchars($op['code']); ?>"><?php echo htmlspecialchars($op['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div id="params-getStatus" style="display:none;" class="form-row">
                            <div class="form-group" style="grid-column:1/-1;">
                                <label>Activation ID</label>
                                <input type="text" name="activation_id" placeholder="Enter activation ID from Get Number response">
                            </div>
                            <div class="form-group" style="grid-column:1/-1;" id="status-code-group" style="display:none;">
                                <label>Status Code</label>
                                <select name="status_code">
                                    <option value="3">3 - Request another SMS</option>
                                    <option value="8" selected>8 - Cancel/Complete</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-play"></i> Send Request</button>
                    </form>

                    <?php if($testResult): ?>
                        <div style="margin-top:20px; border-top:1px solid var(--border-color); padding-top:16px;">
                            <h4 style="font-weight:600; margin-bottom:8px;">📤 Response</h4>
                            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:8px;">
                                <span style="background:var(--bg-body); padding:4px 12px; border-radius:6px; font-size:12px;">
                                    <strong>HTTP:</strong> <?php echo $testResult['http_code']; ?>
                                </span>
                            </div>
                            <div class="response-box">
                                <span class="info">// Request URL</span>
                                <?php echo htmlspecialchars($testResult['url']); ?>

                                <span class="info" style="display:block; margin-top:8px;">// Response</span>
                                <?php if($testResult['error']): ?>
                                    <span class="error">Error: <?php echo htmlspecialchars($testResult['error']); ?></span>
                                <?php else: ?>
                                    <?php 
                                    $response = trim($testResult['response']);
                                    if (strpos($response, 'ACCESS_BALANCE') !== false || 
                                        strpos($response, 'ACCESS_NUMBER') !== false || 
                                        strpos($response, 'STATUS_OK') !== false ||
                                        strpos($response, 'ACCESS_') !== false) {
                                        echo '<span class="success">' . htmlspecialchars($response) . '</span>';
                                    } elseif (strpos($response, 'ERROR') !== false || strpos($response, 'BAD_') !== false) {
                                        echo '<span class="error">' . htmlspecialchars($response) . '</span>';
                                    } else {
                                        echo htmlspecialchars($response);
                                    }
                                    ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Endpoints -->
        <div id="endpoints">
            <h2 style="font-size:22px; font-weight:800; margin-bottom:16px;">🔗 API Endpoints</h2>
        </div>

        <!-- Get Balance -->
        <div class="ref-section">
            <div class="ref-header" onclick="toggleRef(this)">
                <h3><span class="method method-get" style="background:#dbeafe; color:#2563eb; padding:2px 10px; border-radius:4px; font-size:12px;">GET</span> action=getBalance</h3>
                <span style="font-size:13px; color:var(--text-muted);">Check your current account balance</span>
                <span class="endpoint-icon"><i class="fa-solid fa-chevron-down"></i></span>
            </div>
            <div class="ref-body open">
                <div class="code-block" style="background:var(--code-bg); color:var(--code-text); padding:12px 16px; border-radius:8px; font-family:'Courier New',monospace; font-size:13px; overflow-x:auto; margin-bottom:12px; white-space:pre-wrap; word-break:break-all;">
                    <?php echo htmlspecialchars($apiBaseUrl); ?>?api_key=YOUR_API_KEY&action=getBalance
                </div>
                <table>
                    <thead><tr><th>Name</th><th>Type</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><strong>action</strong></td><td>string</td><td>Fixed value: <code>getBalance</code></td></tr>
                        <tr><td><strong>api_key</strong></td><td>string</td><td>Your API Key</td></tr>
                    </tbody>
                </table>
                <div class="response-example" style="background:var(--bg-body); padding:10px 16px; border-radius:8px; font-family:'Courier New',monospace; font-size:13px; border-left:4px solid var(--success); margin-bottom:12px;">ACCESS_BALANCE:150.50</div>
                <div class="code-block" style="background:var(--code-bg); color:var(--code-text); padding:12px 16px; border-radius:8px; font-family:'Courier New',monospace; font-size:13px; overflow-x:auto; white-space:pre-wrap; word-break:break-all;">
curl -X GET "<?php echo htmlspecialchars($apiBaseUrl); ?>?api_key=<span class="hl-string" style="color:#6ee7b7;">YOUR_API_KEY</span>&action=<span class="hl-keyword" style="color:#93c5fd;">getBalance</span>"
                </div>
            </div>
        </div>

        <!-- Get Number -->
        <div class="ref-section">
            <div class="ref-header" onclick="toggleRef(this)">
                <h3><span class="method method-get" style="background:#dbeafe; color:#2563eb; padding:2px 10px; border-radius:4px; font-size:12px;">GET</span> action=getNumber</h3>
                <span style="font-size:13px; color:var(--text-muted);">Purchase a virtual phone number</span>
                <span class="endpoint-icon"><i class="fa-solid fa-chevron-down"></i></span>
            </div>
            <div class="ref-body">
                <div class="code-block" style="background:var(--code-bg); color:var(--code-text); padding:12px 16px; border-radius:8px; font-family:'Courier New',monospace; font-size:13px; overflow-x:auto; margin-bottom:12px; white-space:pre-wrap; word-break:break-all;">
                    <?php echo htmlspecialchars($apiBaseUrl); ?>?api_key=YOUR_API_KEY&action=getNumber&service=wa&country=22&operator=any
                </div>
                <table>
                    <thead><tr><th>Name</th><th>Type</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><strong>action</strong></td><td>string</td><td>Fixed value: <code>getNumber</code></td></tr>
                        <tr><td><strong>api_key</strong></td><td>string</td><td>Your API Key</td></tr>
                        <tr><td><strong>service</strong></td><td>string</td><td>Service code (e.g., <code>wa</code> for WhatsApp)</td></tr>
                        <tr><td><strong>country</strong></td><td>number</td><td>Country code (e.g., <code>22</code> for India)</td></tr>
                        <tr><td><strong>operator</strong></td><td>string</td><td>Operator code (e.g., <code>any</code>)</td></tr>
                    </tbody>
                </table>
                <div class="response-example" style="background:var(--bg-body); padding:10px 16px; border-radius:8px; font-family:'Courier New',monospace; font-size:13px; border-left:4px solid var(--success); margin-bottom:12px;">ACCESS_NUMBER:12345:919876543210</div>
                <div class="code-block" style="background:var(--code-bg); color:var(--code-text); padding:12px 16px; border-radius:8px; font-family:'Courier New',monospace; font-size:13px; overflow-x:auto; white-space:pre-wrap; word-break:break-all;">
curl -X GET "<?php echo htmlspecialchars($apiBaseUrl); ?>?api_key=<span class="hl-string" style="color:#6ee7b7;">YOUR_API_KEY</span>&action=<span class="hl-keyword" style="color:#93c5fd;">getNumber</span>&service=<span class="hl-string" style="color:#6ee7b7;">wa</span>&country=<span class="hl-string" style="color:#6ee7b7;">22</span>&operator=<span class="hl-string" style="color:#6ee7b7;">any</span>"
                </div>
            </div>
        </div>

        <!-- Get Status -->
        <div class="ref-section">
            <div class="ref-header" onclick="toggleRef(this)">
                <h3><span class="method method-get" style="background:#dbeafe; color:#2563eb; padding:2px 10px; border-radius:4px; font-size:12px;">GET</span> action=getStatus</h3>
                <span style="font-size:13px; color:var(--text-muted);">Check SMS status and retrieve OTP</span>
                <span class="endpoint-icon"><i class="fa-solid fa-chevron-down"></i></span>
            </div>
            <div class="ref-body">
                <div class="code-block" style="background:var(--code-bg); color:var(--code-text); padding:12px 16px; border-radius:8px; font-family:'Courier New',monospace; font-size:13px; overflow-x:auto; margin-bottom:12px; white-space:pre-wrap; word-break:break-all;">
                    <?php echo htmlspecialchars($apiBaseUrl); ?>?api_key=YOUR_API_KEY&action=getStatus&id=12345
                </div>
                <table>
                    <thead><tr><th>Name</th><th>Type</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><strong>action</strong></td><td>string</td><td>Fixed value: <code>getStatus</code></td></tr>
                        <tr><td><strong>api_key</strong></td><td>string</td><td>Your API Key</td></tr>
                        <tr><td><strong>id</strong></td><td>number</td><td>Activation ID</td></tr>
                    </tbody>
                </table>
                <div class="response-example" style="background:var(--bg-body); padding:10px 16px; border-radius:8px; font-family:'Courier New',monospace; font-size:13px; border-left:4px solid var(--success); margin-bottom:12px;">STATUS_OK:123456</div>
                <div class="code-block" style="background:var(--code-bg); color:var(--code-text); padding:12px 16px; border-radius:8px; font-family:'Courier New',monospace; font-size:13px; overflow-x:auto; white-space:pre-wrap; word-break:break-all;">
curl -X GET "<?php echo htmlspecialchars($apiBaseUrl); ?>?api_key=<span class="hl-string" style="color:#6ee7b7;">YOUR_API_KEY</span>&action=<span class="hl-keyword" style="color:#93c5fd;">getStatus</span>&id=<span class="hl-string" style="color:#6ee7b7;">12345</span>"
                </div>
            </div>
        </div>

        <!-- Set Status -->
        <div class="ref-section">
            <div class="ref-header" onclick="toggleRef(this)">
                <h3><span class="method method-get" style="background:#dbeafe; color:#2563eb; padding:2px 10px; border-radius:4px; font-size:12px;">GET</span> action=setStatus</h3>
                <span style="font-size:13px; color:var(--text-muted);">Update activation status</span>
                <span class="endpoint-icon"><i class="fa-solid fa-chevron-down"></i></span>
            </div>
            <div class="ref-body">
                <div class="code-block" style="background:var(--code-bg); color:var(--code-text); padding:12px 16px; border-radius:8px; font-family:'Courier New',monospace; font-size:13px; overflow-x:auto; margin-bottom:12px; white-space:pre-wrap; word-break:break-all;">
                    <?php echo htmlspecialchars($apiBaseUrl); ?>?api_key=YOUR_API_KEY&action=setStatus&id=12345&status=8
                </div>
                <table>
                    <thead><tr><th>Name</th><th>Type</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><strong>action</strong></td><td>string</td><td>Fixed value: <code>setStatus</code></td></tr>
                        <tr><td><strong>api_key</strong></td><td>string</td><td>Your API Key</td></tr>
                        <tr><td><strong>id</strong></td><td>number</td><td>Activation ID</td></tr>
                        <tr><td><strong>status</strong></td><td>number</td><td>3=Resend, 8=Cancel/Complete</td></tr>
                    </tbody>
                </table>
                <div class="response-example" style="background:var(--bg-body); padding:10px 16px; border-radius:8px; font-family:'Courier New',monospace; font-size:13px; border-left:4px solid var(--success); margin-bottom:12px;">ACCESS_CANCEL</div>
                <div class="code-block" style="background:var(--code-bg); color:var(--code-text); padding:12px 16px; border-radius:8px; font-family:'Courier New',monospace; font-size:13px; overflow-x:auto; white-space:pre-wrap; word-break:break-all;">
curl -X GET "<?php echo htmlspecialchars($apiBaseUrl); ?>?api_key=<span class="hl-string" style="color:#6ee7b7;">YOUR_API_KEY</span>&action=<span class="hl-keyword" style="color:#93c5fd;">setStatus</span>&id=<span class="hl-string" style="color:#6ee7b7;">12345</span>&status=<span class="hl-string" style="color:#6ee7b7;">8</span>"
                </div>
            </div>
        </div>

        <!-- Reference Data -->
        <div id="reference">
            <h2 style="font-size:22px; font-weight:800; margin-bottom:16px;">📚 Reference Data</h2>
            <p style="color:var(--text-muted); margin-bottom:16px; font-size:14px;">Lookup tables for codes used across endpoints.</p>

            <div class="ref-tabs">
                <button class="active" onclick="switchRefTab('countries', this)">🌍 Countries (<?php echo count($countries); ?>)</button>
                <button onclick="switchRefTab('services', this)">📱 Services (<?php echo count($services); ?>)</button>
                <button onclick="switchRefTab('servers', this)">🖥️ Servers (<?php echo count($servers); ?>)</button>
                <button onclick="switchRefTab('operators', this)">📡 Operators (<?php echo count($operators); ?>)</button>
            </div>

            <!-- Countries -->
            <div id="ref-countries" class="ref-content active">
                <div class="ref-section">
                    <div class="ref-header">
                        <h3>🌍 Countries</h3>
                        <span class="count"><?php echo count($countries); ?> countries</span>
                    </div>
                    <div class="ref-body open" style="max-height:500px; padding:16px 20px;">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass" style="color: var(--text-muted);"></i>
                            <input type="text" id="country-search" placeholder="Search countries by name or code..." oninput="filterTable('country-table', this.value)">
                        </div>
                        <div style="overflow-x:auto; max-height:350px; overflow-y:auto;">
                            <table id="country-table">
                                <thead>
                                    <tr><th>Flag</th><th>Name</th><th>Code</th><th>Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach($countries as $c): ?>
                                        <tr>
                                            <td class="flag"><?php echo $c['flag'] ?? '🌍'; ?></td>
                                            <td><?php echo htmlspecialchars($c['name']); ?></td>
                                            <td><code><?php echo htmlspecialchars($c['code']); ?></code></td>
                                            <td><button class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($c['code']); ?>')"><i class="fa-regular fa-copy"></i> Copy</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination">
                            <span><?php echo count($countries); ?>/<?php echo count($countries); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Services -->
            <div id="ref-services" class="ref-content">
                <div class="ref-section">
                    <div class="ref-header">
                        <h3>📱 Services</h3>
                        <span class="count"><?php echo count($services); ?> services</span>
                    </div>
                    <div class="ref-body open" style="max-height:500px; padding:16px 20px;">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass" style="color: var(--text-muted);"></i>
                            <input type="text" id="service-search" placeholder="Search services by name or code..." oninput="filterTable('service-table', this.value)">
                        </div>
                        <div style="overflow-x:auto; max-height:350px; overflow-y:auto;">
                            <table id="service-table">
                                <thead>
                                    <tr><th>Icon</th><th>Name</th><th>Code</th><th>Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach($services as $s): ?>
                                        <tr>
                                            <td><?php echo !empty($s['icon']) ? '<img src="'.$s['icon'].'" style="width:24px;height:24px;object-fit:contain;">' : '🔑'; ?></td>
                                            <td><?php echo htmlspecialchars($s['name']); ?></td>
                                            <td><code><?php echo htmlspecialchars($s['code']); ?></code></td>
                                            <td><button class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($s['code']); ?>')"><i class="fa-regular fa-copy"></i> Copy</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination">
                            <span><?php echo count($services); ?>/<?php echo count($services); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Servers -->
            <div id="ref-servers" class="ref-content">
                <div class="ref-section">
                    <div class="ref-header">
                        <h3>🖥️ Servers</h3>
                        <span class="count"><?php echo count($servers); ?> servers</span>
                    </div>
                    <div class="ref-body open" style="max-height:500px; padding:16px 20px;">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass" style="color: var(--text-muted);"></i>
                            <input type="text" id="server-search" placeholder="Search servers by name or code..." oninput="filterTable('server-table', this.value)">
                        </div>
                        <div style="overflow-x:auto; max-height:350px; overflow-y:auto;">
                            <table id="server-table">
                                <thead>
                                    <tr><th>ID</th><th>Name</th><th>Country</th><th>Code</th><th>Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach($servers as $s): ?>
                                        <tr>
                                            <td>#<?php echo $s['id']; ?></td>
                                            <td><?php echo htmlspecialchars($s['name']); ?></td>
                                            <td><?php echo ($s['country_emoji'] ?? '🌍') . ' ' . htmlspecialchars($s['country_name'] ?? 'N/A'); ?></td>
                                            <td><code><?php echo htmlspecialchars($s['code'] ?? $s['id']); ?></code></td>
                                            <td><button class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($s['code'] ?? $s['id']); ?>')"><i class="fa-regular fa-copy"></i> Copy</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination">
                            <span><?php echo count($servers); ?>/<?php echo count($servers); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Operators -->
            <div id="ref-operators" class="ref-content">
                <div class="ref-section">
                    <div class="ref-header">
                        <h3>📡 Operators</h3>
                        <span class="count"><?php echo count($operators); ?> operators</span>
                    </div>
                    <div class="ref-body open" style="max-height:500px; padding:16px 20px;">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass" style="color: var(--text-muted);"></i>
                            <input type="text" id="operator-search" placeholder="Search operators..." oninput="filterTable('operator-table', this.value)">
                        </div>
                        <div style="overflow-x:auto; max-height:350px; overflow-y:auto;">
                            <table id="operator-table">
                                <thead>
                                    <tr><th>Name</th><th>Code</th><th>Action</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach($operators as $o): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($o['name']); ?></td>
                                            <td><code><?php echo htmlspecialchars($o['code']); ?></code></td>
                                            <td><button class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($o['code']); ?>')"><i class="fa-regular fa-copy"></i> Copy</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination">
                            <span><?php echo count($operators); ?>/<?php echo count($operators); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Codes -->
        <div id="status-codes">
            <h2 style="font-size:22px; font-weight:800; margin-bottom:16px;">📋 Activation Status Codes</h2>
            <p style="color:var(--text-muted); margin-bottom:16px; font-size:14px;">Use these status values when calling setStatus.</p>

            <div class="status-grid">
                <div class="status-item" style="border-left-color:var(--success);">
                    <div class="code">1</div>
                    <div class="desc">Number received - Confirm SMS receiver is ready</div>
                </div>
                <div class="status-item" style="border-left-color:var(--warning);">
                    <div class="code">3</div>
                    <div class="desc">Request another code - Ask provider to resend a fresh OTP</div>
                </div>
                <div class="status-item" style="border-left-color:var(--accent);">
                    <div class="code">6</div>
                    <div class="desc">Complete activation - Mark the activation as finished</div>
                </div>
                <div class="status-item" style="border-left-color:var(--danger);">
                    <div class="code">8</div>
                    <div class="desc">Cancel activation - Cancel and refund (if eligible)</div>
                </div>
            </div>
        </div>

        <!-- Errors -->
        <div id="errors">
            <h2 style="font-size:22px; font-weight:800; margin-bottom:16px;">⚠️ Common Errors</h2>

            <div class="ref-section">
                <div class="ref-body open" style="padding:16px 20px;">
                    <div class="error-grid">
                        <div class="error-item" style="flex-direction:column; gap:4px;">
                            <div style="display:flex; justify-content:space-between; width:100%;">
                                <span class="code">BAD_KEY</span>
                                <span class="desc">Invalid or revoked API key</span>
                            </div>
                            <span class="resolution" style="font-size:12px; color:var(--text-muted);">💡 Regenerate your key and update integrations</span>
                        </div>
                        <div class="error-item" style="flex-direction:column; gap:4px;">
                            <div style="display:flex; justify-content:space-between; width:100%;">
                                <span class="code">NO_BALANCE</span>
                                <span class="desc">Insufficient wallet balance</span>
                            </div>
                            <span class="resolution" style="font-size:12px; color:var(--text-muted);">💡 Top up your account on the Wallet page</span>
                        </div>
                        <div class="error-item" style="flex-direction:column; gap:4px;">
                            <div style="display:flex; justify-content:space-between; width:100%;">
                                <span class="code">NO_NUMBERS</span>
                                <span class="desc">No numbers available for the selection</span>
                            </div>
                            <span class="resolution" style="font-size:12px; color:var(--text-muted);">💡 Try a different country, service, or operator</span>
                        </div>
                        <div class="error-item" style="flex-direction:column; gap:4px;">
                            <div style="display:flex; justify-content:space-between; width:100%;">
                                <span class="code">BAD_SERVICE</span>
                                <span class="desc">Service code does not exist</span>
                            </div>
                            <span class="resolution" style="font-size:12px; color:var(--text-muted);">💡 Use the Services reference table above</span>
                        </div>
                        <div class="error-item" style="flex-direction:column; gap:4px;">
                            <div style="display:flex; justify-content:space-between; width:100%;">
                                <span class="code">BAD_COUNTRY</span>
                                <span class="desc">Country code does not exist</span>
                            </div>
                            <span class="resolution" style="font-size:12px; color:var(--text-muted);">💡 Use the Countries reference table above</span>
                        </div>
                        <div class="error-item" style="flex-direction:column; gap:4px;">
                            <div style="display:flex; justify-content:space-between; width:100%;">
                                <span class="code">ACCOUNT_BAN</span>
                                <span class="desc">Account has been suspended</span>
                            </div>
                            <span class="resolution" style="font-size:12px; color:var(--text-muted);">💡 Contact support to resolve</span>
                        </div>
                        <div class="error-item" style="flex-direction:column; gap:4px; grid-column:1/-1;">
                            <div style="display:flex; justify-content:space-between; width:100%;">
                                <span class="code">EARLY_CANCEL_DENIED</span>
                                <span class="desc">Cancellation requested too soon after purchase</span>
                            </div>
                            <span class="resolution" style="font-size:12px; color:var(--text-muted);">💡 Wait at least 2 minutes before cancelling</span>
                        </div>
                        <div class="error-item" style="flex-direction:column; gap:4px; grid-column:1/-1;">
                            <div style="display:flex; justify-content:space-between; width:100%;">
                                <span class="code">NO_ACTIVATION</span>
                                <span class="desc">Activation ID not found or expired</span>
                            </div>
                            <span class="resolution" style="font-size:12px; color:var(--text-muted);">💡 Verify the id you received from getNumber</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align:center; padding:30px 0 10px; color:var(--text-muted); font-size:13px; border-top:1px solid var(--border-color); margin-top:20px;">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPSMS'); ?> - API Documentation v2.0
        </div>

    </div>
</div>

<!-- ============================================================
JAVASCRIPT
============================================================ -->
<script>
    // ============================================================
    // THEME TOGGLE
    // ============================================================
    function toggleTheme() {
        document.body.classList.toggle('dark-mode');
        const btn = document.querySelector('.theme-toggle');
        const icon = btn.querySelector('i');
        if (document.body.classList.contains('dark-mode')) {
            icon.className = 'fa-solid fa-sun';
        } else {
            icon.className = 'fa-solid fa-moon';
        }
        localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
    }

    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        const btn = document.querySelector('.theme-toggle');
        if (btn) {
            btn.querySelector('i').className = 'fa-solid fa-sun';
        }
    }

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
    // TOGGLE REFERENCE SECTION
    // ============================================================
    function toggleRef(header) {
        const body = header.nextElementSibling;
        const icon = header.querySelector('.endpoint-icon i');
        
        if (body.classList.contains('open')) {
            body.classList.remove('open');
            if (icon) icon.className = 'fa-solid fa-chevron-down';
        } else {
            body.classList.add('open');
            if (icon) icon.className = 'fa-solid fa-chevron-up';
        }
    }

    // ============================================================
    // SWITCH REFERENCE TAB
    // ============================================================
    function switchRefTab(tab, btn) {
        // Hide all ref contents
        document.querySelectorAll('.ref-content').forEach(el => el.classList.remove('active'));
        
        // Show selected
        const target = document.getElementById('ref-' + tab);
        if (target) target.classList.add('active');
        
        // Update buttons
        document.querySelectorAll('.ref-tabs button').forEach(b => b.classList.remove('active'));
        if (btn) {
            btn.classList.add('active');
        } else {
            document.querySelectorAll('.ref-tabs button').forEach(b => {
                if (b.textContent.toLowerCase().includes(tab)) {
                    b.classList.add('active');
                }
            });
        }
    }

    // ============================================================
    // FILTER TABLE
    // ============================================================
    function filterTable(tableId, query) {
        const table = document.getElementById(tableId);
        if (!table) return;
        const rows = table.querySelectorAll('tbody tr');
        const q = query.toLowerCase().trim();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = q === '' || text.includes(q) ? '' : 'none';
        });
    }

    // ============================================================
    // TOGGLE API TESTER PARAMS
    // ============================================================
    function toggleParams(action) {
        const getNumberParams = document.getElementById('params-getNumber');
        const getStatusParams = document.getElementById('params-getStatus');
        const statusCodeGroup = document.getElementById('status-code-group');
        
        if (action === 'getNumber') {
            getNumberParams.style.display = 'grid';
            getStatusParams.style.display = 'none';
        } else if (action === 'getStatus') {
            getNumberParams.style.display = 'none';
            getStatusParams.style.display = 'grid';
            if (statusCodeGroup) statusCodeGroup.style.display = 'none';
        } else if (action === 'setStatus') {
            getNumberParams.style.display = 'none';
            getStatusParams.style.display = 'grid';
            if (statusCodeGroup) statusCodeGroup.style.display = 'block';
        } else {
            getNumberParams.style.display = 'none';
            getStatusParams.style.display = 'none';
        }
    }

    // ============================================================
    // COPY TEXT
    // ============================================================
    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('✅ Copied: ' + text);
            }).catch(() => fallbackCopy(text));
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('✅ Copied: ' + text);
    }

    // ============================================================
    // COPY API KEY
    // ============================================================
    function copyApiKey() {
        const key = document.getElementById('api-key-display').textContent.trim();
        if (!key || key.includes('Please login')) {
            showToast('❌ Please login to get your API key', 'error');
            return;
        }
        copyText(key);
    }

    // ============================================================
    // REGENERATE API KEY
    // ============================================================
    function regenerateApiKey() {
        if (!confirm('Are you sure you want to regenerate your API key?')) return;
        
        const formData = new FormData();
        formData.append('regenerate_key', '1');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('api-key-display').textContent = data.api_key;
                showToast('✅ API key regenerated successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('❌ ' + data.message, 'error');
            }
        })
        .catch(err => showToast('❌ Error: ' + err.message, 'error'));
    }

    // ============================================================
    // TOAST
    // ============================================================
    function showToast(message, type = 'success') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = `
                position: fixed; bottom: 20px; right: 20px; z-index: 99999;
                display: flex; flex-direction: column; gap: 10px;
                max-width: 350px; width: 100%;
            `;
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = 'toast' + (type === 'error' ? ' error' : '');
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(50px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // ============================================================
    // KEYBOARD SHORTCUTS
    // ============================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSidebar();
        if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
            const search = document.querySelector('.search-box input');
            if (search) { search.focus(); e.preventDefault(); }
        }
    });

    // ============================================================
    // SMOOTH SCROLL
    // ============================================================
    document.querySelectorAll('.sidebar .menu-item[href^="#"]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                closeSidebar();
            }
        });
    });

    console.log('✅ API Documentation Loaded Successfully');
    console.log('📚 Base URL:', '<?php echo htmlspecialchars($apiBaseUrl); ?>');
    console.log('🌍 Countries:', <?php echo count($countries); ?>);
    console.log('📱 Services:', <?php echo count($services); ?>);
    console.log('🖥️ Servers:', <?php echo count($servers); ?>);
</script>

</body>
</html>