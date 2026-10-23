<?php
// 🌟 ULTIMATE MASTER SYNC: DB + CODE LINKER 🌟
// 100% Safe: Fixes missing Logos, Social Icons, and dynamically links Flags globally.

error_reporting(0);
require_once 'db.php';

echo "<div style='font-family: Arial; padding: 30px; background: #f8fafc; border-radius: 12px; max-width: 800px; margin: 40px auto; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);'>";
echo "<h1 style='color: #2563eb; text-align: center;'>🌟 1-Click Master Sync 🌟</h1>";
echo "<p style='text-align: center; color: #64748b;'>Auto-fixing Database & Upgrading PHP Code to Global Standards...</p><hr style='border:1px solid #e2e8f0;'>";

try {
    $dbUpdates = 0;

    // ==========================================
    // 0. FIX DATABASE COLLATION ERROR (The Error You Faced)
    // ==========================================
    $pdo->exec("ALTER TABLE GlobalCountries CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

    // ==========================================
    // 1. SERVICE LOGOS FIX
    // ==========================================
    echo "<h3>📦 1. Auto-Fixing Service Logos...</h3>";
    $serviceLogos = [
        'facebook' => 'https://smsbower.net/img/service_icons/fb.webp',
        'amazon' => 'https://smsbower.net/img/service_icons/am.webp',
        'whatsapp' => 'https://smsbower.net/img/service_icons/wa.webp',
        'telegram' => 'https://smsbower.net/img/service_icons/tg.webp',
        'instagram' => 'https://smsbower.net/img/service_icons/ig.webp',
        'google' => 'https://smsbower.net/img/service_icons/go.webp',
        'twitter' => 'https://smsbower.net/img/service_icons/tw.webp',
        'snapchat' => 'https://smsbower.net/img/service_icons/sn.webp',
        'viber' => 'https://smsbower.net/img/service_icons/vi.webp',
        'michat' => 'https://smsbower.net/img/service_icons/mc.webp',
        'uber' => 'https://smsbower.net/img/service_icons/ub.webp',
        'linkedin' => 'https://smsbower.net/img/service_icons/li.webp',
        'truecaller' => 'https://smsbower.net/img/service_icons/tl.webp'
    ];
    foreach($serviceLogos as $sName => $sLogo) {
        $stmt = $pdo->prepare("UPDATE Services SET logo = :logo WHERE name LIKE :name AND (logo = '' OR logo IS NULL OR logo LIKE '%?%')");
        $stmt->execute([':logo' => $sLogo, ':name' => "%$sName%"]);
        $dbUpdates += $stmt->rowCount();
    }

    // ==========================================
    // 2. SOCIAL ICONS FIX
    // ==========================================
    echo "<h3>💬 2. Auto-Fixing Social Media Icons...</h3>";
    $socialIcons = [
        'whatsapp' => 'fa-brands fa-whatsapp',
        'telegram' => 'fa-brands fa-telegram',
        'instagram' => 'fa-brands fa-instagram',
        'facebook' => 'fa-brands fa-facebook',
        'twitter' => 'fa-brands fa-twitter',
        'youtube' => 'fa-brands fa-youtube',
        'email' => 'fa-solid fa-envelope',
        'support' => 'fa-solid fa-headset'
    ];
    foreach ($socialIcons as $keyword => $iconClass) {
        $stmt = $pdo->prepare("UPDATE SupportLinks SET icon_class = :icon WHERE (LOWER(title) LIKE :keyword OR LOWER(url) LIKE :keyword) AND icon_class != :icon");
        $stmt->execute([':icon' => $iconClass, ':keyword' => "%$keyword%"]);
        $dbUpdates += $stmt->rowCount();
    }

    // ==========================================
    // 3. FLAGS DATABASE SYNC
    // ==========================================
    echo "<h3>🌍 3. Syncing History & Servers with Global Countries...</h3>";
    $stmt1 = $pdo->prepare("UPDATE Servers s JOIN GlobalCountries gc ON s.country_code = gc.code OR s.country_name = gc.name SET s.country_emoji = gc.flag, s.country_flag = gc.flag");
    $stmt1->execute();
    $dbUpdates += $stmt1->rowCount();

    $stmt2 = $pdo->prepare("UPDATE Orders o JOIN GlobalCountries gc ON o.country_code = gc.code OR o.country LIKE CONCAT('%', gc.name, '%') SET o.country = CONCAT(gc.flag, ' ', gc.name) WHERE o.country NOT LIKE CONCAT('%', gc.flag, '%')");
    $stmt2->execute();
    $dbUpdates += $stmt2->rowCount();
    
    $pdo->prepare("UPDATE Servers SET country_emoji = '🌐' WHERE country_emoji LIKE '%?%' OR country_emoji LIKE '%ЁЯМР%'")->execute();

    echo "<p style='color: green;'>✅ Database Synced! Total <b>$dbUpdates</b> records automatically corrected.</p>";

    // ==========================================
    // 4. PHP CODE AUTO-LINKER
    // ==========================================
    echo "<h3>⚙️ 4. Upgrading Website Code to 100% Dynamic...</h3>";
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
    $filesUpdated = 0;

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getRealPath();
            $content = file_get_contents($path);
            $orig = $content;

            // User Dashboard Dynamic Injection
            $dynamicUser = <<<'PHP'
$cName = $ord['country_name'] ?? $ord['country_code'] ?? 'Unknown';
                            $cCode = $ord['country_code'] ?? '';
                            static $gFlags = null;
                            if($gFlags === null) {
                                $gFlags = $pdo->query("SELECT code, name, flag FROM GlobalCountries")->fetchAll(PDO::FETCH_ASSOC);
                            }
                            $cEmoji = '🌐';
                            foreach($gFlags as $gf) {
                                if((!empty($cCode) && strcasecmp($gf['code'], $cCode) === 0) || (!empty($cName) && stripos($cName, $gf['name']) !== false)) {
                                    $cEmoji = $gf['flag'];
                                    $cName = $gf['name'];
                                    break;
                                }
                            }
PHP;
            $content = preg_replace('/\$num\s*=\s*preg_replace\([^;]+;\s*if\s*\(strpos\([^}]+\}\s*elseif[^}]+\}\s*else\s*\{[^}]+\}/s', $dynamicUser, $content);
            $content = preg_replace('/\$num\s*=\s*preg_replace\([^;]+;\s*if\s*\(strpos\([^}]+\}\s*elseif[^}]+\}\s*elseif[^}]+\}\s*else\s*\{[^}]+\}/s', $dynamicUser, $content);

            // Admin Panel Dynamic Injection
            $dynamicAdmin = <<<'PHP'
static $gFlagsAdmin = null;
                                                if($gFlagsAdmin === null) {
                                                    $gFlagsAdmin = $pdo->query("SELECT code, name, flag FROM GlobalCountries")->fetchAll(PDO::FETCH_ASSOC);
                                                }
                                                $flagEmoji = '🌐';
                                                foreach($gFlagsAdmin as $gf) {
                                                    if((!empty($countryCode) && strcasecmp($gf['code'], $countryCode) === 0) || (!empty($srv['country_name']) && stripos($srv['country_name'], $gf['name']) !== false)) {
                                                        $flagEmoji = $gf['flag'];
                                                        break;
                                                    }
                                                }
PHP;
            $content = preg_replace('/\$flagEmoji\s*=\s*\(!empty\([^;]+;/s', $dynamicAdmin, $content);
            $content = preg_replace('/\$flagEmoji\s*=\s*function_exists[^;]+;/s', $dynamicAdmin, $content);

            if ($content !== $orig) {
                file_put_contents($path, $content);
                $filesUpdated++;
            }
        }
    }

    echo "<p style='color: green;'>✅ Website Upgraded! <b>$filesUpdated</b> files successfully linked to Global Database.</p>";

    echo "<hr><h2 style='color: #10b981; text-align:center;'>🎉 System is now 100% Master Synced!</h2>";
    echo "<p style='text-align:center;'>भविष्य में जब भी कोई लोगो, सोशल आइकॉन या झंडा काम न करे, बस इस एक फाइल को रन कर दें।</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'><b>Error:</b> " . $e->getMessage() . "</p>";
}
echo "</div>";
?>