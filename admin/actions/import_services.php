<?php
// ============================================================
// IMPORT SERVICES FROM API - FIXED
// ============================================================
error_reporting(0);

$provider_id = $_POST['provider_id'] ?? 0;

if (!$provider_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid provider ID']);
    exit();
}

try {
    // Create ImportLogs table if not exists
    $pdo->query("CREATE TABLE IF NOT EXISTS `ImportLogs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `provider_id` int(11) NOT NULL,
        `total_servers` int(11) DEFAULT 0,
        `total_services` int(11) DEFAULT 0,
        `status` enum('success','partial','failed') DEFAULT 'success',
        `message` text,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("SELECT * FROM ApiProviders WHERE id = ? AND status = 1");
    $stmt->execute([$provider_id]);
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$provider) {
        echo json_encode(['success' => false, 'message' => 'Provider not found or inactive']);
        exit();
    }

    $api_key = $provider['api_key'];
    $provider_type = $provider['provider_type'];

    // Debug log
    error_log("Importing services from provider: " . $provider_type);

    $countries = fetchCountriesFromAPI($provider_type, $api_key);
    
    if (empty($countries) || !is_array($countries)) {
        echo json_encode(['success' => false, 'message' => 'No countries found from API. Please check API key.']);
        exit();
    }

    $totalServers = 0;
    $totalServices = 0;
    $importedServers = [];
    $errors = [];
    $countryLimit = 0;

    foreach ($countries as $country) {
        if ($countryLimit >= 5) break;
        
        $countryCode = $country['id'] ?? '0';
        $countryName = $country['country'] ?? 'Unknown';
        $countryEmoji = getCountryEmoji($countryName);
        
        // Debug log
        error_log("Fetching services for country: " . $countryName . " (" . $countryCode . ")");
        
        $services = fetchServicesFromAPI($provider_type, $api_key, $countryCode, $pdo);
        
        if (empty($services) || !isset($services['list']) || empty($services['list'])) {
            $errors[] = "No services found for $countryName";
            $countryLimit++;
            continue;
        }

        // Check if server already exists
        $stmt = $pdo->prepare("SELECT id FROM Servers WHERE name = ? AND provider_type = ?");
        $serverName = $countryName . ' - ' . $provider_type;
        $stmt->execute([$serverName, $provider_type]);
        $existingServer = $stmt->fetch(PDO::FETCH_ASSOC);

        $serverData = [
            'name' => $serverName,
            'country_name' => $countryName,
            'country_code' => $countryCode,
            'country_emoji' => $countryEmoji,
            'provider_type' => $provider_type,
            'api_key' => $api_key,
            'status' => 1,
            'base_url' => getBaseUrlForProvider($provider_type),
            'auto_cancel' => 5,
            'retryCount' => 1
        ];

        if ($existingServer) {
            $serverId = $existingServer['id'];
            $updateFields = [];
            $updateValues = [];
            foreach ($serverData as $key => $value) {
                $updateFields[] = "$key = ?";
                $updateValues[] = $value;
            }
            $updateValues[] = $serverId;
            $sql = "UPDATE Servers SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateValues);
        } else {
            $fields = array_keys($serverData);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $sql = "INSERT INTO Servers (" . implode(', ', $fields) . ") VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($serverData));
            $serverId = $pdo->lastInsertId();
            $totalServers++;
        }

        $importedServers[] = $countryName;

        $serviceCount = 0;
        foreach ($services['list'] as $service) {
            $serviceCode = $service['code'] ?? '';
            $serviceName = $service['name'] ?? $serviceCode;
            $price = $service['price'] ?? 0;
            $logo = $service['logo'] ?? '';

            if (empty($serviceCode)) continue;

            $stmt = $pdo->prepare("SELECT id FROM Services WHERE code = ? AND server_id = ?");
            $stmt->execute([strtoupper($serviceCode), $serverId]);
            $existingService = $stmt->fetch(PDO::FETCH_ASSOC);

            $serviceData = [
                'code' => strtoupper($serviceCode),
                'name' => $serviceName,
                'logo' => $logo,
                'price' => $price,
                'server_id' => $serverId,
                'country_id' => $countryCode,
                'popular' => 0,
                'cutback' => 0,
                'cancel_disable' => 2
            ];

            if ($existingService) {
                $updateFields = [];
                $updateValues = [];
                foreach ($serviceData as $key => $value) {
                    $updateFields[] = "$key = ?";
                    $updateValues[] = $value;
                }
                $updateValues[] = $existingService['id'];
                $sql = "UPDATE Services SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($updateValues);
            } else {
                $fields = array_keys($serviceData);
                $placeholders = implode(', ', array_fill(0, count($fields), '?'));
                $sql = "INSERT INTO Services (" . implode(', ', $fields) . ") VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($serviceData));
                $serviceCount++;
                $totalServices++;
            }
        }

        $countryLimit++;
    }

    $status = empty($errors) ? 'success' : 'partial';
    $message = "Imported $totalServers servers and $totalServices services";
    if (!empty($errors)) {
        $message .= ". Issues: " . implode(', ', $errors);
    }

    // Save import log
    $stmt = $pdo->prepare("INSERT INTO ImportLogs (provider_id, total_servers, total_services, status, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$provider_id, $totalServers, $totalServices, $status, $message]);

    error_log("Import completed: " . $message);

    echo json_encode([
        'success' => true,
        'message' => $message,
        'total_servers' => $totalServers,
        'total_services' => $totalServices,
        'imported_servers' => $importedServers,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    error_log("import_services error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Import failed: ' . $e->getMessage()
    ]);
}
?>