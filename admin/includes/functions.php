<?php
// ============================================================
// ALL HELPER FUNCTIONS - SINGLE FILE (WITH LOCAL COUNTRY FLAG DB STORAGE)
// ============================================================

// ---------- 1. INITIALIZE & STORE COUNTRY FLAGS IN DATABASE ----------
function initCountryFlagsTable($pdo) {
    try {
        // Create table for storing flags if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS `CountryFlags` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `country_code` VARCHAR(5) UNIQUE NOT NULL,
            `country_name` VARCHAR(100) NOT NULL,
            `flag_emoji` VARCHAR(10) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Insert default flags into database if table is empty
        $count = $pdo->query("SELECT COUNT(*) FROM CountryFlags")->fetchColumn();
        if ($count == 0) {
            $defaultCountries = [
                ['IN', 'India', '🇮🇳'],
                ['US', 'USA', '🇺🇸'],
                ['UK', 'UK', '🇬🇧'],
                ['RU', 'Russia', '🇷🇺'],
                ['UA', 'Ukraine', '🇺🇦'],
                ['KZ', 'Kazakhstan', '🇰🇿'],
                ['SG', 'Singapore', '🇸🇬'],
                ['KH', 'Cambodia', '🇰🇭'],
                ['NP', 'Nepal', '🇳🇵'],
                ['BD', 'Bangladesh', '🇧🇩'],
                ['PK', 'Pakistan', '🇵🇰'],
                ['LK', 'Sri Lanka', '🇱🇰'],
                ['CA', 'Canada', '🇨🇦'],
                ['AU', 'Australia', '🇦🇺'],
                ['DE', 'Germany', '🇩🇪'],
                ['FR', 'France', '🇫🇷'],
                ['CN', 'China', '🇨🇳'],
                ['JP', 'Japan', '🇯🇵'],
                ['BR', 'Brazil', '🇧🇷'],
                ['AE', 'UAE', '🇦🇪']
            ];

            $stmt = $pdo->prepare("INSERT IGNORE INTO CountryFlags (country_code, country_name, flag_emoji) VALUES (?, ?, ?)");
            foreach ($defaultCountries as $c) {
                $stmt->execute($c);
            }
        }
    } catch (Exception $e) {
        // Silent catch
    }
}

// ---------- 2. GET COUNTRY FLAG FROM DATABASE STORAGE ----------
function getStoredCountryFlag($pdo, $countryCode) {
    $countryCode = strtoupper(trim($countryCode));
    if (empty($countryCode)) {
        return '🌐';
    }

    // Ensure the table and data exist in database
    initCountryFlagsTable($pdo);

    try {
        $stmt = $pdo->prepare("SELECT flag_emoji FROM CountryFlags WHERE country_code = ? LIMIT 1");
        $stmt->execute([$countryCode]);
        $flag = $stmt->fetchColumn();

        if ($flag) {
            return $flag;
        } else {
            // Dynamic fallback if code is not in table yet
            return getCountryFlagEmoji($countryCode);
        }
    } catch (Exception $e) {
        return '🌐';
    }
}

// ---------- 3. GENERATE COUNTRY FLAG EMOJI FROM ISO CODE (FALLBACK) ----------
function getCountryFlagEmoji($countryCode) {
    $countryCode = strtoupper(trim($countryCode));
    if (empty($countryCode) || strlen($countryCode) !== 2) {
        return '🌐';
    }
    $flagOffset = 127397;
    $ascii1 = ord($countryCode[0]) + $flagOffset;
    $ascii2 = ord($countryCode[1]) + $flagOffset;
    return mb_chr($ascii1, 'UTF-8') . mb_chr($ascii2, 'UTF-8');
}

