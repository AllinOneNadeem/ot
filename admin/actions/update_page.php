<?php
// ============================================================
// UPDATE PAGE ACTION (UPDATED WITH SEO META DATA)
// ============================================================
$id = $_POST['page_id'] ?? 0;
$title = trim($_POST['title'] ?? '');
$slug = trim($_POST['slug'] ?? '');
$content = $_POST['content'] ?? '';
$status = $_POST['status'] ?? 1;

// SEO Meta Inputs
$meta_description = trim($_POST['meta_description'] ?? '');
$meta_keywords = trim($_POST['meta_keywords'] ?? '');

if (empty($title) || empty($slug) || empty($content)) {
    echo json_encode(['success' => false, 'message' => 'All fields required']);
    exit();
}

$slug = preg_replace('/[^a-z0-9-]/', '-', strtolower(str_replace(' ', '-', $slug)));
$slug = preg_replace('/-+/', '-', $slug);

try {
    $stmt = $pdo->prepare("UPDATE Pages SET slug = ?, title = ?, meta_description = ?, meta_keywords = ?, content = ?, status = ? WHERE id = ?");
    $stmt->execute([$slug, $title, $meta_description, $meta_keywords, $content, $status, $id]);
    echo json_encode(['success' => true, 'message' => 'Page updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>