<?php
// ============================================================
// DELETE SERVER ACTION
// ============================================================
$id = $_POST['server_id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid server ID']);
    exit();
}

try {
    // Delete all services under this server first
    $pdo->prepare("DELETE FROM Services WHERE server_id = ?")->execute([$id]);
    // Then delete the server
    $stmt = $pdo->prepare("DELETE FROM Servers WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Server deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Server not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>