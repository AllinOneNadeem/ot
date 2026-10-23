<?php
// ============================================================
// SAVE SITE SETTINGS ACTION
// ============================================================
$site_name = trim($_POST['site_name'] ?? 'OTPX');
$currency = trim($_POST['currency_symbol'] ?? '₹');
$support_link = trim($_POST['support_link'] ?? '');
$site_logo = trim($_POST['site_logo'] ?? '');

try {
    $stmt = $pdo->prepare("UPDATE SiteSettings SET site_name = ?, currency_symbol = ?, support_link = ?, site_logo = ? WHERE id = 1");
    $stmt->execute([$site_name, $currency, $support_link, $site_logo]);
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>