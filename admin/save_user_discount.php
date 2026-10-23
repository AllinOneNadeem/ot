<?php
header('Content-Type: application/json');
// include '../config.php'; // Apni DB connection file yahan include karein

$userId = $_POST['user_id'] ?? 0;
$serviceId = $_POST['service_id'] ?? 'all';
$discountType = $_POST['discount_type'] ?? 'percentage';
$discountValue = $_POST['discount_value'] ?? 0;

if (!$userId || !$discountValue) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
    exit;
}

try {
    // Check karein ki is user aur service ke liye pehle se discount hai ya nahi (agar hai toh update, nahi toh insert)
    $check = $pdo->prepare("SELECT id FROM ServiceDiscounts WHERE user_id = ? AND service_id = ?");
    $check->execute([$userId, $serviceId]);
    
    if ($check->rowCount() > 0) {
        // Update existing
        $update = $pdo->prepare("UPDATE ServiceDiscounts SET discount_type = ?, discount_value = ? WHERE user_id = ? AND service_id = ?");
        $update->execute([$discountType, $discountValue, $userId, $serviceId]);
    } else {
        // Insert new
        $insert = $pdo->prepare("INSERT INTO ServiceDiscounts (user_id, service_id, discount_type, discount_value) VALUES (?, ?, ?, ?)");
        $insert->execute([$userId, $serviceId, $discountType, $discountValue]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>