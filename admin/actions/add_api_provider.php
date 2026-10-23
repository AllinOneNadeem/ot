<?php
// ============================================================
// ADD API PROVIDER ACTION
// ============================================================
$name = trim($_POST['name'] ?? '');
$api_key = trim($_POST['api_key'] ?? '');
$provider_type = $_POST['provider_type'] ?? 'SmsBower';

if (empty($name) || empty($api_key)) {
    echo json_encode(['success' => false, 'message' => 'Name and API key required']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO ApiProviders (name, api_key, provider_type) VALUES (?, ?, ?)");
    $stmt->execute([$name, $api_key, $provider_type]);
    echo json_encode(['success' => true, 'message' => 'API Provider added successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>