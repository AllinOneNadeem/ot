<?php
// ============================================================
// UPDATE CURRENCY RATE ACTION
// ============================================================
$rate = $_POST['usd_to_inr'] ?? 0;

if ($rate <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid rate']);
    exit();
}

try {
    $pdo->query("CREATE TABLE IF NOT EXISTS `CurrencySettings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `usd_to_inr` decimal(10,2) DEFAULT 97.00,
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("INSERT INTO CurrencySettings (id, usd_to_inr) VALUES (1, ?) ON DUPLICATE KEY UPDATE usd_to_inr = ?");
    $stmt->execute([$rate, $rate]);
    echo json_encode(['success' => true, 'message' => 'Currency rate updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>