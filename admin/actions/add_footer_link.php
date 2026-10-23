<?php
// ============================================================
// ADD FOOTER LINK ACTION
// ============================================================
$title = trim($_POST['title'] ?? '');
$url = trim($_POST['url'] ?? '');

if (empty($title) || empty($url)) {
    echo json_encode(['success' => false, 'message' => 'Title and URL required']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO FooterLinks (title, url) VALUES (?, ?)");
    $stmt->execute([$title, $url]);
    echo json_encode(['success' => true, 'message' => 'Footer link added successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>