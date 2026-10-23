<?php
// ============================================================
// TOGGLE BAN USER ACTION
// ============================================================
$id = $_POST['user_id'] ?? 0;
$status = $_POST['status'] ?? 1;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE Users SET banned = ? WHERE id = ? AND role != 'admin'");
    $stmt->execute([$status, $id]);
    
    if ($stmt->rowCount() > 0) {
        $message = $status ? 'User banned successfully' : 'User unbanned successfully';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found or cannot ban admin']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>