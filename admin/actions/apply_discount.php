<?php
// ============================================================
// APPLY DISCOUNT ACTION
// ============================================================
$user_id = $_POST['user_id'] ?? 0;
$type = $_POST['type'] ?? 'fixed';
$value = $_POST['value'] ?? 0;
$apply_to = $_POST['apply_to'] ?? 'all';
$service_id = $_POST['service_id'] ?? null;

if (!$user_id || $value <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    $pdo->query("CREATE TABLE IF NOT EXISTS `UserDiscounts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `type` enum('fixed','percentage') NOT NULL DEFAULT 'fixed',
        `value` decimal(10,2) NOT NULL,
        `apply_to` enum('all','specific') DEFAULT 'all',
        `service_id` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("INSERT INTO UserDiscounts (user_id, type, value, apply_to, service_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $type, $value, $apply_to, $service_id]);
    
    echo json_encode(['success' => true, 'message' => 'Discount applied successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>