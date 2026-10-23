<?php
// ============================================================
// DELETE PROMO CODE ACTION
// ============================================================
$id = $_POST['promo_id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid promo ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM PromoCodes WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Promo code deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Promo code not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>