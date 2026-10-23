<?php
// ============================================================
// ADD USER ACTION
// ============================================================
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user';
$balance = $_POST['balance'] ?? 0;

if (empty($name) || empty($email) || strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO Users (name, email, password, role, balance) VALUES (?, ?, ?, ?, ?)");
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt->execute([$name, $email, $hashed, $role, $balance]);
    echo json_encode(['success' => true, 'message' => 'User added successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>