// ---------- GET USD TO INR RATE ----------
function getUsdToInr($pdo) {
    try {
        $pdo->query("CREATE TABLE IF NOT EXISTS `CurrencySettings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `usd_to_inr` decimal(10,2) DEFAULT 97.00,
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $stmt = $pdo->query("SELECT usd_to_inr FROM CurrencySettings LIMIT 1");
        $currency = $stmt->fetch(PDO::FETCH_ASSOC);
        return $currency ? (float)$currency['usd_to_inr'] : 97.00;
    } catch (Exception $e) {
        return 97.00;
    }
}

// ---------- GET BASE URL FOR PROVIDER ----------
function getBaseUrlForProvider($providerType) {
    $urls = [
        'SmsBower' => 'https://smsbower.online/stubs/handler_api.php',
        '5sim' => 'https://5sim.net/v1/user/buy/activation',
        'IndianOtp' => 'https://saddu.site/stubs/handler_api.php',
        'SmsActivate' => 'https://api.sms-activate.ae/stubs/handler_api.php',
        'SmsHub' => 'https://smshub.org/stubs/handler_api.php',
        'Fastsms' => 'https://fastsms.su/stubs/handler_api.php',
        '368sms' => 'http://368sms.com/api'
    ];
    return $urls[$providerType] ?? $urls['SmsBower'];
}

// ---------- FETCH COUNTRIES FROM API ----------
function fetchCountriesFromAPI($providerType, $apiKey) {
    $urls = [
        'SmsBower' => "https://smsbower.online/stubs/handler_api.php?api_key=$apiKey&action=getCountries",
        '5sim' => "https://5sim.net/v1/guest/countries",
        'IndianOtp' => "https://saddu.site/stubs/handler_api.php?api_key=$apiKey&action=getCountries",
        'SmsActivate' => "https://api.sms-activate.ae/stubs/handler_api.php?api_key=$apiKey&action=getCountries",
        'SmsHub' => "https://smshub.org/stubs/handler_api.php?api_key=$apiKey&action=getCountries",
        'Fastsms' => "https://fastsms.su/stubs/handler_api.php?api_key=$apiKey&action=getCountries",
        '368sms' => "http://368sms.com/api?act=app&apik=$apiKey"
    ];

    $url = $urls[$providerType] ?? $urls['SmsBower'];
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            return [];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return [];
        }

        $countries = [];
        
        if ($providerType === '5sim') {
            foreach ($data as $key => $value) {
                $countries[] = ['country' => $key, 'id' => $key];
            }
            return $countries;
        }

        if ($providerType === 'SmsBower' || $providerType === 'Fastsms') {
            foreach ($data as $key => $value) {
                $countries[] = ['country' => $value['eng'] ?? $value, 'id' => $key];
            }
            return $countries;
        }

        if ($providerType === 'SmsActivate') {
            foreach ($data as $key => $value) {
                $countries[] = ['country' => $value['eng'] ?? $value, 'id' => $key];
            }
            return $countries;
        }

        if ($providerType === 'SmsHub') {
            $hubCountries = ['0' => 'Russia', '1' => 'Ukraine', '2' => 'Kazakhstan', '22' => 'India', '187' => 'USA', '196' => 'Singapore'];
            foreach ($hubCountries as $key => $value) {
                $countries[] = ['country' => $value, 'id' => $key];
            }
            return $countries;
        }

        if ($providerType === '368sms') {
            return [['country' => 'Cambodia', 'id' => '368']];
        }

        if ($providerType === 'IndianOtp') {
            foreach ($data as $key => $value) {
                $countries[] = ['country' => $value['name'] ?? $value, 'id' => $key];
            }
            return $countries;
        }

        return [];

    } catch (Exception $e) {
        return [];
    }
}

