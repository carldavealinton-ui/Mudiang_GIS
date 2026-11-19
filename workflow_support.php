<?php
require_once __DIR__ . '/report_activity.php';
function reportVersion(array $c): string {
    return hash('sha256', json_encode([$c['status'],$c['priority'],$c['assigned_to'],$c['resolution_notes'],$c['assigned_staff_id'],$c['due_date'],$c['resident_confirmed_at'],$c['reopen_count']]));
}
function workflowNotice(PDO $pdo, int $user, int $report, string $message): void {
    $q=$pdo->prepare("INSERT INTO notifications(user_id,complaint_id,message,event_type) VALUES(?,?,?,'status')");$q->execute([$user,$report,mb_substr($message,0,255)]);
}
function renderResidentTimeline(PDO $pdo, array $c, bool $readOnly = false): void {
    require_once __DIR__.'/resident_features.php';
    $q=$pdo->prepare("SELECT actor_name,event_type,summary,created_at FROM complaint_activity WHERE complaint_id=? AND event_type IN ('filed','status','remark','assignment','deadline','confirmation','reopen') ORDER BY id DESC LIMIT 100");$q->execute([$c['id']]);
    echo '<section class="card workflow-timeline"><div class="card-header"><h2>Your complaint timeline</h2></div><div class="card-body"><ol class="workflow-stages" aria-label="Complaint stages">';
    $stages=['filed','acknowledged','processing','resolved'];$index=array_search($c['status'],$stages,true);
    foreach($stages as $i=>$stage)echo '<li class="'.($i<=$index?'complete':'').'"'.($i===$index?' aria-current="step"':'').'>'.ucfirst($stage).'</li>';
    echo '</ol><p>Target completion: <strong>'.reportActivityEscape($c['due_date']?:'Awaiting scheduling').'</strong></p>';
    echo '<p class="resident-next-step"><strong>Next step:</strong> '.residentEscape(residentNextStep($c['status'])).'</p>';
    $support=$pdo->prepare('SELECT COUNT(*) FROM complaint_supporters WHERE complaint_id=?');$support->execute([$c['id']]);
    echo '<p>Other residents affected: <strong>'.(int)$support->fetchColumn().'</strong></p>';
    if($c['status']==='resolved') {
        echo '<h3>Resolution details</h3><p>'.nl2br(reportActivityEscape($c['resolution_notes'] ?: 'No closing notes were recorded for this historical report.')).'</p>';
        if($c['resident_confirmed_at'])echo '<p class="alert alert-success">You confirmed this resolution on '.reportActivityEscape($c['resident_confirmed_at']).'.</p>';
        elseif (!$readOnly) echo '<form method="post" action="resolution_feedback.php"><input type="hidden" name="complaint_id" value="'.(int)$c['id'].'"><input type="hidden" name="version" value="'.reportVersion($c).'"><input type="hidden" name="csrf_token" value="'.reportActivityEscape($_SESSION['workflow_csrf']).'"><label for="reopen-reason">If the concern remains unresolved, tell us why</label><textarea class="form-control mb-3" id="reopen-reason" name="reason" maxlength="2000"></textarea><div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" name="action" value="confirm">Confirm resolution</button><button class="btn btn-outline" name="action" value="reopen">Request reopening</button></div></form>';
    }
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $item)echo '<article class="report-activity-item"><div><strong>'.reportActivityEscape($item['actor_name']).'</strong><p>'.nl2br(reportActivityEscape($item['summary'])).'</p><time>'.reportActivityEscape($item['created_at']).'</time></div></article>';
    echo '</div></section>';
}
