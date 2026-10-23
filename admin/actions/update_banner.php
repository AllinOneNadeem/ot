<?php
// ============================================================
// UPDATE BANNER ACTION
// ============================================================
$id = $_POST['banner_id'] ?? 0;
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
    $stmt = $pdo->prepare("UPDATE Banners SET title = ?, subtitle = ?, image_url = ?, link_url = ?, position = ?, status = ?, `order` = ? WHERE id = ?");
    $stmt->execute([$title, $subtitle, $image_url, $link_url, $position, $status, $order, $id]);
    echo json_encode(['success' => true, 'message' => 'Banner updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>