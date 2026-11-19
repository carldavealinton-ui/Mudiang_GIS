<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conversation_support.php';
require_once __DIR__ . '/report_activity.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
function conversationFail(int $status, string $message): void { http_response_code($status); echo json_encode(['error' => $message]); exit; }
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['resident', 'admin', 'staff'], true)) conversationFail(403, 'Sign in to access this conversation.');
$userId = (int) $_SESSION['user_id'];
$isStaff = in_array($_SESSION['role'], ['admin', 'staff'], true);
$complaintId = (int) ($_GET['complaint_id'] ?? $_POST['complaint_id'] ?? 0);
$query = $pdo->prepare('SELECT id, user_id, assigned_staff_id, complaint_type FROM complaints WHERE id=?');
$query->execute([$complaintId]);
$complaint = $query->fetch(PDO::FETCH_ASSOC);
if (!$complaint || !canAccessComplaint($complaint)) conversationFail(404, 'Conversation not found.');
if (!$complaint['user_id']) conversationFail(422, 'This report has no registered resident to message.');
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) { header('Allow: GET, POST'); conversationFail(405, 'Unsupported request.'); }
if ($method === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(conversationToken(), $token)) conversationFail(403, 'Your session expired. Refresh the page and try again.');
    $action = $_POST['action'] ?? 'send';
    if ($action === 'read') {
        $through = max(0, (int) ($_POST['through'] ?? 0));
        $query = $pdo->prepare('SELECT COALESCE(MAX(id),0) FROM complaint_messages WHERE complaint_id=? AND id<=?');
        $query->execute([$complaintId, $through]);
        $through = (int) $query->fetchColumn();
        $pdo->beginTransaction();
        $permission=$pdo->prepare('SELECT user_id,assigned_staff_id FROM complaints WHERE id=? FOR UPDATE');$permission->execute([$complaintId]);$current=$permission->fetch(PDO::FETCH_ASSOC);
        if(!$current||!canAccessComplaint($current)){$pdo->rollBack();conversationFail(404,'Conversation not found.');}
        try {
            $query = $pdo->prepare('INSERT INTO complaint_message_reads (complaint_id,user_id,last_message_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE last_message_id=GREATEST(last_message_id,VALUES(last_message_id))');
            $query->execute([$complaintId, $userId, $through]);
            $query = $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND complaint_id=? AND event_type='message' AND complaint_message_id<=?");
            $query->execute([$userId, $complaintId, $through]);
            $pdo->commit(); echo json_encode(['ok' => true]); exit;
        } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log($error->getMessage()); conversationFail(500, 'Unable to update read status.'); }
    }
    if ($action !== 'send') conversationFail(422, 'Unsupported conversation action.');
    $body = is_string($_POST['body'] ?? null) ? trim($_POST['body']) : '';
    $clientToken = is_string($_POST['client_token'] ?? null) ? $_POST['client_token'] : '';
    if ($body === '' || mb_strlen($body) > 2000) conversationFail(422, 'Write a message between 1 and 2,000 characters.');
    if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/', $clientToken)) conversationFail(422, 'Invalid message request. Refresh and try again.');
    try {
        $pdo->beginTransaction();
        // Retrying a request after a network failure must not duplicate the message or its alerts.
        $permission=$pdo->prepare('SELECT user_id,assigned_staff_id FROM complaints WHERE id=? FOR UPDATE');$permission->execute([$complaintId]);$current=$permission->fetch(PDO::FETCH_ASSOC);
        if(!$current||!canAccessComplaint($current)){$pdo->rollBack();conversationFail(404,'Conversation not found.');}
        $query = $pdo->prepare('INSERT INTO complaint_messages (complaint_id,user_id,body,client_token) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $query->execute([$complaintId, $userId, $body, $clientToken]);
        $inserted = $query->rowCount() === 1;
        $messageId = (int) $pdo->lastInsertId();
        $query = $pdo->prepare('SELECT complaint_id,body FROM complaint_messages WHERE id=?'); $query->execute([$messageId]); $saved = $query->fetch(PDO::FETCH_ASSOC);
        if ((int) $saved['complaint_id'] !== $complaintId || $saved['body'] !== $body) { $pdo->rollBack(); conversationFail(409, 'This request was already used for a different message.'); }
        if ($inserted) {
            if ($isStaff) recordReportActivity($pdo,$complaintId,'message','Sent a message: ' . mb_substr($body,0,300),$messageId);
            $nameQuery = $pdo->prepare('SELECT full_name FROM users WHERE id=?'); $nameQuery->execute([$userId]);
            $name = $nameQuery->fetchColumn() ?: ($isStaff ? 'Barangay staff' : 'Resident');
            $notice = mb_substr($name . ' sent a message about report #' . $complaintId . ': ' . $body, 0, 255);
            if ($isStaff) {
                $query = $pdo->prepare("INSERT INTO notifications (user_id,complaint_id,message,event_type,complaint_message_id) VALUES (?,?,?,'message',?)");
                $query->execute([$complaint['user_id'], $complaintId, $notice, $messageId]);
            } else {
                $query = $pdo->prepare("INSERT INTO notifications (user_id,complaint_id,message,event_type,complaint_message_id) SELECT id,?,?,'message',? FROM users WHERE (role='admin' OR (role='staff' AND id=?)) AND is_active=1 AND id<>?");
                $query->execute([$complaintId, $notice, $messageId, $current['assigned_staff_id'], $userId]);
            }
        }
        $pdo->commit(); echo json_encode(['ok' => true, 'id' => $messageId]); exit;
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('Conversation send failed: ' . $error->getMessage()); conversationFail(500, 'Your message could not be saved. Please try again.'); }
}
$after = max(0, (int) ($_GET['after'] ?? 0));
$before = max(0, (int) ($_GET['before'] ?? 0));
$where = $before ? ' AND m.id<?' : ($after ? ' AND m.id>?' : '');
$order = $after && !$before ? 'ASC' : 'DESC';
$query = $pdo->prepare('SELECT m.id,m.user_id AS sender_id,m.body,m.created_at,u.full_name AS sender_name,u.role AS sender_role FROM complaint_messages m JOIN users u ON u.id=m.user_id WHERE m.complaint_id=?' . $where . ' ORDER BY m.id ' . $order . ' LIMIT 100');
$query->execute($where ? [$complaintId, $before ?: $after] : [$complaintId]);
$items = $query->fetchAll(PDO::FETCH_ASSOC);
if ($order === 'DESC') $items = array_reverse($items);
$oldest = $items ? (int) $items[0]['id'] : 0;
$query = $pdo->prepare('SELECT COUNT(*) FROM complaint_messages WHERE complaint_id=? AND id<?'); $query->execute([$complaintId, $oldest]);
echo json_encode(['items' => $items, 'has_older' => (int) $query->fetchColumn() > 0, 'has_more' => $order === 'ASC' && count($items) === 100]);
