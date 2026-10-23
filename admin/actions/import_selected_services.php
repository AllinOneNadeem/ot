<?php
// ============================================================
// IMPORT SELECTED SERVICES ACTION
// ============================================================
$provider_id = $_POST['provider_id'] ?? 0;
$country_code = $_POST['country_code'] ?? '';
$selected_services = isset($_POST['selected_services']) ? json_decode($_POST['selected_services'], true) : [];
$margin_type = $_POST['margin_type'] ?? 'fixed';
$margin_value = $_POST['margin_value'] ?? 0;

if (!$provider_id || empty($country_code) || empty($selected_services)) {
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

    $countryName = '';
    $allCountries = fetchCountriesFromAPI($provider['provider_type'], $provider['api_key']);
    foreach ($allCountries as $c) {
        if ($c['id'] == $country_code) {
            $countryName = $c['country'];
            break;
        }
    }

    // Auto create server
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(name, 7) AS UNSIGNED)) as max_num FROM Servers WHERE name LIKE 'Server %'");
    $max = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextNumber = ($max['max_num'] ?? 0) + 1;
    $serverName = 'Server ' . $nextNumber;
    $countryEmoji = getCountryEmoji($countryName);

    $stmt = $pdo->prepare("SELECT id FROM Servers WHERE country_code = ? AND provider_type = ?");
    $stmt->execute([$country_code, $provider['provider_type']]);
    $existingServer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingServer) {
        $serverId = $existingServer['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO Servers (name, country_name, country_code, country_emoji, provider_type, api_key, status, auto_cancel, retryCount) 
                               VALUES (?, ?, ?, ?, ?, ?, 1, 5, 1)");
        $stmt->execute([$serverName, $countryName, $country_code, $countryEmoji, $provider['provider_type'], $provider['api_key']]);
        $serverId = $pdo->lastInsertId();
    }

    $importedCount = 0;
    $usdToInr = getUsdToInr($pdo);

    foreach ($selected_services as $service) {
        $code = $service['code'];
        $name = $service['name'];
        $originalPrice = $service['price'];
        $priceInInr = $service['price_inr'] ?? ($originalPrice * $usdToInr);

        if ($margin_type === 'fixed') {
            $finalPrice = $priceInInr + $margin_value;
        } else {
            $finalPrice = $priceInInr + ($priceInInr * $margin_value / 100);
        }
        $finalPrice = round($finalPrice, 2);

        $logo = $service['logo'] ?? '';

        $stmt = $pdo->prepare("SELECT id FROM Services WHERE code = ? AND server_id = ?");
        $stmt->execute([strtoupper($code), $serverId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE Services SET name = ?, price = ?, logo = ?, original_price = ? WHERE id = ?");
            $stmt->execute([$name, $finalPrice, $logo, $priceInInr, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO Services (code, name, price, server_id, logo, original_price, country_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([strtoupper($code), $name, $finalPrice, $serverId, $logo, $priceInInr, $country_code]);
        }
        $importedCount++;
    }

    echo json_encode([
        'success' => true,
        'message' => "Imported $importedCount services to $serverName",
        'server_name' => $serverName,
        'imported' => $importedCount
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>