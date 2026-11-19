<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/message_inbox_support.php';
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['resident','admin','staff'],true)) { http_response_code(403); echo json_encode(['error'=>'Sign in to view messages.']); exit; }
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']),0,100) : '';
echo json_encode(complaintInbox($pdo,(int)$_SESSION['user_id'],$_SESSION['role']!=='resident',$search,($_GET['filter']??'')==='unread',(int)($_GET['page']??1)));
