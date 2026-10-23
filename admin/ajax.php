<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ============================================================
// ADMIN AJAX HANDLER - COMPLETE FIXED
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

require_once '../db.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check admin role
$stmt = $pdo->prepare("SELECT role FROM Users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================
// ROUTE TO APPROPRIATE HANDLER
// ============================================================
switch ($action) {
// ==========================================
    // ADMIN DASHBOARD STATS (Schema Matched)
    // ==========================================
    case 'get_dashboard_stats':
        try {
            $stats = [];

            // 1. Today's Performance
            $today = $pdo->query("
                SELECT 
                    COUNT(*) as orders,
                    SUM(CASE WHEN status IN ('RECEIVED', 'COMPLETED', 'success') THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status IN ('CANCELLED', 'failed') THEN 1 ELSE 0 END) as cancel,
                    COALESCE(SUM(price), 0) as revenue
                FROM Orders WHERE DATE(created_at) = CURDATE()
            ")->fetch(PDO::FETCH_ASSOC);
            $stats['today'] = $today ?: ['orders' => 0, 'success' => 0, 'cancel' => 0, 'revenue' => 0];

            // 2. This Week's Performance
            $week = $pdo->query("
                SELECT 
                    COUNT(*) as orders,
                    COALESCE(SUM(price), 0) as revenue
                FROM Orders WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
            ")->fetch(PDO::FETCH_ASSOC);
            $stats['week'] = $week ?: ['orders' => 0, 'revenue' => 0];

            // 3. This Month's Performance
            $month = $pdo->query("
                SELECT 
                    COUNT(*) as orders,
                    COALESCE(SUM(price), 0) as revenue
                FROM Orders WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
            ")->fetch(PDO::FETCH_ASSOC);
            $stats['month'] = $month ?: ['orders' => 0, 'revenue' => 0];

            // 4. Top Services
            $topServices = $pdo->query("
                SELECT service_name, COUNT(*) as count 
                FROM Orders 
                WHERE service_name IS NOT NULL AND service_name != '' 
                GROUP BY service_name 
                ORDER BY count DESC LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
            $stats['topServices'] = $topServices ?: [];

            // 5. Top Users
            $topUsers = $pdo->query("
                SELECT u.email, COUNT(o.id) as orders, COALESCE(SUM(o.price), 0) as spent 
                FROM Orders o 
                JOIN Users u ON o.user_id = u.id 
                GROUP BY o.user_id 
                ORDER BY spent DESC LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
            $stats['topUsers'] = $topUsers ?: [];

            echo json_encode(array_merge(['success' => true], $stats));
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    
    // ---- USER MANAGEMENT ----
    case 'add_user':
        require 'actions/add_user.php';
        break;
    case 'delete_user':
        require 'actions/delete_user.php';
        break;
    case 'toggle_ban':
        require 'actions/toggle_ban.php';
        break;
    case 'get_user':
        require 'actions/get_user.php';
        break;
    case 'update_user':
        require 'actions/update_user.php';
        break;
    case 'add_balance':
        require 'actions/add_balance.php';
        break;
    case 'cut_balance':
        require 'actions/cut_balance.php';
        break;
    
    // ---- SERVICE MANAGEMENT ----
    case 'add_service':
        require 'actions/add_service.php';
        break;
    case 'delete_service':
        require 'actions/delete_service.php';
        break;
    case 'get_service':
        require 'actions/get_service.php';
        break;
    case 'update_service':
        require 'actions/update_service.php';
        break;
    case 'import_services':
        require 'actions/import_services.php';
        break;
    case 'import_selected_services':
        require 'actions/import_selected_services.php';
        break;
    case 'get_countries':
        require 'actions/get_countries.php';
        break;
    case 'get_services_by_country':
        require 'actions/get_services_by_country.php';
        break;
    
    // ---- SERVER MANAGEMENT ----
    case 'add_server':
        require 'actions/add_server.php';
        break;
    case 'delete_server':
        require 'actions/delete_server.php';
        break;
    case 'get_server':
        require 'actions/get_server.php';
        break;
    case 'update_server':
        require 'actions/update_server.php';
        break;
    
    // ---- ORDER MANAGEMENT ----
    case 'update_order_status':
        require 'actions/update_order_status.php';
        break;
    
    // ---- PAYMENT MANAGEMENT ----
    case 'update_payment_status':
        require 'actions/update_payment_status.php';
        break;
    case 'verify_payment':
        require 'actions/verify_payment.php';
        break;
    
    // ---- PAYMENT SETTINGS ----
    case 'save_payment_settings':
        try {
            // 1. सबसे पहले डेटाबेस से पुराना डेटा निकालें (ताकि बाकी गेटवे डिलीट न हों)
            $existingData = $pdo->query("SELECT * FROM SitePayments LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
            
            // कौन सा गेटवे सबमिट हुआ है? (Paytm, Bharatpe या Crypto)
            $target = $_POST['gateway_target'] ?? '';

            // 2. सारे वेरिएबल्स में पुराना डेटा डाल दें (Default fallback)
            $paytm_status = $existingData['paytm_status'] ?? 0;
            $paytm_mid = $existingData['paytm_mid'] ?? '';
            $paytm_upi = $existingData['paytm_upi'] ?? '';
            $paytm_min = $existingData['paytm_min'] ?? 10;
            $paytm_max = $existingData['paytm_max'] ?? 2000;
            $paytm_instructions = $existingData['paytm_instructions'] ?? '';

            $bharatpe_status = $existingData['bharatpe_status'] ?? 0;
            $bharatpe_mid = $existingData['bharatpe_mid'] ?? '';
            $bharatpe_token = $existingData['bharatpe_token'] ?? '';
            $bharatpe_upi = $existingData['bharatpe_upi'] ?? '';
            $bharatpe_min = $existingData['bharatpe_min'] ?? 10;
            $bharatpe_qr = $existingData['bharatpe_qr'] ?? '';
            $bharatpe_instructions = $existingData['bharatpe_instructions'] ?? '';

            $crypto_status = $existingData['crypto_status'] ?? 0;
            $crypto_api = $existingData['crypto_api'] ?? '';
            $crypto_min = $existingData['crypto_min'] ?? 90;
            $crypto_address = $existingData['crypto_address'] ?? '';
            $crypto_instructions = $existingData['crypto_instructions'] ?? '';

            // 3. जो फॉर्म सबमिट हुआ है (target), सिर्फ उसी का डेटा $_POST से अपडेट करें
            if ($target === 'paytm') {
                $paytm_status = isset($_POST['paytm_status']) ? 1 : 0;
                $paytm_mid = trim($_POST['paytm_mid'] ?? '');
                $paytm_upi = trim($_POST['paytm_upi'] ?? '');
                $paytm_min = floatval($_POST['paytm_min'] ?? 10);
                $paytm_max = floatval($_POST['paytm_max'] ?? 2000);
                $paytm_instructions = trim($_POST['paytm_instructions'] ?? '');
            } 
            elseif ($target === 'bharatpe') {
                $bharatpe_status = isset($_POST['bharatpe_status']) ? 1 : 0;
                $bharatpe_mid = trim($_POST['bharatpe_mid'] ?? '');
                $bharatpe_token = trim($_POST['bharatpe_token'] ?? '');
                $bharatpe_upi = trim($_POST['bharatpe_upi'] ?? '');
                $bharatpe_min = floatval($_POST['bharatpe_min'] ?? 10);
                $bharatpe_qr = trim($_POST['bharatpe_qr'] ?? '');
                $bharatpe_instructions = trim($_POST['bharatpe_instructions'] ?? '');
            } 
            elseif ($target === 'crypto') {
                $crypto_status = isset($_POST['crypto_status']) ? 1 : 0;
                $crypto_api = trim($_POST['crypto_api'] ?? '');
                $crypto_min = floatval($_POST['crypto_min'] ?? 90);
                $crypto_address = trim($_POST['crypto_address'] ?? '');
                $crypto_instructions = trim($_POST['crypto_instructions'] ?? '');
            }

            // 4. डेटाबेस में सुरक्षित तरीके से सेव करें
            if (!empty($existingData) && isset($existingData['id'])) {
                $stmt = $pdo->prepare("UPDATE SitePayments SET 
                    paytm_status = ?, paytm_mid = ?, paytm_upi = ?, paytm_min = ?, paytm_max = ?, paytm_instructions = ?,
                    bharatpe_status = ?, bharatpe_mid = ?, bharatpe_token = ?, bharatpe_upi = ?, bharatpe_min = ?, bharatpe_qr = ?, bharatpe_instructions = ?,
                    crypto_status = ?, crypto_api = ?, crypto_min = ?, crypto_address = ?, crypto_instructions = ?
                    WHERE id = ?");
                $stmt->execute([
                    $paytm_status, $paytm_mid, $paytm_upi, $paytm_min, $paytm_max, $paytm_instructions,
                    $bharatpe_status, $bharatpe_mid, $bharatpe_token, $bharatpe_upi, $bharatpe_min, $bharatpe_qr, $bharatpe_instructions,
                    $crypto_status, $crypto_api, $crypto_min, $crypto_address, $crypto_instructions,
                    $existingData['id']
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO SitePayments (
                    paytm_status, paytm_mid, paytm_upi, paytm_min, paytm_max, paytm_instructions,
                    bharatpe_status, bharatpe_mid, bharatpe_token, bharatpe_upi, bharatpe_min, bharatpe_qr, bharatpe_instructions,
                    crypto_status, crypto_api, crypto_min, crypto_address, crypto_instructions
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $paytm_status, $paytm_mid, $paytm_upi, $paytm_min, $paytm_max, $paytm_instructions,
                    $bharatpe_status, $bharatpe_mid, $bharatpe_token, $bharatpe_upi, $bharatpe_min, $bharatpe_qr, $bharatpe_instructions,
                    $crypto_status, $crypto_api, $crypto_min, $crypto_address, $crypto_instructions
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Payment settings saved successfully!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
    
    // ---- API PROVIDERS ----
    case 'get_api_providers':
        require 'actions/get_api_providers.php';
        break;
    case 'add_api_provider':
        require 'actions/add_api_provider.php';
        break;
    case 'delete_api_provider':
        require 'actions/delete_api_provider.php';
        break;
    
    // ---- SITE SETTINGS ----
    case 'save_settings':
        require 'actions/save_settings.php';
        break;
    case 'update_currency_rate':
        require 'actions/update_currency_rate.php';
        break;
    case 'get_currency_rate':
        require 'actions/get_currency_rate.php';
        break;
    
    // ---- FOOTER MANAGEMENT ----
    case 'save_footer_settings':
        require 'actions/save_footer_settings.php';
        break;
    case 'add_footer_link':
        require 'actions/add_footer_link.php';
        break;
    case 'delete_footer_link':
        require 'actions/delete_footer_link.php';
        break;
    
    // ---- SUPPORT SETTINGS ----
    case 'save_support_settings':
        require 'actions/save_support_settings.php';
        break;
    
    // ---- BROADCAST ----
    case 'send_broadcast':
        require 'actions/send_broadcast.php';
        break;
    
    // ---- PROMO CODES ----
    case 'add_promo':
        require 'actions/add_promo.php';
        break;
    case 'delete_promo':
        require 'actions/delete_promo.php';
        break;
    case 'redeem_promo':
        require 'actions/redeem_promo.php';
        break;
    
    // ---- DISCOUNT ----
    case 'apply_discount':
        require 'actions/apply_discount.php';
        break;
    
   // ==========================================
// 1. GET USER DISCOUNTS (WITH FULL DETAILS)
// ==========================================
case 'get_user_discounts':
    $user_id = $_POST['user_id'] ?? 0;
    try {
        $stmt = $pdo->prepare("
            SELECT d.id as discount_id, d.type, d.value, d.apply_to, d.service_id,
                   s.name as service_name, s.code as service_code, s.logo as service_logo, s.price as original_price,
                   sv.name as server_name, sv.country_name, sv.country_emoji
            FROM UserDiscounts d
            LEFT JOIN Services s ON d.service_id = s.id
            LEFT JOIN Servers sv ON s.server_id = sv.id
            WHERE d.user_id = ? ORDER BY d.id DESC
        ");
        $stmt->execute([$user_id]);
        $discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'discounts' => $discounts]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    break;

// ==========================================
// 2. DELETE DISCOUNT
// ==========================================
case 'delete_discount':
    $discount_id = $_POST['discount_id'] ?? 0;
    try {
        $stmt = $pdo->prepare("DELETE FROM UserDiscounts WHERE id = ?");
        $stmt->execute([$discount_id]);
        echo json_encode(['success' => true, 'message' => 'Discount removed successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    break;

// ==========================================
// 3. APPLY OR EDIT DISCOUNT
// ==========================================
case 'apply_discount':
    $user_id = $_POST['user_id'] ?? 0;
    $type = $_POST['type'] ?? 'fixed';
    $value = $_POST['value'] ?? 0;
    $apply_to = $_POST['apply_to'] ?? 'all';
    $service_id = ($apply_to === 'specific') ? ($_POST['service_id'] ?? null) : null;
    $discount_id = $_POST['edit_discount_id'] ?? ''; // नया एडिट ID

    try {
        if (!empty($discount_id)) {
            // अगर ID है, तो अपडेट (Edit) करें
            $stmt = $pdo->prepare("UPDATE UserDiscounts SET type = ?, value = ?, apply_to = ?, service_id = ? WHERE id = ?");
            $stmt->execute([$type, $value, $apply_to, $service_id, $discount_id]);
            echo json_encode(['success' => true, 'message' => 'Discount updated successfully!']);
        } else {
            // वरना नया डिस्काउंट जोड़ें
            $stmt = $pdo->prepare("INSERT INTO UserDiscounts (user_id, type, value, apply_to, service_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $type, $value, $apply_to, $service_id]);
            echo json_encode(['success' => true, 'message' => 'Discount added successfully!']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    break;
    
    // ---- PROFILE ----
    case 'update_admin_profile':
        require 'actions/update_admin_profile.php';
        break;
    
    // ---- MISC ----
    case 'login_as_user':
        require 'actions/login_as_user.php';
        break;
    case 'reset_password':
        require 'actions/reset_password.php';
        break;
    case 'sync_services':
        echo json_encode(['success' => true, 'message' => 'Services synced']);
        break;
    
    // ---- PAGE MANAGEMENT ----
    case 'add_page':
        require 'actions/add_page.php';
        break;
    case 'get_page':
        require 'actions/get_page.php';
        break;
    case 'update_page':
        require 'actions/update_page.php';
        break;
    case 'delete_page':
        require 'actions/delete_page.php';
        break;
    
    // ---- BANNER MANAGEMENT ----
    case 'add_banner':
        require 'actions/add_banner.php';
        break;
    case 'get_banner':
        require 'actions/get_banner.php';
        break;
    case 'update_banner':
        require 'actions/update_banner.php';
        break;
    case 'delete_banner':
        require 'actions/delete_banner.php';
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        break;
    // ============================================================
    // PAYTM AUTO-VERIFICATION - ADDED CASES
    // ============================================================
    
    case 'check_paytm_payment':
        $amount = $_POST['amount'] ?? 0;
        $order_id = $_POST['order_id'] ?? '';
        $user_id = $_SESSION['user_id'] ?? 0;
        
        if (!$user_id || !$amount || empty($order_id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id, status FROM Payments WHERE utr = ? AND user_id = ?");
            $stmt->execute([$order_id, $user_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing && $existing['status'] === 'completed') {
                echo json_encode(['success' => true, 'status' => 'SUCCESS']);
                break;
            }
            
            if (!$existing) {
                $stmt = $pdo->prepare("INSERT INTO Payments (user_id, utr, amount, mode, status, createdAt) VALUES (?, ?, ?, 'paytm', 'pending', NOW())");
                $stmt->execute([$user_id, $order_id, $amount]);
            }
            
            $stmt = $pdo->prepare("SELECT status FROM Payments WHERE utr = ? AND user_id = ? AND status = 'completed'");
            $stmt->execute([$order_id, $user_id]);
            $isVerified = $stmt->fetch() ? true : false;
            
            echo json_encode([
                'success' => true,
                'status' => $isVerified ? 'SUCCESS' : 'PENDING',
                'message' => $isVerified ? 'Payment verified' : 'Waiting for payment'
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'process_recharge':
        $user_id = $_SESSION['user_id'] ?? 0;
        $amount = $_POST['amount'] ?? 0;
        $payment_mode = $_POST['payment_mode'] ?? 'upi';
        $utr = trim($_POST['utr'] ?? '');
        
        if (!$user_id || $amount <= 0 || empty($utr)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            break;
        }
        
        try {
            $stmt = $pdo->query("SELECT min_deposit FROM SitePayments LIMIT 1");
            $minDeposit = $stmt->fetchColumn() ?: 10;
            
            if ($amount < $minDeposit) {
                echo json_encode(['success' => false, 'message' => "Minimum deposit is ₹$minDeposit"]);
                break;
            }
            
            $stmt = $pdo->prepare("SELECT id FROM Payments WHERE utr = ? AND user_id = ?");
            $stmt->execute([$utr, $user_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'This transaction already processed']);
                break;
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT balance FROM Users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $oldBalance = $user['balance'];
            $newBalance = $oldBalance + $amount;
            
            $stmt = $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?");
            $stmt->execute([$newBalance, $user_id]);
            
            $stmt = $pdo->prepare("INSERT INTO Payments (user_id, utr, amount, mode, status, createdAt) VALUES (?, ?, ?, ?, 'completed', NOW())");
            $stmt->execute([$user_id, $utr, $amount, $payment_mode]);
            
            $stmt = $pdo->prepare("INSERT INTO Transactions (user_id, amount, type, message, balance_before, balance_after, payment_mode, reference_id, status, createdAt) 
                                   VALUES (?, ?, 'credit', ?, ?, ?, ?, ?, 'completed', NOW())");
            $stmt->execute([
                $user_id, 
                $amount, 
                "Payment via " . ucfirst($payment_mode) . " (UTR: $utr)", 
                $oldBalance, 
                $newBalance, 
                $payment_mode, 
                $utr
            ]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => "₹$amount credited successfully!",
                'new_balance' => $newBalance
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'verify_payment_manual':
        $payment_id = $_POST['payment_id'] ?? 0;
        $user_id = $_SESSION['user_id'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT role FROM Users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM Payments WHERE id = ? AND status = 'pending'");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                echo json_encode(['success' => false, 'message' => 'Payment not found or already processed']);
                break;
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT balance FROM Users WHERE id = ?");
            $stmt->execute([$payment['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $oldBalance = $user['balance'];
            $newBalance = $oldBalance + $payment['amount'];
            
            $stmt = $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?");
            $stmt->execute([$newBalance, $payment['user_id']]);
            
            $stmt = $pdo->prepare("UPDATE Payments SET status = 'completed' WHERE id = ?");
            $stmt->execute([$payment_id]);
            
            $stmt = $pdo->prepare("INSERT INTO Transactions (user_id, amount, type, message, balance_before, balance_after, payment_mode, reference_id, status, createdAt) 
                                   VALUES (?, ?, 'credit', ?, ?, ?, ?, ?, 'completed', NOW())");
            $stmt->execute([
                $payment['user_id'],
                $payment['amount'],
                "Payment verified by admin (UTR: {$payment['utr']})",
                $oldBalance,
                $newBalance,
                $payment['mode'],
                $payment['utr']
            ]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => "Payment of ₹{$payment['amount']} verified successfully!"
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
?>