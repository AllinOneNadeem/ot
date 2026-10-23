<?php
// ============================================================
// GET BANNER ACTION
// ============================================================
$id = $_POST['banner_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM Banners WHERE id = ?");
    $stmt->execute([$id]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'banner' => $banner]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>