// ---------- FETCH SERVICES FROM API ----------
function fetchServicesFromAPI($providerType, $apiKey, $countryCode, $pdo = null) {
    $usdToInr = 97.00;
    if ($pdo) {
        $usdToInr = getUsdToInr($pdo);
    }
    
    $urls = [
        'SmsBower' => "https://smsbower.online/stubs/handler_api.php?api_key=$apiKey&action=getPrices&country=$countryCode",
        'IndianOtp' => "https://saddu.site/stubs/handler_api.php?api_key=$apiKey&action=getPrices&country=$countryCode",
        'SmsActivate' => "https://api.sms-activate.ae/stubs/handler_api.php?api_key=$apiKey&action=getNumbersStatus&country=$countryCode",
        'SmsHub' => "https://smshub.org/stubs/handler_api.php?api_key=$apiKey&action=getNumbersStatus&country=$countryCode",
        'Fastsms' => "https://fastsms.su/stubs/handler_api.php?api_key=$apiKey&action=getPrices&country=$countryCode",
        '5sim' => "https://5sim.net/v1/guest/products/$countryCode"
    ];

    $url = $urls[$providerType] ?? $urls['SmsBower'];
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (empty($response) || $httpCode !== 200) {
            return ['list' => []];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['list' => []];
        }

        $services = ['list' => []];
        $serviceNames = [
            'fb' => 'Facebook', 'ig' => 'Instagram', 'tg' => 'Telegram',
            'wa' => 'WhatsApp', 'tw' => 'Twitter', 'yt' => 'YouTube',
            'go' => 'Google', 'am' => 'Amazon', 'ub' => 'Uber',
            'sn' => 'Snapchat', 'li' => 'LinkedIn', 'tl' => 'Truecaller',
            'me' => 'LINE', 'vi' => 'Viber', 'mc' => 'MiChat'
        ];

        // SmsBower / FastSms
        if ($providerType === 'SmsBower' || $providerType === 'Fastsms') {
            if (empty($data[$countryCode])) return ['list' => []];
            
            foreach ($data[$countryCode] as $key => $value) {
                if (isset($value['cost']) && $value['cost'] > 0) {
                    $originalPrice = (float)$value['cost'];
                    $inrPrice = $originalPrice * $usdToInr;
                    
                    $services['list'][] = [
                        'code' => $key,
                        'name' => $serviceNames[$key] ?? $value['name'] ?? strtoupper($key),
                        'price' => $originalPrice,
                        'price_inr' => round($inrPrice, 2),
                        'logo' => "https://smsbower.net/img/service_icons/$key.webp"
                    ];
                }
            }
            return $services;
        }

        // 5sim
        if ($providerType === '5sim') {
            if (empty($data)) return ['list' => []];
            foreach ($data as $key => $value) {
                if (isset($value['Price']) && $value['Price'] > 0) {
                    $inrPrice = $value['Price'] * $usdToInr;
                    $services['list'][] = [
                        'code' => strtolower($key),
                        'name' => $key,
                        'price' => $value['Price'],
                        'price_inr' => round($inrPrice, 2),
                        'logo' => "https://5sim.net/img/service/$key.png"
                    ];
                }
            }
            return $services;
        }

        // IndianOtp
        if ($providerType === 'IndianOtp') {
            if (empty($data[$countryCode])) return ['list' => []];
            foreach ($data[$countryCode] as $key => $value) {
                if (isset($value['cost']) && $value['cost'] > 0) {
                    $inrPrice = $value['cost'] * $usdToInr;
                    $services['list'][] = [
                        'code' => strtolower($key),
                        'name' => $value['name'] ?? $key,
                        'price' => $value['cost'],
                        'price_inr' => round($inrPrice, 2),
                        'logo' => "https://saddu.site/img/service/$key.png"
                    ];
                }
            }
            return $services;
        }

        // SmsActivate
        if ($providerType === 'SmsActivate') {
            if (empty($data)) return ['list' => []];
            foreach ($data as $key => $value) {
                if (is_array($value) && isset($value['cost']) && $value['cost'] > 0) {
                    $inrPrice = $value['cost'] * $usdToInr;
                    $services['list'][] = [
                        'code' => $key,
                        'name' => $serviceNames[$key] ?? $key,
                        'price' => $value['cost'],
                        'price_inr' => round($inrPrice, 2),
                        'logo' => "https://smsactivate.s3.eu-central-1.amazonaws.com/assets/ico/{$key}0.webp"
                    ];
                }
            }
            return $services;
        }

        // SmsHub
        if ($providerType === 'SmsHub') {
            if (empty($data)) return ['list' => []];
            foreach ($data as $key => $value) {
                $code = explode('_', $key)[0];
                if ($code && isset($value['cost']) && $value['cost'] > 0) {
                    $inrPrice = $value['cost'] * $usdToInr;
                    $services['list'][] = [
                        'code' => $code,
                        'name' => $serviceNames[$code] ?? $code,
                        'price' => $value['cost'],
                        'price_inr' => round($inrPrice, 2),
                        'logo' => "https://smshub.org/assets/ico/services/{$code}0.webp"
                    ];
                }
            }
            return $services;
        }

        return ['list' => []];
    } catch (Exception $e) {
        return ['list' => []];
    }
}
?>