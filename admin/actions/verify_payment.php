<?php
// ============================================================
// VERIFY PAYMENT ACTION - UPDATED with Paytm & BharatPe Support
// ============================================================
$payment_id = $_POST['payment_id'] ?? 0;
$utr = trim($_POST['utr'] ?? '');

if (!$payment_id || empty($utr)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM Payments WHERE id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit();
    }

    $pdo->beginTransaction();

    // Update payment status
    $pdo->prepare("UPDATE Payments SET status = 'completed' WHERE id = ?")->execute([$payment_id]);

    // Get user balance
    $stmt = $pdo->prepare("SELECT balance FROM Users WHERE id = ?");
    $stmt->execute([$payment['user_id']]);
    $user = $stmt->fetch();
    $oldBalance = $user['balance'];
    $newBalance = $oldBalance + $payment['amount'];

    // Update user balance
    $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?")->execute([$newBalance, $payment['user_id']]);

    // Create transaction
    $mode = $payment['mode'] ?? 'bharatpe';
    $stmt = $pdo->prepare("INSERT INTO Transactions (user_id, amount, type, message, balance_before, balance_after, payment_mode, reference_id) 
                           VALUES (?, ?, 'credit', 'Payment via ' . ?, ?, ?, ?, ?)");
    $stmt->execute([$payment['user_id'], $payment['amount'], ucfirst($mode), $oldBalance, $newBalance, $mode, $utr]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Payment verified and balance added']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}