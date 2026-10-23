<?php
// ============================================================
// GET SERVICES BY COUNTRY ACTION
// ============================================================
$provider_id = $_POST['provider_id'] ?? 0;
$country_code = $_POST['country_code'] ?? '';
$margin_type = $_POST['margin_type'] ?? 'fixed';
$margin_value = $_POST['margin_value'] ?? 0;

if (!$provider_id || empty($country_code)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM ApiProviders WHERE id = ? AND status = 1");
    $stmt->execute([$provider_id]);
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$provider) {
        echo json_encode(['success' => false, 'message' => 'Provider not found']);
        exit();
    }
    
    $services = fetchServicesFromAPI($provider['provider_type'], $provider['api_key'], $country_code, $pdo);
    
    if (empty($services) || empty($services['list'])) {
        echo json_encode(['success' => false, 'message' => 'No services found for this country']);
        exit();
    }
    
    foreach ($services['list'] as &$service) {
        $priceInInr = $service['price_inr'] ?? ($service['price'] * 97.00);
        
        if ($margin_type === 'fixed') {
            $finalPrice = $priceInInr + $margin_value;
        } else {
            $finalPrice = $priceInInr + ($priceInInr * $margin_value / 100);
        }
        
        $service['price_with_margin'] = round($finalPrice, 2);
        $service['original_price'] = round($priceInInr, 2);
        $service['margin_applied'] = round($finalPrice - $priceInInr, 2);
    }
    
    echo json_encode([
        'success' => true, 
        'services' => $services['list'],
        'total' => count($services['list'])
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>