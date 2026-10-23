<?php
// ============================================================
// GET SERVER DETAILS ACTION
// ============================================================
$id = $_POST['server_id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid server ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM Servers WHERE id = ?");
    $stmt->execute([$id]);
    $server = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($server) {
        echo json_encode(['success' => true, 'server' => $server]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Server not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>