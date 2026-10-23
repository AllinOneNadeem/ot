<?php
// ============================================================
// REDEEM PROMO CODE ACTION (Public)
// ============================================================
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

    // Check if already used
    $stmt = $pdo->prepare("SELECT * FROM PromoRedemptions WHERE promo_id = ? AND user_id = ?");
    $stmt->execute([$promo['id'], $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already used this promo code']);
        exit();
    }

    // Check max uses
    if ($promo['max_uses'] && $promo['used_count'] >= $promo['max_uses']) {
        echo json_encode(['success' => false, 'message' => 'Promo code has expired']);
        exit();
    }

    // Check validity dates
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

    // Check new user only
    if ($promo['for_new_users']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Orders WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'This promo is for new users only']);
            exit();
        }
    }

    $pdo->beginTransaction();

    // Get user balance
    $stmt = $pdo->prepare("SELECT balance FROM Users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $oldBalance = $user['balance'];
    $newBalance = $oldBalance + $amount;

    // Update balance
    $stmt = $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?");
    $stmt->execute([$newBalance, $user_id]);

    // Record redemption
    $stmt = $pdo->prepare("INSERT INTO PromoRedemptions (promo_id, user_id, amount) VALUES (?, ?, ?)");
    $stmt->execute([$promo['id'], $user_id, $amount]);

    // Update used count
    $stmt = $pdo->prepare("UPDATE PromoCodes SET used_count = used_count + 1 WHERE id = ?");
    $stmt->execute([$promo['id']]);

    // Create transaction
    $stmt = $pdo->prepare("INSERT INTO Transactions (user_id, amount, type, message, balance_before, balance_after, payment_mode, status, reference_id) VALUES (?, ?, 'credit', ?, ?, ?, 'promo', 'completed', ?)");
    $stmt->execute([$user_id, $amount, "Promo code: " . $code, $oldBalance, $newBalance, $code]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Promo code applied successfully!',
        'amount' => $amount,
        'new_balance' => number_format($newBalance, 2)
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>