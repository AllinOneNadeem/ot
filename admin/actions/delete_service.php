<?php
// ============================================================
// DELETE SERVICE ACTION
// ============================================================
$id = $_POST['service_id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM Services WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Service deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>