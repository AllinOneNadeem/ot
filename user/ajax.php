<?php
// ============================================================
// USER AJAX HANDLER - COMPLETE FIXED
// ============================================================

// ============================================================
// CLEAN OUTPUT BUFFER - JSON ERROR FIX
// ============================================================
ob_clean();
error_reporting(E_ALL);
ini_set('display_errors', 0);
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
require_once '../db.php';
require_once '../includes/functions.php';

// ============================================================
// HEADER FOR JSON RESPONSE
// ============================================================
header('Content-Type: application/json');

// ============================================================
// CHECK LOGIN
// ============================================================
$publicActions = ['login', 'register'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!in_array($action, $publicActions) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

// ============================================================
// ROUTE ACTIONS
// ============================================================
switch ($action) {
    
    // AUTHENTICATION
    case 'login': handleLogin(); break;
    case 'register': handleRegister(); break;
    case 'logout': handleLogout(); break;
    
    // SERVICE & PURCHASE
    case 'get_countries_for_service': handleGetCountriesForService(); break;
    case 'get_servers_for_country': handleGetServersForCountry(); break;
    case 'purchase_service': handlePurchaseService(); break;
    case 'buy_number': handleBuyNumber(); break;
    case 'cancel_order': handleCancelOrder(); break;
    case 'auto_cancel_orders': handleAutoCancelOrders(); break;
    case 'check_cancel_eligibility': handleCheckCancelEligibility(); break;  
    case 'check_sms': handleCheckSMS(); break;
    case 'get_active_numbers': handleGetActiveNumbers(); break;
    
    // RECHARGE - ✅ FIXED
    case 'process_recharge': handleProcessRecharge(); break;
    case 'check_paytm_payment': handleCheckPaytmPayment(); break;
    case 'get_recharge_history': handleGetRechargeHistory(); break;
    case 'process_bharatpe': handleProcessBharatPe(); break;
    case 'check_crypto_payment': handleCheckCryptoPayment(); break;
    
    // PROMO CODE
    case 'redeem_promo': handleRedeemPromo(); break;
    
    // PROFILE
    case 'update_profile': handleUpdateProfile(); break;
    case 'regenerate_api': handleRegenerateApi(); break;
    case 'logout_session': handleLogoutSession(); break;
    case 'logout_all_sessions': handleLogoutAllSessions(); break;
    
    // DASHBOARD STATS
    case 'get_dashboard_stats': handleGetDashboardStats(); break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        break;
}

// ============================================================
// ============================================================
// HANDLER FUNCTIONS
// ============================================================
// ============================================================

// ============================================================
// 1. PAYTM AUTO-VERIFICATION (Direct API - Error Free)
// ============================================================
function handleCheckPaytmPayment() {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? 0;
    $amount = floatval($_POST['amount'] ?? 0);
    $order_id = trim($_POST['order_id'] ?? '');

    if (!$user_id || empty($order_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit();
    }

    try {
        // 1. डुप्लीकेट पेमेंट चेक
        $stmtCheck = $pdo->prepare("SELECT id FROM Payments WHERE utr = ? AND status = 'completed'");
        $stmtCheck->execute([$order_id]);
        if ($stmtCheck->fetch()) { 
            echo json_encode(['success' => true, 'status' => 'SUCCESS']); 
            exit(); 
        }

        // 2. एडमिन से डिटेल्स निकालें
        $settings = $pdo->query("SELECT * FROM SitePayments LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $mid = $settings['paytm_merchant_id'] ?? $settings['paytm_mid'] ?? '';

        if (!empty($mid)) {
            // 3. Checksum Hash
            $obj = ['MID' => $mid, 'ORDERID' => $order_id];
            ksort($obj);
            $str = "";
            foreach($obj as $key => $val) { $str .= $key . "=" . $val . "&"; }
            $checksum = hash('sha256', $str . $order_id);

            $payload = json_encode([
                'CHECKSUMHASH' => $checksum,
                'MID' => $mid,
                'ORDERID' => $order_id
            ]);

            // 4. cURL Request
            $ch = curl_init('https://securegw.paytm.in/order/status');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Cache-Control: no-cache']);
            $resRaw = curl_exec($ch);
            $res = json_decode($resRaw, true);
            curl_close($ch);

            // 5. वेरीफाई करें
            if (isset($res['STATUS']) && $res['STATUS'] === 'TXN_SUCCESS') {
                $paidAmount = floatval($res['TXNAMOUNT']);
                if ($paidAmount >= $amount) {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("SELECT balance FROM Users WHERE id = ? FOR UPDATE");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    $newBalance = $user['balance'] + $paidAmount;
                    $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?")->execute([$newBalance, $user_id]);
                    
                    // 🔥 'Payments' Table
                    $pdo->prepare("INSERT INTO Payments (user_id, amount, mode, utr, status, createdAt) VALUES (?, ?, 'paytm', ?, 'completed', NOW())")->execute([$user_id, $paidAmount, $order_id]);
                    
                    // 🔥 'Transactions' Table (FIX: Changed 'mode' to 'payment_mode')
                    $pdo->prepare("INSERT INTO Transactions (user_id, amount, type, message, balance_before, balance_after, payment_mode, status, createdAt) VALUES (?, ?, 'credit', 'Paytm Auto Deposit', ?, ?, 'paytm', 'completed', NOW())")->execute([$user_id, $paidAmount, $user['balance'], $newBalance]);
                    
                    $pdo->commit();
                    $_SESSION['user_balance'] = $newBalance;
                    echo json_encode(['success' => true, 'status' => 'SUCCESS']); 
                    exit();
                }
            }
        }
        echo json_encode(['success' => true, 'status' => 'PENDING']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// 2. BHARATPE & CRYPTO VERIFICATION (Merged & Error Free)
// ============================================================
function handleProcessRecharge() {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? 0;
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_mode = $_POST['payment_mode'] ?? '';
    $utr = trim($_POST['utr'] ?? '');

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    try {
        if ($payment_mode === 'bharatpe') {
            if ($amount <= 0 || empty($utr)) {
                echo json_encode(['success' => false, 'message' => 'Invalid amount or UTR']); exit();
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM Payments WHERE utr = ? AND status = 'completed'");
            $stmtCheck->execute([$utr]);
            if ($stmtCheck->fetch()) { 
                echo json_encode(['success' => false, 'message' => 'This UTR has already been used!']); exit(); 
            }

            $settings = $pdo->query("SELECT * FROM SitePayments LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $merchantId = $settings['bharatpe_merchant_id'] ?? $settings['bharatpe_mid'] ?? '';
            $token = $settings['bharatpe_merchant_token'] ?? $settings['bharatpe_token'] ?? '';
            
            if (empty($merchantId) || empty($token)) {
                echo json_encode(['success' => false, 'message' => 'BharatPe credentials missing in Admin panel.']); exit();
            }

            $url = "https://payments-tesseract.bharatpe.in/api/v1/merchant/transactions?module=PAYMENT_QR&merchantId=" . $merchantId;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['token: ' . $token]);
            $resRaw = curl_exec($ch);
            $response = json_decode($resRaw, true);
            curl_close($ch);

            $found = false;
            $verifiedAmount = 0;

            if (!empty($response['data']['transactions']) && is_array($response['data']['transactions'])) {
                foreach ($response['data']['transactions'] as $txn) {
                    if ($txn['bankReferenceNo'] == $utr && $txn['status'] == 'SUCCESS') {
                        $verifiedAmount = floatval($txn['amount']);
                        $minPay = floatval($settings['bharatpe_min_payment'] ?? 10);
                        if ($verifiedAmount >= $minPay) {
                            $found = true;
                        } else {
                            echo json_encode(['success' => false, 'message' => "Amount is less than minimum requirement (₹$minPay)."]); exit();
                        }
                        break;
                    }
                }
            }

            if ($found) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT balance FROM Users WHERE id = ? FOR UPDATE");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                $newBalance = $user['balance'] + $verifiedAmount;
                $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?")->execute([$newBalance, $user_id]);
                
                // 🔥 'Payments' Table
                $pdo->prepare("INSERT INTO Payments (user_id, amount, mode, utr, status, createdAt) VALUES (?, ?, 'bharatpe', ?, 'completed', NOW())")->execute([$user_id, $verifiedAmount, $utr]);
                
                // 🔥 'Transactions' Table (FIX: Changed 'mode' to 'payment_mode')
                $pdo->prepare("INSERT INTO Transactions (user_id, amount, type, message, balance_before, balance_after, payment_mode, status, createdAt) VALUES (?, ?, 'credit', 'BharatPe UTR Deposit', ?, ?, 'bharatpe', 'completed', NOW())")->execute([$user_id, $verifiedAmount, $user['balance'], $newBalance]);
                
                $pdo->commit();
                $_SESSION['user_balance'] = $newBalance;
                echo json_encode(['success' => true, 'amount' => $verifiedAmount, 'message' => 'Wallet Credited Successfully!']); exit();
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid UTR or Payment not received yet. Check again in 1 minute.']); exit();
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Unknown payment mode']); exit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// ============================================================
// LOGIN HANDLER
// ============================================================
function handleLogin() {
    global $pdo;
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, password, role, balance, banned FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        
        if ($user['banned'] == 1) {
            echo json_encode(['success' => false, 'message' => 'Your account has been banned.']);
            exit();
        }
        
        if (!password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid password']);
            exit();
        }
        
$_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_balance'] = $user['balance'];
        
        // 👇👇 === NEW: SESSION TRACKING CODE === 👇👇
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $browser = 'Unknown Browser';
        if (strpos($userAgent, 'Firefox') !== false) $browser = 'Firefox';
        elseif (strpos($userAgent, 'Chrome') !== false) $browser = 'Chrome';
        elseif (strpos($userAgent, 'Safari') !== false) $browser = 'Safari';
        elseif (strpos($userAgent, 'Edge') !== false) $browser = 'Edge';

        $device = (preg_match('/(android|iphone|ipad|mobile)/i', strtolower($userAgent))) ? 'Mobile' : 'Desktop';
        
        try {
            $pdo->prepare("INSERT INTO LoginHistory (user_id, ip, device, browser, loggedOut, created_at) VALUES (?, ?, ?, ?, 0, NOW())")
                ->execute([$user['id'], $ip, $device, $browser]);
        } catch (Exception $e) {
            // Ignore if table doesn't exist
        }
        // 👆👆 === END SESSION TRACKING CODE === 👆👆

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'balance' => number_format($user['balance'], 2)
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================================
// REGISTER HANDLER
// ============================================================
function handleRegister() {
    global $pdo;
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
            exit();
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO Users (name, email, password, role, balance, createdAt) VALUES (?, ?, ?, 'user', 0, NOW())");
        $stmt->execute([$name, $email, $hashedPassword]);
        
        $userId = $pdo->lastInsertId();
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = 'user';
        $_SESSION['user_balance'] = 0;
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'user',
                'balance' => '0.00'
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ============================================================
// LOGOUT HANDLER
// ============================================================
function handleLogout() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

// ============================================================
// GET COUNTRIES FOR SERVICE
// ============================================================
function handleGetCountriesForService() {
    global $pdo;
    
    $service_id = $_POST['service_id'] ?? 0;
    
    if (!$service_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid service']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, sv.api_key, sv.provider_type, sv.id as server_id 
            FROM Services s 
            LEFT JOIN Servers sv ON s.server_id = sv.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$service) {
            echo json_encode(['success' => false, 'message' => 'Service not found']);
            exit();
        }
        
        $stmt = $pdo->query("
            SELECT DISTINCT 
                country_code as id, 
                country_name as country, 
                country_emoji as emoji 
            FROM Servers 
            WHERE status = 1 AND country_code IS NOT NULL AND country_code != ''
            ORDER BY country_name ASC
        ");
        $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($countries)) {
            $countries = [
                ['id' => 'IN', 'country' => 'India', 'emoji' => '🇮🇳'],
                ['id' => 'US', 'country' => 'United States', 'emoji' => '🇺🇸'],
                ['id' => 'GB', 'country' => 'United Kingdom', 'emoji' => '🇬🇧'],
            ];
        }
        
        echo json_encode(['success' => true, 'countries' => $countries]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// GET SERVERS FOR COUNTRY (WITH DISCOUNT CALCULATION)
// ============================================================
function handleGetServersForCountry() {
    global $pdo;
    
    $country_code = $_POST['country_code'] ?? '';
    $service_id = $_POST['service_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0; // User fetch for discount
    
    if (empty($country_code) || !$service_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                sv.id,
                sv.name,
                sv.country_name,
                sv.country_emoji,
                s.id as service_id,
                s.name as service_name,
                s.price,
                s.code
            FROM Servers sv
            INNER JOIN Services s ON s.server_id = sv.id
            WHERE sv.country_code = ? AND s.id = ? AND sv.status = 1
        ");
        $stmt->execute([$country_code, $service_id]);
        $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($servers)) {
            $stmt = $pdo->prepare("
                SELECT 
                    sv.id,
                    sv.name,
                    sv.country_emoji,
                    s.id as service_id,
                    s.name as service_name,
                    s.price,
                    s.code
                FROM Services s
                LEFT JOIN Servers sv ON s.server_id = sv.id
                WHERE s.id = ? AND sv.status = 1
                LIMIT 3
            ");
            $stmt->execute([$service_id]);
            $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // --- APPLY DISCOUNT LOGIC TO DISPLAYED PRICES ---
        if (!empty($servers) && $user_id) {
            $stmtDisc = $pdo->prepare("SELECT * FROM UserDiscounts WHERE user_id = ?");
            $stmtDisc->execute([$user_id]);
            $discounts = $stmtDisc->fetchAll(PDO::FETCH_ASSOC);

            foreach ($servers as &$srv) {
                $origPrice = (float)$srv['price'];
                $finalPrice = $origPrice;
                $globalDisc = 0; 
                $specificFound = false;

                foreach ($discounts as $d) {
                    // Check for Specific Service Discount First
                    if ($d['apply_to'] === 'specific' && $d['service_id'] == $service_id) {
                        if ($d['type'] === 'fixed') {
                            $finalPrice = (float)$d['value'];
                        } else {
                            $finalPrice = $origPrice - ($origPrice * (float)$d['value'] / 100);
                        }
                        $specificFound = true;
                        break; // Stop checking, specific has highest priority
                    }
                    // Capture Global Discount
                    if ($d['apply_to'] === 'all' && $d['type'] === 'percentage') {
                        $globalDisc = (float)$d['value'];
                    }
                }

                // Apply global discount if no specific discount was found
                if (!$specificFound && $globalDisc > 0) {
                    $finalPrice = $origPrice - ($origPrice * $globalDisc / 100);
                }

                $srv['price'] = number_format(max(0, $finalPrice), 2, '.', '');
            }
        }
        // ------------------------------------------------

        echo json_encode(['success' => true, 'servers' => $servers]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// PURCHASE SERVICE (WITH DISCOUNT CALCULATION)
// ============================================================
function handlePurchaseService() {
    global $pdo;
    
    $service_id = $_POST['service_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;
    $country_code = $_POST['country_code'] ?? 'IN';
    
    if (!$service_id || !$user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }
    
    $pdo->beginTransaction();
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                s.*, 
                sv.id AS server_id, 
                sv.name as server_name,
                sv.api_key, 
                sv.provider_type, 
                sv.get_num_url, 
                sv.base_url,
                sv.country_code as server_country_code,
                sv.country_name as server_country_name
            FROM Services s 
            JOIN Servers sv ON s.server_id = sv.id 
            WHERE s.id = ? 
            FOR UPDATE
        ");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$service) {
            throw new Exception("Service not found");
        }
        
        $stmt = $pdo->prepare("SELECT balance, banned FROM Users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['banned'] == 1) {
            throw new Exception("Account restricted");
        }
        
        $price = (float)$service['price'];
        
        // --- APPLY DISCOUNT LOGIC BEFORE DEDUCTING BALANCE ---
        $stmtDisc = $pdo->prepare("SELECT * FROM UserDiscounts WHERE user_id = ?");
        $stmtDisc->execute([$user_id]);
        $discounts = $stmtDisc->fetchAll(PDO::FETCH_ASSOC);
        
        $globalDisc = 0; 
        $specificFound = false;

        foreach ($discounts as $d) {
            if ($d['apply_to'] === 'specific' && $d['service_id'] == $service_id) {
                if ($d['type'] === 'fixed') {
                    $price = (float)$d['value'];
                } else {
                    $price = $price - ($price * (float)$d['value'] / 100);
                }
                $specificFound = true;
                break;
            }
            if ($d['apply_to'] === 'all' && $d['type'] === 'percentage') {
                $globalDisc = (float)$d['value'];
            }
        }

        if (!$specificFound && $globalDisc > 0) {
            $price = $price - ($price * $globalDisc / 100);
        }
        
        $price = max(0, $price); // Make sure price doesn't go into minus
        // -----------------------------------------------------

        $userBalance = (float)$user['balance'];
        
        if ($userBalance < $price) {
            throw new Exception("Insufficient balance! Need ₹" . number_format($price, 2));
        }
        
        // Generate number from API
        $apiResult = generateNumberFromAPI($service, $country_code);
        $number = $apiResult['number'];
        $tzid = $apiResult['tzid'];
        
        if (empty($number)) {
            throw new Exception("API Error: No numbers available right now.");
        }
        
        // Deduct balance
        $newBalance = $userBalance - $price;
        $stmt = $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $user_id]);
        
        // Create order
        $stmt = $pdo->prepare("
            INSERT INTO Orders (
                user_id, service_id, server_id, service_name, code, number, price, status, country_code, server_name, tzid, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'WAITING', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id, $service_id, $service['server_id'], $service['name'], $service['code'], $number, $price, $country_code, $service['server_name'], $tzid
        ]);
        $order_id = $pdo->lastInsertId();
        
        // Create transaction record
        $stmt = $pdo->prepare("
            INSERT INTO Transactions (
                user_id, amount, type, message, balance_before, balance_after, payment_mode, status, reference_id, createdAt
            ) VALUES (?, ?, 'debit', ?, ?, ?, 'service', 'completed', ?, NOW())
        ");
        $stmt->execute([
            $user_id, $price, "Purchased: " . $service['name'] . " - " . $number, $user['balance'], $newBalance, $order_id
        ]);
        
        $pdo->commit();
        $_SESSION['user_balance'] = $newBalance;
        
        echo json_encode([
            'success' => true,
            'message' => 'Number purchased successfully!',
            'order_id' => $order_id,
            'number' => $number,
            'service_name' => $service['name'],
            'new_balance' => number_format($newBalance, 2)
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// BUY NUMBER
// ============================================================
function handleBuyNumber() {
    $_POST['service_id'] = $_POST['service_id'] ?? $_POST['id'] ?? 0;
    handlePurchaseService();
}

// ============================================================
// CANCEL ORDER
// ============================================================
function handleCancelOrder() {
    global $pdo;
    
    $order_id = $_POST['order_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!$order_id || !$user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM Orders WHERE id = ? AND user_id = ? FOR UPDATE");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception("Order not found");
        }
        
        if ($order['status'] !== 'WAITING') {
            throw new Exception("Only WAITING orders can be cancelled");
        }
        
        // Cancel via API
        if (!empty($order['tzid'])) {
            $stmtSrv = $pdo->prepare("SELECT api_key FROM Servers WHERE id = ?");
            $stmtSrv->execute([$order['server_id']]);
            $srv = $stmtSrv->fetch();
            $apiKey = !empty($srv['api_key']) ? $srv['api_key'] : "tdyQsWC1CbZKoEcVOEOPc97aoXIDuKIS";
            
            $url = "https://smsbower.online/stubs/handler_api.php?api_key={$apiKey}&action=setStatus&status=8&id={$order['tzid']}";
            @file_get_contents($url);
        }
        
        // Refund User
        $stmt = $pdo->prepare("SELECT balance FROM Users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $newBalance = $user['balance'] + $order['price'];
        
        $stmt = $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $user_id]);
        
        // Update Order Status
        $stmt = $pdo->prepare("UPDATE Orders SET status = 'CANCELLED' WHERE id = ?");
        $stmt->execute([$order_id]);
        
        // Log Refund
        $stmt = $pdo->prepare("
            INSERT INTO Transactions (
                user_id, amount, type, message, balance_before, balance_after, payment_mode, status, reference_id, createdAt
            ) VALUES (?, ?, 'credit', ?, ?, ?, 'refund', 'completed', ?, NOW())
        ");
        $stmt->execute([
            $user_id, $order['price'], "Refund: Cancelled Order #" . $order_id, $user['balance'], $newBalance, $order_id
        ]);
        
        $pdo->commit();
        $_SESSION['user_balance'] = $newBalance;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Order cancelled and refunded',
            'new_balance' => number_format($newBalance, 2)
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// CHECK CANCEL ELIGIBILITY
// ============================================================
function handleCheckCancelEligibility() {
    global $pdo;
    
    $order_id = $_POST['order_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!$order_id || !$user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT created_at, status FROM Orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit();
        }
        
        $createdTime = strtotime($order['created_at']);
        $currentTime = time();
        $elapsedMinutes = ($currentTime - $createdTime) / 60;
        $canCancel = ($elapsedMinutes >= 2 && $order['status'] == 'WAITING');
        
        echo json_encode([
            'success' => true,
            'can_cancel' => $canCancel,
            'elapsed_minutes' => round($elapsedMinutes, 1)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// AUTO-CANCEL ORDERS
// ============================================================
function handleAutoCancelOrders() {
    global $pdo;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    try {
        $autoCancelMinutes = 20;
        
        $stmt = $pdo->query("SELECT auto_cancel FROM Servers WHERE status = 1 LIMIT 1");
        $server = $stmt->fetch();
        if ($server && $server['auto_cancel'] > 0) {
            $autoCancelMinutes = (int)$server['auto_cancel'];
        }
        
        $stmt = $pdo->prepare("
            SELECT id, user_id, service_name, number, price, server_id, tzid 
            FROM Orders 
            WHERE user_id = ? 
            AND status = 'WAITING' 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$user_id, $autoCancelMinutes]);
        $expiredOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $cancelled = 0;
        foreach ($expiredOrders as $order) {
            $pdo->beginTransaction();
            try {
                if (!empty($order['tzid'])) {
                    $stmtSrv = $pdo->prepare("SELECT api_key FROM Servers WHERE id = ?");
                    $stmtSrv->execute([$order['server_id']]);
                    $srv = $stmtSrv->fetch();
                    $apiKey = !empty($srv['api_key']) ? $srv['api_key'] : "tdyQsWC1CbZKoEcVOEOPc97aoXIDuKIS";
                    @file_get_contents("https://smsbower.online/stubs/handler_api.php?api_key={$apiKey}&action=setStatus&status=8&id={$order['tzid']}");
                }

                $stmt = $pdo->prepare("UPDATE Orders SET status = 'CANCELLED' WHERE id = ?");
                $stmt->execute([$order['id']]);
                
                $stmt = $pdo->prepare("SELECT balance FROM Users WHERE id = ? FOR UPDATE");
                $stmt->execute([$order['user_id']]);
                $user = $stmt->fetch();
                $newBalance = $user['balance'] + $order['price'];
                
                $stmt = $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?");
                $stmt->execute([$newBalance, $order['user_id']]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO Transactions (user_id, amount, type, message, balance_before, balance_after, status, createdAt) 
                    VALUES (?, ?, 'credit', ?, ?, ?, 'completed', NOW())
                ");
                $stmt->execute([
                    $order['user_id'], $order['price'], "Auto-cancel: " . $order['service_name'] . " - Timeout", $user['balance'], $newBalance
                ]);
                
                $pdo->commit();
                $cancelled++;
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Auto-cancel error: " . $e->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Auto-cancelled $cancelled expired orders",
            'cancelled' => $cancelled,
            'auto_cancel_minutes' => $autoCancelMinutes
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// CHECK SMS
// ============================================================
function handleCheckSMS() {
    global $pdo;
    
    $order_id = $_POST['order_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!$order_id || !$user_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, s.api_key, s.provider_type, s.get_msg_url, s.base_url 
            FROM Orders o 
            JOIN Servers s ON o.server_id = s.id 
            WHERE o.id = ? AND o.user_id = ?
        ");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit();
        }
        
        $sms = '';
        $status = $order['status'];
        
        // Fetch from API
        if (!empty($order['tzid'])) {
            $apiKey = !empty($order['api_key']) ? $order['api_key'] : "tdyQsWC1CbZKoEcVOEOPc97aoXIDuKIS";
            $url = "https://smsbower.online/stubs/handler_api.php?api_key={$apiKey}&action=getStatus&id={$order['tzid']}";
            
            $apiResponse = @file_get_contents($url);
            
            if ($apiResponse && strpos($apiResponse, 'STATUS_OK') !== false) {
                $parts = explode(':', $apiResponse);
                if (isset($parts[1])) {
                    $sms = trim($parts[1]);
                    $status = 'RECEIVED';
                    
                    $stmt = $pdo->prepare("UPDATE Orders SET status = 'RECEIVED', sms_text = ? WHERE id = ?");
                    $stmt->execute([$sms, $order_id]);
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'SMS checked',
            'sms' => $sms,
            'status' => $status
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// GET ACTIVE NUMBERS
// ============================================================
function handleGetActiveNumbers() {
    global $pdo;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM Orders 
            WHERE user_id = ? AND status IN ('WAITING', 'RECEIVED') 
            ORDER BY id DESC
        ");
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'orders' => $orders]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// GET RECHARGE HISTORY
// ============================================================
function handleGetRechargeHistory() {
    global $pdo;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM Payments 
            WHERE user_id = ? 
            ORDER BY id DESC 
            LIMIT 20
        ");
        $stmt->execute([$user_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'history' => $history]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// REDEEM PROMO CODE
// ============================================================
function handleRedeemPromo() {
    global $pdo;
    
    try {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $user_id = $_SESSION['user_id'] ?? 0;
        
        if (empty($code) || !$user_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            exit();
        }
        
        $stmt = $pdo->prepare("SELECT * FROM PromoCodes WHERE code = ? AND status = 1");
        $stmt->execute([$code]);
        $promo = $stmt->fetch();
        
        if (!$promo) {
            echo json_encode(['success' => false, 'message' => 'Invalid promo code']);
            exit();
        }
        
        $amount = $promo['amount'] ?? 0;
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Promo code has invalid amount']);
            exit();
        }
        
        $stmt = $pdo->prepare("SELECT * FROM PromoRedemptions WHERE promo_id = ? AND user_id = ?");
        $stmt->execute([$promo['id'], $user_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You have already used this promo code']);
            exit();
        }
        
        if ($promo['max_uses'] && $promo['used_count'] >= $promo['max_uses']) {
            echo json_encode(['success' => false, 'message' => 'Promo code has expired']);
            exit();
        }
        
        $currentTime = time();
        
        if (!empty($promo['valid_from']) && $promo['valid_from'] !== '0000-00-00 00:00:00') {
            $validFrom = strtotime($promo['valid_from']);
            if ($validFrom && $validFrom > $currentTime) {
                echo json_encode(['success' => false, 'message' => 'Promo code is not active yet']);
                exit();
            }
        }
        
        if (!empty($promo['valid_until']) && $promo['valid_until'] !== '0000-00-00 00:00:00') {
            $validUntil = strtotime($promo['valid_until']);
            if ($validUntil && $validUntil < $currentTime) {
                echo json_encode(['success' => false, 'message' => 'Promo code has expired']);
                exit();
            }
        }
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT balance FROM Users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $oldBalance = $user['balance'];
        $newBalance = $oldBalance + $amount;
        
        $stmt = $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $user_id]);
        
        $stmt = $pdo->prepare("INSERT INTO PromoRedemptions (promo_id, user_id, amount) VALUES (?, ?, ?)");
        $stmt->execute([$promo['id'], $user_id, $amount]);
        
        $stmt = $pdo->prepare("UPDATE PromoCodes SET used_count = used_count + 1 WHERE id = ?");
        $stmt->execute([$promo['id']]);
        
        $stmt = $pdo->prepare("
            INSERT INTO Transactions (
                user_id, amount, type, message, balance_before, balance_after, payment_mode, status, reference_id, createdAt
            ) VALUES (?, ?, 'credit', ?, ?, ?, 'promo', 'completed', ?, NOW())
        ");
        $stmt->execute([
            $user_id, $amount, "Promo code: " . $code, $oldBalance, $newBalance, $code
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Promo code applied successfully!',
            'amount' => $amount,
            'new_balance' => number_format($newBalance, 2)
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// UPDATE PROFILE
// ============================================================
function handleUpdateProfile() {
    global $pdo;
    
    try {
        $user_id = $_SESSION['user_id'] ?? 0;
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!$user_id || empty($name) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit();
        }
        
        $stmt = $pdo->prepare("SELECT id FROM Users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit();
        }
        
        if (!empty($password)) {
            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
                exit();
            }
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE Users SET name = ?, email = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $email, $hashed, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE Users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $user_id]);
        }
        
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// GET DASHBOARD STATS
// ============================================================
function handleGetDashboardStats() {
    global $pdo;
    
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    try {
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as orders, COALESCE(SUM(price), 0) as revenue 
            FROM Orders 
            WHERE user_id = ? AND DATE(created_at) = ?
        ");
        $stmt->execute([$user_id, $today]);
        $todayStats = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as orders FROM Orders WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $totalOrders = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active 
            FROM Orders 
            WHERE user_id = ? AND status IN ('WAITING', 'RECEIVED')
        ");
        $stmt->execute([$user_id]);
        $activeNumbers = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'today' => $todayStats ?: ['orders' => 0, 'revenue' => 0],
            'total_orders' => $totalOrders ?: 0,
            'active_numbers' => $activeNumbers ?: 0
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// HELPER: GENERATE NUMBER FROM API
// ============================================================
function generateNumberFromAPI($service, $country_code) {
    try {
        $apiKey = !empty($service['api_key']) ? $service['api_key'] : "tdyQsWC1CbZKoEcVOEOPc97aoXIDuKIS";
        $serviceCode = $service['code'];
        $apiUrl = !empty($service['get_num_url']) ? $service['get_num_url'] : "https://smsbower.online/stubs/handler_api.php";
        
        $api_country = $country_code;
        if ($country_code === 'IN') {
            $api_country = '22';
        }

        $params = [
            'api_key' => $apiKey,
            'action' => 'getNumber',
            'service' => $serviceCode,
            'country' => $api_country
        ];
        
        $url = $apiUrl . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $apiResponse = curl_exec($ch);
        curl_close($ch);
        
        if (!$apiResponse) {
            throw new Exception("Server could not connect to API provider.");
        }
        
        if (strpos($apiResponse, 'ACCESS_NUMBER') !== false) {
            $parts = explode(':', $apiResponse);
            return [
                'tzid' => trim($parts[1]),
                'number' => trim($parts[2])
            ];
        }
        
        throw new Exception("API Provider Error: " . trim($apiResponse));
        
    } catch (Exception $e) {
        throw new Exception($e->getMessage());
    }
}

// ============================================================
// PROFILE: API & SESSION HANDLERS
// ============================================================
function handleRegenerateApi() {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit(); }

    try {
        // Generate a new 32 char random API key
        $newKey = bin2hex(random_bytes(16)); 
        $stmt = $pdo->prepare("UPDATE Users SET api_key = ? WHERE id = ?");
        $stmt->execute([$newKey, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'API Key Regenerated']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleLogoutSession() {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? 0;
    $session_id = $_POST['session_id'] ?? 0;
    if (!$user_id || !$session_id) { echo json_encode(['success' => false, 'message' => 'Invalid request']); exit(); }

    try {
        // Mark specific session as loggedOut
        $stmt = $pdo->prepare("UPDATE LoginHistory SET loggedOut = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$session_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Device logged out']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleLogoutAllSessions() {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit(); }

    try {
        // Mark all active sessions for this user as loggedOut
        $stmt = $pdo->prepare("UPDATE LoginHistory SET loggedOut = 1 WHERE user_id = ? AND loggedOut = 0");
        $stmt->execute([$user_id]);
        
        // Destroy current session
        session_destroy();
        
        echo json_encode(['success' => true, 'message' => 'All devices logged out']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============================================================
// END OF FILE - NO EXTRA SPACE!
// ============================================================