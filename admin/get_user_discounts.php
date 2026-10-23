<?php
header('Content-Type: application/json');
// include '../config.php'; // Apni DB connection file yahan include karein

$userId = $_GET['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['success' => false, 'discounts' => []]);
    exit;
}

try {
    // ServiceDiscounts table se user ke saare discounts nikalna
    $stmt = $pdo->prepare("SELECT * FROM ServiceDiscounts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'discounts' => $discounts]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'discounts' => [], 'error' => $e->getMessage()]);
}
?>