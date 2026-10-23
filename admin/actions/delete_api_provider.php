<?php
// ============================================================
// DELETE API PROVIDER ACTION
// ============================================================
$id = $_POST['provider_id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid provider ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM ApiProviders WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Provider deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Provider not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>