<?php
// ============================================================
// GET CURRENCY RATE ACTION
// ============================================================
try {
    $stmt = $pdo->query("SELECT usd_to_inr FROM CurrencySettings LIMIT 1");
    $rate = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'rate' => $rate['usd_to_inr'] ?? 97.00]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>