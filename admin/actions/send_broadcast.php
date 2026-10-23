<?php
// ============================================================
// SEND BROADCAST ACTION
// ============================================================
$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');
$target = $_POST['target'] ?? 'all';

if (empty($title) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Title and message required']);
    exit();
}

try {
    $pdo->query("CREATE TABLE IF NOT EXISTS `Broadcasts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `message` text NOT NULL,
        `target` varchar(50) DEFAULT 'all',
        `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("INSERT INTO Broadcasts (title, message, target, sent_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$title, $message, $target]);
    echo json_encode(['success' => true, 'message' => 'Broadcast sent successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>