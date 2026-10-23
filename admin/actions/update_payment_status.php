<?php
// ============================================================
// UPDATE PAYMENT STATUS ACTION - UPDATED
// ============================================================
$id = $_POST['payment_id'] ?? 0;
$status = $_POST['status'] ?? '';

if (!$id || !in_array($status, ['completed', 'failed', 'pending'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE Payments SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    
    // If status is completed, add balance
    if ($status === 'completed') {
        $stmt = $pdo->prepare("SELECT * FROM Payments WHERE id = ?");
        $stmt->execute([$id]);
        $payment = $stmt->fetch();
        
        if ($payment) {
            $pdo->beginTransaction();
            
            // Get user balance
            $stmt = $pdo->prepare("SELECT balance FROM Users WHERE id = ?");
            $stmt->execute([$payment['user_id']]);
            $user = $stmt->fetch();
            $oldBalance = $user['balance'];
            $newBalance = $oldBalance + $payment['amount'];
            
            // Update balance
            $stmt = $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?");
            $stmt->execute([$newBalance, $payment['user_id']]);
            
            // Add transaction
            $stmt = $pdo->prepare("INSERT INTO Transactions (user_id, amount, type, message, balance_before, balance_after, payment_mode, reference_id, status, createdAt) 
                                   VALUES (?, ?, 'credit', ?, ?, ?, ?, ?, 'completed', NOW())");
            $stmt->execute([
                $payment['user_id'],
                $payment['amount'],
                "Payment via " . ucfirst($payment['mode'] ?? 'paytm') . " (UTR: {$payment['utr']})",
                $oldBalance,
                $newBalance,
                $payment['mode'] ?? 'paytm',
                $payment['utr']
            ]);
            
            $pdo->commit();
        }
    }
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Payment updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}