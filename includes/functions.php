<?php
// ============================================================
// ALL HELPER FUNCTIONS - SHARED ACROSS PROJECT
// ============================================================
// This file contains all common functions used in both
// admin and user panels, and API calls.
// ============================================================

// ============================================================
// DATABASE & CURRENCY FUNCTIONS
// ============================================================

/**
 * Get USD to INR conversion rate from database
 * @param object $pdo - PDO database connection
 * @return float - USD to INR rate
 */
function getUsdToInr($pdo) {
    try {
        // Create table if not exists
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
        return 97.00; // Default fallback
    }
}

/**
 * Update USD to INR rate
 * @param object $pdo - PDO database connection
 * @param float $rate - New USD to INR rate
 * @return bool - Success status
 */
function updateUsdToInr($pdo, $rate) {
    try {
        $pdo->query("CREATE TABLE IF NOT EXISTS `CurrencySettings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `usd_to_inr` decimal(10,2) DEFAULT 97.00,
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $stmt = $pdo->prepare("INSERT INTO CurrencySettings (id, usd_to_inr) VALUES (1, ?) ON DUPLICATE KEY UPDATE usd_to_inr = ?");
        return $stmt->execute([$rate, $rate]);
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================
// COUNTRY & EMOJI FUNCTIONS
// ============================================================

/**
 * Get emoji for a country name
 * @param string $countryName - Country name
 * @return string - Emoji or default globe
 */
function getCountryEmoji($countryName) {
    $emojis = [
        'India' => '🇮🇳',
        'USA' => '🇺🇸',
        'United States' => '🇺🇸',
        'UK' => '🇬🇧',
        'United Kingdom' => '🇬🇧',
        'Russia' => '🇷🇺',
        'Ukraine' => '🇺🇦',
        'Kazakhstan' => '🇰🇿',
        'Singapore' => '🇸🇬',
        'Cambodia' => '🇰🇭',
        'Nepal' => '🇳🇵',
        'Bangladesh' => '🇧🇩',
        'Pakistan' => '🇵🇰',
        'Sri Lanka' => '🇱🇰',
        'Afghanistan' => '🇦🇫',
        'Bhutan' => '🇧🇹',
        'Suriname' => '🇸🇷',
        'Uzbekistan' => '🇺🇿',
        'Armenia' => '🇦🇲',
        'Canada' => '🇨🇦',
        'Australia' => '🇦🇺',
        'Germany' => '🇩🇪',
        'France' => '🇫🇷',
        'Spain' => '🇪🇸',
        'Italy' => '🇮🇹',
        'Brazil' => '🇧🇷',
        'Mexico' => '🇲🇽',
        'Japan' => '🇯🇵',
        'China' => '🇨🇳',
        'South Korea' => '🇰🇷',
        'Indonesia' => '🇮🇩',
        'Vietnam' => '🇻🇳',
        'Thailand' => '🇹🇭',
        'Philippines' => '🇵🇭',
        'Malaysia' => '🇲🇾',
        'Egypt' => '🇪🇬',
        'Nigeria' => '🇳🇬',
        'Kenya' => '🇰🇪',
        'South Africa' => '🇿🇦'
    ];
    return $emojis[$countryName] ?? '🌍';
}

/**
 * Get country code from country name
 * @param string $countryName - Country name
 * @return string - Country code
 */
function getCountryCode($countryName) {
    $codes = [
        'India' => 'IN',
        'USA' => 'US',
        'United States' => 'US',
        'UK' => 'GB',
        'United Kingdom' => 'GB',
        'Russia' => 'RU',
        'Ukraine' => 'UA',
        'Kazakhstan' => 'KZ',
        'Singapore' => 'SG',
        'Cambodia' => 'KH',
        'Nepal' => 'NP',
        'Bangladesh' => 'BD',
        'Pakistan' => 'PK',
        'Sri Lanka' => 'LK',
        'Canada' => 'CA',
        'Australia' => 'AU',
        'Germany' => 'DE',
        'France' => 'FR',
        'Spain' => 'ES',
        'Italy' => 'IT',
        'Brazil' => 'BR',
        'Mexico' => 'MX',
        'Japan' => 'JP',
        'China' => 'CN',
        'South Korea' => 'KR',
        'Indonesia' => 'ID',
        'Vietnam' => 'VN',
        'Thailand' => 'TH',
        'Philippines' => 'PH',
        'Malaysia' => 'MY',
        'Egypt' => 'EG',
        'Nigeria' => 'NG',
        'South Africa' => 'ZA'
    ];
    return $codes[$countryName] ?? 'XX';
}

// ============================================================
// PROVIDER & API FUNCTIONS
// ============================================================

/**
 * Get base URL for a provider type
 * @param string $providerType - Provider type (SmsBower, 5sim, etc.)
 * @return string - Base URL
 */
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

/**
 * Get API URL for countries list
 * @param string $providerType - Provider type
 * @param string $apiKey - API key
 * @return string - Full URL
 */
function getCountriesApiUrl($providerType, $apiKey) {
    $urls = [
        'SmsBower' => "https://smsbower.online/stubs/handler_api.php?api_key=$apiKey&action=getCountries",
        '5sim' => "https://5sim.net/v1/guest/countries",
        'IndianOtp' => "https://saddu.site/stubs/handler_api.php?api_key=$apiKey&action=getCountries",
        'SmsActivate' => "https://api.sms-activate.ae/stubs/handler_api.php?api_key=$apiKey&action=getCountries",
        'SmsHub' => "https://smshub.org/stubs/handler_api.php?api_key=$apiKey&action=getCountries",
        'Fastsms' => "https://fastsms.su/stubs/handler_api.php?api_key=$apiKey&action=getCountries",
        '368sms' => "http://368sms.com/api?act=app&apik=$apiKey"
    ];
    return $urls[$providerType] ?? $urls['SmsBower'];
}

// ============================================================
// FETCH COUNTRIES FROM API
// ============================================================

/**
 * Fetch countries from API provider
 * @param string $providerType - Provider type
 * @param string $apiKey - API key
 * @return array - List of countries
 */
function fetchCountriesFromAPI($providerType, $apiKey) {
    $url = getCountriesApiUrl($providerType, $apiKey);
    
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
            $hubCountries = [
                '0' => 'Russia',
                '1' => 'Ukraine',
                '2' => 'Kazakhstan',
                '22' => 'India',
                '187' => 'USA',
                '196' => 'Singapore'
            ];
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

// ============================================================
// FETCH SERVICES FROM API
// ============================================================

/**
 * Fetch services from API provider for a country
 * @param string $providerType - Provider type
 * @param string $apiKey - API key
 * @param string $countryCode - Country code
 * @param object $pdo - PDO database connection (optional)
 * @return array - List of services
 */
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
            'fb' => 'Facebook',
            'ig' => 'Instagram',
            'tg' => 'Telegram',
            'wa' => 'WhatsApp',
            'tw' => 'Twitter',
            'yt' => 'YouTube',
            'go' => 'Google',
            'am' => 'Amazon',
            'ub' => 'Uber',
            'sn' => 'Snapchat',
            'li' => 'LinkedIn',
            'tl' => 'Truecaller',
            'me' => 'LINE',
            'vi' => 'Viber',
            'mc' => 'MiChat'
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

// ============================================================
// USER & SESSION FUNCTIONS
// ============================================================

/**
 * Check if user is logged in
 * @return bool - Login status
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user data
 * @param object $pdo - PDO database connection
 * @return array|false - User data or false
 */
function getCurrentUser($pdo) {
    if (!isLoggedIn()) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, balance, role FROM Users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if current user is admin
 * @param object $pdo - PDO database connection
 * @return bool - Admin status
 */
function isAdmin($pdo) {
    $user = getCurrentUser($pdo);
    return $user && $user['role'] === 'admin';
}

// ============================================================
// ⭐⭐⭐ ORDER & TRANSACTION FUNCTIONS - SIRF EK BAAR ⭐⭐⭐
// ============================================================

/**
 * Create a new order
 * @param object $pdo - PDO database connection
 * @param int $userId - User ID
 * @param int $serviceId - Service ID
 * @param string $serviceName - Service name
 * @param string $code - Service code
 * @param string $number - Phone number
 * @param float $price - Price
 * @param string $apiOrderId - API order ID
 * @return int|false - Order ID or false
 */
function createOrder($pdo, $userId, $serviceId, $serviceName, $code, $number, $price, $apiOrderId = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Orders (user_id, service_id, service_name, code, number, price, status, api_order_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'WAITING', ?, NOW())
        ");
        $stmt->execute([$userId, $serviceId, $serviceName, $code, $number, $price, $apiOrderId]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Create a transaction record
 * @param object $pdo - PDO database connection
 * @param int $userId - User ID
 * @param float $amount - Amount
 * @param string $type - Type (credit/debit)
 * @param string $message - Message
 * @param float $balanceBefore - Balance before
 * @param float $balanceAfter - Balance after
 * @param string $paymentMode - Payment mode
 * @param string $referenceId - Reference ID
 * @return bool - Success status
 */
function createTransaction($pdo, $userId, $amount, $type, $message, $balanceBefore, $balanceAfter, $paymentMode = null, $referenceId = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Transactions (user_id, amount, type, message, balance_before, balance_after, payment_mode, status, reference_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)
        ");
        return $stmt->execute([$userId, $amount, $type, $message, $balanceBefore, $balanceAfter, $paymentMode, $referenceId]);
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================
// UTILITY FUNCTIONS
// ============================================================

/**
 * Format currency
 * @param float $amount - Amount
 * @param string $currency - Currency symbol
 * @return string - Formatted currency
 */
function formatCurrency($amount, $currency = '₹') {
    return $currency . number_format((float)$amount, 2);
}

/**
 * Sanitize input
 * @param string $input - Input string
 * @return string - Sanitized string
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate random string
 * @param int $length - Length of string
 * @return string - Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Get user IP address
 * @return string - IP address
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Log error message
 * @param string $message - Error message
 * @param string $file - File name
 */
function logError($message, $file = 'error.log') {
    $logPath = __DIR__ . '/../logs/' . $file;
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
    file_put_contents($logPath, $logMessage, FILE_APPEND);
}

// ============================================================
// END OF FILE
// ============================================================
?>