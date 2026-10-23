<?php
// ============================================================
// DELETE PAGE ACTION
// ============================================================
$id = $_POST['page_id'] ?? 0;

try {
    $pdo->prepare("DELETE FROM Pages WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Page deleted']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>