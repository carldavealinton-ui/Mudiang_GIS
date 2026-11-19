<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    echo json_encode(['ok' => false]);
    exit;
}

$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
    ->execute([$_SESSION['user_id']]);

echo json_encode(['ok' => true]);
