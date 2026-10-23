<?php
// ============================================================
// RESET USER PASSWORD ACTION
// ============================================================
$id = $_POST['user_id'] ?? 0;
$password = $_POST['password'] ?? '';

if (!$id || strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE Users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed, $id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>