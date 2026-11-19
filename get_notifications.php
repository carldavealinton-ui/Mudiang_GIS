<?php
require_once 'config.php';
require_once __DIR__.'/announcement_notifications.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    echo json_encode(['count' => 0, 'items' => []]);
    exit;
}

notifyResidentAnnouncements($pdo, (int)$_SESSION['user_id']);

$stmt = $pdo->prepare("SELECT n.id, n.message, n.is_read, n.created_at, n.complaint_id, n.event_type
    FROM notifications n WHERE n.user_id = ? ORDER BY n.created_at DESC,n.id DESC LIMIT 20");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$cnt->execute([$_SESSION['user_id']]);

echo json_encode(['count' => (int)$cnt->fetchColumn(), 'items' => $items]);
