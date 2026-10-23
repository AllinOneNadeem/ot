<?php
// ============================================================
// GET API PROVIDERS ACTION
// ============================================================
try {
    $stmt = $pdo->query("SELECT * FROM ApiProviders ORDER BY id DESC");
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'providers' => $providers]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>