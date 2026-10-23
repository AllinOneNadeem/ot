<?php
// ============================================================
// ADD BALANCE TO USER ACTION
// ============================================================
$id = $_POST['user_id'] ?? 0;
$amount = $_POST['amount'] ?? 0;
$reason = trim($_POST['reason'] ?? 'Admin added balance');

if (!$id || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT balance FROM Users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    $oldBalance = $user['balance'];
    $newBalance = $oldBalance + $amount;

    $stmt = $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?");
    $stmt->execute([$newBalance, $id]);

    $stmt = $pdo->prepare("INSERT INTO Transactions (user_id, amount, type, message, balance_before, balance_after, payment_mode) VALUES (?, ?, 'credit', ?, ?, ?, 'admin')");
    $stmt->execute([$id, $amount, $reason, $oldBalance, $newBalance]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "₹$amount added successfully"]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>