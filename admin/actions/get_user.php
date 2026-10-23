<?php
// ============================================================
// GET USER DETAILS ACTION
// ============================================================
$id = $_POST['user_id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, name, email, role, balance, banned FROM Users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>