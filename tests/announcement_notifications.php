<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require __DIR__.'/../config.php';
require __DIR__.'/../announcement_notifications.php';
function checkAnnouncement(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS: $label\n";
}
$pdo->beginTransaction();
try {
    $suffix=bin2hex(random_bytes(6));
    $q=$pdo->prepare("INSERT INTO users(username,password,full_name,email,role) VALUES(?,?,?,?, 'resident')");
    $q->execute(['announcement_'.$suffix,password_hash('TestPassword123',PASSWORD_DEFAULT),'Announcement test','announcement_'.$suffix.'@gmail.com']);
    $resident=(int)$pdo->lastInsertId();
    $q=$pdo->prepare("INSERT INTO community_announcements(title,body,category,published_at,is_published,created_by) VALUES(?,'Test body','General',DATE_SUB(NOW(),INTERVAL 1 MINUTE),1,?)");
    $q->execute(['Test announcement '.$suffix,$resident]);
    $announcement=(int)$pdo->lastInsertId();
    $count=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND event_key=? AND event_type='announcement' AND complaint_id IS NULL AND is_read=0");
    $readCount=static function() use ($count,$resident,$announcement): int {$count->execute([$resident,'announcement:'.$announcement]);return (int)$count->fetchColumn();};
    $pdo->exec('UPDATE community_announcements SET is_published=0 WHERE id='.$announcement);
    notifyResidentAnnouncements($pdo,$resident);
    checkAnnouncement($readCount()===0,'Draft does not notify residents');
    $pdo->exec('UPDATE community_announcements SET is_published=1,published_at=DATE_ADD(NOW(),INTERVAL 1 DAY) WHERE id='.$announcement);
    notifyResidentAnnouncements($pdo,$resident);
    checkAnnouncement($readCount()===0,'Scheduled announcement waits for publication');
    $pdo->exec('UPDATE community_announcements SET published_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id='.$announcement);
    notifyResidentAnnouncements($pdo,$resident);
    checkAnnouncement($readCount()===1,'Published announcement creates an unread resident notification');
    notifyResidentAnnouncements($pdo,$resident);
    checkAnnouncement($readCount()===1,'Repeated polling does not duplicate notifications');
} finally {
    $pdo->rollBack();
}
