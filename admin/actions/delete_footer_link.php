<?php
// ============================================================
// DELETE FOOTER LINK ACTION
// ============================================================
$id = $_POST['link_id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid link ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM FooterLinks WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Footer link deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Link not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>