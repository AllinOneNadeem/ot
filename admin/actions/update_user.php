<?php
// ============================================================
// UPDATE USER ACTION
// ============================================================
$id = $_POST['user_id'] ?? 0;
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? 'user';
$balance = $_POST['balance'] ?? 0;
$banned = $_POST['banned'] ?? 0;

if (!$id || empty($name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE Users SET name = ?, email = ?, role = ?, balance = ?, banned = ? WHERE id = ? AND role != 'admin'");
    $stmt->execute([$name, $email, $role, $balance, $banned, $id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found or cannot update admin']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>