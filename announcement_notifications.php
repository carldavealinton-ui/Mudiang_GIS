<?php
/** Notify each active resident once when an announcement becomes visible. */
function notifyResidentAnnouncements(PDO $pdo, ?int $residentId = null): void
{
    $sql = "INSERT INTO notifications (user_id, complaint_id, message, event_type, event_key)
        SELECT u.id, NULL, LEFT(CONCAT('New barangay announcement: ', a.title), 255),
            'announcement', CONCAT('announcement:', a.id)
        FROM users u CROSS JOIN community_announcements a
        WHERE u.role='resident' AND u.is_active=1 AND a.is_published=1
            AND a.published_at<=NOW() AND (a.expires_at IS NULL OR a.expires_at>NOW())";
    if ($residentId !== null) $sql .= ' AND u.id=?';
    $sql .= ' ON DUPLICATE KEY UPDATE id=notifications.id';
    $query = $pdo->prepare($sql);
    $query->execute($residentId === null ? [] : [$residentId]);
}
