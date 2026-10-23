<?php
// ============================================================
// UPDATE SERVER ACTION
// ============================================================
$id = $_POST['server_id'] ?? 0;
$name = trim($_POST['name'] ?? '');
$code = trim($_POST['code'] ?? '');
$country_code = trim($_POST['country_code'] ?? '');
$country_emoji = trim($_POST['country_emoji'] ?? '');
$api_type = $_POST['api_type'] ?? 'text';
$hasHeaders = $_POST['hasHeaders'] ?? 0;
$headers = $_POST['headers'] ?? '';
$api_key = trim($_POST['api_key'] ?? '');
$base_url = trim($_POST['base_url'] ?? '');
$get_num_url = trim($_POST['get_num_url'] ?? '');
$get_msg_url = trim($_POST['get_msg_url'] ?? '');
$next_msg_url = trim($_POST['next_msg_url'] ?? '');
$cancel_num_url = trim($_POST['cancel_num_url'] ?? '');
$auto_cancel = $_POST['auto_cancel'] ?? 5;
$retryCount = $_POST['retryCount'] ?? 1;
$status = $_POST['status'] ?? 1;

if (!$id || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Server name required']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE Servers SET 
        name = ?, code = ?, country_code = ?, country_emoji = ?, api_type = ?, 
        hasHeaders = ?, headers = ?, api_key = ?, base_url = ?, 
        get_num_url = ?, get_msg_url = ?, next_msg_url = ?, cancel_num_url = ?, 
        auto_cancel = ?, retryCount = ?, status = ? 
        WHERE id = ?");
    $stmt->execute([
        $name, $code, $country_code, $country_emoji, $api_type,
        $hasHeaders, $headers, $api_key, $base_url,
        $get_num_url, $get_msg_url, $next_msg_url, $cancel_num_url,
        $auto_cancel, $retryCount, $status, $id
    ]);
    echo json_encode(['success' => true, 'message' => 'Server updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>