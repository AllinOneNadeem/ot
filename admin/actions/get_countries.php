<?php
// ============================================================
// GET COUNTRIES FROM API PROVIDER - FIXED
// ============================================================
$provider_id = $_POST['provider_id'] ?? 0;

if (!$provider_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid provider ID']);
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
    
    // Debug log
    error_log("Fetching countries for provider: " . $provider['provider_type'] . " with API key: " . substr($provider['api_key'], 0, 10) . "...");
    
    $countries = fetchCountriesFromAPI($provider['provider_type'], $provider['api_key']);
    
    // Debug log
    error_log("Countries found: " . count($countries));
    
    if (empty($countries)) {
        echo json_encode(['success' => false, 'message' => 'No countries found. Please check API key.']);
        exit();
    }
    
    foreach ($countries as &$country) {
        $country['emoji'] = getCountryEmoji($country['country']);
    }
    
    echo json_encode(['success' => true, 'countries' => $countries]);
} catch (Exception $e) {
    error_log("get_countries error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>