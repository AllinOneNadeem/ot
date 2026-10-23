<?php
// ============================================================
// ACTION: SAVE PAYMENT SETTINGS (SEPARATE & SAFE UPDATE)
// ============================================================

ob_clean();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

global $pdo;

try {
    $stmtUser = $pdo->prepare("SELECT role FROM Users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user_id']]);
    $currentUser = $stmtUser->fetch();

    if (!$currentUser || $currentUser['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Security check error: ' . $e->getMessage()]);
    exit();
}

try {
    // 1. Data ko properly fetch karein
    $existingData = $pdo->query("SELECT * FROM SitePayments LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // Target detect karein ke kaunsa form submit hua hai
    $target = $_POST['gateway_target'] ?? '';

    // 2. Default Variables ko purane data se set karein (Taki wo blank na ho jayein)
    $paytm_status = $existingData['paytm_status'] ?? 0;
    $paytm_upi = $existingData['paytm_upi'] ?? '';
    $paytm_mid = $existingData['paytm_mid'] ?? '';
    $paytm_merchant_id = $existingData['paytm_merchant_id'] ?? '';
    $paytm_key = $existingData['paytm_key'] ?? '';
    $paytm_qr = $existingData['paytm_qr'] ?? '';
    $paytm_instructions = $existingData['paytm_instructions'] ?? '';
    $paytm_min = $existingData['paytm_min'] ?? 10;
    $paytm_max = $existingData['paytm_max'] ?? 2000;

    $bharatpe_status = $existingData['bharatpe_status'] ?? 0;
    $bharatpe_mid = $existingData['bharatpe_mid'] ?? '';
    $bharatpe_token = $existingData['bharatpe_token'] ?? '';
    $bharatpe_merchant_id = $existingData['bharatpe_merchant_id'] ?? '';
    $bharatpe_merchant_token = $existingData['bharatpe_merchant_token'] ?? '';
    $bharatpe_upi = $existingData['bharatpe_upi'] ?? '';
    $bharatpe_qr = $existingData['bharatpe_qr'] ?? '';
    $bharatpe_instructions = $existingData['bharatpe_instructions'] ?? '';
    $bharatpe_min = $existingData['bharatpe_min'] ?? 10;

    $crypto_status = $existingData['crypto_status'] ?? 0;
    $crypto_api = $existingData['crypto_api'] ?? '';
    $crypto_min = $existingData['crypto_min'] ?? 90;
    $crypto_address = $existingData['crypto_address'] ?? '';
    $crypto_instructions = $existingData['crypto_instructions'] ?? 'Send TRX to the address above';

    // 3. Update ONLY Target variables (Jis gateway ka button press kiya hai sirf use update karein)
    if ($target === 'paytm') {
        $paytm_status = isset($_POST['paytm_status']) ? (int)$_POST['paytm_status'] : 0;
        $paytm_upi = trim($_POST['paytm_upi'] ?? '');
        $paytm_mid = trim($_POST['paytm_mid'] ?? '');
        $paytm_merchant_id = $paytm_mid ?: $paytm_merchant_id;
        $paytm_instructions = trim($_POST['paytm_instructions'] ?? '');
        $paytm_min = floatval($_POST['paytm_min'] ?? 10);
        $paytm_max = floatval($_POST['paytm_max'] ?? 2000);
    } 
    elseif ($target === 'bharatpe') {
        $bharatpe_status = isset($_POST['bharatpe_status']) ? (int)$_POST['bharatpe_status'] : 0;
        $bharatpe_mid = trim($_POST['bharatpe_mid'] ?? '');
        $bharatpe_token = trim($_POST['bharatpe_token'] ?? '');
        $bharatpe_merchant_id = $bharatpe_mid ?: $bharatpe_merchant_id;
        $bharatpe_merchant_token = $bharatpe_token ?: $bharatpe_merchant_token;
        $bharatpe_upi = trim($_POST['bharatpe_upi'] ?? '');
        $bharatpe_qr = trim($_POST['bharatpe_qr'] ?? '');
        $bharatpe_instructions = trim($_POST['bharatpe_instructions'] ?? '');
        $bharatpe_min = floatval($_POST['bharatpe_min'] ?? 10);
    } 
    elseif ($target === 'crypto') {
        $crypto_status = isset($_POST['crypto_status']) ? (int)$_POST['crypto_status'] : 0;
        $crypto_api = trim($_POST['crypto_api'] ?? '');
        $crypto_min = floatval($_POST['crypto_min'] ?? 90);
        $crypto_address = trim($_POST['crypto_address'] ?? '');
        $crypto_instructions = trim($_POST['crypto_instructions'] ?? 'Send TRX to the address above');
    }

    // 4. Database Query Process
    if (!empty($existingData) && isset($existingData['id'])) {
        $stmt = $pdo->prepare("
            UPDATE SitePayments SET 
                paytm_status = ?, paytm_upi = ?, paytm_mid = ?, paytm_merchant_id = ?, paytm_key = ?, paytm_qr = ?, paytm_instructions = ?, paytm_min = ?, paytm_max = ?,
                bharatpe_status = ?, bharatpe_mid = ?, bharatpe_token = ?, bharatpe_merchant_id = ?, bharatpe_merchant_token = ?, bharatpe_upi = ?, bharatpe_qr = ?, bharatpe_instructions = ?, bharatpe_min = ?,
                crypto_status = ?, crypto_api = ?, crypto_min = ?, crypto_address = ?, crypto_instructions = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $paytm_status, $paytm_upi, $paytm_mid, $paytm_merchant_id, $paytm_key, $paytm_qr, $paytm_instructions, $paytm_min, $paytm_max,
            $bharatpe_status, $bharatpe_mid, $bharatpe_token, $bharatpe_merchant_id, $bharatpe_merchant_token, $bharatpe_upi, $bharatpe_qr, $bharatpe_instructions, $bharatpe_min,
            $crypto_status, $crypto_api, $crypto_min, $crypto_address, $crypto_instructions,
            $existingData['id']
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO SitePayments (
                paytm_status, paytm_upi, paytm_mid, paytm_merchant_id, paytm_key, paytm_qr, paytm_instructions, paytm_min, paytm_max,
                bharatpe_status, bharatpe_mid, bharatpe_token, bharatpe_merchant_id, bharatpe_merchant_token, bharatpe_upi, bharatpe_qr, bharatpe_instructions, bharatpe_min,
                crypto_status, crypto_api, crypto_min, crypto_address, crypto_instructions
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $paytm_status, $paytm_upi, $paytm_mid, $paytm_merchant_id, $paytm_key, $paytm_qr, $paytm_instructions, $paytm_min, $paytm_max,
            $bharatpe_status, $bharatpe_mid, $bharatpe_token, $bharatpe_merchant_id, $bharatpe_merchant_token, $bharatpe_upi, $bharatpe_qr, $bharatpe_instructions, $bharatpe_min,
            $crypto_status, $crypto_api, $crypto_min, $crypto_address, $crypto_instructions
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Payment settings saved successfully!']);
    exit();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?>