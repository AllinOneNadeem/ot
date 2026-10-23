<?php
session_start();
// अपना डेटाबेस कनेक्शन यहाँ इन्क्लूड करें (जैसे: include '../db.php';)
// मान लीजिये आपका डेटाबेस कनेक्शन $pdo है।
require_once '../db.php'; 

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Invalid Request'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // सबसे पहले SiteSettings की सही ID निकालें (ताकि WHERE id=1 वाला एरर ना आये)
    $settingId = 1;
    try {
        $stmt = $pdo->query("SELECT id FROM SiteSettings LIMIT 1");
        if ($row = $stmt->fetch()) {
            $settingId = $row['id'];
        }
    } catch(Exception $e) {}

    // 1. Save SEO Settings
    if ($action == 'save_seo') {
        $stmt = $pdo->prepare("UPDATE SiteSettings SET site_name=?, site_title=?, seo_description=?, seo_keywords=?, seo_author=? WHERE id=?");
        if($stmt->execute([$_POST['site_name'], $_POST['site_title'], $_POST['seo_description'], $_POST['seo_keywords'], $_POST['seo_author'], $settingId])){
            $response = ['success' => true, 'message' => 'SEO Settings Saved successfully!'];
        }
    }

    // 2. Save Logo Settings (URL + File Upload Logic)
    elseif ($action == 'save_logo') {
        $logoUrl = $_POST['site_logo_url'] ?? '';
        
        // अगर फाइल अपलोड की गई है, तो उसका URL बनायें
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] == 0) {
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($_FILES['logo_file']['name']));
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $targetPath)) {
                $logoUrl = '/uploads/' . $filename; 
            }
        }

        $stmt = $pdo->prepare("UPDATE SiteSettings SET site_logo=? WHERE id=?");
        if($stmt->execute([$logoUrl, $settingId])){
            $response = ['success' => true, 'message' => 'Logo Saved successfully!'];
        }
    }

    // 3. Delete Logo
    elseif ($action == 'delete_logo') {
        $stmt = $pdo->prepare("UPDATE SiteSettings SET site_logo='' WHERE id=?");
        if($stmt->execute([$settingId])){
            $response = ['success' => true, 'message' => 'Logo Deleted'];
        }
    }

    // 4. Save Auth & API Settings
    elseif ($action == 'save_auth') {
        $stmt = $pdo->prepare("UPDATE SiteSettings SET multi_device_login=?, google_login_status=?, google_client_id=?, telegram_login_status=?, telegram_bot_token=?, telegram_bot_username=? WHERE id=?");
        if($stmt->execute([
            $_POST['multi_device_login'], $_POST['google_login_status'], $_POST['google_client_id'], 
            $_POST['telegram_login_status'], $_POST['telegram_bot_token'], $_POST['telegram_bot_username'], 
            $settingId
        ])){
            $response = ['success' => true, 'message' => 'Auth Settings Saved successfully!'];
        }
    }

    // 5. Save Currency Settings
    elseif ($action == 'save_currency') {
        $pdo->prepare("UPDATE SiteSettings SET currency_symbol=? WHERE id=?")->execute([$_POST['currency_symbol'], $settingId]);
        
        $currId = $pdo->query("SELECT id FROM CurrencySettings LIMIT 1")->fetchColumn() ?: 1;
        $pdo->prepare("UPDATE CurrencySettings SET usd_to_inr=? WHERE id=?")->execute([$_POST['usd_to_inr'], $currId]);
        
        $response = ['success' => true, 'message' => 'Currency Saved successfully!'];
    }

    // 6. Upload Server File
    elseif ($action == 'upload_server_file') {
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $uploaded = 0;
        foreach ($_FILES['files']['name'] as $key => $name) {
            if ($_FILES['files']['error'][$key] == 0) {
                $safeName = preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($name));
                $target = $uploadDir . $safeName;
                if (move_uploaded_file($_FILES['files']['tmp_name'][$key], $target)) {
                    $uploaded++;
                }
            }
        }
        if ($uploaded > 0) $response = ['success' => true, 'message' => "$uploaded files uploaded successfully."];
        else $response['message'] = 'Upload failed. Please check folder permissions.';
    }

    // 7. Delete Server File
    elseif ($action == 'delete_server_file') {
        $filename = basename($_POST['filename']);
        $filepath = '../uploads/' . $filename;
        if (file_exists($filepath)) {
            unlink($filepath);
            $response = ['success' => true];
        } else {
            $response['message'] = 'File not found.';
        }
    }

    // 8. Generate Sitemap
    elseif ($action == 'generate_sitemap') {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
        $domain = $protocol . $_SERVER['HTTP_HOST'];
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        $urls = ['/', '/login.php', '/register.php', '/aboutus'];
        
        foreach($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$domain}{$url}</loc>\n";
            $xml .= "    <lastmod>".date('Y-m-d')."</lastmod>\n";
            $xml .= "    <priority>0.8</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        if(file_put_contents('../sitemap.xml', $xml)) {
            $response = ['success' => true, 'message' => 'Sitemap.xml Generated in root folder!'];
        } else {
            $response['message'] = 'Permission denied. Could not create sitemap.xml.';
        }
    }

    // ==========================================
    // SUPPORT LINKS & FAQ LOGIC (FIXED)
    // ==========================================
    
    // Add OR Update Support Link
    elseif ($action == 'add_support_link') {
        $show = isset($_POST['show_in_widget']) ? 1 : 0;
        
        if (!empty($_POST['link_id'])) {
            // UPDATE EXISTING LINK
            $stmt = $pdo->prepare("UPDATE SupportLinks SET title=?, subtitle=?, url=?, icon_class=?, show_in_widget=? WHERE id=?");
            if($stmt->execute([$_POST['title'], $_POST['subtitle'], $_POST['url'], $_POST['icon_class'], $show, $_POST['link_id']])){
                $response = ['success' => true, 'message' => 'Link updated'];
            }
        } else {
            // ADD NEW LINK
            $stmt = $pdo->prepare("INSERT INTO SupportLinks (title, subtitle, url, icon_class, show_in_widget) VALUES (?, ?, ?, ?, ?)");
            if($stmt->execute([$_POST['title'], $_POST['subtitle'], $_POST['url'], $_POST['icon_class'], $show])){
                $response = ['success' => true, 'message' => 'Link added'];
            }
        }
    }
    
    // Add OR Update FAQ
    elseif ($action == 'add_faq') {
        if (!empty($_POST['faq_id'])) {
            // UPDATE EXISTING FAQ
            $stmt = $pdo->prepare("UPDATE FAQs SET category=?, question=?, answer=? WHERE id=?");
            if($stmt->execute([$_POST['category'], $_POST['question'], $_POST['answer'], $_POST['faq_id']])){
                $response = ['success' => true, 'message' => 'FAQ updated'];
            }
        } else {
            // ADD NEW FAQ
            $stmt = $pdo->prepare("INSERT INTO FAQs (category, question, answer) VALUES (?, ?, ?)");
            if($stmt->execute([$_POST['category'], $_POST['question'], $_POST['answer']])){
                $response = ['success' => true, 'message' => 'FAQ added'];
            }
        }
    }
    
    // Delete Support Item
    elseif ($action == 'delete_support_item') {
        $table = $_POST['type'] == 'link' ? 'SupportLinks' : 'FAQs';
        $stmt = $pdo->prepare("DELETE FROM $table WHERE id=?");
        if($stmt->execute([$_POST['id']])){
            $response = ['success' => true, 'message' => 'Item deleted'];
        }
    }
    
    // ==========================================
    // ADVANCED BROADCAST LOGIC (WITH TYPE & BUTTON)
    // ==========================================
    
    // 1. Add / Update Broadcast
    elseif ($action == 'save_broadcast') {
        $title = $_POST['title'] ?? '';
        $message = $_POST['message'] ?? '';
        $target = $_POST['target'] ?? 'all';
        $type = $_POST['type'] ?? 'info';
        $btn_text = $_POST['btn_text'] ?? '';
        $btn_link = $_POST['btn_link'] ?? '';
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d H:i:s');
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d H:i:s', strtotime('+7 days'));
        $status = isset($_POST['status']) ? 1 : 0;
        $broadcast_id = $_POST['broadcast_id'] ?? '';
        
        $imageUrl = $_POST['image_url_text'] ?? '';
        
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $filename = time() . '_b_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", basename($_FILES['image_file']['name']));
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $filename)) {
                $imageUrl = '/uploads/' . $filename; 
            }
        }

        if (!empty($broadcast_id)) {
            $stmt = $pdo->prepare("UPDATE Broadcasts SET title=?, message=?, target=?, type=?, btn_text=?, btn_link=?, image_url=?, start_date=?, end_date=?, status=? WHERE id=?");
            if($stmt->execute([$title, $message, $target, $type, $btn_text, $btn_link, $imageUrl, $start_date, $end_date, $status, $broadcast_id])){
                $response = ['success' => true, 'message' => 'Broadcast Updated'];
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO Broadcasts (title, message, target, type, btn_text, btn_link, image_url, start_date, end_date, status, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if($stmt->execute([$title, $message, $target, $type, $btn_text, $btn_link, $imageUrl, $start_date, $end_date, $status])){
                $response = ['success' => true, 'message' => 'Broadcast Added'];
            }
        }
    }
    
    // 2. Delete Broadcast
    elseif ($action == 'delete_broadcast') {
        $stmt = $pdo->prepare("DELETE FROM Broadcasts WHERE id=?");
        if($stmt->execute([$_POST['id']])){
            $response = ['success' => true, 'message' => 'Broadcast Deleted'];
        }
    }
    
    // Footer Settings Save Logic
if ($action == 'save_footer') {
    $footer_content = $_POST['footer_content'] ?? '';
    $footer_copyright = $_POST['footer_copyright'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE SiteSettings SET footer = ?, footer_copyright = ? LIMIT 1");
    $stmt->execute([$footer_content, $footer_copyright]);
    echo json_encode(['success' => true, 'message' => 'Footer Saved Successfully']);
    exit;
}

} // <--- MAIN IF BLOCK ENDS HERE

echo json_encode($response);
exit;
?>