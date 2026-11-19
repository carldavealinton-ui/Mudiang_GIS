<?php
function reportActivityEscape($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function recordReportActivity(PDO $pdo,int $complaintId,string $type,string $summary,?int $messageId=null): void
{
 $actorId=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null;
 $actorName='Guest resident';
 if($actorId){$q=$pdo->prepare('SELECT full_name,username FROM users WHERE id=?');$q->execute([$actorId]);$actor=$q->fetch(PDO::FETCH_ASSOC);$actorName=$actor?($actor['full_name'].' (@'.$actor['username'].')'):'Former account';}
 $q=$pdo->prepare('INSERT INTO complaint_activity(complaint_id,actor_id,actor_name,event_type,summary,source_message_id) VALUES(?,?,?,?,?,?)');
 $q->execute([$complaintId,$actorId,mb_substr($actorName,0,200),$type,$summary,$messageId]);
}
function renderReportActivity(PDO $pdo,int $complaintId): void
{
 $q=$pdo->prepare('SELECT * FROM complaint_activity WHERE complaint_id=? ORDER BY created_at DESC,id DESC LIMIT 100');$q->execute([$complaintId]);$items=$q->fetchAll(PDO::FETCH_ASSOC);
 echo '<section class="card report-activity-card" id="report-activity"><div class="card-header"><div><span class="portal-kicker">Accountability / Report history</span><h2><i class="fas fa-history" aria-hidden="true"></i> Report activity</h2></div></div><div class="report-activity-list">';
 foreach($items as $item){echo '<article class="report-activity-item"><span class="activity-dot" aria-hidden="true"></span><div><strong>'.reportActivityEscape($item['actor_name']).'</strong><span class="activity-kind">'.reportActivityEscape(ucfirst($item['event_type'])).'</span><p>'.nl2br(reportActivityEscape($item['summary'])).'</p><time>'.reportActivityEscape($item['created_at']).'</time></div></article>';}
 if(!$items)echo '<p class="activity-empty">Updates, remarks, and staff messages will appear here with their author and date.</p>';
 echo '</div></section>';
}
function renderResidentRemarks(PDO $pdo,int $complaintId): void
{
 $q=$pdo->prepare("SELECT actor_name,summary,created_at FROM complaint_activity WHERE complaint_id=? AND event_type='remark' ORDER BY id DESC LIMIT 30");$q->execute([$complaintId]);$items=$q->fetchAll(PDO::FETCH_ASSOC);if(!$items)return;
 echo '<section class="resident-remarks"><h3>Staff remarks</h3>';
 foreach($items as $item)echo '<article><strong>'.reportActivityEscape($item['actor_name']).'</strong><p>'.nl2br(reportActivityEscape($item['summary'])).'</p><time>'.reportActivityEscape($item['created_at']).'</time></article>';
 echo '</section>';
}
