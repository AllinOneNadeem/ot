<?php
// ============================================================
// UPDATE ADMIN PROFILE ACTION
// ============================================================
$id = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Name and email required']);
    exit();
}

try {
    if (!empty($password)) {
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            exit();
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE Users SET name = ?, email = ?, password = ? WHERE id = ? AND role = 'admin'");
        $stmt->execute([$name, $email, $hashed, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE Users SET name = ?, email = ? WHERE id = ? AND role = 'admin'");
        $stmt->execute([$name, $email, $id]);
    }
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>