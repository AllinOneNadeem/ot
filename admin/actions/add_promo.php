<?php
// ============================================================
// ADD PROMO CODE ACTION
// ============================================================
$code = strtoupper(trim($_POST['code'] ?? ''));
$name = trim($_POST['name'] ?? '');
$amount = $_POST['amount'] ?? 0;
$type = $_POST['type'] ?? 'fixed';
$max_uses = $_POST['max_uses'] ?? null;
$valid_from = $_POST['valid_from'] ?? null;
$valid_until = $_POST['valid_until'] ?? null;
$for_new_users = $_POST['for_new_users'] ?? 0;

if (empty($code) || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Code and amount required']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO PromoCodes (code, name, amount, type, max_uses, valid_from, valid_until, for_new_users) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$code, $name, $amount, $type, $max_uses, $valid_from, $valid_until, $for_new_users]);
    echo json_encode(['success' => true, 'message' => 'Promo code added successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>