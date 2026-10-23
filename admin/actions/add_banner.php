<?php
// ============================================================
// ADD BANNER ACTION
// ============================================================
$title = trim($_POST['title'] ?? '');
$subtitle = trim($_POST['subtitle'] ?? '');
$image_url = trim($_POST['image_url'] ?? '');
$link_url = trim($_POST['link_url'] ?? '');
$position = $_POST['position'] ?? 'home_top';
$status = $_POST['status'] ?? 1;
$order = $_POST['order'] ?? 0;

if (empty($title) || empty($image_url)) {
    echo json_encode(['success' => false, 'message' => 'Title and image URL required']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO Banners (title, subtitle, image_url, link_url, position, status, `order`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $subtitle, $image_url, $link_url, $position, $status, $order]);
    echo json_encode(['success' => true, 'message' => 'Banner added successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>