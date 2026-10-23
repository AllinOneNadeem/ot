<?php
// ============================================================
// GET PAGE ACTION
// ============================================================
$id = $_POST['page_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM Pages WHERE id = ?");
    $stmt->execute([$id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'page' => $page]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>