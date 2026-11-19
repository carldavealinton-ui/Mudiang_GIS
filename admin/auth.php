<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true)) {
    http_response_code(403);
    exit('Administrator or staff access required.');
}
if($_SESSION['role']==='staff'&&in_array(basename($_SERVER['SCRIPT_NAME']??''),['residents.php','annual_report.php','operations.php','staff.php','account_access.php'],true)){http_response_code(403);exit('This page is reserved for administrators.');}
if (empty($_SESSION['admin_notification_csrf'])) {
    $_SESSION['admin_notification_csrf'] = bin2hex(random_bytes(32));
}
// Generate at most one overdue alert per viewer and report per day.
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'notifications.php' || ($_SESSION['overdue_checked_at'] ?? 0) < time()-300) {
    $overdue=$pdo->prepare("INSERT IGNORE INTO notifications(user_id,complaint_id,message,event_type,event_key)
        SELECT ?,c.id,CONCAT('Overdue report #',c.id,': target ',c.due_date,' (',CURDATE(),')'),'status',CONCAT('overdue:',c.id,':',CURDATE())
        FROM complaints c WHERE c.due_date<CURDATE() AND c.status<>'resolved'
        AND (?='admin' OR c.assigned_staff_id=?)
        AND NOT EXISTS(SELECT 1 FROM notifications n WHERE n.user_id=? AND n.complaint_id=c.id AND n.message=CONCAT('Overdue report #',c.id,': target ',c.due_date,' (',CURDATE(),')'))");
    $overdue->execute([$_SESSION['user_id'],$_SESSION['role'],$_SESSION['user_id'],$_SESSION['user_id']]);
    $_SESSION['overdue_checked_at']=time();
}
