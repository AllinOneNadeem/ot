<?php
// ============================================================
// ADD SERVICE ACTION
// ============================================================
$code = strtoupper(trim($_POST['code'] ?? ''));
$name = trim($_POST['name'] ?? '');
$price = $_POST['price'] ?? 0;
$server_id = $_POST['server_id'] ?? null;
$logo = trim($_POST['logo'] ?? '');
$api_id = trim($_POST['api_service_id'] ?? '');

if (empty($code) || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Code and name required']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO Services (code, name, price, server_id, logo, api_service_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$code, $name, $price, $server_id, $logo, $api_id]);
    echo json_encode(['success' => true, 'message' => 'Service added successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>