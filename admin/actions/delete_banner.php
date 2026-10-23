<?php
// ============================================================
// DELETE BANNER ACTION
// ============================================================
$id = $_POST['banner_id'] ?? 0;

try {
    $pdo->prepare("DELETE FROM Banners WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Banner deleted']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>