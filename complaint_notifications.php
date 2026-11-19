<?php
/** Call inside the complaint transaction so the report and alerts are committed together. */
function notifyComplaintAdministrators(PDO $pdo, int $complaintId): void
{
    $query = $pdo->prepare('SELECT complainant_name, complaint_type, priority FROM complaints WHERE id = ?');
    $query->execute([$complaintId]);
    $complaint = $query->fetch(PDO::FETCH_ASSOC);
    $message = mb_substr($complaint['complainant_name'] . ' submitted a new complaint: ' . $complaint['complaint_type'] . ' (' . ucfirst($complaint['priority']) . ' priority).', 0, 255);
    $query = $pdo->prepare("INSERT INTO notifications (user_id, complaint_id, message) SELECT id, ?, ? FROM users WHERE role='admin' AND is_active=1");
    $query->execute([$complaintId, $message]);
}
