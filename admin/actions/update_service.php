<?php
// ============================================================
// UPDATE SERVICE ACTION
// ============================================================
$id = $_POST['service_id'] ?? 0;
$code = strtoupper(trim($_POST['code'] ?? ''));
$name = trim($_POST['name'] ?? '');
$price = $_POST['price'] ?? 0;
$server_id = $_POST['server_id'] ?? null;
$logo = trim($_POST['logo'] ?? '');
$popular = $_POST['popular'] ?? 0;
$cutback = $_POST['cutback'] ?? 0;
$cancel_disable = $_POST['cancel_disable'] ?? 2;

if (!$id || empty($code) || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Code and name required']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE Services SET 
        code = ?, name = ?, price = ?, server_id = ?, logo = ?, 
        popular = ?, cutback = ?, cancel_disable = ? 
        WHERE id = ?");
    $stmt->execute([$code, $name, $price, $server_id, $logo, $popular, $cutback, $cancel_disable, $id]);
    echo json_encode(['success' => true, 'message' => 'Service updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>