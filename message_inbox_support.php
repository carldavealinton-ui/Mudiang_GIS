<?php
require_once __DIR__ . '/conversation_support.php';
function complaintInbox(PDO $pdo, int $userId, bool $isStaff, string $search, bool $unreadOnly, int $page): array
{
    $params = [$userId, $userId];
    $where = 'c.user_id IS NOT NULL';
    [$scope,$scopeParams]=complaintAccessScope();$where.=' AND '.$scope;array_push($params,...$scopeParams);
    if ($search !== '') {
        $where .= ' AND (c.complaint_type LIKE ? OR u.full_name LIKE ? OR CAST(c.id AS CHAR) LIKE ?)';
        array_push($params, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
    }
    $base = 'SELECT c.id,c.complaint_type,c.status,c.priority,c.created_at,u.full_name AS resident_name,lm.body AS last_body,lm.user_id AS last_sender_id,lm.created_at AS last_message_at,
        (SELECT COUNT(*) FROM complaint_messages um WHERE um.complaint_id=c.id AND um.user_id<>? AND um.id>COALESCE(r.last_message_id,0)) AS unread
        FROM complaints c JOIN users u ON u.id=c.user_id
        LEFT JOIN complaint_message_reads r ON r.complaint_id=c.id AND r.user_id=?
        LEFT JOIN (SELECT complaint_id,MAX(id) AS last_message_id FROM complaint_messages GROUP BY complaint_id) latest ON latest.complaint_id=c.id
        LEFT JOIN complaint_messages lm ON lm.id=latest.last_message_id
        WHERE ' . $where;
    $filter = $unreadOnly ? ' WHERE inbox.unread>0' : '';
    $query = $pdo->prepare('SELECT COUNT(*) FROM (' . $base . ') inbox' . $filter); $query->execute($params);
    $total = (int) $query->fetchColumn(); $pages = max(1, (int) ceil($total / 30)); $page = min(max(1,$page),$pages); $offset = ($page-1)*30;
    $query = $pdo->prepare('SELECT * FROM (' . $base . ') inbox' . $filter . ' ORDER BY COALESCE(last_message_at,created_at) DESC,id DESC LIMIT 30 OFFSET ' . $offset); $query->execute($params);
    $counts = unreadConversationCounts($pdo,$userId,$isStaff);
    return ['items' => $query->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>$pages,'unread'=>array_sum($counts),'unread_threads'=>count($counts)];
}
