<?php
// ============================================================
// GET SERVICE DETAILS ACTION
// ============================================================
$id = $_POST['service_id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM Services WHERE id = ?");
    $stmt->execute([$id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($service) {
        echo json_encode(['success' => true, 'service' => $service]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>