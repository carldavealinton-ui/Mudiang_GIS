<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/conversation_support.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
if (($_SESSION['role'] ?? '') !== 'resident' || !isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error'=>'Resident access required.']); exit;
}
$user=(int)$_SESSION['user_id'];
$after=max(0,(int)($_GET['after']??0));
$query=$pdo->prepare("SELECT m.id,m.complaint_id,m.body,u.full_name AS sender_name
    FROM complaint_messages m JOIN complaints c ON c.id=m.complaint_id
    JOIN users u ON u.id=m.user_id
    LEFT JOIN complaint_message_reads r ON r.complaint_id=c.id AND r.user_id=?
    WHERE c.user_id=? AND u.role IN ('admin','staff')
        AND m.id>COALESCE(r.last_message_id,0) AND m.id>?
    ORDER BY m.id DESC LIMIT 3");
$query->execute([$user,$user,$after]);
$items=$query->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['count'=>array_sum(unreadConversationCounts($pdo,$user,false)),
    'items'=>$items,'cursor'=>max(array_merge([$after],array_map(static fn($item)=>(int)$item['id'],$items)))]);
