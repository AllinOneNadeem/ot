<?php
// ============================================================
// DELETE USER ACTION
// ============================================================
$id = $_POST['user_id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

try {
    // Admin user delete nahi kar sakte
    $stmt = $pdo->prepare("DELETE FROM Users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found or cannot delete admin']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>