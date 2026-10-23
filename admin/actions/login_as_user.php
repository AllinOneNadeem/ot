<?php
// ============================================================
// LOGIN AS USER ACTION
// ============================================================
$id = $_POST['user_id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id FROM Users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_original_user_id'] = $_SESSION['user_id'];
        $_SESSION['user_id'] = $user['id'];
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>