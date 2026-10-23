<?php
// ============================================================
// UPDATE ORDER STATUS ACTION
// ============================================================
$id = $_POST['order_id'] ?? 0;
$status = $_POST['status'] ?? '';

if (!$id || !in_array($status, ['RECEIVED', 'CANCELLED'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE Orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>