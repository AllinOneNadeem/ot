<?php
// ============================================================
// SAVE FOOTER SETTINGS ACTION
// ============================================================
$footer_content = $_POST['footer_content'] ?? '';
$footer_copyright = trim($_POST['footer_copyright'] ?? '');

try {
    $stmt = $pdo->prepare("UPDATE SiteSettings SET footer = ?, footer_copyright = ? WHERE id = 1");
    $stmt->execute([$footer_content, $footer_copyright]);
    echo json_encode(['success' => true, 'message' => 'Footer settings saved successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>