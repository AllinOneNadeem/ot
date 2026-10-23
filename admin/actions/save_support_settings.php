<?php
// ============================================================
// SAVE SUPPORT SETTINGS ACTION
// ============================================================
$support_email = trim($_POST['support_email'] ?? '');
$support_link = trim($_POST['support_link'] ?? '');
$support_bot = trim($_POST['support_bot'] ?? '');
$support_channel = trim($_POST['support_channel'] ?? '');

try {
    $stmt = $pdo->prepare("UPDATE SiteSettings SET support_link = ?, support_bot = ?, support_channel = ? WHERE id = 1");
    $stmt->execute([$support_link, $support_bot, $support_channel]);
    echo json_encode(['success' => true, 'message' => 'Support settings saved successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>